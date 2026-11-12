<?php
namespace App\Models;

use App\Core\Banco;

/**
 * Funções comerciais (Televendas, Externo, Prospecção e Reativação...).
 * As regras do sistema usam o `slug` da função, nunca o nome de uma pessoa.
 */
class Funcao
{
    /** Todas as funções, com quantas pessoas estão em cada uma */
    public static function listar(bool $somenteAtivas = false): array
    {
        return Banco::todos(
            'SELECT f.*, (SELECT COUNT(*) FROM usuario_funcoes uf JOIN usuarios u ON u.id = uf.usuario_id
                           WHERE uf.funcao_id = f.id AND u.ativo = 1) AS pessoas
               FROM funcoes f ' . ($somenteAtivas ? 'WHERE f.ativa = 1 ' : '') . 'ORDER BY f.ordem, f.nome'
        );
    }

    public static function buscar(int $id): ?array
    {
        return Banco::um('SELECT * FROM funcoes WHERE id = ?', [$id]);
    }

    /** Gera um slug a partir do nome: "Pós-venda" → "pos_venda" */
    public static function gerarSlug(string $nome): string
    {
        $slug = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', mb_strtolower($nome));
        $slug = trim(preg_replace('/[^a-z0-9]+/', '_', (string) $slug), '_');
        $base = $slug ?: 'funcao';
        $i = 1;
        while (Banco::valor('SELECT 1 FROM funcoes WHERE slug = ?', [$slug])) {
            $slug = $base . '_' . ++$i;
        }
        return $slug;
    }
}
