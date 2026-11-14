<?php
/**
 * Barra da meta com o marcador "onde deveria estar hoje" (ritmo). Variável: $m = MetaService::calcular (ou linha da grade)
 */
$meta = (float) ($m['meta'] ?? 0);
$pct = $meta > 0 ? (float) $m['vendido'] / $meta * 100 : 0;
$ideal = !empty($m['dias_trabalho']) && empty($m['passado']) && empty($m['futuro']) ? min(100, (int) $m['decorridos'] / (int) $m['dias_trabalho'] * 100) : null;
?>
<div class="barra-ritmo <?= !empty($m['atingida']) ? 'batida' : ($ideal !== null && $pct < $ideal ? 'atras' : 'ok') ?>" title="<?= e(number_format($pct, 0, ',', '.')) ?>% da meta<?= $ideal !== null ? ' · deveria estar em ' . number_format($ideal, 0, ',', '.') . '% hoje' : '' ?>">
  <span style="width: <?= min(100, $pct) ?>%"></span>
  <?php if ($ideal !== null && $meta > 0): ?><i style="left: <?= $ideal ?>%" aria-hidden="true"></i><?php endif; ?>
</div>
