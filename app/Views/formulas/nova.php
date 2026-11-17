<?php
/** Nova fórmula: só o essencial; os ingredientes entram na tela da fórmula. Variáveis: $projeto (ou null), $produtos */
use App\Services\FormulaService as F;
?>
<a class="voltar-link" href="<?= url('/formulas') ?>">← Fórmulas</a>
<div class="cabecalho-secao"><div><div class="sobretitulo">FÓRMULAS</div><h1>Nova fórmula</h1>
  <?php if ($projeto): ?><p>Para o produto novo <strong><?= e($projeto['nome']) ?></strong> (a fórmula fica ligada ao projeto e ao produto).</p><?php endif; ?></div></div>
<form class="card fm-form" method="post" action="<?= url('/formulas') ?>" data-form-nova-formula><?= csrf_campo() ?>
  <?php if ($projeto): ?><input type="hidden" name="dp_projeto_id" value="<?= (int) $projeto['id'] ?>"><?php endif; ?>
  <label>Nome da fórmula<input type="text" name="nome" required maxlength="150" value="<?= e($projeto['nome'] ?? '') ?>" placeholder="ex.: Magnésio + B6 cápsulas"></label>
  <div class="fm-linha">
    <label>Forma<select name="forma" data-forma><?php foreach (F::FORMAS as $k => $nome): ?><option value="<?= $k ?>"><?= e($nome) ?></option><?php endforeach; ?></select></label>
    <label>Porção<input type="text" name="porcao_qtd" value="2" inputmode="decimal"></label>
    <label>Unidade da porção<select name="porcao_unidade"><?php foreach (F::PORCAO_UNIDADES as $u): ?><option><?= e($u) ?></option><?php endforeach; ?></select></label>
    <label>Porções por embalagem<input type="number" name="porcoes_embalagem" value="30" min="1"></label>
    <label>Capacidade da cápsula (mg)<input type="text" name="capacidade_capsula_mg" value="500" inputmode="decimal" title="Tamanho 00 ≈ 600-950 mg · 0 ≈ 400-680 mg · 1 ≈ 300-500 mg"></label>
  </div>
  <label>Produto (a ficha técnica é dele quando a fórmula for aprovada)
    <select name="produto_id"><option value="">— escolher depois —</option>
      <?php foreach ($produtos as $p): ?><option value="<?= (int) $p['id'] ?>" <?= (int) ($projeto['produto_id'] ?? 0) === (int) $p['id'] ? 'selected' : '' ?>><?= e($p['nome']) ?><?= $p['codigo'] ? ' (' . e($p['codigo']) . ')' : '' ?><?= $p['ativo'] ? '' : ' — produto novo' ?></option><?php endforeach; ?>
    </select></label>
  <label>Para que é / alegação pretendida<textarea name="objetivo" rows="3" placeholder="ex.: fonte de magnésio e vitamina B6; público adulto"></textarea></label>
  <button class="botao" type="submit">Criar e colocar os ingredientes</button>
</form>
