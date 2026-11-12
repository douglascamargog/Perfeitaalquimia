<?php /** Quadro de crédito do cliente. Variável: $cr (Pedido::credito) */ ?>
<section class="ped-credito <?= $cr['cobranca_casos'] ? 'alerta' : 'ok' ?>">
  <h3>Crédito do cliente</h3>
  <dl>
    <div><dt>Em cobrança</dt><dd><?= $cr['cobranca_casos'] ? dinheiro($cr['cobranca_valor']) . ' · ' . (int) $cr['dias_atraso'] . ' dias' : 'Nada em aberto ✓' ?></dd></div>
    <div><dt>Comprou em 12 meses</dt><dd><?= dinheiro((float) $cr['comprado_12m']) ?> · <?= (int) $cr['pedidos_12m'] ?> pedido(s)</dd></div>
    <div><dt>Última compra</dt><dd><?= $cr['ultima_compra'] ? data_br($cr['ultima_compra']) : 'Primeira compra' ?></dd></div>
    <div><dt>Aprovados a faturar</dt><dd><?= dinheiro((float) $cr['aprovados_aberto']) ?></dd></div>
  </dl>
</section>
