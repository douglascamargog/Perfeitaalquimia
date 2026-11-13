<?php /** Aba sem acesso (o menu mostra todas as abas; esta é a página quando a pessoa não pode abrir). Variáveis: $aba */ ?>
<div class="card card-erro" style="max-width:560px" data-sem-acesso>
  <img src="<?= asset('img/one/one-pensando-padrao.svg') ?>" alt="" width="96" height="106">
  <h2>Você ainda não tem acesso a <?= e($aba) ?></h2>
  <p>Os acessos de cada pessoa são definidos pelo administrador em <strong>Administração › Acessos</strong>.
    Se você precisa desta aba no seu trabalho, peça a liberação: pode ser só para ver ou também para editar.</p>
  <a class="botao" href="<?= url('/') ?>">Voltar ao início</a>
</div>
