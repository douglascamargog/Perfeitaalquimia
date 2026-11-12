<?php
/**
 * Falas do One (usado no painel flutuante e no card do Início).
 * Variáveis: $saudacao, $falas
 */
$img = fn(string $humor, string $variacao = 'padrao') => asset("img/one/one-$humor-$variacao.svg");
?>
<div class="one-falas">
  <div class="one-fala one-saudacao">
    <img src="<?= $img($saudacao['humor']) ?>" alt="" width="44" height="48">
    <div><p><?= e($saudacao['texto']) ?></p></div>
  </div>
  <?php foreach ($falas as $f): ?>
    <div class="one-fala">
      <img src="<?= $img($f['humor']) ?>" alt="" width="36" height="40">
      <div>
        <p><?= e($f['texto']) ?></p>
        <?php if (!empty($f['link'])): ?><a href="<?= url($f['link']) ?>"><?= e($f['acao'] ?? 'Abrir') ?> →</a><?php endif; ?>
      </div>
    </div>
  <?php endforeach; ?>
</div>
