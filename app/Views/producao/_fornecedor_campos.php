<?php /** Campos do fornecedor de um item. Variável: $f (null = novo) */
$v = fn(string $c) => e((string) ($f[$c] ?? ''));
$preco = $f && $f['ultimo_preco'] !== null ? rtrim(rtrim(number_format((float) $f['ultimo_preco'], 4, ',', ''), '0'), ',') : ''; ?>
<label>Fornecedor<input type="text" name="fornecedor" maxlength="160" required value="<?= $v('fornecedor') ?>"></label>
<label>CNPJ<input type="text" name="cnpj" maxlength="18" value="<?= $v('cnpj') ?>"></label>
<label>Contato<input type="text" name="contato" maxlength="120" value="<?= $v('contato') ?>"></label>
<label>Telefone<input type="text" name="telefone" maxlength="30" value="<?= $v('telefone') ?>"></label>
<label>E-mail<input type="email" name="email" maxlength="160" value="<?= $v('email') ?>"></label>
<label>Código no fornecedor<input type="text" name="codigo_no_fornecedor" maxlength="60" value="<?= $v('codigo_no_fornecedor') ?>"></label>
<label>Preço (R$)<input type="text" name="ultimo_preco" inputmode="decimal" value="<?= $preco ?>"></label>
<label>Prazo (dias)<input type="number" name="prazo_dias" min="0" value="<?= $v('prazo_dias') ?>"></label>
<label class="it-largo">Observações<input type="text" name="observacoes" maxlength="500" value="<?= $v('observacoes') ?>"></label>
