<?php
/** Meu perfil. Variáveis: $u (usuário logado) */
use App\Core\Visao;
?>
<div class="cabecalho-secao"><div><div class="sobretitulo">MINHA CONTA</div><h1>Meu perfil</h1>
  <p>Sua foto aparece no topo, no ranking da equipe e no card de aniversariantes.</p></div>
  <div class="acoes"><a class="botao contorno" href="<?= url('/minha-senha') ?>">Trocar minha senha</a></div></div>
<form method="post" action="<?= url('/meu-perfil') ?>" class="card" style="max-width:820px" enctype="multipart/form-data">
  <?= csrf_campo() ?>
  <p class="suave"><strong><?= e($u['nome']) ?></strong> · <?= e($u['email']) ?> · <?= e($u['perfil_nome']) ?><?= $u['funcoes'] ? ' · ' . e(implode(', ', array_column($u['funcoes'], 'nome'))) : '' ?></p>
  <?= Visao::renderizar('usuarios/_ficha', ['u' => $u, 'completo' => false]) ?>
  <div class="acoes-form"><button class="botao" type="submit">Salvar</button></div>
</form>
