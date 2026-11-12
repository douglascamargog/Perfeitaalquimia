<?php /** Formulário de venda. Variáveis: $mes, $voltar ('painel'|'vendas') */
$maximo = min(date('Y-m-d'), date('Y-m-t', strtotime("$mes-01")));
?>
<form method="post" action="<?= url('/meu-mes/vendas') ?>" class="form-venda">
  <?= csrf_campo() ?>
  <input type="hidden" name="voltar_aba" value="<?= e($voltar) ?>">
  <div class="campo"><label for="v-valor">Valor (R$)</label><input type="text" inputmode="decimal" id="v-valor" name="valor" placeholder="1.250,50" required></div>
  <div class="campo"><label for="v-data">Data</label><input type="date" id="v-data" name="data" value="<?= e($maximo) ?>" min="<?= e("$mes-01") ?>" max="<?= e($maximo) ?>" required></div>
  <div class="campo"><label for="v-desc">Cliente ou pedido (opcional)</label><input type="text" id="v-desc" name="descricao" maxlength="200"></div>
  <button class="botao lima" type="submit">Registrar venda</button>
</form>
