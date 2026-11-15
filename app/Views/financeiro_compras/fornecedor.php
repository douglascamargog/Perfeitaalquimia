<?php
/** Página de um fornecedor. Variável: $f (FornecedorService::ver) */
use App\Services\ItemCompraService as I;
use App\Services\ProducaoService as P;

$n = $f['numeros'];
$c = $f['contato'];
?>
<div class="cabecalho-secao">
  <div><div class="sobretitulo"><a href="<?= url('/financeiro/fornecedores') ?>">FORNECEDORES</a></div><h1><?= e($f['nome']) ?></h1>
    <p><?= $c['cnpj'] ? 'CNPJ ' . e(cnpj_formatado(preg_replace('/\D/', '', $c['cnpj']))) : 'CNPJ não informado' ?>
      <?= $c['contato'] ? ' · ' . e($c['contato']) : '' ?><?= $c['telefone'] ? ' · ' . e($c['telefone']) : '' ?><?= $c['email'] ? ' · <a href="mailto:' . e($c['email']) . '">' . e($c['email']) . '</a>' : '' ?></p></div>
  <?php if ($c['telefone']): ?><div class="acoes"><a class="botao contorno" href="https://wa.me/55<?= e(preg_replace('/\D/', '', $c['telefone'])) ?>" target="_blank" rel="noopener">WhatsApp</a></div><?php endif; ?>
</div>

<div class="it-numeros" data-numeros-fornecedor>
  <section class="card it-num escuro"><span>Comprado em 12 meses</span><strong><?= dinheiro($n['gasto_12m']) ?></strong><small><?= (int) $n['compras_12m'] ?> NF(s) · média <?= dinheiro($n['ticket']) ?></small></section>
  <section class="card it-num"><span>Última compra</span><strong><?= $n['ultima'] ? data_br($n['ultima']) : '—' ?></strong><small><?= $n['intervalo'] ? 'compramos a cada ~' . (int) $n['intervalo'] . ' dias' : 'sem histórico suficiente' ?></small></section>
  <section class="card it-num"><span>Prazo de entrega</span><strong><?= $n['prazo'] !== null ? (int) $n['prazo'] . ' dia(s)' : '—' ?></strong><small>média informada nos itens</small></section>
  <section class="card it-num"><span>Itens que fornece</span><strong><?= count($f['itens']) ?></strong><small><?= count(array_filter($f['itens'], fn($l) => $l['principal'])) ?> como principal</small></section>
  <section class="card it-num <?= $n['comparaveis'] > $n['mais_barato_em'] ? 'alerta' : '' ?>"><span>Menor preço em</span><strong><?= $n['comparaveis'] ? (int) $n['mais_barato_em'] . ' de ' . (int) $n['comparaveis'] : '—' ?></strong>
    <small><?= $n['comparaveis'] ? 'itens que têm outro fornecedor para comparar' : 'nenhum item com outro fornecedor para comparar' ?></small></section>
</div>

