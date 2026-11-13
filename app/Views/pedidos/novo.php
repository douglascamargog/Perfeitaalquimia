<?php
/**
 * Novo pedido — passo 2: produtos (preço da tabela do cliente), pagamento e enviar.
 * Variáveis: $c (cliente), $tabela, $produtos, $quantidades, $minimo, $formas, $boletoBloqueado, $ultimo, $uuid
 */
?>
<div class="cabecalho-secao">
  <div>
    <div class="sobretitulo">NOVO PEDIDO · TABELA <?= e(mb_strtoupper($tabela['nome'])) ?></div>
    <h1><?= e($c['nome_fantasia'] ?: $c['razao_social']) ?></h1>
    <p><?= e($c['cidade']) ?>/<?= e($c['uf']) ?> · pedido mínimo da região: <strong><?= dinheiro($minimo) ?></strong>
      <?= $boletoBloqueado ? ' · <span class="selo vermelho">Boleto bloqueado (título em aberto)</span>' : '' ?></p>
  </div>
  <div class="acoes">
    <?php if ($ultimo): ?><a class="botao contorno" href="<?= url('/pedidos/novo') ?>?cliente=<?= (int) $c['id'] ?>&repetir=1">↻ Repetir último pedido</a><?php endif; ?>
    <a class="botao contorno" href="<?= url('/pedidos/novo') ?>">Trocar cliente</a>
  </div>
</div>

<form class="ped-novo" method="post" action="<?= url('/pedidos') ?>" data-pedido data-minimo="<?= e((string) $minimo) ?>">
  <?= csrf_campo() ?>
  <input type="hidden" name="cliente_id" value="<?= (int) $c['id'] ?>">
  <input type="hidden" name="uuid" value="<?= e($uuid) ?>">
  <section class="card ped-produtos">
    <div class="ped-produtos-topo">
      <input type="search" placeholder="Filtrar por produto ou código…" aria-label="Filtrar produtos" data-filtro-produtos>
      <label class="opcao"><input type="checkbox" data-so-escolhidos> Só os escolhidos</label>
    </div>
    <div class="tabela-rolagem">
      <table class="tabela ped-tabela">
        <thead><tr><th>Produto</th><th class="direita">Preço</th><th>Qtd.</th><th class="direita">Total</th></tr></thead>
        <tbody>
        <?php foreach ($produtos as $p): $q = (int) ($quantidades[$p['id']] ?? 0); ?>
          <tr data-linha class="<?= !empty($p['terceirizado']) ? 'ped-linha-marca' : '' ?>" data-nome="<?= e(mb_strtolower(($p['codigo'] ?? '') . ' ' . $p['nome'] . ' ' . $p['categoria'])) ?>" data-preco="<?= e((string) $p['preco']) ?>">
            <td><div class="ped-produto"><?php if ($p['imagem']): ?><img src="<?= url('/' . $p['imagem']) ?>" alt="" width="40" height="40" loading="lazy"><?php endif; ?>
              <span><strong><?= !empty($p['codigo']) ? '<span class="cod-produto">' . e($p['codigo']) . '</span> ' : '' ?><?= e($p['nome']) ?></strong><small><?= !empty($p['terceirizado']) ? '<span class="selo-marca">marca do cliente</span> ' . (int) $p['rotulos'] . ' rótulo(s) · ' : '' ?><?= e($p['embalagem']) ?><?= $p['estoque'] !== null ? ' · estoque ' . (int) $p['estoque'] : '' ?></small></span></div></td>
            <td class="direita"><?= dinheiro((float) $p['preco']) ?></td>
            <td><input class="ped-qtd" type="number" name="qtd[<?= e((string) $p['id']) ?>]" min="0" max="<?= !empty($p['terceirizado']) ? 99999 : 9999 ?>" value="<?= $q ?: '' ?>" placeholder="0" aria-label="Quantidade de <?= e($p['nome']) ?>"></td>
            <td class="direita" data-total-linha><?= $q ? dinheiro($q * (float) $p['preco']) : '' ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>

  <aside class="card ped-resumo">
    <h2>Resumo</h2>
    <div class="ped-total"><span><b data-itens>0</b> item(ns)</span><strong data-subtotal>R$ 0,00</strong></div>
    <div class="cat-minimo falta" data-caixa-minimo><span data-texto-minimo></span><div class="cat-minimo-barra"><i data-barra-minimo style="width:0"></i></div></div>
    <h3>Pagamento</h3>
    <?php foreach ($formas as $f): ?>
      <fieldset class="cat-forma <?= $f['bloqueio'] ? 'bloqueada' : '' ?>">
        <legend><?= e($f['nome']) ?></legend>
        <?php if ($f['bloqueio']): ?><p class="pequeno"><?= e($f['bloqueio']) ?></p><?php else: foreach ($f['condicoes'] as $cd): ?>
          <label class="opcao" data-condicao-minimo="<?= e((string) $cd['valor_minimo']) ?>">
            <input type="radio" name="pagamento" value="<?= (int) $f['id'] ?>:<?= (int) $cd['id'] ?>" required <?= antigo('pagamento') === $f['id'] . ':' . $cd['id'] ? 'checked' : '' ?>>
            <?= e($cd['nome']) ?><?= (float) $cd['desconto_pct'] > 0 ? ' · ' . number_format((float) $cd['desconto_pct'], 1, ',', '.') . '% desc.' : '' ?>
            <?= (float) $cd['valor_minimo'] > 0 ? '<small>(a partir de ' . e(dinheiro((float) $cd['valor_minimo'])) . ')</small>' : '' ?>
          </label>
        <?php endforeach; endif; ?>
      </fieldset>
    <?php endforeach; ?>
    <div class="campo"><label for="obs">Observação</label><textarea id="obs" name="observacoes" rows="2" maxlength="1000"><?= antigo('observacoes') ?></textarea></div>
    <button class="botao largo" type="submit" data-enviar>Enviar pedido</button>
    <p class="pequeno suave">Vai ao ERP como orçamento e para a aprovação do Financeiro.</p>
  </aside>
</form>
