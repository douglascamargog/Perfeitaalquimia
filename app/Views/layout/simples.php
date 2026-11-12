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
  <title><?= e($titulo ?? '') ?> · <?= e(config('app_nome')) ?></title>
  <link rel="stylesheet" href="<?= asset('css/base.css') ?>">
  <style>
    .pagina-simples { min-height: 100vh; display: grid; place-items: center; padding: 24px 16px;
      background: radial-gradient(circle at 85% 10%, rgba(202,255,77,.35), transparent 40%), var(--verde-escuro); }
    .caixa-simples { width: 100%; max-width: 420px; }
    .caixa-simples .marca-login { display: flex; align-items: center; gap: 12px; color: #fff; margin-bottom: 20px; }
    .caixa-simples .marca-login span { width: 44px; height: 44px; border-radius: 14px; background: var(--lima);
      color: var(--verde-escuro); display: grid; place-items: center; }
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
      <span><svg class="icone"><use href="<?= asset('img/icones.svg') ?>#folha"/></svg></span>
      <div><strong><?= e(config('app_nome')) ?></strong><div style="font-size:.8rem;color:#9fbcb0">Sistema unificado</div></div>
    </div>
    <?php foreach (Sessao::avisos() as $aviso): ?>
      <div class="aviso <?= e($aviso['tipo']) ?>" role="status"><?= e($aviso['mensagem']) ?></div>
    <?php endforeach; ?>
    <?= $conteudo ?>
  </div>
</div>
</body>
</html>
