<?php
/** Pedidos do e-commerce (V11.4) — DEMONSTRAÇÃO (só instalação nova): busca na Tray simulada os pedidos de hoje. */
if (\App\Core\Config::get('ecommerce.provedor', 'fake') !== 'fake') {
    return 'Loja real configurada: os pedidos vêm pela tarefa agendada.';
}
\App\Core\Config::recarregar(); // os horários dos canais acabaram de ser gravados (seed 065)
$r = \App\Services\EcommercePedidoService::importar();
return "Pedidos do e-commerce de exemplo: {$r['novos']} importado(s), {$r['na_fila']} na fila do Depósito.";
