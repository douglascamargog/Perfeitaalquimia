<?php
namespace App\Services\Nota10;

use App\Core\Auditoria;
use App\Core\Banco;
use App\Core\Config;
use App\Models\Listas;
use App\Services\CarteiraService;
use App\Services\EntregaService;
use App\Services\Normalizar;

/**
 * Importação de verdade do Nota10 → One (V17.0): das tabelas n10_* (o backup lido) para as tabelas do One.
 * Regras completas: docs/nota10/regras-importacao.md (e o diagnóstico em docs/nota10/diagnostico.md).
 *
 * - Em ETAPAS e em PARTES: cada chamada trabalha alguns segundos e guarda até onde chegou (limite de tempo da HostGator).
 * - Pode rodar de novo sem duplicar: cada registro guarda o código do Nota10 (nota10_numero, nota10_guid, nota10_codigo).
 * - Nada se perde: o que não tem tela no One continua nas tabelas n10_*, que nunca são apagadas.
 * - Compatível com MySQL 5.7 (sem WITH nem funções de janela).
 */
class Importador
{
    /** Etapas, na ordem: [chave, texto para a tela] */
    public const ETAPAS = [
        'preparar' => 'Preparando (índices e ligações internas)',
        'funcionarios' => 'Criando os funcionários que faltam',
        'produtos' => 'Produtos, matérias-primas, preços e estoque',
        'cadastros' => 'Clientes, leads, fornecedores e transportadoras',
        'pedidos' => 'Pedidos e notas',
        'itens' => 'Itens dos pedidos (com as bonificações juntadas)',
        'ligar' => 'Ligando as NFs de "Outras saídas" e as bonificações',
        'titulos' => 'Contas a receber',
        'cobranca' => 'Casos de cobrança (títulos vencidos)',
        'linha_tempo' => 'Follow-up e eventos das NF-e',
        'compras' => 'Compras de fornecedores',
        'recalcular' => 'Recalculando última compra, recompra, crédito e carteiras',
    ];

    private const LOTE = 2000;        // linhas por vez (cadastros e pedidos)
    private const FAIXA = 25000;      // ids por consulta (itens e títulos)

    private static array $cache = [];

    // ------------------------------------------------------------------ andamento

    /** Começa (ou recomeça) a importação do backup já lido */
    public static function iniciar(int $impId, int $usuarioId): void
    {
        // backup completo do One ANTES (fica em storage/backups, como o do "Zerar dados")
        $pasta = RAIZ . '/storage/backups';
        if (!is_dir($pasta)) {
            mkdir($pasta, 0750, true);
        }
        $backup = $pasta . '/one-antes-de-importar-nota10-' . date('Y-m-d-His') . '.sql.gz';
        rename(\App\Services\BackupBanco::gerar(), $backup);
        Banco::atualizar('nota10_importacoes', $impId, ['etapa' => 'preparar', 'cursor_id' => 0, 'importado_em' => null,
            'relatorio' => json_encode(['inicio' => date('c'), 'backup' => basename($backup)])]);
        Auditoria::registrar('configuracoes', $impId, 'nota10_importacao_iniciada', null, ['usuario' => $usuarioId]);
    }

    /** Roda mais uma parte. Devolve o andamento: [etapa, rotulo, pct, fim, relatorio] */
    public static function parte(int $impId, float $tempo = 8.0): array
    {
        $imp = Banco::um('SELECT * FROM nota10_importacoes WHERE id = ?', [$impId]);
        if (!$imp || !$imp['etapa']) {
            return ['erro' => 'A importação não foi iniciada.'];
        }
        @set_time_limit(300);
        Banco::pdo()->exec("SET SESSION sql_mode = ''");
        $rel = json_decode((string) $imp['relatorio'], true) ?: [];
        $etapa = $imp['etapa'];
        $cursor = (int) $imp['cursor_id'];
        $inicio = microtime(true);
        $chaves = array_keys(self::ETAPAS);
        while ($etapa !== 'fim' && microtime(true) - $inicio < $tempo) {
            $novo = self::{'etapa' . ucfirst(str_replace('_', '', ucwords($etapa, '_')))}($cursor, $rel);
            if ($novo === null) { // etapa terminou
                $i = array_search($etapa, $chaves, true);
                $etapa = $chaves[$i + 1] ?? 'fim';
                $cursor = 0;
            } else {
                $cursor = $novo;
            }
            Banco::atualizar('nota10_importacoes', $impId, ['etapa' => $etapa, 'cursor_id' => $cursor, 'relatorio' => json_encode($rel, JSON_UNESCAPED_UNICODE)]);
        }
        if ($etapa === 'fim' && !$imp['importado_em']) {
            $rel['fim'] = date('c');
            Banco::atualizar('nota10_importacoes', $impId, ['importado_em' => date('Y-m-d H:i:s'), 'relatorio' => json_encode($rel, JSON_UNESCAPED_UNICODE)]);
            Auditoria::registrar('configuracoes', $impId, 'nota10_importacao_concluida', null, $rel);
        }
        $pos = $etapa === 'fim' ? count($chaves) : array_search($etapa, $chaves, true);
        return ['etapa' => $etapa, 'rotulo' => self::ETAPAS[$etapa] ?? 'Pronto', 'pct' => round($pos / count($chaves) * 100, 1),
            'fim' => $etapa === 'fim', 'relatorio' => $rel];
    }

    /**
     * Linhas de uma consulta com as chaves SEMPRE em minúsculas: o nome da coluna vem com a grafia do backup
     * (o real usa "numero", "tipooperacao"...; outro Nota10 pode usar "Numero", "TipoOperacao"). O SQL não diferencia; o PHP sim.
     */
    private static function linhas(string $sql, array $parametros = []): array
    {
        return array_map(fn($r) => array_change_key_case($r, CASE_LOWER), Banco::todos($sql, $parametros));
    }

    private static function somar(array &$rel, string $chave, int|float $n = 1): void
    {
        $rel[$chave] = ($rel[$chave] ?? 0) + $n;
    }

    // ------------------------------------------------------------------ apoio

    /** Data guardada como texto no Nota10 → 'aaaa-mm-dd' em SQL; ano fora de 2000–2035 vira NULL */
    public static function data(string $expr): string
    {
        $d = "COALESCE(STR_TO_DATE(LEFT(NULLIF(TRIM($expr), ''), 10), '%Y-%m-%d'), STR_TO_DATE(LEFT(NULLIF(TRIM($expr), ''), 10), '%d/%m/%Y'),"
            . " STR_TO_DATE(LEFT(NULLIF(TRIM($expr), ''), 10), '%Y/%m/%d'))";
        return "(CASE WHEN YEAR($d) BETWEEN 2000 AND 2035 THEN $d END)";
    }

    /** O mesmo em PHP */
    public static function dataPhp(?string $v): ?string
    {
        $v = trim((string) $v);
        foreach (['/^(\d{4})-(\d{2})-(\d{2})/' => [1, 2, 3], '/^(\d{2})\/(\d{2})\/(\d{4})/' => [3, 2, 1], '/^(\d{4})\/(\d{2})\/(\d{2})/' => [1, 2, 3]] as $re => [$a, $m, $d]) {
            if (preg_match($re, $v, $x) && checkdate((int) $x[$m], (int) $x[$d], (int) $x[$a]) && $x[$a] >= 2000 && $x[$a] <= 2035) {
                return sprintf('%04d-%02d-%02d', $x[$a], $x[$m], $x[$d]);
            }
        }
        return null;
    }

    private static function digitos(?string $v): string
    {
        return preg_replace('/\D/', '', (string) $v);
    }

    /** CNPJ da empresa principal (Perfeita Alquimia) */
    public static function principal(): string
    {
        return self::digitos((string) Config::get('empresa.cnpj_principal', '15652740000180'));
    }

    private static function mapas(): array
    {
        return self::$cache['mapas'] ??= Conferencia::mapas();
    }

    /** Pessoa do One para um código de vendedor do Nota10 (só funcionário ativo) */
    private static function vendedor($codigo): ?int
    {
        $codigo = (string) (int) $codigo;
        if ($codigo === '0') {
            return null;
        }
        if (!array_key_exists($codigo, self::$cache['vend'] ?? [])) {
            $destino = self::mapas()['vendedor'][$codigo] ?? null;
            if ($destino === null && !array_key_exists($codigo, self::mapas()['vendedor'] ?? [])) {
                $destino = Banco::valor("SELECT id FROM usuarios WHERE codigo_vendedor = ? AND ativo = 1", [$codigo]); // sem ligação: pelo código
            }
            $ok = $destino && ctype_digit((string) $destino) && Banco::valor('SELECT 1 FROM usuarios WHERE id = ? AND ativo = 1', [$destino]);
            self::$cache['vend'][$codigo] = $ok ? (int) $destino : null;
        }
        return self::$cache['vend'][$codigo];
    }

    private static function tipoCadastro(?string $tipo, int $numero): string
    {
        $t = trim((string) $tipo) === '' ? '(vazio)' : trim((string) $tipo);
        if (isset(self::$cache['internos'][$numero])) {
            return 'vendedor'; // cadastro interno usado como carteira de outros (não é cliente)
        }
        return self::mapas()['tipo_cadastro'][$t] ?? Conferencia::palpiteTipo($t);
    }

    private static function etapaId(string $nome): ?int
    {
        return self::$cache['etapa'][$nome] ??= ((int) Banco::valor('SELECT id FROM funil_etapas WHERE nome = ? LIMIT 1', [$nome]) ?: null);
    }

    private static function formaId($codigo): int
    {
        $c = (string) (int) $codigo;
        if (!isset(self::$cache['forma'][$c])) {
            $d = self::mapas()['forma'][$c] ?? null;
            if (!$d || !ctype_digit((string) $d)) {
                $nome = mb_strtolower((string) Banco::valor('SELECT Nome FROM n10_formapgto WHERE Codigo = ? LIMIT 1', [$c]));
                $slug = str_contains($nome, 'pix') ? 'pix' : (str_contains($nome, 'cart') ? 'cartao' : 'boleto');
                $d = Banco::valor('SELECT id FROM formas_pagamento WHERE slug = ?', [$slug]) ?: Banco::valor('SELECT id FROM formas_pagamento ORDER BY id LIMIT 1');
            }
            self::$cache['forma'][$c] = (int) $d;
        }
        return self::$cache['forma'][$c];
    }

    private static function tabelaLojista(): int
    {
        return self::$cache['lojista'] ??= (int) (Banco::valor("SELECT id FROM tabelas_preco WHERE slug = 'lojista'") ?: Banco::valor('SELECT id FROM tabelas_preco ORDER BY id LIMIT 1'));
    }

