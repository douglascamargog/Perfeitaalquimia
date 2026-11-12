<div class="one-boas-vindas">
  <img src="<?= asset('img/one/one-ola-invertido.svg') ?>" alt="One, o ajudante do sistema" width="110" height="121">
  <p><strong>Oi! Eu sou o One.</strong> Entre com o seu login e eu te mostro o que é importante hoje.</p>
</div>
<div class="card">
  <h2>Entrar</h2>
  <p class="suave pequeno">Use o seu login individual.</p>
  <form method="post" action="<?= url('/entrar') ?>">
    <?= csrf_campo() ?>
    <div class="campo">
      <label for="email">E-mail</label>
      <input type="email" id="email" name="email" value="<?= antigo('email') ?>" autocomplete="username" required autofocus>
    </div>
    <div class="campo">
      <label for="senha">Senha</label>
      <input type="password" id="senha" name="senha" autocomplete="current-password" required>
    </div>
    <button class="botao largo" type="submit">Entrar</button>
  </form>
</div>

<?php if (config('modo_demo')): ?>
<div class="card pequeno">
  <h3>Logins de demonstração</h3>
  <p class="suave">Senha de todos: <strong>Demo@2026</strong></p>
  <div class="tabela-rolagem">
    <table class="tabela">
      <tr><td>admin@demo.local</td><td>Admin</td></tr>
      <tr><td>gestor@demo.local</td><td>Gestor</td></tr>
      <tr><td>televendas1@demo.local</td><td>Vendedor · Televendas</td></tr>
      <tr><td>externo1@demo.local</td><td>Vendedor · Externo</td></tr>
      <tr><td>reativacao@demo.local</td><td>Vendedor · Prospecção e Reativação</td></tr>
      <tr><td>terceirizacao@demo.local</td><td>Vendedor · Terceirização</td></tr>
      <tr><td>financeiro@demo.local</td><td>Financeiro</td></tr>
      <tr><td>marketing@demo.local</td><td>Marketing</td></tr>
      <tr><td>producao@demo.local</td><td>Produção</td></tr>
    </table>
  </div>
</div>
<?php endif; ?>
