<?php
/** Ficha do produto. Variáveis: $p, $abas, $abaProduto, $relacionados + cabeçalho */
use App\Core\Visao;
use App\Services\AcademiaService;

$linkAba = fn(string $a) => url('/academia/produtos/' . e($p['slug'])) . ($a === 'conheca' ? '' : '?aba=' . $a);
$fatos = array_filter(['Apresentação' => $p['embalagem'], 'Porção' => $p['serving'] ?? '', 'Rendimento' => $p['servings'] ?? '']);
$lista = fn(array $itens, string $classe = 'lista-aula') => '<ul class="' . $classe . '">' . implode('', array_map(fn($i) => '<li>' . e($i) . '</li>', $itens)) . '</ul>';
?>
<?= Visao::renderizar('academia/_cabecalho', get_defined_vars() + ['sobretitulo' => 'Guia de produto · ' . $p['categoria'], 'titulo_pagina' => $p['nome'], 'chamada' => '']) ?>

<a class="voltar-link" href="<?= url('/academia/produtos') ?>">← Voltar aos produtos</a>

<section class="ficha-capa" style="--cor-produto: <?= e($p['color'] ?? '#9bd344') ?>">
  <?php if ($p['imagem']): ?>
    <a class="ficha-foto" href="<?= e($p['imagem']) ?>" target="_blank" rel="noopener"><img src="<?= e($p['imagem']) ?>" alt="Embalagem de <?= e($p['nome']) ?>"><span>Ampliar foto ↗</span></a>
  <?php endif; ?>
  <div>
    <p class="ficha-chamada"><?= e($p['chamada']) ?></p>
    <?php if (!empty($p['features'])): ?><div class="chips"><?php foreach ($p['features'] as $f): ?><span class="chip"><?= e($f) ?></span><?php endforeach; ?></div><?php endif; ?>
    <?php if (!empty($p['metrics'])): ?>
      <div class="ficha-metricas"><?php foreach ($p['metrics'] as [$valor, $rotulo]): ?><div><strong><?= e($valor) ?></strong><small><?= e($rotulo) ?></small></div><?php endforeach; ?></div>
    <?php endif; ?>
  </div>
</section>

<nav class="abas abas-claras" aria-label="Informações do produto">
  <?php foreach ($abas as $chave => $nome): ?><a href="<?= $linkAba($chave) ?>" class="<?= $chave === $abaProduto ? 'ativa' : '' ?>"><?= e($nome) ?></a><?php endforeach; ?>
</nav>

<?php if ($abaProduto === 'conheca'): ?>
  <div class="grade-ficha">
    <div>
      <section class="card">
        <span class="sobretitulo">CONHECIMENTO DE PRODUTO</span>
        <h2>O que é e para que serve</h2>
        <?php foreach ($p['descriptionParagraphs'] ?? [] as $par): ?><p><?= e($par) ?></p><?php endforeach; ?>
        <?php if (!empty($p['benefits'])): ?><h3>Características e benefícios</h3><?= $lista($p['benefits'], 'lista-check') ?><?php endif; ?>
      </section>
      <?php if (!empty($p['learn'])): ?>
        <section class="card">
          <span class="sobretitulo">PARA DIFERENCIAR NA VENDA</span>
          <h2>O que destacar nesta opção</h2>
          <?= $lista($p['learn']) ?>
          <a class="botao contorno pequeno" href="<?= $linkAba('atendimento') ?>">Preparar minha abordagem →</a>
        </section>
      <?php endif; ?>
      <?php if (!empty($p['topics'])): ?>
        <section class="card">
          <h2>Temas ligados a este produto</h2>
          <dl class="temas-produto"><?php foreach ($p['topics'] as $tema => $texto): ?><div><dt><a href="<?= url('/academia/produtos') ?>?tema=<?= e($tema) ?>"><?= e(ucfirst(str_replace('-', ' ', $tema))) ?></a></dt><dd><?= e($texto) ?></dd></div><?php endforeach; ?></dl>
        </section>
      <?php endif; ?>
    </div>
    <aside>
      <section class="card">
        <h3>Resumo rápido</h3>
        <dl class="ficha-dados"><?php foreach ($fatos as $k => $v): ?><div><dt><?= e($k) ?></dt><dd><?= e($v) ?></dd></div><?php endforeach; ?></dl>
        <?php if (isset($abas['composicao'])): ?><a class="botao contorno pequeno" href="<?= $linkAba('composicao') ?>">Ver composição →</a><?php endif; ?>
      </section>
      <?php if (!empty($p['understand'])): ?><section class="card"><h3>Entenda a apresentação</h3><p><?= e($p['understand']) ?></p></section><?php endif; ?>
      <?php if (!empty($p['studyQuestion'])): ?><section class="pratica"><span class="sobretitulo">PARA MEMORIZAR</span><p><?= e($p['studyQuestion']) ?></p></section><?php endif; ?>
    </aside>
  </div>

