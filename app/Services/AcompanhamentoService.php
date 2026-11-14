<?php
namespace App\Services;

use App\Core\Auth;
use App\Core\Banco;
use App\Core\Config;

/**
 * Gestão › Acompanhamento: tudo de cada vendedora num lugar só (ligações e gravações do Ligue Talk,
 * contatos registrados, reativações e pedidos), por período.
 * Quem vê: "acompanhamento.ver" (Gestor e Admin: todas as pessoas que vendem) ou a gestora do setor (a equipe dela).
 */
class AcompanhamentoService
{
    /** Tipos de evento que contam como "contato" com o cliente */
    private const CONTATOS = "'contato','ligacao','whatsapp','visita','email'";

    /** Pessoas que a pessoa logada acompanha; $setor (função) limita a um setor */
    public static function pessoas(?int $setor = null): array
    {
        if (Auth::pode('acompanhamento.ver')) {
            $sql = 'SELECT DISTINCT u.id, u.nome FROM usuarios u JOIN usuario_funcoes uf ON uf.usuario_id = u.id JOIN funcoes f ON f.id = uf.funcao_id
                WHERE u.ativo = 1 AND f.setor_venda = 1' . ($setor ? ' AND f.id = ' . (int) $setor : '') . ' ORDER BY u.nome';
            return Banco::todos($sql);
        }
        $equipe = Setores::equipeGerenciada();
        if ($setor) {
            $doSetor = array_map('intval', array_column(Banco::todos('SELECT usuario_id FROM usuario_funcoes WHERE funcao_id = ?', [$setor]), 'usuario_id'));
            $equipe = array_values(array_filter($equipe, fn($p) => in_array((int) $p['id'], $doSetor, true)));
        }
        return $equipe;
    }

    public static function podeVer(): bool
    {
        return Auth::pode('acompanhamento.ver') || Setores::gerenciados();
    }

