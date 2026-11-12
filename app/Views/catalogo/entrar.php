<?php
/** Entrar no catálogo: CNPJ + senha, ou código (primeiro acesso / esqueci a senha). Variáveis: $modo, $codigoDemo */
?>
<div class="cat-entrar">
  <h1><?= $modo === 'codigo' ? 'Primeiro acesso ou nova senha' : 'Entrar' ?></h1>
  <?php if ($modo !== 'codigo'): ?>
    <p class="suave">Use o CNPJ da sua empresa para ver os preços e fazer pedidos.</p>
    <form class="card" method="post" action="<?= url('/catalogo/entrar') ?>">
      <?= csrf_campo() ?>
      <div class="campo"><label for="cnpj">CNPJ</label><input type="text" id="cnpj" name="cnpj" inputmode="numeric" autocomplete="username" required value="<?= e(antigo('cnpj')) ?>" placeholder="00.000.000/0000-00"></div>
      <div class="campo"><label for="senha">Senha</label><input type="password" id="senha" name="senha" autocomplete="current-password" required></div>
      <button class="botao largo" type="submit">Entrar</button>
    </form>
    <p><a href="<?= url('/catalogo/entrar') ?>?modo=codigo">Primeiro acesso ou esqueceu a senha? Receba um código</a></p>
  <?php else: ?>
    <form class="card" method="post" action="<?= url('/catalogo/codigo') ?>">
      <?= csrf_campo() ?>
      <p class="pequeno">1. Informe o CNPJ: enviamos um código para o e-mail do cadastro. Também dá para pedir o código ao seu vendedor pelo WhatsApp.</p>
      <div class="campo"><label for="cnpj1">CNPJ</label><input type="text" id="cnpj1" name="cnpj" inputmode="numeric" required value="<?= e(antigo('cnpj')) ?>" placeholder="00.000.000/0000-00"></div>
      <button class="botao contorno largo" type="submit">Receber código</button>
    </form>
    <?php if ($codigoDemo): ?><p class="aviso aviso">Demonstração: o código enviado é <strong data-codigo-demo><?= e($codigoDemo) ?></strong></p><?php endif; ?>
    <form class="card" method="post" action="<?= url('/catalogo/senha') ?>">
      <?= csrf_campo() ?>
      <p class="pequeno">2. Digite o código e crie a sua senha (mínimo 8 caracteres).</p>
      <div class="campo"><label for="cnpj2">CNPJ</label><input type="text" id="cnpj2" name="cnpj" inputmode="numeric" required value="<?= e(antigo('cnpj')) ?>"></div>
      <div class="campo"><label for="codigo">Código</label><input type="text" id="codigo" name="codigo" inputmode="numeric" maxlength="6" autocomplete="one-time-code" required></div>
      <div class="campo"><label for="nova">Nova senha</label><input type="password" id="nova" name="senha" minlength="8" autocomplete="new-password" required></div>
      <button class="botao largo" type="submit">Criar senha e entrar</button>
    </form>
    <p><a href="<?= url('/catalogo/entrar') ?>">← Já tenho senha</a></p>
  <?php endif; ?>
</div>
