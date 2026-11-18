<?php
/** Fila de aprovação do Financeiro. Variáveis: $pedidos, $credito, $itens */
?>
<div class="cabecalho-secao">
  <div><div class="sobretitulo">FINANCEIRO · APROVAÇÃO</div><h1>Pedidos para aprovar</h1>
    <p>Mais antigos primeiro. Confira o crédito; ao aprovar, o pedido é liberado no ERP. A recusa exige motivo.</p></div>
</div>
<?php if (!$pedidos): ?><div class="card ped-vazio"><img src="<?= asset('img/one/one-feliz-padrao.webp') ?>" alt="" width="90" height="99"><p>Nenhum pedido aguardando aprovação. Tudo em dia!</p></div><?php endif; ?>
<div class="ped-fila">
  <?php foreach ($pedidos as $p): $cr = $credito[$p['id']]; ?>
    <article class="card ped-fila-item">
      <header>
        <div><a href="<?= url('/pedidos/' . (int) $p['id']) ?>"><strong>Pedido #<?= (int) $p['id'] ?></strong></a><?= \App\Services\NotificacaoService::marcaPedido((int) $p['id']) ?> · <?= data_br($p['criado_em'], true) ?>
          <h2><a href="<?= url('/carteira/clientes/' . (int) $p['cliente_id']) ?>" data-link-loja><?= e($p['nome_fantasia'] ?: $p['razao_social']) ?></a></h2>
          <?php if (!empty($p['campanha_brindes']) || !empty($p['frete_gratis'])): ?><span class="selo azul" data-campanha-brinde><?= !empty($p['campanha_brindes']) ? '🎁 ' . e($p['campanha_brindes']) : '' ?><?= !empty($p['frete_gratis']) ? (!empty($p['campanha_brindes']) ? ' · ' : '') . 'frete grátis' : '' ?></span><?php endif; ?>
          <?php if (!empty($p['desconto_acima_limite'])): ?><span class="selo selo-desconto" data-desconto-acima>desconto acima do limite · <?= dinheiro((float) $p['desconto_vendedor']) ?></span><?php endif; ?>
          <small><?= e($p['cidade']) ?>/<?= e($p['uf']) ?> · vendedor: <?= pessoa($p['vendedor'] ?? null, $p['vendedor_foto'] ?? null, false, 18) ?> · <?= e(['catalogo' => 'pelo catálogo', 'sistema' => 'pelo sistema', 'app' => 'pelo app'][$p['origem']] ?? '') ?></small></div>
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
<?php if (($paginas ?? 1) > 1): ?>
  <div class="paginacao-barra"><span><?= (int) $total ?> pedido(s) esperando · página <?= (int) $pagina ?> de <?= (int) $paginas ?></span>
    <nav class="paginacao"><?php for ($n = 1; $n <= $paginas; $n++): ?><a class="botao <?= $n === $pagina ? '' : 'contorno' ?> pequeno" href="<?= url('/aprovacao') ?>?pagina=<?= $n ?>"><?= $n ?></a><?php endfor; ?></nav></div>
<?php endif; ?>
</div>
