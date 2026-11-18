<?php
/** Página do pedido. Variáveis: $p, $itens, $historico, $credito (ou null), $erpSimulado */
use App\Core\Auth;
use App\Services\PedidoService;

[$rotulo, $cor] = PedidoService::SITUACOES[$p['situacao']] ?? [$p['situacao'], 'cinza'];
$origens = ['catalogo' => 'Catálogo (cliente)', 'sistema' => 'Sistema (equipe)', 'app' => 'App do externo', 'orcamento' => 'Orçamento aprovado pelo cliente'];
?>
<div class="cabecalho-secao nao-imprimir">
  <div><div class="sobretitulo">PEDIDO · <?= e(mb_strtoupper($origens[$p['origem']] ?? $p['origem'])) ?></div><h1>Pedido #<?= (int) $p['id'] ?> <span class="selo <?= $cor ?>"><?= $rotulo ?></span></h1></div>
  <div class="acoes">
    <a class="botao contorno" href="<?= url('/pedidos') ?>">← Pedidos</a>
    <button class="botao contorno" type="button" onclick="window.print()">Imprimir</button>
    <?php if ($p['situacao'] === 'aguardando_aprovacao' && Auth::pode('pedidos.criar')): ?>
      <a class="botao contorno" href="<?= url('/pedidos/novo') ?>?cliente=<?= (int) $p['cliente_id'] ?>&corrigir=<?= (int) $p['id'] ?>" data-corrigir-pedido>Corrigir pedido</a>
    <?php endif; ?>
    <?php if ($p['situacao'] === 'aguardando_aprovacao' && (Auth::pode('pedidos.criar') || Auth::pode('pedidos.ver_todos'))): ?>
      <form method="post" action="<?= url('/pedidos/' . (int) $p['id'] . '/cancelar') ?>" data-confirmar="Cancelar este pedido?"><?= csrf_campo() ?><button class="botao contorno" type="submit">Cancelar pedido</button></form>
    <?php endif; ?>
    <?php if ($erpSimulado && $p['situacao'] === 'aprovado' && (Auth::pode('pedidos.aprovar') || Auth::pode('pedidos.ver_todos'))): ?>
      <form method="post" action="<?= url('/pedidos/' . (int) $p['id'] . '/simular-faturamento') ?>"><?= csrf_campo() ?><button class="botao lima" type="submit">Simular faturamento (demonstração)</button></form>
    <?php endif; ?>
  </div>
</div>

