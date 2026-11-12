<?php
/**
 * Painel do e-commerce (Tray), em blocos como a Início.
 * Variáveis: $painel (EcommerceService::painel), $pedidos (recentes, sem dados pessoais)
 */
use App\Core\Visao;

$p = $painel;
$a = $p['atual'];
$icones = asset('img/icones.svg');
$n = fn($v) => number_format((float) $v, 0, ',', '.');
$var = function (?float $v): string {
    if ($v === null) {
        return '<span class="mk-var neutra">sem comparação</span>';
    }
    $classe = $v >= 0 ? 'sobe' : 'desce';
    return '<span class="mk-var ' . $classe . '">' . ($v >= 0 ? '▲' : '▼') . ' ' . number_format(abs($v), 1, ',', '.') . '%</span>';
};
$mesAtual = $p['mes'] === date('Y-m');
$acoes = (\App\Core\Auth::pode('config.gerenciar') ? '<form method="post" action="' . url('/marketing/ecommerce/testar') . '">' . csrf_campo() . '<button class="botao contorno" type="submit">Testar conexão</button></form>' : '')
    . '<form method="post" action="' . url('/marketing/ecommerce/atualizar') . '">' . csrf_campo() . '<button class="botao contorno" type="submit"><svg class="icone"><use href="' . $icones . '#nuvem"/></svg>Atualizar agora</button></form>'
    . '<button class="botao contorno" type="button" onclick="window.print()"><svg class="icone"><use href="' . $icones . '#imprimir"/></svg>Imprimir</button>';
$situacaoCor = ['Aguardando pagamento' => 'laranja', 'Pago' => 'lima', 'Em separação' => 'cinza', 'Enviado' => 'cinza', 'Entregue' => 'verde'];
$maxProduto = max(1, (float) ($p['produtos'][0]['faturamento'] ?? 1));
?>
<?= Visao::renderizar('marketing/_cabecalho', ['aba' => 'ecommerce', 'mes' => $p['mes'], 'titulo' => 'E-commerce',
    'sobretitulo' => 'LOJA VIRTUAL · TRAY', 'acoes' => $acoes,
    'subtitulo' => 'Desempenho da loja virtual. Atualizado em ' . ($p['ultima_sincronizacao'] ? data_br($p['ultima_sincronizacao'], true) : '—') . '.']) ?>

<?php if ($p['simulado']): ?>
  <p class="aviso mk-aviso-simulado">Dados simulados da loja. Quando a Tray liberar o aplicativo, o admin coloca as chaves no config.php e troca "Origem dos números do e-commerce" para <code>tray</code> em Configurações.</p>
<?php endif; ?>

