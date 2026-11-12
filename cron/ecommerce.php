<?php
/**
 * Tarefa agendada: copia os números da loja virtual (Tray) para o painel do e-commerce.
 * Não duplica: cada dia é atualizado no lugar.
 *
 * Configure no cPanel (Cron Jobs) para rodar de hora em hora:
 *     0 * * * *  php /home/USUARIO/public_html/cron/ecommerce.php
 */
if (PHP_SAPI !== 'cli') {
    exit('Use pelo cron.');
}
require __DIR__ . '/../app/bootstrap.php';

$r = App\Services\EcommerceService::sincronizar();
echo date('d/m/Y H:i') . " — E-commerce: {$r['dias']} dia(s) e {$r['meses']} mês(es) atualizados." . PHP_EOL;
