<?php
/** Comparar duas versões da fórmula. Variáveis: $f (versão atual), $dif (FormulaService::comparar), $outroId */
$n = fn($v, $c = 1) => rtrim(rtrim(number_format((float) $v, $c, ',', '.'), '0'), ',');
?>
<a class="voltar-link" href="<?= url('/formulas/' . (int) $f['id']) ?>">← <?= e($f['codigo']) ?> v<?= (int) $f['versao'] ?></a>
<div class="cabecalho-secao"><div><div class="sobretitulo">FÓRMULA <?= e($f['codigo']) ?></div>
  <h1>v<?= (int) $dif['versoes'][0] ?> → v<?= (int) $dif['versoes'][1] ?></h1>
  <p>Custo por embalagem: <?= dinheiro((float) $dif['custo'][0]) ?> → <strong><?= dinheiro((float) $dif['custo'][1]) ?></strong> ·
    massa da porção: <?= $n($dif['massa'][0]) ?> mg → <strong><?= $n($dif['massa'][1]) ?> mg</strong></p></div></div>
<section class="card" data-comparacao>
  <?php if (!$dif['cabecalho'] && !$dif['entrou'] && !$dif['saiu'] && !$dif['mudou']): ?><p>As duas versões são iguais.</p><?php endif; ?>
  <?php if ($dif['cabecalho']): ?><h2>Mudou no cabeçalho</h2><ul><?php foreach ($dif['cabecalho'] as [$campo, $a, $b]): ?><li><?= e($campo) ?>: <?= e((string) $a) ?> → <strong><?= e((string) $b) ?></strong></li><?php endforeach; ?></ul><?php endif; ?>
  <?php if ($dif['entrou']): ?><h2>Entrou</h2><ul class="fm-entrou"><?php foreach ($dif['entrou'] as $x): ?><li>➕ <?= e($x) ?></li><?php endforeach; ?></ul><?php endif; ?>
  <?php if ($dif['saiu']): ?><h2>Saiu</h2><ul class="fm-saiu"><?php foreach ($dif['saiu'] as $x): ?><li>➖ <?= e($x) ?></li><?php endforeach; ?></ul><?php endif; ?>
  <?php if ($dif['mudou']): ?><h2>Quantidade mudou</h2><ul><?php foreach ($dif['mudou'] as [$nome, $a, $b]): ?><li><?= e($nome) ?>: <?= e($a) ?> → <strong><?= e($b) ?></strong></li><?php endforeach; ?></ul><?php endif; ?>
</section>
