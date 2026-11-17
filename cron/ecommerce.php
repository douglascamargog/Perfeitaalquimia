<?php
/**
 * Tarefa agendada: copia os pedidos da loja virtual (Tray: loja própria + marketplaces) para o painel do e-commerce.
 * Não duplica: cada pedido é atualizado no lugar.
 *
 * Configure no cPanel (Cron Jobs) para rodar a cada 30 minutos:
 *     */30 * * * *  php /home/USUARIO/public_html/cron/ecommerce.php
 */
if (PHP_SAPI !== 'cli') {
    exit('Use pelo cron.');
}
require __DIR__ . '/../app/bootstrap.php';

$r = App\Services\EcommerceService::sincronizar();
echo date('d/m/Y H:i') . ($r['ocupado'] ? ' — E-commerce: outra atualização já estava rodando.' : " — E-commerce: {$r['novos']} pedido(s) novo(s), {$r['atualizados']} alterado(s), {$r['detalhados']} lido(s) por completo, faltam {$r['faltam']}.") . PHP_EOL;
