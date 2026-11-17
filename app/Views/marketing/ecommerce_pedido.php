<?php
/**
 * Um pedido do e-commerce: itens, valores, pagamento, envio, marketplace e o caminho no Depósito do One.
 * LGPD: sem nome, CPF, e-mail, telefone ou endereço do comprador (só cidade/UF).
 * Variáveis: $p (EcommerceService::pedido)
 */
use App\Core\Visao;
use App\Integrations\Tray\TrayTexto;

[$sitNome] = TrayTexto::SITUACOES[$p['status_tipo']] ?? [$p['status']];
$etapasDep = ['aguardando' => 'A separar', 'separando' => 'Separando', 'separado' => 'Separado', 'despachado' => 'Despachado'];
$custo = $p['custo'] !== null ? (float) $p['custo'] : null;
$acoes = '<a class="botao contorno" href="' . url('/marketing/ecommerce/pedidos') . '">← Pedidos</a>'
    . '<button class="botao contorno" type="button" onclick="window.print()">Imprimir</button>';
?>
<?= Visao::renderizar('marketing/_cabecalho', ['aba' => 'ecommerce', 'mes' => date('Y-m'), 'semMes' => true, 'titulo' => 'Pedido #' . $p['tray_id'],
    'sobretitulo' => 'E-COMMERCE · ' . mb_strtoupper(TrayTexto::nomeCanal($p['canal'], $p['canal_nome'])), 'acoes' => $acoes,
    'subtitulo' => 'Feito em ' . data_br($p['data_hora'], (bool) $p['detalhado']) . ($p['cidade'] ? ' · ' . $p['cidade'] . '/' . $p['uf'] : '')]) ?>

<?php if (!$p['detalhado']): ?><p class="aviso">Os itens e a cidade deste pedido ainda não foram lidos da loja (o One completa na próxima atualização).</p><?php endif; ?>
<?php if ($p['status_tipo'] === 'cancelado'): ?><p class="aviso erro">Pedido cancelado na loja.</p><?php endif; ?>

