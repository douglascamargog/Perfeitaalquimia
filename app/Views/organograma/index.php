<?php
/**
 * Organograma: interligações entre os setores (diagrama desenhado por organograma.js) e a organização da empresa.
 * Variáveis: $interligacoes (setores, ligacoes, colunas, resumo), $organizacao (areas, sem_funcao, total), $numeros (mostra números vivos)
 */
use App\Services\EquipeService;

$r = $interligacoes['resumo'];
$setores = $interligacoes['setores'];
$situacao = ['ok' => 'integrado', 'parcial' => 'pela metade', 'ilha' => 'ilha'];
$primeiroNome = fn(array $p) => EquipeService::comoChamar($p);
?>
<div class="cabecalho-secao">
  <div><div class="sobretitulo">EMPRESA</div><h1>Organograma</h1>
    <p>Como os setores da Perfeita Alquimia trocam informação dentro do One e quem faz parte de cada área.</p></div>
</div>

<nav class="abas org-abas" aria-label="Visões do organograma" data-org-abas>
  <a href="#interligacoes" class="ativa" data-org-aba="interligacoes">Interligações dos setores</a>
  <a href="#organizacao" data-org-aba="organizacao">Organização da empresa <span class="contador"><?= (int) $organizacao['total'] ?></span></a>
</nav>

