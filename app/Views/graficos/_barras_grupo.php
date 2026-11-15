<?php
/**
 * Barras agrupadas por mês (HTML/CSS, sem biblioteca): uma barra por série em cada mês.
 * Variáveis: $rotulos ['AAAA-MM', ...], $series [['nome' => 'Externos', 'valores' => [..]], ...]
 */
$max = max(1, ...array_merge(...array_map(fn($s) => array_map('floatval', $s['valores']), $series ?: [['valores' => [1]]])));
$curto = fn(float $v) => $v >= 1000 ? 'R$ ' . number_format($v / 1000, $v >= 100000 ? 0 : 1, ',', '.') . ' mil' : dinheiro($v);
?>
<div class="grafico-legenda"><?php foreach ($series as $i => $s): ?><span><i class="g<?= $i % 3 ?>"></i><?= e($s['nome']) ?></span><?php endforeach; ?></div>
<div class="grafico-grupos" data-grafico-grupos>
  <?php foreach ($rotulos as $k => $m): ?>
    <div class="grafico-grupo">
      <div class="grafico-grupo-barras">
        <?php foreach ($series as $i => $s): $v = (float) ($s['valores'][$k] ?? 0); ?>
          <span class="g<?= $i % 3 ?>" style="height: <?= round($v / $max * 100, 2) ?>%" title="<?= e($s['nome'] . ' · ' . mes_curto((int) substr($m, 5, 2)) . '/' . substr($m, 0, 4) . ': ' . dinheiro($v)) ?>"><?php if ($v > 0 && $v / $max > .18): ?><b><?= e($curto($v)) ?></b><?php endif; ?></span>
        <?php endforeach; ?>
      </div>
      <small><?= mes_curto((int) substr($m, 5, 2)) ?></small>
    </div>
  <?php endforeach; ?>
</div>
