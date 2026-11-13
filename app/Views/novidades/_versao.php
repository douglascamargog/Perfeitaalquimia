<?php
/** Uma versão do One: título, data e listas de novidades, melhorias e correções. Variável: $versao */
$grupos = ['novo' => ['Novidades', 'novo'], 'melhoria' => ['Melhorias', 'melhoria'], 'correcao' => ['Correções', 'correcao']];
?>
<article class="nov-versao" data-versao="<?= e($versao['versao']) ?>">
  <header><strong>ONE_V<?= e($versao['versao']) ?></strong><span><?= e($versao['titulo']) ?></span><small><?= e(data_br($versao['data'])) ?></small></header>
  <?php foreach ($grupos as $chave => [$rotulo, $classe]): if (empty($versao[$chave])) continue; ?>
    <h3 class="nov-tipo <?= $classe ?>"><?= $rotulo ?></h3>
    <ul><?php foreach ($versao[$chave] as $item): ?><li><?= e($item) ?></li><?php endforeach; ?></ul>
  <?php endforeach; ?>
</article>
