<?php
/**
 * Depósito › Separação: pedidos com nota emitida para separar e despachar.
 * Variáveis: $aba (separar|separados|despachados), $tipo ('' | lojista | ecommerce | terceirizacao), $tipos (contagem), $fila, $resumo, $diasAtraso, $pode
 */
use App\Core\Visao;
use App\Services\DepositoService as D;
use App\Services\EcommercePedidoService as Eco;

$link = fn(string $a) => url('/deposito') . ($a === 'separar' ? '' : '?aba=' . $a);
$linkTipo = fn(string $t) => url('/deposito') . '?' . e(http_build_query(array_filter(['aba' => $aba === 'separar' ? '' : $aba, 'tipo' => $t])));
$atrasado = fn(array $p) => $p['expedicao'] !== 'despachado' && $p['expedicao_desde'] && strtotime($p['expedicao_desde']) < strtotime("-$diasAtraso days");
?>
<div class="cabecalho-secao">
  <div><div class="sobretitulo">DEPÓSITO · SEPARAÇÃO E EXPEDIÇÃO</div><h1>Pedidos para separar</h1>
    <p>Pedidos com nota emitida. Urgentes primeiro, depois os do e-commerce pelo horário limite do canal, depois do mais antigo para o mais novo.</p></div>
  <?php if ($pode): ?><div class="acoes"><form method="post" action="<?= url('/deposito/tray') ?>"><?= csrf_campo() ?><button class="botao contorno" type="submit" data-buscar-tray>↻ Buscar pedidos da Tray</button></form></div><?php endif; ?>
</div>
<?= Visao::renderizar('deposito/_abas', ['atual' => '/deposito']) ?>

<div class="dep-resumo" data-resumo>
  <a href="<?= $link('separar') ?>" class="<?= $aba === 'separar' ? 'ativo' : '' ?>"><b><?= (int) $resumo['a_separar'] ?></b><span>a separar</span></a>
  <a href="<?= $link('separar') ?>"><b><?= (int) $resumo['separando'] ?></b><span>separando</span></a>
  <a href="<?= $link('separados') ?>" class="<?= $aba === 'separados' ? 'ativo' : '' ?>"><b><?= (int) $resumo['separados'] ?></b><span>para despachar</span></a>
  <a href="<?= $link('despachados') ?>" class="<?= $aba === 'despachados' ? 'ativo' : '' ?>"><b><?= (int) $resumo['despachados_hoje'] ?></b><span>despachados hoje</span></a>
  <span class="<?= $resumo['atrasados'] ? 'alerta' : '' ?>"><b><?= (int) $resumo['atrasados'] ?></b><span>atrasados (+<?= $diasAtraso ?> dias)</span></span>
  <a href="<?= url('/deposito/recebimento') ?>"><b><?= (int) $resumo['receber'] ?></b><span>compras para receber</span></a>
</div>

<nav class="dep-tipos" aria-label="Tipo de pedido" data-tipos>
  <a href="<?= $linkTipo('') ?>" class="<?= $tipo === '' ? 'ativo' : '' ?>">Todos <b><?= array_sum($tipos) ?></b></a>
  <?php foreach (Eco::TIPOS as $t => $nomeTipo): ?>
    <a href="<?= $linkTipo($t) ?>" class="tipo-<?= $t ?> <?= $tipo === $t ? 'ativo' : '' ?>" data-tipo="<?= $t ?>"><?= $nomeTipo ?> <b><?= (int) $tipos[$t] ?></b></a>
  <?php endforeach; ?>
</nav>