<?php elseif ($abaProduto === 'composicao'): ?>
  <?php if (!empty($p['nutrition'])): ?>
    <section class="card">
      <h2>Informação nutricional</h2>
      <p class="pequeno"><?= !empty($p['serving']) ? '<strong>Porção:</strong> ' . e($p['serving']) . ' &nbsp; ' : '' ?><?= !empty($p['servings']) ? '<strong>Rendimento:</strong> ' . e($p['servings']) : '' ?></p>
      <div class="tabela-rolagem"><table class="tabela tabela-tecnica">
        <thead><tr><th>Componente</th><th>Por porção</th><th>%VD</th></tr></thead>
        <tbody><?php foreach ($p['nutrition'] as $linha): ?><tr><?php foreach ($linha as $celula): ?><td><?= e($celula) ?></td><?php endforeach; ?></tr><?php endforeach; ?></tbody>
      </table></div>
      <?php if (!empty($p['tableNote'])): ?><p class="pequeno suave"><?= e($p['tableNote']) ?></p><?php endif; ?>
      <p class="pequeno suave">%VD: percentual do valor diário. * ou **: valor diário não estabelecido.</p>
      <details class="faq"><summary>Como ler as quantidades</summary><p>Os valores correspondem à porção, que pode reunir uma ou mais cápsulas ou uma medida de pó. mg significa miligrama; mcg e µg significam micrograma. 1 mg = 1.000 microgramas.</p></details>
    </section>
  <?php endif; ?>
  <div class="grade-2">
    <?php if (!empty($p['ingredients'])): ?><section class="card"><h2><?= e($p['ingredientHeading'] ?? 'Ingredientes') ?></h2><p><?= e($p['ingredients']) ?></p></section><?php endif; ?>
    <?php if (!empty($p['capsule']) && $p['categoria'] === 'Cápsulas'): ?><section class="card"><h2>Composição da cápsula</h2><p><?= e($p['capsule']) ?></p></section><?php endif; ?>
  </div>

<?php elseif ($abaProduto === 'uso'): ?>
  <div class="grade-2">
    <?php if (!empty($p['use'])): ?><section class="card"><h2>Preparo e consumo</h2><p><?= e($p['use']) ?></p></section><?php endif; ?>
    <?php if (!empty($p['storage'])): ?><section class="card"><h2>Como guardar</h2><p><?= e($p['storage']) ?></p></section><?php endif; ?>
  </div>
  <?php if (!empty($p['care'])): ?><section class="card alerta-cuidado"><h2>Cuidados importantes</h2><p><?= e($p['care']) ?></p></section><?php endif; ?>

<?php elseif ($abaProduto === 'atendimento'): $s = $p['sales'] ?? []; ?>
  <?php if (!empty($s['when'])): ?><section class="nota-destaque"><strong>Quando apresentar: </strong><?= e($s['when']) ?></section><?php endif; ?>
  <div class="grade-2">
    <section class="card"><span class="sobretitulo">01 · ENTENDA A PROCURA</span><h2>Perguntas para a conversa</h2><ol class="lista-aula"><?php foreach ($s['questions'] ?? [] as $q): ?><li><?= e($q) ?></li><?php endforeach; ?></ol></section>
    <section class="card"><span class="sobretitulo">02 · CONECTE AO PRODUTO</span><h2>Pontos para destacar</h2><?= $lista($s['highlights'] ?? [], 'lista-check') ?></section>
  </div>
  <?php if (!empty($s['pitch'])): ?>
    <section class="card"><span class="sobretitulo">03 · APRESENTE COM CLAREZA</span><h2>Uma sugestão de fala</h2><?= Visao::renderizar('academia/_mensagem', ['texto' => $s['pitch']]) ?></section>
  <?php endif; ?>
  <?php if (!empty($p['faq'])): ?>
    <section class="card"><h2>Se o lojista perguntar…</h2>
      <?php foreach ($p['faq'] as [$pergunta, $resposta]): ?><details class="faq"><summary><?= e($pergunta) ?></summary><p><?= e($resposta) ?></p></details><?php endforeach; ?>
    </section>
  <?php endif; ?>
  <?php if (!empty($s['next'])): ?><section class="card"><span class="sobretitulo">04 · PRÓXIMO PASSO</span><p><?= e(is_array($s['next']) ? implode(' ', $s['next']) : $s['next']) ?></p></section><?php endif; ?>
  <?php if (!empty($p['salesScripts'])): ?>
    <section class="card"><h2>Roteiros prontos</h2>
      <?php foreach ($p['salesScripts'] as $roteiro): ?><?= Visao::renderizar('academia/_mensagem', ['titulo' => $roteiro['title'], 'dica' => $roteiro['when'] ?? '', 'texto' => $roteiro['message']]) ?><?php endforeach; ?>
    </section>
  <?php endif; ?>
  <section class="card">
    <h2>Apoio de marketing</h2>
    <?= Visao::renderizar('academia/_mensagem', ['texto' => 'Além dos produtos, a Perfeita Alquimia fornece todo o material de marketing para ajudar sua loja na divulgação de ' . $p['nome'] . '. Você pode baixar as mídias pelo nosso catálogo: abra a página do produto e clique em “Baixar imagens do produto”. No Drive, escolha entre Stories, Site- Fundo Branco, Sem fundo / PNG e Feed Instagram. Salve o link para acessar os materiais sempre que precisar: ' . AcademiaService::link('catalogo')]) ?>
  </section>

<?php elseif ($abaProduto === 'treinamento'): ?>
  <?= Visao::renderizar('academia/_treinamento_tecnico', ['p' => $p]) ?>
<?php endif; ?>

<?php if ($relacionados && in_array($abaProduto, ['conheca', 'composicao'], true)): ?>
  <section class="card">
    <span class="sobretitulo">CONTINUE A CONSULTA</span>
    <h2>Apresentações relacionadas</h2>
    <div class="grade-relacionados">
      <?php foreach ($relacionados as $r): ?>
        <a href="<?= url('/academia/produtos/' . e($r['slug'])) ?>"><?php if ($r['imagem']): ?><img src="<?= e($r['imagem']) ?>" alt="" loading="lazy"><?php endif; ?><span><strong><?= e($r['nome']) ?></strong><small><?= e($r['embalagem']) ?></small></span></a>
      <?php endforeach; ?>
    </div>
  </section>
<?php endif; ?>
