<?php
/**
 * Pedidos do e-commerce na separação (V11.4) — DADOS ESSENCIAIS (idempotente):
 * horário limite de envio de cada canal (Configurações › Depósito). Vazio = sem horário.
 */
use App\Core\Banco;

$configs = [
    ['deposito.horario_mercado_livre', '13:00', 'Horário limite de envio: Mercado Livre', 50],
    ['deposito.horario_shopee', '11:00', 'Horário limite de envio: Shopee', 51],
    ['deposito.horario_amazon', '12:00', 'Horário limite de envio: Amazon', 52],
    ['deposito.horario_loja', '', 'Horário limite de envio: loja própria (site)', 53],
];
foreach ($configs as [$chave, $valor, $rotulo, $ordem]) {
    if (!Banco::valor('SELECT 1 FROM configuracoes WHERE chave = ?', [$chave])) {
        Banco::inserir('configuracoes', ['chave' => $chave, 'valor' => $valor, 'tipo' => 'texto', 'grupo' => 'Depósito', 'rotulo' => $rotulo,
            'descricao' => 'Hora no formato 13:00. O pedido que entra na fila depois desse horário passa para o próximo dia útil. Vazio = sem horário limite.', 'ordem' => $ordem]);
    }
}
return null;