<div class="dep-fila" data-fila="<?= e($aba) ?>">
  <?php foreach ($fila as $p): [$er, $ec] = D::ETAPAS[$p['expedicao']] ?? [$p['expedicao'], 'cinza'];
    $eco = $p['tipo_venda'] === 'ecommerce';
    $tipoP = $eco ? 'ecommerce' : ($p['terceirizados'] ? 'terceirizacao' : 'lojista');
    $prazo = $eco && $p['expedicao'] !== 'despachado' ? Eco::prazo($p['expedicao_limite']) : null; ?>
    <article class="dep-cartao tipo-<?= $tipoP ?> <?= $p['expedicao_prioridade'] ? 'urgente' : '' ?> <?= $atrasado($p) ? 'atrasado' : '' ?> <?= $prazo ? 'prazo-' . $prazo[1] : '' ?>" data-pedido="<?= (int) $p['id'] ?>" data-tipo-pedido="<?= $tipoP ?>">
      <a class="dep-cartao-link" href="<?= url('/deposito/' . (int) $p['id']) ?>">
        <span class="dep-cartao-topo"><strong>#<?= (int) $p['id'] ?></strong>
          <?= $p['numero_nf'] ? '<small>NF ' . e($p['numero_nf']) . '</small>' : '' ?>
          <span class="selo <?= $ec ?>"><?= e($er) ?></span>
          <?= $p['expedicao_prioridade'] ? '<span class="selo vermelho">urgente</span>' : '' ?>
          <?= $p['expedicao_aguarda_produto'] ? '<span class="selo laranja">aguardando produto</span>' : '' ?>
          <span class="dep-tipo-selo tipo-<?= $tipoP ?>"><?= $eco ? '🛒 ' . e(Eco::nomeCanal($p['canal'])) : ($p['terceirizados'] ? '🏷️ Terceirização' : '🏪 Lojista') ?></span>
          <?= $p['falta'] ? '<span class="selo laranja">saiu com falta</span>' : '' ?></span>
        <?php if ($prazo): ?><span class="dep-prazo <?= $prazo[1] ?>" data-prazo>⏰ <?= e($prazo[0]) ?></span><?php endif; ?>
        <?php if ($eco): ?>
          <span class="dep-cliente"><?= e(Eco::nomeCanal($p['canal'])) ?><?= $p['marketplace_numero'] ? ' · pedido ' . e($p['marketplace_numero']) : '' ?></span>
          <small><?= e(trim(($p['comprador_cidade'] ?? '') . '/' . ($p['comprador_uf'] ?? ''), '/')) ?> · <?= (int) $p['itens'] ?> item(ns), <?= (int) $p['unidades'] ?> un · venda do e-commerce</small>
        <?php else: ?>
          <span class="dep-cliente"><?= e($p['cliente']) ?></span>
          <small><?= e($p['cidade']) ?>/<?= e($p['uf']) ?> · <?= (int) $p['itens'] ?> item(ns), <?= (int) $p['unidades'] ?> un · vendedor <?= pessoa($p['vendedor'] ?? null, $p['vendedor_foto'] ?? null, false, 18) ?></small>
        <?php endif; ?>
        <small class="suave"><?php if ($p['expedicao'] === 'despachado'): ?>Despachado <?= data_br($p['despachado_em'], true) ?> · <?= e($p['transportadora']) ?><?= $p['rastreio'] ? ' · ' . e($p['rastreio']) : '' ?>
          <?php else: ?>Na fila desde <?= data_br($p['expedicao_desde'], true) ?><?= $p['separador'] ? ' · com ' . pessoa($p['separador'] ?? null, null, false, 18, '—') : '' ?><?php endif; ?></small>
      </a>
      <?php if ($pode && $p['expedicao'] !== 'despachado'): ?>
        <form method="post" action="<?= url('/deposito/' . (int) $p['id'] . '/urgente') ?>" class="dep-urgente"><?= csrf_campo() ?>
          <input type="hidden" name="urgente" value="<?= $p['expedicao_prioridade'] ? '0' : '1' ?>"><input type="hidden" name="voltar" value="fila">
          <button class="botao contorno pequeno" type="submit"><?= $p['expedicao_prioridade'] ? 'Tirar urgência' : 'Urgente' ?></button></form>
      <?php endif; ?>
    </article>
  <?php endforeach; ?>
  <?php if (!$fila): ?><div class="card vazio"><p><?= ['separar' => 'Nenhum pedido para separar agora.', 'separados' => 'Nenhum pedido separado esperando despacho.', 'despachados' => 'Nenhum pedido despachado nos últimos 7 dias.'][$aba] ?></p></div><?php endif; ?>
</div>
