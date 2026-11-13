<?php
/** Campos do cadastro de matéria-prima. Variáveis: $m (null = nova), $unidades */
$v = fn(string $campo, $padrao = '') => $m === null ? antigo($campo, $padrao) : e((string) ($m[$campo] ?? $padrao));
$num = fn($valor) => rtrim(rtrim(number_format((float) $valor, 4, ',', ''), '0'), ',');
?>
<div class="grade-campos">
  <div class="campo"><label for="nome">Nome</label><input type="text" id="nome" name="nome" required maxlength="160" value="<?= $v('nome') ?>"></div>
  <div class="campo"><label for="codigo">Código interno</label><input type="text" id="codigo" name="codigo" maxlength="40" value="<?= $v('codigo') ?>"></div>
  <div class="campo"><label for="unidade">Unidade</label>
    <select id="unidade" name="unidade">
      <?php $atual = $m['unidade'] ?? (string) \App\Core\Sessao::antigo('unidade', 'kg'); foreach ($unidades as $u): ?>
        <option <?= $atual === $u ? 'selected' : '' ?>><?= e($u) ?></option>
      <?php endforeach; ?>
    </select>
    <?php if ($m): ?><div class="ajuda">Não muda depois do primeiro movimento.</div><?php endif; ?></div>
  <div class="campo"><label for="tipo">Tipo</label>
    <select id="tipo" name="tipo">
      <?php $tipo = $m['tipo'] ?? (string) \App\Core\Sessao::antigo('tipo', 'materia_prima'); ?>
      <option value="materia_prima" <?= $tipo !== 'embalagem' ? 'selected' : '' ?>>Matéria-prima</option>
      <option value="embalagem" <?= $tipo === 'embalagem' ? 'selected' : '' ?>>Embalagem (pote, tampa, rótulo, lacre...)</option>
    </select>
    <div class="ajuda">Embalagens aparecem na aba Embalagens do produto.</div></div>
  <div class="campo"><label for="estoque_minimo">Estoque mínimo</label>
    <input type="text" id="estoque_minimo" name="estoque_minimo" inputmode="decimal" value="<?= $m ? $num($m['estoque_minimo']) : antigo('estoque_minimo', '0') ?>"></div>
  <div class="campo"><label for="custo_unitario">Custo por unidade (R$)</label>
    <input type="text" id="custo_unitario" name="custo_unitario" inputmode="decimal" value="<?= $m ? $num($m['custo_unitario']) : antigo('custo_unitario') ?>">
    <div class="ajuda">Atualiza sozinho a cada entrada de compra.</div></div>
  <div class="campo"><label for="fornecedor">Fornecedor</label><input type="text" id="fornecedor" name="fornecedor" maxlength="160" value="<?= $v('fornecedor') ?>"></div>
</div>
<div class="campo"><label for="observacoes">Observações</label><textarea id="observacoes" name="observacoes" rows="2"><?= $v('observacoes') ?></textarea></div>
<label class="opcao"><input type="checkbox" name="ativo" value="1" <?= ($m === null || $m['ativo']) ? 'checked' : '' ?>> Ativa</label>
