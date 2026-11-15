<?php
/**
 * Reativação com a própria vendedora (V12.1) — ESSENCIAL (instalação e atualizações; idempotente).
 * Cliente sem compra há X dias fica com a vendedora e aparece na Reativação dela (não vai mais para a fila
 * da função Prospecção e Reativação). Quem quiser o jeito antigo troca para "fila" em Configurações › Carteira.
 */
use App\Core\Banco;

if (!Banco::valor("SELECT 1 FROM configuracoes WHERE chave = 'carteira.reativacao_modo'")) {
    Banco::inserir('configuracoes', ['chave' => 'carteira.reativacao_modo', 'valor' => 'vendedora', 'tipo' => 'texto', 'grupo' => 'Carteira',
        'rotulo' => 'Quem reativa o cliente sem compra',
        'descricao' => 'vendedora = o cliente fica com a vendedora e aparece na Reativação dela · fila = vai para a fila da função de reativação (jeito antigo).',
        'ordem' => 75]);
}
Banco::executar("UPDATE configuracoes SET rotulo = 'Dias sem compra para aparecer na Reativação', descricao = 'Cliente sem comprar há esse número de dias aparece na Reativação da vendedora dele.'
    WHERE chave = 'carteira.dias_inatividade' AND rotulo = 'Dias sem compra para ir à reativação'");
return null;
