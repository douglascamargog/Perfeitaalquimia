<?php
namespace App\Models;

use App\Core\Auth;
use App\Core\Banco;

/**
 * Consultas de pedidos.
 * Escopo da equipe: quem tem pedidos.ver_todos vê tudo; o vendedor vê os pedidos dele
 * e os dos clientes da carteira dele.
 */
class Pedido
{
    public const FILTROS = ['q', 'situacao', 'origem', 'vendedor', 'de', 'ate'];

    private const CAMPOS = "p.*, c.razao_social, c.nome_fantasia, c.cnpj, c.cidade, c.uf, c.responsavel_id,
        u.nome AS vendedor, f.nome AS forma, cp.nome AS condicao, t.nome AS tabela, a.nome AS aprovador,
        (SELECT COUNT(*) FROM pedido_itens i WHERE i.pedido_id = p.id) AS qtd_itens";
    private const JUNCOES = 'FROM pedidos p JOIN clientes c ON c.id = p.cliente_id
        LEFT JOIN usuarios u ON u.id = p.vendedor_id
        JOIN formas_pagamento f ON f.id = p.forma_pagamento_id
        LEFT JOIN condicoes_pagamento cp ON cp.id = p.condicao_id
        JOIN tabelas_preco t ON t.id = p.tabela_preco_id
        LEFT JOIN usuarios a ON a.id = p.aprovado_por';

    public static function filtrosDaUrl(): array
    {
        $f = [];
        foreach (self::FILTROS as $nome) {
            $f[$nome] = trim((string) ($_GET[$nome] ?? ''));
        }
        return $f;
    }

    /** Escopo do usuário logado (SQL + parâmetros) */
    private static function escopo(): array
    {
        if (Auth::pode('pedidos.ver_todos')) {
            return ['1 = 1', []];
        }
        $setor = \App\Services\Setores::sqlGerenciados(); // gestor(a) do setor vê os pedidos do setor
        return ['(p.vendedor_id = ? OR c.responsavel_id = ?' . ($setor ? " OR $setor" : '') . ')', [Auth::id(), Auth::id()]];
    }

