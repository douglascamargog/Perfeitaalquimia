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

// Menu em grupos que abrem e fecham: topo (Início, Equipe), um grupo por SETOR DE VENDA da pessoa e os demais
// (Financeiro, Gestão, Marketing...). Todos veem todas as abas que não são de setor: sem acesso → cadeado; só leitura → olho.
$grupos = \App\Core\Menu::lateral($caminhoAtual, $pendencias);
?>
<!doctype html>
<html lang="pt-BR" data-tema="<?= e(\App\Services\Aparencia::tema(Auth::usuario())) ?>">
<head>
  <script>/* tema escuro antes de desenhar a tela (sem "piscar" claro) */(function(){var h=document.documentElement,m=window.matchMedia('(prefers-color-scheme: dark)');function a(){var t=h.dataset.tema;h.classList.toggle('escuro',t==='escuro'||(t==='auto'&&m.matches));}a();m.addEventListener&&m.addEventListener('change',a);window.oneAplicarTema=a;})();</script>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= $totalAtencao ? '(' . $totalAtencao . ') ' : '' ?><?= e($titulo ?? '') ?> · <?= e(config('app_nome')) ?> · <?= e(config('empresa_nome', 'Perfeita Alquimia')) ?></title>
  <link rel="icon" type="image/png" href="<?= asset('img/one/one-icone-64.png') ?>">
  <link rel="stylesheet" href="<?= asset('css/base.css') ?>">
  <link rel="stylesheet" href="<?= asset('css/app.css') ?>">
  <?php // CSS extras da tela: $estilos = ['carteira.css'] ou endereços externos (https://...) ?>
  <?php foreach ($estilos ?? [] as $css): ?>
    <link rel="stylesheet" href="<?= str_starts_with($css, 'https://') ? e($css) : asset('css/' . $css) ?>">
  <?php endforeach; ?>
  <?php // Tema escuro: versão escura das cores fixas (gerada por ferramentas/gerar_tema_escuro.py) ?>
  <link rel="stylesheet" href="<?= asset('css/tema-escuro.css') ?>">
  <?php // Cores do sistema: as da pessoa (Meu perfil › Aparência) ou as da empresa ?>
  <?= \App\Services\Aparencia::estilo(Auth::usuario()) ?>
  <link rel="preload" href="<?= asset('fonts/montserrat-latin.woff2') ?>" as="font" type="font/woff2" crossorigin>
  <meta name="csrf" content="<?= e(\App\Core\Csrf::token()) ?>">
  <meta name="base" content="<?= e(url_base()) ?>">
</head>
<?php $meuAniversario = \App\Services\AniversarioService::eHoje(Auth::usuario()); // só o sistema do aniversariante fica decorado ?>
<body class="<?= $meuAniversario ? 'aniversario' : '' ?>">
<?php if ($meuAniversario): ?>
  <div class="aniv-baloes" aria-hidden="true">
    <svg viewBox="0 0 60 120" class="aniv-balao b1"><ellipse cx="30" cy="34" rx="24" ry="30" fill="#f472b6"/><path d="M30 64l-4 6h8z" fill="#f472b6"/><path d="M30 70c-6 14 6 26 0 46" stroke="#94a3b8" fill="none"/></svg>
    <svg viewBox="0 0 60 120" class="aniv-balao b2"><ellipse cx="30" cy="34" rx="24" ry="30" fill="#facc15"/><path d="M30 64l-4 6h8z" fill="#facc15"/><path d="M30 70c-6 14 6 26 0 46" stroke="#94a3b8" fill="none"/></svg>
    <svg viewBox="0 0 60 120" class="aniv-balao b3"><ellipse cx="30" cy="34" rx="24" ry="30" fill="#5cd4b6"/><path d="M30 64l-4 6h8z" fill="#5cd4b6"/><path d="M30 70c-6 14 6 26 0 46" stroke="#94a3b8" fill="none"/></svg>
  </div>
  <canvas class="aniv-confete" data-confete aria-hidden="true"></canvas>
<?php endif; ?>

