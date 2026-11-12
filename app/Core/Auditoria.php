<?php
namespace App\Core;

/**
 * Log de auditoria: registra QUEM fez O QUÊ e QUANDO.
 * Usado em alterações de clientes, pedidos, usuários e configurações.
 *
 * Exemplo:
 *     Auditoria::registrar('cliente', $id, 'editar', $dadosAntes, $dadosDepois);
 *
 * LGPD: esta tabela só é vista na tela de Auditoria (perfil admin). Senhas nunca são gravadas.
 */
class Auditoria
{
    /** Campos que nunca vão para o log */
    private const CAMPOS_SECRETOS = ['senha', 'senha_hash', 'codigo_hash', 'app_secret', '_csrf'];

    public static function registrar(string $entidade, ?int $entidadeId, string $acao, ?array $antes = null, ?array $depois = null): void
    {
        // Guarda só o que mudou, para o log ficar fácil de ler
        if ($antes !== null && $depois !== null) {
            [$antes, $depois] = self::somenteDiferencas($antes, $depois);
        }
        Banco::inserir('auditoria', [
            'usuario_id'  => Sessao::get('usuario_id'),
            'entidade'    => $entidade,
            'entidade_id' => $entidadeId,
            'acao'        => $acao,
            'antes_json'  => $antes ? json_encode(self::limpar($antes), JSON_UNESCAPED_UNICODE) : null,
            'depois_json' => $depois ? json_encode(self::limpar($depois), JSON_UNESCAPED_UNICODE) : null,
            'ip'          => $_SERVER['REMOTE_ADDR'] ?? 'cli',
        ]);
    }

    private static function limpar(array $dados): array
    {
        foreach (self::CAMPOS_SECRETOS as $campo) {
            if (array_key_exists($campo, $dados)) {
                $dados[$campo] = '***';
            }
        }
        return $dados;
    }

    private static function somenteDiferencas(array $antes, array $depois): array
    {
        $a = $d = [];
        foreach ($depois as $campo => $valor) {
            if (!array_key_exists($campo, $antes) || (string) $antes[$campo] !== (string) $valor) {
                $a[$campo] = $antes[$campo] ?? null;
                $d[$campo] = $valor;
            }
        }
        return [$a, $d];
    }
}
