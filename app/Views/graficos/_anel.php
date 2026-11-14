<?php
/**
 * Anel de progresso da meta (SVG feito no servidor: aparece sempre, também no celular e na impressão).
 * Variáveis: $pct (0..∞), $rotulo (texto embaixo do número), $estado ('ok' | 'atras' | 'batida' | 'neutro'), $tamanho (px, padrão 140)
 */
$tamanho = (int) ($tamanho ?? 140);
$r = 52;
$volta = 2 * M_PI * $r;
$cheio = max(0, min(100, (float) $pct));
$estado = $estado ?? 'neutro';
?>
<div class="anel anel-<?= e($estado) ?>" style="width:<?= $tamanho ?>px;height:<?= $tamanho ?>px" role="img" aria-label="<?= e(number_format((float) $pct, 0, ',', '.')) ?>% <?= e($rotulo ?? '') ?>" data-anel="<?= (int) round((float) $pct) ?>">
  <svg viewBox="0 0 120 120" aria-hidden="true">
    <circle class="anel-trilho" cx="60" cy="60" r="<?= $r ?>"/>
    <circle class="anel-valor" cx="60" cy="60" r="<?= $r ?>" stroke-dasharray="<?= round($volta, 2) ?>" stroke-dashoffset="<?= round($volta * (1 - $cheio / 100), 2) ?>" transform="rotate(-90 60 60)"/>
  </svg>
  <div class="anel-texto"><strong><?= number_format((float) $pct, 0, ',', '.') ?>%</strong><?php if (!empty($rotulo)): ?><small><?= e($rotulo) ?></small><?php endif; ?></div>
</div>
