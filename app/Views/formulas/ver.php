<?php
/**
 * Editor da fórmula: ingredientes por porção (ou por embalagem), contas na hora (massa, %, cápsulas, custo, estoque),
 * versões, testes, histórico e aprovação (vira a ficha técnica do produto).
 * Variáveis: $f, $calc, $mps, $produtos, $versoes, $testes, $historico, $pendencias, $podeEditar
 */
use App\Core\Auth;
use App\Services\FormulaService as F;

$n = fn($v, $c = 2) => $v === null ? '—' : rtrim(rtrim(number_format((float) $v, $c, ',', '.'), '0'), ',');
[$sit, $cor] = F::SITUACOES[$f['situacao']] ?? [$f['situacao'], 'cinza'];
$dis = $podeEditar ? '' : 'disabled';
$linha = function (array $i, int $k) use ($mps, $n, $dis): string {
    ob_start(); ?>
    <tr data-item>
      <td>
        <select name="itens[<?= $k ?>][materia_prima_id]" data-item-mp aria-label="Matéria-prima" <?= $dis ?>>
          <option value="">— não cadastrada —</option>
          <?php foreach ($mps as $m): ?><option value="<?= (int) $m['id'] ?>" <?= (int) ($i['materia_prima_id'] ?? 0) === (int) $m['id'] ? 'selected' : '' ?>><?= e($m['nome']) ?><?= $m['codigo'] ? ' · ' . e($m['codigo']) : '' ?></option><?php endforeach; ?>
        </select>
        <input type="text" name="itens[<?= $k ?>][nome_livre]" value="<?= e((string) ($i['nome_livre'] ?? '')) ?>" placeholder="nome (se ainda não cadastrada)" data-item-livre maxlength="150" <?= $dis ?>>
      </td>
      <td><input type="text" name="itens[<?= $k ?>][quantidade]" value="<?= isset($i['quantidade']) ? e($n($i['quantidade'], 6)) : '' ?>" inputmode="decimal" data-item-qtd aria-label="Quantidade" <?= $dis ?>></td>
      <td><select name="itens[<?= $k ?>][unidade]" data-item-unidade aria-label="Unidade" <?= $dis ?>><?php foreach (F::UNIDADES as $u => $nome): ?><option <?= ($i['unidade'] ?? 'mg') === $u ? 'selected' : '' ?>><?= $u ?></option><?php endforeach; ?></select></td>
      <td><select name="itens[<?= $k ?>][base]" data-item-base aria-label="Por" <?= $dis ?>><option value="porcao" <?= ($i['base'] ?? 'porcao') === 'porcao' ? 'selected' : '' ?>>porção</option><option value="embalagem" <?= ($i['base'] ?? '') === 'embalagem' ? 'selected' : '' ?>>embalagem</option></select></td>
      <td><select name="itens[<?= $k ?>][funcao]" data-item-funcao aria-label="Função" <?= $dis ?>><?php foreach (F::FUNCOES as $fn => $nome): ?><option value="<?= $fn ?>" <?= ($i['funcao'] ?? 'ativo') === $fn ? 'selected' : '' ?>><?= e($nome) ?></option><?php endforeach; ?></select></td>
      <td class="direita" data-item-pct><?= isset($i['pct']) && $i['pct'] !== null ? $n($i['pct'], 1) . '%' : '—' ?></td>
      <td class="direita" data-item-custo><?= isset($i['custo_embalagem']) && $i['custo_embalagem'] !== null ? dinheiro((float) $i['custo_embalagem']) : '—' ?></td>
      <td><?php if (!$dis): ?><button type="button" class="botao-icone" data-item-tirar aria-label="Tirar ingrediente">×</button><?php endif; ?></td>
    </tr>
    <?php return (string) ob_get_clean();
};
?>
<a class="voltar-link" href="<?= url('/formulas') ?>">← Fórmulas</a>
<div class="cabecalho-secao">
  <div><div class="sobretitulo">FÓRMULA <?= e($f['codigo']) ?> · VERSÃO <?= (int) $f['versao'] ?></div>
    <h1><?= e($f['nome']) ?> <span class="selo <?= $cor ?>" data-situacao><?= e($sit) ?></span></h1>
    <p><?= $f['produto'] ? 'Produto: <a href="' . url('/producao/fichas') . '">' . e($f['produto']) . '</a>' : 'Ainda sem produto ligado' ?><?= $f['projeto'] ? ' · Produto novo: <a href="' . url('/desenvolvimento/' . (int) $f['dp_projeto_id']) . '">' . e($f['projeto']) . '</a>' : '' ?>
      <?= $f['aprovado_em'] ? ' · aprovada por ' . e((string) $f['aprovador']) . ' em ' . data_br($f['aprovado_em']) : '' ?></p></div>
  <div class="acoes">
    <button class="botao contorno" type="button" data-perguntar-especialista>🧪 Perguntar ao especialista</button>
    <?php if (Auth::pode('formulas.editar')): ?>
      <form method="post" action="<?= url('/formulas/' . (int) $f['id'] . '/versao') ?>"><?= csrf_campo() ?><button class="botao contorno" type="submit" data-nova-versao>Nova versão</button></form>
    <?php endif; ?>
    <?php if (count($versoes) > 1): ?><a class="botao contorno" href="<?= url('/formulas/' . (int) $f['id'] . '/comparar') ?>?com=<?= (int) ($f['formula_origem_id'] ?: $versoes[1]['id']) ?>" data-comparar>Comparar versões</a><?php endif; ?>
    <button class="botao contorno" type="button" onclick="window.print()">Imprimir</button>
  </div>
