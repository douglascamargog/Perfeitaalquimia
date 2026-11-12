<?php
/**
 * App do vendedor externo (PWA). A tela é montada pelo app.js com os dados guardados no celular (IndexedDB),
 * por isso abre e funciona sem internet depois da primeira vez. Esta página não traz dados de clientes.
 */
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="theme-color" content="#14251b">
  <meta name="base" content="<?= e(url_base()) ?>">
  <title>One Vendas</title>
  <link rel="manifest" href="<?= url('/app/manifest.webmanifest') ?>">
  <link rel="apple-touch-icon" href="<?= asset('app/apple-touch-icon.png') ?>">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-title" content="One Vendas">
  <link rel="icon" type="image/png" href="<?= asset('app/icone-192.png') ?>">
  <link rel="stylesheet" href="<?= asset('css/base.css') ?>">
  <link rel="stylesheet" href="<?= asset('app/app.css') ?>">
</head>
<body class="ap">
<?php if (config('modo_demo')): ?><div class="faixa-demo">Ambiente de demonstração — dados simulados</div><?php endif; ?>
<header class="ap-topo">
  <img src="<?= asset('img/one/one-ola-invertido.svg') ?>" alt="" width="34" height="37">
  <div class="ap-titulo"><strong>One Vendas</strong><small data-vendedor>&nbsp;</small></div>
  <span class="ap-rede" data-rede>…</span>
</header>
<div class="ap-aviso" data-aviso hidden></div>
<main class="ap-conteudo" data-tela><p class="ap-carregando">Abrindo…</p></main>
<nav class="ap-abas" aria-label="Seções do app">
  <button type="button" data-aba="clientes" class="ativa"><span>Clientes</span></button>
  <button type="button" data-aba="pedidos"><span>Pedidos</span><b data-pendentes hidden></b></button>
  <button type="button" data-aba="sincronizar"><span>Sincronizar</span></button>
</nav>
<div class="aviso-flutuante" data-aviso-flutuante role="status" aria-live="polite"></div>
<script type="module" src="<?= asset('app/app.js') ?>"></script>
</body>
</html>
