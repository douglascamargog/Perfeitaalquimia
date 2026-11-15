<?php
/** Uma compra lançada (NF). Variáveis: $c, $itens, $catalogo, $pode */
use App\Services\CompraLancamentoService as L;
?>
<a class="voltar-link" href="<?= url('/financeiro/compras') ?>">← Compras da empresa</a>
<div class="cabecalho-secao">
  <div><div class="sobretitulo">COMPRA · <?= e(mb_strtoupper(L::ORIGENS[$c['origem']] ?? $c['origem'])) ?></div>
    <h1><?= e($c['fornecedor']) ?> <a class="botao contorno pequeno" href="<?= url('/financeiro/fornecedores/ver') ?>?nome=<?= e(rawurlencode($c['fornecedor'])) ?>">Ver fornecedor</a></h1>
    <p>NF <strong><?= e($c['numero_nf'] ?: '—') ?></strong> · <?= data_br($c['emissao']) ?><?= $c['cnpj'] ? ' · CNPJ ' . e($c['cnpj']) : '' ?> · <?= dinheiro((float) $c['valor_total']) ?>
      <?= $c['lancado_por_nome'] ? ' · lançada por ' . e($c['lancado_por_nome']) : '' ?></p></div>
  <div class="acoes"><?php if ($c['arquivo']): ?><a class="botao contorno" href="<?= url('/financeiro/compras/' . (int) $c['id'] . '/nf') ?>" target="_blank" rel="noopener" data-nf>📎 Ver a NF</a><?php endif; ?>
    <?php if ($c['compra_id']): ?><a class="botao contorno" href="<?= url('/compras/' . (int) $c['compra_id']) ?>">Orçamento #<?= (int) $c['compra_id'] ?></a><?php endif; ?></div>
</div>
<?php if ($c['observacoes']): ?><p class="aviso"><?= e($c['observacoes']) ?></p><?php endif; ?>
<section class="card sem-espaco">
  <div class="tabela-rolagem"><table class="tabela" data-itens-nf>
    <thead><tr><th>Item</th><th class="direita">Quantidade</th><th class="direita">Valor unitário</th><th class="direita">Total</th><th>Estoque</th></tr></thead>
    <tbody>
    <?php foreach ($itens as $i): ?>
      <tr><td><?= $i['materia_prima_id'] ? '<a href="' . url('/producao/materias/' . (int) $i['materia_prima_id']) . '"><strong>' . e($i['item']) . '</strong></a>' : '<strong>' . e($i['descricao']) . '</strong>' ?>
          <?= $i['omie_item'] ? '<small class="suave" style="display:block">' . e($i['descricao']) . ' · código Omie ' . e($i['omie_item']) . '</small>' : '' ?></td>
        <td class="direita"><?= rtrim(rtrim(number_format((float) $i['quantidade'], 3, ',', '.'), '0'), ',') ?> <?= e((string) $i['unidade']) ?></td>
        <td class="direita"><?= dinheiro((float) $i['valor_unitario']) ?></td><td class="direita"><?= dinheiro((float) $i['total']) ?></td>
        <td><?php if ($i['lancado']): ?><span class="selo verde">entrou no estoque</span>
          <?php elseif ($pode): ?><form method="post" action="<?= url('/financeiro/compras/ligar/' . (int) $i['id']) ?>" class="it-ligar"><?= csrf_campo() ?><input type="hidden" name="voltar" value="/financeiro/compras/<?= (int) $c['id'] ?>">
            <select name="item" required aria-label="Item do One"><option value="">— qual item do One?</option><?php foreach ($catalogo as $m): ?><option value="<?= (int) $m['id'] ?>"><?= e($m['nome']) ?></option><?php endforeach; ?></select>
            <button class="botao pequeno" type="submit">Ligar</button></form>
          <?php else: ?><span class="selo laranja">para ligar</span><?php endif; ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</section>
