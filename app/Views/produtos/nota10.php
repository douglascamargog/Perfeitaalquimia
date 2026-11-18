<?php
/**
 * Produtos › Ligar produtos do Nota10. Variáveis: $pendentes (duplicados com 'sugestao'), $doOne (produtos do One), $ligados (ligações feitas)
 */
$porCodigo = count(array_filter($pendentes, fn($d) => ($d['sugestao']['por'] ?? '') === 'codigo'));
$motivo = ['codigo' => 'mesmo código', 'ean' => 'mesmo código de barras', 'nome' => 'mesmo nome'];
$rotulo = fn(array $p) => (int) $p['id'] . ' · ' . ($p['codigo'] ? $p['codigo'] . ' · ' : '') . $p['nome'] . ((int) ($p['ativo'] ?? 1) ? '' : ' (inativo)');
?>
<div class="cabecalho-secao">
  <div><div class="sobretitulo">PRODUTOS · NOTA10</div><h1>Ligar produtos do Nota10</h1>
    <p>Produtos que a importação do Nota10 não reconheceu e criou à parte (inativos, fora do catálogo). Ligue cada um ao produto do One:
      as vendas e o histórico passam para o produto do One, e as próximas importações já caem nele. Pode desfazer.</p></div>
  <div class="acoes"><a class="botao contorno" href="<?= url('/produtos/catalogo') ?>">Preencher códigos no Catálogo</a></div>
</div>

<div class="aviso" role="status">
  <strong>Como fazer:</strong> 1) no <a href="<?= url('/produtos/catalogo') ?>">Catálogo</a>, preencha em cada produto do One o <strong>mesmo código do Nota10</strong>
  (um por um ou pela planilha de códigos); 2) volte aqui: o One sugere o produto certo pelo código, pelo código de barras ou pelo nome; 3) confira e clique em Ligar.
  O que é do One (preços, foto, ficha, estoque) continua valendo; do Nota10 só entra o que estiver faltando.
</div>

<?php if (!$pendentes): ?>
  <section class="card" data-n10-produtos-vazio><p>✅ Nenhum produto do Nota10 para ligar.</p></section>
<?php else: ?>
  <section class="card sem-espaco" data-n10-produtos>
    <header class="cl-secao-topo"><h2><?= count($pendentes) ?> produto(s) do Nota10 sem ligação</h2>
      <?php if ($porCodigo): ?><form method="post" action="<?= url('/produtos/nota10/ligar-por-codigo') ?>"><?= csrf_campo() ?>
        <button class="botao" type="submit" data-ligar-por-codigo data-confirmar="Ligar os <?= $porCodigo ?> produto(s) que têm o mesmo código de um produto do One?">Ligar os <?= $porCodigo ?> com o mesmo código</button></form>
      <?php else: ?><span class="pequeno suave">Nenhum com o mesmo código ainda: preencha os códigos no Catálogo.</span><?php endif; ?></header>
    <datalist id="produtos-one"><?php foreach ($doOne as $p): ?><option value="<?= e($rotulo($p)) ?>"></option><?php endforeach; ?></datalist>
    <div class="tabela-rolagem">
      <table class="tabela">
        <thead><tr><th>Produto do Nota10</th><th class="direita">Vendas</th><th>Ligar ao produto do One</th><th></th></tr></thead>
        <tbody data-lista-longa="40">
        <?php foreach ($pendentes as $d): $s = $d['sugestao']; $form = 'n10p-' . (int) $d['id']; ?>
          <tr data-n10-produto="<?= e($d['nota10_codigo']) ?>">
            <td><strong><?= e($d['nome']) ?></strong><br><small class="suave">código no Nota10: <span class="cod-produto"><?= e($d['nota10_codigo']) ?></span>
              <?= $d['ean'] ? ' · EAN ' . e($d['ean']) : '' ?><?= $d['grupo_erp'] ? ' · ' . e($d['grupo_erp']) : '' ?></small></td>
            <td class="direita"><?= (int) $d['itens'] ?> item(ns)<?= $d['ultima_venda'] ? '<br><small class="suave">última ' . data_br($d['ultima_venda']) . '</small>' : '' ?></td>
            <td><form method="post" action="<?= url('/produtos/nota10/ligar') ?>" id="<?= $form ?>"><?= csrf_campo() ?>
                <input type="hidden" name="origem" value="<?= (int) $d['id'] ?>">
                <input type="text" name="destino" list="produtos-one" value="<?= $s ? e($rotulo($s)) : '' ?>" placeholder="digite o nome ou o código…" required style="min-width:280px"
                  aria-label="Produto do One para <?= e($d['nome']) ?>"></form>
              <?php if ($s): ?><small class="selo <?= $s['por'] === 'codigo' ? 'verde' : 'laranja' ?>" data-sugestao="<?= e($s['por']) ?>">sugestão: <?= e($motivo[$s['por']]) ?></small><?php endif; ?></td>
            <td><button class="botao pequeno" type="submit" form="<?= $form ?>">Ligar</button></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>
<?php endif; ?>

<?php if ($ligados): ?>
  <section class="card sem-espaco" id="ligados" data-n10-ligados>
    <header class="cl-secao-topo"><h2>Já ligados</h2><span class="cl-contador"><?= count($ligados) ?></span></header>
    <div class="tabela-rolagem">
      <table class="tabela">
        <thead><tr><th>Do Nota10</th><th>Ficou no produto do One</th><th>Quando</th><th></th></tr></thead>
        <tbody data-lista-longa="20">
        <?php foreach ($ligados as $j): $mov = (array) json_decode((string) $j['movidos'], true); ?>
          <tr><td><?= e($j['origem_nome']) ?> <small class="suave">(<?= e($j['nota10_codigo']) ?>)</small></td>
            <td><strong><?= e($j['destino_nome']) ?></strong><?= $j['destino_codigo'] ? ' <span class="cod-produto">' . e($j['destino_codigo']) . '</span>' : '' ?>
              <br><small class="suave"><?= count($mov['pedido_itens'] ?? []) ?> item(ns) de pedido passaram para ele</small></td>
            <td class="pequeno"><?= data_br($j['criado_em'], true) ?><?= $j['usuario'] ? '<br>' . e($j['usuario']) : '' ?></td>
            <td><form method="post" action="<?= url('/produtos/nota10/desfazer/' . (int) $j['id']) ?>"><?= csrf_campo() ?>
              <button class="botao contorno pequeno" type="submit" data-confirmar="Desfazer esta ligação? As vendas voltam para o produto do Nota10.">Desfazer</button></form></td></tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>
<?php endif; ?>
