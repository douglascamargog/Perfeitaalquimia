<?php
/**
 * Barras por dia (HTML/CSS, sem biblioteca): uma ou duas séries empilhadas, linha tracejada de referência e o dia de hoje em destaque.
 * Variáveis:
 *   $dias       [['dia' => 'AAAA-MM-DD', 'valores' => [v1, v2?]], ...]
 *   $series     [['nome' => 'Atendidas', 'classe' => 's1'], ['nome' => 'Não atendidas', 'classe' => 's2']] (legenda só com 2+)
 *   $referencia valor da linha tracejada (ex.: necessário por dia) ou null; $refRotulo texto da linha
 *   $dinheiro   true = valores em R$ (tooltip)
 */
$referencia = $referencia ?? null;
$dinheiro = !empty($dinheiro);
$fmt = fn($v) => $dinheiro ? dinheiro((float) $v) : number_format((float) $v, 0, ',', '.');
$max = max(1, $referencia ?? 0, ...array_map(fn($d) => array_sum($d['valores']), $dias ?: [['valores' => [1]]]));
$hoje = date('Y-m-d');
?>
<?php if (count($series) > 1): ?>
  <div class="grafico-legenda"><?php foreach ($series as $s): ?><span><i class="<?= e($s['classe']) ?>"></i><?= e($s['nome']) ?></span><?php endforeach; ?>
    <?php if ($referencia): ?><span><i class="ref"></i><?= e($refRotulo ?? 'Referência') ?></span><?php endif; ?></div>
<?php elseif ($referencia): ?>
  <div class="grafico-legenda"><span><i class="<?= e($series[0]['classe'] ?? 's1') ?>"></i><?= e($series[0]['nome'] ?? '') ?></span><span><i class="ref"></i><?= e($refRotulo ?? 'Referência') ?></span></div>
<?php endif; ?>
<div class="grafico-barras" data-grafico-barras>
  <?php if ($referencia): ?><div class="grafico-ref" style="bottom: calc(<?= round($referencia / $max * 100, 2) ?>% * .86 + 22px)" title="<?= e(($refRotulo ?? 'Referência') . ': ' . $fmt($referencia)) ?>"></div><?php endif; ?>
  <?php foreach ($dias as $d): $total = array_sum($d['valores']);
    $dica = data_br($d['dia']) . ': ' . implode(' · ', array_map(fn($s, $v) => $s['nome'] . ' ' . $fmt($v), $series, $d['valores'])); ?>
    <div class="grafico-coluna <?= $d['dia'] === $hoje ? 'hoje' : '' ?>" title="<?= e($dica) ?>" data-coluna="<?= e($d['dia']) ?>">
      <div class="grafico-pilha" style="height: calc(<?= round($total / $max * 100, 2) ?>% * .86)">
        <?php foreach (array_reverse($d['valores'], true) as $i => $v): if ($v <= 0) continue; ?>
          <span class="<?= e($series[$i]['classe'] ?? 's1') ?>" style="flex: <?= (float) $v ?>"></span>
        <?php endforeach; ?>
      </div>
      <small><?= (int) substr($d['dia'], 8) ?></small>
    </div>
  <?php endforeach; ?>
  <?php if (!$dias): ?><p class="suave pequeno">Sem dados no período.</p><?php endif; ?>
</div>
