<?php
namespace App\Models;

use App\Core\Banco;

/**
 * Produtos do catálogo (o MESMO cadastro da Academia) com o preço da tabela do cliente.
 */
class Produto
{
    /**
     * Vitrine: produtos ativos e visíveis no catálogo.
     * $tabelaId = tabela do cliente logado (null = visitante: sem preço).
     */
    public static function vitrine(string $busca, ?int $categoriaId, ?int $tabelaId, bool $mostrarSemPreco): array
    {
        $w = ['p.ativo = 1', 'p.visivel_catalogo = 1'];
        $par = [$tabelaId ?? 0];
        if ($busca !== '') {
            $w[] = '(p.nome LIKE ? OR p.chamada LIKE ? OR p.codigo LIKE ?)';
            array_push($par, "%$busca%", "%$busca%", "$busca%");
        }
        if ($categoriaId) {
            $w[] = 'p.categoria_id = ?';
            $par[] = $categoriaId;
        }
        if ($tabelaId && !$mostrarSemPreco) {
            $w[] = 'pp.preco IS NOT NULL';
        }
        return Banco::todos('SELECT p.id, p.slug, p.codigo, p.nome, p.embalagem, p.chamada, c.nome AS categoria, m.arquivo AS imagem, pp.preco
            FROM produtos p LEFT JOIN categorias c ON c.id = p.categoria_id LEFT JOIN midias m ON m.id = p.imagem_id
            LEFT JOIN produto_precos pp ON pp.produto_id = p.id AND pp.tabela_preco_id = ?
            WHERE ' . implode(' AND ', $w) . ' ORDER BY p.ordem, p.nome', $par);
    }

    public static function categorias(): array
    {
        return Banco::todos('SELECT c.id, c.nome, COUNT(p.id) AS total FROM categorias c JOIN produtos p ON p.categoria_id = c.id AND p.ativo = 1 AND p.visivel_catalogo = 1
            GROUP BY c.id, c.nome, c.ordem ORDER BY c.ordem, c.nome');
    }

    public static function porSlug(string $slug, ?int $tabelaId): ?array
    {
        $p = Banco::um('SELECT p.*, c.nome AS categoria, m.arquivo AS imagem, pp.preco FROM produtos p
            LEFT JOIN categorias c ON c.id = p.categoria_id LEFT JOIN midias m ON m.id = p.imagem_id
            LEFT JOIN produto_precos pp ON pp.produto_id = p.id AND pp.tabela_preco_id = ?
            WHERE p.slug = ? AND p.ativo = 1 AND p.visivel_catalogo = 1', [$tabelaId ?? 0, $slug]);
        if ($p) {
            $p['ficha'] = json_decode((string) $p['ficha_json'], true) ?: [];
        }
        return $p;
    }

    /** Lista para a tela de Produtos (equipe): preços das tabelas e estoque */
    public static function administracao(string $busca = ''): array
    {
        $tabelas = Banco::todos('SELECT id, nome FROM tabelas_preco WHERE ativa = 1 ORDER BY id');
        $par = [];
        $w = '1 = 1';
        if ($busca !== '') {
            $w = '(p.nome LIKE ? OR p.codigo LIKE ?)';
            array_push($par, "%$busca%", "$busca%");
        }
        $produtos = Banco::todos("SELECT p.id, p.slug, p.codigo, p.nome, p.embalagem, p.chamada, p.ordem, p.ativo, p.visivel_catalogo, c.nome AS categoria, m.arquivo AS imagem,
                e.quantidade AS estoque FROM produtos p LEFT JOIN categorias c ON c.id = p.categoria_id LEFT JOIN midias m ON m.id = p.imagem_id
                LEFT JOIN produto_estoque e ON e.produto_id = p.id WHERE $w ORDER BY p.ordem, p.nome", $par);
        $precos = [];
        foreach (Banco::todos('SELECT produto_id, tabela_preco_id, preco FROM produto_precos') as $pp) {
            $precos[$pp['produto_id']][$pp['tabela_preco_id']] = (float) $pp['preco'];
        }
        foreach ($produtos as &$p) {
            $p['precos'] = $precos[$p['id']] ?? [];
        }
        return ['tabelas' => $tabelas, 'produtos' => $produtos];
    }
}
