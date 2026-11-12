<?php
/**
 * Layout do sistema interno: menu lateral (filtrado pelo perfil), topo e área de conteúdo.
 * Variáveis: $titulo, $conteudo
 */
use App\Controllers\InicioController;
use App\Core\Auth;
use App\Core\Sessao;

$usuario = Auth::usuario();
$caminhoAtual = '/' . trim(substr(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), strlen(url_base())), '/');
$icones = asset('img/icones.svg');

// Agrupa os itens do menu que este usuário pode ver e acha o item da página atual
// (o endereço mais longo que combina: /configuracoes/listas destaca "Listas", não "Configurações")
$grupos = [];
$itemAtual = '/';
foreach (require RAIZ . '/app/menu.php' as $item) {
    if (InicioController::visivel($item)) {
        $grupos[$item['grupo']][] = $item;
        if ($item['url'] !== '/' && str_starts_with($caminhoAtual . '/', $item['url'] . '/') && strlen($item['url']) > strlen($itemAtual)) {
            $itemAtual = $item['url'];
        }
    }
}
// Carteira filtrada pela campanha em destaque → acende o item da campanha (ex.: Black Friday 2026)
if (str_starts_with($caminhoAtual, '/carteira') && ($_GET['origem'] ?? '') !== '' && (string) \App\Controllers\CarteiraController::campanhaOrigemId() === (string) $_GET['origem']) {
    $itemAtual = '/carteira/campanha';
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($titulo ?? '') ?> · <?= e(config('app_nome')) ?> · <?= e(config('empresa_nome', 'Perfeita Alquimia')) ?></title>
  <link rel="icon" type="image/svg+xml" href="<?= asset('img/one/one-ola-padrao.svg') ?>">
  <link rel="stylesheet" href="<?= asset('css/base.css') ?>">
  <link rel="stylesheet" href="<?= asset('css/app.css') ?>">
  <?php // CSS extras da tela: $estilos = ['carteira.css'] ou endereços externos (https://...) ?>
  <?php foreach ($estilos ?? [] as $css): ?>
    <link rel="stylesheet" href="<?= str_starts_with($css, 'https://') ? e($css) : asset('css/' . $css) ?>">
  <?php endforeach; ?>
  <meta name="csrf" content="<?= e(\App\Core\Csrf::token()) ?>">
  <meta name="base" content="<?= e(url_base()) ?>">
</head>
<body>
<?php if (config('modo_demo')): ?>
  <div class="faixa-demo">Ambiente de demonstração — dados simulados</div>
<?php endif; ?>

<div class="estrutura">
  <aside class="menu-lateral" aria-label="Menu principal">
    <a class="marca" href="<?= url('/') ?>">
      <span class="marca-logo"><img src="<?= asset('img/logo.png') ?>" alt="Logo <?= e(config('app_nome')) ?>"></span>
      <span><strong class="marca-nome"><?= e(config('app_nome')) ?></strong><span><?= e(config('empresa_nome', 'Perfeita Alquimia')) ?></span></span>
    </a>
    <nav>
      <?php foreach ($grupos as $grupo => $itens): ?>
        <?php if ($grupo !== ''): ?><div class="menu-grupo"><?= e($grupo) ?></div><?php endif; ?>
        <?php foreach ($itens as $item):
            $ativo = $item['url'] === '/' ? $caminhoAtual === '/' : $item['url'] === $itemAtual; ?>
          <?php if ($item['pronto']): ?>
            <a href="<?= url($item['url']) ?>" class="<?= trim(($ativo ? 'ativo' : '') . (!empty($item['destaque']) ? ' destaque' : '')) ?>">
              <svg class="icone"><use href="<?= $icones ?>#<?= e($item['icone']) ?>"/></svg><?= e($item['titulo']) ?>
            </a>
          <?php else: ?>
            <span class="item" title="Módulo em construção no protótipo">
              <svg class="icone"><use href="<?= $icones ?>#<?= e($item['icone']) ?>"/></svg><?= e($item['titulo']) ?>
              <span class="em-breve">em breve</span>
            </span>
          <?php endif; ?>
        <?php endforeach; ?>
      <?php endforeach; ?>
    </nav>
    <div class="menu-rodape"><?= e(config('app_nome')) ?> · versão <?= e(\App\Controllers\AtualizacaoController::versao()) ?></div>
  </aside>

  <div class="principal">
    <header class="topo">
      <button class="botao-icone abrir-menu" type="button" aria-label="Abrir menu"><svg class="icone"><use href="<?= $icones ?>#menu"/></svg></button>
      <h1><?= e($titulo ?? '') ?></h1>
      <div class="usuario">
        <div class="usuario-nome">
          <strong><?= e($usuario['nome']) ?></strong>
          <span><?= e($usuario['perfil_nome']) ?><?= $usuario['funcoes'] ? ' · ' . e(implode(', ', array_column($usuario['funcoes'], 'nome'))) : '' ?></span>
        </div>
        <a class="botao-icone" href="<?= url('/minha-senha') ?>" title="Minha senha" aria-label="Minha senha"><svg class="icone"><use href="<?= $icones ?>#chave"/></svg></a>
        <form method="post" action="<?= url('/sair') ?>">
          <?= csrf_campo() ?>
          <button class="botao-icone" type="submit" title="Sair" aria-label="Sair"><svg class="icone"><use href="<?= $icones ?>#sair"/></svg></button>
        </form>
      </div>
    </header>

    <main class="conteudo">
      <?php foreach (Sessao::avisos() as $aviso): ?>
        <div class="aviso <?= e($aviso['tipo']) ?>" role="status"><?= e($aviso['mensagem']) ?></div>
      <?php endforeach; ?>
      <?= $conteudo ?>
    </main>
  </div>
</div>
<!-- One, o ajudante: botão flutuante; as falas carregam quando a pessoa abre -->
<button type="button" class="one-botao" data-one-abrir aria-expanded="false" aria-controls="one-painel" title="Falar com o One">
  <img src="<?= asset('img/one/one-ola-invertido.svg') ?>" alt="" width="40" height="44"><span class="one-botao-texto">One</span>
</button>
<section class="one-painel" id="one-painel" hidden aria-label="One, seu ajudante">
  <header><img src="<?= asset('img/one/one-piscando-invertido.svg') ?>" alt="" width="36" height="40"><div><strong>One</strong><small>Seu ajudante no sistema</small></div>
    <button type="button" class="botao-icone escuro" data-one-fechar aria-label="Fechar">✕</button></header>
  <div class="one-painel-corpo" data-one-corpo><p class="one-carregando"><img src="<?= asset('img/one/one-pensando-padrao.svg') ?>" alt="" width="40">Um instante, estou olhando seus dados…</p></div>
</section>
<script type="module" src="<?= asset('js/app.js') ?>"></script>
<?php // JS extras da tela: $scripts = ['carteira.js'] (módulos) ou bibliotecas externas (https://...) ?>
<?php foreach ($scripts ?? [] as $js): ?>
  <?php if (str_starts_with($js, 'https://')): ?>
    <script src="<?= e($js) ?>"></script>
  <?php else: ?>
    <script type="module" src="<?= asset('js/' . $js) ?>"></script>
  <?php endif; ?>
<?php endforeach; ?>
</body>
</html>
