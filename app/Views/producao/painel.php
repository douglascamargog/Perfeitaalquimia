<?php
/**
 * Painel da Produção. Variáveis: $p (ProducaoService::painel), $pode (pode alterar)
 */
use App\Core\Visao;
use App\Services\ProducaoService as P;

$icones = asset('img/icones.svg');
$n = fn($v) => number_format((float) $v, 0, ',', '.');
?>
<div class="cabecalho-secao">
  <div><div class="sobretitulo">PRODUÇÃO · FÁBRICA</div><h1>Produção</h1>
    <p>Matérias-primas que precisam de compra, produtos para reabastecer e o que está programado.</p></div>
  <div class="acoes nao-imprimir">
    <?php if ($pode): ?><a class="botao" href="<?= url('/producao/ordens/nova') ?>"><svg class="icone"><use href="<?= $icones ?>#mais"/></svg>Nova ordem</a><?php endif; ?>
    <button class="botao contorno" type="button" onclick="window.print()"><svg class="icone"><use href="<?= $icones ?>#imprimir"/></svg>Imprimir</button>
  </div>
</div>
<?= Visao::renderizar('producao/_abas', ['atual' => '/producao']) ?>

<div class="bento">
  <section class="b-card b-escuro span-4" data-card="mes">
    <header class="b-topo"><span>Produzido neste mês</span><span class="b-icone-redondo lima"><svg class="icone"><use href="<?= $icones ?>#fabrica"/></svg></span></header>
    <strong class="b-numero-grande" data-unidades-mes><?= $n($p['mes']['unidades']) ?> <small>un</small></strong>
    <p class="b-sub"><?= $n($p['mes']['ordens']) ?> ordem(ns) concluída(s) · custo de matéria-prima <?= dinheiro((float) $p['mes']['custo']) ?></p>
  </section>
  <section class="b-card b-kpi span-2 <?= $p['materias_alerta'] ? 'pr-kpi-alerta' : '' ?>"><header class="b-topo"><span>Matérias-primas em alerta</span></header>
    <strong class="b-valor" data-mp-alerta><?= count($p['materias_alerta']) ?></strong><small>de <?= $n($p['totais']['materias']) ?> cadastradas</small></section>
  <section class="b-card b-kpi span-2 <?= $p['reabastecer'] ? 'pr-kpi-alerta' : '' ?>"><header class="b-topo"><span>Produtos para reabastecer</span></header>
    <strong class="b-valor" data-reabastecer-qtd><?= count($p['reabastecer']) ?></strong><small>abaixo do estoque mínimo</small></section>
  <section class="b-card b-kpi span-2"><header class="b-topo"><span>Ordens planejadas</span></header>
    <strong class="b-valor"><?= count($p['planejadas']) ?></strong><small><a href="<?= url('/producao/ordens?situacao=planejada') ?>">ver todas</a></small></section>
  <section class="b-card b-kpi b-lima span-2"><header class="b-topo"><span>Estoque de matéria-prima</span></header>
    <strong class="b-valor"><?= dinheiro($p['totais']['valor_estoque']) ?></strong><small>pelo último custo</small></section>

  <section class="b-card span-6" data-card="materias-alerta">
    <header class="b-topo"><h2>Matérias-primas: comprar</h2><a class="b-chip" href="<?= url('/producao/materias?situacao=alerta') ?>">ver lista →</a></header>
    <?php if (!$p['materias_alerta']): ?><p class="suave">Nenhuma matéria-prima abaixo do mínimo ou acabando. 👍</p><?php endif; ?>
    <ul class="pr-alertas">
      <?php foreach (array_slice($p['materias_alerta'], 0, 8) as $m): ?>
        <li>
          <a href="<?= url('/producao/materias/' . (int) $m['id']) ?>"><strong><?= e($m['nome']) ?></strong>
            <small><?= P::qtd($m['estoque'], $m['unidade']) ?> em estoque · mínimo <?= P::qtd($m['estoque_minimo'], $m['unidade']) ?></small></a>
          <?php if ($m['abaixo']): ?><span class="selo vermelho">abaixo do mínimo</span><?php endif; ?>
          <?php if ($m['dias_restantes'] !== null): ?><span class="selo <?= $m['dias_restantes'] < 7 ? 'vermelho' : 'laranja' ?>">acaba em ~<?= (int) $m['dias_restantes'] ?> dia(s) · <?= data_br($m['acaba_em']) ?></span><?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ul>
  </section>

  <section class="b-card span-6" data-card="reabastecer">
    <header class="b-topo"><h2>Reabastecimento do produto acabado</h2><a class="b-chip" href="<?= url('/producao/acabados?situacao=abaixo') ?>">estoque acabado →</a></header>
    <?php if (!$p['reabastecer']): ?><p class="suave">Todos os produtos estão acima do estoque mínimo.</p><?php endif; ?>
    <ul class="pr-alertas">
      <?php foreach ($p['reabastecer'] as $a): ?>
        <li data-reabastecer="<?= (int) $a['id'] ?>">
          <a href="<?= url('/producao/acabados/' . (int) $a['id']) ?>"><strong><?= e($a['nome']) ?></strong>
            <small><?= $n($a['quantidade']) ?> un em estoque · mínimo <?= $n($a['minimo']) ?> · vende ~<?= number_format($a['venda_dia'], 1, ',', '.') ?>/dia</small></a>
          <?php if ($a['sugestao'] > 0 && $pode): ?>
            <form method="post" action="<?= url('/producao/reabastecer/' . (int) $a['id']) ?>"><?= csrf_campo() ?>
              <button class="botao lima pequeno" type="submit">Gerar ordem de <?= $n($a['sugestao']) ?> un</button></form>
          <?php elseif ($a['sugestao'] > 0): ?><span class="selo laranja">sugerido: <?= $n($a['sugestao']) ?> un</span>
          <?php else: ?><span class="selo cinza">ordem planejada: <?= $n($a['planejado']) ?> un</span><?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ul>
  </section>

  <section class="b-card span-6">
    <header class="b-topo"><h2>Unidades produzidas por mês</h2><span class="b-chip">últimos 6 meses</span></header>
    <div class="mk-grafico"><canvas id="grafico-producao" aria-label="Unidades produzidas por mês"
      data-meses='<?= e(json_encode(array_map(fn($m, $u) => ['mes' => $m, 'unidades' => $u], array_keys($p['por_mes']), array_values($p['por_mes'])))) ?>'></canvas></div>
  </section>
  <section class="b-card span-6" data-card="planejadas">
    <header class="b-topo"><h2>Programação</h2><?php if ($pode): ?><a class="b-chip" href="<?= url('/producao/ordens/nova') ?>">+ nova ordem</a><?php endif; ?></header>
    <?php if (!$p['planejadas']): ?><p class="suave">Nenhuma ordem planejada.</p><?php endif; ?>
    <ul class="pr-alertas">
      <?php foreach ($p['planejadas'] as $o): ?>
        <li><a href="<?= url('/producao/ordens/' . (int) $o['id']) ?>"><strong>OP <?= (int) $o['id'] ?> · <?= e($o['produto']) ?></strong>
          <small><?= $n($o['quantidade']) ?> un<?= $o['previsto_para'] ? ' · para ' . data_br($o['previsto_para']) : '' ?><?= $o['origem'] === 'reabastecimento' ? ' · reabastecimento' : '' ?></small></a></li>
      <?php endforeach; ?>
    </ul>
    <?php if ($p['ultimas']): ?>
      <h3 class="pr-sub">Últimas concluídas</h3>
      <ul class="pr-alertas pr-compacta">
        <?php foreach ($p['ultimas'] as $o): ?>
          <li><a href="<?= url('/producao/ordens/' . (int) $o['id']) ?>"><strong>OP <?= (int) $o['id'] ?> · <?= e($o['produto']) ?></strong>
            <small><?= $n($o['quantidade']) ?> un · <?= data_br($o['concluida_em']) ?><?= $o['lote'] ? ' · lote ' . e($o['lote']) : '' ?></small></a></li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>
  <?php if ($p['totais']['sem_ficha']): ?>
    <section class="b-card span-12 pr-dica nao-imprimir"><p><strong><?= $n($p['totais']['sem_ficha']) ?> produto(s)</strong> ainda não têm ficha técnica e não podem ser produzidos pelo sistema.
      <a href="<?= url('/producao/fichas?ficha=sem') ?>">Cadastrar fichas →</a></p></section>
  <?php endif; ?>
</div>
