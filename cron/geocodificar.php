<?php
/**
 * Tarefa agendada: localiza no mapa (Geoapify ou Google Maps, conforme ⚙️ Administração › Mapas) as lojas da Carteira sem localização exata ou com endereço mudado.
 * Vai aos poucos (Configurações › Carteira: "Lojas localizadas por rodada"). Sem a chave do serviço escolhido, não faz nada.
 *
 * Configure no cPanel (Cron Jobs) para rodar de hora em hora
 * (campo minuto: 0; os demais campos com asterisco):
 *     php /home/USUARIO/public_html/cron/geocodificar.php
 */
if (PHP_SAPI !== 'cli') {
    exit('Use pelo cron.');
}
require __DIR__ . '/../app/bootstrap.php';

$r = App\Services\LocalizacaoService::rodada(max(10, (int) App\Core\Config::get('carteira.geo_por_rodada', 200)));
echo date('d/m/Y H:i') . " — Mapa: {$r['achadas']} loja(s) localizada(s), {$r['nao_achadas']} sem endereço preciso." . ($r['erro'] ? ' ' . $r['erro'] : '') . PHP_EOL;