<div class="it-duas">
  <section class="card sem-espaco">
    <header class="cl-secao-topo"><h2>O que ele fornece</h2></header>
    <div class="tabela-rolagem"><table class="tabela" data-itens-fornecedor>
      <thead><tr><th>Item</th><th class="direita">Último preço</th><th>Comparação</th><th>Última compra</th></tr></thead>
      <tbody>
      <?php foreach ($f['itens'] as $l): ?>
        <tr data-item="<?= (int) $l['materia_prima_id'] ?>"><td><a href="<?= url('/producao/materias/' . (int) $l['materia_prima_id'] . '?aba=fornecedores') ?>"><strong><?= e($l['item']) ?></strong></a>
            <small class="suave"><?= e(I::TIPOS[$l['tipo']] ?? $l['tipo']) ?><?= $l['categoria'] ? ' · ' . e($l['categoria']) : '' ?></small> <?= $l['principal'] ? '<span class="selo verde">principal</span>' : '' ?></td>
          <td class="direita"><?= $l['ultimo_preco'] !== null ? dinheiro((float) $l['ultimo_preco']) . '<small class="suave">/' . e($l['unidade']) . '</small>' : '—' ?></td>
          <td><?php if (!$l['outros']): ?><span class="suave pequeno">único fornecedor</span>
            <?php elseif ($l['mais_barato']): ?><span class="selo verde" data-mais-barato>menor preço</span>
            <?php elseif ($l['diferenca'] !== null): ?><span class="selo laranja"><?= number_format($l['diferenca'], 0, ',', '.') ?>% acima do menor</span><?php endif; ?></td>
          <td class="pequeno"><?= $l['ultima_compra_em'] ? data_br($l['ultima_compra_em']) : '—' ?></td></tr>
      <?php endforeach; ?>
      <?php if (!$f['itens']): ?><tr><td colspan="4" class="suave">Nenhum item com este fornecedor cadastrado (só aparece nas NFs).</td></tr><?php endif; ?>
      </tbody>
    </table></div>
  </section>

  <section class="card">
    <h2>Compras por mês</h2>
    <?= \App\Core\Visao::renderizar('graficos/_barras_dia', [
        'dias' => array_map(fn($m, $v) => ['dia' => $m . '-01', 'valores' => [$v], 'rotulo' => mes_curto((int) substr($m, 5, 2)), 'dica' => mes_curto((int) substr($m, 5, 2)) . '/' . substr($m, 0, 4)], array_keys($f['por_mes']), array_values($f['por_mes'])),
        'series' => [['nome' => 'Comprado', 'classe' => 's1']], 'dinheiro' => true]) ?>
  </section>
</div>

<section class="card sem-espaco">
  <header class="cl-secao-topo"><h2>Compras (NFs)</h2></header>
  <div class="tabela-rolagem fo-rolar"><table class="tabela" data-compras-fornecedor>
    <thead><tr><th>Emissão</th><th>NF</th><th>Origem</th><th class="direita">Itens</th><th class="direita">Valor</th></tr></thead>
    <tbody>
    <?php foreach ($f['compras'] as $cp): ?>
      <tr><td><?= data_br($cp['emissao']) ?></td><td><a href="<?= url('/financeiro/compras/' . (int) $cp['id']) ?>"><?= $cp['numero_nf'] ? 'NF ' . e($cp['numero_nf']) : 'sem número' ?></a><?= $cp['arquivo'] ? ' 📎' : '' ?></td>
        <td class="pequeno"><?= e(\App\Services\CompraLancamentoService::ORIGENS[$cp['origem']] ?? $cp['origem']) ?></td>
        <td class="direita"><?= (int) $cp['qtd_itens'] ?></td><td class="direita"><strong><?= dinheiro((float) $cp['valor_total']) ?></strong></td></tr>
    <?php endforeach; ?>
    <?php if (!$f['compras']): ?><tr><td colspan="5" class="suave">Nenhuma compra lançada deste fornecedor ainda.</td></tr><?php endif; ?>
    </tbody>
  </table></div>
</section>

<?php if ($f['documentos']): ?>
<section class="card">
  <h2>Documentos deste fornecedor</h2>
  <ul class="fo-docs">
    <?php foreach ($f['documentos'] as $d): $sit = I::situacaoValidade($d['validade']); ?>
      <li><a href="<?= url('/producao/materias/documento/' . (int) $d['id']) ?>" target="_blank" rel="noopener">📄 <?= e(I::TIPOS_DOCUMENTO[$d['tipo']] ?? $d['tipo']) ?> · <?= e($d['titulo']) ?></a>
        <small class="suave"><?= e($d['item']) ?><?= $d['lote'] ? ' · lote ' . e($d['lote']) : '' ?></small>
        <?php if ($d['validade']): ?><span class="selo <?= $sit === 'vencido' ? 'vermelho' : ($sit === 'vencendo' ? 'laranja' : 'cinza') ?>">validade <?= data_br($d['validade']) ?></span><?php endif; ?></li>
    <?php endforeach; ?>
  </ul>
</section>
<?php endif; ?>
