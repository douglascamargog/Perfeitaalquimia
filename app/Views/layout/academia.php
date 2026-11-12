<?php
/**
 * Layout da Academia: página inteira no visual da Central de Televendas original,
 * com a faixa de demonstração e um botão para voltar ao sistema.
 */
?>
<!doctype html>
<html lang="pt-BR" data-theme="classic">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="description" content="Central de treinamento e consulta do televendas da Perfeita Alquimia.">
  <title>Academia Comercial | <?= e(config('app_nome')) ?></title>
  <link rel="stylesheet" href="<?= asset('academia/academia.css') ?>">
  <style>
    /* Acréscimos do sistema: faixa de demonstração e botão de volta */
    .faixa-demo { background: #caff4d; color: #14251b; text-align: center; font: 600 12px/1 'Segoe UI', Arial, sans-serif; padding: 6px 12px; position: relative; z-index: 60; }
    .voltar-sistema { position: fixed; right: 18px; bottom: 18px; z-index: 70; display: inline-flex; align-items: center; gap: 8px; padding: 10px 16px;
      border-radius: 999px; background: #101711; color: #caff4d; font: 700 13px/1 'Segoe UI', Arial, sans-serif; text-decoration: none; box-shadow: 0 8px 24px rgba(0,0,0,.25); }
    .voltar-sistema:hover { background: #253726; }
    @media print { .faixa-demo, .voltar-sistema { display: none !important; } }
  </style>
</head>
<body>
<?php if (config('modo_demo')): ?><div class="faixa-demo">Ambiente de demonstração — dados simulados</div><?php endif; ?>
<?= $conteudo ?>
<a class="voltar-sistema" href="<?= url('/') ?>" title="Voltar ao sistema">← Sistema</a>
<script src="<?= url('/academia/dados.js') ?>"></script>
<script src="<?= asset('academia/academia.js') ?>"></script>
</body>
</html>
