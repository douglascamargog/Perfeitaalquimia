<?php
/**
 * Tarefa agendada: backup automático do banco, uma vez por dia.
 * Guarda em storage/backups/ (fora da web) e apaga os mais antigos que "Configurações › Backup › dias".
 *
 * Configure no cPanel (Cron Jobs), todo dia de madrugada:
 *     15 3 * * *  php /home/USUARIO/public_html/cron/backup.php
 * O arquivo tem TODOS os dados (LGPD): de vez em quando, baixe uma cópia para fora do servidor.
 */
if (PHP_SAPI !== 'cli') {
    exit('Use pelo cron.');
}
require __DIR__ . '/../app/bootstrap.php';

$arquivo = App\Services\BackupBanco::automatico();
echo date('d/m/Y H:i') . ' — backup salvo: ' . basename($arquivo) . ' (' . round(filesize($arquivo) / 1024) . ' KB).' . PHP_EOL;
