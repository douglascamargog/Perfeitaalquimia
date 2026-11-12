<?php
namespace App\Core;

/**
 * Envio de e-mail simples pelo mail() do PHP (funciona na HostGator; no servidor próprio, configurar o sendmail/SMTP).
 * Remetente: Configurações › Catálogo › E-mail que envia o código (crie a conta no cPanel).
 */
class Email
{
    /** Devolve true se o servidor aceitou o envio (não garante que chegou) */
    public static function enviar(string $para, string $assunto, string $texto): bool
    {
        if (!filter_var($para, FILTER_VALIDATE_EMAIL) || !function_exists('mail')) {
            return false;
        }
        $remetente = (string) Config::get('catalogo.email_remetente', '');
        $nome = (string) Config::get('empresa.nome', 'Perfeita Alquimia');
        $cabecalhos = ['Content-Type: text/plain; charset=UTF-8', 'MIME-Version: 1.0'];
        if (filter_var($remetente, FILTER_VALIDATE_EMAIL)) {
            $cabecalhos[] = 'From: =?UTF-8?B?' . base64_encode($nome) . "?= <$remetente>";
        }
        try {
            return @mail($para, '=?UTF-8?B?' . base64_encode($assunto) . '?=', $texto, implode("\r\n", $cabecalhos));
        } catch (\Throwable $erro) {
            error_log('E-mail não enviado: ' . $erro->getMessage());
            return false;
        }
    }
}
