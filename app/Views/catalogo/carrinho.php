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

  <?php $b = $c['campanha']; $ganhou = \App\Services\CampanhaService::conquistas($b); $msg = \App\Services\CampanhaService::mensagem($b);
  if ($ganhou || $msg): $prox = $b['proximas'][0] ?? null; ?>
    <div class="cat-campanha" data-campanha-carrinho>
      <small><?= e(implode(' · ', $b['nomes']) ?: 'Campanha') ?></small>
      <?php if ($ganhou): ?><ul aria-label="Você ganhou"><?php foreach ($ganhou as $g): ?><li><?= e($g) ?></li><?php endforeach; ?></ul><?php endif; ?>
      <strong><?= e($msg ?: 'Você já ganhou tudo desta campanha! 🎉') ?></strong>
      <?php if ($prox): ?><div class="vt-progresso-barra"><i style="width: <?= (int) min(100, round($c['subtotal'] / max(0.01, $prox['a_partir_de']) * 100)) ?>%"></i></div><?php endif; ?>
    </div>
  <?php endif; ?>

  <ul class="cat-itens">
    <?php foreach ($c['itens'] as $i): ?>
      <li>
        <?php if ($i['imagem']): ?><img src="<?= url('/' . $i['imagem']) ?>" alt="" width="64" height="64"><?php endif; ?>
        <div><strong><?= !empty($i['codigo']) ? '<span class="cod-produto">' . e($i['codigo']) . '</span> ' : '' ?><?= e($i['nome']) ?></strong><small><?php if (isset($i['preco_cheio'])): ?><s><?= dinheiro($i['preco_cheio']) ?></s><?php endif; ?><?= dinheiro($i['preco']) ?> / un</small>
          <?php if (!empty($i['promo_pct'])): ?><span class="vt-mini-off">-<?= e(\App\Services\CampanhaService::pct((float) $i['promo_pct'])) ?> na campanha</span><?php endif; ?></div>
        <div class="cat-qtd" data-produto="<?= e((string) ($i['chave'] ?? $i['id'])) ?>" data-recarregar>
          <button type="button" data-menos aria-label="Diminuir">−</button>
          <input type="number" min="0" max="99999" value="<?= (int) $i['quantidade'] ?>" aria-label="Quantidade de <?= e($i['nome']) ?>">
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
    <div class="campo"><label for="obs">Observação (opcional)</label><textarea id="obs" name="observacoes" rows="2" maxlength="1000" placeholder="Ex.: entregar pela manhã"><?= antigo('observacoes') ?></textarea></div>
    <div class="cat-totais">
      <?php if ($c['economia_promo'] > 0): ?><div><span>Economia nos produtos em promoção</span><span class="verde">− <?= dinheiro($c['economia_promo']) ?></span></div><?php endif; ?>
      <div><span>Subtotal</span><span><?= dinheiro($c['subtotal']) ?></span></div>
      <?php if ($c['desconto_campanha'] > 0): ?><div><span>Desconto da campanha (<?= e(\App\Services\CampanhaService::pct($c['campanha']['desconto_pct'])) ?>)</span><span class="verde">− <?= dinheiro($c['desconto_campanha']) ?></span></div><?php endif; ?>
      <?php foreach ($c['campanha']['brindes'] as $br): ?><div><span>Brinde</span><span class="verde">🎁 <?= e($br['texto']) ?></span></div><?php endforeach; ?>
      <?php if ($c['campanha']['frete']): ?><div><span>Frete</span><span class="verde">grátis</span></div><?php endif; ?>
    </div>
    <div class="cat-total"><span>Total</span><strong><?= dinheiro(round($c['subtotal'] - $c['desconto_campanha'], 2)) ?></strong></div>
    <p class="pequeno suave">Descontos da forma de pagamento são aplicados no pedido. O Financeiro confere e aprova; você acompanha em "Meus pedidos".</p>
    <button class="botao largo" type="submit" <?= $c['falta'] > 0 ? 'disabled' : '' ?>><?= $c['falta'] > 0 ? 'Complete o pedido mínimo' : 'Enviar pedido' ?></button>
  </form>
<?php endif; ?>
