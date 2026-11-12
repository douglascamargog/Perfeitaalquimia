<?php
/** Página do pedido. Variáveis: $p, $itens, $historico, $credito (ou null), $erpSimulado */
use App\Core\Auth;
use App\Services\PedidoService;

[$rotulo, $cor] = PedidoService::SITUACOES[$p['situacao']] ?? [$p['situacao'], 'cinza'];
$origens = ['catalogo' => 'Catálogo (cliente)', 'sistema' => 'Sistema (equipe)', 'app' => 'App do externo'];
?>
<div class="cabecalho-secao nao-imprimir">
  <div><div class="sobretitulo">PEDIDO · <?= e(mb_strtoupper($origens[$p['origem']] ?? $p['origem'])) ?></div><h1>Pedido #<?= (int) $p['id'] ?> <span class="selo <?= $cor ?>"><?= $rotulo ?></span></h1></div>
  <div class="acoes">
    <a class="botao contorno" href="<?= url('/pedidos') ?>">← Pedidos</a>
    <button class="botao contorno" type="button" onclick="window.print()">Imprimir</button>
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
      <div><dt>Cliente</dt><dd><?= e($p['razao_social']) ?><?= $p['cnpj'] ? '<br><small>CNPJ ' . e(cnpj_formatado($p['cnpj'])) . '</small>' : '' ?></dd></div>
      <div><dt>Cidade</dt><dd><?= e($p['cidade']) ?>/<?= e($p['uf']) ?></dd></div>
      <div><dt>Vendedor</dt><dd><?= e($p['vendedor'] ?? '—') ?></dd></div>
      <div><dt>Tabela</dt><dd><?= e($p['tabela']) ?></dd></div>
      <div><dt>Pagamento</dt><dd><?= e($p['forma']) ?><?= $p['condicao'] ? ' · ' . e($p['condicao']) : '' ?></dd></div>
      <div><dt>ERP</dt><dd><?= $p['omie_numero'] ? 'Orçamento nº ' . e($p['omie_numero']) : '<span class="suave">envio pendente</span>' ?><?= $p['numero_nf'] ? '<br>NF ' . e($p['numero_nf']) . ' · ' . data_br($p['faturado_em']) : '' ?></dd></div>
    </dl>
    <?php if ($p['motivo_recusa']): ?><p class="aviso erro">Recusado: <?= e($p['motivo_recusa']) ?></p><?php endif; ?>
    <table class="tabela ped-itens">
      <thead><tr><th>Produto</th><th class="direita">Qtd.</th><th class="direita">Preço</th><th class="direita">Total</th></tr></thead>
      <tbody>
      <?php foreach ($itens as $i): ?><tr><td><?= e($i['nome']) ?></td><td class="direita"><?= (int) $i['quantidade'] ?></td><td class="direita"><?= dinheiro((float) $i['preco_unitario']) ?></td><td class="direita"><?= dinheiro((float) $i['total']) ?></td></tr><?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr><td colspan="3" class="direita">Subtotal</td><td class="direita"><?= dinheiro((float) $p['subtotal']) ?></td></tr>
        <?php if ((float) $p['desconto'] > 0): ?><tr><td colspan="3" class="direita">Desconto</td><td class="direita">−<?= dinheiro((float) $p['desconto']) ?></td></tr><?php endif; ?>
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
    <section class="card">
      <h2>Histórico</h2>
      <ol class="linha-tempo">
        <?php foreach ($historico as $h): ?><li><strong><?= e(PedidoService::SITUACOES[$h['situacao']][0] ?? $h['situacao']) ?></strong><span><?= e($h['texto']) ?></span><small><?= data_br($h['criado_em'], true) ?> · <?= e($h['usuario_nome'] ?? 'Sistema') ?></small></li><?php endforeach; ?>
      </ol>
    </section>
  </aside>
</div>
