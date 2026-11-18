<?php
namespace App\Services\Nota10;

use App\Core\Banco;

/**
 * Conferência do backup do Nota10 já lido na área separada (tabelas n10_), ANTES de gravar qualquer coisa no One:
 * quantidades, período, problemas encontrados e as ligações (vendedor do Nota10 → pessoa do One, tabelas de preço,
 * formas de pagamento, segmentos e o tipo de cada cadastro: cliente, fornecedor, transportadora...).
 *
 * O Nota10 muda de versão: toda consulta confere antes se a tabela e a coluna existem no backup recebido.
 */
class Conferencia
{
    /** Escolhas possíveis para cada tipo de cadastro do Nota10 */
    public const TIPOS_CADASTRO = ['cliente' => 'Cliente (vai para a Carteira)', 'fornecedor' => 'Fornecedor (Compras › Fornecedores)',
        'transportadora' => 'Transportadora', 'ignorar' => 'Não trazer'];

    private static ?array $tabelas = null;
    private static array $colunas = [];

    // ------------------------------------------------------------------ apoio

    public static function existe(string $tabela): bool
    {
        self::$tabelas ??= Leitor::tabelasDaArea();
        return in_array(Leitor::PREFIXO . $tabela, self::$tabelas, true);
    }

    /** Nome real da coluna (o Nota10 mistura maiúsculas), ou null se ela não existe neste backup */
    public static function col(string $tabela, string $coluna): ?string
    {
        if (!self::existe($tabela)) {
            return null;
        }
        if (!isset(self::$colunas[$tabela])) {
            self::$colunas[$tabela] = [];
            foreach (Banco::todos('SHOW COLUMNS FROM `' . Leitor::PREFIXO . $tabela . '`') as $c) {
                self::$colunas[$tabela][strtolower($c['Field'])] = $c['Field'];
            }
        }
        return self::$colunas[$tabela][strtolower($coluna)] ?? null;
    }

    /** Data guardada como texto no Nota10 (aaaa-mm-dd ou dd/mm/aaaa, às vezes com hora) → DATE no SQL */
    public static function data(string $expr): string
    {
        return "COALESCE(STR_TO_DATE(LEFT(NULLIF(TRIM($expr), ''), 10), '%Y-%m-%d'), STR_TO_DATE(LEFT(NULLIF(TRIM($expr), ''), 10), '%d/%m/%Y'))";
    }

    /** Só os dígitos de um CNPJ/CPF (sem REGEXP_REPLACE, que o MySQL 5.7 não tem) */
    public static function soDigitos(string $expr): string
    {
        return "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE($expr, ''), '.', ''), '/', ''), '-', ''), ' ', ''), ',', '')";
    }

    private static function contar(string $tabela, string $onde = '1'): int
    {
        return self::existe($tabela) ? (int) Banco::valor('SELECT COUNT(*) FROM `' . Leitor::PREFIXO . "$tabela` WHERE $onde") : 0;
    }

    /** Condição SQL: só os cadastros cujo tipo vira cliente (pela ligação escolhida, ou pelo palpite) */
    public static function soClientes(string $alias = ''): string
    {
        $tipo = self::col('clientes', 'TipoCliente');
        if (!$tipo) {
            return '1';
        }
        $salvo = self::mapas()['tipo_cadastro'] ?? [];
        $tipos = [];
        foreach (Banco::todos("SELECT DISTINCT COALESCE(NULLIF(TRIM(`$tipo`), ''), '(vazio)') AS t FROM n10_clientes") as $l) {
            if (($salvo[$l['t']] ?? self::palpiteTipo($l['t'])) === 'cliente') {
                $tipos[] = Banco::pdo()->quote($l['t']);
            }
        }
        return $tipos ? "COALESCE(NULLIF(TRIM($alias`$tipo`), ''), '(vazio)') IN (" . implode(',', $tipos) . ')' : '0';
    }

