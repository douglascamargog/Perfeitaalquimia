<?php
namespace App\Services;

use App\Core\Auditoria;
use App\Core\Auth;
use App\Core\Banco;
use RuntimeException;

/**
 * Desenvolvimento de Produto: do briefing ao lançamento, em etapas feitas por funções diferentes
 * (Técnica, Marketing, Qualidade, Produção). O projeto cria o produto logo no início (oculto: ativo = 0),
 * e cada área preenche nas telas de sempre (Produtos › Composição, Ficha técnica, Embalagens, Documentos).
 * Cada etapa tem uma checagem automática: só conclui quando o necessário está pronto.
 */
class DesenvolvimentoService
{
    public const CHECAGENS = [
        'ficha' => 'Ficha técnica com as matérias-primas', 'composicao' => 'Composição (ingredientes, porção e tabela nutricional)',
        'codigo' => 'EAN e NCM', 'arte' => 'Arte do rótulo enviada', 'compras' => 'Compras marcadas', 'grafica' => 'Pedido à gráfica',
        'aprovacao' => 'Prova aprovada', 'recebimento' => 'Rótulos recebidos', 'piloto' => 'Lote piloto', 'lancamento' => 'Lançamento',
    ];

    /** Tipos de embalagem do "jogo": cada um tem o seu modelo 3D (public/assets/js/dp-jogo.js) */
    public const TIPOS = ['pote' => 'Pote', 'frasco' => 'Frasco', 'caixinha' => 'Caixinha'];

    /** Cores da embalagem do mockup (a tampa é qualquer cor) */
    public const CORES = ['branco' => 'Branco', 'preto' => 'Preto', 'ambar' => 'Âmbar'];

    /** Tom de cada cor (para os desenhos) */
    public const HEX = ['branco' => '#f3f5f4', 'preto' => '#1d1f1e', 'ambar' => '#8a4a12'];

    /** Estilo com as cores do projeto para os desenhos SVG (cartões, escolha e reserva do 3D) */
    public static function estiloCores(array $p): string
    {
        [$cor, $tampa] = self::cores((string) ($p['tipo'] ?? 'pote'), (string) ($p['cor_embalagem'] ?? ''), (string) ($p['cor_tampa'] ?? ''));
        return '--cor-pote: ' . self::HEX[$cor] . '; --cor-tampa: ' . $tampa . ';';
    }

    /** Cores de tampa sugeridas (a pessoa pode escolher outra) */
    public const TAMPAS = ['#14473a' => 'Verde Perfeita Alquimia', '#111111' => 'Preta', '#f4f4f2' => 'Branca', '#c9a227' => 'Dourada',
        '#b3261e' => 'Vermelha', '#1f4e9c' => 'Azul', '#6b3fa0' => 'Roxa', '#e07a1f' => 'Laranja', '#8bc34a' => 'Verde-claro', '#e46aa0' => 'Rosa'];

    /** Peça que cada etapa acrescenta na embalagem (pela checagem, para continuar certo se o admin renomear as etapas) */
    public const PECAS = [
        'arte' => 'a arte do rótulo na embalagem', 'aprovacao' => 'o selo de aprovado', 'piloto' => 'o lacre de produto pronto',
        'lancamento' => 'a festa do lançamento',
    ];

    // ------------------------------------------------------------------
    // Consulta
    // ------------------------------------------------------------------

    public static function etapas(bool $soAtivas = true): array
    {
        return Banco::todos('SELECT e.*, f.nome AS funcao FROM dp_etapas e LEFT JOIN funcoes f ON f.slug = e.funcao_slug'
            . ($soAtivas ? ' WHERE e.ativa = 1' : '') . ' ORDER BY e.ordem, e.id');
    }

