<?php
/**
 * Porta de entrada do site. Toda página passa por aqui:
 * o .htaccess manda qualquer endereço para este arquivo, que chama o roteador.
 */

// No servidor embutido do PHP (php -S), arquivos reais (CSS, JS, imagens) são servidos direto
if (PHP_SAPI === 'cli-server' && is_file(__DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH))) {
    return false;
}

require __DIR__ . '/../app/bootstrap.php';

use App\Core\Roteador;
use App\Core\Sessao;

// Cabeçalhos de segurança básicos
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: same-origin');

// Versão nova extraída? Aplica as mudanças do banco antes de qualquer tela (com backup antes).
App\Core\AtualizacaoAutomatica::verificar();

Sessao::iniciar();
// Toda ação (POST) pode mudar o que está pendente: o contador do menu é recalculado na próxima página
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    Sessao::remover('pendencias');
}

// Caminho pedido, sem a pasta base e sem a "?query"
$caminho = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
if (url_base() !== '' && str_starts_with($caminho, url_base())) {
    $caminho = substr($caminho, strlen(url_base())) ?: '/';
}

$roteador = new Roteador(require RAIZ . '/app/rotas.php');
try {
    $roteador->despachar($_SERVER['REQUEST_METHOD'] ?? 'GET', $caminho);
} catch (\Throwable $erro) {
    error_log((string) $erro);
    if (config('depurar')) {
        throw $erro;
    }
    try {
        $roteador->erro(500, 'Algo deu errado. O erro foi registrado; tente de novo em instantes.');
    } catch (\Throwable $erroNaPagina) { // nem a página de erro abriu (ex.: banco fora do ar): mensagem simples
        error_log((string) $erroNaPagina);
        http_response_code(500);
        echo '<!doctype html><meta charset="utf-8"><title>One</title><p style="font-family:system-ui;padding:24px">Algo deu errado. '
            . 'O erro foi registrado (storage/logs/php-erros.log). Tente de novo em instantes.</p>';
    }
}
