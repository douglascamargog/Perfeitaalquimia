<?php
/**
 * Layout do CATÁLOGO B2B (área do cliente, pensada para o celular).
 * Variáveis: $titulo, $conteudo, $clienteCatalogo (ou null), $vendedorCatalogo (ou null), $qtdCarrinho
 */
use App\Core\Config;
use App\Core\Sessao;

$cli = $clienteCatalogo ?? null;
$icones = asset('img/icones.svg');
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#14251b">
  <title><?= e($titulo ?? 'Catálogo') ?> · <?= e(Config::get('catalogo.titulo', 'Catálogo')) ?></title>
  <link rel="icon" type="image/png" href="<?= asset('img/logo.png') ?>">
  <link rel="stylesheet" href="<?= asset('css/base.css') ?>">
  <link rel="stylesheet" href="<?= asset('css/catalogo.css') ?>">
  <meta name="csrf" content="<?= e(\App\Core\Csrf::token()) ?>">
  <meta name="base" content="<?= e(url_base()) ?>">
</head>
<body class="cat">
<?php if (config('modo_demo')): ?><div class="faixa-demo">Ambiente de demonstração — dados simulados</div><?php endif; ?>
<header class="cat-topo">
  <a class="cat-marca" href="<?= url('/catalogo') ?>"><img src="<?= asset('img/logo.png') ?>" alt="Perfeita Alquimia" width="44" height="44"><span><?= e(Config::get('catalogo.titulo', 'Catálogo')) ?></span></a>
  <nav class="cat-nav" aria-label="Catálogo">
    <?php if ($cli): ?>
      <a href="<?= url('/catalogo/pedidos') ?>">Meus pedidos</a>
      <a class="cat-carrinho" href="<?= url('/catalogo/carrinho') ?>" aria-label="Carrinho">
        <svg class="icone"><use href="<?= $icones ?>#carrinho"/></svg><b data-qtd-carrinho><?= (int) ($qtdCarrinho ?? 0) ?></b>
      </a>
      <form method="post" action="<?= url('/catalogo/sair') ?>"><?= csrf_campo() ?><button type="submit" class="cat-link">Sair</button></form>
    <?php else: ?>
      <a class="cat-botao" href="<?= url('/catalogo/entrar') ?>">Entrar para ver preços</a>
    <?php endif; ?>
  </nav>
</header>
<?php if ($cli || !empty($vendedorCatalogo)): ?>
  <div class="cat-faixa">
    <?php if ($cli): ?><span><?= e($cli['nome_fantasia'] ?: $cli['razao_social']) ?> · Sua tabela: <strong><?= e($tabelaCatalogo ?? '') ?></strong></span><?php endif; ?>
    <?php if (!empty($vendedorCatalogo)): ?><span>Atendimento: <strong><?= e($vendedorCatalogo['nome']) ?></strong></span><?php endif; ?>
  </div>
<?php endif; ?>
<main class="cat-conteudo">
  <?php foreach (Sessao::avisos() as $aviso): ?>
    <div class="aviso <?= e($aviso['tipo']) ?>" role="status"><?= e($aviso['mensagem']) ?></div>
  <?php endforeach; ?>
  <?= $conteudo ?>
</main>
<footer class="cat-rodape">
  <?= e(Config::get('empresa.nome', 'Perfeita Alquimia')) ?> · Venda exclusiva para empresas (CNPJ)
  <?php if ($w = preg_replace('/\D/', '', (string) Config::get('empresa.whatsapp', ''))): ?> · <a href="https://wa.me/55<?= e($w) ?>" target="_blank" rel="noopener noreferrer">WhatsApp</a><?php endif; ?>
</footer>
<div class="aviso-flutuante" data-aviso-flutuante role="status" aria-live="polite"></div>
<script type="module" src="<?= asset('js/catalogo.js') ?>"></script>
</body>
</html>
