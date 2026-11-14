<?php /** Cartão de projeto. Variáveis: $p */
$atrasado = $p['prazo'] && $p['prazo'] < date('Y-m-d') && $p['situacao'] !== 'lancado'; ?>
<a class="dp-cartao <?= $atrasado ? 'atrasado' : '' ?> <?= e($p['situacao']) ?>" href="<?= url('/desenvolvimento/' . (int) $p['id']) ?>" data-projeto="<?= (int) $p['id'] ?>">
  <strong><?= e($p['nome']) ?></strong>
  <?php if ($p['cliente']): ?><span class="selo cinza">Terceirização · <?= e($p['cliente']) ?></span><?php endif; ?>
  <small><?= $p['funcao'] ? 'Com: ' . e($p['funcao']) : 'Com: gestão' ?><?= $p['situacao'] === 'pausado' ? ' · <b>pausado</b>' : '' ?></small>
  <?php if ($p['prazo']): ?><small class="<?= $atrasado ? 'dp-atraso' : '' ?>">Prazo <?= data_br($p['prazo']) ?></small><?php endif; ?>
</a>
