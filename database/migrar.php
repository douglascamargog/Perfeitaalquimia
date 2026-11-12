<?php
/**
 * Atualiza o banco pelo terminal:
 *     php database/migrar.php            → tabelas + dados de demonstração
 *     php database/migrar.php --sem-seeds → só as tabelas
 *
 * Sem acesso ao terminal (HostGator sem SSH)? Use a tela /instalar (veja o README).
 */
if (PHP_SAPI !== 'cli') {
    exit('Use pelo terminal.');
}
require __DIR__ . '/../app/bootstrap.php';

$instalador = new App\Core\Instalador();
$instalador->executar(!in_array('--sem-seeds', $argv, true));
echo implode(PHP_EOL, $instalador->log) . PHP_EOL;
