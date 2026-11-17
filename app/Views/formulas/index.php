<?php
/**
 * Fórmulas: lista (última versão de cada fórmula) com forma, porção, custo por embalagem e situação.
 * Variáveis: $lista, $filtros
 */
use App\Core\Auth;
use App\Services\FormulaService as F;

$n = fn($v, $c = 1) => rtrim(rtrim(number_format((float) $v, $c, ',', '.'), '0'), ',');
?>
<div class="cabecalho-secao">
  <div><div class="sobretitulo">PRODUTOS · RESPONSÁVEL TÉCNICA</div><h1>Fórmulas</h1>
    <p>Monte e teste as fórmulas: ingredientes por porção, custo, cápsulas e estoque para o lote piloto. A fórmula aprovada vira a ficha técnica do produto.</p></div>
  <div class="acoes"><?php if (Auth::pode('formulas.editar')): ?><a class="botao" href="<?= url('/formulas/nova') ?>" data-nova-formula>+ Nova fórmula</a><?php endif; ?></div>
</div>
<form class="fm-filtros" method="get" action="<?= url('/formulas') ?>">
  <input type="search" name="busca" value="<?= e($filtros['busca']) ?>" placeholder="Nome ou código (F-0001)" aria-label="Buscar">
  <select name="situacao" aria-label="Situação"><option value="">Todas as situações</option>
    <?php foreach (F::SITUACOES as $k => [$nome]): ?><option value="<?= $k ?>" <?= $filtros['situacao'] === $k ? 'selected' : '' ?>><?= e($nome) ?></option><?php endforeach; ?></select>
  <select name="forma" aria-label="Forma"><option value="">Todas as formas</option>
    <?php foreach (F::FORMAS as $k => $nome): ?><option value="<?= $k ?>" <?= $filtros['forma'] === $k ? 'selected' : '' ?>><?= e($nome) ?></option><?php endforeach; ?></select>
  <label class="pequeno"><input type="checkbox" name="projeto" value="1" <?= $filtros['projeto'] ? 'checked' : '' ?>> ligadas a produto novo</label>
  <button class="botao pequeno" type="submit">Filtrar</button>
</form>
<?php if (!$lista): ?>
  <section class="card fm-vazio"><p>Nenhuma fórmula ainda. <?= Auth::pode('formulas.editar') ? 'Clique em <strong>+ Nova fórmula</strong> para começar.' : '' ?></p></section>
<?php endif; ?>
<div class="fm-grade" data-formulas>
  <?php foreach ($lista as $f): $c = $f['calculo']; [$sit, $cor] = F::SITUACOES[$f['situacao']] ?? [$f['situacao'], 'cinza']; ?>
    <a class="card fm-cartao" href="<?= url('/formulas/' . (int) $f['id']) ?>" data-formula="<?= e($f['codigo']) ?>">
      <header><span class="fm-codigo"><?= e($f['codigo']) ?> · v<?= (int) $f['versao'] ?></span><span class="selo <?= $cor ?>"><?= e($sit) ?></span></header>
      <strong><?= e($f['nome']) ?></strong>
      <small><?= e(F::FORMAS[$f['forma']] ?? $f['forma']) ?> · porção <?= $n($f['porcao_qtd'], 3) ?> <?= e($f['porcao_unidade']) ?> · <?= (int) $f['porcoes_embalagem'] ?> por embalagem</small>
      <small><?= count($c['itens']) ?> ingrediente(s) · <?= $n($c['massa_porcao_mg'], 0) ?> mg por porção<?= $f['produto'] ? ' · ' . e($f['produto']) : '' ?></small>
      <span class="fm-custo"><?= dinheiro($c['custo_embalagem']) ?> <small>por embalagem</small></span>
      <?php if ($c['a_cadastrar'] || $c['cabe'] === false): ?><small class="fm-alerta">⚠️ <?= $c['cabe'] === false ? 'não cabe na cápsula' : count($c['a_cadastrar']) . ' ingrediente(s) a cadastrar' ?></small><?php endif; ?>
    </a>
  <?php endforeach; ?>
</div>
