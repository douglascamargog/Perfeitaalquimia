<?php /** Página de erro. Variáveis: $codigo, $mensagem */ ?>
<div class="card card-erro" style="max-width:560px">
  <img src="<?= asset('img/one/one-ops-padrao.svg') ?>" alt="" width="96" height="106">
  <h2><?= e($mensagem) ?></h2>
  <p class="suave">Código <?= (int) $codigo ?></p>
  <a class="botao" href="<?= url('/') ?>">Voltar ao início</a>
</div>
