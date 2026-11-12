<?php
/**
 * Painel de vendas (pedidos faturados).
 * Variáveis: $f (filtros), $periodo, $periodoAnterior, $atual, $anterior, $aFaturar, $meses, $vendedores, $funcoes,
 *            $ufs, $origens, $clientes, $produtos, $semCompra, $metas (ou null), $mes, $opcoes
 */
use App\Services\VendasService;

$icones = asset('img/icones.svg');
$n = fn($v) => number_format((float) $v, 0, ',', '.');
$curto = fn(float $v) => $v >= 1000000 ? 'R$ ' . number_format($v / 1000000, 2, ',', '.') . ' mi' : ($v >= 10000 ? 'R$ ' . number_format($v / 1000, 1, ',', '.') . ' mil' : dinheiro($v));
$var = function (float $atual, float $anterior): string {
    if ($anterior <= 0) {
        return '<span class="mk-var neutra">sem comparação</span>';
    }
    $v = ($atual - $anterior) / $anterior * 100;
    return '<span class="mk-var ' . ($v >= 0 ? 'sobe' : 'desce') . '">' . ($v >= 0 ? '▲' : '▼') . ' ' . number_format(abs($v), 1, ',', '.') . '%</span>';
};
$sel = fn(string $campo, $valor) => (string) ($f[$campo] ?? '') === (string) $valor ? 'selected' : '';
$query = http_build_query(array_filter(['periodo' => $periodo] + ($periodo === 'livre' ? ['de' => $f['de'], 'ate' => $f['ate']] : []) + array_diff_key($f, ['de' => 1, 'ate' => 1])));
$origemNome = ['catalogo' => 'Catálogo', 'sistema' => 'Sistema', 'app' => 'App externo'];
$linkCarteira = fn(int $dias) => url('/carteira/lista') . '?' . http_build_query(array_filter(['tipo' => 'cliente', 'compra' => "sem$dias", 'uf' => $f['uf'], 'responsavel' => $f['vendedor'], 'funcao' => $f['funcao'], 'regiao' => $f['regiao']]));
?>
<div class="cabecalho-secao">
  <div>
    <div class="sobretitulo">GESTÃO · VENDAS FATURADAS</div>
    <h1>Painel de vendas</h1>
    <p><?= data_br($f['de']) ?> a <?= data_br($f['ate']) ?> · comparado com <?= data_br($periodoAnterior[0]) ?> a <?= data_br($periodoAnterior[1]) ?></p>
  </div>
  <div class="acoes nao-imprimir">
    <button class="botao contorno" type="button" onclick="window.print()"><svg class="icone"><use href="<?= $icones ?>#imprimir"/></svg>Imprimir</button>
    <a class="botao contorno" href="<?= url('/painel/exportar.csv') ?>?<?= e($query) ?>"><svg class="icone"><use href="<?= $icones ?>#baixar"/></svg>CSV</a>
  </div>
</div>

<form class="filtros card nao-imprimir" method="get" action="<?= url('/painel') ?>" data-filtros>
  <div class="filtros-grade">
    <select name="periodo" aria-label="Período" data-periodo><?php foreach (VendasService::PERIODOS as $k => $nome): ?><option value="<?= $k ?>" <?= $periodo === $k ? 'selected' : '' ?>><?= $nome ?></option><?php endforeach; ?></select>
    <input type="date" name="de" value="<?= e($f['de']) ?>" aria-label="De" data-livre <?= $periodo === 'livre' ? '' : 'hidden' ?>>
    <input type="date" name="ate" value="<?= e($f['ate']) ?>" aria-label="Até" data-livre <?= $periodo === 'livre' ? '' : 'hidden' ?>>
    <select name="vendedor" aria-label="Vendedor"><option value="">Todos os vendedores</option><?php foreach ($opcoes['vendedores'] as $o): ?><option value="<?= (int) $o['id'] ?>" <?= $sel('vendedor', $o['id']) ?>><?= e($o['nome']) ?></option><?php endforeach; ?></select>
    <select name="funcao" aria-label="Função"><option value="">Todas as funções</option><?php foreach ($opcoes['funcoes'] as $o): ?><option value="<?= (int) $o['id'] ?>" <?= $sel('funcao', $o['id']) ?>><?= e($o['nome']) ?></option><?php endforeach; ?></select>
    <select name="regiao" aria-label="Região"><option value="">Todas as regiões</option><?php foreach ($opcoes['regioes'] as $o): ?><option value="<?= (int) $o['id'] ?>" <?= $sel('regiao', $o['id']) ?>><?= e($o['nome']) ?></option><?php endforeach; ?></select>
    <select name="uf" aria-label="UF"><option value="">Todas as UFs</option><?php foreach ($opcoes['ufs'] as $uf): ?><option <?= $sel('uf', $uf) ?>><?= e($uf) ?></option><?php endforeach; ?></select>
    <select name="origem" aria-label="Origem"><option value="">Todas as origens</option><?php foreach ($origemNome as $k => $nome): ?><option value="<?= $k ?>" <?= $sel('origem', $k) ?>><?= $nome ?></option><?php endforeach; ?></select>
  </div>
  <?php if (array_filter(array_diff_key($f, ['de' => 1, 'ate' => 1])) || $periodo !== 'mes'): ?><a class="pequeno" href="<?= url('/painel') ?>">Limpar filtros</a><?php endif; ?>
