<?php
/** Catálogo de produtos da Academia. Variáveis: $lista, $total, $pagina, $paginas, $q, $categoria, $tema, $categorias, $temas + cabeçalho */
use App\Core\Visao;

$parametros = array_filter(['q' => $q, 'categoria' => $categoria, 'tema' => $tema['id'] ?? '']);
$link = fn(array $extra) => url('/academia/produtos') . '?' . e(http_build_query(array_filter($extra + $parametros)));
?>
<?= Visao::renderizar('academia/_cabecalho', get_defined_vars() + ['sobretitulo' => 'Sua biblioteca de consulta', 'titulo_pagina' => 'Conheça os produtos', 'chamada' => 'Pesquise pelo nome, ingrediente ou necessidade. Abra a ficha para entender as diferenças.']) ?>

<form class="card filtros-produtos" method="get" action="<?= url('/academia/produtos') ?>">
  <div class="filtros-busca">
    <svg class="icone"><use href="<?= asset('img/icones.svg') ?>#lupa"/></svg>
    <input type="search" name="q" value="<?= e($q) ?>" placeholder="Ex.: 8+ magnésio, emagrecimento, digestão…" aria-label="O que você procura?">
  </div>
  <select name="categoria" aria-label="Apresentação" onchange="this.form.submit()">
    <option value="">Todas as apresentações</option>
    <?php foreach ($categorias as $cat => $qtd): ?><option value="<?= e($cat) ?>" <?= $cat === $categoria ? 'selected' : '' ?>><?= e($cat) ?> (<?= (int) $qtd ?>)</option><?php endforeach; ?>
  </select>
  <button class="botao" type="submit">Buscar</button>
  <?php if ($parametros): ?><a class="botao contorno" href="<?= url('/academia/produtos') ?>">Limpar</a><?php endif; ?>
</form>

<div class="chips" style="margin-bottom:16px">
  <?php foreach ($temas as $t): ?>
    <a class="chip <?= ($tema['id'] ?? '') === $t['id'] ? 'ativo' : '' ?>" href="<?= url('/academia/produtos') ?>?<?= e(http_build_query(array_filter(['tema' => $t['id'], 'categoria' => $categoria]))) ?>"><?= e($t['label']) ?></a>
  <?php endforeach; ?>
</div>

<p class="suave pequeno">
  <?= (int) $total ?> produto(s)<?= $tema ? ' para <strong>' . e($tema['label']) . '</strong>' : '' ?><?= $categoria ? ' em ' . e($categoria) : '' ?>.
  <?php if ($tema): ?>A busca por necessidade usa os temas cadastrados em cada ficha.<?php endif; ?>
</p>

<div class="grade-produtos">
  <?php foreach ($lista as $p): ?>
    <a class="cartao-produto" href="<?= url('/academia/produtos/' . e($p['slug'])) ?>" style="--cor-produto: <?= e($p['color'] ?? '#9bd344') ?>">
      <span class="foto"><?php if ($p['imagem']): ?><img src="<?= e($p['imagem']) ?>" alt="Embalagem de <?= e($p['nome']) ?>" loading="lazy"><?php else: ?><b>PA</b><?php endif; ?></span>
      <span class="corpo">
        <small><?= e($p['categoria']) ?> · <?= e($p['embalagem']) ?></small>
        <strong><?= e($p['nome']) ?></strong>
        <span class="chamada"><?= e($tema && !empty($p['topics'][$tema['id']]) ? $p['topics'][$tema['id']] : $p['chamada']) ?></span>
      </span>
    </a>
  <?php endforeach; ?>
  <?php if (!$lista): ?><p class="card vazio">Nenhum produto encontrado. Tente outra palavra ou limpe os filtros.</p><?php endif; ?>
</div>

<?php if ($paginas > 1): ?>
  <nav class="paginacao" aria-label="Páginas">
    <?php if ($pagina > 1): ?><a class="botao contorno pequeno" href="<?= $link(['pagina' => $pagina - 1]) ?>">‹ Anterior</a><?php endif; ?>
    <span class="pequeno">Página <?= $pagina ?> de <?= $paginas ?></span>
    <?php if ($pagina < $paginas): ?><a class="botao contorno pequeno" href="<?= $link(['pagina' => $pagina + 1]) ?>">Próxima ›</a><?php endif; ?>
  </nav>
<?php endif; ?>
