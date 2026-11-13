<?php
/** Lista de produtos com a ficha técnica. Variáveis: $busca, $filtro, $itens */
use App\Core\Visao;

$icones = asset('img/icones.svg');
?>
<div class="cabecalho-secao">
  <div><div class="sobretitulo">PRODUÇÃO · FICHAS TÉCNICAS</div><h1>Fichas técnicas</h1>
    <p>O que vai em 1 unidade de cada produto. A ordem de produção usa a ficha para baixar as matérias-primas.</p></div>
</div>
<?= Visao::renderizar('producao/_abas', ['atual' => '/producao/fichas']) ?>
<form class="filtros card" method="get" action="<?= url('/producao/fichas') ?>" data-filtros>
  <div class="filtros-busca"><svg class="icone"><use href="<?= $icones ?>#lupa"/></svg>
    <input type="search" name="q" value="<?= e($busca) ?>" placeholder="Nome do produto…" aria-label="Buscar"></div>
  <div class="filtros-grade">
    <select name="ficha" aria-label="Ficha"><option value="">Todos os produtos</option>
      <option value="com" <?= $filtro === 'com' ? 'selected' : '' ?>>Com ficha</option><option value="sem" <?= $filtro === 'sem' ? 'selected' : '' ?>>Sem ficha</option></select>
  </div>
</form>
<section class="card sem-espaco">
  <div class="tabela-rolagem">
    <table class="tabela tabela-carteira pr-tabela">
      <thead><tr><th>Produto</th><th>Categoria</th><th class="direita">Matérias-primas</th><th class="direita">Custo de MP por unidade</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($itens as $p): ?>
        <tr>
          <td><a href="<?= url('/producao/fichas/' . (int) $p['id']) ?>"><strong><?= e($p['nome']) ?></strong></a><small class="suave"><?= e($p['embalagem'] ?? '') ?></small></td>
          <td class="pequeno"><?= e($p['categoria'] ?? '—') ?></td>
          <td class="direita"><?= (int) $p['itens'] ?: '<span class="selo cinza">sem ficha</span>' ?></td>
          <td class="direita"><?= $p['itens'] ? dinheiro((float) $p['custo']) : '—' ?></td>
          <td class="direita"><a class="botao contorno pequeno" href="<?= url('/producao/fichas/' . (int) $p['id']) ?>"><?= $p['itens'] ? 'Abrir' : 'Cadastrar ficha' ?></a></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$itens): ?><tr><td colspan="5" class="vazio">Nenhum produto encontrado.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
  <div class="paginacao-barra"><span><?= count($itens) ?> produto(s) · <?= count(array_filter($itens, fn($p) => $p['itens'] > 0)) ?> com ficha</span></div>
</section>