</form>

<div class="bento">
  <section class="b-card b-escuro span-4" data-card="faturamento">
    <header class="b-topo"><span>Faturado no período</span><span class="b-icone-redondo lima"><svg class="icone"><use href="<?= $icones ?>#moeda"/></svg></span></header>
    <strong class="b-numero-grande mk-dinheiro" data-faturamento><?= dinheiro($atual['faturamento']) ?></strong>
    <p class="b-sub"><?= $var($atual['faturamento'], $anterior['faturamento']) ?> vs. período anterior (<?= $curto($anterior['faturamento']) ?>)</p>
    <?php if ($metas && $metas['equipe'] > 0 && !$f['vendedor'] && !$f['funcao'] && !$f['uf'] && !$f['regiao'] && !$f['origem']): ?>
      <p class="b-sub">Meta da equipe: <strong class="mk-lima"><?= number_format($atual['faturamento'] / $metas['equipe'] * 100, 0, ',', '.') ?>%</strong> de <?= $curto($metas['equipe']) ?></p>
    <?php endif; ?>
    <span class="b-link">A faturar (aprovados): <?= dinheiro((float) $aFaturar['valor']) ?> em <?= $n($aFaturar['pedidos']) ?> pedido(s)</span>
  </section>
  <section class="b-card b-kpi span-2"><header class="b-topo"><span>Pedidos</span></header><strong class="b-valor" data-pedidos><?= $n($atual['pedidos']) ?></strong><small><?= $var($atual['pedidos'], $anterior['pedidos']) ?></small></section>
  <section class="b-card b-kpi span-2"><header class="b-topo"><span>Ticket médio</span></header><strong class="b-valor"><?= dinheiro($atual['ticket']) ?></strong><small><?= $var($atual['ticket'], $anterior['ticket']) ?></small></section>
  <section class="b-card b-kpi span-2"><header class="b-topo"><span>Clientes que compraram</span></header><strong class="b-valor" data-clientes><?= $n($atual['clientes']) ?></strong><small><?= $var($atual['clientes'], $anterior['clientes']) ?></small></section>
  <section class="b-card b-kpi b-lima span-2"><header class="b-topo"><span>Recorrência</span></header><strong class="b-valor" data-recorrencia><?= number_format($atual['recorrencia'], 1, ',', '.') ?>%</strong><small><?= $n($atual['recorrentes']) ?> cliente(s) compraram 2× ou mais</small></section>

  <section class="b-card span-8">
    <header class="b-topo"><h2>Faturamento mês a mês</h2><span class="b-chip">12 meses · linha = meta da equipe</span></header>
    <div class="mk-grafico"><canvas id="grafico-meses" aria-label="Faturamento mês a mês"></canvas></div>
  </section>
  <section class="b-card span-4">
    <header class="b-topo"><h2>Sem compra há…</h2></header>
    <div class="pn-sem-compra">
      <?php foreach ($semCompra as $dias => $qtd): ?>
        <a href="<?= e($linkCarteira($dias)) ?>"><strong><?= $n($qtd) ?></strong><span>clientes · <?= $dias ?>+ dias</span></a>
      <?php endforeach; ?>
    </div>
    <a class="b-chip" href="<?= url('/recompra') ?>">Ver fila de recompra →</a>
  </section>

  <section class="b-card span-6">
    <header class="b-topo"><h2>Por vendedor</h2><?php if ($mes): ?><span class="b-chip">com a meta de <?= e($mes) ?></span><?php endif; ?></header>
    <div class="tabela-rolagem">
      <table class="b-tabela pn-tabela">
        <thead><tr><th>Vendedor</th><th class="direita">Faturado</th><?php if ($metas): ?><th>Meta</th><?php endif; ?><th class="direita">Pedidos</th><th class="direita">Ticket</th><th class="direita">Clientes</th></tr></thead>
        <tbody>
        <?php foreach ($vendedores as $v): $meta = $metas ? (float) ($metas['vendedor'][$v['id']] ?? 0) : 0; ?>
          <tr><td><?= e($v['nome']) ?></td><td class="direita"><strong><?= dinheiro($v['valor']) ?></strong></td>
            <?php if ($metas): ?><td><?php if ($meta > 0): $pct = $v['valor'] / $meta * 100; ?><div class="pn-meta"><div class="b-barra"><i style="width: <?= min(100, round($pct)) ?>%"></i></div><small class="<?= $pct >= 100 ? 'ok' : '' ?>"><?= number_format($pct, 0, ',', '.') ?>% de <?= $curto($meta) ?></small></div><?php else: ?><span class="suave pequeno">sem meta</span><?php endif; ?></td><?php endif; ?>
            <td class="direita"><?= $n($v['pedidos']) ?></td><td class="direita"><?= dinheiro($v['ticket']) ?></td><td class="direita"><?= $n($v['clientes']) ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$vendedores): ?><tr><td colspan="6" class="suave">Sem vendas no período.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>
  <section class="b-card span-6">
    <header class="b-topo"><h2>Por função</h2></header>
    <div class="tabela-rolagem">
      <table class="b-tabela pn-tabela">
        <thead><tr><th>Função</th><th class="direita">Faturado</th><?php if ($metas): ?><th>Meta</th><?php endif; ?><th class="direita">Pedidos</th><th class="direita">Ticket</th><th class="direita">Clientes</th></tr></thead>
        <tbody>
        <?php foreach ($funcoes as $v): $meta = $metas ? (float) ($metas['funcao'][$v['id']] ?? 0) : 0; ?>
          <tr><td><?= e($v['nome']) ?></td><td class="direita"><strong><?= dinheiro($v['valor']) ?></strong></td>
            <?php if ($metas): ?><td><?php if ($meta > 0): $pct = $v['valor'] / $meta * 100; ?><div class="pn-meta"><div class="b-barra"><i style="width: <?= min(100, round($pct)) ?>%"></i></div><small class="<?= $pct >= 100 ? 'ok' : '' ?>"><?= number_format($pct, 0, ',', '.') ?>% de <?= $curto($meta) ?></small></div><?php else: ?><span class="suave pequeno">sem meta</span><?php endif; ?></td><?php endif; ?>
            <td class="direita"><?= $n($v['pedidos']) ?></td><td class="direita"><?= dinheiro($v['ticket']) ?></td><td class="direita"><?= $n($v['clientes']) ?></td></tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>

  <section class="b-card span-8">
    <header class="b-topo"><h2>Por UF</h2></header>
    <div class="mk-grafico"><canvas id="grafico-ufs" aria-label="Faturamento por UF"></canvas></div>
  </section>
  <section class="b-card span-4">
    <header class="b-topo"><h2>Por origem</h2></header>
    <div class="mk-grafico"><canvas id="grafico-origens" aria-label="Faturamento por origem"></canvas></div>
  </section>

  <section class="b-card span-6">
    <header class="b-topo"><h2>Top 10 clientes</h2></header>
    <ol class="mk-ranking">
      <?php $max = max(1, $clientes[0]['valor'] ?? 1); foreach ($clientes as $i => $c): ?>
        <li><span class="mk-posicao"><?= $i + 1 ?></span><div><a href="<?= url('/carteira/lista') ?>?abrir=<?= (int) $c['id'] ?>"><strong><?= e($c['nome']) ?></strong></a><div class="b-barra"><i style="width: <?= round($c['valor'] / $max * 100) ?>%"></i></div></div>
          <span class="mk-ranking-num"><b><?= dinheiro($c['valor']) ?></b><small><?= $n($c['pedidos']) ?> pedidos · <?= e($c['cidade']) ?>/<?= e($c['uf']) ?></small></span></li>
      <?php endforeach; ?>
    </ol>
  </section>
  <section class="b-card span-6">
    <header class="b-topo"><h2>Top 10 produtos</h2></header>
    <ol class="mk-ranking">
      <?php $max = max(1, $produtos[0]['valor'] ?? 1); foreach ($produtos as $i => $p): ?>
        <li><span class="mk-posicao"><?= $i + 1 ?></span><div><strong><?= e($p['nome']) ?></strong><div class="b-barra"><i style="width: <?= round($p['valor'] / $max * 100) ?>%"></i></div></div>
          <span class="mk-ranking-num"><b><?= dinheiro($p['valor']) ?></b><small><?= $n($p['quantidade']) ?> un</small></span></li>
      <?php endforeach; ?>
    </ol>
  </section>
</div>

<script type="application/json" id="dados-painel"><?= json_encode([
    'meses' => $meses,
    'ufs' => array_map(fn($u) => ['nome' => $u['nome'], 'valor' => $u['valor']], $ufs),
    'origens' => array_map(fn($o) => ['nome' => $origemNome[$o['nome']] ?? $o['nome'], 'valor' => $o['valor']], $origens),
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?></script>
