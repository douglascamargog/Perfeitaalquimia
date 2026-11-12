<?php
namespace App\Models;

use App\Core\Banco;

/**
 * Consultas dos posts do Instagram (mural do Marketing) e das campanhas.
 */
class Post
{
    public const FORMATOS = ['feed' => 'Feed', 'carrossel' => 'Carrossel', 'reels' => 'Reels', 'stories' => 'Stories'];

    private const CAMPOS = 'p.*, e.nome AS etapa, e.cor AS etapa_cor, e.tipo AS etapa_tipo, m.arquivo AS imagem,
        c.nome AS campanha, c.cor AS campanha_cor, u.nome AS responsavel, a.nome AS aprovador';
    private const JUNCOES = 'FROM mkt_posts p
        JOIN mkt_post_etapas e ON e.id = p.etapa_id
        LEFT JOIN midias m ON m.id = p.midia_id
        LEFT JOIN mkt_campanhas c ON c.id = p.campanha_id
        LEFT JOIN usuarios u ON u.id = p.responsavel_id
        LEFT JOIN usuarios a ON a.id = p.aprovado_por';

    /** Posts de um período, com filtros opcionais (formato, etapa, campanha) */
    public static function doPeriodo(string $desde, string $ate, array $f = []): array
    {
        $w = ['p.data_publicacao BETWEEN ? AND ?'];
        $par = [$desde, $ate];
        foreach (['formato' => 'p.formato', 'etapa' => 'p.etapa_id', 'campanha' => 'p.campanha_id'] as $campo => $coluna) {
            if (($f[$campo] ?? '') !== '') {
                $w[] = "$coluna = ?";
                $par[] = $f[$campo];
            }
        }
        return Banco::todos('SELECT ' . self::CAMPOS . ' ' . self::JUNCOES . ' WHERE ' . implode(' AND ', $w) . ' ORDER BY p.data_publicacao, p.hora IS NULL, p.hora, p.id', $par);
    }

    public static function buscar(int $id): ?array
    {
        return Banco::um('SELECT ' . self::CAMPOS . ' ' . self::JUNCOES . ' WHERE p.id = ?', [$id]);
    }

    public static function comentarios(int $postId): array
    {
        return Banco::todos('SELECT c.*, u.nome AS usuario_nome FROM mkt_post_comentarios c LEFT JOIN usuarios u ON u.id = c.usuario_id
            WHERE c.post_id = ? ORDER BY c.criado_em DESC, c.id DESC LIMIT 50', [$postId]);
    }

    /** Campanhas que tocam o período */
    public static function campanhas(string $desde, string $ate): array
    {
        return Banco::todos('SELECT c.*, pr.nome AS produto, pr.slug AS produto_slug,
                (SELECT COUNT(*) FROM mkt_posts p WHERE p.campanha_id = c.id) AS posts,
                (SELECT COUNT(*) FROM mkt_posts p JOIN mkt_post_etapas e ON e.id = p.etapa_id WHERE p.campanha_id = c.id AND e.tipo = \'publicado\') AS publicados
              FROM mkt_campanhas c LEFT JOIN produtos pr ON pr.id = c.produto_id
             WHERE c.fim >= ? AND c.inicio <= ? ORDER BY c.inicio, c.id', [$desde, $ate]);
    }

    /** Todas as campanhas (para selects), mais recentes primeiro */
    public static function listaCampanhas(): array
    {
        return Banco::todos('SELECT id, nome, inicio, fim, cor FROM mkt_campanhas WHERE fim >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH) ORDER BY inicio DESC');
    }

    /** Pessoas que fazem posts (perfil Marketing ou Admin) */
    public static function responsaveis(): array
    {
        return Banco::todos("SELECT u.id, u.nome FROM usuarios u JOIN perfis p ON p.id = u.perfil_id
            WHERE u.ativo = 1 AND p.slug IN ('marketing', 'admin') ORDER BY p.slug = 'admin', u.nome");
    }
}
