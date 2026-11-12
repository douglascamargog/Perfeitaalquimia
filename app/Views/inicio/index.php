<?php
/** Tela inicial. Variáveis: $usuario, $modulos, $saudacao e $falas (One, o ajudante) */
$icones = asset('img/icones.svg');
$primeiroNome = explode(' ', $usuario['nome'])[0];
?>
<section class="one-inicio" aria-label="One, seu ajudante">
  <img src="<?= asset('img/one/one-' . e($saudacao['humor']) . '-invertido.svg') ?>" alt="One, o ajudante do sistema" width="160" height="176">
  <div>
    <h2><?= e(explode('!', $saudacao['texto'])[0]) ?>!</h2>
    <p>Eu sou o One, seu ajudante. Separei o que merece sua atenção hoje:</p>
    <?= \App\Core\Visao::renderizar('one/_falas', ['saudacao' => $saudacao, 'falas' => $falas]) ?>
  </div>
</section>

<h2>Módulos disponíveis para o seu perfil</h2>
<div class="grade-modulos">
  <?php foreach ($modulos as $m): ?>
    <?php if ($m['pronto']): ?>
      <a class="modulo" href="<?= url($m['url']) ?>">
    <?php else: ?>
      <div class="modulo">
    <?php endif; ?>
        <div class="modulo-icone"><svg class="icone"><use href="<?= $icones ?>#<?= e($m['icone']) ?>"/></svg></div>
        <strong><?= e($m['titulo']) ?></strong>
        <p><?= e($m['descricao']) ?></p>
        <?= $m['pronto'] ? '<span class="selo lima">Disponível</span>' : '<span class="selo cinza">Em breve</span>' ?>
    <?= $m['pronto'] ? '</a>' : '</div>' ?>
  <?php endforeach; ?>
</div>
