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
        $linhas = [];
        foreach ($pessoas as $p) {
            $id = (int) $p['id'];
            $l = ['id' => $id, 'nome' => $p['nome']] + ($lig[$id] ?? []) + ($cont[$id] ?? []) + ($ped[$id] ?? []) + ($reat[$id] ?? []);
            foreach (['ligacoes', 'atendidas', 'falado', 'gravacoes', 'contatos', 'clientes_contatados', 'pedidos', 'reativacoes'] as $k) {
                $l[$k] = (int) ($l[$k] ?? 0);
            }
            $l['valor'] = (float) ($l['valor'] ?? 0);
            $l['taxa'] = $l['ligacoes'] ? round($l['atendidas'] / $l['ligacoes'] * 100) : 0;
            $l['medio'] = $l['atendidas'] ? (int) round($l['falado'] / $l['atendidas']) : 0;
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
        return "SELECT p.id, p.vendedor_id, p.cliente_id, p.faturado_em, p.total,
                (SELECT MAX(p2.faturado_em) FROM pedidos p2 WHERE p2.cliente_id = p.cliente_id AND p2.situacao = 'faturado' AND p2.faturado_em < p.faturado_em) AS anterior
            FROM pedidos p WHERE p.situacao = 'faturado' AND p.vendedor_id IN ($listaVendedores) AND p.faturado_em BETWEEN ? AND ?
            HAVING anterior IS NOT NULL AND anterior < p.faturado_em - INTERVAL $dias DAY";
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
}
