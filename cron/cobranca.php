<?php
/**
 * Tarefa agendada da Cobrança:
 *   1. Lê os títulos do ERP (OmieFake no protótipo, Omie depois): vencidos há X dias entram na Cobrança,
 *      pagos no ERP são baixados aqui (e o caso é encerrado quando zera).
 *   2. Promessas de pagamento vencidas voltam para "Tentativa de contato".
 *
 * Configure no cPanel (Cron Jobs) para rodar 1 vez por dia, por exemplo às 7h:
 *     0 7 * * *  php /home/USUARIO/public_html/cron/cobranca.php
 */
if (PHP_SAPI !== 'cli') {
    exit('Use pelo cron.');
}
require __DIR__ . '/../app/bootstrap.php';

use App\Services\CobrancaService;

$r = CobrancaService::importarDoErp(null);
$quebradas = CobrancaService::verificarPromessas();
echo date('d/m/Y H:i') . " — Cobrança: {$r['casos']} caso(s) novo(s)/atualizado(s), {$r['pagos']} título(s) pago(s) baixado(s), "
    . "{$r['sem_cadastro']} CNPJ(s) sem cadastro, $quebradas promessa(s) vencida(s)." . PHP_EOL;