    /** Venda, bonificação, devolução, remessa... pelo CFOP (e pela finalidade da NF-e: 4 = devolução) */
    public static function tipoOperacao(?string $cfop, $finalidade = null): string
    {
        $c = preg_replace('/\D/', '', (string) $cfop);
        if ((int) $finalidade === 4) {
            return 'devolucao';
        }
        if (strlen($c) !== 4) {
            return 'sem_cfop';
        }
        $resto = (int) substr($c, 1);
        if (in_array($c[0], ['1', '2', '3'], true)) {
            return in_array($resto, [201, 202, 203, 204, 410, 411, 660, 661, 662], true) ? 'devolucao' : 'entrada';
        }
        if (($resto >= 101 && $resto <= 125) || ($resto >= 401 && $resto <= 405) || in_array($resto, [501, 502], true)) {
            return 'venda';
        }
        if (in_array($resto, [910, 911], true)) {
            return 'bonificacao';
        }
        if (in_array($resto, [201, 202, 410, 411, 413, 553, 556], true)) {
            return 'devolucao_compra';
        }
        return $resto >= 900 ? 'remessa' : 'outra';
    }

    public const OPERACOES = ['venda' => 'Venda', 'bonificacao' => 'Bonificação / amostra', 'devolucao' => 'Devolução de cliente',
        'remessa' => 'Remessa (industrialização, conserto, outras)', 'entrada' => 'Entrada (compra)', 'devolucao_compra' => 'Devolução a fornecedor',
        'outra' => 'Outra operação', 'sem_cfop' => 'Sem CFOP (pedido sem nota)'];

    // ------------------------------------------------------------------ resumo

    /** Quantas linhas de cada tabela vieram no backup */
    public static function tabelas(): array
    {
        $r = [];
        foreach (Leitor::TABELAS as $t) {
            $r[$t] = self::existe($t) ? (int) Banco::valor('SELECT COUNT(*) FROM `' . Leitor::PREFIXO . "$t`") : null;
        }
        return $r;
    }

