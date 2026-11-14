<?php
namespace App\Core;

use App\Models\Usuario;

/**
 * Login individual e permissões.
 *
 * - Senhas guardadas só como "hash" (password_hash): nem quem acessa o banco vê a senha.
 * - Bloqueio temporário após várias tentativas erradas (configurável).
 * - Sessão expira após X minutos sem uso (configurável).
 * - Permissões vêm do PERFIL do usuário (tabela perfil_permissoes). O perfil admin tem '*' (tudo).
 */
class Auth
{
    private static ?array $usuario = null;

    /**
     * Tenta fazer login. Devolve null se deu certo, ou a mensagem de erro.
     */
    public static function entrar(string $email, string $senha): ?string
    {
        $email = mb_strtolower(trim($email));
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $maxTentativas = (int) Config::get('seguranca.login_max_tentativas', 5);
        $minutosBloqueio = (int) Config::get('seguranca.login_bloqueio_minutos', 15);

        // Quantas tentativas erradas recentes para este e-mail ou IP?
        $erros = (int) Banco::valor(
            'SELECT COUNT(*) FROM tentativas_login WHERE (email = ? OR ip = ?) AND criado_em > (NOW() - INTERVAL ? MINUTE)',
            [$email, $ip, $minutosBloqueio]
        );
        if ($erros >= $maxTentativas) {
            return "Muitas tentativas. Aguarde $minutosBloqueio minutos e tente de novo.";
        }

        $usuario = Usuario::buscarPorEmail($email);
        // password_verify roda mesmo se o usuário não existe, para não revelar quais e-mails existem
        $hash = $usuario['senha_hash'] ?? '$2y$10$LCYV8N7emDp60jzWBBMolecjAZG6MxcCP48tKxmp.CMoQMzw1P3c2';
        if (!password_verify($senha, $hash) || !$usuario || !$usuario['ativo']) {
            Banco::inserir('tentativas_login', ['email' => $email, 'ip' => $ip]);
            return 'E-mail ou senha incorretos.';
        }

        // Atualiza o hash se o PHP tiver um algoritmo mais forte disponível
        if (password_needs_rehash($usuario['senha_hash'], PASSWORD_DEFAULT)) {
            Banco::atualizar('usuarios', (int) $usuario['id'], ['senha_hash' => password_hash($senha, PASSWORD_DEFAULT)]);
        }

        Banco::executar('DELETE FROM tentativas_login WHERE email = ?', [$email]);
        Banco::executar('UPDATE usuarios SET ultimo_login_em = NOW() WHERE id = ?', [$usuario['id']]);

        Sessao::renovar();
        Sessao::set('usuario_id', (int) $usuario['id']);
        Sessao::set('ultimo_uso', time());
        Auditoria::registrar('usuario', (int) $usuario['id'], 'login');
        return null;
    }

    public static function sair(): void
    {
        if (self::logado()) {
            Auditoria::registrar('usuario', self::id(), 'logout');
        }
        Sessao::destruir();
        self::$usuario = null;
    }

    /** Usuário logado (com perfil, permissões e funções) ou null */
    public static function usuario(): ?array
    {
        if (self::$usuario !== null) {
            return self::$usuario;
        }
        $id = Sessao::get('usuario_id');
        if (!$id) {
            return null;
        }

        // Expira por inatividade
        $limite = (int) Config::get('seguranca.sessao_minutos', 120) * 60;
        if (time() - (int) Sessao::get('ultimo_uso', 0) > $limite) {
            Sessao::destruir();
            return null;
        }
        Sessao::set('ultimo_uso', time());

        $usuario = Usuario::buscar((int) $id);
        if (!$usuario || !$usuario['ativo']) {
            Sessao::destruir();
            return null;
        }
        $usuario['permissoes'] = Usuario::permissoes((int) $usuario['perfil_id'], (int) $usuario['id']);
        $usuario['funcoes'] = Usuario::funcoesDe((int) $usuario['id']);
        return self::$usuario = $usuario;
    }

    public static function logado(): bool
    {
        return self::usuario() !== null;
    }

    public static function id(): ?int
    {
        return self::usuario()['id'] ?? null;
    }

    /** O usuário logado tem esta permissão? Ex.: Auth::pode('usuarios.gerenciar') */
    public static function pode(string $permissao): bool
    {
        $permissoes = self::usuario()['permissoes'] ?? [];
        if ($permissao === 'setor.gestao') {
            // Não é uma permissão da grade: vale para quem é "gestor(a) do setor" (Administração › Usuários › Funções)
            return \App\Services\Setores::gerenciados() !== [];
        }
        return in_array('*', $permissoes, true) || in_array($permissao, $permissoes, true);
    }

    /** O usuário logado está vinculado a esta função? Ex.: Auth::temFuncao('prospeccao_reativacao') */
    public static function temFuncao(string $slugFuncao): bool
    {
        foreach (self::usuario()['funcoes'] ?? [] as $f) {
            if ($f['slug'] === $slugFuncao) {
                return true;
            }
        }
        return false;
    }
}