    private static function montarWhere(array $f): array
    {
        [$w, $par] = self::escopo();
        $w = [$w];
        if (($f['q'] ?? '') !== '') {
            $digitos = preg_replace('/\D/', '', $f['q']);
            $w[] = '(c.razao_social LIKE ? OR c.nome_fantasia LIKE ?' . ($digitos !== '' ? ' OR c.cnpj LIKE ? OR p.id = ?' : '') . ')';
            array_push($par, "%{$f['q']}%", "%{$f['q']}%");
            if ($digitos !== '') {
                array_push($par, "$digitos%", (int) $digitos);
            }
        }
        foreach (['situacao' => 'p.situacao', 'origem' => 'p.origem', 'vendedor' => 'p.vendedor_id'] as $campo => $coluna) {
            if (($f[$campo] ?? '') !== '') {
                $w[] = "$coluna = ?";
                $par[] = $f[$campo];
            }
        }
        // Setor de venda (escolhido no menu): pedidos dos clientes da carteira daquele setor
        if (ctype_digit((string) ($f['funcao'] ?? ''))) {
            $w[] = 'c.funcao_id = ?';
            $par[] = (int) $f['funcao'];
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $f['de'] ?? '')) {
            $w[] = 'p.criado_em >= ?';
            $par[] = $f['de'];
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $f['ate'] ?? '')) {
            $w[] = 'p.criado_em < ? + INTERVAL 1 DAY';
            $par[] = $f['ate'];
        }
        return [implode(' AND ', $w), $par];
    }

    public static function listar(array $f, int $pagina, int $porPagina = 30): array
    {
        [$where, $par] = self::montarWhere($f);
        $resumo = Banco::um('SELECT COUNT(*) AS total, COALESCE(SUM(p.total), 0) AS valor ' . self::JUNCOES . " WHERE $where", $par);
        $total = (int) $resumo['total'];
        $paginas = max(1, (int) ceil($total / $porPagina));
        $pagina = min(max(1, $pagina), $paginas);
        $itens = Banco::todos('SELECT ' . self::CAMPOS . ' ' . self::JUNCOES . " WHERE $where ORDER BY p.criado_em DESC, p.id DESC LIMIT $porPagina OFFSET " . (($pagina - 1) * $porPagina), $par);
        return ['itens' => $itens, 'total' => $total, 'valor' => (float) $resumo['valor'], 'pagina' => $pagina, 'paginas' => $paginas, 'por_pagina' => $porPagina];
    }

    public static function buscar(int $id): ?array
    {
        return Banco::um('SELECT ' . self::CAMPOS . ' ' . self::JUNCOES . ' WHERE p.id = ?', [$id]);
    }

    /** A equipe pode ver este pedido? */
    public static function podeVer(array $p): bool
    {
        return Auth::pode('pedidos.ver_todos') || Auth::pode('pedidos.aprovar') || (int) $p['vendedor_id'] === Auth::id() || (int) $p['responsavel_id'] === Auth::id();
    }

    public static function itens(int $pedidoId): array
    {
        return Banco::todos('SELECT i.*, COALESCE(i.codigo, pr.codigo) AS codigo, pr.slug, m.arquivo AS imagem FROM pedido_itens i LEFT JOIN produtos pr ON pr.id = i.produto_id
            LEFT JOIN midias m ON m.id = pr.imagem_id WHERE i.pedido_id = ? ORDER BY i.nome', [$pedidoId]);
    }

    public static function historico(int $pedidoId): array
    {
        return Banco::todos('SELECT h.*, u.nome AS usuario_nome FROM pedido_historico h LEFT JOIN usuarios u ON u.id = h.usuario_id
            WHERE h.pedido_id = ? ORDER BY h.criado_em DESC, h.id DESC', [$pedidoId]);
    }

    /** Pedidos de um cliente (catálogo "Meus pedidos" e ficha da Carteira) */
    public static function doCliente(int $clienteId, int $limite = 20): array
    {
        return Banco::todos('SELECT ' . self::CAMPOS . ' ' . self::JUNCOES . ' WHERE p.cliente_id = ? ORDER BY p.criado_em DESC, p.id DESC LIMIT ' . (int) $limite, [$clienteId]);
    }

    /** Quadro de crédito do cliente (tela de aprovação do Financeiro) */
    public static function credito(int $clienteId): array
    {
        $r = Banco::um("SELECT COUNT(*) AS pedidos_12m, COALESCE(SUM(total), 0) AS comprado_12m, MAX(faturado_em) AS ultima_compra
            FROM pedidos WHERE cliente_id = ? AND situacao = 'faturado' AND faturado_em >= CURDATE() - INTERVAL 12 MONTH", [$clienteId]);
        $cob = Banco::um("SELECT COUNT(*) AS casos, COALESCE(SUM(valor_total), 0) AS em_aberto, MIN(vencimento_mais_antigo) AS vencimento
            FROM cobrancas WHERE cliente_id = ? AND situacao = 'aberta'", [$clienteId]);
        $r['cobranca_casos'] = (int) $cob['casos'];
        $r['cobranca_valor'] = (float) $cob['em_aberto'];
        $r['dias_atraso'] = $cob['vencimento'] ? (int) ((strtotime('today') - strtotime($cob['vencimento'])) / 86400) : 0;
        $r['aprovados_aberto'] = (float) Banco::valor("SELECT COALESCE(SUM(total), 0) FROM pedidos WHERE cliente_id = ? AND situacao = 'aprovado'", [$clienteId]);
        return $r;
    }

    /** Fila do Financeiro (mais antigos primeiro) */
    public static function aguardandoAprovacao(): array
    {
        return Banco::todos('SELECT ' . self::CAMPOS . ' ' . self::JUNCOES . " WHERE p.situacao = 'aguardando_aprovacao' ORDER BY p.criado_em, p.id");
    }
}
