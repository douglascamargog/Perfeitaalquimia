<?php
/**
 * V15.3 — DADOS ESSENCIAIS (roda uma vez): "última compra" de cada loja = o pedido FATURADO mais recente dela.
 * Corrige lojas em que pedidos faturados entraram sem atualizar a data (ex.: movimento de Dados de teste),
 * o que fazia o filtro "Sem compra há +30 dias" mostrar quem tinha comprado há poucos dias.
 * Só ADIANTA a data (nunca volta para trás) e recalcula o intervalo médio entre as compras.
 */
use App\Core\Banco;

$ids = array_column(Banco::todos("SELECT c.id FROM clientes c
    JOIN (SELECT cliente_id, MAX(DATE(faturado_em)) AS d FROM pedidos WHERE situacao = 'faturado' AND faturado_em IS NOT NULL GROUP BY cliente_id) x ON x.cliente_id = c.id
    WHERE c.ultima_compra_em IS NULL OR c.ultima_compra_em < x.d"), 'id');
foreach ($ids as $id) {
    App\Services\PedidoService::atualizarCompras((int) $id);
}
return $ids ? count($ids) . ' loja(s) com a última compra corrigida pelos pedidos faturados' : null;
