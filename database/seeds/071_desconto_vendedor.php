<?php
/** Desconto da vendedora (V11.8) — ESSENCIAL (idempotente): limite de desconto sem aprovação. */
use App\Core\Banco;

if (!Banco::valor("SELECT 1 FROM configuracoes WHERE chave = 'pedidos.desconto_max_pct'")) {
    Banco::inserir('configuracoes', ['chave' => 'pedidos.desconto_max_pct', 'valor' => '10', 'tipo' => 'numero', 'grupo' => 'Pedidos',
        'rotulo' => 'Desconto máximo da vendedora sem aprovação (%)',
        'descricao' => 'Somando o desconto dos produtos e o do pedido. Acima disso o pedido vai para a Aprovação marcado "desconto acima do limite" e os gestores são avisados.',
        'ordem' => 30]);
}
return null;
