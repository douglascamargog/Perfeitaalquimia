<?php
/** Tela inicial. Variáveis: $usuario, $modulos */
$icones = asset('img/icones.svg');
$primeiroNome = explode(' ', $usuario['nome'])[0];
?>
<section class="boas-vindas">
  <h2>Olá, <?= e($primeiroNome) ?>!</h2>
  <p>Este é o protótipo do sistema unificado da Perfeita Alquimia: carteira e CRM, catálogo B2B, pedidos integrados ao Omie,
     painel de vendas, Academia e produção num lugar só. Os módulos marcados como "em breve" estão em construção.</p>
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
