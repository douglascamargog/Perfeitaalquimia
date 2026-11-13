<?php
/**
 * Layout do sistema interno: menu lateral (filtrado pelo perfil), topo e área de conteúdo.
 * Variáveis: $titulo, $conteudo
 */
use App\Controllers\InicioController;
use App\Core\Auth;
use App\Core\Sessao;

$usuario = Auth::usuario();
// Contador vermelho do menu (o que está pendente) e notificações não lidas do sino
$pendencias = \App\Services\PendenciasService::doUsuario();
$naoLidas = \App\Services\NotificacaoService::naoLidas((int) $usuario['id']);
$totalAtencao = array_sum($pendencias) + $naoLidas;
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
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= $totalAtencao ? '(' . $totalAtencao . ') ' : '' ?><?= e($titulo ?? '') ?> · <?= e(config('app_nome')) ?> · <?= e(config('empresa_nome', 'Perfeita Alquimia')) ?></title>
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
              <b class="menu-contador" data-pendencia="<?= e($item['url']) ?>" <?= empty($pendencias[$item['url']]) ? 'hidden' : '' ?> title="Pendente agora"><?= (int) ($pendencias[$item['url']] ?? 0) ?></b>
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
      <button class="botao-icone abrir-menu" type="button" aria-label="Abrir menu"><svg class="icone"><use href="<?= $icones ?>#menu"/></svg><i class="menu-ponto" data-menu-ponto <?= array_sum($pendencias) ? '' : 'hidden' ?>></i></button>
      <h1><?= e($titulo ?? '') ?></h1>
      <div class="busca-rapida" data-busca-rapida>
        <svg class="icone" aria-hidden="true"><use href="<?= $icones ?>#lupa"/></svg>
        <input type="search" placeholder="<?= Auth::pode('carteira.ver_propria') || Auth::pode('carteira.ver_todas') ? 'Buscar cliente, CNPJ, telefone ou código…' : 'Buscar produto ou código…' ?>"
               aria-label="Busca rápida" autocomplete="off" data-busca-campo>
        <kbd aria-hidden="true">/</kbd>
        <div class="busca-resultados" data-busca-resultados hidden role="listbox"></div>
      </div>
      <div class="usuario">
        <div class="sino" data-sino>
          <button class="botao-icone" type="button" aria-label="Notificações" aria-haspopup="true" aria-expanded="false" data-sino-botao>
            <svg class="icone" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg>
            <b class="sino-contador" data-sino-contador <?= $naoLidas ? '' : 'hidden' ?>><?= $naoLidas > 99 ? '99+' : $naoLidas ?></b>
          </button>
          <div class="sino-painel" data-sino-painel hidden role="dialog" aria-label="Notificações">
            <header><strong>Notificações</strong>
              <form method="post" action="<?= url('/notificacoes/marcar-todas') ?>" data-sino-marcar><?= csrf_campo() ?><button type="submit" class="botao contorno pequeno">Marcar todas como lidas</button></form></header>
            <div class="sino-lista" data-sino-lista><p class="suave pequeno">Carregando…</p></div>
            <footer><a href="<?= url('/notificacoes') ?>">Ver todas</a>
              <button type="button" class="sino-pc" data-ativar-avisos hidden>Avisar no computador</button></footer>
          </div>
        </div>
        <a class="usuario-perfil" href="<?= url('/meu-perfil') ?>" title="Meu perfil">
          <span class="usuario-nome">
            <strong><?= e($usuario['nome']) ?></strong>
            <span><?= e($usuario['perfil_nome']) ?><?= $usuario['funcoes'] ? ' · ' . e(implode(', ', array_column($usuario['funcoes'], 'nome'))) : '' ?></span>
          </span>
          <?= \App\Services\EquipeService::avatar($usuario, 38) ?>
        </a>
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
<script type="module" src="<?= asset('js/busca.js') ?>"></script>
<script type="module" src="<?= asset('js/notificacoes.js') ?>"></script>
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
