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
// Quem está online: registra onde a pessoa está (só o nome da aba) e pega quem mais está usando agora
\App\Services\Presenca::registrar((int) Auth::id(), $caminhoAtual);
$online = \App\Services\Presenca::online((int) Auth::id());
$icones = asset('img/icones.svg');

// Agrupa TODOS os itens do menu (todo mundo vê todas as abas): sem acesso → cadeado; só leitura → olho.
// Dentro de cada grupo, as abas que a pessoa pode abrir vêm primeiro.
// Item da página atual = o endereço mais longo que combina (/configuracoes/listas destaca "Listas", não "Configurações")
$grupos = [];
$itemAtual = '/';
foreach (require RAIZ . '/app/menu.php' as $item) {
    $item['bloqueado'] = !InicioController::visivel($item);
    $item['leitura'] = !$item['bloqueado'] && !empty($item['editar']) && !Auth::pode($item['editar']);
    $grupos[$item['grupo']][] = $item;
    if (!$item['bloqueado'] && $item['url'] !== '/' && str_starts_with($caminhoAtual . '/', $item['url'] . '/') && strlen($item['url']) > strlen($itemAtual)) {
        $itemAtual = $item['url'];
    }
}
foreach ($grupos as &$itensDoGrupo) {
    usort($itensDoGrupo, fn($a, $b) => (int) $a['bloqueado'] <=> (int) $b['bloqueado']); // usort é estável no PHP 8
}
unset($itensDoGrupo);
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
  <?php // Cores do sistema: as da pessoa (Meu perfil › Aparência) ou as da empresa ?>
  <?= \App\Services\Aparencia::estilo(Auth::usuario()) ?>
  <link rel="preload" href="<?= asset('fonts/montserrat-latin.woff2') ?>" as="font" type="font/woff2" crossorigin>
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
            <a href="<?= url($item['url']) ?>" class="<?= trim(($ativo ? 'ativo' : '') . (!empty($item['destaque']) ? ' destaque' : '') . ($item['bloqueado'] ? ' bloqueado' : '')) ?>"
               <?= $item['bloqueado'] ? 'title="Você não tem acesso a esta aba" data-bloqueado' : ($item['leitura'] ? 'title="Você pode ver, mas não alterar"' : '') ?>>
              <svg class="icone"><use href="<?= $icones ?>#<?= e($item['icone']) ?>"/></svg><?= e($item['titulo']) ?>
              <?php if ($item['bloqueado']): ?><span class="menu-cadeado" aria-label="sem acesso">🔒</span><?php elseif ($item['leitura']): ?><span class="menu-leitura" aria-label="só leitura" title="Só leitura">👁</span><?php endif; ?>
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
    <div class="menu-rodape"><a href="<?= url('/novidades') ?>" title="O que mudou em cada versão"><?= e(\App\Services\Versoes::nome()) ?> · novidades</a></div>
  </aside>

  <div class="principal">
    <header class="topo">
      <button class="botao-icone abrir-menu" type="button" aria-label="Abrir menu"><svg class="icone"><use href="<?= $icones ?>#menu"/></svg><i class="menu-ponto" data-menu-ponto <?= array_sum($pendencias) ? '' : 'hidden' ?>></i></button>
      <h1><?= e($titulo ?? '') ?></h1>
      <?php // Comunicados não lidos: faixa em destaque (some ao clicar em "Li e entendi") ?>
      <div class="com-lugar" data-com-lugar><?= \App\Core\Visao::renderizar('comunicados/_faixa', ['comunicados' => \App\Services\ComunicadoService::ativosPara((int) Auth::id())]) ?></div>
      <div class="busca-rapida" data-busca-rapida>
        <svg class="icone" aria-hidden="true"><use href="<?= $icones ?>#lupa"/></svg>
        <input type="search" placeholder="<?= Auth::pode('carteira.ver_propria') || Auth::pode('carteira.ver_todas') ? 'Buscar cliente, CNPJ, telefone ou código…' : 'Buscar produto ou código…' ?>"
               aria-label="Busca rápida" autocomplete="off" data-busca-campo>
        <kbd aria-hidden="true">/</kbd>
        <div class="busca-resultados" data-busca-resultados hidden role="listbox"></div>
      </div>
      <div class="usuario">
        <details class="online" data-online <?= $online ? '' : 'hidden' ?>><?= \App\Core\Visao::renderizar('layout/_online', ['online' => $online]) ?></details>
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
            <strong title="<?= e($usuario['nome']) ?>"><?= e(\App\Services\EquipeService::comoChamar($usuario)) ?></strong>
            <span><?= e($usuario['perfil_nome']) ?><?= $usuario['funcoes'] ? ' · ' . e(implode(', ', array_column($usuario['funcoes'], 'nome'))) : '' ?></span>
          </span>
          <?= \App\Services\EquipeService::avatar($usuario, 38) ?>
        </a>
        <a class="botao-icone so-tela-grande" href="<?= url('/minha-senha') ?>" title="Minha senha" aria-label="Minha senha"><svg class="icone"><use href="<?= $icones ?>#chave"/></svg></a>
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
<?php // Novidades da versão: aparece uma vez para quem ainda não viu a versão atual
$usuarioNov = Auth::usuario();
\App\Services\Versoes::avisarSeNova(); // versão nova? avisa a equipe no sino (uma vez)
if ($usuarioNov && array_key_exists('versao_vista', $usuarioNov) && empty($usuarioNov['trocar_senha']) && $caminhoAtual !== '/novidades' && ($novasVersoes = \App\Services\Versoes::naoVistas($usuarioNov['versao_vista'] ?? null, \App\Services\Versoes::ehAdministrador()))): ?>
  <div class="nov-fundo" data-novidades>
    <section class="nov-janela" role="dialog" aria-modal="true" aria-labelledby="nov-titulo">
      <header class="nov-topo">
        <img src="<?= asset('img/one/one-ola-padrao.svg') ?>" alt="" width="54" height="60">
        <div><span class="nov-rotulo">O One foi atualizado</span><h2 id="nov-titulo">O que há de novo</h2></div>
      </header>
      <div class="nov-corpo">
        <?php foreach ($novasVersoes as $versao): ?><?= \App\Core\Visao::renderizar('novidades/_versao', ['versao' => $versao]) ?><?php endforeach; ?>
      </div>
      <form method="post" action="<?= url('/novidades/vista') ?>" class="nov-acoes">
        <?= csrf_campo() ?>
        <a href="<?= url('/novidades') ?>">Ver todas as versões</a>
        <button class="botao" type="submit" data-novidades-ok>Entendi</button>
      </form>
    </section>
  </div>
<?php endif; ?>
<div class="com-janela-fundo" data-com-janela hidden role="dialog" aria-modal="true" aria-label="Comunicado">
  <div class="com-janela">
    <div data-com-conteudo></div>
    <div class="com-janela-acoes"><a href="<?= url('/comunicados') ?>">Ver todos os comunicados</a>
      <button type="button" class="botao contorno" data-com-fechar>Depois</button><button type="button" class="botao" data-com-ok>Li e entendi</button></div>
  </div>
</div>
<script type="module" src="<?= asset('js/comunicados.js') ?>"></script>
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
