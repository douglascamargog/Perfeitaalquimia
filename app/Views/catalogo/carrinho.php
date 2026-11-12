<?php
/** Carrinho + finalizar. Variáveis: $c (PedidoService::calcular), $formas, $uuid */
use App\Services\PedidoService;

$pct = $c['minimo'] > 0 ? min(100, round($c['subtotal'] / $c['minimo'] * 100)) : 100;
?>
<h1>Seu pedido</h1>
<?php if (!$c['itens']): ?>
  <div class="cat-vazio"><p>O carrinho está vazio.</p><a class="botao" href="<?= url('/catalogo') ?>">Escolher produtos</a></div>
<?php else: ?>
  <div class="cat-minimo <?= $c['falta'] > 0 ? 'falta' : 'ok' ?>" data-minimo>
    <div class="cat-minimo-texto" data-falta-texto><?= $c['falta'] > 0 ? 'Faltam <strong>' . e(dinheiro($c['falta'])) . '</strong> para o pedido mínimo da sua região (' . e(dinheiro($c['minimo'])) . ')' : 'Pedido mínimo atingido ✓' ?></div>
    <div class="cat-minimo-barra"><i style="width: <?= $pct ?>%" data-barra></i></div>
  </div>

  <ul class="cat-itens">
    <?php foreach ($c['itens'] as $i): ?>
      <li>
        <?php if ($i['imagem']): ?><img src="<?= url('/' . $i['imagem']) ?>" alt="" width="64" height="64"><?php endif; ?>
        <div><strong><?= e($i['nome']) ?></strong><small><?= dinheiro($i['preco']) ?> / un</small></div>
        <div class="cat-qtd" data-produto="<?= (int) $i['id'] ?>" data-recarregar>
          <button type="button" data-menos aria-label="Diminuir">−</button>
          <input type="number" min="0" max="9999" value="<?= (int) $i['quantidade'] ?>" aria-label="Quantidade de <?= e($i['nome']) ?>">
          <button type="button" data-mais aria-label="Aumentar">+</button>
        </div>
        <b class="cat-item-total"><?= dinheiro($i['total']) ?></b>
      </li>
    <?php endforeach; ?>
  </ul>
  <?php if ($c['sem_preco']): ?><p class="aviso aviso">Sem preço na sua tabela (ficaram fora do pedido): <?= e(implode(', ', $c['sem_preco'])) ?>.</p><?php endif; ?>

  <form class="cat-finalizar card" method="post" action="<?= url('/catalogo/finalizar') ?>" data-finalizar>
    <?= csrf_campo() ?>
    <input type="hidden" name="uuid" value="<?= e($uuid) ?>">
    <h2>Pagamento</h2>
    <div class="cat-formas">
      <?php foreach ($formas as $f): ?>
        <fieldset class="cat-forma <?= $f['bloqueio'] ? 'bloqueada' : '' ?>">
          <legend><?= e($f['nome']) ?></legend>
          <?php if ($f['bloqueio']): ?><p class="pequeno"><?= e($f['bloqueio']) ?></p><?php else: ?>
            <?php foreach ($f['condicoes'] as $cd): ?>
              <label class="opcao <?= $cd['bloqueio'] ? 'desligada' : '' ?>">
                <input type="radio" name="pagamento" value="<?= (int) $f['id'] ?>:<?= (int) $cd['id'] ?>" <?= $cd['bloqueio'] ? 'disabled' : '' ?> required>
                <?= e($cd['nome']) ?><?= (float) $cd['desconto_pct'] > 0 ? ' · <b>' . number_format((float) $cd['desconto_pct'], 1, ',', '.') . '% de desconto</b>' : '' ?>
                <?= $cd['bloqueio'] ? '<small>(' . e($cd['bloqueio']) . ')</small>' : '' ?>
              </label>
            <?php endforeach; ?>
          <?php endif; ?>
        </fieldset>
      <?php endforeach; ?>
    </div>
    <div class="campo"><label for="obs">Observação (opcional)</label><textarea id="obs" name="observacoes" rows="2" maxlength="1000" placeholder="Ex.: entregar pela manhã"><?= e(antigo('observacoes')) ?></textarea></div>
    <div class="cat-total"><span>Subtotal</span><strong><?= dinheiro($c['subtotal']) ?></strong></div>
    <p class="pequeno suave">Descontos da forma de pagamento são aplicados no pedido. O Financeiro confere e aprova; você acompanha em "Meus pedidos".</p>
    <button class="botao largo" type="submit" <?= $c['falta'] > 0 ? 'disabled' : '' ?>><?= $c['falta'] > 0 ? 'Complete o pedido mínimo' : 'Enviar pedido' ?></button>
  </form>
<?php endif; ?>
