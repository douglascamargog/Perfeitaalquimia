<?php
/**
 * Tarefa agendada: busca no Ligue Talk as chamadas e gravações novas (Gestão › Acompanhamento).
 * Só faz algo com Configurações › Ligue Talk › Modo = "api" e company_id/token no config.php.
 * Respeita o limite do Ligue Talk (10 requisições por minuto): rode a cada 15 ou 30 minutos, por exemplo:
 *     0,20,40 * * * *  php /home/USUARIO/public_html/cron/sincronizar_ligacoes.php
 */
if (PHP_SAPI !== 'cli') {
    exit('Use pelo cron.');
}
require __DIR__ . '/../app/bootstrap.php';

$novas = App\Services\TelefoniaService::sincronizar();
echo date('d/m/Y H:i') . " — $novas ligação(ões) nova(s) do Ligue Talk." . PHP_EOL;