<div class="estrutura">
  <aside class="menu-lateral" aria-label="Menu principal">
    <a class="marca" href="<?= url('/') ?>">
      <span class="marca-logo"><img src="<?= asset('img/logo.png') ?>" alt="Logo <?= e(config('app_nome')) ?>"></span>
      <span><strong class="marca-nome"><?= e(config('app_nome')) ?></strong><span><?= e(config('empresa_nome', 'Perfeita Alquimia')) ?></span></span>
    </a>
    <nav>
      <?php foreach ($grupos as $g): ?>
        <?php if (!$g['topo']): ?>
        <div class="menu-secao <?= $g['aberto'] ? 'aberta' : '' ?>" data-menu-secao="<?= e($g['chave']) ?>" <?= $g['aberto'] ? 'data-atual' : '' ?>>
          <button type="button" class="menu-secao-botao <?= $g['bloqueado'] ? 'bloqueado' : '' ?>" aria-expanded="<?= $g['aberto'] ? 'true' : 'false' ?>">
            <svg class="icone"><use href="<?= $icones ?>#<?= e($g['icone']) ?>"/></svg><span><?= e($g['nome']) ?></span>
            <b class="menu-contador" <?= $g['contador'] ? '' : 'hidden' ?>><?= (int) $g['contador'] ?></b>
            <svg class="icone menu-seta" aria-hidden="true"><use href="<?= $icones ?>#mais"/></svg>
          </button>
          <div class="menu-sub">
          <?php if (!empty($g['setores'])): ?>
            <label class="menu-setor-escolha"><span>Setor</span>
              <select data-setor-escolha aria-label="Setor de venda">
                <?php foreach ($g['setores'] as $st): ?><option value="<?= e(url($st['href'])) ?>" data-setor-chip="<?= e($st['slug']) ?>" <?= $st['ativo'] ? 'selected' : '' ?>><?= e($st['nome']) ?></option><?php endforeach; ?>
              </select>
            </label>
          <?php endif; ?>
        <?php endif; ?>
        <?php foreach ($g['itens'] as $item): ?>
          <?php if ($item['pronto']): ?>
            <a href="<?= url($item['href']) ?>" class="<?= trim(($item['ativo'] ? 'ativo' : '') . (!empty($item['destaque']) ? ' destaque' : '') . ($item['bloqueado'] ? ' bloqueado' : '')) ?>" data-url="<?= e($item['url']) ?>"
               <?= $item['bloqueado'] ? 'title="Você não tem acesso a esta aba" data-bloqueado' : ($item['leitura'] ? 'title="Você pode ver, mas não alterar"' : '') ?>>
              <svg class="icone"><use href="<?= $icones ?>#<?= e($item['icone']) ?>"/></svg><?= e($item['titulo']) ?>
              <?php if ($item['bloqueado']): ?><span class="menu-cadeado" aria-label="sem acesso">🔒</span><?php elseif ($item['leitura']): ?><span class="menu-leitura" aria-label="só leitura" title="Só leitura">👁</span><?php endif; ?>
              <?php if ($item['comContador'] ?? true): ?><b class="menu-contador" data-pendencia="<?= e($item['url']) ?>" <?= $item['contador'] ? '' : 'hidden' ?> title="Pendente agora"><?= (int) $item['contador'] ?></b><?php endif; ?>
            </a>
          <?php else: ?>
            <span class="item" title="Módulo em construção no protótipo">
              <svg class="icone"><use href="<?= $icones ?>#<?= e($item['icone']) ?>"/></svg><?= e($item['titulo']) ?>
              <span class="em-breve">em breve</span>
            </span>
          <?php endif; ?>
        <?php endforeach; ?>
        <?php if (!$g['topo']): ?></div></div><?php endif; ?>
      <?php endforeach; ?>
    </nav>
    <div class="menu-rodape"><a href="<?= url('/novidades') ?>" title="O que mudou em cada versão"><?= e(\App\Services\Versoes::nome()) ?> · novidades</a></div>
  </aside>

  <div class="principal">
    <header class="topo">
      <button class="botao-icone abrir-menu" type="button" aria-label="Abrir menu"><svg class="icone"><use href="<?= $icones ?>#menu"/></svg><i class="menu-ponto" data-menu-ponto <?= array_sum($pendencias) ? '' : 'hidden' ?>></i></button>
      <h1><?= e($titulo ?? '') ?></h1>
      <?php // Setor de venda em que a pessoa está (Carteira, Agenda, Pedidos... mostram só os clientes dele)
      $setorAtual = !empty(\App\Core\Menu::itemDe($caminhoAtual)['setor']) ? \App\Services\Setores::atual() : null; ?>
      <?php if ($setorAtual): ?><span class="selo-setor" data-selo-setor><svg class="icone"><use href="<?= $icones ?>#<?= e($setorAtual['icone']) ?>"/></svg><?= e($setorAtual['nome']) ?>
        <?php if (Auth::pode('carteira.ver_todas')): ?><a href="<?= url($caminhoAtual) ?>?setor=" title="Ver todos os setores" aria-label="Ver todos os setores">✕</a><?php endif; ?></span><?php endif; ?>
      <?php // Comunicados não lidos: faixa em destaque (some ao clicar em "Li e entendi") ?>
      <?php // Letreiro: "Novo pedido de Fulana" passa ~30 s quando entra um pedido (o notificacoes.js traz a cada minuto) ?>
      <div class="letreiro" data-letreiro data-pedido-ultimo="<?= (int) \App\Core\Banco::valor('SELECT COALESCE(MAX(id), 0) FROM pedidos') ?>" hidden aria-live="polite"></div>
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
        <?php if (\App\Services\TelefoniaService::veDiscador()): ?><?= \App\Core\Visao::renderizar('layout/_discador') ?><?php endif; ?>
        <button class="botao-icone" type="button" data-tema-alternar title="Trocar entre tema claro e escuro" aria-label="Trocar entre tema claro e escuro"><svg class="icone tema-icone-lua"><use href="<?= $icones ?>#lua"/></svg><svg class="icone tema-icone-sol"><use href="<?= $icones ?>#sol"/></svg></button>
        <a class="botao-icone <?= str_starts_with($caminhoAtual, '/pessoas') ? 'ativo' : '' ?>" href="<?= url('/pessoas') ?>" title="Pessoas: perfis, gostos e aniversários" aria-label="Pessoas" data-icone-pessoas><svg class="icone"><use href="<?= $icones ?>#pessoas"/></svg></a>
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
        <?php if (Auth::ehDesenvolvedor()): $modo = $usuario['modo'] ?? null; ?>
          <details class="dev-modo <?= $modo ? 'como-funcao' : '' ?>" data-dev-modo>
            <summary title="Trocar o jeito de ver o One (só você vê este botão)"><?= $modo ? '👁 ' . e($modo['nome']) : '🛠 Desenvolvedor' ?></summary>
            <form method="post" action="<?= url('/desenvolvedor/modo') ?>" class="dev-modo-painel"><?= csrf_campo() ?>
              <input type="hidden" name="voltar" value="<?= e($caminhoAtual) ?>">
              <strong>Ver o One como</strong>
              <button type="submit" name="funcao" value="" class="<?= $modo ? '' : 'ativo' ?>" data-dev-opcao="desenvolvedor">🛠 Desenvolvedor <small>vê e faz tudo</small></button>
              <?php $minha = null; foreach (\App\Services\Desenvolvedor::opcoes((int) $usuario['id']) as $op): ?>
                <?php if ($minha !== null && $minha !== (bool) $op['minha']): ?><span class="dev-modo-sep">outras funções</span><?php endif; $minha = (bool) $op['minha']; ?>
                <button type="submit" name="funcao" value="<?= (int) $op['id'] ?>" class="<?= ($modo['id'] ?? 0) == $op['id'] ? 'ativo' : '' ?>" data-dev-opcao="<?= e($op['slug']) ?>"><?= e($op['nome']) ?>
                  <small><?= $op['perfil'] ? 'perfil ' . e($op['perfil']) : '' ?><?= $op['minha'] ? ' · sua função' : '' ?></small></button>
              <?php endforeach; ?>
            </form>
          </details>
        <?php endif; ?>
        <a class="usuario-perfil" href="<?= url('/meu-perfil') ?>" title="Meu perfil">
          <span class="usuario-nome">
            <strong title="<?= e($usuario['nome']) ?>"><?= e(\App\Services\EquipeService::comoChamar($usuario)) ?></strong>
            <span><?= e($usuario['perfil_nome']) ?><?= $usuario['funcoes'] ? ' · ' . e(implode(', ', array_column($usuario['funcoes'], 'nome'))) : '' ?></span>
          </span>
          <?= \App\Services\EquipeService::avatar($usuario, 38) ?>
        </a>
        <?php if (\App\Core\Menu::administracao()): ?><a class="botao-icone <?= str_starts_with($caminhoAtual, '/administracao') ? 'ativo' : '' ?>" href="<?= url('/administracao') ?>" title="Administração" aria-label="Administração" data-engrenagem><svg class="icone"><use href="<?= $icones ?>#engrenagem"/></svg></a><?php endif; ?>
        <form method="post" action="<?= url('/sair') ?>">
          <?= csrf_campo() ?>
          <button class="botao-icone" type="submit" title="Sair" aria-label="Sair"><svg class="icone"><use href="<?= $icones ?>#sair"/></svg></button>
        </form>
      </div>
    </header>

    <main class="conteudo">
      <?php if (!empty($usuario['modo'])): ?>
        <form class="dev-faixa" method="post" action="<?= url('/desenvolvedor/modo') ?>" role="status" data-dev-faixa><?= csrf_campo() ?>
          <input type="hidden" name="voltar" value="<?= e($caminhoAtual) ?>">
          <span>👁 Você está vendo o One como <strong><?= e($usuario['modo']['nome']) ?></strong> (perfil <?= e($usuario['modo']['perfil']) ?>): só essa função e o que esse perfil pode.</span>
          <button type="submit" name="funcao" value="" class="botao contorno pequeno">Voltar ao modo desenvolvedor</button>
        </form>
      <?php endif; ?>
      <?php if ($meuAniversario): ?>
        <div class="aniv-faixa" role="status">🎉 <strong>Feliz aniversário, <?= e(\App\Services\EquipeService::comoChamar($usuario)) ?>!</strong> A equipe toda está torcendo por você hoje.
          <a href="<?= url('/') ?>#mural-aniversario">Ver os parabéns</a></div>
      <?php endif; ?>
      <?php $desfazer = \App\Services\DesfazerService::paraMostrar(); ?>
      <?php foreach (Sessao::avisos() as $aviso): ?>
        <div class="aviso <?= e($aviso['tipo']) ?>" role="status"><?= e($aviso['mensagem']) ?>
          <?php if ($desfazer && $aviso['tipo'] === 'sucesso'): ?>
            <form method="post" action="<?= url('/desfazer') ?>" style="display:inline;margin-left:10px" data-desfazer><?= csrf_campo() ?>
              <input type="hidden" name="voltar" value="<?= e(substr((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), strlen(url_base())) ?: '/') ?>">
              <button class="botao contorno pequeno" type="submit">↶ Desfazer</button></form>
          <?php endif; ?></div>
      <?php endforeach; ?>
      <?= $conteudo ?>
    </main>
  </div>
