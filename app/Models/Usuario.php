<?php
namespace App\Models;

use App\Core\Banco;

/**
 * Acesso à tabela `usuarios` (e às funções e permissões ligadas a ele).
 */
class Usuario
{
    /** Usuário com o nome do perfil */
    public static function buscar(int $id): ?array
    {
        return Banco::um(
            'SELECT u.*, p.slug AS perfil_slug, p.nome AS perfil_nome
               FROM usuarios u JOIN perfis p ON p.id = u.perfil_id
              WHERE u.id = ?',
            [$id]
        );
    }

    public static function buscarPorEmail(string $email): ?array
    {
        return Banco::um('SELECT * FROM usuarios WHERE email = ?', [mb_strtolower(trim($email))]);
    }

    /** Lista para a tela de usuários, com as funções de cada um */
    public static function listar(): array
    {
        $usuarios = Banco::todos(
            'SELECT u.*, p.nome AS perfil_nome,
                    (SELECT GROUP_CONCAT(CONCAT(f.nome, IF(uf.gestor = 1, " (gestão)", "")) ORDER BY f.ordem SEPARATOR ", ")
                       FROM usuario_funcoes uf JOIN funcoes f ON f.id = uf.funcao_id
                      WHERE uf.usuario_id = u.id) AS funcoes_nomes
               FROM usuarios u JOIN perfis p ON p.id = u.perfil_id
              ORDER BY u.ativo DESC, p.id, u.nome'
        );
        return $usuarios;
    }

    /** Lista de permissões (slugs) do perfil */
    public static function permissoes(int $perfilId, ?int $usuarioId = null): array
    {
        $lista = array_column(Banco::todos('SELECT permissao FROM perfil_permissoes WHERE perfil_id = ?', [$perfilId]), 'permissao');
        if ($usuarioId === null || in_array('*', $lista, true)) {
            return $lista; // o Admin tem tudo (exceções não tiram nada dele)
        }
        // Exceções desta pessoa (Administração › Usuários): concedida = 1 dá, 0 tira
        foreach (Banco::todos('SELECT permissao, concedida FROM usuario_permissoes WHERE usuario_id = ?', [$usuarioId]) as $e) {
            $lista = $e['concedida'] ? array_values(array_unique([...$lista, $e['permissao']])) : array_values(array_diff($lista, [$e['permissao']]));
        }
        return $lista;
    }

    /** Funções às quais a pessoa está vinculada */
    public static function funcoesDe(int $usuarioId): array
    {
        return Banco::todos(
            'SELECT f.id, f.slug, f.nome, uf.gestor FROM usuario_funcoes uf JOIN funcoes f ON f.id = uf.funcao_id
              WHERE uf.usuario_id = ? ORDER BY f.ordem',
            [$usuarioId]
        );
    }

    /**
     * Substitui as funções da pessoa pela lista de ids informada.
     * $gestorIds: funções (setores de venda) que a pessoa também GERENCIA (ex.: gestora do Televendas que vende).
     */
    public static function definirFuncoes(int $usuarioId, array $funcaoIds, array $gestorIds = []): void
    {
        $gestorIds = array_map('intval', $gestorIds);
        Banco::executar('DELETE FROM usuario_funcoes WHERE usuario_id = ?', [$usuarioId]);
        foreach (array_unique(array_map('intval', $funcaoIds)) as $funcaoId) {
            Banco::inserir('usuario_funcoes', ['usuario_id' => $usuarioId, 'funcao_id' => $funcaoId, 'gestor' => in_array($funcaoId, $gestorIds, true) ? 1 : 0]);
        }
    }

    public static function perfis(): array
    {
        return Banco::todos('SELECT * FROM perfis ORDER BY id');
    }
}
