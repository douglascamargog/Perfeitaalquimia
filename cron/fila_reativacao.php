<?php
/**
 * Tarefa agendada: manda para a fila de reativação os clientes sem compra há mais de X dias.
 *
 * Configure no cPanel (Cron Jobs) para rodar 1 vez por dia, por exemplo às 6h:
 *     0 6 * * *  php /home/USUARIO/public_html/cron/fila_reativacao.php
 */
if (PHP_SAPI !== 'cli') {
    exit('Use pelo cron.');
}
require __DIR__ . '/../app/bootstrap.php';

$movidos = App\Services\CarteiraService::processarInatividade(null);
echo date('d/m/Y H:i') . " — $movidos cliente(s) enviados para a fila de reativação." . PHP_EOL;
