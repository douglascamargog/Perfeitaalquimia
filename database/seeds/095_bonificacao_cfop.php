<?php
/**
 * V16.4 — DADOS ESSENCIAIS (idempotente): regras da bonificação e os CFOPs que o One sugere em cada item do pedido.
 * Tudo editável em Configurações (grupos "Pedidos" e "Fiscal"). Os CFOPs devem ser conferidos com a contadora.
 */
use App\Core\Banco;

$configs = [
    ['pedidos.bonificacao_max_pct', 'Bonificação máxima sem aprovação (%)', '15', 'numero', 'Pedidos', 'Até este % do valor vendido, o vendedor bonifica sozinho; acima, o pedido vai marcado para aprovação'],
    ['pedidos.bonificacao_motivos', 'Motivos de bonificação', "Campanha,Troca,Amostra,Acordo comercial,Primeiro pedido", 'lista', 'Pedidos', 'O vendedor escolhe um motivo ao bonificar'],
    ['fiscal.uf_empresa', 'UF da empresa (saída da mercadoria)', 'RS', 'texto', 'Fiscal', 'Entrega nesta UF = CFOP de dentro do estado (5.xxx); outra UF = fora do estado (6.xxx)'],
    ['fiscal.cfop_venda_dentro', 'CFOP de venda dentro do estado', '5102', 'texto', 'Fiscal', 'Sugerido em cada item vendido quando a entrega é na UF da empresa'],
    ['fiscal.cfop_venda_fora', 'CFOP de venda para fora do estado', '6102', 'texto', 'Fiscal', 'Sugerido em cada item vendido quando a entrega é em outra UF'],
    ['fiscal.cfop_bonificacao_dentro', 'CFOP de bonificação dentro do estado', '5910', 'texto', 'Fiscal', 'Item bonificado (mesma nota da venda)'],
    ['fiscal.cfop_bonificacao_fora', 'CFOP de bonificação para fora do estado', '6910', 'texto', 'Fiscal', 'Item bonificado para outra UF'],
];
foreach ($configs as [$chave, $rotulo, $valor, $tipo, $grupo, $ajuda]) {
    Banco::executar('INSERT IGNORE INTO configuracoes (chave, rotulo, valor, tipo, grupo, descricao) VALUES (?, ?, ?, ?, ?, ?)', [$chave, $rotulo, $valor, $tipo, $grupo, $ajuda]);
}
return null;
