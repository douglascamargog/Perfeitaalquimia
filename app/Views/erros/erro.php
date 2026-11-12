<?php /** Página de erro. Variáveis: $codigo, $mensagem */ ?>
<div class="card" style="max-width:560px">
  <h2><?= e($mensagem) ?></h2>
  <p class="suave">Código <?= (int) $codigo ?></p>
  <a class="botao" href="<?= url('/') ?>">Voltar ao início</a>
</div>
