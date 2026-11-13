<?php
/** Conteúdo (o que a Academia e o catálogo mostram). Editado pelo Marketing; os demais só leem. */
$f = $p['ficha'];
$faq = implode("\n", array_map(fn($q) => ($q[0] ?? '') . ' | ' . ($q[1] ?? ''), $f['faq'] ?? []));
$lista = fn(array $itens) => '<ul class="lista-check">' . implode('', array_map(fn($i) => '<li>' . e($i) . '</li>', $itens)) . '</ul>';
?>
<p class="pd-dica">Este conteúdo aparece na <strong>Academia</strong> e no <strong>catálogo</strong> do cliente. Mudou aqui, muda lá.</p>
<?php if ($podeConteudo): ?>
<form class="card pd-form" method="post" action="<?= url('/produtos/' . e($p['slug']) . '/conteudo') ?>">
  <?= csrf_campo() ?>
  <label>Chamada (uma frase que resume o produto)<input type="text" name="chamada" maxlength="255" value="<?= e($p['chamada']) ?>"></label>
  <label>Descrição <small>parágrafos separados por uma linha em branco</small>
    <textarea name="descricao" rows="7"><?= e(implode("\n\n", $f['descriptionParagraphs'] ?? [])) ?></textarea></label>
  <label>Benefícios e características <small>um por linha</small><textarea name="beneficios" rows="4"><?= e(implode("\n", $f['benefits'] ?? [])) ?></textarea></label>
  <div class="pd-duas">
    <label>Modo de uso<textarea name="uso" rows="3"><?= e($f['use'] ?? '') ?></textarea></label>
    <label>Cuidados e público<textarea name="cuidados" rows="3"><?= e($f['care'] ?? '') ?></textarea></label>
  </div>
  <label>Perguntas frequentes <small>uma por linha: Pergunta? | Resposta</small><textarea name="faq" rows="5"><?= e($faq) ?></textarea></label>
  <div class="pd-duas">
    <label>Argumento de venda<textarea name="argumento" rows="4"><?= e($f['sales']['pitch'] ?? '') ?></textarea></label>
    <label>Destaques para a venda <small>um por linha</small><textarea name="destaques" rows="4"><?= e(implode("\n", $f['sales']['highlights'] ?? [])) ?></textarea></label>
  </div>
  <div class="acoes-form"><button class="botao" type="submit">Salvar conteúdo</button></div>
</form>
<?php else: ?>
<div class="pd-grade">
  <section class="card">
    <h2>Descrição</h2>
    <?php foreach ($f['descriptionParagraphs'] ?? [] as $par): ?><p><?= e($par) ?></p><?php endforeach; ?>
    <?php if (!empty($f['benefits'])): ?><h3>Benefícios</h3><?= $lista($f['benefits']) ?><?php endif; ?>
    <?php if (!empty($f['use'])): ?><h3>Modo de uso</h3><p><?= e($f['use']) ?></p><?php endif; ?>
    <?php if (!empty($f['care'])): ?><h3>Cuidados</h3><p><?= e($f['care']) ?></p><?php endif; ?>
  </section>
  <aside>
    <?php if (!empty($f['sales']['pitch'])): ?><section class="card"><h3>Argumento de venda</h3><p><?= e($f['sales']['pitch']) ?></p></section><?php endif; ?>
    <?php if (!empty($f['faq'])): ?><section class="card"><h3>Perguntas frequentes</h3>
      <?php foreach ($f['faq'] as [$q, $r]): ?><details class="faq"><summary><?= e($q) ?></summary><p><?= e($r) ?></p></details><?php endforeach; ?></section><?php endif; ?>
  </aside>
</div>
<?php endif; ?>