<div class="ped-pagina">
  <section class="card ped-espelho">
    <header class="ped-espelho-topo">
      <img src="<?= asset('img/logo.png') ?>" alt="" width="60" height="60">
      <div><strong><?= e(\App\Core\Config::get('empresa.nome', 'Perfeita Alquimia')) ?></strong><small>Pedido #<?= (int) $p['id'] ?> · <?= data_br($p['criado_em'], true) ?></small></div>
      <span class="selo <?= $cor ?> so-imprimir"><?= $rotulo ?></span>
    </header>
    <dl class="ped-dados">
      <div><dt>Cliente</dt><dd><?php if (\App\Models\Cliente::podeVer(['id' => (int) $p['cliente_id'], 'responsavel_id' => $p['responsavel_id']])): ?><a href="<?= url('/carteira/clientes/' . (int) $p['cliente_id']) ?>" data-link-loja><?= e($p['razao_social']) ?></a><?php else: ?><?= e($p['razao_social']) ?><?php endif; ?><?= $p['cnpj'] ? '<br><small>CNPJ ' . e(cnpj_formatado($p['cnpj'])) . '</small>' : '' ?></dd></div>
      <div><dt>Cidade</dt><dd><?= e($p['cidade']) ?>/<?= e($p['uf']) ?></dd></div>
      <div><dt>Vendedor</dt><dd><?= pessoa($p['vendedor'] ?? null, $p['vendedor_foto'] ?? null, false, 28) ?></dd></div>
      <div><dt>Tabela</dt><dd><?= e($p['tabela']) ?></dd></div>
      <div><dt>Pagamento</dt><dd><?= e($p['forma']) ?><?= $p['condicao'] ? ' · ' . e($p['condicao']) : '' ?></dd></div>
      <div><dt>ERP</dt><dd><?= $p['omie_numero'] ? 'Orçamento nº ' . e($p['omie_numero']) : '<span class="suave">envio pendente</span>' ?><?= $p['numero_nf'] ? '<br>NF ' . e($p['numero_nf']) . ' · ' . data_br($p['faturado_em']) : '' ?></dd></div>
    </dl>
    <?php if ($p['motivo_recusa']): ?><p class="aviso erro">Recusado: <?= e($p['motivo_recusa']) ?></p><?php endif; ?>
    <table class="tabela ped-itens">
      <thead><tr><th>Produto</th><th class="direita">Qtd.</th><th class="direita">Preço</th><th class="direita">Total</th></tr></thead>
      <tbody>
      <?php foreach ($itens as $i): ?><tr><td><?= !empty($i['codigo']) ? '<span class="cod-produto">' . e($i['codigo']) . '</span> ' : '' ?><?= e($i['nome']) ?></td><td class="direita"><?= (int) $i['quantidade'] ?></td><td class="direita"><?= dinheiro((float) $i['preco_unitario']) ?><?= (float) ($i['desconto_pct'] ?? 0) > 0 ? '<small class="suave" style="display:block">tabela ' . dinheiro((float) $i['preco_tabela']) . ' · −' . e(rtrim(rtrim(number_format((float) $i['desconto_pct'], 2, ',', ''), '0'), ',')) . '%</small>' : '' ?></td><td class="direita"><?= dinheiro((float) $i['total']) ?></td></tr><?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr><td colspan="3" class="direita">Subtotal</td><td class="direita"><?= dinheiro((float) $p['subtotal']) ?></td></tr>
        <?php if ((float) $p['desconto'] > 0): ?><tr><td colspan="3" class="direita">Desconto</td><td class="direita">−<?= dinheiro((float) $p['desconto']) ?></td></tr><?php endif; ?>
        <?php if ((float) ($p['desconto_vendedor'] ?? 0) > 0): ?><tr data-desconto-vendedor><td colspan="3" class="direita">Desconto dado pela vendedora (produtos<?= (float) $p['desconto_pedido_pct'] > 0 ? ' + ' . e(rtrim(rtrim(number_format((float) $p['desconto_pedido_pct'], 2, ',', ''), '0'), ',')) . '% no pedido' : '' ?>)
          <?= $p['desconto_acima_limite'] ? '<span class="selo selo-desconto">acima do limite</span>' : '' ?></td><td class="direita"><?= dinheiro((float) $p['desconto_vendedor']) ?></td></tr><?php endif; ?>
        <?php if (!empty($p['campanhas'])): ?><tr data-pedido-campanha><td colspan="3" class="direita">Campanha <?= e($p['campanhas']) ?><?= (float) $p['campanha_desconto'] > 0 ? ' (' . e(\App\Services\CampanhaService::pct((float) $p['campanha_desconto_pct'])) . ', já no desconto)' : '' ?>
          <?= $p['campanha_brindes'] ? '<br>🎁 Brinde: <strong>' . e($p['campanha_brindes']) . '</strong>' : '' ?><?= (int) $p['frete_gratis'] ? '<br><strong>Frete grátis</strong>' : '' ?></td>
          <td class="direita"><?= (float) $p['campanha_desconto'] > 0 ? dinheiro((float) $p['campanha_desconto']) : '' ?></td></tr><?php endif; ?>
        <tr class="ped-total-final"><td colspan="3" class="direita">Total</td><td class="direita"><?= dinheiro((float) $p['total']) ?></td></tr>
      </tfoot>
    </table>
    <?php if ($p['observacoes']): ?><p><strong>Observação:</strong> <?= e($p['observacoes']) ?></p><?php endif; ?>
  </section>

  <aside class="ped-lateral nao-imprimir">
    <?php if ($credito !== null && $p['situacao'] === 'aguardando_aprovacao'): ?>
      <?= \App\Core\Visao::renderizar('aprovacao/_credito', ['cr' => $credito]) ?>
      <?= \App\Core\Visao::renderizar('aprovacao/_acoes', ['p' => $p, 'voltar' => 'pedido']) ?>
    <?php endif; ?>
    <?php if (!empty($p['expedicao'])): [$er, $ec] = \App\Services\DepositoService::ETAPAS[$p['expedicao']] ?? [$p['expedicao'], 'cinza'];
      $lotesPed = \App\Core\Banco::todos('SELECT l.lote, l.validade, l.quantidade, i.nome FROM pedido_item_lotes l JOIN pedido_itens i ON i.id = l.pedido_item_id WHERE l.pedido_id = ? ORDER BY l.id', [$p['id']]); ?>
    <section class="card ped-expedicao" data-expedicao="<?= e($p['expedicao']) ?>">
      <h2>Expedição <span class="selo <?= $ec ?>"><?= e($er) ?></span></h2>
      <?php if ($p['expedicao_aguarda_produto']): ?><p class="aviso alerta">Esperando produto no depósito.</p><?php endif; ?>
      <?php if ($p['falta']): ?><p class="aviso alerta">Saiu sem alguns itens<?= $p['expedicao_obs'] ? ': ' . e($p['expedicao_obs']) : '' ?>.</p><?php endif; ?>
      <dl class="ped-dados">
        <?php if ($p['separado_em']): ?><div><dt>Separado</dt><dd><?= data_br($p['separado_em'], true) ?></dd></div><?php endif; ?>
        <?php if ($p['despachado_em']): ?><div><dt>Despachado</dt><dd><?= data_br($p['despachado_em'], true) ?></dd></div>
          <div><dt>Transportadora</dt><dd><?= e($p['transportadora']) ?> · <?= (int) $p['volumes'] ?> vol.</dd></div>
          <?php if ($p['rastreio']): ?><div><dt>Rastreio</dt><dd data-rastreio><?= e($p['rastreio']) ?></dd></div><?php endif; ?><?php endif; ?>
      </dl>
      <?php if ($lotesPed): ?><p class="pequeno suave">Lotes: <?= e(implode(' · ', array_map(fn($l) => $l['nome'] . ' ' . $l['lote'] . ' (' . $l['quantidade'] . ')', $lotesPed))) ?></p><?php endif; ?>
      <?php if (\App\Core\Auth::pode('deposito.ver') || \App\Core\Auth::pode('deposito.operar')): ?><a class="botao contorno pequeno" href="<?= url('/deposito/' . (int) $p['id']) ?>">Abrir no depósito</a><?php endif; ?>
    </section>
    <?php endif; ?>
    <section class="card">
      <h2>Histórico</h2>
      <ol class="linha-tempo">
        <?php foreach ($historico as $h): ?><li><strong><?= e(PedidoService::SITUACOES[$h['situacao']][0] ?? ($h['situacao'] === 'deposito' ? 'Depósito' : $h['situacao'])) ?></strong><span><?= e($h['texto']) ?></span><small><?= data_br($h['criado_em'], true) ?> · <?= pessoa($h['usuario_nome'] ?? null, null, false, 18, 'Sistema') ?></small></li><?php endforeach; ?>
      </ol>
    </section>
  </aside>
</div>
