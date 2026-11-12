<?php /** Instalação pelo navegador. Variáveis: $ativo, $log */ ?>
<div class="card">
  <h2>Instalação do banco de dados</h2>
  <?php if (!$ativo): ?>
    <p class="suave">A instalação pelo navegador está desligada. Para ligar, preencha <code>instalar_token</code> no arquivo <code>config/config.php</code>.</p>
  <?php else: ?>
    <p class="suave pequeno">Cria as tabelas que faltam e carrega os dados de demonstração. Pode rodar mais de uma vez: só aplica o que é novo.</p>
    <form method="post" action="<?= url('/instalar') ?>">
      <?= csrf_campo() ?>
      <div class="campo">
        <label for="token">Token de instalação</label>
        <input type="password" id="token" name="token" required>
        <div class="ajuda">É o valor de <code>instalar_token</code> no config.php.</div>
      </div>
      <label class="opcao"><input type="checkbox" name="seeds" value="1" checked> Carregar dados de demonstração</label>
      <button class="botao largo" type="submit">Instalar / atualizar</button>
    </form>
  <?php endif; ?>
  <?php if ($log): ?>
    <div class="aviso sucesso" style="margin-top:16px;white-space:pre-line"><?= e(implode("\n", $log)) ?></div>
    <p class="pequeno suave">Terminou? Apague o <code>instalar_token</code> do config.php e <a href="<?= url('/entrar') ?>">entre no sistema</a>.</p>
  <?php endif; ?>
</div>