<!-- ============================ INTERLIGAÇÕES ============================ -->
<section class="org-painel" id="interligacoes" data-org-painel="interligacoes">
  <div class="org-resumo">
    <div class="org-resumo-numeros">
      <span class="org-num ok"><strong><?= (int) $r['ok'] ?></strong> integradas</span>
      <span class="org-num parcial"><strong><?= (int) $r['parcial'] ?></strong> pela metade</span>
      <span class="org-num ilha"><strong><?= (int) $r['ilha'] ?></strong> ilhas</span>
    </div>
    <div class="org-filtros" role="group" aria-label="Mostrar">
      <button type="button" class="botao contorno pequeno ativo" data-org-filtro="todas" aria-pressed="true">Todas as ligações</button>
      <button type="button" class="botao contorno pequeno" data-org-filtro="falta" aria-pressed="false">Só o que falta ligar</button>
    </div>
  </div>
  <p class="pequeno suave org-dica">Clique num setor para ver o que ele recebe e o que entrega. Linha cheia: integrado. Tracejada: pela metade (amarela) ou ilha, o dado existe mas não chega (laranja).</p>

  <div class="org-mapa">
    <div class="card org-desenho" data-org-desenho aria-label="Diagrama das interligações entre os setores">
      <noscript><p>O diagrama precisa de JavaScript. Veja a lista abaixo.</p></noscript>
    </div>
    <aside class="card org-detalhe" data-org-detalhe aria-live="polite">
      <div data-org-detalhe-vazio>
        <h2>Escolha um setor</h2>
        <p class="pequeno">O One liga <?= count($setores) ?> setores e sistemas. Clique num deles no diagrama para ver de onde vêm as informações que ele usa e para quem ele passa as dele.</p>
        <h3 class="org-sub">O que ainda falta ligar</h3>
        <ul class="org-lista">
          <?php foreach ($interligacoes['ligacoes'] as [$de, $para, $texto, $sit]): if ($sit === 'ok') continue; ?>
            <li class="<?= e($sit) ?>"><span class="org-chip <?= e($sit) ?>"><?= e($situacao[$sit]) ?></span>
              <strong><?= e($setores[$de]['nome']) ?> → <?= e($setores[$para]['nome']) ?></strong><br><span class="pequeno"><?= e($texto) ?></span></li>
          <?php endforeach; ?>
        </ul>
      </div>
      <div data-org-detalhe-setor hidden></div>
    </aside>
  </div>

  <details class="card org-texto">
    <summary>Ver todas as ligações em lista</summary>
    <div class="tabela-rolagem">
      <table class="tabela">
        <thead><tr><th>De</th><th>Para</th><th>O que passa</th><th>Situação</th></tr></thead>
        <tbody>
        <?php foreach ($interligacoes['ligacoes'] as [$de, $para, $texto, $sit]): ?>
          <tr><td><?= e($setores[$de]['nome']) ?></td><td><?= e($setores[$para]['nome']) ?></td><td><?= e($texto) ?></td>
            <td><span class="org-chip <?= e($sit) ?>"><?= e($situacao[$sit]) ?></span></td></tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </details>
  <script type="application/json" id="org-dados"><?= json_encode(['colunas' => $interligacoes['colunas'], 'setores' => array_values($setores),
      'ligacoes' => $interligacoes['ligacoes'], 'icones' => asset('img/icones.svg'), 'base' => url('')], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
</section>

<!-- ============================ ORGANIZAÇÃO ============================ -->
<section class="org-painel" id="organizacao" data-org-painel="organizacao">
  <p class="pequeno suave">Áreas, funções e as pessoas ligadas a cada função (uma pessoa pode estar em mais de uma).
    <span class="org-gestor-legenda">★</span> gestor(a) do setor.
    <?php if (\App\Core\Auth::pode('usuarios.gerenciar')): ?>A área de cada função se muda em <a href="<?= url('/funcoes') ?>">Funções</a>; as pessoas, em <a href="<?= url('/usuarios') ?>">Usuários</a>.<?php endif; ?></p>

  <div class="org-arvore" data-org-arvore>
    <?php $areas = $organizacao['areas']; $diretoria = $areas['diretoria'] ?? null; unset($areas['diretoria']); ?>
    <?php $bloco = function (array $a) use ($primeiroNome) { ?>
      <article class="org-area area-<?= e($a['slug']) ?>" data-org-area="<?= e($a['slug']) ?>">
        <header><h2><?= e($a['nome']) ?></h2><span class="org-area-total"><?= (int) $a['pessoas'] ?> pessoa(s)</span></header>
        <?php foreach ($a['funcoes'] as $f): $gestores = array_filter($f['pessoas'], fn($p) => (int) $p['gestor']); $equipe = array_filter($f['pessoas'], fn($p) => !(int) $p['gestor']); ?>
          <div class="org-funcao" data-org-funcao="<?= e($f['slug']) ?>">
            <div class="org-funcao-topo"><strong><?= e($f['nome']) ?></strong><?= $f['setor_venda'] ? ' <span class="org-chip ok">setor de venda</span>' : '' ?></div>
            <?php if ($f['descricao']): ?><small class="suave"><?= e($f['descricao']) ?></small><?php endif; ?>
            <?php foreach ($gestores as $p): ?>
              <a class="org-lider" href="<?= url('/pessoas/' . (int) $p['id']) ?>"><?= EquipeService::avatar($p, 40) ?><span><strong><?= e($p['nome']) ?></strong><small>★ gestor(a) do setor</small></span></a>
            <?php endforeach; ?>
            <?php if ($equipe): ?><div class="org-pessoas">
              <?php foreach ($equipe as $p): ?><a class="org-pessoa" href="<?= url('/pessoas/' . (int) $p['id']) ?>" title="<?= e($p['nome'] . ($p['perfil'] ? ' · ' . $p['perfil'] : '')) ?>"><?= EquipeService::avatar($p, 30) ?><span><?= e($primeiroNome($p)) ?></span></a><?php endforeach; ?>
            </div><?php elseif (!$gestores): ?><small class="org-vazio">ninguém nesta função ainda</small><?php endif; ?>
          </div>
        <?php endforeach; ?>
      </article>
    <?php }; ?>

    <?php if ($diretoria): ?><div class="org-topo"><?php $bloco($diretoria); ?></div><?php endif; ?>
    <div class="org-ramos">
      <?php foreach ($areas as $a): ?><div class="org-ramo"><?php $bloco($a); ?></div><?php endforeach; ?>
    </div>
  </div>

  <?php if ($organizacao['sem_funcao']): ?>
    <section class="card org-sem-funcao" data-org-sem-funcao>
      <h2>Sem função definida</h2>
      <p class="pequeno suave">Pessoas ativas que ainda não estão ligadas a nenhuma função<?= \App\Core\Auth::pode('usuarios.gerenciar') ? ' (ligue em Usuários)' : '' ?>.</p>
      <div class="org-pessoas"><?php foreach ($organizacao['sem_funcao'] as $p): ?><span class="org-pessoa" title="<?= e($p['nome'] . ($p['perfil'] ? ' · ' . $p['perfil'] : '')) ?>"><?= EquipeService::avatar($p, 30) ?><span><?= e($primeiroNome($p)) ?></span></span><?php endforeach; ?></div>
    </section>
  <?php endif; ?>
</section>
