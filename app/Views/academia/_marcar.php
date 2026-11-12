<?php
/** Botão "Já estudei". Variáveis: $item ('modulo:x' ou 'crm:y'), $feito (bool), $rotulo, $voltar (endereço) */
?>
<form method="post" action="<?= url('/academia/progresso') ?>" class="marcar-estudo">
  <?= csrf_campo() ?>
  <input type="hidden" name="item" value="<?= e($item) ?>">
  <input type="hidden" name="concluido" value="<?= $feito ? '0' : '1' ?>">
  <input type="hidden" name="voltar" value="<?= e($voltar) ?>">
  <button type="submit" class="botao <?= $feito ? 'contorno' : 'lima' ?>"><?= $feito ? '✓ Estudado · desmarcar' : e($rotulo) ?></button>
</form>
