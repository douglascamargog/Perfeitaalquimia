<?php
/** Meus pedidos (cliente). Variáveis: $pedidos, $itens */
use App\Services\PedidoService;
?>
<h1>Meus pedidos</h1>
<?php if (!$pedidos): ?><div class="cat-vazio"><p>Você ainda não fez pedidos por aqui.</p><a class="botao" href="<?= url('/catalogo') ?>">Ver produtos</a></div><?php endif; ?>
<?php if ($pedidos): ?>
  <form method="post" action="<?= url('/catalogo/pedidos/' . (int) $pedidos[0]['id'] . '/repetir') ?>" class="cat-repetir">
    <?= csrf_campo() ?><button class="botao lima" type="submit">↻ Repetir último pedido</button>
  </form>
<?php endif; ?>
<div class="cat-pedidos">
  <?php foreach ($pedidos as $p): [$rotulo, $cor] = PedidoService::SITUACOES[$p['situacao']] ?? [$p['situacao'], 'cinza']; ?>
    <details class="cat-pedido">
      <summary>
        <div><strong>Pedido #<?= (int) $p['id'] ?></strong><small><?= data_br($p['criado_em'], true) ?> · <?= (int) $p['qtd_itens'] ?> item(ns) · <?= e($p['forma']) ?></small></div>
        <div class="cat-pedido-direita"><b><?= dinheiro((float) $p['total']) ?></b><span class="selo <?= $cor ?>"><?= $rotulo ?></span></div>
      </summary>
      <?php if ($p['situacao'] === 'recusado' && $p['motivo_recusa']): ?><p class="aviso erro">Motivo: <?= e($p['motivo_recusa']) ?></p><?php endif; ?>
      <?php if ($p['numero_nf']): ?><p class="pequeno">Nota fiscal nº <?= e($p['numero_nf']) ?> · faturado em <?= data_br($p['faturado_em']) ?></p><?php endif; ?>
      <ul class="cat-itens compacto">
        <?php foreach ($itens[$p['id']] as $i): ?><li><span><?= (int) $i['quantidade'] ?>×</span><span><?= e($i['nome']) ?></span><b><?= dinheiro((float) $i['total']) ?></b></li><?php endforeach; ?>
      </ul>
      <?php if ((float) $p['desconto'] > 0): ?><p class="pequeno">Desconto: −<?= dinheiro((float) $p['desconto']) ?></p><?php endif; ?>
      <div class="ficha-acoes">
        <form method="post" action="<?= url('/catalogo/pedidos/' . (int) $p['id'] . '/repetir') ?>"><?= csrf_campo() ?><button class="botao contorno pequeno" type="submit">Repetir este pedido</button></form>
        <?php if ($p['situacao'] === 'aguardando_aprovacao'): ?>
          <form method="post" action="<?= url('/catalogo/pedidos/' . (int) $p['id'] . '/cancelar') ?>" data-confirmar="Cancelar o pedido #<?= (int) $p['id'] ?>?"><?= csrf_campo() ?><button class="botao contorno pequeno" type="submit">Cancelar</button></form>
        <?php endif; ?>
      </div>
    </details>
  <?php endforeach; ?>
</div>
