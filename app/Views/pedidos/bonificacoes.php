<?php
/** Pedidos › Bonificações. Variáveis: $periodo, $grupo, $resumo, $grupos, $pedidos, $limite */
use App\Services\BonificacaoService as B;

$n = fn($v) => number_format((float) $v, 0, ',', '.');
$pct = fn($a, $b) => $b > 0 ? number_format($a / $b * 100, 1, ',', '.') . '%' : '—';
$link = fn(array $m) => url('/pedidos/bonificacoes') . '?' . e(http_build_query($m + ['periodo' => $periodo, 'grupo' => $grupo]));
?>
<div class="cabecalho-secao">
  <div><div class="sobretitulo">PEDIDOS · BONIFICAÇÕES</div><h1>Bonificações</h1>
    <p>Itens que foram na nota com CFOP de bonificação e que a loja não pagou. Não entram no faturamento nem na meta.
      Limite sem aprovação: <?= number_format($limite, 0, ',', '.') ?>% do valor vendido no pedido.</p></div>
  <div class="acoes nao-imprimir"><button class="botao contorno" type="button" onclick="window.print()">Imprimir</button></div>
</div>
<nav class="an-chips nao-imprimir" aria-label="Período" style="margin-bottom:12px">
  <?php foreach (B::PERIODOS as $k => $r): ?><a href="<?= $link(['periodo' => $k]) ?>" class="<?= $periodo === $k ? 'ativo' : '' ?>"><?= e($r) ?></a><?php endforeach; ?>
</nav>
<div class="an-kpis" data-bonificacao-resumo>
  <div class="card an-kpi an-destaque"><span>Bonificado</span><strong data-bon="valor"><?= dinheiro((float) $resumo['bonificado']) ?></strong><small>preço de tabela dos itens</small></div>
  <div class="card an-kpi"><span>Pedidos com bonificação</span><strong data-bon="pedidos"><?= $n($resumo['pedidos']) ?></strong><small><?= $n($resumo['acima']) ?> acima do limite</small></div>
  <div class="card an-kpi"><span>Vendido nesses pedidos</span><strong><?= dinheiro((float) $resumo['vendido']) ?></strong><small>o que as lojas pagam</small></div>
  <div class="card an-kpi"><span>Bonificação sobre o vendido</span><strong data-bon="pct"><?= $pct((float) $resumo['bonificado'], (float) $resumo['vendido']) ?></strong><small>média do período</small></div>
</div>
<section class="card an-bloco" data-bonificacao-grupos>
  <header class="an-topo"><h2>Por <?= e(mb_strtolower(B::GRUPOS[$grupo])) ?></h2>
    <nav class="an-chips nao-imprimir" aria-label="Agrupar por"><?php foreach (B::GRUPOS as $k => $r): ?><a href="<?= $link(['grupo' => $k]) ?>" class="<?= $grupo === $k ? 'ativo' : '' ?>"><?= e($r) ?></a><?php endforeach; ?></nav></header>
  <div class="tabela-rolagem"><table class="b-tabela an-tabela">
    <thead><tr><th><?= e(B::GRUPOS[$grupo]) ?></th><th class="direita">Pedidos</th><?= $grupo === 'produto' ? '<th class="direita">Unidades</th>' : '<th class="direita">Vendido</th>' ?><th class="direita">Bonificado</th><?= $grupo === 'produto' ? '' : '<th class="direita">% do vendido</th>' ?></tr></thead>
    <tbody>
    <?php foreach ($grupos as $g): ?>
      <tr><td><?= e($grupo === 'mes' ? substr($g['grupo'], 5, 2) . '/' . substr($g['grupo'], 0, 4) : $g['grupo']) ?></td><td class="direita"><?= $n($g['pedidos']) ?></td>
        <td class="direita"><?= $grupo === 'produto' ? $n($g['unidades']) : dinheiro((float) $g['vendido']) ?></td><td class="direita"><strong><?= dinheiro((float) $g['bonificado']) ?></strong></td>
        <?= $grupo === 'produto' ? '' : '<td class="direita">' . $pct((float) $g['bonificado'], (float) $g['vendido']) . '</td>' ?></tr>
    <?php endforeach; ?>
    <?php if (!$grupos): ?><tr><td colspan="5" class="suave">Nenhuma bonificação no período.</td></tr><?php endif; ?>
    </tbody></table></div>
</section>
<section class="card an-bloco" data-bonificacao-pedidos>
  <header class="an-topo"><h2>Pedidos com bonificação</h2></header>
  <div class="tabela-rolagem"><table class="b-tabela an-tabela">
    <thead><tr><th>Pedido</th><th>Data</th><th>Cliente</th><th>Vendedor(a)</th><th>Motivo</th><th class="direita">Vendido</th><th class="direita">Bonificado</th></tr></thead>
    <tbody>
    <?php foreach ($pedidos as $p): ?>
      <tr><td><a href="<?= url('/pedidos/' . (int) $p['id']) ?>">#<?= (int) $p['id'] ?></a></td><td><?= data_br($p['criado_em']) ?></td><td><?= e($p['cliente']) ?></td><td><?= e($p['vendedor'] ?? '—') ?></td>
        <td><?= e($p['bonificacao_motivo'] ?? '—') ?></td><td class="direita"><?= dinheiro((float) $p['total']) ?></td>
        <td class="direita"><strong><?= dinheiro((float) $p['bonificado_valor']) ?></strong><?= $p['bonificacao_acima_limite'] ? '<br><span class="selo selo-desconto">acima do limite</span>' : '' ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$pedidos): ?><tr><td colspan="7" class="suave">Nenhum pedido com bonificação no período.</td></tr><?php endif; ?>
    </tbody></table></div>
</section>
