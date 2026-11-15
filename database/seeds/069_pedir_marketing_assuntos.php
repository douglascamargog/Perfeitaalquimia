<?php
/**
 * "Pedir ao Marketing" (V11.6) — ajuste ESSENCIAL (uma vez só): saem da lista de assuntos "Rótulo ou embalagem",
 * "Campanha de desconto ou oferta relâmpago" e "Envio FULL". Só desativa (as tarefas antigas continuam guardadas);
 * dá para religar em Configurações › Listas › Tipos de tarefa do Marketing.
 */
use App\Core\Banco;

if (!Banco::valor("SELECT 1 FROM migracoes WHERE arquivo = 'mkt:assuntos-v11.6'")) {
    Banco::executar("UPDATE mkt_tipos SET ativo = 0 WHERE nome IN ('Rótulo ou embalagem', 'Campanha de desconto ou oferta relâmpago', 'Envio FULL (Shopee / Mercado Livre)')");
    Banco::inserir('migracoes', ['arquivo' => 'mkt:assuntos-v11.6']);
}
return null;