</div>
<!-- One, o ajudante: botão flutuante; as falas carregam quando a pessoa abre (depois do horário de descanso, ele dorme) -->
<?php $descansar = \App\Core\Auth::logado() && \App\Services\OneAssistente::horaDeDescansar(); ?>
<button type="button" class="one-botao" data-one-abrir aria-expanded="false" aria-controls="one-painel" title="Falar com o One" <?= $descansar ? 'data-one-descanso="' . e('Já passou das ' . str_replace(':', 'h', (string) \App\Core\Config::get('one.hora_descanso', '17:45')) . '. Hora de descansar! Termine o que estiver fazendo e até amanhã. 😴') . '"' : '' ?>>
  <img src="<?= asset('img/one/one-' . ($descansar ? 'dormindo' : 'piscando') . '-invertido.webp') ?>" alt="" width="40" height="44"><span class="one-botao-texto">One</span>
</button>
<section class="one-painel" id="one-painel" hidden aria-label="One, seu ajudante">
  <header><img src="<?= asset('img/one/one-piscando-invertido.webp') ?>" alt="" width="36" height="40"><div><strong>One</strong><small>Seu ajudante no sistema</small></div>
    <button type="button" class="botao-icone escuro" data-one-fechar aria-label="Fechar">✕</button></header>
  <div class="one-abas" role="tablist" aria-label="One">
    <button type="button" role="tab" aria-selected="true" data-one-aba="dicas">💡 Para você</button>
    <button type="button" role="tab" aria-selected="false" data-one-aba="perguntar">💬 Perguntar</button>
  </div>
  <div class="one-painel-corpo" data-one-corpo data-one-aba-painel="dicas"><p class="one-carregando"><img src="<?= asset('img/one/one-correndo-padrao.webp') ?>" alt="" width="40">Um instante, estou olhando seus dados…</p></div>
  <div class="one-chat" data-one-aba-painel="perguntar" hidden>
    <?php $especialistas = \App\Services\OneEspecialistas::paraPessoa(); $espPadrao = \App\Services\OneEspecialistas::padrao(); ?>
    <?php if (count($especialistas) > 1): ?>
      <div class="one-especialistas" role="tablist" aria-label="Especialistas do One" data-one-especialistas data-padrao="<?= e($espPadrao) ?>">
        <?php foreach ($especialistas as $esp): ?>
          <button type="button" role="tab" data-one-especialista="<?= e($esp['slug']) ?>" aria-selected="<?= $esp['slug'] === $espPadrao ? 'true' : 'false' ?>"
            title="<?= e($esp['descricao']) ?>" data-sugestoes="<?= e(json_encode(array_values(array_filter(explode('|', (string) $esp['sugestoes']))), JSON_UNESCAPED_UNICODE)) ?>"
            data-nome="<?= e($esp['nome']) ?>" data-descricao="<?= e($esp['descricao']) ?>"><span aria-hidden="true"><?= e($esp['icone']) ?></span><?= e($esp['nome']) ?></button>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
    <div class="one-conversa" data-one-conversa aria-live="polite">
      <div class="one-chat-vazio" data-one-sugestoes>
        <img src="<?= asset('img/one/one-ola-padrao.webp') ?>" alt="" width="54" height="60">
        <p data-one-esp-intro>Pergunte do seu jeito, como falaria com um colega. Por exemplo:</p>
        <div data-one-exemplos>
          <button type="button" data-one-exemplo>Como faço um orçamento?</button>
          <button type="button" data-one-exemplo>Quanto falta para a minha meta?</button>
          <button type="button" data-one-exemplo>Quais campanhas estão no ar?</button>
        </div>
      </div>
    </div>
    <form class="one-pergunte" data-one-pergunte>
      <input type="text" name="pergunta" maxlength="500" autocomplete="off" placeholder="Escreva sua pergunta…" aria-label="Pergunte ao One">
      <button type="submit" aria-label="Perguntar">➤</button>
    </form>
    <p class="one-pergunte-nota"><?= \App\Services\OnePergunta::comIa() ? '✨ Respostas com IA' : 'Respostas com as aulas e os dados do sistema' ?> · <button type="button" data-one-limpar>nova conversa</button></p>
  </div>