    /** Projetos (com etapa, produto e cliente). $f: tipo (nosso|terceirizacao), situacao, atrasados */
    public static function projetos(array $f = []): array
    {
        $w = ["p.situacao IN ('andamento','pausado')"];
        if (($f['situacao'] ?? '') === 'todos') {
            $w = ['1 = 1'];
        } elseif (($f['situacao'] ?? '') === 'lancado') {
            $w = ["p.situacao = 'lancado'"];
        }
        if (($f['tipo'] ?? '') === 'nosso') {
            $w[] = 'p.cliente_id IS NULL';
        } elseif (($f['tipo'] ?? '') === 'terceirizacao') {
            $w[] = 'p.cliente_id IS NOT NULL';
        }
        if (!empty($f['atrasados'])) {
            $w[] = 'p.prazo < CURDATE()';
        }
        return Banco::todos('SELECT p.*, e.nome AS etapa, e.ordem AS etapa_ordem, e.funcao_slug, f.nome AS funcao, pr.slug AS produto_slug,
                COALESCE(NULLIF(c.nome_fantasia, \'\'), c.razao_social) AS cliente, u.nome AS criado_por_nome
            FROM dp_projetos p LEFT JOIN dp_etapas e ON e.id = p.etapa_id LEFT JOIN funcoes f ON f.slug = e.funcao_slug
            LEFT JOIN produtos pr ON pr.id = p.produto_id LEFT JOIN clientes c ON c.id = p.cliente_id LEFT JOIN usuarios u ON u.id = p.criado_por
            WHERE ' . implode(' AND ', $w) . ' ORDER BY p.prazo IS NULL, p.prazo, p.id');
    }

    public static function buscar(int $id): ?array
    {
        $p = Banco::um('SELECT p.*, e.nome AS etapa, e.ordem AS etapa_ordem, e.funcao_slug, e.checagem, e.aprovadores, f.nome AS funcao,
                pr.slug AS produto_slug, pr.nome AS produto_nome, pr.codigo AS produto_codigo, cat.nome AS categoria,
                COALESCE(NULLIF(c.nome_fantasia, \'\'), c.razao_social) AS cliente
            FROM dp_projetos p LEFT JOIN dp_etapas e ON e.id = p.etapa_id LEFT JOIN funcoes f ON f.slug = e.funcao_slug
            LEFT JOIN produtos pr ON pr.id = p.produto_id LEFT JOIN categorias cat ON cat.id = p.categoria_id LEFT JOIN clientes c ON c.id = p.cliente_id
            WHERE p.id = ?', [$id]);
        if ($p) {
            $p['compras_ok'] = json_decode((string) $p['compras_ok'], true) ?: [];
        }
        return $p;
    }

    /** Linha das etapas do projeto: cada etapa com situação, quem concluiu e quando */
    public static function linhaDoProjeto(int $id): array
    {
        return Banco::todos('SELECT e.id, e.nome, e.ordem, e.funcao_slug, e.checagem, e.aprovadores, e.paralela, f.nome AS funcao, pe.situacao, pe.concluida_em, pe.observacao, u.nome AS concluida_por
            FROM dp_etapas e LEFT JOIN dp_projeto_etapas pe ON pe.etapa_id = e.id AND pe.projeto_id = ? LEFT JOIN funcoes f ON f.slug = e.funcao_slug
            LEFT JOIN usuarios u ON u.id = pe.concluida_por WHERE e.ativa = 1 ORDER BY e.ordem, e.id', [$id]);
    }

    public static function historico(int $id): array
    {
        return Banco::todos('SELECT h.*, u.nome AS usuario FROM dp_historico h LEFT JOIN usuarios u ON u.id = h.usuario_id WHERE h.projeto_id = ? ORDER BY h.id DESC LIMIT 200', [$id]);
    }

    /**
     * Etapas em andamento agora: a da sequência e as que andam "ao mesmo tempo" (paralela = 1: Formulação, Rotulagem,
     * Criação do rótulo, Compras...). Cada item é o projeto "visto" naquela etapa
     * (etapa_id, etapa, checagem, funcao_slug, aprovadores...), pronto para pendencias(), podeConcluir() e concluir().
     */
    public static function atuais(array $p): array
    {
        $etapas = Banco::todos("SELECT e.id, e.nome, e.ordem, e.funcao_slug, e.checagem, e.aprovadores, f.nome AS funcao
            FROM dp_projeto_etapas pe JOIN dp_etapas e ON e.id = pe.etapa_id AND e.ativa = 1 LEFT JOIN funcoes f ON f.slug = e.funcao_slug
            WHERE pe.projeto_id = ? AND pe.situacao = 'andamento' ORDER BY e.ordem, e.id", [(int) $p['id']]);
        return array_map(fn($e) => self::naEtapa($p, $e), $etapas);
    }

    public static function naEtapa(array $p, array $e): array
    {
        return array_merge($p, ['etapa_id' => (int) $e['id'], 'etapa' => $e['nome'], 'etapa_ordem' => (int) $e['ordem'], 'funcao_slug' => $e['funcao_slug'],
            'checagem' => $e['checagem'], 'aprovadores' => $e['aprovadores'], 'funcao' => $e['funcao']]);
    }

    /** Etapas em andamento de vários projetos (para o quadro): [projeto_id => [etapa_id, ...]] */
    public static function ativasDe(array $ids): array
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        $r = [];
        if ($ids) {
            foreach (Banco::todos("SELECT projeto_id, etapa_id FROM dp_projeto_etapas WHERE situacao = 'andamento' AND projeto_id IN (" . implode(',', $ids) . ')') as $x) {
                $r[(int) $x['projeto_id']][] = (int) $x['etapa_id'];
            }
        }
        return $r;
    }

    /** Projetos esperando uma etapa das funções da pessoa (ou a aprovação dela) */
    public static function minhas(?int $usuarioId = null): array
    {
        $funcoes = self::funcoesDe($usuarioId ?? (int) Auth::id());
        if (!$funcoes) {
            return [];
        }
        $lista = "'" . implode("','", array_map(fn($f) => preg_replace('/[^a-z0-9_]/', '', $f), $funcoes)) . "'";
        return array_values(array_filter(self::projetos(), function ($p) use ($funcoes) {
            if ($p['situacao'] !== 'andamento') {
                return false;
            }
            foreach (self::atuais($p) as $a) {
                if (in_array((string) $a['funcao_slug'], $funcoes, true) || self::aguardaAprovacaoDe($a, $funcoes)) {
                    return true;
                }
            }
            return false;
        }));
    }

    private static function funcoesDe(int $usuarioId): array
    {
        return array_column(Banco::todos('SELECT f.slug FROM usuario_funcoes uf JOIN funcoes f ON f.id = uf.funcao_id WHERE uf.usuario_id = ?', [$usuarioId]), 'slug');
    }

    private static function aguardaAprovacaoDe(array $p, array $funcoes): bool
    {
        $e = Banco::um('SELECT * FROM dp_etapas WHERE id = ?', [(int) $p['etapa_id']]);
        if (!$e || !$e['aprovadores']) {
            return false;
        }
        $faltam = array_diff(explode(',', $e['aprovadores']), array_column(self::aprovacoesDaRodada((int) $p['id'], (int) $e['id']), 'funcao_slug'));
        return (bool) array_intersect($faltam, $funcoes);
    }

    // ------------------------------------------------------------------
    // Criar
    // ------------------------------------------------------------------

    public static function criar(array $d, ?int $usuarioId): int
    {
        $nome = mb_substr(trim((string) ($d['nome'] ?? '')), 0, 160);
        if ($nome === '') {
            throw new RuntimeException('Dê um nome para o produto novo.');
        }
        $clienteId = (int) ($d['cliente_id'] ?? 0) ?: null;
        if ($clienteId && !Banco::valor('SELECT 1 FROM clientes WHERE id = ? AND excluido_em IS NULL', [$clienteId])) {
            throw new RuntimeException('Cliente de terceirização não encontrado.');
        }
        $etapas = self::etapas();
        if (!$etapas) {
            throw new RuntimeException('Cadastre as etapas do desenvolvimento em Configurações.');
        }
        $prazo = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($d['prazo'] ?? '')) ? $d['prazo'] : null;
        $categoria = (int) ($d['categoria_id'] ?? 0) ?: null;
        $embalagem = mb_substr(trim((string) ($d['embalagem'] ?? '')), 0, 120) ?: null;
        $tipo = isset(self::TIPOS[$d['tipo'] ?? '']) ? $d['tipo'] : 'pote';
        [$cor, $tampa] = self::cores($tipo, (string) ($d['cor_embalagem'] ?? ''), (string) ($d['cor_tampa'] ?? ''));
        return Banco::transacao(function () use ($nome, $d, $clienteId, $etapas, $prazo, $categoria, $embalagem, $tipo, $cor, $tampa, $usuarioId) {
            // O produto nasce oculto (fora do catálogo e da lista de Produtos) e vai sendo preenchido pelas áreas
            $produto = Banco::inserir('produtos', ['slug' => 'dev-' . bin2hex(random_bytes(5)), 'nome' => $nome, 'embalagem' => $embalagem,
                'categoria_id' => $categoria, 'ativo' => 0, 'visivel_catalogo' => 0, 'ordem' => 9999, 'ficha_json' => '{}']);
            $id = Banco::inserir('dp_projetos', ['nome' => $nome, 'briefing' => mb_substr(trim((string) ($d['briefing'] ?? '')), 0, 5000) ?: null,
                'categoria_id' => $categoria, 'embalagem' => $embalagem, 'tipo' => $tipo, 'cor_embalagem' => $cor, 'cor_tampa' => $tampa, 'cliente_id' => $clienteId, 'produto_id' => $produto,
                'etapa_id' => (int) $etapas[0]['id'], 'prazo' => $prazo, 'criado_por' => $usuarioId]);
            foreach ($etapas as $i => $e) {
                Banco::inserir('dp_projeto_etapas', ['projeto_id' => $id, 'etapa_id' => (int) $e['id'], 'situacao' => $i === 0 ? 'andamento' : 'pendente']);
            }
            self::registrar($id, $usuarioId, 'sistema', 'Projeto criado' . ($clienteId ? ' (terceirização)' : '') . '.');
            Auditoria::registrar('desenvolvimento', $id, 'criar', null, ['nome' => $nome, 'cliente' => $clienteId]);
            return $id;
        });
    }

    // ------------------------------------------------------------------
    // Etapas: o que falta, quem pode, concluir
    // ------------------------------------------------------------------

    /** O que ainda falta na etapa atual (lista de textos; vazia = pode concluir) */
    public static function pendencias(array $p): array
    {
        $prod = (int) $p['produto_id'];
        $f = $prod ? json_decode((string) Banco::valor('SELECT ficha_json FROM produtos WHERE id = ?', [$prod]), true) ?: [] : [];
        $t = $prod ? (Banco::um('SELECT * FROM produto_tecnico WHERE produto_id = ?', [$prod]) ?: []) : [];
        $falta = [];
        switch ((string) ($p['checagem'] ?? '')) {
            case 'ficha':
                if (!Banco::valor('SELECT 1 FROM ficha_tecnica ft JOIN materias_primas m ON m.id = ft.materia_prima_id WHERE ft.produto_id = ? AND m.tipo = \'materia_prima\' LIMIT 1', [$prod])) {
                    $falta[] = 'Cadastrar a ficha técnica (matérias-primas por unidade) na aba Ficha técnica do produto.';
                }
                break;
            case 'composicao':
                if (empty($f['ingredients'])) { $falta[] = 'Ingredientes (aba Composição).'; }
                if (empty($f['serving'])) { $falta[] = 'Porção (aba Composição).'; }
                if (empty($f['nutrition'])) { $falta[] = 'Tabela nutricional (aba Composição).'; }
                break;
            case 'codigo':
                if (empty($t['ean'])) { $falta[] = 'Código de barras (EAN).'; }
                if (empty($t['ncm'])) { $falta[] = 'NCM.'; }
                break;
            case 'arte':
                if (!self::temDocumento($prod, 'rotulo')) { $falta[] = 'Enviar a arte do rótulo.'; }
                break;
            case 'compras':
                $itens = array_column(Banco::todos('SELECT materia_prima_id FROM ficha_tecnica WHERE produto_id = ?', [$prod]), 'materia_prima_id');
                $faltam = array_diff(array_map('intval', $itens), array_map('intval', $p['compras_ok']));
                if (!$itens) { $falta[] = 'A ficha técnica ainda está vazia.'; }
                if ($faltam) { $falta[] = count($faltam) . ' item(ns) da ficha ainda sem compra marcada.'; }
                break;
            case 'grafica':
                $falta = array_merge($falta, self::esperando($p, ['arte']));
                if (empty($p['grafica']) || empty($p['grafica_pedido_em'])) { $falta[] = 'Informar a gráfica e a data do pedido.'; }
                break;
            case 'aprovacao':
                if (!self::temDocumento($prod, 'prova')) { $falta[] = 'Anexar a prova que a gráfica mandou.'; }
                $feitas = array_column(self::aprovacoesDaRodada((int) $p['id'], (int) $p['etapa_id']), 'funcao_slug');
                foreach (array_diff(array_filter(explode(',', (string) $p['aprovadores'])), $feitas) as $quem) {
                    $falta[] = 'Aprovação de ' . (Banco::valor('SELECT nome FROM funcoes WHERE slug = ?', [$quem]) ?: $quem) . '.';
                }
                break;
            case 'recebimento':
                if ((int) $p['rotulos_recebidos'] <= 0) { $falta[] = 'Registrar a quantidade de rótulos recebidos.'; }
                break;
            case 'piloto':
                $falta = array_merge($falta, self::esperando($p, ['ficha', 'compras']));
                $ordem = $p['ordem_id'] ? Banco::valor('SELECT situacao FROM ordens_producao WHERE id = ?', [$p['ordem_id']]) : null;
                if ($ordem !== 'concluida') { $falta[] = $ordem ? 'Concluir a ordem do lote piloto em Produção › Ordens.' : 'Criar a ordem do lote piloto.'; }
                break;
            case 'lancamento':
                $falta = array_merge($falta, self::esperando($p, null));
                break;
        }
        return $falta;
    }

    /**
     * Etapas que precisam estar concluídas antes desta (pelas checagens; null = todas as outras).
     * Assim as etapas "ao mesmo tempo" não seguram o projeto, mas a gráfica espera a arte, o piloto espera ficha e compras
     * e o lançamento espera tudo.
     */
    private static function esperando(array $p, ?array $checagens): array
    {
        $sql = "SELECT e.nome FROM dp_etapas e LEFT JOIN dp_projeto_etapas pe ON pe.etapa_id = e.id AND pe.projeto_id = ?
            WHERE e.ativa = 1 AND e.id <> ? AND COALESCE(pe.situacao, 'pendente') <> 'concluida'";
        $par = [(int) $p['id'], (int) $p['etapa_id']];
        if ($checagens !== null) {
            $sql .= ' AND e.checagem IN (' . implode(',', array_fill(0, count($checagens), '?')) . ')';
            $par = array_merge($par, $checagens);
        }
        return array_map(fn($n) => 'Antes, concluir: ' . $n . '.', array_column(Banco::todos($sql . ' ORDER BY e.ordem, e.id', $par), 'nome'));
    }

    private static function temDocumento(int $produtoId, string $tipo): bool
    {
        return (bool) Banco::valor('SELECT 1 FROM produto_documentos WHERE produto_id = ? AND tipo = ? AND arquivado_em IS NULL LIMIT 1', [$produtoId, $tipo]);
    }

    /** A pessoa pode concluir a etapa atual? (função da etapa; etapa sem função: quem gerencia; Admin sempre) */
    public static function podeConcluir(array $p): bool
    {
        if (Auth::pode('*')) { // Admin
            return true;
        }
        $funcao = (string) $p['funcao_slug'];
        return $funcao === '' ? Auth::pode('desenvolvimento.gerenciar') : in_array($funcao, self::funcoesDe((int) Auth::id()), true);
    }

    /** Conclui a etapa atual e passa para a próxima. Observação vai para o histórico. */
    public static function concluir(int $id, ?int $usuarioId, string $observacao = '', bool $forcarPiloto = false, int $etapaId = 0): void
    {
        $projeto = self::buscar($id);
        if (!$projeto || $projeto['situacao'] !== 'andamento') {
            throw new RuntimeException('Projeto não está em andamento.');
        }
        // Qual etapa concluir: a pedida; sem pedir, a primeira em andamento que a pessoa pode concluir
        $atuais = self::atuais($projeto);
        $p = $etapaId ? (current(array_filter($atuais, fn($a) => $a['etapa_id'] === $etapaId)) ?: null)
            : (current(array_filter($atuais, fn($a) => self::podeConcluir($a))) ?: ($atuais[0] ?? null));
        if (!$p) {
            throw new RuntimeException('Esta etapa não está em andamento.');
        }
        if (!self::podeConcluir($p)) {
            throw new RuntimeException('Esta etapa é feita por ' . ($p['funcao'] ?: 'quem gerencia o desenvolvimento') . '.');
        }
        $falta = self::pendencias($p);
        // Lote piloto: pode concluir sem ordem, explicando (ex.: teste feito fora do sistema)
        if ($p['checagem'] === 'piloto' && $forcarPiloto && trim($observacao) !== '') {
            $falta = self::esperando($p, ['ficha', 'compras']);
        }
        if ($falta) {
            throw new RuntimeException('Ainda falta: ' . implode(' ', $falta));
        }
        Banco::transacao(function () use ($p, $usuarioId, $observacao) {
            if ($p['checagem'] === 'lancamento') {
                self::lancar($p, $usuarioId);
            }
            self::avancar($p, $usuarioId, $observacao);
        });
    }

    private static function avancar(array $p, ?int $usuarioId, string $observacao): void
    {
        $id = (int) $p['id'];
        Banco::executar("UPDATE dp_projeto_etapas SET situacao = 'concluida', concluida_por = ?, concluida_em = NOW(), observacao = ? WHERE projeto_id = ? AND etapa_id = ?",
            [$usuarioId, mb_substr($observacao, 0, 500) ?: null, $id, $p['etapa_id']]);
        self::registrar($id, $usuarioId, 'etapa', 'Etapa concluída: ' . $p['etapa'] . ($observacao !== '' ? ' — ' . $observacao : ''));
        self::sincronizar($p, $usuarioId);
        if (PHP_SAPI !== 'cli') {
            $lancado = Banco::valor('SELECT situacao FROM dp_projetos WHERE id = ?', [$id]) === 'lancado';
            NotificacaoService::diretoria('etapa_dp', ($lancado ? 'Produto lançado: ' : 'Desenvolvimento avançou: ') . $p['nome'], '/desenvolvimento/' . $id,
                ['texto' => 'Etapa concluída: ' . $p['etapa']], $usuarioId);
        }
    }

    /**
     * Abre o que pode estar aberto agora:
     *  - da SEQUÊNCIA: a etapa (ou etapas com o mesmo número) de menor ordem ainda por fazer;
     *  - as de "AO MESMO TEMPO" (paralela): todas as que faltam, desde que o projeto já começou (briefing concluído).
     * Quem é da função de cada etapa que abriu recebe aviso. Nada mais por fazer = produto lançado.
     */
    public static function sincronizar(array $p, ?int $usuarioId): void
    {
        $id = (int) $p['id'];
        $etapas = Banco::todos('SELECT e.*, pe.situacao FROM dp_etapas e LEFT JOIN dp_projeto_etapas pe ON pe.etapa_id = e.id AND pe.projeto_id = ?
            WHERE e.ativa = 1 ORDER BY e.ordem, e.id', [$id]);
        $abertas = array_values(array_filter($etapas, fn($e) => $e['situacao'] !== 'concluida'));
        if (!$abertas) {
            Banco::executar("UPDATE dp_projetos SET situacao = 'lancado', lancado_em = NOW() WHERE id = ?", [$id]);
            self::registrar($id, $usuarioId, 'sistema', 'Produto lançado. 🎉');
            Auditoria::registrar('desenvolvimento', $id, 'lancar');
            return;
        }
        $sequencia = array_values(array_filter($abertas, fn($e) => !$e['paralela']));
        $fase = $sequencia ? (int) $sequencia[0]['ordem'] : null;
        $comecou = (bool) array_filter($etapas, fn($e) => $e['situacao'] === 'concluida');
        $novas = [];
        foreach ($abertas as $e) {
            $abre = $e['paralela'] ? $comecou : (int) $e['ordem'] === $fase;
            if ($abre && $e['situacao'] !== 'andamento') {
                Banco::executar("INSERT INTO dp_projeto_etapas (projeto_id, etapa_id, situacao) VALUES (?, ?, 'andamento') ON DUPLICATE KEY UPDATE situacao = 'andamento'",
                    [$id, $e['id']]);
                $novas[] = $e;
            }
        }
        // Etapa "principal" (a da sequência): é a que aparece como etapa do projeto no quadro e nas listas
        $principal = $sequencia ? (int) $sequencia[0]['id'] : (int) $abertas[0]['id'];
        Banco::executar('UPDATE dp_projetos SET etapa_id = ? WHERE id = ?', [$principal, $id]);
        foreach ($novas as $e) {
            Auditoria::registrar('desenvolvimento', $id, 'etapa', null, ['etapa' => $e['nome']]);
            self::avisarFuncao($e['funcao_slug'] ?: null, $p, 'Desenvolvimento: "' . $p['nome'] . '" chegou na etapa ' . $e['nome'], $usuarioId);
            foreach (array_filter(explode(',', (string) $e['aprovadores'])) as $f) {
                self::avisarFuncao($f, $p, 'Desenvolvimento: aprovar a prova de "' . $p['nome'] . '"', $usuarioId);
            }
        }
    }

    /** Volta uma etapa da sequência (quem gerencia). As etapas "ao mesmo tempo" não mudam. */
    public static function voltar(int $id, ?int $usuarioId, string $motivo): void
    {
        $p = self::buscar($id);
        $ordem = $p ? Banco::valor('SELECT MAX(ordem) FROM dp_etapas WHERE ativa = 1 AND paralela = 0 AND ordem < ?', [$p['etapa_ordem']]) : null;
        if ($ordem === null || $ordem === false) {
            throw new RuntimeException('Já está na primeira etapa.');
        }
        $anteriores = Banco::todos('SELECT * FROM dp_etapas WHERE ativa = 1 AND paralela = 0 AND ordem = ? ORDER BY id', [$ordem]);
        self::irPara($p, $anteriores, $usuarioId, 'Voltou para ' . implode(', ', array_column($anteriores, 'nome')) . ($motivo !== '' ? ' — ' . $motivo : ''));
    }

    /** Reabre estas etapas; as etapas da sequência que vêm depois delas ficam para refazer */
    private static function irPara(array $p, array $etapas, ?int $usuarioId, string $texto): void
    {
        $id = (int) $p['id'];
        $ordem = min(array_map(fn($e) => (int) $e['ordem'], $etapas));
        $ids = array_map(fn($e) => (int) $e['id'], $etapas);
        Banco::executar("UPDATE dp_projeto_etapas pe JOIN dp_etapas e ON e.id = pe.etapa_id SET pe.situacao = 'pendente'
            WHERE pe.projeto_id = ? AND e.paralela = 0 AND e.ordem > ? AND e.id NOT IN (" . implode(',', $ids) . ')', [$id, $ordem]);
        foreach ($etapas as $e) {
            Banco::executar("INSERT INTO dp_projeto_etapas (projeto_id, etapa_id, situacao) VALUES (?, ?, 'andamento')
                ON DUPLICATE KEY UPDATE situacao = 'andamento', concluida_em = NULL, concluida_por = NULL", [$id, $e['id']]);
            self::avisarFuncao($e['funcao_slug'] ?: null, $p, 'Desenvolvimento: "' . $p['nome'] . '" voltou para ' . $e['nome'], $usuarioId);
        }
        self::registrar($id, $usuarioId, 'etapa', $texto);
        Auditoria::registrar('desenvolvimento', $id, 'voltar', ['etapa' => $p['etapa']], ['etapa' => implode(', ', array_column($etapas, 'nome'))]);
        self::sincronizar(self::buscar($id), $usuarioId);
    }

    public static function situacao(int $id, string $situacao, ?int $usuarioId): void
    {
        if (!in_array($situacao, ['andamento', 'pausado', 'cancelado'], true)) {
            return;
        }
        Banco::executar("UPDATE dp_projetos SET situacao = ? WHERE id = ? AND situacao <> 'lancado'", [$situacao, $id]);
        self::registrar($id, $usuarioId, 'sistema', ['andamento' => 'Projeto retomado.', 'pausado' => 'Projeto pausado.', 'cancelado' => 'Projeto cancelado.'][$situacao]);
        Auditoria::registrar('desenvolvimento', $id, $situacao);
    }

    /** Cor da embalagem (só as do tipo; âmbar só no frasco) e cor da tampa (#rrggbb) — valores inválidos viram o padrão */
    public static function cores(string $tipo, string $cor, string $tampa): array
    {
        $validas = $tipo === 'frasco' ? ['ambar', 'branco', 'preto'] : ['branco', 'preto'];
        return [in_array($cor, $validas, true) ? $cor : $validas[0], preg_match('/^#[0-9a-fA-F]{6}$/', $tampa) ? strtolower($tampa) : '#14473a'];
    }

    /** Troca o tipo e as cores do mockup (quem gerencia) */
    public static function trocarTipo(int $id, string $tipo, ?int $usuarioId, ?string $cor = null, ?string $tampa = null): void
    {
        if (!isset(self::TIPOS[$tipo])) {
            throw new RuntimeException('Tipo de embalagem inválido.');
        }
        $p = self::buscar($id);
        [$cor, $tampa] = self::cores($tipo, $cor ?? (string) $p['cor_embalagem'], $tampa ?? (string) $p['cor_tampa']);
        Banco::executar('UPDATE dp_projetos SET tipo = ?, cor_embalagem = ?, cor_tampa = ? WHERE id = ?', [$tipo, $cor, $tampa, $id]);
        self::registrar($id, $usuarioId, 'sistema', 'Embalagem: ' . self::TIPOS[$tipo] . ' ' . mb_strtolower(self::CORES[$cor]) . ', tampa ' . (self::TAMPAS[$tampa] ?? $tampa) . '.');
        Auditoria::registrar('desenvolvimento', $id, 'tipo', null, ['tipo' => $tipo, 'cor' => $cor, 'tampa' => $tampa]);
    }

    /** % do produto "formado" (etapas concluídas sobre as etapas ativas) */
    public static function progresso(array $linha): int
    {
        $total = count($linha);
        $feitas = count(array_filter($linha, fn($e) => ($e['situacao'] ?? '') === 'concluida'));
        return $total ? (int) round($feitas * 100 / $total) : 0;
    }

    /** Progresso de vários projetos de uma vez (para o quadro): [projeto_id => %] */
    public static function progressos(array $ids): array
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if (!$ids) {
            return [];
        }
        $total = (int) Banco::valor('SELECT COUNT(*) FROM dp_etapas WHERE ativa = 1') ?: 1;
        $r = [];
        foreach (Banco::todos("SELECT pe.projeto_id, COUNT(*) AS n FROM dp_projeto_etapas pe JOIN dp_etapas e ON e.id = pe.etapa_id AND e.ativa = 1
            WHERE pe.situacao = 'concluida' AND pe.projeto_id IN (" . implode(',', $ids) . ') GROUP BY pe.projeto_id') as $x) {
            $r[(int) $x['projeto_id']] = (int) round(min(100, $x['n'] * 100 / $total));
        }
        return $r;
    }

    /**
     * Dados para o palco 3D (vão embutidos na página como JSON; nada de dado de cliente em endereço).
     * Cada etapa diz a sua checagem; o JS liga a peça correspondente quando a etapa está concluída.
     */
    public static function dadosDoJogo(array $p, array $linha, array $faltaPorEtapa): array
    {
        $prod = (int) $p['produto_id'];
        // A arte mais recente em imagem (PDF não dá para "enrolar" na embalagem)
        $arte = $prod ? Banco::um("SELECT id, mime FROM produto_documentos WHERE produto_id = ? AND tipo = 'rotulo' AND arquivado_em IS NULL
            ORDER BY mime LIKE 'image/%' DESC, id DESC LIMIT 1", [$prod]) : null;
        return [
            'id' => (int) $p['id'], 'tipo' => isset(self::TIPOS[$p['tipo'] ?? '']) ? $p['tipo'] : 'pote', 'nome' => $p['nome'],
            'situacao' => $p['situacao'], 'progresso' => self::progresso($linha),
            'cor' => (string) ($p['cor_embalagem'] ?? ''), 'tampa' => (string) ($p['cor_tampa'] ?? '#14473a'),
            'arte_pdf' => $arte && !str_starts_with((string) $arte['mime'], 'image/'),
            'ean' => $prod ? (string) Banco::valor('SELECT ean FROM produto_tecnico WHERE produto_id = ?', [$prod]) : '',
            'arte' => $arte && str_starts_with((string) $arte['mime'], 'image/') ? url('/produtos/documentos/' . (int) $arte['id']) : null,
            'embalagem' => (string) ($p['embalagem'] ?? ''),
            'etapas' => array_map(fn($e) => [
                'id' => (int) $e['id'], 'nome' => $e['nome'], 'checagem' => (string) $e['checagem'], 'situacao' => $e['situacao'] ?: 'pendente',
                'funcao' => $e['funcao'] ?: 'Gestão', 'por' => $e['concluida_por'], 'em' => $e['concluida_em'] ? data_br($e['concluida_em']) : null,
                'peca' => self::PECAS[(string) $e['checagem']] ?? null, 'falta' => $faltaPorEtapa[(int) $e['id']] ?? [], 'paralela' => (bool) $e['paralela'],
            ], $linha),
        ];
    }

    /** Arte/prova de um produto em desenvolvimento: quem acompanha o desenvolvimento pode ver (para o palco 3D) */
    public static function documentoDoDesenvolvimento(int $docId): bool
    {
        return (bool) Banco::valor("SELECT 1 FROM produto_documentos d JOIN dp_projetos p ON p.produto_id = d.produto_id
            WHERE d.id = ? AND d.tipo IN ('rotulo','prova') AND d.arquivado_em IS NULL", [$docId]);
    }

    // ------------------------------------------------------------------
    // Ações das etapas
    // ------------------------------------------------------------------

    /** Código de barras e NCM (Qualidade), sem mexer nas outras especificações do produto */
    public static function salvarCodigo(int $id, string $ean, string $ncm, ?int $usuarioId): void
    {
        $p = self::buscar($id);
        $ean = preg_replace('/\D/', '', $ean);
        $ncm = preg_replace('/\D/', '', $ncm);
        if (!in_array(strlen($ean), [8, 12, 13, 14], true)) {
            throw new RuntimeException('O EAN (código de barras) deve ter 8, 12, 13 ou 14 números.');
        }
        if (strlen($ncm) !== 8) {
            throw new RuntimeException('O NCM deve ter 8 números (ex.: 2106.90.30).');
        }
        $ncmF = substr($ncm, 0, 4) . '.' . substr($ncm, 4, 2) . '.' . substr($ncm, 6, 2);
        Banco::executar('INSERT INTO produto_tecnico (produto_id, ean, ncm, atualizado_por) VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE ean = VALUES(ean), ncm = VALUES(ncm), atualizado_por = VALUES(atualizado_por)', [$p['produto_id'], $ean, $ncmF, $usuarioId]);
        Auditoria::registrar('produto_tecnico', (int) $p['produto_id'], 'editar', null, ['ean' => $ean, 'ncm' => $ncmF]);
        self::registrar($id, $usuarioId, 'sistema', "Código de barras $ean e NCM $ncmF cadastrados.");
    }

    /** Arte do rótulo (Marketing) ou prova da gráfica (Qualidade): vai para os Documentos do produto */
    public static function enviarArquivo(int $id, string $tipo, array $arquivo, ?int $usuarioId): void
    {
        $p = self::buscar($id);
        $titulo = $tipo === 'prova' ? 'Prova da gráfica' : 'Arte do rótulo';
        ProdutoService::enviarDocumento((int) $p['produto_id'], $arquivo, ['tipo' => $tipo, 'titulo' => $titulo . ' · ' . date('d/m/Y')], $usuarioId);
        self::registrar($id, $usuarioId, 'sistema', "$titulo enviada.");
    }

    /** Compras: marca/desmarca um item da ficha como comprado */
    public static function marcarCompra(int $id, int $mpId, bool $comprado, ?int $usuarioId): void
    {
        $p = self::buscar($id);
        $lista = array_values(array_unique(array_map('intval', $p['compras_ok'])));
        $lista = $comprado ? array_values(array_unique([...$lista, $mpId])) : array_values(array_diff($lista, [$mpId]));
        Banco::executar('UPDATE dp_projetos SET compras_ok = ? WHERE id = ?', [json_encode($lista), $id]);
        $nome = Banco::valor('SELECT nome FROM materias_primas WHERE id = ?', [$mpId]);
        self::registrar($id, $usuarioId, 'sistema', ($comprado ? 'Compra feita: ' : 'Compra desmarcada: ') . $nome);
    }

    public static function grafica(int $id, string $grafica, string $data, ?int $usuarioId): void
    {
        $grafica = mb_substr(trim($grafica), 0, 160);
        if ($grafica === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) {
            throw new RuntimeException('Informe a gráfica e a data do pedido.');
        }
        Banco::executar('UPDATE dp_projetos SET grafica = ?, grafica_pedido_em = ? WHERE id = ?', [$grafica, $data, $id]);
        self::registrar($id, $usuarioId, 'sistema', "Pedido do rótulo enviado à gráfica $grafica em " . date('d/m/Y', strtotime($data)) . '.');
    }

    /** Aprovação da prova da gráfica: cada função aprovadora decide uma vez por rodada; "Ajustar" volta para a criação do rótulo */
    public static function aprovar(int $id, string $decisao, string $comentario, ?int $usuarioId): string
    {
        $projeto = self::buscar($id);
        $p = $projeto ? current(array_filter(self::atuais($projeto), fn($a) => $a['checagem'] === 'aprovacao')) : false;
        if (!$p) {
            throw new RuntimeException('O projeto não está na aprovação da prova.');
        }
        $rodada = self::rodada($id, (int) $p['etapa_id']);
        $feitas = self::aprovacoesDaRodada($id, (int) $p['etapa_id']);
        if (in_array((int) $usuarioId, array_map('intval', array_column($feitas, 'usuario_id')), true)) {
            throw new RuntimeException('Você já respondeu esta prova. Falta a outra área.');
        }
        $minhas = self::funcoesDe((int) $usuarioId);
        $pendentes = array_diff(array_filter(explode(',', (string) $p['aprovadores'])), array_column($feitas, 'funcao_slug'));
        $funcao = current(array_intersect($pendentes, $minhas)) ?: (Auth::pode('*') ? current($pendentes) : false);
        if (!$funcao) {
            throw new RuntimeException('Esta prova é aprovada por ' . implode(' e ', array_map(fn($s) => Banco::valor('SELECT nome FROM funcoes WHERE slug = ?', [$s]) ?: $s, $pendentes)) . '.');
        }
        if ($decisao === 'ajustar' && trim($comentario) === '') {
            throw new RuntimeException('Escreva o que precisa ajustar.');
        }
        Banco::inserir('dp_aprovacoes', ['projeto_id' => $id, 'etapa_id' => $p['etapa_id'], 'funcao_slug' => $funcao, 'usuario_id' => $usuarioId,
            'decisao' => $decisao === 'ajustar' ? 'ajustar' : 'aprovado', 'comentario' => mb_substr(trim($comentario), 0, 500) ?: null, 'rodada' => $rodada]);
        $nomeFuncao = Banco::valor('SELECT nome FROM funcoes WHERE slug = ?', [$funcao]) ?: $funcao;
        if ($decisao === 'ajustar') {
            self::registrar($id, $usuarioId, 'aprovacao', "$nomeFuncao pediu ajuste na prova: " . trim($comentario));
            $arte = Banco::um("SELECT * FROM dp_etapas WHERE ativa = 1 AND checagem = 'arte' ORDER BY ordem LIMIT 1");
            if ($arte) {
                self::irPara($p, [$arte], $usuarioId, 'Voltou para ' . $arte['nome'] . ' para ajustar o rótulo.');
            }
            return 'Ajuste pedido: o projeto voltou para a criação do rótulo.';
        }
        self::registrar($id, $usuarioId, 'aprovacao', "$nomeFuncao aprovou a prova da gráfica.");
        $agora = self::naEtapa(self::buscar($id), ['id' => $p['etapa_id'], 'nome' => $p['etapa'], 'ordem' => $p['etapa_ordem'], 'funcao_slug' => $p['funcao_slug'],
            'checagem' => $p['checagem'], 'aprovadores' => $p['aprovadores'], 'funcao' => $p['funcao']]);
        if (!self::pendencias($agora)) {
            self::avancar($agora, $usuarioId, 'Prova aprovada por todos');
            return 'Prova aprovada por todas as áreas: o projeto seguiu para a próxima etapa.';
        }
        return 'Aprovação registrada. Falta a outra área aprovar.';
    }

    private static function rodada(int $id, int $etapaId): int
    {
        return 1 + (int) Banco::valor("SELECT COUNT(*) FROM dp_aprovacoes WHERE projeto_id = ? AND etapa_id = ? AND decisao = 'ajustar'", [$id, $etapaId]);
    }

    public static function aprovacoesDaRodada(int $id, int $etapaId): array
    {
        return Banco::todos("SELECT a.*, u.nome AS usuario FROM dp_aprovacoes a LEFT JOIN usuarios u ON u.id = a.usuario_id
            WHERE a.projeto_id = ? AND a.etapa_id = ? AND a.rodada = ? AND a.decisao = 'aprovado'", [$id, $etapaId, self::rodada($id, $etapaId)]);
    }

    /**
     * Rótulos recebidos da gráfica. Produto nosso: entrada no estoque do item "Rótulo {produto}" (embalagem, criado
     * sozinho e colocado na ficha técnica). Terceirização: fica no projeto e vai para os rótulos do cliente no lançamento.
     */
    public static function receberRotulos(int $id, int $quantidade, ?int $usuarioId): void
    {
        $p = self::buscar($id);
        if ($quantidade <= 0) {
            throw new RuntimeException('Informe quantos rótulos chegaram.');
        }
        Banco::transacao(function () use ($p, $quantidade, $usuarioId) {
            if (!$p['cliente_id']) {
                $nome = mb_substr('Rótulo ' . $p['nome'], 0, 160);
                $mp = Banco::valor('SELECT id FROM materias_primas WHERE nome = ?', [$nome]);
                if (!$mp) {
                    $mp = ProducaoService::salvarMateria(null, ['nome' => $nome, 'unidade' => 'un', 'tipo' => 'embalagem', 'rotulo' => 1, 'estoque_minimo' => '0', 'custo_unitario' => '0', 'ativo' => 1], $usuarioId);
                    Banco::executar('INSERT IGNORE INTO ficha_tecnica (produto_id, materia_prima_id, quantidade) VALUES (?, ?, 1)', [$p['produto_id'], $mp]);
                }
                ProducaoService::entrada((int) $mp, $quantidade, 0, $p['grafica'] ?: 'Gráfica', 'Rótulos do produto novo (Desenvolvimento)', $usuarioId);
            }
            Banco::executar('UPDATE dp_projetos SET rotulos_recebidos = rotulos_recebidos + ? WHERE id = ?', [$quantidade, $p['id']]);
            self::registrar((int) $p['id'], $usuarioId, 'sistema', number_format($quantidade, 0, ',', '.') . ' rótulo(s) recebido(s)' . ($p['cliente_id'] ? '.' : ' e lançados no estoque.'));
        });
    }

    /** Lote piloto: cria a ordem de produção (Produção conclui em Produção › Ordens) */
    public static function criarPiloto(int $id, int $quantidade, ?int $usuarioId): int
    {
        $p = self::buscar($id);
        $ordem = ProducaoService::criarOrdem(['produto_id' => $p['produto_id'], 'quantidade' => $quantidade, 'lote' => 'PILOTO-' . $p['id'],
            'observacoes' => 'Lote piloto do produto novo (Desenvolvimento).'] + ($p['cliente_id'] ? [] : []), $usuarioId);
        Banco::executar('UPDATE dp_projetos SET ordem_id = ? WHERE id = ?', [$ordem, $id]);
        self::registrar($id, $usuarioId, 'sistema', "Ordem do lote piloto criada (OP $ordem, $quantidade un).");
        return $ordem;
    }

    /** Lançamento: produto nosso entra no catálogo; de terceirização vira produto daquele cliente */
    private static function lancar(array $p, ?int $usuarioId): void
    {
        if ($p['cliente_id']) {
            Banco::executar('UPDATE produtos SET ativo = 1, visivel_catalogo = 0, cliente_exclusivo_id = ?, codigo = COALESCE(codigo, ?) WHERE id = ?',
                [$p['cliente_id'], 'EX-' . $p['produto_id'], $p['produto_id']]);
            if (!Banco::valor('SELECT 1 FROM terceirizacao_produtos WHERE cliente_id = ? AND produto_id = ?', [$p['cliente_id'], $p['produto_id']])) {
                Banco::inserir('terceirizacao_produtos', ['cliente_id' => $p['cliente_id'], 'produto_id' => $p['produto_id'], 'nome_rotulo' => $p['nome'],
                    'rotulos_estoque' => (int) $p['rotulos_recebidos']]);
            }
        } else {
            Banco::executar('UPDATE produtos SET ativo = 1, visivel_catalogo = 1, ordem = 0 WHERE id = ?', [$p['produto_id']]);
        }
        Auditoria::registrar('produto', (int) $p['produto_id'], 'lancado', null, ['projeto' => $p['id']]);
    }

    public static function comentar(int $id, string $texto, ?int $usuarioId): void
    {
        $texto = trim($texto);
        if ($texto === '') {
            throw new RuntimeException('Escreva o comentário.');
        }
        self::registrar($id, $usuarioId, 'comentario', mb_substr($texto, 0, 1000));
    }

    // ------------------------------------------------------------------

    private static function registrar(int $id, ?int $usuarioId, string $tipo, string $texto): void
    {
        Banco::inserir('dp_historico', ['projeto_id' => $id, 'usuario_id' => $usuarioId, 'tipo' => $tipo, 'texto' => mb_substr($texto, 0, 1000)]);
    }

    /** Aviso no sino para quem é da função (sem função: quem gerencia o desenvolvimento) */
    private static function avisarFuncao(?string $funcao, array $p, string $titulo, ?int $usuarioId): void
    {
        $pessoas = $funcao
            ? array_column(Banco::todos('SELECT u.id FROM usuarios u JOIN usuario_funcoes uf ON uf.usuario_id = u.id JOIN funcoes f ON f.id = uf.funcao_id
                WHERE u.ativo = 1 AND f.slug = ?', [$funcao]), 'id')
            : array_column(Banco::todos("SELECT DISTINCT u.id FROM usuarios u JOIN perfil_permissoes pp ON pp.perfil_id = u.perfil_id
                WHERE u.ativo = 1 AND pp.permissao IN ('desenvolvimento.gerenciar', '*')"), 'id');
        if ($pessoas) {
            NotificacaoService::avisar($pessoas, 'desenvolvimento', $titulo, '/desenvolvimento/' . (int) $p['id'], [], $usuarioId);
        }
    }

    /** Quantos projetos esperam a pessoa (número do menu e One) */
    public static function qtdMinhas(int $usuarioId): int
    {
        return count(self::minhas($usuarioId));
    }
}