<div class="bento" data-eco-detalhe>
  <section class="b-card span-8">
    <header class="b-topo"><h2>Produtos</h2><span class="b-chip"><?= (int) $p['itens_qtd'] ?> unidade(s)</span></header>
    <div class="tabela-rolagem">
      <table class="b-tabela">
        <thead><tr><th></th><th>Produto</th><th>Ref.</th><th class="direita">Qtd</th><th class="direita">Preço</th><th class="direita">Total</th></tr></thead>
        <tbody>
        <?php foreach ($p['itens'] as $i): ?>
          <tr>
            <td><?php if ($i['imagem']): ?><img class="eco-foto" src="<?= e($i['imagem']) ?>" alt="" width="40" height="40" loading="lazy"><?php endif; ?></td>
            <td><strong><?= e($i['nome']) ?></strong><?= $i['variacao'] ? '<br><small class="suave">' . e($i['variacao']) . '</small>' : '' ?><?= $i['categoria'] ? '<br><small class="suave">' . e($i['categoria']) . '</small>' : '' ?></td>
            <td class="pequeno"><?= e($i['produto_ref']) ?><?= $i['ean'] ? '<br><small class="suave">EAN ' . e($i['ean']) . '</small>' : '' ?></td>
            <td class="direita"><?= (int) $i['quantidade'] ?></td><td class="direita"><?= dinheiro((float) $i['preco']) ?></td>
            <td class="direita"><strong><?= dinheiro($i['preco'] * $i['quantidade']) ?></strong></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$p['itens']): ?><tr><td colspan="6" class="suave">Itens ainda não lidos.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>
  <section class="b-card span-4">
    <header class="b-topo"><h2>Valores</h2></header>
    <dl class="eco-valores">
      <dt>Produtos</dt><dd><?= dinheiro((float) $p['subtotal']) ?></dd>
      <dt>Desconto<?= $p['cupom'] ? ' (cupom <code>' . e($p['cupom']) . '</code>)' : '' ?></dt><dd>− <?= dinheiro((float) $p['desconto']) ?></dd>
      <dt>Frete</dt><dd><?= dinheiro((float) $p['frete']) ?></dd>
      <dt class="forte">Total</dt><dd class="forte"><?= dinheiro((float) $p['total']) ?></dd>
      <?php if ($custo !== null): ?><dt>Custo dos produtos</dt><dd><?= dinheiro($custo) ?></dd>
        <dt>Margem estimada</dt><dd><?= dinheiro($p['total'] - $p['frete'] - $custo) ?></dd><?php endif; ?>
    </dl>
  </section>

  <section class="b-card span-4">
    <header class="b-topo"><h2>Situação</h2></header>
    <p><span class="selo eco-sit sit-<?= e($p['status_tipo']) ?>"><?= e($sitNome) ?></span> <small class="suave">na loja: <?= e($p['status']) ?></small></p>
    <dl class="eco-valores">
      <dt>Pagamento</dt><dd><?= e($p['pagamento'] ?: '—') ?></dd>
      <dt>Pago em</dt><dd><?= $p['pago_em'] ? data_br($p['pago_em']) : '—' ?></dd>
      <dt>Nota fiscal</dt><dd><?= $p['tem_nf'] ? '✅ ' . e($p['nf_numero'] ?: 'emitida') : 'ainda não' ?></dd>
    </dl>
  </section>
  <section class="b-card span-4">
    <header class="b-topo"><h2>Envio</h2></header>
    <dl class="eco-valores">
      <dt>Forma</dt><dd><?= e($p['envio'] ?: '—') ?></dd>
      <dt>Transportadora</dt><dd><?= e($p['transportadora'] ?: '—') ?></dd>
      <dt>Prazo de entrega</dt><dd><?= $p['prazo_entrega'] ? data_br($p['prazo_entrega']) : '—' ?></dd>
      <dt>Enviado em</dt><dd><?= $p['enviado_em'] ? data_br($p['enviado_em']) : '—' ?></dd>
      <dt>Entregue</dt><dd><?= $p['entregue'] ? 'sim' : 'não' ?></dd>
      <dt>Destino</dt><dd><?= $p['cidade'] ? e($p['cidade']) . '/' . e($p['uf']) : '—' ?></dd>
    </dl>
  </section>
  <section class="b-card span-4" data-eco-marketplace>
    <header class="b-topo"><h2>Canal de venda</h2></header>
    <p><span class="eco-canal canal-<?= e($p['canal']) ?>"><?= e(TrayTexto::nomeCanal($p['canal'], $p['canal_nome'])) ?></span></p>
    <dl class="eco-valores">
      <?php if ($p['marketplace_pedido']): ?><dt>Pedido no marketplace</dt><dd><?= e($p['marketplace_pedido']) ?></dd><?php endif; ?>
      <?php if ($p['marketplace_envio']): ?><dt>Tipo de envio</dt><dd><?= e($p['marketplace_envio']) ?></dd><?php endif; ?>
      <?php if ($p['marketplace_status']): ?><dt>Status no marketplace</dt><dd><?= e($p['marketplace_status']) ?></dd><?php endif; ?>
      <?php if ($p['parceiro']): ?><dt>Parceiro</dt><dd><?= e($p['parceiro']) ?></dd><?php endif; ?>
      <dt>Depósito do One</dt>
      <dd><?php if ($p['deposito']): ?><a href="<?= url('/deposito/' . (int) $p['deposito']['id']) ?>"><?= $p['deposito']['situacao'] === 'cancelado' ? 'cancelado' : e($etapasDep[$p['deposito']['expedicao']] ?? 'aguardando a nota') ?> →</a>
        <?php else: ?><span class="suave">ainda não entrou</span><?php endif; ?></dd>
    </dl>
  </section>
</div>
