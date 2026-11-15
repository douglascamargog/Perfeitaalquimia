<?php /** Minhas vendas. Variáveis: $mes, $eu, $ou. Modo Omie: pedidos faturados (nada para digitar); modo manual: a pessoa registra. */
$omie = \App\Services\MetaService::vendasDoOmie(); ?>
<div class="grade-meu-mes">
  <?php if ($omie): ?>
    <section class="card mm-origem" data-vendas-omie><h2>De onde vêm as vendas</h2>
      <p>Cada pedido seu <strong>faturado no Omie</strong> entra aqui sozinho, no dia do faturamento. Não precisa registrar nada.</p></section>
  <?php else: ?>
    <section class="card registrar-venda"><h2>Registrar venda</h2><?= \App\Core\Visao::renderizar('meu_mes/_form_venda', ['mes' => $mes, 'voltar' => 'vendas']) ?></section>
  <?php endif; ?>
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
      <thead><tr><th>Data</th><th><?= $omie ? 'Cliente e pedido' : 'Cliente ou pedido' ?></th><th style="text-align:right">Valor</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($eu['vendas'] as $v): ?>
        <tr><td><?= data_br($v['data']) ?></td><td><?= e($v['descricao'] ?: '—') ?></td><td style="text-align:right"><strong><?= dinheiro($v['valor']) ?></strong></td>
          <td style="text-align:right"><?php if ($omie): ?><a class="botao contorno pequeno" href="<?= url('/pedidos/' . (int) $v['pedido_id']) ?>">Ver pedido<?= !empty($v['numero_nf']) ? ' · NF ' . e($v['numero_nf']) : '' ?></a>
            <?php else: ?><form method="post" action="<?= url('/meu-mes/vendas/' . (int) $v['id'] . '/excluir') ?>" data-confirmar="Excluir esta venda?"><?= csrf_campo() ?><button class="botao contorno pequeno" type="submit">Excluir</button></form><?php endif; ?></td></tr>
      <?php endforeach; ?>
      <?php if (!$eu['vendas']): ?><tr><td colspan="4" class="vazio"><?= $omie ? 'Nenhum pedido seu faturado neste mês ainda.' : 'Nenhuma venda registrada neste mês.' ?></td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</section>