    public static function clientes(): array
    {
        if (!self::existe('clientes')) {
            return [];
        }
        $doc = self::col('clientes', 'CPF_CNPJ');
        $tipo = self::col('clientes', 'TipoCliente');
        $rep = self::col('clientes', 'Representante');
        $ativo = self::col('clientes', 'Ativo');
        $digitos = $doc ? self::soDigitos("`$doc`") : "''";
        $r = [
            'total' => self::contar('clientes'),
            'por_tipo' => $tipo ? Banco::todos("SELECT COALESCE(NULLIF(TRIM(`$tipo`), ''), '(vazio)') AS tipo, COUNT(*) AS qtd FROM n10_clientes GROUP BY 1 ORDER BY qtd DESC") : [],
            'inativos' => $ativo ? self::contar('clientes', "UPPER(`$ativo`) = 'N'") : 0,
            'com_cnpj' => self::contar('clientes', "LENGTH($digitos) = 14"),
            'com_cpf' => self::contar('clientes', "LENGTH($digitos) = 11"),
            'sem_documento' => self::contar('clientes', "LENGTH($digitos) NOT IN (11, 14)"),
            'cnpj_repetido' => (int) Banco::valor("SELECT COUNT(*) FROM (SELECT $digitos AS d FROM n10_clientes WHERE LENGTH($digitos) = 14 GROUP BY d HAVING COUNT(*) > 1) x"),
            'sem_vendedor' => $rep ? self::contar('clientes', "COALESCE(`$rep`, 0) = 0 AND " . self::soClientes()) : null,
        ];
        // Endereço de entrega: nos campos próprios do Nota10, ou só escrito na observação (vai para a revisão; o One não adivinha)
        $entEnd = self::col('clientes', 'entrega_Endereco');
        $r['entrega_campos'] = $entEnd ? self::contar('clientes', "COALESCE(TRIM(`$entEnd`), '') <> '' AND " . self::soClientes()) : 0;
        $r['entrega_na_obs'] = 0;
        $obs = array_filter([self::col('clientes', 'Obs'), self::col('clientes', 'Ocorrencias'), self::col('clientes', 'Refer')]);
        if ($obs) {
            $consulta = Banco::pdo()->query('SELECT ' . implode(', ', array_map(fn($c) => "`$c`", $obs)) . ' FROM n10_clientes WHERE '
                . ($entEnd ? "COALESCE(TRIM(`$entEnd`), '') = '' AND " : '') . self::soClientes(), \PDO::FETCH_NUM);
            while ($l = $consulta->fetch()) {
                if (\App\Services\EntregaService::trechoNaObservacao(implode("\n", array_filter($l)))) {
                    $r['entrega_na_obs']++;
                }
            }
        }
        $r['ja_no_one'] = (int) Banco::valor("SELECT COUNT(DISTINCT c.id) FROM clientes c JOIN n10_clientes n ON c.cnpj = $digitos
            WHERE c.excluido_em IS NULL AND LENGTH($digitos) = 14 AND " . self::soClientes('n.'));
        return $r;
    }

    public static function notas(): array
    {
        if (!self::existe('notas')) {
            return [];
        }
        $data = self::col('notas', 'Data') ? self::data('n.`' . self::col('notas', 'Data') . '`') : 'NULL';
        $total = self::col('notas', 'TotalNota') ? 'n.`' . self::col('notas', 'TotalNota') . '`' : '0';
        $cfop = self::col('notas', 'CFOP');
        $fin = self::col('notas', 'finNFe');
        $cancel = self::col('notas', 'PedidoCancelado');
        $cli = self::col('notas', 'NumeroCliente');
        $r = ['total' => self::contar('notas'),
            'canceladas' => $cancel ? self::contar('notas', "COALESCE(`$cancel`, 0) <> 0") : 0,
            'periodo' => Banco::um("SELECT MIN($data) AS de, MAX($data) AS ate FROM n10_notas n"),
            'sem_data' => (int) Banco::valor("SELECT COUNT(*) FROM n10_notas n WHERE $data IS NULL")];
        // por operação (classificada pelo CFOP)
        $ops = [];
        foreach (Banco::todos('SELECT ' . ($cfop ? "n.`$cfop`" : 'NULL') . ' AS cfop, ' . ($fin ? "n.`$fin`" : 'NULL') . " AS fin, COUNT(*) AS qtd, SUM($total) AS valor
                FROM n10_notas n " . ($cancel ? "WHERE COALESCE(n.`$cancel`, 0) = 0 " : '') . 'GROUP BY cfop, fin') as $l) {
            $tipo = self::tipoOperacao($l['cfop'], $l['fin']);
            $ops[$tipo] ??= ['tipo' => $tipo, 'rotulo' => self::OPERACOES[$tipo], 'qtd' => 0, 'valor' => 0.0];
            $ops[$tipo]['qtd'] += (int) $l['qtd'];
            $ops[$tipo]['valor'] += (float) $l['valor'];
        }
        usort($ops, fn($a, $b) => $b['valor'] <=> $a['valor']);
        $r['por_operacao'] = $ops;
        // por ano (só o que não foi cancelado)
        $r['por_ano'] = Banco::todos("SELECT YEAR($data) AS ano, COUNT(*) AS qtd, SUM($total) AS valor FROM n10_notas n
            WHERE $data IS NOT NULL " . ($cancel ? "AND COALESCE(n.`$cancel`, 0) = 0 " : '') . 'GROUP BY ano ORDER BY ano');
        // a "situação" do Nota10 (para entender como ele marca pedido, orçamento e nota)
        $campos = array_filter(['StatusPedido', 'Status_NF', 'Prevenda'], fn($c) => self::col('notas', $c));
        $r['situacoes'] = $campos ? Banco::todos('SELECT ' . implode(', ', array_map(fn($c) => 'COALESCE(`' . self::col('notas', $c) . "`, '') AS `$c`", $campos))
            . ', COUNT(*) AS qtd FROM n10_notas GROUP BY ' . implode(', ', array_map(fn($c) => "`$c`", $campos)) . ' ORDER BY qtd DESC LIMIT 12') : [];
        $r['sem_cliente'] = $cli && self::col('clientes', 'Numero')
            ? (int) Banco::valor("SELECT COUNT(*) FROM n10_notas n LEFT JOIN n10_clientes c ON c.Numero = n.`$cli` WHERE c.id IS NULL") : null;
        return $r;
    }

    public static function itens(): array
    {
        if (!self::existe('itensnota')) {
            return [];
        }
        $prod = self::col('itensnota', 'CodigoProduto');
        $r = ['total' => self::contar('itensnota')];
        $r['sem_nota'] = self::existe('notas') ? (int) Banco::valor('SELECT COUNT(*) FROM n10_itensnota i LEFT JOIN n10_notas n ON n.GUID_Nota = i.GUID_Nota WHERE n.id IS NULL') : null;
        $r['produto_desconhecido'] = $prod && self::existe('produtos')
            ? (int) Banco::valor("SELECT COUNT(DISTINCT i.`$prod`) FROM n10_itensnota i LEFT JOIN n10_produtos p ON p.CodigoProduto = i.`$prod` WHERE p.id IS NULL") : null;
        $r['com_lote'] = self::col('itensnota', 'Lote') ? self::contar('itensnota', "COALESCE(Lote, '') <> ''") : 0;
        $r['com_custo'] = self::col('itensnota', 'CustoProduto') ? self::contar('itensnota', 'COALESCE(CustoProduto, 0) > 0') : 0;
        return $r;
    }

    public static function produtos(): array
    {
        if (!self::existe('produtos')) {
            return [];
        }
        $ativo = self::col('produtos', 'Ativo');
        return ['total' => self::contar('produtos'),
            'inativos' => $ativo ? self::contar('produtos', "COALESCE(`$ativo`, 1) IN (0, '0', 'N')") : 0,
            'no_one' => (int) Banco::valor('SELECT COUNT(*) FROM n10_produtos n JOIN produtos p ON p.codigo = TRIM(n.CodigoProduto)'),
            'novos' => (int) Banco::valor('SELECT COUNT(*) FROM n10_produtos n LEFT JOIN produtos p ON p.codigo = TRIM(n.CodigoProduto) WHERE p.id IS NULL'),
            'one_sem_nota10' => (int) Banco::valor('SELECT COUNT(*) FROM produtos p LEFT JOIN n10_produtos n ON TRIM(n.CodigoProduto) = p.codigo WHERE n.id IS NULL AND p.cliente_exclusivo_id IS NULL')];
    }

    public static function titulos(): array
    {
        if (!self::existe('contaspagrec')) {
            return [];
        }
        $tipo = self::col('contaspagrec', 'Tipo');
        $venc = self::col('contaspagrec', 'DataVencimento');
        $pago = self::col('contaspagrec', 'dataPagamento');
        $cancel = self::col('contaspagrec', 'Cancelado');
        $valor = self::col('contaspagrec', 'ValorLiquido') ?: self::col('contaspagrec', 'ValorBruto');
        $r = ['total' => self::contar('contaspagrec'),
            'por_tipo' => $tipo ? Banco::todos("SELECT COALESCE(NULLIF(TRIM(`$tipo`), ''), '(vazio)') AS tipo, COUNT(*) AS qtd, SUM(" . ($valor ? "`$valor`" : '0') . ") AS valor
                FROM n10_contaspagrec GROUP BY 1 ORDER BY qtd DESC") : []];
        if ($tipo && $venc && $pago) {
            $aberto = "UPPER(`$tipo`) = 'R' AND " . self::data("`$pago`") . ' IS NULL' . ($cancel ? " AND COALESCE(`$cancel`, 0) = 0" : '');
            $r['abertos'] = self::contar('contaspagrec', $aberto);
            $r['vencidos'] = self::contar('contaspagrec', "$aberto AND " . self::data("`$venc`") . ' < CURDATE()');
            $r['valor_vencido'] = (float) Banco::valor("SELECT COALESCE(SUM(" . ($valor ? "`$valor`" : '0') . "), 0) FROM n10_contaspagrec WHERE $aberto AND " . self::data("`$venc`") . ' < CURDATE()');
        }
        return $r;
    }

    public static function outros(): array
    {
        $prox = self::col('followup', 'proximocontato');
        $fech = self::col('followup', 'datafechamento');
        return ['contatos' => self::contar('contatos'), 'followup' => self::contar('followup'),
            'agendados' => $prox ? self::contar('followup', self::data("`$prox`") . ' >= CURDATE()' . ($fech ? ' AND ' . self::data("`$fech`") . ' IS NULL' : '')) : 0,
            'producao' => self::contar('producao'), 'composicao' => self::contar('composicao'), 'estoques' => self::contar('estoques'),
            'compras' => self::contar('compras'), 'listas' => self::contar('emails')];
    }

    /** Avisos para a tela: [nível (alerta|info), texto] */
    public static function problemas(array $cli, array $notas, array $itens, array $prod): array
    {
        $p = [];
        if (!$cli) {
            $p[] = ['alerta', 'O backup não tem a tabela de clientes. Confira se é o banco do Nota10 inteiro.'];
        }
        if (!$notas) {
            $p[] = ['alerta', 'O backup não tem a tabela de notas/pedidos.'];
        }
        if (($cli['cnpj_repetido'] ?? 0) > 0) {
            $p[] = ['alerta', $cli['cnpj_repetido'] . ' CNPJ(s) aparecem em mais de um cadastro. Na importação, eles viram uma loja só (os pedidos de todos os cadastros vão para ela).'];
        }
        if (($cli['sem_documento'] ?? 0) > 0) {
            $p[] = ['info', $cli['sem_documento'] . ' cadastro(s) sem CNPJ nem CPF válido. Entram assim mesmo; a ficha mostra "Validar cadastro".'];
        }
        if (($cli['entrega_na_obs'] ?? 0) > 0) {
            $p[] = ['info', $cli['entrega_na_obs'] . ' cliente(s) com endereço de entrega escrito só na observação. Entram marcados para revisar em Carteira › Endereços de entrega (o One não adivinha).'];
        }
        if (($cli['sem_vendedor'] ?? 0) > 0) {
            $p[] = ['info', $cli['sem_vendedor'] . ' cliente(s) sem vendedor no Nota10. Vão para a fila de Prospecção e Reativação.'];
        }
        if (($notas['sem_cliente'] ?? 0) > 0) {
            $p[] = ['alerta', $notas['sem_cliente'] . ' pedido(s)/nota(s) de cliente que não está no cadastro (provavelmente consumidor final no balcão). Não entram.'];
        }
        if (($notas['sem_data'] ?? 0) > 0) {
            $p[] = ['info', $notas['sem_data'] . ' pedido(s) sem data. Não entram nos números por período.'];
        }
        if (($itens['sem_nota'] ?? 0) > 0) {
            $p[] = ['info', $itens['sem_nota'] . ' item(ns) sem a nota correspondente. Não entram.'];
        }
        if (($itens['produto_desconhecido'] ?? 0) > 0) {
            $p[] = ['info', $itens['produto_desconhecido'] . ' código(s) de produto vendidos que não estão no cadastro do Nota10. O item entra com o nome que está no pedido.'];
        }
        if (($prod['novos'] ?? 0) > 0) {
            $p[] = ['info', $prod['novos'] . ' produto(s) só existem no Nota10. Entram no One inativos e fora do catálogo, para o histórico ficar completo.'];
        }
        return $p;
    }

    // ------------------------------------------------------------------ ligações (de-para)

    /** Ligações já escolhidas: ['vendedor' => ['12' => '5'], ...] */
    public static function mapas(): array
    {
        $r = [];
        foreach (Banco::todos('SELECT tipo, codigo, destino FROM nota10_mapas') as $m) {
            $r[$m['tipo']][$m['codigo']] = $m['destino'];
        }
        return $r;
    }

    /** Grava as ligações escolhidas na tela */
    public static function salvarMapas(array $entrada): int
    {
        $n = 0;
        foreach (['vendedor', 'tabela', 'forma', 'segmento', 'tipo_cadastro'] as $tipo) {
            foreach ((array) ($entrada[$tipo] ?? []) as $codigo => $destino) {
                $codigo = mb_substr((string) $codigo, 0, 40);
                $destino = mb_substr(trim((string) $destino), 0, 40);
                Banco::executar('INSERT INTO nota10_mapas (tipo, codigo, destino) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE destino = VALUES(destino)',
                    [$tipo, $codigo, $destino === '' ? null : $destino]);
                $n++;
            }
        }
        return $n;
    }

    /** Tudo o que precisa de ligação, com o nome no Nota10, quantos registros usam e um palpite de destino */
    public static function paraLigar(): array
    {
        $salvo = self::mapas();
        $r = ['tipo_cadastro' => [], 'vendedor' => [], 'tabela' => [], 'forma' => [], 'segmento' => []];

        // tipos de cadastro (cliente, fornecedor, transportadora...)
        foreach (self::clientes()['por_tipo'] ?? [] as $t) {
            $r['tipo_cadastro'][] = ['codigo' => $t['tipo'], 'nome' => $t['tipo'], 'qtd' => (int) $t['qtd'],
                'destino' => $salvo['tipo_cadastro'][$t['tipo']] ?? self::palpiteTipo($t['tipo'])];
        }

        // vendedores: o representante do cliente e o vendedor do pedido
        $usuariosOne = Banco::todos('SELECT id, nome FROM usuarios WHERE ativo = 1 ORDER BY nome');
        $codigos = [];
        if ($rep = self::col('clientes', 'Representante')) {
            foreach (Banco::todos("SELECT `$rep` AS c, COUNT(*) AS q FROM n10_clientes WHERE COALESCE(`$rep`, 0) <> 0 GROUP BY c") as $l) {
                $codigos[(string) $l['c']]['clientes'] = (int) $l['q'];
            }
        }
        if ($cv = self::col('notas', 'CodigoVendedor')) {
            foreach (Banco::todos("SELECT `$cv` AS c, COUNT(*) AS q FROM n10_notas WHERE COALESCE(`$cv`, 0) <> 0 GROUP BY c") as $l) {
                $codigos[(string) $l['c']]['pedidos'] = (int) $l['q'];
            }
        }
        foreach ($codigos as $codigo => $q) {
            $nome = self::nomeVendedor((string) $codigo);
            $r['vendedor'][] = ['codigo' => (string) $codigo, 'nome' => $nome, 'clientes' => $q['clientes'] ?? 0, 'pedidos' => $q['pedidos'] ?? 0,
                'destino' => $salvo['vendedor'][$codigo] ?? self::palpitePessoa($nome, $usuariosOne)];
        }
        usort($r['vendedor'], fn($a, $b) => [$b['clientes'], $b['pedidos']] <=> [$a['clientes'], $a['pedidos']]);

        // tabelas de preço
        if (self::col('tabelaprecos', 'Numero')) {
            $tabelasOne = Banco::todos('SELECT id, nome, slug FROM tabelas_preco');
            foreach (Banco::todos('SELECT Numero AS codigo, Nome AS nome FROM n10_tabelaprecos ORDER BY Numero') as $t) {
                $r['tabela'][] = ['codigo' => (string) $t['codigo'], 'nome' => (string) $t['nome'],
                    'destino' => $salvo['tabela'][(string) $t['codigo']] ?? self::palpiteNome((string) $t['nome'], $tabelasOne, ['lojista', 'distrib', 'terceir'])];
            }
        }
        // formas de pagamento
        if (self::col('formapgto', 'Codigo')) {
            $formasOne = Banco::todos('SELECT id, nome, slug FROM formas_pagamento');
            foreach (Banco::todos('SELECT Codigo AS codigo, Nome AS nome FROM n10_formapgto ORDER BY Codigo') as $t) {
                $r['forma'][] = ['codigo' => (string) $t['codigo'], 'nome' => (string) $t['nome'],
                    'destino' => $salvo['forma'][(string) $t['codigo']] ?? self::palpiteNome((string) $t['nome'], $formasOne, ['pix', 'cart', 'bolet'])];
            }
        }
        // segmentos do cliente (IdSegmento); o nome vem da tabela de apoio quando existe
        if ($seg = self::col('clientes', 'IdSegmento')) {
            $segOne = Banco::todos('SELECT id, nome FROM segmentos WHERE ativo = 1');
            foreach (Banco::todos("SELECT `$seg` AS codigo, COUNT(*) AS qtd FROM n10_clientes WHERE COALESCE(`$seg`, 0) <> 0 GROUP BY codigo ORDER BY qtd DESC") as $s) {
                $nome = self::nomeApoio('segm', (string) $s['codigo']) ?? 'Segmento ' . $s['codigo'];
                $r['segmento'][] = ['codigo' => (string) $s['codigo'], 'nome' => $nome, 'qtd' => (int) $s['qtd'],
                    'destino' => $salvo['segmento'][(string) $s['codigo']] ?? self::palpiteSegmento($nome, $segOne)];
            }
        }
        return $r;
    }

    private static function nomeVendedor(string $codigo): string
    {
        if (self::existe('usuarios')) {
            $n = Banco::valor('SELECT nome FROM n10_usuarios WHERE ' . (self::col('usuarios', 'Numero') ? 'Numero = ? OR ' : '') . 'id = ? ORDER BY ' . (self::col('usuarios', 'Numero') ? '(Numero = ?) DESC' : 'id') . ' LIMIT 1',
                self::col('usuarios', 'Numero') ? [$codigo, $codigo, $codigo] : [$codigo]);
            if ($n) {
                return (string) $n;
            }
        }
        if (self::col('clientes', 'Numero')) {
            $n = Banco::valor('SELECT COALESCE(NULLIF(Nome, \'\'), Razao) FROM n10_clientes WHERE Numero = ? LIMIT 1', [$codigo]);
            if ($n) {
                return (string) $n;
            }
        }
        return 'Vendedor ' . $codigo;
    }

    private static function nomeApoio(string $parteTabela, string $codigo): ?string
    {
        if (!self::existe('apoio')) {
            return null;
        }
        $n = Banco::valor("SELECT descricao FROM n10_apoio WHERE codigo = ? AND LOWER(tabela) LIKE ? LIMIT 1", [$codigo, "%$parteTabela%"]);
        return $n ? (string) $n : null;
    }

    private static function palpiteTipo(string $tipo): string
    {
        $t = mb_strtoupper(trim($tipo));
        return match (true) {
            str_starts_with($t, 'F') => 'fornecedor',
            str_starts_with($t, 'T') => 'transportadora',
            str_starts_with($t, 'R'), str_starts_with($t, 'V'), str_starts_with($t, 'U') => 'ignorar',
            default => 'cliente',
        };
    }

    private static function semAcento(string $s): string
    {
        return \App\Services\Normalizar::semAcento(mb_strtolower($s));
    }

    /** Palpite de pessoa do One pelo primeiro nome */
    private static function palpitePessoa(string $nome, array $pessoas): ?string
    {
        $primeiro = explode(' ', self::semAcento(trim($nome)))[0] ?? '';
        if (strlen($primeiro) < 3) {
            return null;
        }
        foreach ($pessoas as $p) {
            if (explode(' ', self::semAcento($p['nome']))[0] === $primeiro) {
                return (string) $p['id'];
            }
        }
        return null;
    }

    private static function palpiteNome(string $nome, array $opcoes, array $chaves): ?string
    {
        $n = self::semAcento($nome);
        foreach ($chaves as $k) {
            if (str_contains($n, $k)) {
                foreach ($opcoes as $o) {
                    if (str_contains(self::semAcento($o['slug'] . ' ' . $o['nome']), $k)) {
                        return (string) $o['id'];
                    }
                }
            }
        }
        return null;
    }

    private static function palpiteSegmento(string $nome, array $segmentos): ?string
    {
        $n = self::semAcento($nome);
        $regras = ['farm' => 'farm', 'natur' => 'natur', 'suplem' => 'suplem', 'empor' => 'natur', 'academ' => 'suplem'];
        foreach ($regras as $tem => $alvo) {
            if (str_contains($n, $tem)) {
                foreach ($segmentos as $s) {
                    if (str_contains(self::semAcento($s['nome']), $alvo)) {
                        return (string) $s['id'];
                    }
                }
            }
        }
        return null;
    }
}
