<?php
namespace App\Services;

use App\Core\Auth;
use App\Core\Banco;

/**
 * Relatório de bonificações (V16.4): quanto foi bonificado, por vendedor, cliente, mês, motivo ou produto.
 * Bonificação = itens que foram na nota com CFOP de bonificação e que a loja não pagou (não entram no faturamento nem na meta).
 * Quem não vê todos os pedidos vê só os pedidos dele.
 */
class BonificacaoService
{
    public const PERIODOS = ['mes' => 'Este mês', '3m' => 'Últimos 3 meses', '12m' => 'Últimos 12 meses', 'ano' => 'Este ano'];
    public const GRUPOS = ['vendedor' => 'Vendedor(a)', 'cliente' => 'Cliente', 'mes' => 'Mês', 'motivo' => 'Motivo', 'produto' => 'Produto'];

    public static function inicio(string $periodo): string
    {
        return match ($periodo) {
            '3m' => date('Y-m-01', strtotime('first day of -2 months')),
            '12m' => date('Y-m-01', strtotime('first day of -11 months')),
            'ano' => date('Y-01-01'),
            default => date('Y-m-01'),
        };
    }

    /** WHERE dos pedidos com bonificação no período (e só os da pessoa, se ela não vê todos) */
    private static function base(string $periodo): array
    {
        $w = "p.bonificado_valor > 0 AND p.situacao NOT IN ('cancelado', 'recusado') AND p.criado_em >= ?";
        $par = [self::inicio($periodo)];
        if (!Auth::pode('pedidos.ver_todos')) {
            $w .= ' AND (p.vendedor_id = ? OR p.tirado_por_id = ?)';
            array_push($par, Auth::id() ?? 0, Auth::id() ?? 0);
        }
        return [$w, $par];
    }

    public static function resumo(string $periodo): array
    {
        [$w, $par] = self::base($periodo);
        return Banco::um("SELECT COUNT(*) AS pedidos, COALESCE(SUM(p.bonificado_valor), 0) AS bonificado, COALESCE(SUM(p.total), 0) AS vendido,
            COALESCE(SUM(p.bonificacao_acima_limite), 0) AS acima FROM pedidos p WHERE $w", $par);
    }

    public static function agrupado(string $periodo, string $grupo): array
    {
        [$w, $par] = self::base($periodo);
        if ($grupo === 'produto') {
            return Banco::todos("SELECT i.nome AS grupo, COUNT(DISTINCT p.id) AS pedidos, SUM(i.valor_bonificado) AS bonificado, SUM(i.quantidade) AS unidades, NULL AS vendido
                FROM pedido_itens i JOIN pedidos p ON p.id = i.pedido_id WHERE i.bonificado = 1 AND $w GROUP BY i.nome ORDER BY bonificado DESC LIMIT 100", $par);
        }
        $expr = match ($grupo) {
            'cliente' => "COALESCE(NULLIF(c.nome_fantasia, ''), c.razao_social)",
            'mes' => "DATE_FORMAT(p.criado_em, '%Y-%m')",
            'motivo' => "COALESCE(p.bonificacao_motivo, 'Sem motivo')",
            default => "COALESCE(u.nome, 'Sem vendedor')",
        };
        return Banco::todos("SELECT $expr AS grupo, COUNT(*) AS pedidos, SUM(p.bonificado_valor) AS bonificado, NULL AS unidades, SUM(p.total) AS vendido
            FROM pedidos p JOIN clientes c ON c.id = p.cliente_id LEFT JOIN usuarios u ON u.id = p.vendedor_id
            WHERE $w GROUP BY 1 ORDER BY " . ($grupo === 'mes' ? 'grupo' : 'bonificado DESC') . ' LIMIT 100', $par);
    }

    /** Os pedidos com bonificação (mais recentes primeiro) */
    public static function pedidos(string $periodo): array
    {
        [$w, $par] = self::base($periodo);
        return Banco::todos("SELECT p.id, p.criado_em, p.total, p.bonificado_valor, p.bonificacao_motivo, p.bonificacao_acima_limite, p.situacao,
            COALESCE(NULLIF(c.nome_fantasia, ''), c.razao_social) AS cliente, u.nome AS vendedor
            FROM pedidos p JOIN clientes c ON c.id = p.cliente_id LEFT JOIN usuarios u ON u.id = p.vendedor_id WHERE $w ORDER BY p.criado_em DESC LIMIT 200", $par);
    }
}
