<?php
/** Composição (técnica): ingredientes, porção, tabela nutricional, alergênicos e avisos. Só a Responsável Técnica edita. */
$f = $p['ficha'];
$tabela = $f['nutrition'] ?? [];
?>
<?php if ($podeTecnico): ?>
<form class="card pd-form" method="post" action="<?= url('/produtos/' . e($p['slug']) . '/composicao') ?>" data-composicao>
  <?= csrf_campo() ?>
  <label>Ingredientes<textarea name="ingredientes" rows="4"><?= e($f['ingredients'] ?? '') ?></textarea></label>
  <label>Cápsula / veículo<input type="text" name="capsula" maxlength="500" value="<?= e($f['capsule'] ?? '') ?>"></label>
  <div class="pd-duas">
    <label>Porção<input type="text" name="porcao" maxlength="120" value="<?= e($f['serving'] ?? '') ?>" placeholder="Ex.: 1 g – 2 cápsulas"></label>
    <label>Porções por embalagem<input type="text" name="porcoes" maxlength="120" value="<?= e($f['servings'] ?? '') ?>" placeholder="Ex.: 30 porções"></label>
  </div>
  <fieldset class="pd-nutri">
    <legend>Tabela nutricional (por porção)</legend>
    <div class="tabela-rolagem"><table class="tabela">
      <thead><tr><th>Nutriente / componente</th><th>Quantidade</th><th>%VD</th><th></th></tr></thead>
      <tbody data-nutri-linhas>
        <?php foreach ($tabela ?: [['', '', '']] as $l): ?>
          <tr><td><input type="text" name="nutriente[]" value="<?= e($l[0] ?? '') ?>" aria-label="Nutriente"></td>
            <td><input type="text" name="quantidade[]" value="<?= e($l[1] ?? '') ?>" aria-label="Quantidade" class="pd-curto"></td>
            <td><input type="text" name="vd[]" value="<?= e($l[2] ?? '') ?>" aria-label="%VD" class="pd-curto"></td>
            <td><button class="botao contorno pequeno" type="button" data-nutri-tirar aria-label="Tirar linha">✕</button></td></tr>
        <?php endforeach; ?>
      </tbody>
    </table></div>
    <button class="botao contorno pequeno" type="button" data-nutri-mais>+ Linha</button>
    <label>Nota abaixo da tabela<textarea name="nota_tabela" rows="2"><?= e($f['tableNote'] ?? '') ?></textarea></label>
  </fieldset>
  <div class="pd-duas">
    <label>Selos do rótulo <small>um por linha (ex.: Sem glúten)</small><textarea name="selos" rows="4"><?= e(implode("\n", $f['features'] ?? [])) ?></textarea></label>
    <label>Conservação<textarea name="conservacao" rows="4"><?= e($f['storage'] ?? '') ?></textarea></label>
  </div>
  <div class="pd-duas">
    <label>Alergênicos<textarea name="alergenicos" rows="3" placeholder="Ex.: Contém derivados de soja. Pode conter traços de leite."><?= e($tecnico['alergenicos'] ?? '') ?></textarea></label>
    <label>Avisos obrigatórios do rótulo<textarea name="avisos" rows="3"><?= e($tecnico['avisos'] ?? '') ?></textarea></label>
  </div>
  <div class="acoes-form"><button class="botao" type="submit">Salvar composição</button></div>
</form>
<?php else: ?>
<div class="pd-grade">
  <section class="card">
    <h2>Ingredientes</h2>
    <p><?= e($f['ingredients'] ?? '') ?: '<span class="suave">Não informado.</span>' ?></p>
    <?php if (!empty($f['capsule'])): ?><p><strong>Cápsula:</strong> <?= e($f['capsule']) ?></p><?php endif; ?>
    <?php if ($tabela): ?>
      <h3>Informação nutricional</h3>
      <p class="pequeno"><?= !empty($f['serving']) ? '<strong>Porção:</strong> ' . e($f['serving']) . ' &nbsp; ' : '' ?><?= !empty($f['servings']) ? '<strong>Rendimento:</strong> ' . e($f['servings']) : '' ?></p>
      <div class="tabela-rolagem"><table class="tabela tabela-tecnica"><thead><tr><th>Componente</th><th>Por porção</th><th>%VD</th></tr></thead>
        <tbody><?php foreach ($tabela as $l): ?><tr><td><?= e($l[0] ?? '') ?></td><td><?= e($l[1] ?? '') ?></td><td><?= e($l[2] ?? '') ?></td></tr><?php endforeach; ?></tbody></table></div>
      <?php if (!empty($f['tableNote'])): ?><p class="pequeno suave"><?= e($f['tableNote']) ?></p><?php endif; ?>
    <?php endif; ?>
  </section>
  <aside>
    <section class="card"><h3>Alergênicos</h3><p><?= e($tecnico['alergenicos'] ?? '') ?: '<span class="suave">Não informado.</span>' ?></p></section>
    <?php if (!empty($tecnico['avisos'])): ?><section class="card"><h3>Avisos do rótulo</h3><p><?= e($tecnico['avisos']) ?></p></section><?php endif; ?>
    <?php if (!empty($f['storage'])): ?><section class="card"><h3>Conservação</h3><p><?= e($f['storage']) ?></p></section><?php endif; ?>
  </aside>
</div>
<p class="pd-dica">Só a Responsável Técnica altera a composição.</p>
<?php endif; ?>
