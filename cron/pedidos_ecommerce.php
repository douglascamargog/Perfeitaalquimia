<?php
/**
 * Tarefa agendada: busca na Tray os pedidos pagos do e-commerce (Mercado Livre, Shopee, Amazon, loja própria).
 * Pedido com nota emitida entra sozinho na fila de separação do Depósito, com o horário limite do canal.
 * Não duplica: o código da Tray garante que cada pedido entra uma vez só.
 *
 * Configure no cPanel (Cron Jobs) para rodar a cada 15 minutos
 * (campo minuto: barra-15, ou seja, de 15 em 15; os demais campos com asterisco):
 *     php /home/USUARIO/public_html/cron/pedidos_ecommerce.php
 */
if (PHP_SAPI !== 'cli') {
    exit('Use pelo cron.');
}
require __DIR__ . '/../app/bootstrap.php';

$r = App\Services\EcommercePedidoService::importar();
echo date('d/m/Y H:i') . " — Tray: {$r['novos']} pedido(s) novo(s), {$r['na_fila']} na fila do Depósito, {$r['cancelados']} cancelado(s)." . PHP_EOL;
