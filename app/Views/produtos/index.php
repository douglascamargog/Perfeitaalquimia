<?php
/** Produtos (equipe): cartões com os selos de situação. Variáveis: $filtros, $produtos, $contagens, $categorias */
use App\Services\ProdutoService as PS;

$icones = asset('img/icones.svg');
$link = fn(array $extra) => url('/produtos') . '?' . e(http_build_query(array_filter($extra + $filtros)));
?>
<div class="cabecalho-secao">
  <div><div class="sobretitulo">CONTEÚDO · PRODUTOS</div><h1>Produtos</h1>
    <p>Tudo sobre cada produto: conteúdo, composição, ficha técnica, embalagens e documentos. A vitrine do cliente fica no <a href="<?= url('/produtos/catalogo') ?>">Catálogo</a>.</p></div>
</div>

<form class="card filtros-produtos" method="get" action="<?= url('/produtos') ?>">
  <div class="filtros-busca">
    <svg class="icone"><use href="<?= $icones ?>#lupa"/></svg>
    <input type="search" name="q" value="<?= e($filtros['q']) ?>" placeholder="Nome ou código do produto" aria-label="Buscar produto">
  </div>
  <select name="categoria" aria-label="Apresentação" onchange="this.form.submit()">
    <option value="">Todas as apresentações</option>
    <?php foreach ($categorias as $c): ?><option value="<?= (int) $c['id'] ?>" <?= (int) $c['id'] === $filtros['categoria'] ? 'selected' : '' ?>><?= e($c['nome']) ?></option><?php endforeach; ?>
  </select>
  <?php if ($filtros['situacao']): ?><input type="hidden" name="situacao" value="<?= e($filtros['situacao']) ?>"><?php endif; ?>
  <button class="botao" type="submit">Buscar</button>
  <?php if (array_filter($filtros)): ?><a class="botao contorno" href="<?= url('/produtos') ?>">Limpar</a><?php endif; ?>
</form>

<div class="chips pd-situacoes" aria-label="Filtrar por situação">
  <a class="chip <?= $filtros['situacao'] === '' ? 'ativo' : '' ?>" href="<?= $link(['situacao' => '']) ?>">Todos</a>
  <?php foreach (PS::SITUACOES as $chave => $nome): ?>
    <a class="chip <?= $filtros['situacao'] === $chave ? 'ativo' : '' ?> <?= $chave === 'vencendo' && $contagens[$chave] ? 'alerta' : '' ?>" href="<?= $link(['situacao' => $chave]) ?>"><?= e($nome) ?> <b><?= (int) $contagens[$chave] ?></b></a>
  <?php endforeach; ?>
</div>

<p class="suave pequeno"><?= count($produtos) ?> produto(s).</p>

<?php if (!$produtos): ?>
  <div class="card vazio"><p>Nenhum produto com esses filtros.</p></div>
<?php else: ?>
<div class="grade-produtos">
  <?php foreach ($produtos as $p): ?>
    <a class="cartao-produto pd-cartao" href="<?= url('/produtos/' . e($p['slug'])) ?>">
      <span class="foto"><?php if ($p['imagem']): ?><img src="<?= url('/' . e($p['imagem'])) ?>" alt="Embalagem de <?= e($p['nome']) ?>" loading="lazy"><?php else: ?><b>PA</b><?php endif; ?></span>
      <span class="corpo">
        <small><?= $p['codigo'] ? '<span class="cod-produto">' . e($p['codigo']) . '</span> ' : '' ?><?= e($p['categoria']) ?><?= $p['embalagem'] ? ' · ' . e($p['embalagem']) : '' ?></small>
        <strong><?= e($p['nome']) ?></strong>
        <span class="pd-selos">
          <span class="pd-selo <?= $p['visivel_catalogo'] ? 'ok' : 'cinza' ?>"><?= $p['visivel_catalogo'] ? 'No catálogo' : 'Oculto' ?></span>
          <span class="pd-selo <?= $p['itens_ficha'] ? 'ok' : 'falta' ?>"><?= $p['itens_ficha'] ? 'Ficha técnica' : 'Sem ficha' ?></span>
          <?php if ($p['documentos']): ?><span class="pd-selo <?= $p['vencendo'] ? 'falta' : 'ok' ?>"><?= (int) $p['documentos'] ?> doc.<?= $p['vencendo'] ? ' · vencendo' : '' ?></span><?php endif; ?>
          <?php if (!$p['precos']): ?><span class="pd-selo falta">Sem preço</span><?php endif; ?>
        </span>
      </span>
    </a>
  <?php endforeach; ?>
</div>
<?php endif; ?>
