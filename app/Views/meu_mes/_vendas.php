<?php /** Minhas vendas. Variáveis: $mes, $eu, $ou */ ?>
<div class="grade-meu-mes">
  <section class="card registrar-venda"><h2>Registrar venda</h2><?= \App\Core\Visao::renderizar('meu_mes/_form_venda', ['mes' => $mes, 'voltar' => 'vendas']) ?></section>
  <section class="card">
    <h2>Resumo</h2>
    <dl class="ficha-dados">
      <div><dt>Vendas no mês</dt><dd><?= (int) $eu['qtd_vendas'] ?></dd></div>
      <div><dt>Total</dt><dd><?= dinheiro(array_sum(array_column($eu['vendas'], 'valor'))) ?></dd></div>
      <div><dt>Ticket médio</dt><dd><?= dinheiro($eu['ticket_medio']) ?></dd></div>
      <div><dt>Maior venda</dt><dd><?= $eu['maior_venda'] ? dinheiro($eu['maior_venda']) : '—' ?></dd></div>
    </dl>
  </section>
</div>
<section class="card sem-espaco">
  <div class="tabela-rolagem">
    <table class="tabela">
      <thead><tr><th>Data</th><th>Cliente ou pedido</th><th style="text-align:right">Valor</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($eu['vendas'] as $v): ?>
        <tr><td><?= data_br($v['data']) ?></td><td><?= e($v['descricao'] ?: '—') ?></td><td style="text-align:right"><strong><?= dinheiro($v['valor']) ?></strong></td>
          <td style="text-align:right"><form method="post" action="<?= url('/meu-mes/vendas/' . (int) $v['id'] . '/excluir') ?>" data-confirmar="Excluir esta venda?"><?= csrf_campo() ?><button class="botao contorno pequeno" type="submit">Excluir</button></form></td></tr>
      <?php endforeach; ?>
      <?php if (!$eu['vendas']): ?><tr><td colspan="4" class="vazio">Nenhuma venda registrada neste mês.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</section>
