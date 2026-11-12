<?php
/** Ficha do produto (mesmo conteúdo da Academia). Variáveis: $p, $logado, $noCarrinho */
$f = $p['ficha'];
$lista = fn($v) => array_values(array_filter(is_array($v) ? array_map(fn($x) => is_array($x) ? implode(' — ', array_filter($x, 'is_string')) : (string) $x, $v) : [], 'strlen'));
?>
<a class="cat-voltar" href="<?= url('/catalogo') ?>">← Todos os produtos</a>
<article class="cat-produto">
  <div class="cat-produto-foto"><?php if ($p['imagem']): ?><img src="<?= url('/' . $p['imagem']) ?>" alt="<?= e($p['nome']) ?>"><?php endif; ?></div>
  <div class="cat-produto-info">
    <small><?= e($p['categoria']) ?></small>
    <h1><?= e($p['nome']) ?></h1>
    <p class="cat-embalagem"><?= e($p['embalagem']) ?></p>
    <?php if ($p['chamada']): ?><p class="cat-chamada"><?= e($p['chamada']) ?></p><?php endif; ?>
    <?php if ($logado && $p['preco'] !== null): ?>
      <div class="cat-preco grande"><?= dinheiro((float) $p['preco']) ?> <small>/ unidade</small></div>
      <div class="cat-qtd grande" data-produto="<?= (int) $p['id'] ?>">
        <button type="button" data-menos aria-label="Diminuir">−</button>
        <input type="number" min="0" max="9999" value="<?= (int) $noCarrinho ?>" aria-label="Quantidade">
        <button type="button" data-mais aria-label="Aumentar">+</button>
      </div>
      <a class="botao" href="<?= url('/catalogo/carrinho') ?>">Ver carrinho</a>
    <?php elseif ($logado): ?>
      <p class="cat-sem-preco">Este produto está sem preço na sua tabela. Fale com o seu vendedor.</p>
    <?php else: ?>
      <a class="botao lima" href="<?= url('/catalogo/entrar') ?>">Entrar com o CNPJ para ver o preço</a>
    <?php endif; ?>

    <?php foreach ((array) ($f['descriptionParagraphs'] ?? []) as $par): if (is_string($par) && $par !== ''): ?><p><?= e($par) ?></p><?php endif; endforeach; ?>
    <?php if ($b = $lista($f['benefits'] ?? [])): ?>
      <h2>Destaques</h2><ul><?php foreach ($b as $item): ?><li><?= e($item) ?></li><?php endforeach; ?></ul>
    <?php endif; ?>
    <dl class="cat-ficha">
      <?php foreach (['Porção' => $f['serving'] ?? '', 'Modo de uso' => $f['use'] ?? '', 'Ingredientes' => $f['ingredients'] ?? '', 'Cuidados' => $f['care'] ?? '', 'Conservação' => $f['storage'] ?? ''] as $rotulo => $valor):
          if (is_string($valor) && trim($valor) !== ''): ?>
        <div><dt><?= $rotulo ?></dt><dd><?= e($valor) ?></dd></div>
      <?php endif; endforeach; ?>
    </dl>
  </div>
</article>
