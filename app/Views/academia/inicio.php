<?php
/** Início da Academia. Variáveis: $proximo, $modulos, $totalProdutos, $aulasOne, $categorias, $temas, $progresso + cabeçalho */
use App\Core\Auth;
use App\Core\Visao;
use App\Services\AcademiaService;

$icones = asset('img/icones.svg');
$nome = \App\Services\EquipeService::comoChamar(Auth::usuario());
?>
<?= Visao::renderizar('academia/_cabecalho', get_defined_vars() + ['sobretitulo' => 'Academia Perfeita Alquimia', 'titulo_pagina' => 'Boas-vindas ao televendas.', 'chamada' => 'Aprenda no seu ritmo. Consulte sempre que precisar.']) ?>

<section class="academia-hero">
  <div>
    <span class="sobretitulo">SEU DESENVOLVIMENTO</span>
    <h2>Um passo de cada vez, <?= e($nome) ?>.</h2>
    <p>Conheça a empresa, explore os produtos e aprenda a conduzir um atendimento, do primeiro contato ao próximo pedido.</p>
    <div class="acoes-form">
      <?php if ($proximo): ?>
        <a class="botao lima" href="<?= url('/academia/trilha/' . e($proximo['id'])) ?>">Continuar: <?= e($proximo['title']) ?> →</a>
      <?php else: ?>
        <span class="selo lima">Você concluiu a trilha! 🎉</span>
      <?php endif; ?>
      <a class="botao contorno claro" href="<?= url('/academia/trilha/empresa') ?>">Conhecer a empresa</a>
    </div>
  </div>
  <div class="academia-hero-numeros">
    <div><strong><?= count($modulos) ?></strong><span>módulos na trilha</span></div>
    <div><strong><?= (int) $totalProdutos ?></strong><span>produtos para consulta</span></div>
    <div><strong><?= count($aulasOne) ?></strong><span>aulas de como usar o One</span></div>
  </div>
</section>

<div class="academia-atalhos">
  <a class="v4-acao" href="<?= url('/academia/trilha') ?>"><span class="v4-acao-icone lima"><svg class="icone"><use href="<?= $icones ?>#livro"/></svg></span><div><strong>Meu treinamento</strong><small>Da empresa à rotina de atendimento</small></div><b>↗</b></a>
  <a class="v4-acao" href="<?= url('/academia/produtos') ?>"><span class="v4-acao-icone verde"><svg class="icone"><use href="<?= $icones ?>#caixa"/></svg></span><div><strong>Conhecer os produtos</strong><small>Benefícios, composição e dúvidas frequentes</small></div><b>↗</b></a>
  <a class="v4-acao" href="<?= url('/academia/atendimento') ?>"><span class="v4-acao-icone pessego"><svg class="icone"><use href="<?= $icones ?>#conversa"/></svg></span><div><strong>Guia de atendimento</strong><small>Falas prontas para adaptar</small></div><b>↗</b></a>
  <?php if (Auth::pode('meu_mes.usar')): ?>
    <a class="v4-acao" href="<?= url('/meu-mes') ?>"><span class="v4-acao-icone lima"><svg class="icone"><use href="<?= $icones ?>#alvo"/></svg></span><div><strong>Meu mês</strong><small>Sua meta, a meta da equipe, vendas e tarefas</small></div><b>↗</b></a>
  <?php endif; ?>
  <a class="v4-acao" href="<?= url('/academia/trilha/midias-marketing') ?>"><span class="v4-acao-icone verde"><svg class="icone"><use href="<?= $icones ?>#nuvem"/></svg></span><div><strong>Mídias do marketing</strong><small>Materiais para o cliente divulgar</small></div><b>↗</b></a>
  <a class="v4-acao" href="<?= url('/academia/sistema') ?>"><span class="v4-acao-icone pessego"><svg class="icone"><use href="<?= $icones ?>#check"/></svg></span><div><strong>Como usar o One</strong><small><?= count($aulasOne) ?> aulas, do primeiro acesso ao pedido</small></div><b>↗</b></a>
</div>

<div class="grade-2">
  <section class="card">
    <h2>Pesquise um produto</h2>
    <form method="get" action="<?= url('/academia/produtos') ?>" class="busca-produto">
      <input type="search" name="q" placeholder="Nome, ingrediente ou necessidade: magnésio, sono, digestão…" aria-label="Buscar produto">
      <button class="botao" type="submit">Buscar</button>
    </form>
    <p class="pequeno suave" style="margin:14px 0 8px">Buscas por necessidade:</p>
    <div class="chips">
      <?php foreach (array_slice($temas, 0, 12) as $t): ?><a class="chip" href="<?= url('/academia/produtos') ?>?tema=<?= e($t['id']) ?>"><?= e($t['label']) ?></a><?php endforeach; ?>
    </div>
    <p class="pequeno suave" style="margin:14px 0 8px">Por apresentação:</p>
    <div class="chips">
      <?php foreach ($categorias as $cat => $qtd): ?><a class="chip" href="<?= url('/academia/produtos') ?>?categoria=<?= rawurlencode($cat) ?>"><?= e($cat) ?> <b><?= (int) $qtd ?></b></a><?php endforeach; ?>
    </div>
  </section>
  <section class="card">
    <h2>Acessos rápidos</h2>
    <div class="links-externos">
      <?php foreach (['catalogo' => 'Catálogo de produtos', 'site' => 'Site da empresa', 'instagram' => 'Instagram', 'grupo_marketing' => 'Grupo do marketing (WhatsApp)'] as $chave => $rotulo):
          $link = AcademiaService::link($chave); if (!$link) continue; ?>
        <a href="<?= e($link) ?>" target="_blank" rel="noopener noreferrer"><span><?= e($rotulo) ?></span><b>↗</b></a>
      <?php endforeach; ?>
    </div>
    <p class="pequeno suave">Seu progresso no treinamento fica salvo no sistema e acompanha você em qualquer computador ou celular.</p>
  </section>
</div>