<div class="bento">
  <section class="b-card b-escuro span-4">
    <header class="b-topo"><span>Faturamento <?= $mesAtual ? 'do mês até hoje' : 'do mês' ?></span><span class="b-icone-redondo lima"><svg class="icone"><use href="<?= $icones ?>#moeda"/></svg></span></header>
    <strong class="b-numero-grande mk-dinheiro"><?= dinheiro((float) $a['faturamento']) ?></strong>
    <p class="b-sub"><?= $var($p['variacao']['faturamento']) ?> vs. mesmo período do mês anterior</p>
    <?php if ($p['projecao']): ?><p class="b-sub">Projeção do mês: <strong class="mk-lima"><?= dinheiro($p['projecao']) ?></strong></p><?php endif; ?>
    <?php if ($mesAtual): ?><span class="b-link">Hoje: <?= dinheiro((float) $p['hoje']['faturamento']) ?> em <?= $n($p['hoje']['pedidos']) ?> pedido(s)</span><?php endif; ?>
  </section>
  <section class="b-card b-kpi span-2">
    <header class="b-topo"><span>Pedidos</span><span class="b-icone-redondo"><svg class="icone"><use href="<?= $icones ?>#carrinho"/></svg></span></header>
    <strong class="b-valor"><?= $n($a['pedidos']) ?></strong><small><?= $var($p['variacao']['pedidos']) ?></small>
  </section>
  <section class="b-card b-kpi span-2">
    <header class="b-topo"><span>Ticket médio</span><span class="b-icone-redondo"><svg class="icone"><use href="<?= $icones ?>#etiqueta"/></svg></span></header>
    <strong class="b-valor"><?= dinheiro((float) $a['ticket_medio']) ?></strong><small><?= $var($p['variacao']['ticket']) ?></small>
  </section>
  <section class="b-card b-kpi span-2">
    <header class="b-topo"><span>Clientes que compraram</span><span class="b-icone-redondo"><svg class="icone"><use href="<?= $icones ?>#usuario"/></svg></span></header>
    <strong class="b-valor"><?= $n($a['novos_clientes']) ?></strong><small><?= $var($p['variacao']['novos']) ?></small>
  </section>
  <section class="b-card b-kpi b-lima span-2">
    <header class="b-topo"><span>Conversão</span><span class="b-icone-redondo escuro"><svg class="icone"><use href="<?= $icones ?>#alvo"/></svg></span></header>
    <strong class="b-valor"><?= $a['conversao'] !== null ? number_format((float) $a['conversao'], 2, ',', '.') . '%' : '—' ?></strong>
    <small><?= $a['visitas'] ? $n($a['visitas']) . ' visitas' : 'a loja não informou visitas' ?></small>
  </section>

  <section class="b-card span-8">
    <header class="b-topo"><h2>Faturamento por dia</h2><span class="b-chip"><?= $n(count($p['diario'])) ?> dias</span></header>
    <div class="mk-grafico"><canvas id="grafico-diario" aria-label="Faturamento por dia do mês"></canvas></div>
  </section>
  <section class="b-card span-4">
    <header class="b-topo"><h2>Últimos 12 meses</h2></header>
    <div class="mk-grafico"><canvas id="grafico-meses" aria-label="Faturamento dos últimos 12 meses"></canvas></div>
  </section>

  <section class="b-card span-6">
    <header class="b-topo"><h2>Mais vendidos do mês</h2><span class="b-chip">Top <?= count($p['produtos']) ?></span></header>
    <?php if (!$p['produtos']): ?><p class="b-vazio">Sem vendas registradas neste mês.</p><?php endif; ?>
    <ol class="mk-ranking">
      <?php foreach ($p['produtos'] as $i => $pr): ?>
        <li>
          <span class="mk-posicao"><?= $i + 1 ?></span>
          <div><strong><?= e($pr['nome']) ?></strong><div class="b-barra"><i style="width: <?= round($pr['faturamento'] / $maxProduto * 100) ?>%"></i></div></div>
          <span class="mk-ranking-num"><b><?= dinheiro((float) $pr['faturamento']) ?></b><small><?= $n($pr['quantidade']) ?> un</small></span>
        </li>
      <?php endforeach; ?>
    </ol>
  </section>
  <section class="b-card span-6">
    <header class="b-topo"><h2>Pedidos recentes</h2><span class="b-chip">sem dados pessoais</span></header>
    <?php if (!$pedidos): ?><p class="b-vazio">Não foi possível carregar os pedidos da loja agora.</p><?php else: ?>
      <div class="tabela-rolagem">
        <table class="b-tabela">
          <thead><tr><th>Pedido</th><th>Quando</th><th>Cidade</th><th class="direita">Valor</th><th>Situação</th></tr></thead>
          <tbody>
          <?php foreach ($pedidos as $pd): ?>
            <tr><td>#<?= e($pd['numero']) ?></td><td><?= data_br($pd['data'], true) ?></td><td class="suave"><?= $pd['cidade'] !== '' ? e($pd['cidade']) . '/' . e($pd['uf']) : '—' ?></td>
              <td class="direita"><strong><?= dinheiro((float) $pd['valor']) ?></strong></td><td><span class="selo <?= $situacaoCor[$pd['situacao']] ?? 'cinza' ?>"><?= e($pd['situacao']) ?></span></td></tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>
</div>

<script type="application/json" id="dados-ecommerce"><?= json_encode([
    'diario' => array_map(fn($d) => ['dia' => (int) substr($d['data'], 8), 'valor' => (float) $d['faturamento'], 'pedidos' => (int) $d['pedidos']], $p['diario']),
    'meses' => array_map(fn($m) => ['mes' => $m['mes'], 'valor' => (float) $m['faturamento']], $p['meses']),
    'mesAtualParcial' => $mesAtual,
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?></script>
