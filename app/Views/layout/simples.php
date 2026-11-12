<?php
/**
 * Layout simples (sem menu): login, instalação e erros para quem não está logado.
 */
use App\Core\Sessao;
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($titulo ?? '') ?> · <?= e(config('app_nome')) ?> · <?= e(config('empresa_nome', 'Perfeita Alquimia')) ?></title>
  <link rel="icon" type="image/svg+xml" href="<?= asset('img/one/one-ola-padrao.svg') ?>">
  <link rel="stylesheet" href="<?= asset('css/base.css') ?>">
  <style>
    .pagina-simples { min-height: 100vh; display: grid; place-items: center; padding: 24px 16px;
      background: radial-gradient(ellipse at 78% 8%, #a2de3c36 0, transparent 34%), linear-gradient(125deg, #0f1711 0%, #19261a 55%, #243927 100%); }
    .caixa-simples { width: 100%; max-width: 420px; }
    .caixa-simples .marca-login { display: flex; align-items: center; gap: 12px; color: #fff; margin-bottom: 20px; }
    .caixa-simples .marca-login span { width: 64px; height: 60px; border-radius: 14px; background: #fff; padding: 6px; display: grid; place-items: center; }
    .caixa-simples .marca-login img { width: 100%; height: 100%; object-fit: contain; }
    .caixa-simples .marca-login strong { font-size: 1.2rem; }
  </style>
</head>
<body>
<?php if (config('modo_demo')): ?>
  <div class="faixa-demo">Ambiente de demonstração — dados simulados</div>
<?php endif; ?>
<div class="pagina-simples">
  <div class="caixa-simples">
    <div class="marca-login">
      <span><img src="<?= asset('img/logo.png') ?>" alt="Logo <?= e(config('app_nome')) ?>"></span>
      <div><strong style="font-size:1.6rem;letter-spacing:-.04em"><?= e(config('app_nome')) ?></strong><div style="font-size:.8rem;color:#a5b1a4"><?= e(config('empresa_nome', 'Perfeita Alquimia')) ?></div></div>
    </div>
    <?php foreach (Sessao::avisos() as $aviso): ?>
      <div class="aviso <?= e($aviso['tipo']) ?>" role="status"><?= e($aviso['mensagem']) ?></div>
    <?php endforeach; ?>
    <?= $conteudo ?>
  </div>
</div>
</body>
</html>