    /** Período: hoje | 7d | mes | escolher (de/até). Devolve [de, ate, chave] */
    public static function periodo(string $p, string $de = '', string $ate = ''): array
    {
        $valida = fn(string $d) => preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) ? $d : null;
        return match ($p) {
            'hoje'  => [date('Y-m-d'), date('Y-m-d'), 'hoje'],
            '7d'    => [date('Y-m-d', strtotime('-6 days')), date('Y-m-d'), '7d'],
            'outro' => [$valida($de) ?? date('Y-m-01'), $valida($ate) ?? date('Y-m-d'), 'outro'],
            default => [date('Y-m-01'), date('Y-m-d'), 'mes'],
        };
    }

    /** Uma linha por pessoa com os números do período */
    public static function resumo(array $pessoas, string $de, string $ate): array
    {
        $ids = array_map('intval', array_column($pessoas, 'id'));
        if (!$ids) {
            return [];
        }
        $lista = implode(',', $ids);
        $par = [$de . ' 00:00:00', $ate . ' 23:59:59'];
        $lig = self::porPessoa("SELECT usuario_id AS id, COUNT(*) AS ligacoes, SUM(situacao = 'atendida') AS atendidas, SUM(duracao_seg) AS falado,
            SUM(gravacao IS NOT NULL) AS gravacoes FROM ligacoes WHERE usuario_id IN ($lista) AND inicio BETWEEN ? AND ? GROUP BY usuario_id", $par);
        $cont = self::porPessoa('SELECT usuario_id AS id, COUNT(*) AS contatos, COUNT(DISTINCT cliente_id) AS clientes_contatados FROM cliente_eventos
            WHERE usuario_id IN (' . $lista . ') AND tipo IN (' . self::CONTATOS . ') AND ocorrido_em BETWEEN ? AND ? GROUP BY usuario_id', $par);
        $ped = self::porPessoa("SELECT vendedor_id AS id, COUNT(*) AS pedidos, SUM(total) AS valor FROM pedidos
            WHERE vendedor_id IN ($lista) AND situacao NOT IN ('cancelado','recusado') AND criado_em BETWEEN ? AND ? GROUP BY vendedor_id", $par);
        $reat = self::porPessoa('SELECT r.vendedor_id AS id, COUNT(*) AS reativacoes FROM (' . self::sqlReativacoes($lista) . ') r GROUP BY r.vendedor_id', $par);
        // WhatsApp (conversas e mensagens prontas) e agenda (feitas no prazo, atrasadas em aberto)
        $whats = self::porPessoa("SELECT usuario_id AS id, COUNT(*) AS whatsapp, SUM(descricao LIKE 'Mensagem pronta%') AS mensagens_prontas FROM cliente_eventos
            WHERE usuario_id IN ($lista) AND tipo = 'whatsapp' AND ocorrido_em BETWEEN ? AND ? GROUP BY usuario_id", $par);
        $tar = self::porPessoa("SELECT usuario_id AS id, COUNT(*) AS tarefas_feitas, SUM(DATE(concluida_em) <= vence_em) AS tarefas_no_prazo FROM tarefas
            WHERE usuario_id IN ($lista) AND concluida_em BETWEEN ? AND ? GROUP BY usuario_id", $par);
        $atr = self::porPessoa("SELECT usuario_id AS id, COUNT(*) AS tarefas_atrasadas FROM tarefas WHERE usuario_id IN ($lista) AND concluida_em IS NULL AND vence_em < CURDATE() GROUP BY usuario_id", []);
        // Meta do mês (Gestão › Metas) × faturado no mês da data final do período
        $mes = substr($ate, 0, 7);
        $metas = array_column(Banco::todos("SELECT alvo_id, valor FROM metas WHERE mes = ? AND tipo = 'vendedor' AND alvo_id IN ($lista)", [$mes]), 'valor', 'alvo_id');
        $linhas = [];
        foreach ($pessoas as $p) {
            $id = (int) $p['id'];
            $l = ['id' => $id, 'nome' => $p['nome']] + ($lig[$id] ?? []) + ($cont[$id] ?? []) + ($ped[$id] ?? []) + ($reat[$id] ?? [])
                + ($whats[$id] ?? []) + ($tar[$id] ?? []) + ($atr[$id] ?? []);
            foreach (['ligacoes', 'atendidas', 'falado', 'gravacoes', 'contatos', 'clientes_contatados', 'pedidos', 'reativacoes',
                      'whatsapp', 'mensagens_prontas', 'tarefas_feitas', 'tarefas_no_prazo', 'tarefas_atrasadas'] as $k) {
                $l[$k] = (int) ($l[$k] ?? 0);
            }
            $l['valor'] = (float) ($l['valor'] ?? 0);
            $l['taxa'] = $l['ligacoes'] ? round($l['atendidas'] / $l['ligacoes'] * 100) : 0;
            $l['medio'] = $l['atendidas'] ? (int) round($l['falado'] / $l['atendidas']) : 0;
            $l['meta'] = (float) ($metas[$id] ?? 0);
            $l['faturado_mes'] = (float) VendasService::faturadoNoMes($mes, 'vendedor', $id)['valor'];
            $l['pct_meta'] = $l['meta'] > 0 ? round($l['faturado_mes'] / $l['meta'] * 100) : null;
            $l['pct_prazo'] = $l['tarefas_feitas'] ? round($l['tarefas_no_prazo'] / $l['tarefas_feitas'] * 100) : null;
            $linhas[] = $l;
        }
        usort($linhas, fn($a, $b) => [$b['valor'], $b['ligacoes']] <=> [$a['valor'], $a['ligacoes']]);
        return $linhas;
    }

    /**
     * Reativação = pedido faturado no período de cliente que estava sem comprar há mais de X dias
     * (Configurações › carteira.dias_inatividade) antes desse pedido.
     */
    private static function sqlReativacoes(string $listaVendedores): string
    {
        $dias = max(1, (int) Config::get('carteira.dias_inatividade', 90));
        // Tabela derivada + WHERE (sem HAVING sem GROUP BY: compatível com MySQL 5.7 e MariaDB em modo estrito)
        return "SELECT x.* FROM (SELECT p.id, p.vendedor_id, p.cliente_id, p.faturado_em, p.total,
                (SELECT MAX(p2.faturado_em) FROM pedidos p2 WHERE p2.cliente_id = p.cliente_id AND p2.situacao = 'faturado' AND p2.faturado_em < p.faturado_em) AS anterior
            FROM pedidos p WHERE p.situacao = 'faturado' AND p.vendedor_id IN ($listaVendedores) AND p.faturado_em BETWEEN ? AND ?) x
            WHERE x.anterior IS NOT NULL AND x.anterior < x.faturado_em - INTERVAL $dias DAY";
    }

    private static function porPessoa(string $sql, array $par): array
    {
        $r = [];
        foreach (Banco::todos($sql, $par) as $l) {
            $r[(int) $l['id']] = $l;
        }
        return $r;
    }

    /** Detalhe de uma pessoa: ligações por dia, lista de ligações (com gravação), contatos e reativações */
    public static function detalhe(int $usuarioId, string $de, string $ate): array
    {
        $par = [$usuarioId, $de . ' 00:00:00', $ate . ' 23:59:59'];
        $porDia = Banco::todos("SELECT DATE(inicio) AS dia, COUNT(*) AS total, SUM(situacao = 'atendida') AS atendidas FROM ligacoes
            WHERE usuario_id = ? AND inicio BETWEEN ? AND ? GROUP BY DATE(inicio) ORDER BY dia", $par);
        $ligacoes = Banco::todos("SELECT l.*, COALESCE(NULLIF(c.nome_fantasia, ''), c.razao_social) AS cliente FROM ligacoes l LEFT JOIN clientes c ON c.id = l.cliente_id
            WHERE l.usuario_id = ? AND l.inicio BETWEEN ? AND ? ORDER BY l.inicio DESC LIMIT 200", $par);
        $contatos = Banco::todos('SELECT e.tipo, e.descricao, e.ocorrido_em, e.cliente_id, COALESCE(NULLIF(c.nome_fantasia, \'\'), c.razao_social) AS cliente
            FROM cliente_eventos e JOIN clientes c ON c.id = e.cliente_id
            WHERE e.usuario_id = ? AND e.tipo IN (' . self::CONTATOS . ') AND e.ocorrido_em BETWEEN ? AND ? ORDER BY e.ocorrido_em DESC LIMIT 100', $par);
        $reativacoes = Banco::todos('SELECT r.*, COALESCE(NULLIF(c.nome_fantasia, \'\'), c.razao_social) AS cliente FROM (' . self::sqlReativacoes((string) $usuarioId) . ') r
            JOIN clientes c ON c.id = r.cliente_id ORDER BY r.faturado_em DESC', [$de . ' 00:00:00', $ate . ' 23:59:59']);
        return compact('porDia', 'ligacoes', 'contatos', 'reativacoes');
    }

    /** O mesmo período, imediatamente antes (para comparar ▲ ▼) */
    public static function anterior(string $de, string $ate): array
    {
        $dias = (int) ((strtotime($ate) - strtotime($de)) / 86400) + 1;
        return [date('Y-m-d', strtotime("$de -$dias days")), date('Y-m-d', strtotime("$de -1 day"))];
    }

    /** Carteira, funil e agenda da pessoa (abas da página da vendedora) */
    public static function carteiraEAgenda(int $usuarioId, string $de, string $ate): array
    {
        $par = [$de . ' 00:00:00', $ate . ' 23:59:59'];
        $carteira = Banco::um("SELECT COUNT(*) AS total, SUM(tipo = 'cliente') AS clientes, SUM(tipo = 'lead') AS leads,
                SUM(tipo = 'cliente' AND ultima_compra_em >= CURDATE() - INTERVAL 30 DAY) AS compraram_30,
                SUM(tipo = 'cliente' AND ultima_compra_em < CURDATE() - INTERVAL 30 DAY AND ultima_compra_em >= CURDATE() - INTERVAL 60 DAY) AS sem_30,
                SUM(tipo = 'cliente' AND ultima_compra_em < CURDATE() - INTERVAL 60 DAY AND ultima_compra_em >= CURDATE() - INTERVAL 90 DAY) AS sem_60,
                SUM(tipo = 'cliente' AND (ultima_compra_em < CURDATE() - INTERVAL 90 DAY OR ultima_compra_em IS NULL)) AS sem_90,
                SUM(criado_em BETWEEN ? AND ?) AS novos
            FROM clientes WHERE responsavel_id = ? AND excluido_em IS NULL", [...$par, $usuarioId]) ?: [];
        $funil = Banco::todos('SELECT e.nome, e.cor, (SELECT COUNT(*) FROM clientes c WHERE c.etapa_id = e.id AND c.responsavel_id = ? AND c.excluido_em IS NULL) AS qtd
            FROM funil_etapas e WHERE e.ativa = 1 ORDER BY e.ordem', [$usuarioId]);
        $etapas = Banco::todos("SELECT descricao, COUNT(*) AS qtd FROM cliente_eventos WHERE usuario_id = ? AND tipo = 'etapa' AND ocorrido_em BETWEEN ? AND ?
            GROUP BY descricao ORDER BY qtd DESC LIMIT 12", [$usuarioId, ...$par]);
        $canais = Banco::todos("SELECT tipo, COUNT(*) AS qtd FROM cliente_eventos WHERE usuario_id = ? AND tipo IN (" . self::CONTATOS . ")
            AND ocorrido_em BETWEEN ? AND ? GROUP BY tipo ORDER BY qtd DESC", [$usuarioId, ...$par]);
        $prontas = Banco::todos("SELECT SUBSTRING(descricao, 18) AS modelo, COUNT(*) AS qtd FROM cliente_eventos WHERE usuario_id = ? AND tipo = 'whatsapp'
            AND descricao LIKE 'Mensagem pronta:%' AND ocorrido_em BETWEEN ? AND ? GROUP BY modelo ORDER BY qtd DESC LIMIT 10", [$usuarioId, ...$par]);
        $agenda = Banco::um("SELECT SUM(concluida_em BETWEEN ? AND ?) AS feitas, SUM(concluida_em BETWEEN ? AND ? AND DATE(concluida_em) <= vence_em) AS no_prazo,
                SUM(concluida_em IS NULL AND vence_em < CURDATE()) AS atrasadas, SUM(concluida_em IS NULL AND vence_em = CURDATE()) AS hoje,
                SUM(concluida_em IS NULL AND vence_em > CURDATE()) AS futuras FROM tarefas WHERE usuario_id = ?", [...$par, ...$par, $usuarioId]) ?: [];
        $atrasadas = Banco::todos("SELECT t.titulo, t.vence_em, t.cliente_id, COALESCE(NULLIF(c.nome_fantasia, ''), c.razao_social) AS cliente FROM tarefas t
            LEFT JOIN clientes c ON c.id = t.cliente_id WHERE t.usuario_id = ? AND t.concluida_em IS NULL AND t.vence_em < CURDATE() ORDER BY t.vence_em LIMIT 15", [$usuarioId]);
        $pedidos = Banco::todos("SELECT p.id, p.situacao, p.total, p.criado_em, COALESCE(NULLIF(c.nome_fantasia, ''), c.razao_social) AS cliente FROM pedidos p
            JOIN clientes c ON c.id = p.cliente_id WHERE p.vendedor_id = ? AND p.criado_em BETWEEN ? AND ? ORDER BY p.criado_em DESC LIMIT 60", [$usuarioId, ...$par]);
        return compact('carteira', 'funil', 'etapas', 'canais', 'prontas', 'agenda', 'atrasadas', 'pedidos');
    }

    /** Meta × realizado da pessoa no mês (mesma conta de Gestão › Metas) */
    public static function meta(int $usuarioId, string $mes): ?array
    {
        foreach (MetaService::grade($mes, 'vendedor') as $l) {
            if ($l['id'] === $usuarioId) {
                return $l;
            }
        }
        return null;
    }

    // ------------------------------------------------------------------
    // Cobrança
    // ------------------------------------------------------------------

    /** Pode ver a visão Cobrança: Gestor/Admin (acompanhamento.ver) ou quem trabalha a Cobrança (Financeiro) */
    public static function podeVerCobranca(): bool
    {
        return Auth::pode('acompanhamento.ver') || Auth::pode('cobranca.gerenciar');
    }

    public static function podeVerVendas(): bool
    {
        return Auth::pode('acompanhamento.ver') || Setores::gerenciados();
    }

    /** Quem cuida da Cobrança: pessoas da função Cobrança e responsáveis por casos */
    public static function pessoasCobranca(): array
    {
        if (!self::podeVerCobranca()) {
            return [];
        }
        $funcao = CarteiraService::funcaoId((string) Config::get('cobranca.funcao', 'cobranca')) ?? 0;
        return Banco::todos('SELECT DISTINCT u.id, u.nome FROM usuarios u WHERE u.ativo = 1 AND (
                EXISTS (SELECT 1 FROM usuario_funcoes uf WHERE uf.usuario_id = u.id AND uf.funcao_id = ?)
                OR EXISTS (SELECT 1 FROM cobrancas cb WHERE cb.responsavel_id = u.id)) ORDER BY u.nome', [$funcao]);
    }

    /** Uma linha por pessoa da Cobrança: recuperado, tentativas, promessas, carteira em atraso e ligações */
    public static function resumoCobranca(array $pessoas, string $de, string $ate): array
    {
        $ids = array_map('intval', array_column($pessoas, 'id'));
        if (!$ids) {
            return [];
        }
        $lista = implode(',', $ids);
        $par = [$de . ' 00:00:00', $ate . ' 23:59:59'];
        $rec = self::porPessoa("SELECT cb.responsavel_id AS id, SUM(t.valor) AS recuperado, COUNT(*) AS titulos_pagos FROM cobranca_titulos t
            JOIN cobrancas cb ON cb.id = t.cobranca_id WHERE cb.responsavel_id IN ($lista) AND t.pago_em BETWEEN ? AND ? GROUP BY cb.responsavel_id", [$de, $ate]);
        $tent = self::porPessoa("SELECT usuario_id AS id, COUNT(*) AS tentativas,
                SUM(descricao LIKE 'Cobrança — Atendeu%' OR descricao LIKE 'Cobrança — Respondeu%') AS com_sucesso
            FROM cliente_eventos WHERE usuario_id IN ($lista) AND tipo = 'cobranca' AND descricao LIKE 'Cobrança — %' AND ocorrido_em BETWEEN ? AND ? GROUP BY usuario_id", $par);
        $aberto = self::porPessoa("SELECT responsavel_id AS id, COUNT(*) AS casos, SUM(valor_total) AS em_aberto,
                AVG(DATEDIFF(CURDATE(), vencimento_mais_antigo)) AS atraso_medio FROM cobrancas WHERE situacao = 'aberta' AND responsavel_id IN ($lista) GROUP BY responsavel_id", []);
        $lig = self::porPessoa("SELECT usuario_id AS id, COUNT(*) AS ligacoes, SUM(situacao = 'atendida') AS atendidas, SUM(duracao_seg) AS falado
            FROM ligacoes WHERE usuario_id IN ($lista) AND inicio BETWEEN ? AND ? GROUP BY usuario_id", $par);
        $prom = [];
        foreach (self::promessas($lista, $de, $ate) as $p) {
            $prom[$p['usuario_id']]['promessas'] = ($prom[$p['usuario_id']]['promessas'] ?? 0) + 1;
            $prom[$p['usuario_id']][$p['situacao']] = ($prom[$p['usuario_id']][$p['situacao']] ?? 0) + 1;
        }
        $linhas = [];
        foreach ($pessoas as $p) {
            $id = (int) $p['id'];
            $l = ['id' => $id, 'nome' => $p['nome']] + ($rec[$id] ?? []) + ($tent[$id] ?? []) + ($aberto[$id] ?? []) + ($lig[$id] ?? []) + ($prom[$id] ?? []);
            foreach (['titulos_pagos', 'tentativas', 'com_sucesso', 'casos', 'ligacoes', 'atendidas', 'falado', 'promessas', 'cumprida', 'quebrada', 'aguardando'] as $k) {
                $l[$k] = (int) ($l[$k] ?? 0);
            }
            foreach (['recuperado', 'em_aberto', 'atraso_medio'] as $k) {
                $l[$k] = (float) ($l[$k] ?? 0);
            }
            $linhas[] = $l;
        }
        usort($linhas, fn($a, $b) => $b['recuperado'] <=> $a['recuperado']);
        return $linhas;
    }

    /**
     * Promessas de pagamento feitas no período, com a situação: cumprida (algum título do caso pago até a data prometida),
     * quebrada (passou a data sem pagamento) ou aguardando.
     */
    public static function promessas(string $listaPessoas, string $de, string $ate): array
    {
        $r = [];
        $eventos = Banco::todos("SELECT e.usuario_id, e.descricao, e.ocorrido_em, e.cliente_id, JSON_UNQUOTE(JSON_EXTRACT(e.dados_json, '$.cobranca_id')) AS caso,
                COALESCE(NULLIF(c.nome_fantasia, ''), c.razao_social) AS cliente
            FROM cliente_eventos e JOIN clientes c ON c.id = e.cliente_id
            WHERE e.usuario_id IN ($listaPessoas) AND e.tipo = 'promessa' AND e.ocorrido_em BETWEEN ? AND ? ORDER BY e.ocorrido_em DESC", [$de . ' 00:00:00', $ate . ' 23:59:59']);
        foreach ($eventos as $e) {
            $data = preg_match('#em (\d{2})/(\d{2})/(\d{4})#', (string) $e['descricao'], $m) ? "$m[3]-$m[2]-$m[1]" : substr($e['ocorrido_em'], 0, 10);
            $pago = $e['caso'] ? Banco::valor('SELECT 1 FROM cobranca_titulos WHERE cobranca_id = ? AND pago_em BETWEEN ? AND ? LIMIT 1',
                [(int) $e['caso'], substr($e['ocorrido_em'], 0, 10), $data]) : null;
            $e['data_prometida'] = $data;
            $e['situacao'] = $pago ? 'cumprida' : ($data < date('Y-m-d') ? 'quebrada' : 'aguardando');
            $r[] = $e;
        }
        return $r;
    }

    /** Detalhe de quem cuida da Cobrança */
    public static function detalheCobranca(int $usuarioId, string $de, string $ate): array
    {
        $etapas = Banco::todos("SELECT e.nome, e.cor,
                (SELECT COUNT(*) FROM cobrancas cb WHERE cb.etapa_id = e.id AND cb.responsavel_id = ? AND cb.situacao = 'aberta') AS casos,
                (SELECT COALESCE(SUM(cb.valor_total), 0) FROM cobrancas cb WHERE cb.etapa_id = e.id AND cb.responsavel_id = ? AND cb.situacao = 'aberta') AS valor
            FROM cobranca_etapas e WHERE e.ativa = 1 ORDER BY e.ordem", [$usuarioId, $usuarioId]);
        $atrasos = Banco::todos("SELECT cb.id, cb.valor_total, cb.vencimento_mais_antigo, DATEDIFF(CURDATE(), cb.vencimento_mais_antigo) AS dias, cb.cliente_id,
                COALESCE(NULLIF(c.nome_fantasia, ''), c.razao_social) AS cliente, e.nome AS etapa
            FROM cobrancas cb JOIN clientes c ON c.id = cb.cliente_id JOIN cobranca_etapas e ON e.id = cb.etapa_id
            WHERE cb.responsavel_id = ? AND cb.situacao = 'aberta' ORDER BY dias DESC LIMIT 12", [$usuarioId]);
        $porDia = Banco::todos("SELECT t.pago_em AS dia, SUM(t.valor) AS valor FROM cobranca_titulos t JOIN cobrancas cb ON cb.id = t.cobranca_id
            WHERE cb.responsavel_id = ? AND t.pago_em BETWEEN ? AND ? GROUP BY t.pago_em ORDER BY t.pago_em", [$usuarioId, $de, $ate]);
        $semana = Banco::todos("SELECT cb.promessa_em, cb.promessa_valor, cb.cliente_id, COALESCE(NULLIF(c.nome_fantasia, ''), c.razao_social) AS cliente
            FROM cobrancas cb JOIN clientes c ON c.id = cb.cliente_id WHERE cb.responsavel_id = ? AND cb.situacao = 'aberta'
              AND cb.promessa_em BETWEEN CURDATE() AND CURDATE() + INTERVAL 7 DAY ORDER BY cb.promessa_em", [$usuarioId]);
        $promessas = self::promessas((string) $usuarioId, $de, $ate);
        $ligacoes = Banco::todos("SELECT l.*, COALESCE(NULLIF(c.nome_fantasia, ''), c.razao_social) AS cliente FROM ligacoes l LEFT JOIN clientes c ON c.id = l.cliente_id
            WHERE l.usuario_id = ? AND l.inicio BETWEEN ? AND ? ORDER BY l.inicio DESC LIMIT 100", [$usuarioId, $de . ' 00:00:00', $ate . ' 23:59:59']);
        return compact('etapas', 'atrasos', 'porDia', 'semana', 'promessas', 'ligacoes');
    }
}
