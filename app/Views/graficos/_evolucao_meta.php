<?php
/**
 * Evolução da meta da equipe (SVG desenhado aqui, sem biblioteca e sem internet).
 * Variáveis: $mes 'AAAA-MM', $meta (R$), $trabalho (datas dos dias de trabalho), $lancamentos (linhas com data e vendido)
 *  - eixo com TODOS os dias do mês (lançamento num sábado/feriado também aparece);
 *  - vendido é acumulado: vale o último valor de cada dia, o último lançamento do mês é o teto
 *    e a linha nunca cai de um dia para o outro (um valor digitado errado não faz a linha "despencar");
 *  - ritmo necessário sobe só nos dias de trabalho; linha da meta no topo.
 */
$dias = [];
for ($d = strtotime("$mes-01"); date('Y-m', $d) === $mes; $d = strtotime('+1 day', $d)) {
    $dias[] = date('Y-m-d', $d);
}
$porDia = [];
foreach ($lancamentos as $l) {
    $porDia[$l['data']] = (float) $l['vendido'];
}
ksort($porDia);
// O último lançamento é o valor certo (nada passa dele) e o acumulado nunca cai de um dia para o outro:
// um número digitado errado no meio do mês não faz a linha despencar
$final = $porDia ? end($porDia) : 0.0;
$anterior = 0.0;
foreach ($porDia as $data => $v) {
    $porDia[$data] = $anterior = max($anterior, min($v, $final));
}
$ultima = $porDia ? array_key_last($porDia) : null;
$trabalho = array_flip($trabalho);
$total = max(1, count(array_filter($dias, fn($d) => isset($trabalho[$d]))));
$real = [];
$ritmo = [];
$atual = 0.0;
$contados = 0;
foreach ($dias as $d) {
    if (isset($porDia[$d])) {
        $atual = $porDia[$d];
    }
    $real[$d] = $ultima !== null && $d <= $ultima ? $atual : null;
    if (isset($trabalho[$d])) {
        $contados++;
    }
    $ritmo[$d] = $meta * $contados / $total;
}
// escala "redonda"
$maior = max($meta, ...array_values(array_filter($real, fn($v) => $v !== null) ?: [0])) * 1.08;
$passo = 10 ** floor(log10(max(1, $maior / 4)));
foreach ([1, 2, 2.5, 5, 10] as $m) {
    if ($maior / ($passo * $m) <= 5) { $passo *= $m; break; }
}
$topo = ceil($maior / $passo) * $passo ?: 1;
[$L, $R, $T, $B, $W, $H] = [56, 14, 14, 30, 1000, 290];
$x = fn(int $i) => $L + ($W - $L - $R) * ($i / max(1, count($dias) - 1));
$y = fn(float $v) => $T + ($H - $T - $B) * (1 - $v / $topo);
$curto = fn(float $v) => $v >= 1000 ? number_format($v / 1000, $v % 1000 ? 1 : 0, ',', '.') . ' mil' : number_format($v, 0, ',', '.');
$linha = function (array $valores) use ($dias, $x, $y) {
    $pts = [];
    foreach ($dias as $i => $d) {
        if ($valores[$d] !== null) {
            $pts[] = round($x($i), 1) . ',' . round($y($valores[$d]), 1);
        }
    }
    return implode(' ', $pts);
};
$hoje = date('Y-m-d');
?>
<figure class="ev-grafico" data-grafico-evolucao>
  <svg viewBox="0 0 <?= $W ?> <?= $H ?>" role="img" aria-label="Evolução do vendido pela equipe no mês, comparado ao ritmo necessário para a meta">
    <?php for ($v = 0; $v <= $topo + 0.01; $v += $passo): ?>
      <line class="ev-grade" x1="<?= $L ?>" x2="<?= $W - $R ?>" y1="<?= round($y($v), 1) ?>" y2="<?= round($y($v), 1) ?>"/>
      <text class="ev-eixo" x="<?= $L - 8 ?>" y="<?= round($y($v), 1) + 4 ?>" text-anchor="end"><?= e($curto($v)) ?></text>
    <?php endfor; ?>
    <?php foreach ($dias as $i => $d): // fim de semana / feriado: faixa clarinha; rótulo a cada 2 dias
      if (!isset($trabalho[$d])): ?><rect class="ev-folga" x="<?= round($x($i) - ($W - $L - $R) / count($dias) / 2, 1) ?>" y="<?= $T ?>" width="<?= round(($W - $L - $R) / count($dias), 1) ?>" height="<?= $H - $T - $B ?>"/><?php endif; ?>
      <?php if ($i % 2 === 0 || $d === $hoje): ?><text class="ev-eixo <?= $d === $hoje ? 'ev-hoje' : '' ?>" x="<?= round($x($i), 1) ?>" y="<?= $H - 8 ?>" text-anchor="middle"><?= substr($d, 8, 2) . '/' . substr($d, 5, 2) ?></text><?php endif; ?>
    <?php endforeach; ?>
    <line class="ev-meta" x1="<?= $L ?>" x2="<?= $W - $R ?>" y1="<?= round($y($meta), 1) ?>" y2="<?= round($y($meta), 1) ?>"><title>Meta: <?= e(dinheiro($meta)) ?></title></line>
    <polyline class="ev-ritmo" points="<?= $linha($ritmo) ?>"/>
    <?php if ($ultima !== null): ?>
      <polyline class="ev-real" points="<?= $linha($real) ?>" data-linha-vendido/>
      <?php foreach ($dias as $i => $d): if (!isset($porDia[$d])) continue; ?>
        <circle class="ev-ponto" cx="<?= round($x($i), 1) ?>" cy="<?= round($y($real[$d]), 1) ?>" r="5" data-ponto="<?= e($d) ?>" data-valor="<?= (float) $real[$d] ?>"><title><?= data_br($d) ?>: <?= e(dinheiro($real[$d])) ?> vendido · ritmo necessário <?= e(dinheiro($ritmo[$d])) ?></title></circle>
      <?php endforeach; ?>
    <?php endif; ?>
  </svg>
  <figcaption class="legenda-evolucao"><span><i class="real"></i>Vendido acumulado (lançamentos do gestor)</span><span><i class="ideal"></i>Ritmo necessário para a meta</span><span><i class="meta"></i>Meta</span><span><i class="folga"></i>Sem expediente</span></figcaption>
</figure>
