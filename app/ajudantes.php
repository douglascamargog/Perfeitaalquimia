<?php
/**
 * Funções de ajuda curtas, usadas nas telas e nos controllers.
 * Ficam soltas (sem classe) para serem fáceis de usar nos templates.
 */

use App\Core\Csrf;
use App\Core\Sessao;

/** Lê um item da configuração. Ex.: config('banco.host') */
function config(string $chave, mixed $padrao = null): mixed
{
    $valor = $GLOBALS['config'] ?? [];
    foreach (explode('.', $chave) as $parte) {
        if (!is_array($valor) || !array_key_exists($parte, $valor)) {
            return $padrao;
        }
        $valor = $valor[$parte];
    }
    return $valor;
}

/**
 * "Escapa" um texto para exibir no HTML com segurança.
 * USE SEMPRE ao mostrar qualquer dado: <?= e($cliente['nome']) ?>
 */
function e(mixed $texto): string
{
    return htmlspecialchars((string) ($texto ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Pasta base do site (ex.: '' ou '/sistema'). Detectada sozinha se não estiver na config. */
function url_base(): string
{
    static $base = null;
    if ($base !== null) {
        return $base;
    }
    $configurada = config('url_base', '');
    if ($configurada !== '') {
        return $base = rtrim($configurada, '/');
    }
    // Ex.: SCRIPT_NAME = /sistema/public/index.php → /sistema/public
    // (só confia no SCRIPT_NAME quando ele aponta para o index.php; o servidor embutido do PHP às vezes põe a URL pedida)
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $base = basename($script) === 'index.php' ? rtrim(dirname($script), '/') : '';
    // Quando o .htaccess da raiz redireciona para /public, a URL visível não tem "/public"
    $pedido = $_SERVER['REQUEST_URI'] ?? '';
    if (str_ends_with($base, '/public') && !str_starts_with($pedido, $base)) {
        $base = substr($base, 0, -strlen('/public'));
    }
    return $base;
}

/** Monta um endereço interno. Ex.: url('/usuarios') */
function url(string $caminho = '/'): string
{
    return url_base() . '/' . ltrim($caminho, '/');
}

/** Endereço de um arquivo estático (CSS, JS, imagem) com versão para evitar cache antigo */
function asset(string $caminho): string
{
    $arquivo = RAIZ . '/public/assets/' . ltrim($caminho, '/');
    $versao = is_file($arquivo) ? filemtime($arquivo) : 0;
    return url('/assets/' . ltrim($caminho, '/')) . '?v=' . $versao;
}

/** Campo escondido com o token CSRF. Coloque dentro de TODO <form method="post"> */
function csrf_campo(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(Csrf::token()) . '">';
}

/** Valor em reais: 1234.5 → "R$ 1.234,50" */
function dinheiro(float|int|string|null $valor): string
{
    return 'R$ ' . number_format((float) $valor, 2, ',', '.');
}

/** Data no formato brasileiro: '2026-11-12 10:30:00' → '12/11/2026' (ou com hora) */
function data_br(?string $data, bool $comHora = false): string
{
    if (!$data) {
        return '—';
    }
    $ts = strtotime($data);
    return $ts ? date($comHora ? 'd/m/Y H:i' : 'd/m/Y', $ts) : '—';
}

/** CNPJ com máscara: '12345678000190' → '12.345.678/0001-90' */
function cnpj_formatado(?string $cnpj): string
{
    $d = preg_replace('/\D/', '', (string) $cnpj);
    if (strlen($d) !== 14) {
        return (string) $cnpj;
    }
    return vsprintf('%s.%s.%s/%s-%s', [substr($d, 0, 2), substr($d, 2, 3), substr($d, 5, 3), substr($d, 8, 4), substr($d, 12, 2)]);
}

/** Valor digitado anteriormente no formulário (para não perder o que o usuário escreveu após um erro) */
function antigo(string $campo, mixed $padrao = ''): string
{
    return e(Sessao::antigo($campo, $padrao));
}

/** Envia o navegador para outra página e encerra */
function redirecionar(string $caminho): never
{
    header('Location: ' . url($caminho));
    exit;
}
