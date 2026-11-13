<?php
/** Meu perfil. Variáveis: $u (usuário logado), $foco (áreas atuais), $focoFuncoes (áreas das funções) */
use App\Core\Visao;
?>
<div class="cabecalho-secao"><div><div class="sobretitulo">MINHA CONTA</div><h1>Meu perfil</h1>
  <p>Sua foto aparece no topo, no ranking da equipe e no card de aniversariantes.</p></div>
  <div class="acoes"><a class="botao contorno" href="<?= url('/minha-senha') ?>">Trocar minha senha</a></div></div>
<form method="post" action="<?= url('/meu-perfil') ?>" class="card" style="max-width:820px" enctype="multipart/form-data">
  <?= csrf_campo() ?>
  <p class="suave"><strong><?= e($u['nome']) ?></strong> · <?= e($u['email']) ?> · <?= e($u['perfil_nome']) ?><?= $u['funcoes'] ? ' · ' . e(implode(', ', array_column($u['funcoes'], 'nome'))) : '' ?></p>
  <?= Visao::renderizar('usuarios/_ficha', ['u' => $u, 'completo' => false]) ?>
  <h2>O que aparece para mim</h2>
  <p class="suave pequeno">Escolha as áreas que você acompanha: elas definem os cartões do seu Início, as dicas do One, o sino e os números do menu.
    <?= $focoFuncoes ? 'Pelas suas funções: <strong>' . e(implode(', ', array_map(fn($a) => explode(' (', \App\Services\Foco::AREAS[$a])[0], $focoFuncoes))) . '</strong>.' : '' ?>
    Isto não muda o que você pode abrir (todas as abas continuam no menu).</p>
  <input type="hidden" name="foco_enviado" value="1">
  <div class="mp-foco" data-foco>
    <?php foreach (\App\Services\Foco::AREAS as $area => $rotulo): ?>
      <label class="opcao"><input type="checkbox" name="foco[]" value="<?= $area ?>" <?= in_array($area, $foco, true) ? 'checked' : '' ?>> <?= e($rotulo) ?></label>
    <?php endforeach; ?>
  </div>
  <div class="acoes-form"><button class="botao" type="submit">Salvar</button></div>
</form>