    /** INSERT de várias linhas de uma vez */
    private static function inserirLote(string $tabela, array $linhas): void
    {
        if (!$linhas) {
            return;
        }
        $colunas = array_keys($linhas[0]);
        $marcas = '(' . implode(',', array_fill(0, count($colunas), '?')) . ')';
        $valores = [];
        foreach ($linhas as $l) {
            foreach ($colunas as $c) {
                $valores[] = $l[$c] ?? null;
            }
        }
        Banco::executar("INSERT INTO `$tabela` (`" . implode('`,`', $colunas) . '`) VALUES ' . implode(',', array_fill(0, count($linhas), $marcas)), $valores);
    }

    private static function indice(string $tabela, string $coluna, int $tam = 0): void
    {
        try {
            Banco::pdo()->exec("ALTER TABLE `$tabela` ADD INDEX `ix_$coluna` (`$coluna`" . ($tam ? "($tam)" : '') . ')');
        } catch (\PDOException) {
            // já existe (ou a coluna não existe neste backup)
        }
    }

    private static function maxId(string $tabela): int
    {
        return Conferencia::existe(substr($tabela, 4)) ? (int) Banco::valor("SELECT COALESCE(MAX(id), 0) FROM `$tabela`") : 0;
    }

    // ------------------------------------------------------------------ 1. preparar

