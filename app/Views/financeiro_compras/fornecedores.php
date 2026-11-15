<?php
/** Financeiro › Compras da empresa › Fornecedores. Variáveis: $busca, $lista, $pendentes, $pode */
$maior = max(1, ...array_map(fn($f) => (float) $f['gasto'], $lista ?: [['gasto' => 1]]));
?>
<div class="cabecalho-secao">
  <div><div class="sobretitulo">FINANCEIRO · COMPRAS</div><h1>Compras da empresa</h1>
    <p>Cada fornecedor com o que ele fornece, quanto já compramos dele, preços comparados com os outros fornecedores e documentos.</p></div>
</div>
<nav class="abas" aria-label="Compras">
  <a href="<?= url('/financeiro/compras') ?>?aba=lancamentos">Lançamentos</a>
  <a href="<?= url('/financeiro/compras') ?>?aba=itens">Itens comprados</a>
  <?php if ($pode): ?><a href="<?= url('/financeiro/compras') ?>?aba=lancar">Lançar compra</a><?php endif; ?>
  <a href="<?= url('/financeiro/compras') ?>?aba=omie">Do Omie<?= $pendentes ? ' (' . count($pendentes) . ')' : '' ?></a>
  <a class="ativa" href="<?= url('/financeiro/fornecedores') ?>" data-aba-compras="fornecedores">Fornecedores</a>
</nav>
<form class="card it-filtros" method="get" action="<?= url('/financeiro/fornecedores') ?>">
  <label class="it-largo">Buscar<input type="search" name="q" value="<?= e($busca) ?>" placeholder="Nome ou CNPJ do fornecedor…"></label>
  <button class="botao contorno" type="submit">Buscar</button>
</form>
<?php if (!$lista): ?><p class="card suave">Nenhum fornecedor encontrado. Eles aparecem aqui quando você cadastra um fornecedor num item ou lança uma compra.</p><?php endif; ?>
<div class="fo-grade" data-fornecedores>
  <?php foreach ($lista as $f): ?>
    <a class="card fo-cartao" href="<?= url('/financeiro/fornecedores/ver') ?>?nome=<?= e(rawurlencode($f['nome'])) ?>" data-fornecedor="<?= e($f['nome']) ?>">
      <span class="fo-inicial" aria-hidden="true"><?= e(mb_strtoupper(mb_substr($f['nome'], 0, 1))) ?></span>
      <div class="fo-txt">
        <strong><?= e($f['nome']) ?></strong>
        <small><?= $f['cnpj'] ? 'CNPJ ' . e(cnpj_formatado(preg_replace('/\D/', '', $f['cnpj']))) . ' · ' : '' ?><?= (int) $f['itens'] ?> item(ns)<?= $f['principal_de'] ? ' · principal em ' . (int) $f['principal_de'] : '' ?><?= $f['prazo'] !== null ? ' · entrega ~' . (int) round($f['prazo']) . ' dia(s)' : '' ?></small>
        <span class="fo-barra" title="Gasto nos últimos 12 meses"><i style="width: <?= round((float) $f['gasto'] / $maior * 100) ?>%"></i></span>
      </div>
      <div class="fo-valor"><strong><?= dinheiro((float) $f['gasto']) ?></strong><small><?= (int) $f['nfs'] ?> NF(s) em 12 meses<?= $f['ultima'] ? ' · última ' . data_br($f['ultima']) : '' ?></small></div>
    </a>
  <?php endforeach; ?>
</div>
