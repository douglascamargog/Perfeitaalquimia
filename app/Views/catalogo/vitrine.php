<?php
/** Vitrine. Variáveis: $busca, $categoria, $categorias, $produtos, $carrinho, $logado, $minimo, $tabelaNome, $semPrecos */
use App\Core\Config;

$icones = asset('img/icones.svg');
?>
<section class="cat-hero">
  <div>
    <h1><?= $logado ? 'Faça o seu pedido' : 'Nossos produtos' ?></h1>
    <p><?= $logado ? 'Preços da sua tabela. Pedido mínimo da sua região: <strong>' . e(dinheiro((float) $minimo)) . '</strong>.' : e(Config::get('catalogo.chamada', '')) ?></p>
  </div>
  <?php if (!$logado): ?><a class="botao lima" href="<?= url('/catalogo/entrar') ?>">Entrar com o CNPJ</a><?php endif; ?>
</section>

<form class="cat-busca" method="get" action="<?= url('/catalogo') ?>">
  <input type="search" name="q" value="<?= e($busca) ?>" placeholder="Buscar produto ou código…" aria-label="Buscar produto">
  <?php if ($categoria): ?><input type="hidden" name="categoria" value="<?= (int) $categoria ?>"><?php endif; ?>
  <button class="botao" type="submit">Buscar</button>
</form>
<nav class="cat-categorias" aria-label="Categorias">
  <a href="<?= url('/catalogo') ?><?= $busca !== '' ? '?q=' . rawurlencode($busca) : '' ?>" class="<?= !$categoria ? 'ativa' : '' ?>">Todos</a>
  <?php foreach ($categorias as $c): ?>
    <a href="<?= url('/catalogo') ?>?categoria=<?= (int) $c['id'] ?><?= $busca !== '' ? '&q=' . rawurlencode($busca) : '' ?>" class="<?= (int) $categoria === (int) $c['id'] ? 'ativa' : '' ?>"><?= e($c['nome']) ?> <small><?= (int) $c['total'] ?></small></a>
  <?php endforeach; ?>
</nav>

<?php if (!empty($semPrecos)): ?>
  <div class="cat-vazio cat-aviso-preco" role="status"><strong>Ainda não há produtos com preço na sua tabela (<?= e($tabelaNome) ?>).</strong>
    Os preços chegam do nosso sistema de faturamento. Fale com seu vendedor: ele faz o pedido para você enquanto isso.</div>
<?php elseif (!$produtos): ?><p class="cat-vazio">Nenhum produto encontrado.</p><?php endif; ?>
<div class="cat-grade">
  <?php foreach ($produtos as $p): $qtd = $carrinho[$p['id']] ?? 0; ?>
    <article class="cat-card">
      <a href="<?= url('/catalogo/produto/' . rawurlencode($p['slug'])) ?>" class="cat-card-foto">
        <?php if ($p['imagem']): ?><img src="<?= url('/' . $p['imagem']) ?>" alt="<?= e($p['nome']) ?>" loading="lazy"><?php endif; ?>
      </a>
      <div class="cat-card-corpo">
        <small><?= !empty($p['codigo']) ? '<span class="cod-produto">' . e($p['codigo']) . '</span> ' : '' ?><?= e($p['categoria']) ?></small>
        <a href="<?= url('/catalogo/produto/' . rawurlencode($p['slug'])) ?>"><strong><?= e($p['nome']) ?></strong></a>
        <span class="cat-embalagem"><?= e($p['embalagem']) ?></span>
        <?php if ($logado): ?>
          <?php if ($p['preco'] !== null): ?>
            <div class="cat-preco"><?= dinheiro((float) $p['preco']) ?> <small>/ un</small></div>
            <div class="cat-qtd" data-produto="<?= (int) $p['id'] ?>">
              <button type="button" data-menos aria-label="Diminuir">−</button>
              <input type="number" min="0" max="9999" value="<?= (int) $qtd ?>" aria-label="Quantidade de <?= e($p['nome']) ?>">
              <button type="button" data-mais aria-label="Aumentar">+</button>
            </div>
          <?php else: ?>
            <div class="cat-sem-preco">Sem preço na sua tabela</div>
          <?php endif; ?>
        <?php endif; ?>
      </div>
    </article>
  <?php endforeach; ?>
</div>

<?php if ($logado): ?>
  <a class="cat-barra-carrinho" href="<?= url('/catalogo/carrinho') ?>" data-barra-carrinho <?= array_sum($carrinho) ? '' : 'hidden' ?>>
    <svg class="icone"><use href="<?= $icones ?>#carrinho"/></svg>
    <span><b data-qtd-barra><?= array_sum($carrinho) ?></b> item(ns) · <span data-subtotal></span></span>
    <span class="cat-barra-falta" data-falta></span>
    <strong>Ver carrinho →</strong>
  </a>
<?php endif; ?>