</section>
<?php // Novidades da versão: aparece uma vez para quem ainda não viu a versão atual
$usuarioNov = Auth::usuario();
\App\Services\Versoes::avisarSeNova(); // versão nova? avisa a equipe no sino (uma vez)
if ($usuarioNov && array_key_exists('versao_vista', $usuarioNov) && empty($usuarioNov['trocar_senha']) && $caminhoAtual !== '/novidades' && ($novasVersoes = \App\Services\Versoes::naoVistas($usuarioNov['versao_vista'] ?? null, \App\Services\Versoes::ehAdministrador()))): ?>
  <div class="nov-fundo" data-novidades>
    <section class="nov-janela" role="dialog" aria-modal="true" aria-labelledby="nov-titulo">
      <header class="nov-topo">
        <img src="<?= asset('img/one/one-ola-padrao.webp') ?>" alt="" width="54" height="60">
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
<?php if ($meuAniversario): ?><script type="module" src="<?= asset('js/aniversario.js') ?>"></script><?php endif; ?>
<?php if ($festaOne = \App\Services\OneTela::festaPendente()): ?><script type="application/json" id="one-festa"><?= json_encode($festaOne, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script><?php endif; ?>
<script type="module" src="<?= asset('js/app.js') ?>"></script>
<script type="module" src="<?= asset('js/one-vivo.js') ?>"></script>
<script type="module" src="<?= asset('js/lista-longa.js') ?>"></script>
<script type="module" src="<?= asset('js/busca.js') ?>"></script>
<script type="module" src="<?= asset('js/notificacoes.js') ?>"></script>
<?php if (\App\Services\TelefoniaService::veDiscador()): ?><script type="module" src="<?= asset('js/discador.js') ?>"></script><?php endif; ?>
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
