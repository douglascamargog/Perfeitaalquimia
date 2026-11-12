<?php
namespace App\Core;

/**
 * Sessão do navegador (quem está logado, mensagens de aviso, dados do formulário).
 * Configurada de forma segura: cookie inacessível ao JavaScript e protegido contra uso por outros sites.
 */
class Sessao
{
    public static function iniciar(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';

        session_name('pa_sessao');
        session_set_cookie_params([
            'lifetime' => 0,          // some ao fechar o navegador
            'path'     => url_base() ?: '/',
            'secure'   => $https,     // só via HTTPS quando disponível
            'httponly' => true,       // JavaScript não lê o cookie
            'samesite' => 'Lax',      // outro site não consegue usar a sessão
        ]);
        ini_set('session.use_strict_mode', '1');
        session_start();

        // Os dados de formulário antigos valem só para a próxima página
        $_SESSION['_antigo'] = $_SESSION['_antigo_proximo'] ?? [];
        unset($_SESSION['_antigo_proximo']);
    }

    /** Troca o identificador da sessão (fazer sempre no login, evita sequestro de sessão) */
    public static function renovar(): void
    {
        session_regenerate_id(true);
    }

    public static function destruir(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 3600, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
    }

    public static function get(string $chave, mixed $padrao = null): mixed
    {
        return $_SESSION[$chave] ?? $padrao;
    }

    public static function set(string $chave, mixed $valor): void
    {
        $_SESSION[$chave] = $valor;
    }

    public static function remover(string $chave): void
    {
        unset($_SESSION[$chave]);
    }

    /** Mensagem que aparece uma única vez na próxima tela. Tipos: sucesso, erro, aviso */
    public static function aviso(string $tipo, string $mensagem): void
    {
        $_SESSION['_avisos'][] = ['tipo' => $tipo, 'mensagem' => $mensagem];
    }

    /** Pega e apaga os avisos pendentes */
    public static function avisos(): array
    {
        $avisos = $_SESSION['_avisos'] ?? [];
        unset($_SESSION['_avisos']);
        return $avisos;
    }

    /** Guarda o que foi digitado para reexibir no formulário depois de um erro (sem senhas) */
    public static function guardarFormulario(array $dados): void
    {
        unset($dados['senha'], $dados['senha_confirmacao'], $dados['senha_atual'], $dados['_csrf']);
        $_SESSION['_antigo_proximo'] = $dados;
    }

    public static function antigo(string $campo, mixed $padrao = ''): mixed
    {
        return $_SESSION['_antigo'][$campo] ?? $padrao;
    }
}