</div>

<div class="fm-layout">
  <form class="card fm-editor" method="post" action="<?= url('/formulas/' . (int) $f['id']) ?>" data-form-formula><?= csrf_campo() ?>
    <div class="fm-linha">
      <label>Nome<input type="text" name="nome" value="<?= e($f['nome']) ?>" maxlength="150" <?= $dis ?>></label>
      <label>Forma<select name="forma" data-forma <?= $dis ?>><?php foreach (F::FORMAS as $k => $nome): ?><option value="<?= $k ?>" <?= $f['forma'] === $k ? 'selected' : '' ?>><?= e($nome) ?></option><?php endforeach; ?></select></label>
      <label>Porção<input type="text" name="porcao_qtd" value="<?= e($n($f['porcao_qtd'], 3)) ?>" inputmode="decimal" data-porcao <?= $dis ?>></label>
      <label>Unidade<select name="porcao_unidade" data-porcao-unidade <?= $dis ?>><?php foreach (F::PORCAO_UNIDADES as $u): ?><option <?= $f['porcao_unidade'] === $u ? 'selected' : '' ?>><?= e($u) ?></option><?php endforeach; ?></select></label>
      <label>Porções/embalagem<input type="number" name="porcoes_embalagem" value="<?= (int) $f['porcoes_embalagem'] ?>" min="1" data-porcoes <?= $dis ?>></label>
      <label>Cápsula (mg)<input type="text" name="capacidade_capsula_mg" value="<?= e($f['capacidade_capsula_mg'] ? $n($f['capacidade_capsula_mg'], 2) : '') ?>" inputmode="decimal" data-capacidade <?= $dis ?>></label>
      <label>Lote piloto (un)<input type="number" name="lote_piloto" value="<?= (int) $f['lote_piloto'] ?>" min="1" data-lote <?= $dis ?>></label>
    </div>
    <label>Produto
      <select name="produto_id" <?= $dis ?>><option value="">— sem produto —</option>
        <?php foreach ($produtos as $p): ?><option value="<?= (int) $p['id'] ?>" <?= (int) $f['produto_id'] === (int) $p['id'] ? 'selected' : '' ?>><?= e($p['nome']) ?><?= $p['codigo'] ? ' (' . e($p['codigo']) . ')' : '' ?><?= $p['ativo'] ? '' : ' — produto novo' ?></option><?php endforeach; ?>
      </select></label>

    <h2 class="fm-titulo">Ingredientes</h2>
    <div class="tabela-rolagem">
      <table class="tabela fm-itens" data-itens>
        <thead><tr><th>Matéria-prima</th><th>Quantidade</th><th>Unid.</th><th>Por</th><th>Função</th><th class="direita">% porção</th><th class="direita">Custo/emb.</th><th></th></tr></thead>
        <tbody data-itens-corpo>
          <?php foreach ($calc['itens'] as $k => $i) { echo $linha($i, $k); } ?>
          <?php if (!$calc['itens'] && $podeEditar) { echo $linha([], 0); } ?>
        </tbody>
      </table>
    </div>
    <?php if ($podeEditar): ?>
      <template data-item-modelo><?= $linha([], 999) ?></template>
      <button type="button" class="botao contorno pequeno" data-item-mais>+ Ingrediente</button>
    <?php endif; ?>
    <label>Para que é / alegação pretendida<textarea name="objetivo" rows="2" <?= $dis ?>><?= e((string) $f['objetivo']) ?></textarea></label>
    <label>Observações (modo de preparo, ordem de mistura, cuidados)<textarea name="observacoes" rows="3" <?= $dis ?>><?= e((string) $f['observacoes']) ?></textarea></label>
    <?php if ($podeEditar): ?><button class="botao" type="submit" data-salvar-formula>Salvar fórmula</button>
    <?php else: ?><p class="pequeno suave">Esta versão está <?= e(mb_strtolower($sit)) ?> e não muda. Para alterar, crie uma <strong>nova versão</strong>.</p><?php endif; ?>
  </form>

  <aside class="fm-lado">
    <section class="card fm-resumo" data-resumo>
      <h2>Contas da fórmula</h2>
      <dl>
        <dt>Massa da porção</dt><dd data-r-massa><?= $n($calc['massa_porcao_mg'], 1) ?> mg</dd>
        <dt>Por cápsula / unidade</dt><dd data-r-dose><?= $calc['mg_por_unidade_dose'] !== null ? $n($calc['mg_por_unidade_dose'], 1) . ' mg' : '—' ?></dd>
        <dt>Ocupação da cápsula</dt><dd data-r-ocupacao class="<?= $calc['cabe'] === false ? 'fm-ruim' : '' ?>"><?= $calc['ocupacao_capsula_pct'] !== null ? $n($calc['ocupacao_capsula_pct'], 0) . '%' . ($calc['cabe'] === false ? ' — não cabe' : '') : '—' ?></dd>
        <dt>Custo por porção</dt><dd data-r-custo-porcao><?= dinheiro($calc['custo_porcao']) ?></dd>
        <dt class="forte">Custo por embalagem</dt><dd class="forte" data-r-custo-emb><?= dinheiro($calc['custo_embalagem']) ?></dd>
        <dt>Lote piloto (<?= (int) $f['lote_piloto'] ?> un)</dt><dd data-r-custo-lote><?= dinheiro($calc['custo_lote']) ?></dd>
      </dl>
      <?php if ($calc['capsulas_necessarias'] && $calc['cabe'] === false): ?><p class="fm-alerta">⚠️ A porção pede <?= (int) $calc['capsulas_necessarias'] ?> cápsula(s) deste tamanho.</p><?php endif; ?>
      <?php if ($calc['faltas']): ?><div class="fm-alerta" data-r-faltas><strong>Falta estoque para o lote piloto:</strong>
        <ul><?php foreach ($calc['faltas'] as $x): ?><li><?= e($x['nome']) ?>: precisa <?= $n($x['precisa'], 3) ?> <?= e($x['unidade']) ?>, tem <?= $n($x['tem'], 3) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
      <?php if ($calc['a_cadastrar']): ?><p class="fm-alerta">📝 A cadastrar em Matérias-primas: <?= e(implode(', ', $calc['a_cadastrar'])) ?>.</p><?php endif; ?>
      <?php if ($calc['sem_custo']): ?><p class="pequeno suave">Sem custo cadastrado: <?= e(implode(', ', $calc['sem_custo'])) ?>.</p><?php endif; ?>
      <p class="pequeno suave">As contas mudam na hora enquanto você edita; o valor oficial é o salvo.</p>
    </section>

    <section class="card fm-acoes">
      <h2>Situação: <?= e($sit) ?></h2>
      <?php if (Auth::pode('formulas.editar') && $f['situacao'] === 'rascunho'): ?>
        <form method="post" action="<?= url('/formulas/' . (int) $f['id'] . '/situacao') ?>"><?= csrf_campo() ?><input type="hidden" name="situacao" value="em_teste"><button class="botao contorno pequeno" type="submit">Passar para "Em teste"</button></form>
      <?php endif; ?>
      <?php if (Auth::pode('formulas.aprovar') && in_array($f['situacao'], ['rascunho', 'em_teste'], true)): ?>
        <?php if ($pendencias): ?><ul class="fm-pendencias" data-pendencias><?php foreach ($pendencias as $x): ?><li><?= e($x) ?></li><?php endforeach; ?></ul><?php endif; ?>
        <form method="post" action="<?= url('/formulas/' . (int) $f['id'] . '/aprovar') ?>"><?= csrf_campo() ?>
          <button class="botao lima" type="submit" data-aprovar <?= $pendencias ? 'disabled' : '' ?>
            data-confirmar="Aprovar a fórmula? Ela vira a ficha técnica de <?= e((string) $f['produto']) ?> (a ficha atual fica guardada no histórico) e a Produção é avisada.">✓ Aprovar e gerar a ficha técnica</button></form>
      <?php endif; ?>
      <?php if (Auth::pode('formulas.editar') && $f['situacao'] !== 'arquivada'): ?>
        <form method="post" action="<?= url('/formulas/' . (int) $f['id'] . '/situacao') ?>"><?= csrf_campo() ?><input type="hidden" name="situacao" value="arquivada">
          <button class="botao contorno pequeno" type="submit" data-confirmar="Arquivar esta versão?">Arquivar</button></form>
      <?php endif; ?>
      <h3>Versões</h3>
      <ul class="fm-versoes"><?php foreach ($versoes as $v): ?>
        <li><a href="<?= url('/formulas/' . (int) $v['id']) ?>" <?= (int) $v['id'] === (int) $f['id'] ? 'aria-current="page"' : '' ?>>v<?= (int) $v['versao'] ?></a> · <?= e(F::SITUACOES[$v['situacao']][0] ?? $v['situacao']) ?> <small class="suave"><?= data_br($v['atualizado_em']) ?></small></li>
      <?php endforeach; ?></ul>
    </section>
  </aside>
