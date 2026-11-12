<?php /** Aprovar / recusar. Variáveis: $p, $voltar ('fila' | 'pedido') */ ?>
<div class="ped-acoes-aprovacao">
  <form method="post" action="<?= url('/aprovacao/' . (int) $p['id'] . '/aprovar') ?>">
    <?= csrf_campo() ?><input type="hidden" name="voltar" value="<?= e($voltar) ?>">
    <button class="botao" type="submit">✓ Aprovar</button>
  </form>
  <details>
    <summary class="botao contorno">Recusar</summary>
    <form method="post" action="<?= url('/aprovacao/' . (int) $p['id'] . '/recusar') ?>">
      <?= csrf_campo() ?><input type="hidden" name="voltar" value="<?= e($voltar) ?>">
      <textarea name="motivo" rows="2" required maxlength="500" placeholder="Motivo (o vendedor e o cliente vão ver)" aria-label="Motivo da recusa"></textarea>
      <button class="botao contorno pequeno" type="submit">Confirmar recusa</button>
    </form>
  </details>
</div>
