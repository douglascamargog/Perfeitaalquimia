<?php /** Diferença em relação ao período anterior: ▲ 12 / ▼ 3. Variáveis: $a (agora), $b (antes), $inverso (true = subir é ruim) */
$a = (float) ($a ?? 0); $b = (float) ($b ?? 0);
if ($a == $b) { echo '<small class="ac-delta igual" title="Igual ao período anterior">=</small>'; return; }
$bom = ($a > $b) xor !empty($inverso);
$texto = $b > 0 ? round(abs($a - $b) / $b * 100) . '%' : 'novo';
?><small class="ac-delta <?= $bom ? 'bom' : 'ruim' ?>" title="Período anterior: <?= e(number_format($b, fmod($b, 1) ? 2 : 0, ',', '.')) ?>"><?= $a > $b ? '▲' : '▼' ?> <?= e($texto) ?></small>
