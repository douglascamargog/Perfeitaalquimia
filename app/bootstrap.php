<?php
/**
 * Inicialização do sistema. Todo ponto de entrada (site, cron, scripts) começa por aqui.
 *
 * Faz 4 coisas:
 *  1. Carrega a configuração (config/config.php)
 *  2. Registra o "autoload": quando o código usa uma classe App\..., o arquivo é carregado sozinho
 *  3. Carrega as funções de ajuda (e(), url(), dinheiro()...)
 *  4. Configura fuso horário e exibição de erros
 */
declare(strict_types=1);

// Pasta raiz do projeto (uma acima de /app)
define('RAIZ', dirname(__DIR__));

// 1. Configuração
$arquivoConfig = RAIZ . '/config/config.php';
if (!is_file($arquivoConfig)) {
    http_response_code(500);
    exit('Configuração não encontrada. Copie config/config.example.php para config/config.php e preencha os dados.');
}
$GLOBALS['config'] = require $arquivoConfig;

// 2. Autoload: App\Core\Banco  →  app/Core/Banco.php
spl_autoload_register(function (string $classe): void {
    if (!str_starts_with($classe, 'App\\')) {
        return;
    }
    $arquivo = RAIZ . '/app/' . str_replace('\\', '/', substr($classe, 4)) . '.php';
    if (is_file($arquivo)) {
        require $arquivo;
    }
});

// 3. Funções de ajuda usadas em todo lugar
require RAIZ . '/app/ajudantes.php';

// 4. Fuso e erros
date_default_timezone_set(config('fuso_horario', 'America/Sao_Paulo'));
mb_internal_encoding('UTF-8');

if (config('depurar')) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    // Em produção o erro vai para o arquivo de log, nunca para a tela (LGPD)
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    ini_set('error_log', RAIZ . '/storage/logs/php-erros.log');
}