</div>

<section class="card" id="testes" data-testes>
  <h2>Testes e lote piloto</h2>
  <?php if (Auth::pode('formulas.editar') && $f['situacao'] !== 'arquivada'): ?>
    <form class="fm-linha" method="post" action="<?= url('/formulas/' . (int) $f['id'] . '/teste') ?>"><?= csrf_campo() ?>
      <label>Data<input type="date" name="data" value="<?= date('Y-m-d') ?>"></label>
      <label>Tamanho (un)<input type="number" name="lote_tamanho" min="1"></label>
      <label>Resultado<select name="resultado"><option value="ajustar">Precisa ajustar</option><option value="aprovado">Aprovado</option><option value="reprovado">Reprovado</option></select></label>
      <label style="flex:3">Observações<input type="text" name="observacoes" maxlength="4000" placeholder="aspecto, sabor, dissolução, encapsulamento..."></label>
      <button class="botao pequeno" type="submit" data-registrar-teste>Registrar teste</button>
    </form>
  <?php endif; ?>
  <?php if (!$testes): ?><p class="pequeno suave">Nenhum teste registrado.</p><?php endif; ?>
  <ul class="fm-testes"><?php foreach ($testes as $t): ?>
    <li><strong><?= data_br($t['data']) ?></strong> · <?= ['aprovado' => '✅ aprovado', 'reprovado' => '❌ reprovado', 'ajustar' => '🔧 ajustar'][$t['resultado']] ?? e($t['resultado']) ?>
      <?= $t['lote_tamanho'] ? ' · ' . (int) $t['lote_tamanho'] . ' un' : '' ?> · <?= e((string) $t['observacoes']) ?> <small class="suave">— <?= e((string) $t['autor']) ?></small></li>
  <?php endforeach; ?></ul>
</section>

<section class="card">
  <h2>Histórico</h2>
  <ul class="fm-historico"><?php foreach ($historico as $h): ?>
    <li><small class="suave"><?= data_br($h['criado_em'], true) ?> · <?= e((string) $h['autor']) ?></small> <?= e($h['texto']) ?></li>
  <?php endforeach; ?></ul>
</section>

<script type="application/json" id="dados-formula"><?= json_encode([
    'mps' => array_column(array_map(fn($m) => ['id' => (int) $m['id'], 'unidade' => $m['unidade'], 'custo' => (float) $m['custo_unitario'], 'estoque' => (float) $m['estoque']], $mps), null, 'id'),
    'codigo' => $f['codigo'],
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?></script>
