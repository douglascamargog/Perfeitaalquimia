<?php /** Cartão de projeto. Variáveis: $p, $progresso (% formado) */
$atrasado = $p['prazo'] && $p['prazo'] < date('Y-m-d') && $p['situacao'] !== 'lancado';
$progresso = (int) ($progresso ?? 0); ?>
<a class="dp-cartao <?= $atrasado ? 'atrasado' : '' ?> <?= e($p['situacao']) ?>" href="<?= url('/desenvolvimento/' . (int) $p['id']) ?>" data-projeto="<?= (int) $p['id'] ?>">
  <span class="dp-cartao-topo"><?= \App\Core\Visao::renderizar('desenvolvimento/_tipos', ['tipo' => $p['tipo'] ?? 'pote', 'classe' => 'mini completo']) ?><strong><?= e($p['nome']) ?></strong></span>
  <?php if ($p['cliente']): ?><span class="selo cinza">Terceirização · <?= e($p['cliente']) ?></span><?php endif; ?>
  <small><?= $p['funcao'] ? 'Com: ' . e($p['funcao']) : 'Com: gestão' ?><?= $p['situacao'] === 'pausado' ? ' · <b>pausado</b>' : '' ?></small>
  <?php if ($p['prazo']): ?><small class="<?= $atrasado ? 'dp-atraso' : '' ?>">Prazo <?= data_br($p['prazo']) ?></small><?php endif; ?>
  <span class="dp-barra" title="<?= $progresso ?>% formado"><i style="width: <?= $progresso ?>%"></i></span>
</a>
