<div class="card" style="max-width:520px">
  <h2>Trocar minha senha</h2>
  <p class="suave pequeno">Mínimo de 8 caracteres, com letras e números.</p>
  <form method="post" action="<?= url('/minha-senha') ?>">
    <?= csrf_campo() ?>
    <div class="campo">
      <label for="senha_atual">Senha atual</label>
      <input type="password" id="senha_atual" name="senha_atual" autocomplete="current-password" required>
    </div>
    <div class="campo">
      <label for="senha">Nova senha</label>
      <input type="password" id="senha" name="senha" autocomplete="new-password" minlength="8" required>
    </div>
    <div class="campo">
      <label for="senha_confirmacao">Repita a nova senha</label>
      <input type="password" id="senha_confirmacao" name="senha_confirmacao" autocomplete="new-password" minlength="8" required>
    </div>
    <button class="botao" type="submit">Salvar nova senha</button>
  </form>
</div>
