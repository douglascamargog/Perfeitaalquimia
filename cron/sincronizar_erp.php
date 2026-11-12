<?php
/**
 * Tarefa agendada: sincroniza com o ERP (Omie; simulado no protótipo).
 *   - preços das 3 tabelas e estoque → catálogo
 *   - reenvia pedidos que ficaram sem envio (o uuid evita duplicar)
 *   - pedidos aprovados que o ERP faturou → "faturado" + NF na ficha do cliente
 *
 * Configure no cPanel (Cron Jobs) a cada 30 minutos:
  *     0,30 * * * *  php /home/USUARIO/public_html/cron/sincronizar_erp.php
 */
if (PHP_SAPI !== 'cli') {
    exit('Use pelo cron.');
}
require __DIR__ . '/../app/bootstrap.php';

$r = App\Services\ErpSync::tudo();
echo date('d/m/Y H:i') . " — ERP: {$r['precos']} preço(s), {$r['estoque']} estoque(s), {$r['enviados']} pedido(s) reenviado(s), {$r['faturados']} faturado(s)." . PHP_EOL;
