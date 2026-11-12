<?php
namespace App\Core;

/**
 * Proteção CSRF: impede que outro site envie formulários em nome do usuário logado.
 * Cada sessão tem um token secreto; todo POST precisa trazê-lo (campo _csrf ou cabeçalho X-CSRF-Token).
 */
class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf'];
    }

    /** Confere o token enviado. Se não bater, a requisição é recusada. */
    public static function valido(): bool
    {
        $enviado = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        return is_string($enviado) && $enviado !== '' && hash_equals(self::token(), $enviado);
    }
}
