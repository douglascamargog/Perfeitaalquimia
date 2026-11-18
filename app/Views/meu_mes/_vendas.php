<?php /** Minhas vendas. Variáveis: $mes, $eu, $ou, $tirados (pedidos em visitas de outras carteiras). Modo Omie: pedidos faturados (nada para digitar); modo manual: a pessoa registra. */
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

<?php if (!empty($tirados['pedidos'])): ?>
  <section class="card" data-vendas-visitas style="margin-top:16px">
    <h2>Vendas em visitas de outras carteiras</h2>
    <p class="suave pequeno">Pedidos que você fechou em visitas do <a href="<?= url('/roteiros') ?>">Planejador de visitas</a> em lojas de outras carteiras.
      A venda é da dona da carteira e conta para a meta dela; aqui é só para você acompanhar o que conseguiu vender.</p>
    <dl class="ficha-dados">
      <div><dt>Pedidos</dt><dd><?= count($tirados['pedidos']) ?></dd></div>
      <div><dt>Total vendido</dt><dd data-total-visitas><?= dinheiro($tirados['total']) ?></dd></div>
      <div><dt>Já faturado</dt><dd><?= dinheiro($tirados['faturado']) ?></dd></div>
    </dl>
    <div class="tabela-rolagem">
      <table class="tabela">
        <thead><tr><th>Data</th><th>Loja</th><th>Carteira de</th><th style="text-align:right">Valor</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($tirados['pedidos'] as $p): ?>
          <tr><td><?= data_br($p['criado_em']) ?></td><td><a href="<?= url('/carteira/clientes/' . (int) $p['cliente_id']) ?>" data-link-loja><?= e($p['cliente']) ?></a> <small class="suave"><?= e($p['cidade']) ?>/<?= e($p['uf']) ?></small></td><td><?= e($p['dona'] ?? '—') ?></td>
            <td style="text-align:right"><strong><?= dinheiro($p['total']) ?></strong><br><small class="suave"><?= e(\App\Services\PedidoService::SITUACOES[$p['situacao']][0] ?? $p['situacao']) ?></small></td>
            <td style="text-align:right"><a class="botao contorno pequeno" href="<?= url('/pedidos/' . (int) $p['id']) ?>">Ver pedido</a></td></tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>
<?php endif; ?>
