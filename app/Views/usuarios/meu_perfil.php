<?php
/** Meu perfil. Variável: $u (usuário logado) */
use App\Core\Visao;
?>
<div class="cabecalho-secao"><div><div class="sobretitulo">MINHA CONTA</div><h1>Meu perfil</h1>
  <p>Sua foto aparece no topo, no ranking da equipe e no card de aniversariantes.</p></div>
  <div class="acoes"><a class="botao contorno" href="<?= url('/minha-senha') ?>">Trocar minha senha</a></div></div>
<form method="post" action="<?= url('/meu-perfil') ?>" class="card" style="max-width:820px" enctype="multipart/form-data">
  <?= csrf_campo() ?>
  <div class="grade-campos">
    <div class="campo"><label for="nome">Nome</label><input type="text" id="nome" name="nome" maxlength="120" required value="<?= e(antigo('nome', $u['nome'])) ?>"></div>
    <div class="campo"><label>E-mail de acesso</label><input type="email" value="<?= e($u['email']) ?>" disabled>
      <div class="ajuda"><?= e($u['perfil_nome']) ?><?= $u['funcoes'] ? ' · ' . e(implode(', ', array_column($u['funcoes'], 'nome'))) : '' ?></div></div>
  </div>
  <?= Visao::renderizar('usuarios/_ficha', ['u' => $u, 'completo' => false]) ?>
  <p class="suave pequeno">O que aparece no seu Início e no sino segue a sua função. Para mudar o e-mail de acesso, a função ou o que você acompanha, fale com o administrador.</p>
  <div class="acoes-form"><button class="botao" type="submit">Salvar</button></div>
</form>