    private static function etapaPreparar(int $cursor, array &$rel): ?int
    {
        foreach (['n10_notas' => ['Pedido', 'TipoOperacao', 'NumeroCliente', 'GUID_Nota'], 'n10_itensnota' => ['GUID_Nota', 'GUID_Item'],
                  'n10_contaspagrec' => ['ChaveGUID', 'CredorDevedor', 'GUID_Nota'], 'n10_clientes' => ['Numero', 'Representante'],
                  'n10_produtos' => ['CodigoProduto'], 'n10_eventosnfe' => ['GUID_NOTA']] as $t => $cols) {
            foreach ($cols as $c) {
                self::indice($t, $c);
            }
        }
        $pdo = Banco::pdo();
        // número do cadastro no Nota10 → cliente do One (inclui os juntados pelo CNPJ)
        $pdo->exec('CREATE TABLE IF NOT EXISTS n10_x_cliente (numero INT PRIMARY KEY, cliente_id INT NOT NULL, KEY (cliente_id)) ENGINE=InnoDB');
        $pdo->exec('DELETE m FROM n10_x_cliente m LEFT JOIN clientes c ON c.id = m.cliente_id WHERE c.id IS NULL'); // depois de um "Zerar dados"
        // um backup novo apaga a área n10_ (inclusive este mapa): refaz pelo que já está no One, para não duplicar quem não tem CNPJ/CPF
        $pdo->exec('INSERT IGNORE INTO n10_x_cliente (numero, cliente_id) SELECT nota10_numero, MIN(id) FROM clientes
            WHERE nota10_numero IS NOT NULL AND excluido_em IS NULL GROUP BY nota10_numero');
        // última venda de cada cadastro (para a regra do responsável)
        $data = self::data('n.Data');
        $pdo->exec('DROP TABLE IF EXISTS n10_x_ultima_venda');
        $pdo->exec("CREATE TABLE n10_x_ultima_venda ENGINE=InnoDB AS
            SELECT n.NumeroCliente AS numero, SUBSTRING_INDEX(MAX(CONCAT(COALESCE($data, '2000-01-01'), LPAD(n.id, 12, '0'), '|', COALESCE(n.CodigoVendedor, 0))), '|', -1) + 0 AS vendedor,
                   MAX($data) AS ultima
            FROM n10_notas n WHERE n.TipoOperacao = 'V' AND COALESCE(n.TotalNota, 0) > 0 AND COALESCE(n.PedidoCancelado, 0) = 0 AND COALESCE(n.NumeroCliente, 0) > 0
              AND UPPER(COALESCE(n.Status_NF, '')) <> 'C'
            GROUP BY n.NumeroCliente");
        $pdo->exec('ALTER TABLE n10_x_ultima_venda ADD PRIMARY KEY (numero)');
        // pedido "gêmeo" de bonificação: V com total 0 e o mesmo número de pedido (e emitente) de um V com total > 0
        $pdo->exec('DROP TABLE IF EXISTS n10_x_gemeo');
        $pdo->exec("CREATE TABLE n10_x_gemeo ENGINE=InnoDB AS
            SELECT g.GUID_Nota AS guid, (SELECT p.GUID_Nota FROM n10_notas p WHERE p.TipoOperacao = 'V' AND COALESCE(p.TotalNota, 0) > 0
                     AND p.Pedido = g.Pedido AND COALESCE(p.Emitente, '') = COALESCE(g.Emitente, '') ORDER BY p.id LIMIT 1) AS principal,
                   g.Nota_S AS nf, g.Status_NF AS status_nf
            FROM n10_notas g WHERE g.TipoOperacao = 'V' AND COALESCE(g.TotalNota, 0) = 0 AND COALESCE(g.Pedido, '') NOT IN ('', '0')");
        $pdo->exec('DELETE FROM n10_x_gemeo WHERE principal IS NULL');
        $pdo->exec('ALTER TABLE n10_x_gemeo ADD PRIMARY KEY (guid), ADD KEY (principal)');
        // quantos itens cada nota tem (para achar o par das "Outras saídas")
        $pdo->exec('DROP TABLE IF EXISTS n10_x_itens');
        $pdo->exec('CREATE TABLE n10_x_itens ENGINE=InnoDB AS SELECT GUID_Nota AS guid, COUNT(*) AS qtd FROM n10_itensnota GROUP BY GUID_Nota');
        $pdo->exec('ALTER TABLE n10_x_itens ADD PRIMARY KEY (guid)');
        // NF de "Outras saídas" (venda) que é a NF de um pedido de venda (conferencia-faturamento.md). Em três passes, do mais forte ao mais fraco:
        //  1) mesma empresa + mesmo nº de pedido + mesmo cliente de um pedido de venda (V com total > 0);
        //  2) idem, mas o pedido é o gêmeo de bonificação (total 0) → liga ao pedido principal dele;
        //  3) regra antiga: mesmo cliente e empresa, mesmo nº de itens, até 3 dias de diferença.
        // O nº de pedido sozinho não basta (há números repetidos entre clientes).
        try {
            $pdo->exec('ALTER TABLE n10_notas ADD INDEX ix_Pedido (Pedido)');
        } catch (\PDOException) {
            // o índice já existe
        }
        $ds = self::data('s.Data');
        $dv = self::data('v.Data');
        $saidas = "FROM n10_notas s JOIN n10_x_itens si ON si.guid = s.GUID_Nota
            WHERE s.TipoOperacao = 'S' AND COALESCE(s.NumeroCliente, 0) > 0 AND SUBSTRING(REPLACE(COALESCE(s.CFOP, ''), '.', ''), 2) IN
                  ('101', '102', '103', '104', '105', '106', '107', '108', '109', '110', '111', '112', '113', '114', '115', '116', '117', '118', '119', '120', '122', '123', '124', '125', '401', '402', '403', '404', '405')";
        $mesmoPedido = "v.Pedido = s.Pedido AND v.NumeroCliente = s.NumeroCliente AND COALESCE(v.Emitente, '') = COALESCE(s.Emitente, '') AND v.TipoOperacao = 'V'";
        $temPedido = "COALESCE(s.Pedido, '') NOT IN ('', '0')";
        $pdo->exec('DROP TABLE IF EXISTS n10_x_par_saida');
        // mesmo tipo (e collation) da coluna do backup
        $pdo->exec("CREATE TABLE n10_x_par_saida ENGINE=InnoDB AS SELECT GUID_Nota AS guid_s, GUID_Nota AS guid_v, 'pedido' AS por FROM n10_notas WHERE 1 = 0");
        $pdo->exec('ALTER TABLE n10_x_par_saida ADD PRIMARY KEY (guid_s), ADD KEY (guid_v)');
        $pdo->exec("INSERT IGNORE INTO n10_x_par_saida (guid_s, guid_v, por)
            SELECT x.guid_s, x.guid_v, 'pedido' FROM (SELECT s.GUID_Nota AS guid_s, (SELECT v.GUID_Nota FROM n10_notas v
                WHERE $mesmoPedido AND COALESCE(v.TotalNota, 0) > 0 ORDER BY v.id LIMIT 1) AS guid_v $saidas AND $temPedido) x WHERE x.guid_v IS NOT NULL");
        $pdo->exec("INSERT IGNORE INTO n10_x_par_saida (guid_s, guid_v, por)
            SELECT x.guid_s, x.guid_v, 'gemeo' FROM (SELECT s.GUID_Nota AS guid_s, (SELECT g.principal FROM n10_notas v JOIN n10_x_gemeo g ON g.guid = v.GUID_Nota
                WHERE $mesmoPedido ORDER BY v.id LIMIT 1) AS guid_v $saidas AND $temPedido
                AND NOT EXISTS (SELECT 1 FROM n10_x_par_saida j WHERE j.guid_s = s.GUID_Nota)) x WHERE x.guid_v IS NOT NULL");
        $pdo->exec("INSERT IGNORE INTO n10_x_par_saida (guid_s, guid_v, por)
            SELECT x.guid_s, x.guid_v, 'data' FROM (SELECT s.GUID_Nota AS guid_s, (SELECT v.GUID_Nota FROM n10_notas v JOIN n10_x_itens iv ON iv.guid = v.GUID_Nota
                    WHERE v.TipoOperacao = 'V' AND v.NumeroCliente = s.NumeroCliente AND COALESCE(v.Emitente, '') = COALESCE(s.Emitente, '')
                      AND iv.qtd = si.qtd AND ABS(DATEDIFF($dv, $ds)) <= 3 AND COALESCE(v.TotalNota, 0) > 0
                    ORDER BY ABS(DATEDIFF($dv, $ds)), v.id LIMIT 1) AS guid_v $saidas
                AND NOT EXISTS (SELECT 1 FROM n10_x_par_saida j WHERE j.guid_s = s.GUID_Nota)) x WHERE x.guid_v IS NOT NULL");
        $rel['gemeos'] = (int) Banco::valor('SELECT COUNT(*) FROM n10_x_gemeo');
        $rel['saidas_com_par'] = (int) Banco::valor('SELECT COUNT(*) FROM n10_x_par_saida');
        foreach (self::linhas('SELECT por, COUNT(*) AS q FROM n10_x_par_saida GROUP BY por') as $l) {
            $rel['saidas_par_' . $l['por']] = (int) $l['q']; // pedido · gemeo · data
        }
        return null;
    }

    // ------------------------------------------------------------------ 2. funcionários

    /** Cria os funcionários marcados como "➕ Criar funcionário no One" na tela de ligações */
    private static function etapaFuncionarios(int $cursor, array &$rel): ?int
    {
        $perfil = (int) Banco::valor("SELECT id FROM perfis WHERE slug = 'vendedor'");
        $funcao = CarteiraService::funcaoId((string) Config::get('nota10.funcao_vendedor', 'televendas'));
        $criados = [];
        foreach (self::linhas("SELECT codigo FROM nota10_mapas WHERE tipo = 'vendedor' AND destino = 'novo' ORDER BY codigo + 0") as $m) {
            $codigo = (string) $m['codigo'];
            $nome = Conferencia::nomeVendedor($codigo);
            $nome = mb_convert_case(mb_strtolower($nome), MB_CASE_TITLE);
            $chave = Normalizar::semAcento(mb_strtolower($nome));
            $id = $criados[$chave] ?? (Banco::valor('SELECT id FROM usuarios WHERE codigo_vendedor = ?', [$codigo]) ?: null);
            if (!$id) {
                $id = Banco::inserir('usuarios', ['nome' => mb_substr($nome, 0, 120), 'email' => 'nota10-' . $codigo . '@funcionario.pendente',
                    'senha_hash' => password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT), 'perfil_id' => $perfil, 'codigo_vendedor' => $codigo,
                    'ativo' => 1, 'trocar_senha' => 1, 'versao_vista' => \App\Services\Versoes::atual(), 'tutorial_ativo' => 1,
                    'observacoes' => 'Criado pela importação do Nota10 (código ' . $codigo . '). Defina o e-mail e a senha para a pessoa entrar.']);
                if ($funcao) {
                    \App\Models\Usuario::definirFuncoes((int) $id, [$funcao]);
                }
                Auditoria::registrar('usuario', (int) $id, 'criar_nota10', null, ['codigo_vendedor' => $codigo]);
                self::somar($rel, 'funcionarios_criados');
            }
            $criados[$chave] = (int) $id;
            Banco::executar("UPDATE nota10_mapas SET destino = ? WHERE tipo = 'vendedor' AND codigo = ?", [(string) $id, $codigo]);
        }
        self::$cache = [];
        return null;
    }

    // ------------------------------------------------------------------ 3. produtos

    private static function etapaProdutos(int $cursor, array &$rel): ?int
    {
        if (!Conferencia::existe('produtos')) {
            return null;
        }
        $grupos = array_column(Conferencia::existe('grupos') ? self::linhas('SELECT Numero AS numero, Nome AS nome FROM n10_grupos') : [], 'nome', 'numero');
        $estoque = Conferencia::existe('estoques') ? array_column(self::linhas('SELECT CodigoProduto AS cod, SUM(Quantidade) AS q FROM n10_estoques GROUP BY CodigoProduto'), 'q', 'cod') : [];
        $linhas = self::linhas('SELECT * FROM n10_produtos WHERE id > ? ORDER BY id LIMIT ' . self::LOTE, [$cursor]);
        foreach ($linhas as $p) {
            $codigo = trim((string) $p['codigoproduto']);
            if ($codigo === '') {
                continue;
            }
            $g = (string) (int) ($p['grupo'] ?? 0);
            $destino = self::mapas()['grupo'][$g] ?? ($g !== '0' && isset($grupos[$g]) ? Conferencia::palpiteGrupo((string) $grupos[$g]) : 'produto');
            $nomeGrupo = $grupos[$g] ?? null;
            if ($destino === 'materia_prima') {
                $dados = ['codigo' => mb_substr($codigo, 0, 40), 'nome' => mb_substr((string) $p['nome'] ?: "Item $codigo", 0, 160),
                    'unidade' => mb_substr(strtolower((string) ($p['unidadevenda'] ?: 'un')), 0, 10), 'estoque' => (float) ($estoque[$codigo] ?? $p['quant'] ?? 0),
                    'estoque_minimo' => (float) ($p['minimo'] ?? 0), 'custo_unitario' => (float) ($p['custo'] ?? 0), 'ativo' => (int) ($p['ativo'] ?? 1) ? 1 : 0,
                    'tipo' => preg_match('/embalag/i', (string) $nomeGrupo) ? 'embalagem' : 'materia_prima', 'rotulo' => preg_match('/r[oó]tulo/iu', (string) $nomeGrupo) ? 1 : 0,
                    'categoria' => $nomeGrupo ? mb_substr((string) $nomeGrupo, 0, 60) : null, 'fabricante' => mb_substr((string) ($p['fabricante'] ?? ''), 0, 120) ?: null, 'nota10_codigo' => $codigo];
                $id = Banco::valor('SELECT id FROM materias_primas WHERE nota10_codigo = ? OR (codigo = ? AND nota10_codigo IS NULL) LIMIT 1', [$codigo, $codigo]);
                $id ? Banco::atualizar('materias_primas', (int) $id, $dados) : Banco::inserir('materias_primas', $dados);
                self::somar($rel, 'materias_primas');
                continue;
            }
            if ($destino === 'ignorar') {
                self::somar($rel, 'produtos_so_guardados');
                continue;
            }
            $extras = ['nota10_codigo' => $codigo, 'unidade' => mb_substr((string) ($p['unidadevenda'] ?? ''), 0, 6) ?: null, 'cest' => mb_substr((string) ($p['cest'] ?? ''), 0, 10) ?: null,
                'custo' => (float) ($p['custo'] ?? 0) ?: null, 'grupo_erp' => $nomeGrupo ? mb_substr((string) $nomeGrupo, 0, 60) : null,
                'cadastrado_erp_em' => self::dataPhp($p['datacadastro'] ?? null)];
            $id = Banco::valor('SELECT id FROM produtos WHERE nota10_codigo = ? OR (codigo = ? AND nota10_codigo IS NULL) ORDER BY nota10_codigo IS NULL LIMIT 1', [$codigo, $codigo]);
            if ($id) {
                Banco::atualizar('produtos', (int) $id, $extras);
                self::somar($rel, 'produtos_ligados');
            } else { // só existe no Nota10: entra inativo e fora do catálogo (para o histórico ficar completo)
                $id = Banco::inserir('produtos', $extras + ['slug' => 'n10-' . preg_replace('/[^a-z0-9]+/', '-', strtolower($codigo)) . '-' . substr(md5($codigo), 0, 5),
                    'codigo' => mb_substr($codigo, 0, 40), 'nome' => mb_substr((string) $p['nome'] ?: "Produto $codigo", 0, 160), 'ficha_json' => '{}', 'ativo' => 0, 'visivel_catalogo' => 0]);
                self::somar($rel, 'produtos_novos_inativos');
            }
            // parte técnica (só onde está vazia) e estoque
            Banco::executar('INSERT INTO produto_tecnico (produto_id, ean, ncm, peso_liquido, peso_bruto) VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE ean = COALESCE(ean, VALUES(ean)), ncm = COALESCE(ncm, VALUES(ncm)), peso_liquido = COALESCE(peso_liquido, VALUES(peso_liquido)), peso_bruto = COALESCE(peso_bruto, VALUES(peso_bruto))',
                [$id, mb_substr(self::digitos($p['codigobarras'] ?? ''), 0, 20) ?: null, mb_substr(self::digitos($p['ncm'] ?? ''), 0, 10) ?: null,
                 (float) ($p['pesoliquido'] ?? 0) ?: null, (float) ($p['pesobruto'] ?? 0) ?: null]);
            if (isset($estoque[$codigo])) {
                Banco::executar('INSERT INTO produto_estoque (produto_id, quantidade) VALUES (?, ?) ON DUPLICATE KEY UPDATE quantidade = VALUES(quantidade)', [$id, (int) round((float) $estoque[$codigo])]);
            }
        }
        if (count($linhas) === self::LOTE) {
            return (int) end($linhas)['id'];
        }
        // preços (a tabela do Nota10 vai para a tabela do One escolhida na ligação; "Base" → Lojista)
        if (Conferencia::existe('precos')) {
            foreach (array_unique(array_column(self::linhas('SELECT DISTINCT Id_Tabela AS tab FROM n10_precos'), 'tab')) as $tab) {
                $destino = self::mapas()['tabela'][(string) $tab] ?? ((string) $tab === '1' ? (string) self::tabelaLojista() : null);
                if (!$destino || !ctype_digit((string) $destino)) {
                    continue;
                }
                $n = Banco::executar('INSERT INTO produto_precos (produto_id, tabela_preco_id, preco)
                    SELECT pr.id, ?, x.ValorPreco FROM n10_precos x JOIN produtos pr ON pr.nota10_codigo = TRIM(x.CodigoProduto)
                    WHERE x.Id_Tabela = ? AND x.ValorPreco > 0 AND x.id = (SELECT MAX(y.id) FROM n10_precos y WHERE y.Id_Tabela = x.Id_Tabela AND y.CodigoProduto = x.CodigoProduto)
                    ON DUPLICATE KEY UPDATE preco = VALUES(preco)', [$destino, $tab]);
                self::somar($rel, 'precos', $n);
            }
        }
        return null;
    }

    // ------------------------------------------------------------------ 4. cadastros

    private static function etapaCadastros(int $cursor, array &$rel): ?int
    {
        if (!Conferencia::existe('clientes')) {
            return null;
        }
        // cadastros internos: usados como carteira de muitos outros e sem compra própria (ex.: um vendedor antigo cadastrado como cliente)
        self::$cache['internos'] ??= array_flip(array_map('intval', array_column(self::linhas("SELECT c.Numero AS numero FROM n10_clientes c
            WHERE (SELECT COUNT(*) FROM n10_clientes o WHERE o.Representante = c.Numero) >= 50
              AND NOT EXISTS (SELECT 1 FROM n10_notas n WHERE n.NumeroCliente = c.Numero AND n.TipoOperacao = 'V' AND COALESCE(n.TotalNota, 0) > 0)
            UNION SELECT c.Numero AS numero FROM n10_clientes c WHERE UPPER(c.Nome) LIKE '%ENTRADA%' AND UPPER(c.Nome) LIKE '%PRODU%'"), 'numero'))); // e o cadastro das entradas de produção
        $linhas = self::linhas('SELECT * FROM n10_clientes WHERE id > ? ORDER BY id LIMIT ' . self::LOTE, [$cursor]);
        if (!$linhas) {
            self::fornecedoresPorMovimento($rel);
            return null;
        }
        $numeros = array_map(fn($l) => (int) $l['numero'], $linhas);
        $feitos = array_column(self::linhas('SELECT numero, cliente_id FROM n10_x_cliente WHERE numero IN (' . implode(',', $numeros) . ')'), 'cliente_id', 'numero');
        $ultima = array_column(self::linhas('SELECT numero, vendedor FROM n10_x_ultima_venda WHERE numero IN (' . implode(',', $numeros) . ')'), 'vendedor', 'numero');
        $docs = array_filter(array_map(fn($l) => self::digitos($l['cpf_cnpj'] ?? ''), $linhas), fn($d) => strlen($d) === 14 || strlen($d) === 11);
        $porDoc = $docs ? array_column(self::linhas('SELECT cnpj, MIN(id) AS id FROM clientes WHERE excluido_em IS NULL AND cnpj IN (' . implode(',', array_fill(0, count($docs), '?')) . ') GROUP BY cnpj', array_values($docs)), 'id', 'cnpj') : [];
        $prospeccao = CarteiraService::funcaoId((string) Config::get('carteira.funcao_reativacao', 'prospeccao_reativacao'));
        $ufs = Listas::ufs();
        $cidades = self::$cache['cidades'] ??= array_column(self::linhas('SELECT cod_ibge, id, uf FROM cidades WHERE cod_ibge IS NOT NULL'), null, 'cod_ibge');
        $novos = [];
        $fornecedores = [];
        $juntarLote = [];
        foreach ($linhas as $l) {
            $numero = (int) $l['numero'];
            $tipo = self::tipoCadastro($l['tipocliente'] ?? '', $numero);
            $doc = self::digitos($l['cpf_cnpj'] ?? '');
            $doc = in_array(strlen($doc), [11, 14], true) ? $doc : null;
            if (in_array($tipo, ['fornecedor', 'transportadora', 'cliente_fornecedor', 'cliente_transportadora'], true)) {
                $fornecedores[] = $l + ['_tipo' => str_contains($tipo, 'transportadora') ? 'transportadora' : 'fornecedor'];
            }
            if (!in_array($tipo, ['cliente', 'cliente_fornecedor', 'cliente_transportadora', 'validar', 'lead'], true)) {
                self::somar($rel, "cadastro_$tipo");
                continue;
            }
            $ja = isset($feitos[$numero]) ? (int) $feitos[$numero] : null; // já veio num backup anterior: só atualiza (abaixo)
            if (!$ja && $doc && ($porDoc[$doc] ?? null) === -1) { // mesmo CNPJ de outro cadastro deste lote: junta depois de gravar
                $juntarLote[$numero] = $doc;
                self::somar($rel, 'clientes_juntados_cnpj');
                continue;
            }
            if (!$ja && $doc && isset($porDoc[$doc])) { // mesmo CNPJ de uma loja que já está no One: vira uma loja só
                Banco::executar('INSERT IGNORE INTO n10_x_cliente (numero, cliente_id) VALUES (?, ?)', [$numero, $porDoc[$doc]]);
                Banco::executar('UPDATE clientes SET nota10_numero = COALESCE(nota10_numero, ?) WHERE id = ?', [$numero, $porDoc[$doc]]);
                self::somar($rel, 'clientes_juntados_cnpj');
                continue;
            }
            // UF: a digitada, ou a do código IBGE do município
            $uf = strtoupper(trim((string) ($l['estado'] ?? '')));
            $cid = $cidades[(string) ($l['codmunic'] ?? '')] ?? null;
            if (!in_array($uf, $ufs, true)) {
                $uf = $cid['uf'] ?? null;
            }
            $resp = self::vendedor($ultima[$numero] ?? 0) ?? self::vendedor($l['representante'] ?? 0);
            $obs = trim(implode("\n", array_filter([
                trim((string) ($l['obs'] ?? '')) !== '' ? 'Obs. (Nota10): ' . trim((string) $l['obs']) : '',
                trim((string) ($l['ocorrencias'] ?? '')) !== '' ? 'Ocorrências (Nota10): ' . trim((string) $l['ocorrencias']) : '',
                trim((string) ($l['refer'] ?? '')) !== '' ? 'Referência (Nota10): ' . trim((string) $l['refer']) : '',
            ])));
            $temEntrega = trim((string) ($l['entrega_endereco'] ?? '')) !== '';
            $segmento = null;
            foreach (['seg' => 'idsegmento', 's1' => 'segmento1', 's2' => 'segmento2'] as $pref => $campo) {
                $v = (int) ($l[$campo] ?? 0);
                if ($v && !$segmento && ctype_digit((string) (self::mapas()['segmento']["$pref:$v"] ?? ''))) {
                    $segmento = (int) self::mapas()['segmento']["$pref:$v"];
                }
            }
            $ativo = strtoupper((string) ($l['ativo'] ?? 'S')) !== 'N';
            $etapa = $tipo === 'validar' || (!$doc && $tipo !== 'lead') ? 'Validar cadastro' : (!$ativo ? 'Inativo' : (isset($ultima[$numero]) ? 'Cliente ativo' : 'Não abordado'));
            $email = trim(preg_split('/[;,\s]+/', (string) ($l['email'] ?? ''))[0] ?? '');
            $linha = [
                'tipo' => $tipo === 'lead' && !isset($ultima[$numero]) ? 'lead' : 'cliente',
                'razao_social' => mb_substr(trim((string) ($l['razao'] ?: $l['nome'])) ?: "Cadastro $numero", 0, 200),
                'nome_fantasia' => mb_substr(trim((string) $l['nome']), 0, 200) ?: null, 'cnpj' => $doc,
                'endereco' => mb_substr(trim((string) ($l['endereco'] ?? '')), 0, 255) ?: null, 'end_numero' => mb_substr((string) ($l['endnum'] ?? ''), 0, 20) ?: null,
                'end_complemento' => mb_substr((string) ($l['endcomp'] ?? ''), 0, 60) ?: null, 'bairro' => mb_substr((string) ($l['bairro'] ?? ''), 0, 120) ?: null,
                'cep' => strlen(self::digitos($l['cep'] ?? '')) === 8 ? self::digitos($l['cep']) : null,
                'cidade' => mb_substr(trim((string) ($l['cidade'] ?? '')), 0, 120) ?: 'Sem cidade', 'uf' => $uf, 'cidade_id' => $cid['id'] ?? null,
                'regiao_id' => $uf ? (self::$cache['regiao'][$uf] ??= Listas::regiaoDaUf($uf)) : null, 'segmento_id' => $segmento,
                'segmento_original' => mb_substr(trim(implode(' ', array_filter(['s1=' . (int) ($l['segmento1'] ?? 0), 's2=' . (int) ($l['segmento2'] ?? 0), ($l['grupo'] ?? '') !== '' ? 'grupo=' . $l['grupo'] : '']))), 0, 120),
                'telefone' => Normalizar::telefone((string) ($l['telefones'] ?? '')), 'whatsapp' => Normalizar::telefone((string) ($l['celular_1'] ?? '')),
                'celular2' => mb_substr((string) ($l['celular_2'] ?? ''), 0, 30) ?: null,
                'email' => filter_var($email, FILTER_VALIDATE_EMAIL) ? mb_substr($email, 0, 160) : null,
                'email_nfe' => mb_substr((string) ($l['email_nfe'] ?? ''), 0, 250) ?: null, 'email_financeiro' => mb_substr((string) ($l['email_fin'] ?? ''), 0, 250) ?: null,
                'site' => mb_substr((string) ($l['website'] ?? ''), 0, 255) ?: null, 'contato_nome' => mb_substr((string) ($l['contato'] ?? ''), 0, 60) ?: null,
                'inscricao_estadual' => mb_substr((string) ($l['rg_ie'] ?? ''), 0, 20) ?: null, 'inscricao_municipal' => mb_substr((string) ($l['inscricaomunicipal'] ?? ''), 0, 20) ?: null,
                'contribuinte' => ['1' => 'contribuinte', '2' => 'isento', '9' => 'nao_contribuinte'][(string) ($l['tipocontribuinte'] ?? '')] ?? null,
                'suframa' => mb_substr((string) ($l['suframa'] ?? ''), 0, 15) ?: null, 'data_fundacao' => self::dataPhp($l['datnas'] ?? null),
                'cliente_desde' => self::dataPhp($l['entrada'] ?? null), 'limite_credito' => (float) ($l['limitecredito'] ?? 0) ?: null,
                'desconto_padrao' => (float) ($l['desconto'] ?? 0) ?: null, 'comissao_pct' => (float) ($l['comissaovendedor'] ?? 0) ?: null,
                'situacao_erp' => mb_substr(trim(($ativo ? '' : 'Inativo ') . (string) ($l['situacao'] ?? '')), 0, 30) ?: null,
                'motivo_inativo' => mb_substr((string) ($l['motivostatus'] ?? ''), 0, 100) ?: null,
                'entrega_razao' => mb_substr((string) ($l['entrega_razao'] ?? ''), 0, 100) ?: null, 'entrega_cnpj' => self::digitos($l['entrega_cpf_cnpj'] ?? '') ?: null,
                'entrega_endereco' => mb_substr((string) ($l['entrega_endereco'] ?? ''), 0, 150) ?: null, 'entrega_numero' => mb_substr((string) ($l['entrega_endnum'] ?? ''), 0, 20) ?: null,
                'entrega_complemento' => mb_substr((string) ($l['entrega_endcomp'] ?? ''), 0, 60) ?: null, 'entrega_bairro' => mb_substr((string) ($l['entrega_bairro'] ?? ''), 0, 60) ?: null,
                'entrega_cidade' => mb_substr((string) ($l['entrega_cidade'] ?? ''), 0, 80) ?: null,
                'entrega_uf' => in_array(strtoupper((string) ($l['entrega_estado'] ?? '')), $ufs, true) ? strtoupper((string) $l['entrega_estado']) : null,
                'entrega_cep' => strlen(self::digitos($l['entrega_cep'] ?? '')) === 8 ? self::digitos($l['entrega_cep']) : null,
                'entrega_revisar' => !$temEntrega ? EntregaService::trechoNaObservacao($obs) : null,
                'observacoes' => $obs ?: null, 'responsavel_id' => $resp,
                'funcao_id' => $resp ? (self::$cache['funcao'][$resp] ??= CarteiraService::funcaoPrincipal($resp)) : $prospeccao,
                'etapa_id' => self::etapaId($etapa), 'prioridade' => 'Média',
                'validacao' => $tipo === 'validar' ? mb_substr('Tipo no Nota10: ' . ($l['tipocliente'] ?? ''), 0, 255) : (!$doc && $tipo !== 'lead' ? 'Sem CNPJ/CPF no Nota10' : null),
                'nota10_numero' => $numero, 'nota10_guid' => mb_substr((string) ($l['chave_guid'] ?? ''), 0, 32) ?: null,
                'nota10_dados' => json_encode(array_filter($l, fn($v) => $v !== null && $v !== '' && $v !== '0' && $v !== '0.00000'), JSON_UNESCAPED_UNICODE),
            ];
            if ($ja) {
                self::atualizarCliente($ja, $linha, $rel);
                continue;
            }
            $novos[] = $linha;
            if ($doc) {
                $porDoc[$doc] = -1; // repetido dentro deste lote: junta depois (abaixo)
            }
            self::somar($rel, $tipo === 'lead' ? 'leads' : 'clientes');
            if (!$resp) {
                self::somar($rel, 'sem_responsavel_fila');
            }
        }
        // grava, sem repetir o mesmo CNPJ dentro do lote
        $vistos = [];
        $gravar = [];
        $juntar = [];
        foreach ($novos as $n) {
            if ($n['cnpj'] && isset($vistos[$n['cnpj']])) {
                $juntar[$n['nota10_numero']] = $n['cnpj'];
                self::somar($rel, 'clientes_juntados_cnpj');
                continue;
            }
            if ($n['cnpj']) {
                $vistos[$n['cnpj']] = true;
            }
            $gravar[] = $n;
        }
        foreach (array_chunk($gravar, 300) as $parte) {
            self::inserirLote('clientes', $parte);
        }
        if ($gravar) {
            Banco::executar('INSERT IGNORE INTO n10_x_cliente (numero, cliente_id) SELECT nota10_numero, id FROM clientes WHERE nota10_numero IN ('
                . implode(',', array_column($gravar, 'nota10_numero')) . ')');
            // pessoa de contato (o Nota10 desta empresa não usa a tabela de contatos: só o campo "contato")
            Banco::executar("INSERT INTO cliente_contatos (cliente_id, nome, telefone, origem)
                SELECT c.id, LEFT(c.contato_nome, 80), c.telefone, 'nota10' FROM clientes c WHERE c.nota10_numero IN (" . implode(',', array_column($gravar, 'nota10_numero')) . ")
                  AND COALESCE(c.contato_nome, '') <> '' AND NOT EXISTS (SELECT 1 FROM cliente_contatos x WHERE x.cliente_id = c.id AND x.origem = 'nota10')");
        }
        foreach ($juntar + $juntarLote as $numero => $doc) {
            Banco::executar('INSERT IGNORE INTO n10_x_cliente (numero, cliente_id) SELECT ?, MIN(id) FROM clientes WHERE cnpj = ?', [$numero, $doc]);
        }
        self::gravarFornecedores($fornecedores, $rel);
        return (int) end($linhas)['id'];
    }

    /**
     * Cadastro que já veio de um backup anterior: completa o que está vazio no One (não apaga o que a equipe preencheu)
     * e atualiza o que é do ERP (situação, motivo, limite de crédito).
     */
    private static function atualizarCliente(int $id, array $l, array &$rel): void
    {
        $preencher = ['cnpj', 'endereco', 'end_numero', 'end_complemento', 'bairro', 'cep', 'telefone', 'whatsapp', 'celular2', 'email', 'email_nfe',
            'email_financeiro', 'site', 'contato_nome', 'inscricao_estadual', 'inscricao_municipal', 'contribuinte', 'suframa', 'data_fundacao', 'cliente_desde'];
        $sets = [];
        $valores = [];
        foreach ($preencher as $c) {
            if ($l[$c] !== null && $l[$c] !== '') {
                $sets[] = "`$c` = COALESCE(NULLIF(`$c`, ''), ?)";
                $valores[] = $l[$c];
            }
        }
        foreach (['situacao_erp', 'motivo_inativo', 'limite_credito'] as $c) {
            $sets[] = "`$c` = ?";
            $valores[] = $l[$c];
        }
        $n = Banco::executar('UPDATE clientes SET ' . implode(', ', $sets) . ' WHERE id = ?', [...$valores, $id]);
        self::somar($rel, $n ? 'clientes_atualizados' : 'clientes_sem_mudanca');
    }

    private static function gravarFornecedores(array $linhas, array &$rel): void
    {
        foreach ($linhas as $l) {
            $tipo = $l['_tipo'];
            $numero = (int) $l['numero'];
            if (Banco::valor('SELECT 1 FROM fornecedores WHERE nota10_numero = ? AND tipo = ?', [$numero, $tipo])) {
                continue;
            }
            Banco::inserir('fornecedores', ['tipo' => $tipo, 'razao_social' => mb_substr(trim((string) ($l['razao'] ?: $l['nome'])) ?: "Cadastro $numero", 0, 200),
                'nome_fantasia' => mb_substr((string) $l['nome'], 0, 200) ?: null, 'cnpj' => mb_substr(self::digitos($l['cpf_cnpj'] ?? ''), 0, 14) ?: null,
                'inscricao_estadual' => mb_substr((string) ($l['rg_ie'] ?? ''), 0, 20) ?: null, 'endereco' => mb_substr((string) ($l['endereco'] ?? ''), 0, 200) ?: null,
                'bairro' => mb_substr((string) ($l['bairro'] ?? ''), 0, 120) ?: null, 'cidade' => mb_substr((string) ($l['cidade'] ?? ''), 0, 120) ?: null,
                'uf' => mb_substr(strtoupper((string) ($l['estado'] ?? '')), 0, 2) ?: null, 'cep' => strlen(self::digitos($l['cep'] ?? '')) === 8 ? self::digitos($l['cep']) : null,
                'telefone' => mb_substr((string) ($l['telefones'] ?? ''), 0, 40) ?: null, 'email' => mb_substr((string) ($l['email'] ?? ''), 0, 250) ?: null,
                'contato' => mb_substr((string) ($l['contato'] ?? ''), 0, 100) ?: null, 'observacoes' => trim((string) ($l['obs'] ?? '')) ?: null,
                'ativo' => strtoupper((string) ($l['ativo'] ?? 'S')) !== 'N' ? 1 : 0, 'nota10_numero' => $numero,
                'cliente_id' => Banco::valor('SELECT cliente_id FROM n10_x_cliente WHERE numero = ?', [$numero]) ?: null,
                'nota10_dados' => json_encode(array_filter($l, fn($v) => $v !== null && $v !== '' && $v !== '0'), JSON_UNESCAPED_UNICODE)]);
            self::somar($rel, $tipo === 'transportadora' ? 'transportadoras' : 'fornecedores');
        }
    }

    /** Também é fornecedor quem aparece em Compras (C), Outras entradas (E) ou Devolução a fornecedor (F), mesmo cadastrado como cliente */
    private static function fornecedoresPorMovimento(array &$rel): void
    {
        $linhas = self::linhas("SELECT c.* FROM n10_clientes c WHERE c.Numero IN (SELECT DISTINCT n.NumeroCliente FROM n10_notas n
                WHERE n.TipoOperacao IN ('C', 'E', 'F') AND COALESCE(n.NumeroCliente, 0) > 0 AND COALESCE(n.NaturezaOperacao, '') NOT LIKE '%MANUAL%')
            AND NOT (UPPER(c.Nome) LIKE '%ENTRADA%' AND UPPER(c.Nome) LIKE '%PRODU%')
            AND NOT EXISTS (SELECT 1 FROM fornecedores f WHERE f.nota10_numero = c.Numero AND f.tipo = 'fornecedor')");
        self::gravarFornecedores(array_map(fn($l) => $l + ['_tipo' => 'fornecedor'], $linhas), $rel);
    }

    // ------------------------------------------------------------------ 5. pedidos

    /** Situação do pedido pelas regras do Nota10 (regras-importacao.md 3.2); StatusPedido: 0 Cadastrado · 1 Processando · 2 Faturado · 3 Cancelado · 4 Em separação · 5 Despachado */
    public static function situacao(array $n): array
    {
        $nf = strtoupper(trim((string) ($n['status_nf'] ?? '')));
        $st = trim((string) ($n['statuspedido'] ?? ''));
        $cancel = (int) ($n['pedidocancelado'] ?? 0) === 1;
        if ($nf === 'C' || $st === '3' || ($cancel && $nf !== 'E')) {
            return ['cancelado', null];
        }
        if ($nf === 'E') {
            return ['faturado', $cancel ? 'alerta_cancelado_com_nf' : null];
        }
        if (in_array($st, ['5', '2'], true)) {
            return ['faturado', 'sem_nf'];
        }
        return ['historico', ['0' => 'cadastrado', '1' => 'processando', '4' => 'em_separacao'][$st] ?? 'sem_situacao'];
    }

    private static function etapaPedidos(int $cursor, array &$rel): ?int
    {
        if (!Conferencia::existe('notas')) {
            return null;
        }
        $linhas = self::linhas('SELECT * FROM n10_notas WHERE id > ? ORDER BY id LIMIT ' . self::LOTE, [$cursor]);
        if (!$linhas) {
            return null;
        }
        $guids = array_map(fn($l) => (string) $l['guid_nota'], $linhas);
        $in = implode(',', array_fill(0, count($guids), '?'));
        $feitos = array_column(self::linhas("SELECT * FROM pedidos WHERE nota10_guid IN ($in)", $guids), null, 'nota10_guid');
        $gemeos = array_flip(array_column(self::linhas("SELECT guid FROM n10_x_gemeo WHERE guid IN ($in)", $guids), 'guid'));
        $comPar = array_flip(array_column(self::linhas("SELECT guid_s FROM n10_x_par_saida WHERE guid_s IN ($in)", $guids), 'guid_s'));
        $numeros = array_unique(array_merge(array_map(fn($l) => (int) $l['numerocliente'], $linhas), array_map(fn($l) => (int) $l['codigotransportadora'], $linhas), [0]));
        $clientes = array_column(self::linhas('SELECT numero, cliente_id FROM n10_x_cliente WHERE numero IN (' . implode(',', $numeros) . ')'), 'cliente_id', 'numero');
        $nomes = array_column(self::linhas('SELECT Numero AS numero, COALESCE(NULLIF(Nome, \'\'), Razao) AS nome FROM n10_clientes WHERE Numero IN ('
            . implode(',', array_unique(array_merge($numeros, array_map(fn($l) => (int) $l['codigovendedor'], $linhas)))) . ')'), 'nome', 'numero');
        $principal = self::principal();
        $novos = [];
        foreach ($linhas as $n) {
            $guid = (string) $n['guid_nota'];
            $t = strtoupper(trim((string) $n['tipooperacao']));
            $ja = $feitos[$guid] ?? null; // já veio num backup anterior: atualiza se mudou (abaixo)
            if ($ja && $ja['origem'] !== 'nota10') {
                continue; // nunca mexe em pedido que não veio do Nota10
            }
            if (!$ja && isset($gemeos[$guid])) {
                self::somar($rel, 'gemeos_juntados'); // os itens vão para o pedido principal como bonificados (etapa "itens")
                continue;
            }
            if (!$ja && isset($comPar[$guid])) {
                self::somar($rel, 'saidas_ligadas_a_pedido'); // é a NF de um pedido de venda (etapa "ligar")
                continue;
            }
            $natureza = mb_strtoupper((string) ($n['naturezaoperacao'] ?? ''));
            if (in_array($t, ['E', 'S'], true) && str_contains($natureza, 'ALTERACAO MANUAL')) {
                self::somar($rel, 'ajustes_estoque_guardados');
                continue;
            }
            if (in_array($t, ['C', 'E', 'F'], true)) {
                self::somar($rel, "notas_{$t}_guardadas"); // compras (etapa "compras"), entradas e devoluções a fornecedor ficam em n10_notas
                continue;
            }
            $cliente = $clientes[(int) $n['numerocliente']] ?? null;
            if (!$cliente) {
                self::somar($rel, 'notas_sem_cliente');
                continue;
            }
            [$situacao, $detalhe] = self::situacao($n);
            $operacao = Conferencia::tipoOperacao($n['cfop'] ?? null, $n['finnfe'] ?? null);
            $total = (float) ($n['totalnota'] ?? 0);
            $tipoOp = match ($t) {
                'O' => 'orcamento',
                'D' => 'devolucao',
                'V' => $total <= 0 ? 'bonificacao' : 'venda',
                default => in_array($operacao, ['venda', 'bonificacao', 'remessa', 'devolucao'], true) ? $operacao : 'outra',
            };
            if ($t === 'O' || $t === 'D' || $tipoOp === 'bonificacao' || ($t === 'S' && $tipoOp !== 'venda')) {
                $detalhe = $situacao;     // guarda o que seria
                $situacao = $situacao === 'cancelado' ? 'cancelado' : 'historico'; // não conta no faturamento
            }
            if ($detalhe === 'alerta_cancelado_com_nf') {
                self::somar($rel, 'alerta_cancelado_com_nf');
            }
            $emitente = self::digitos($n['emitente'] ?? '');
            $outra = $emitente !== '' && $emitente !== $principal;
            $situacaoOrigem = $outra ? $situacao : ($situacao === 'historico' || $situacao === 'faturado' && $detalhe === 'sem_nf' ? $detalhe : null);
            if ($outra && $situacao !== 'cancelado') {
                $situacao = 'outra_empresa';
            }
            $data = self::dataPhp($n['data'] ?? null) ?? '2000-01-01';
            $hora = preg_match('/^\d{2}:\d{2}(:\d{2})?$/', trim((string) ($n['hora'] ?? ''))) ? trim((string) $n['hora']) : '00:00:00';
            $vend = (int) ($n['codigovendedor'] ?? 0);
            $uf = strtoupper(trim((string) ($n['uf_destino'] ?? '')));
            $linha = [
                'uuid' => \App\Services\PedidoService::novoUuid(), 'cliente_id' => $cliente, 'vendedor_id' => self::vendedor($vend), 'origem' => 'nota10',
                'tabela_preco_id' => self::tabelaLojista(), 'forma_pagamento_id' => self::formaId($n['formapgto'] ?? 0),
                'subtotal' => (float) ($n['totalmercadoria'] ?? 0) ?: $total, 'desconto' => (float) ($n['totaldesconto'] ?? 0), 'total' => $total,
                'situacao' => $situacao, 'situacao_origem' => $situacaoOrigem, 'tipo_venda' => $t === 'S' && $tipoOp === 'venda' ? 'nf_avulsa' : null,
                'faturado_em' => in_array($situacao, ['faturado', 'outra_empresa'], true) ? ((self::dataPhp($n['data_nf'] ?? null) ?? $data) . ' 12:00:00') : null,
                'numero_nf' => (int) ($n['nota_s'] ?? 0) ? (string) $n['nota_s'] : null, 'nf_serie' => mb_substr((string) ($n['serie'] ?? ''), 0, 5) ?: null,
                'nf_chave' => mb_substr(self::digitos($n['prefnfe'] ?? ''), 0, 44) ?: null,
                'nf_situacao' => ['E' => 'autorizada', 'C' => 'cancelada', 'D' => 'denegada', 'R' => 'recebida'][strtoupper((string) ($n['status_nf'] ?? ''))] ?? null,
                'emitido_em' => self::dataPhp($n['data_nf'] ?? null), 'saida_em' => self::dataPhp($n['datasaida'] ?? null), 'entrega_em' => self::dataPhp($n['dataentrega'] ?? null),
                'cfop' => mb_substr(self::digitos($n['cfop'] ?? ''), 0, 10) ?: null, 'natureza_operacao' => mb_substr((string) ($n['naturezaoperacao'] ?? ''), 0, 80) ?: null,
                'tipo_operacao' => $tipoOp, 'frete_valor' => (float) ($n['valorfrete'] ?? 0) ?: null,
                'valor_icms' => (float) ($n['valoricms'] ?? 0) ?: null, 'valor_st' => (float) ($n['valor_st'] ?? 0) ?: null, 'valor_ipi' => (float) ($n['valoripi'] ?? 0) ?: null,
                'transportadora' => (int) ($n['codigotransportadora'] ?? 0) ? mb_substr((string) ($nomes[(int) $n['codigotransportadora']] ?? 'Transportadora ' . $n['codigotransportadora']), 0, 80) : null,
                'volumes' => (int) ($n['volumes'] ?? 0) ?: null, 'peso_kg' => (float) ($n['pesobruto'] ?? 0) ?: null, 'rastreio' => mb_substr((string) ($n['codigorastreio'] ?? ''), 0, 60) ?: null,
                'observacoes' => mb_substr(trim((string) ($n['obs'] ?? '')), 0, 1000) ?: null, 'obs_interna' => trim((string) ($n['obsinterna'] ?? '')) ?: null,
                'entrega_uf' => in_array($uf, Listas::ufs(), true) ? $uf : null, 'entrega_outro' => 0,
                'emitente_cnpj' => $emitente ?: null, 'nota10_guid' => $guid, 'nota10_pedido' => mb_substr((string) ($n['pedido'] ?? ''), 0, 20) ?: null, 'nota10_tipo' => $t ?: null,
                'nota10_vendedor_codigo' => $vend ?: null, 'nota10_vendedor_nome' => $vend ? mb_substr(Conferencia::limparNomeVendedor((string) ($nomes[$vend] ?? '')), 0, 100) ?: null : null,
                'criado_em' => "$data $hora", 'atualizado_em' => "$data $hora",
            ];
            if ($ja && isset($comPar[$guid]) && $ja['tipo_venda'] === 'nf_avulsa') {
                // importada antes como venda avulsa, mas é a NF de um pedido de venda: sai do faturamento (não conta em dobro)
                $linha['situacao'] = $linha['situacao'] === 'cancelado' ? 'cancelado' : 'historico';
                $linha['situacao_origem'] = 'nf_de_pedido';
                $linha['tipo_venda'] = null;
                $linha['faturado_em'] = null;
                self::somar($rel, 'saidas_ligadas_a_pedido');
            }
            if ($ja) {
                self::atualizarPedido($ja, $linha, $rel);
                continue;
            }
            $novos[] = $linha;
            self::somar($rel, 'pedidos_' . $situacao);
            if ($t === 'O') {
                self::somar($rel, 'orcamentos_historico');
            }
        }
        foreach (array_chunk($novos, 300) as $parte) {
            self::inserirLote('pedidos', $parte);
        }
        return (int) end($linhas)['id'];
    }

    /**
     * Pedido que já veio de um backup anterior e mudou no Nota10 (ganhou NF, foi despachado, cancelado...): atualiza a situação,
     * a NF e os valores, e registra no histórico do pedido. NF ligada pela etapa "ligar" (Outras saídas) não é apagada.
     */
    private static function atualizarPedido(array $ja, array $novo, array &$rel): void
    {
        if ((int) $ja['possivel_duplicado'] && $novo['situacao'] === 'faturado') { // duplicado continua fora do faturamento
            $novo['situacao'] = 'historico';
            $novo['situacao_origem'] = 'faturado_duplicado';
        }
        $mudou = [];
        foreach (['situacao', 'situacao_origem', 'numero_nf', 'nf_serie', 'nf_chave', 'nf_situacao', 'faturado_em', 'emitido_em', 'saida_em', 'entrega_em',
                  'total', 'subtotal', 'desconto', 'tipo_operacao', 'tipo_venda', 'cfop', 'vendedor_id'] as $c) {
            $velho = $ja[$c] ?? null;
            $valor = $novo[$c] ?? null;
            if ($valor === null && $velho !== null && in_array($c, ['numero_nf', 'nf_chave', 'nf_situacao', 'emitido_em', 'vendedor_id'], true)) {
                continue; // não apaga o que já estava (ex.: NF ligada pelas "Outras saídas")
            }
            $igual = is_numeric($velho) && is_numeric($valor) ? abs((float) $velho - (float) $valor) < 0.005 : (string) $velho === (string) $valor;
            if (!$igual) {
                $mudou[$c] = $valor;
            }
        }
        if (!$mudou) {
            return;
        }
        Banco::atualizar('pedidos', (int) $ja['id'], $mudou);
        $texto = 'Atualizado pelo Nota10: ' . implode(' · ', array_filter([
            isset($mudou['situacao']) ? 'situação ' . $ja['situacao'] . ' → ' . $mudou['situacao'] : null,
            isset($mudou['numero_nf']) ? 'NF ' . $mudou['numero_nf'] : null,
            isset($mudou['total']) ? 'total ' . dinheiro((float) $mudou['total']) : null,
        ])) ?: 'Atualizado pelo Nota10.';
        Banco::inserir('pedido_historico', ['pedido_id' => (int) $ja['id'], 'situacao' => $mudou['situacao'] ?? $ja['situacao'], 'texto' => mb_substr($texto, 0, 500)]);
        self::somar($rel, 'pedidos_atualizados');
    }

    // ------------------------------------------------------------------ 6. itens

    private static function etapaItens(int $cursor, array &$rel): ?int
    {
        $max = self::maxId('n10_itensnota');
        if ($cursor >= $max) {
            return null;
        }
        $ate = $cursor + self::FAIXA;
        $uf = Banco::pdo()->quote(strtoupper((string) Config::get('fiscal.uf_empresa', 'RS')));
        $fora = "(p.entrega_uf IS NOT NULL AND p.entrega_uf <> $uf)";
        $n = Banco::executar("INSERT INTO pedido_itens (pedido_id, produto_id, codigo, nome, quantidade, quantidade_nota10, preco_unitario, total, preco_tabela,
                desconto_valor, custo_unitario, lote, validade, fabricacao, ncm, cfop, bonificado, valor_bonificado, nota10_guid)
            SELECT p.id, pr.id, LEFT(i.CodigoProduto, 40),
                LEFT(COALESCE(NULLIF(TRIM(i.DescricaoProduto), ''), pr.nome, CONCAT('Produto ', i.CodigoProduto)), 200),
                ROUND(COALESCE(i.Quantidade, 0)), i.Quantidade, COALESCE(i.ValorUnitario, 0),
                CASE WHEN g.guid IS NULL THEN COALESCE(i.ValorTotalItem, 0) ELSE 0 END, i.ValorUnitario,
                NULLIF(i.ValorDesconto, 0), NULLIF(i.CustoProduto, 0), NULLIF(TRIM(i.Lote), ''), " . self::data('i.Validade') . ', ' . self::data('i.datafabricacao') . ",
                LEFT(NULLIF(TRIM(i.ncm), ''), 10),
                CASE WHEN g.guid IS NOT NULL THEN
                        CASE WHEN LEFT(REPLACE(COALESCE(i.CFOP, ''), '.', ''), 1) IN ('5', '6') THEN CONCAT(LEFT(REPLACE(i.CFOP, '.', ''), 1), '910')
                             WHEN $fora THEN '6910' ELSE '5910' END
                     ELSE COALESCE(NULLIF(REPLACE(TRIM(i.CFOP), '.', ''), ''), NULLIF(REPLACE(TRIM(n.CFOP), '.', ''), ''), IF($fora, '6102', '5102')) END,
                IF(g.guid IS NULL, 0, 1), IF(g.guid IS NULL, 0, ROUND(COALESCE(i.Quantidade, 0) * COALESCE(i.ValorUnitario, 0), 2)),
                COALESCE(NULLIF(i.GUID_Item, ''), CONCAT('n10i', i.id))
            FROM n10_itensnota i
            LEFT JOIN n10_x_gemeo g ON g.guid = i.GUID_Nota
            JOIN pedidos p ON p.nota10_guid = COALESCE(g.principal, i.GUID_Nota)
            LEFT JOIN n10_notas n ON n.GUID_Nota = i.GUID_Nota
            LEFT JOIN produtos pr ON pr.nota10_codigo = TRIM(i.CodigoProduto)
            WHERE i.id > ? AND i.id <= ?
              AND NOT EXISTS (SELECT 1 FROM pedido_itens x WHERE x.nota10_guid = COALESCE(NULLIF(i.GUID_Item, ''), CONCAT('n10i', i.id)))", [$cursor, $ate]);
        self::somar($rel, 'itens', $n);
        return $ate;
    }

    // ------------------------------------------------------------------ 7. ligar NFs e bonificações

    private static function etapaLigar(int $cursor, array &$rel): ?int
    {
        // NF de "Outras saídas" = NF do pedido de venda (não conta o valor de novo)
        $n = Banco::executar("UPDATE pedidos p JOIN n10_x_par_saida x ON x.guid_v = p.nota10_guid JOIN n10_notas s ON s.GUID_Nota = x.guid_s
            SET p.numero_nf = COALESCE(p.numero_nf, NULLIF(s.Nota_S, 0)), p.nf_chave = COALESCE(p.nf_chave, NULLIF(s.PrefNFE, '')),
                p.nf_situacao = COALESCE(p.nf_situacao, CASE s.Status_NF WHEN 'E' THEN 'autorizada' WHEN 'C' THEN 'cancelada' END),
                p.valor_nf = s.TotalNota, p.emitido_em = COALESCE(p.emitido_em, " . self::data('s.data_NF') . ')');
        $rel['nfs_de_saida_ligadas'] = $n;
        // NF própria da bonificação (pedido gêmeo com NF)
        Banco::executar("UPDATE pedidos p JOIN n10_x_gemeo g ON g.principal = p.nota10_guid SET p.nf_bonificacao = g.nf WHERE COALESCE(g.nf, 0) > 0 AND g.status_nf = 'E'");
        // valor bonificado de cada pedido
        Banco::executar("UPDATE pedidos p JOIN (SELECT pedido_id, SUM(valor_bonificado) AS v FROM pedido_itens WHERE bonificado = 1 GROUP BY pedido_id) b ON b.pedido_id = p.id
            SET p.bonificado_valor = b.v WHERE p.origem = 'nota10'");
        return null;
    }

    // ------------------------------------------------------------------ 8. títulos

    private static function etapaTitulos(int $cursor, array &$rel): ?int
    {
        $max = self::maxId('n10_contaspagrec');
        if ($cursor >= $max) {
            $rel['titulos_a_pagar_guardados'] = (int) Banco::valor("SELECT COUNT(*) FROM n10_contaspagrec WHERE UPPER(Tipo) = 'P'");
            return null;
        }
        $ate = $cursor + self::FAIXA;
        $venc = self::data('t.DataVencimento');
        $pago = self::data('t.dataPagamento');
        $canc = self::data('t.dataCancelamento');
        $emit = "REPLACE(REPLACE(REPLACE(COALESCE(t.Emitente, ''), '.', ''), '/', ''), '-', '')";
        $n = Banco::executar("INSERT INTO titulos_receber (cliente_id, pedido_id, documento, parcela, nosso_numero, forma_pagamento, historico, lancado_em,
                vencimento, pago_em, cancelado_em, valor, desconto, acrescimo, valor_pago, situacao, origem, nota10_guid, emitente_cnpj)
            SELECT xc.cliente_id, p.id, LEFT(NULLIF(TRIM(t.Documento), ''), 40), NULLIF(t.NumeroParcela, 0), LEFT(NULLIF(t.NossoNumero, ''), 30), LEFT(f.Nome, 40),
                LEFT(NULLIF(t.Historico, ''), 255), " . self::data('t.DataLancamento') . ", $venc, $pago, $canc,
                COALESCE(NULLIF(t.ValorLiquido, 0), t.ValorBruto, 0), COALESCE(t.Desconto, 0), COALESCE(t.Acrescimo, 0),
                IF($pago IS NOT NULL, t.ValorLiquido, NULL),
                CASE WHEN COALESCE(t.Cancelado, 0) = 1 OR $canc IS NOT NULL THEN 'cancelado' WHEN $pago IS NOT NULL THEN 'pago' ELSE 'aberto' END,
                'nota10', t.ChaveGUID, NULLIF(LEFT($emit, 14), '')
            FROM n10_contaspagrec t
            LEFT JOIN n10_x_cliente xc ON xc.numero = t.CredorDevedor
            LEFT JOIN pedidos p ON p.nota10_guid = t.GUID_Nota AND COALESCE(t.GUID_Nota, '') <> ''
            LEFT JOIN n10_formapgto f ON f.Codigo = t.FormaPgto
            WHERE t.id > ? AND t.id <= ? AND UPPER(t.Tipo) = 'R' AND COALESCE(t.ChaveGUID, '') <> ''
            ON DUPLICATE KEY UPDATE situacao = VALUES(situacao), pago_em = VALUES(pago_em), cancelado_em = VALUES(cancelado_em), valor_pago = VALUES(valor_pago),
                cliente_id = VALUES(cliente_id), pedido_id = VALUES(pedido_id)", [$cursor, $ate]);
        self::somar($rel, 'titulos_receber', $n);
        return $ate;
    }

    // ------------------------------------------------------------------ 8b. cobrança

    /** Títulos vencidos viram casos na Cobrança (um por cliente) e os pagos recebem baixa: CobrancaService::sincronizarTitulos() */
    private static function etapaCobranca(int $cursor, array &$rel): ?int
    {
        // a mesma rotina da tela de Cobrança: os títulos da ficha (titulos_receber) abrem/completam os casos e os pagos recebem baixa
        $r = \App\Services\CobrancaService::sincronizarTitulos(null, $cursor, 200);
        self::somar($rel, 'cobranca_casos', $r['casos']);
        self::somar($rel, 'cobranca_casos_com_titulo_novo', $r['casos_com_titulo_novo']);
        self::somar($rel, 'cobranca_titulos', $r['titulos']);
        self::somar($rel, 'cobranca_titulos_baixados', $r['baixas']);
        return $r['mais'] ? $r['ultimo'] : null;
    }

    // ------------------------------------------------------------------ 9. linha do tempo

    private static function etapaLinhaTempo(int $cursor, array &$rel): ?int
    {
        if (Conferencia::existe('followup')) {
            $usuarios = Conferencia::existe('usuarios') ? 'LEFT JOIN n10_usuarios u ON u.Numero = f.usuario AND f.usuario <> 0' : 'LEFT JOIN (SELECT NULL AS nome) u ON 1 = 0';
            $n = Banco::executar("INSERT INTO cliente_eventos (cliente_id, usuario_id, tipo, canal, descricao, dados_json, ocorrido_em, registrado_em)
                SELECT xc.cliente_id, NULL, 'contato', 'Nota10',
                    LEFT(CONCAT(COALESCE(NULLIF(TRIM(f.resumo), ''), 'Follow-up'), IF(COALESCE(f.detalhes, '') <> '', CONCAT(' — ', f.detalhes), ''),
                        IF(u.nome IS NOT NULL, CONCAT(' (', u.nome, ' no Nota10)'), '')), 2000),
                    JSON_OBJECT('nota10_followup', f.id), COALESCE(" . self::data('f.data') . ", NOW()), NULL
                FROM n10_followup f JOIN n10_x_cliente xc ON xc.numero = f.cliente
                $usuarios
                WHERE NOT EXISTS (SELECT 1 FROM cliente_eventos e WHERE e.cliente_id = xc.cliente_id AND e.canal = 'Nota10'
                    AND JSON_UNQUOTE(JSON_EXTRACT(e.dados_json, '$.nota10_followup')) = CAST(f.id AS CHAR))");
            $rel['followups'] = ($rel['followups'] ?? 0) + $n;
        }
        if (Conferencia::existe('eventosnfe')) {
            $n = Banco::executar("INSERT INTO pedido_historico (pedido_id, situacao, texto, usuario_id, criado_em)
                SELECT p.id, p.situacao, LEFT(CONCAT('[NF-e #', e.id, '] ', CASE WHEN e.Tipo LIKE 'CANC%' OR e.CodigoEvento = '110111' THEN 'Cancelamento da NF'
                        WHEN e.Tipo LIKE 'CC%' OR e.CodigoEvento = '110110' THEN 'Carta de correção' ELSE 'Evento da NF' END,
                        IF(COALESCE(e.Texto, '') <> '', CONCAT(': ', e.Texto), '')), 500), NULL,
                    COALESCE(" . self::data('e.data') . ", NOW())
                FROM n10_eventosnfe e JOIN pedidos p ON p.nota10_guid = e.GUID_NOTA
                WHERE NOT EXISTS (SELECT 1 FROM pedido_historico h WHERE h.pedido_id = p.id AND h.texto LIKE CONCAT('[NF-e #', e.id, ']%'))");
            $rel['eventos_nfe'] = ($rel['eventos_nfe'] ?? 0) + $n;
        }
        return null;
    }

    // ------------------------------------------------------------------ 10. compras

    private static function etapaCompras(int $cursor, array &$rel): ?int
    {
        if (!Conferencia::existe('notas')) {
            return null;
        }
        $compras = self::linhas("SELECT n.*, c.Razao AS razao, c.Nome AS nomecad, c.CPF_CNPJ AS cpf_cnpj FROM n10_notas n LEFT JOIN n10_clientes c ON c.Numero = n.NumeroCliente
            WHERE n.TipoOperacao = 'C' AND n.id > ? AND NOT EXISTS (SELECT 1 FROM compras_lancadas x WHERE x.nota10_guid = n.GUID_Nota) ORDER BY n.id LIMIT " . self::LOTE, [$cursor]);
        foreach ($compras as $n) {
            $id = Banco::inserir('compras_lancadas', ['origem' => 'nota10', 'situacao' => 'lancado',
                'numero_nf' => (int) ($n['nota_e'] ?? 0) ? (string) $n['nota_e'] : null, 'serie' => mb_substr((string) ($n['serie'] ?? ''), 0, 5) ?: null,
                'fornecedor' => mb_substr(trim((string) ($n['razao'] ?: $n['nomecad'])) ?: 'Fornecedor ' . $n['numerocliente'], 0, 160),
                'cnpj' => mb_substr(self::digitos($n['cpf_cnpj'] ?? ''), 0, 14) ?: null, 'emissao' => self::dataPhp($n['data']) ?? date('Y-m-d'),
                'valor_total' => (float) ($n['totalnota'] ?? 0), 'observacoes' => mb_substr(trim((string) ($n['naturezaoperacao'] ?? '') . ' ' . (string) ($n['obs'] ?? '')), 0, 1000) ?: null,
                'fornecedor_id' => Banco::valor("SELECT id FROM fornecedores WHERE nota10_numero = ? AND tipo = 'fornecedor'", [$n['numerocliente']]) ?: null,
                'nota10_guid' => $n['guid_nota']]);
            Banco::executar('INSERT INTO compras_lancadas_itens (lancamento_id, materia_prima_id, descricao, quantidade, valor_unitario, total, lancado)
                SELECT ?, mp.id, LEFT(COALESCE(NULLIF(i.DescricaoProduto, \'\'), mp.nome, CONCAT(\'Item \', i.CodigoProduto)), 200), COALESCE(i.Quantidade, 0), i.ValorUnitario, i.ValorTotalItem, 1
                FROM n10_itensnota i LEFT JOIN materias_primas mp ON mp.nota10_codigo = TRIM(i.CodigoProduto) WHERE i.GUID_Nota = ?', [$id, $n['guid_nota']]);
            self::somar($rel, 'compras');
        }
        return count($compras) === self::LOTE ? (int) end($compras)['id'] : null;
    }

    // ------------------------------------------------------------------ 11. recalcular

    private static function etapaRecalcular(int $cursor, array &$rel): ?int
    {
        // rede: filial → matriz
        if (Conferencia::col('clientes', 'NumeroMatriz')) {
            Banco::executar('UPDATE clientes c JOIN n10_clientes n ON n.Numero = c.nota10_numero JOIN n10_x_cliente m ON m.numero = n.NumeroMatriz
                SET c.matriz_id = m.cliente_id WHERE COALESCE(n.NumeroMatriz, 0) > 0 AND m.cliente_id <> c.id');
        }
        // duplicados exatos (mesmo tipo, cliente, dia e total): fica o primeiro; os outros não somam
        $n = Banco::executar("UPDATE pedidos p JOIN (SELECT cliente_id, DATE(criado_em) AS d, total, COALESCE(nota10_tipo, '') AS t, COALESCE(emitente_cnpj, '') AS e, MIN(id) AS primeiro
                FROM pedidos WHERE origem = 'nota10' AND total > 0 AND situacao <> 'cancelado' GROUP BY 1, 2, 3, 4, 5 HAVING COUNT(*) > 1) x
              ON x.cliente_id = p.cliente_id AND x.d = DATE(p.criado_em) AND x.total = p.total AND x.t = COALESCE(p.nota10_tipo, '') AND x.e = COALESCE(p.emitente_cnpj, '')
            SET p.possivel_duplicado = 1, p.situacao_origem = IF(p.situacao = 'faturado', 'faturado_duplicado', p.situacao_origem),
                p.situacao = IF(p.situacao = 'faturado', 'historico', p.situacao)
            WHERE p.id <> x.primeiro AND p.origem = 'nota10' AND p.possivel_duplicado = 0");
        self::somar($rel, 'possiveis_duplicados', $n);
        // última compra, intervalo médio e tipo (lead que comprou vira cliente)
        Banco::executar("UPDATE clientes c JOIN (SELECT cliente_id, MAX(DATE(criado_em)) AS u, MIN(DATE(criado_em)) AS p, COUNT(*) AS n
                FROM pedidos WHERE situacao = 'faturado' GROUP BY cliente_id) x ON x.cliente_id = c.id
            SET c.ultima_compra_em = x.u, c.intervalo_medio_dias = IF(x.n > 1, ROUND(DATEDIFF(x.u, x.p) / (x.n - 1)), NULL), c.tipo = IF(c.tipo = 'lead', 'cliente', c.tipo)
            WHERE c.nota10_numero IS NOT NULL");
        // crédito: título vencido em aberto (de qualquer empresa do grupo) bloqueia o boleto
        Banco::executar("UPDATE clientes c SET c.credito_situacao = IF(EXISTS (SELECT 1 FROM titulos_receber t WHERE t.cliente_id = c.id AND t.situacao = 'aberto'
                AND t.vencimento < CURDATE()), 'atraso', 'ok') WHERE c.nota10_numero IS NOT NULL");
        $rel['clientes_boleto_bloqueado'] = (int) Banco::valor("SELECT COUNT(*) FROM clientes WHERE nota10_numero IS NOT NULL AND credito_situacao = 'atraso'");
        // carteiras: cada cliente na carteira do seu responsável (ou da fila)
        \App\Services\CarteirasService::encaixar(null);
        $rel['entrega_para_revisar'] = (int) Banco::valor('SELECT COUNT(*) FROM clientes WHERE entrega_revisar IS NOT NULL AND nota10_numero IS NOT NULL');
        $rel['itens_sem_nota_guardados'] = (int) Banco::valor('SELECT COUNT(*) FROM n10_itensnota i LEFT JOIN n10_notas n ON n.GUID_Nota = i.GUID_Nota WHERE n.id IS NULL');
        // o que está no One agora (vale também quando a importação roda de novo)
        $conta = fn(string $sql) => (int) Banco::valor($sql);
        $rel['no_one'] = [
            'clientes' => ['Clientes', $conta("SELECT COUNT(*) FROM clientes WHERE nota10_numero IS NOT NULL AND tipo = 'cliente' AND excluido_em IS NULL")],
            'leads' => ['Leads (prospects)', $conta("SELECT COUNT(*) FROM clientes WHERE nota10_numero IS NOT NULL AND tipo = 'lead' AND excluido_em IS NULL")],
            'fila' => ['Na fila de Prospecção e Reativação (sem responsável)', $conta('SELECT COUNT(*) FROM clientes WHERE nota10_numero IS NOT NULL AND responsavel_id IS NULL AND excluido_em IS NULL')],
            'fornecedores' => ['Fornecedores e transportadoras', $conta('SELECT COUNT(*) FROM fornecedores WHERE nota10_numero IS NOT NULL')],
            'funcionarios' => ['Funcionários criados pela importação', $conta("SELECT COUNT(*) FROM usuarios WHERE email LIKE 'nota10-%@funcionario.pendente'")],
            'produtos' => ['Produtos ligados ao Nota10', $conta('SELECT COUNT(*) FROM produtos WHERE nota10_codigo IS NOT NULL')],
            'materias' => ['Matérias-primas', $conta('SELECT COUNT(*) FROM materias_primas WHERE nota10_codigo IS NOT NULL')],
            'faturados' => ['Pedidos faturados', $conta("SELECT COUNT(*) FROM pedidos WHERE origem = 'nota10' AND situacao = 'faturado'")],
            'historico' => ['Pedidos só de histórico (orçamentos, devoluções, sem nota...)', $conta("SELECT COUNT(*) FROM pedidos WHERE origem = 'nota10' AND situacao = 'historico'")],
            'cancelados' => ['Pedidos cancelados', $conta("SELECT COUNT(*) FROM pedidos WHERE origem = 'nota10' AND situacao = 'cancelado'")],
            'outra' => ['Pedidos de outras empresas do grupo', $conta("SELECT COUNT(*) FROM pedidos WHERE origem = 'nota10' AND situacao = 'outra_empresa'")],
            'itens' => ['Itens de pedido', $conta("SELECT COUNT(*) FROM pedido_itens WHERE nota10_guid IS NOT NULL")],
            'bonificados' => ['Itens bonificados (juntados ao pedido)', $conta("SELECT COUNT(*) FROM pedido_itens WHERE nota10_guid IS NOT NULL AND bonificado = 1")],
            'titulos' => ['Títulos a receber', $conta("SELECT COUNT(*) FROM titulos_receber WHERE origem = 'nota10'")],
            'abertos' => ['Títulos em aberto e vencidos', $conta("SELECT COUNT(*) FROM titulos_receber WHERE origem = 'nota10' AND situacao = 'aberto' AND vencimento < CURDATE()")],
            'boleto' => ['Clientes com boleto bloqueado', $rel['clientes_boleto_bloqueado']],
            'cobranca' => ['Casos abertos na Cobrança (títulos vencidos)', $conta("SELECT COUNT(*) FROM cobrancas WHERE origem = 'nota10' AND situacao = 'aberta'")],
            'compras' => ['Compras de fornecedores', $conta("SELECT COUNT(*) FROM compras_lancadas WHERE origem = 'nota10'")],
            'linha' => ['Follow-ups na linha do tempo', $conta("SELECT COUNT(*) FROM cliente_eventos WHERE canal = 'Nota10'")],
            'entrega' => ['Endereços de entrega para revisar', $rel['entrega_para_revisar']],
        ];
        $rel['faturado_total'] = (float) Banco::valor("SELECT COALESCE(SUM(total), 0) FROM pedidos WHERE origem = 'nota10' AND situacao = 'faturado'");
        $rel['faturado_por_ano'] = array_column(self::linhas("SELECT YEAR(criado_em) AS ano, ROUND(SUM(total), 2) AS v FROM pedidos WHERE origem = 'nota10' AND situacao = 'faturado' GROUP BY 1 ORDER BY 1"), 'v', 'ano');
        return null;
    }
}
