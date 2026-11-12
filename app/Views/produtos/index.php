<?php
/** Produtos (catálogo). Variáveis: $busca, $tabelas, $produtos */
$icones = asset('img/icones.svg');
?>
<div class="cabecalho-secao">
  <div><div class="sobretitulo">CONTEÚDO · PRODUTOS</div><h1>Produtos</h1>
    <p>Mesmo cadastro da Academia e do catálogo. Preços e estoque vêm do ERP (somente leitura). Aqui você escolhe o que aparece no catálogo.</p></div>
  <div class="acoes">
    <form method="post" action="<?= url('/produtos/sincronizar') ?>"><?= csrf_campo() ?><button class="botao contorno" type="submit"><svg class="icone"><use href="<?= $icones ?>#nuvem"/></svg>Atualizar preços do ERP</button></form>
    <a class="botao contorno" href="<?= url('/catalogo') ?>" target="_blank" rel="noopener">Ver catálogo</a>
  </div>
</div>
<form class="card ped-busca" method="get" action="<?= url('/produtos') ?>">
  <input type="search" name="q" value="<?= e($busca) ?>" placeholder="Buscar produto…" aria-label="Buscar produto"><button class="botao" type="submit">Buscar</button>
</form>
<section class="card sem-espaco">
  <div class="tabela-rolagem">
    <table class="tabela">
      <thead><tr><th>Produto</th><?php foreach ($tabelas as $t): ?><th class="direita"><?= e($t['nome']) ?></th><?php endforeach; ?><th class="direita">Estoque</th><th>Catálogo</th><th>Chamada e ordem</th></tr></thead>
      <tbody>
      <?php foreach ($produtos as $p): $form = 'prod-' . (int) $p['id']; ?>
        <tr id="produto-<?= (int) $p['id'] ?>" class="<?= $p['visivel_catalogo'] ? '' : 'inativo' ?>">
          <td><div class="ped-produto"><?php if ($p['imagem']): ?><img src="<?= url('/' . $p['imagem']) ?>" alt="" width="40" height="40" loading="lazy"><?php endif; ?><span><strong><?= e($p['nome']) ?></strong><small><?= e($p['categoria']) ?> · <?= e($p['embalagem']) ?></small></span></div></td>
          <?php foreach ($tabelas as $t): ?><td class="direita"><?= isset($p['precos'][$t['id']]) ? dinheiro($p['precos'][$t['id']]) : '<span class="selo laranja">sem preço</span>' ?></td><?php endforeach; ?>
          <td class="direita"><?= $p['estoque'] !== null ? (int) $p['estoque'] : '—' ?></td>
          <td><label class="opcao"><input type="checkbox" name="visivel_catalogo" value="1" form="<?= $form ?>" <?= $p['visivel_catalogo'] ? 'checked' : '' ?>> Visível</label></td>
          <td>
            <form id="<?= $form ?>" method="post" action="<?= url('/produtos/' . (int) $p['id']) ?>" class="ped-produto-form">
              <?= csrf_campo() ?><input type="hidden" name="q" value="<?= e($busca) ?>">
              <input type="text" name="chamada" value="<?= e($p['chamada']) ?>" maxlength="255" aria-label="Chamada de <?= e($p['nome']) ?>">
              <input type="number" name="ordem" value="<?= (int) $p['ordem'] ?>" aria-label="Ordem" style="width:70px">
              <button class="botao contorno pequeno" type="submit">Salvar</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>
