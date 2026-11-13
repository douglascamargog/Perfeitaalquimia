<?php
/** Fila de aprovação do Financeiro. Variáveis: $pedidos, $credito, $itens */
?>
<div class="cabecalho-secao">
  <div><div class="sobretitulo">FINANCEIRO · APROVAÇÃO</div><h1>Pedidos para aprovar</h1>
    <p>Mais antigos primeiro. Confira o crédito; ao aprovar, o pedido é liberado no ERP. A recusa exige motivo.</p></div>
</div>
<?php if (!$pedidos): ?><div class="card ped-vazio"><img src="<?= asset('img/one/one-feliz-padrao.svg') ?>" alt="" width="90" height="99"><p>Nenhum pedido aguardando aprovação. Tudo em dia!</p></div><?php endif; ?>
<div class="ped-fila">
  <?php foreach ($pedidos as $p): $cr = $credito[$p['id']]; ?>
    <article class="card ped-fila-item">
      <header>
        <div><a href="<?= url('/pedidos/' . (int) $p['id']) ?>"><strong>Pedido #<?= (int) $p['id'] ?></strong></a> · <?= data_br($p['criado_em'], true) ?>
          <h2><?= e($p['nome_fantasia'] ?: $p['razao_social']) ?></h2>
          <small><?= e($p['cidade']) ?>/<?= e($p['uf']) ?> · vendedor: <?= e($p['vendedor'] ?? '—') ?> · <?= e(['catalogo' => 'pelo catálogo', 'sistema' => 'pelo sistema', 'app' => 'pelo app'][$p['origem']] ?? '') ?></small></div>
        <div class="ped-fila-valor"><strong><?= dinheiro((float) $p['total']) ?></strong><small><?= e($p['forma']) ?><?= $p['condicao'] ? ' · ' . e($p['condicao']) : '' ?></small></div>
      </header>
      <div class="ped-fila-corpo">
        <ul class="ped-fila-itens">
          <?php foreach (array_slice($itens[$p['id']], 0, 6) as $i): ?><li><?= (int) $i['quantidade'] ?>× <?= !empty($i['codigo']) ? '<span class="cod-produto">' . e($i['codigo']) . '</span> ' : '' ?><?= e($i['nome']) ?></li><?php endforeach; ?>
          <?php if (count($itens[$p['id']]) > 6): ?><li class="suave">+ <?= count($itens[$p['id']]) - 6 ?> produto(s)</li><?php endif; ?>
        </ul>
        <?= \App\Core\Visao::renderizar('aprovacao/_credito', ['cr' => $cr]) ?>
      </div>
      <?php if ($p['observacoes']): ?><p class="pequeno"><strong>Observação:</strong> <?= e($p['observacoes']) ?></p><?php endif; ?>
      <?= \App\Core\Visao::renderizar('aprovacao/_acoes', ['p' => $p, 'voltar' => 'fila']) ?>
    </article>
  <?php endforeach; ?>
</div>
