<?php
/**
 * Atualiza o banco pelo terminal:
 *     php database/migrar.php                → instalação nova: tabelas + dados essenciais + demonstração
 *     php database/migrar.php --atualizacao  → versão nova num sistema em uso: sem dados de demonstração
 *
 * Sem acesso ao terminal (HostGator sem SSH)? Use a tela /instalar (veja o README).
 */
if (PHP_SAPI !== 'cli') {
    exit('Use pelo terminal.');
}
require __DIR__ . '/../app/bootstrap.php';

$instalador = new App\Core\Instalador();
$instalador->executar(!in_array('--atualizacao', $argv, true));
echo implode(PHP_EOL, $instalador->log) . PHP_EOL;
