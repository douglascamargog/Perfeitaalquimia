<?php
/**
 * Tarefa agendada da Cobrança:
 *   1. Lê os títulos do ERP para a ficha dos clientes (titulos_receber) e a Cobrança se atualiza a partir deles:
 *      vencidos há X dias entram na Cobrança, pagos são baixados (e o caso é encerrado quando zera).
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

try {
    $r = CobrancaService::importarDoErp(null); // títulos do ERP → ficha do cliente → Cobrança
} catch (\Throwable $e) {                     // ERP fora do ar ou ainda não ligado: a Cobrança se atualiza com os títulos que já estão no One
    error_log('Cobrança (ERP): ' . $e->getMessage());
    $s = CobrancaService::sincronizarTitulos(null);
    $r = ['casos' => $s['casos'] + $s['casos_com_titulo_novo'], 'pagos' => $s['baixas'], 'sem_cadastro' => 0];
}
$quebradas = CobrancaService::verificarPromessas();
echo date('d/m/Y H:i') . " — Cobrança: {$r['casos']} caso(s) novo(s)/atualizado(s), {$r['pagos']} título(s) pago(s) baixado(s), "
    . "{$r['sem_cadastro']} CNPJ(s) sem cadastro, $quebradas promessa(s) vencida(s)." . PHP_EOL;
