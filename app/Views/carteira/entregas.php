<?php
/** Carteira › Endereços de entrega a revisar. Variáveis: $lojas, $ufs, $podeProcurar */
?>
<div class="cabecalho-secao">
  <div><div class="sobretitulo">CARTEIRA · ENDEREÇOS DE ENTREGA</div><h1>Endereços de entrega a revisar</h1>
    <p>Na observação destas lojas tem um texto que parece ser um endereço de entrega diferente do endereço do CNPJ.
      O One não adivinha: confira e preencha. Confirmado, os pedidos já vão para o endereço de entrega.</p></div>
  <?php if ($podeProcurar): ?>
    <div class="acoes"><form method="post" action="<?= url('/carteira/entregas/procurar') ?>"><?= csrf_campo() ?>
      <button class="botao contorno" type="submit" data-entregas-procurar>Procurar nas observações das lojas</button></form></div>
  <?php endif; ?>
</div>

<?php if (!$lojas): ?>
  <div class="card vazio" data-entregas-vazio><p>Nenhuma loja para revisar. 🎉</p></div>
<?php endif; ?>

<div class="entregas-lista" data-entregas>
<?php foreach ($lojas as $l): $id = (int) $l['id']; ?>
  <form class="card entregas-loja" id="loja-<?= $id ?>" method="post" action="<?= url("/carteira/entregas/$id") ?>" data-loja="<?= $id ?>"><?= csrf_campo() ?>
    <header>
      <div><a href="<?= url("/carteira/clientes/$id") ?>"><strong><?= e($l['nome_fantasia'] ?: $l['razao_social']) ?></strong></a>
        <small class="suave"> · <?= e($l['cidade']) ?>/<?= e($l['uf']) ?><?= $l['responsavel'] ? ' · ' . e($l['responsavel']) : '' ?></small></div>
    </header>
    <blockquote class="entregas-trecho">“<?= e($l['entrega_revisar']) ?>”</blockquote>
    <div class="grade-campos">
      <div class="campo"><label for="end-<?= $id ?>">Endereço de entrega</label><input id="end-<?= $id ?>" type="text" name="entrega_endereco" maxlength="150"></div>
      <div class="campo"><label for="num-<?= $id ?>">Número</label><input id="num-<?= $id ?>" type="text" name="entrega_numero" maxlength="20"></div>
      <div class="campo"><label for="comp-<?= $id ?>">Complemento</label><input id="comp-<?= $id ?>" type="text" name="entrega_complemento" maxlength="60"></div>
      <div class="campo"><label for="bai-<?= $id ?>">Bairro</label><input id="bai-<?= $id ?>" type="text" name="entrega_bairro" maxlength="60"></div>
      <div class="campo"><label for="cid-<?= $id ?>">Cidade</label><input id="cid-<?= $id ?>" type="text" name="entrega_cidade" maxlength="80" value="<?= e($l['cidade']) ?>"></div>
      <div class="campo"><label for="uf-<?= $id ?>">UF</label><select id="uf-<?= $id ?>" name="entrega_uf"><?php foreach ($ufs as $uf): ?><option <?= $uf === $l['uf'] ? 'selected' : '' ?>><?= e($uf) ?></option><?php endforeach; ?></select></div>
      <div class="campo"><label for="cep-<?= $id ?>">CEP</label><input id="cep-<?= $id ?>" type="text" name="entrega_cep" inputmode="numeric"></div>
    </div>
    <div class="acoes">
      <button class="botao" type="submit" name="acao" value="confirmar">Confirmar endereço de entrega</button>
      <button class="botao contorno" type="submit" name="acao" value="descartar" formnovalidate>Não é endereço de entrega</button>
    </div>
  </form>
<?php endforeach; ?>
</div>
