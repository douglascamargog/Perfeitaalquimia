<?php
/**
 * Selo de ritmo da meta (texto + ícone, nunca só cor). Variável: $m = resultado de MetaService::calcular (ou linha da grade)
 * Estados: meta batida · no ritmo · atrás do ritmo (precisa de R$ X por dia) · mês encerrado · sem meta
 */
$meta = (float) ($m['meta'] ?? 0);
if ($meta <= 0) {
    echo '<span class="ritmo neutro" data-ritmo="sem_meta">○ Sem meta cadastrada</span>';
    return;
}
if (!empty($m['atingida'])) {
    echo '<span class="ritmo batida" data-ritmo="batida">🎉 Meta batida</span>';
} elseif (!empty($m['passado'])) {
    echo '<span class="ritmo atras" data-ritmo="encerrado">■ Mês encerrado abaixo da meta</span>';
} elseif (!empty($m['futuro']) || ($m['projecao_mes'] ?? null) === null) {
    echo '<span class="ritmo neutro" data-ritmo="inicio">○ Começando o mês</span>';
} elseif ((float) $m['projecao_mes'] >= $meta) {
    echo '<span class="ritmo ok" data-ritmo="no_ritmo">▲ No ritmo para bater a meta</span>';
} else {
    $nec = $m['necessario_dia'] ?? null;
    echo '<span class="ritmo atras" data-ritmo="atras">▼ Atrás do ritmo' . ($nec ? ' · precisa de ' . e(dinheiro((float) $nec)) . ' por dia' : '') . '</span>';
}
