<?php /** Novo orçamento. Variáveis: $c, $materias, $projetos */ ?>
<a class="voltar-link" href="<?= url('/compras') ?>">← Orçamentos</a>
<div class="cabecalho-secao"><div><div class="sobretitulo">COMPRAS · NOVO ORÇAMENTO</div><h1>O que vamos comprar?</h1>
  <p>Depois de criar, adicione as cotações e envie para aprovação.</p></div></div>
<form method="post" action="<?= url('/compras') ?>" class="card pd-form cp-form"><?= csrf_campo() ?>
  <?= \App\Core\Visao::renderizar('compras/_campos', compact('c', 'materias', 'projetos')) ?>
  <div class="acoes-form"><a class="botao contorno" href="<?= url('/compras') ?>">Cancelar</a><button class="botao" type="submit">Criar orçamento</button></div>
</form>
