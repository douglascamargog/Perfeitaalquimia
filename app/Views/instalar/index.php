<?php /** Instalação pelo navegador. Variáveis: $ativo, $log */ ?>
<div class="card">
  <h2>Instalação do banco de dados</h2>
  <?php if (!$ativo): ?>
    <p class="suave">A instalação pelo navegador está desligada. Para ligar, preencha <code>instalar_token</code> no arquivo <code>config/config.php</code>.</p>
  <?php else: ?>
    <p class="suave pequeno">Primeira instalação: cria as tabelas e carrega os dados (lojas, Academia e demonstração). Para versões novas, use Administração › Atualização, dentro do sistema.</p>
    <form method="post" action="<?= url('/instalar') ?>">
      <?= csrf_campo() ?>
      <div class="campo">
        <label for="token">Token de instalação</label>
        <input type="password" id="token" name="token" required>
        <div class="ajuda">É o valor de <code>instalar_token</code> no config.php.</div>
      </div>
      <button class="botao largo" type="submit">Instalar / atualizar</button>
    </form>
  <?php endif; ?>
  <?php if ($log): ?>
    <div class="aviso sucesso" style="margin-top:16px;white-space:pre-line"><?= e(implode("\n", $log)) ?></div>
    <p class="pequeno suave">Terminou? Apague o <code>instalar_token</code> do config.php e <a href="<?= url('/entrar') ?>">entre no sistema</a>.</p>
  <?php endif; ?>
</div>
