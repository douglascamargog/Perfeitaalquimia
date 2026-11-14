<?php
/**
 * Depósito › Separação: pedidos com nota emitida para separar e despachar.
 * Variáveis: $aba (separar|separados|despachados), $fila, $resumo, $diasAtraso, $pode
 */
use App\Core\Visao;
use App\Services\DepositoService as D;

$link = fn(string $a) => url('/deposito') . ($a === 'separar' ? '' : '?aba=' . $a);
$atrasado = fn(array $p) => $p['expedicao'] !== 'despachado' && $p['expedicao_desde'] && strtotime($p['expedicao_desde']) < strtotime("-$diasAtraso days");
?>
<div class="cabecalho-secao">
  <div><div class="sobretitulo">DEPÓSITO · SEPARAÇÃO E EXPEDIÇÃO</div><h1>Pedidos para separar</h1>
    <p>Pedidos aprovados e com nota emitida. Urgentes primeiro, depois do mais antigo para o mais novo.</p></div>
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

<div class="dep-fila" data-fila="<?= e($aba) ?>">
  <?php foreach ($fila as $p): [$er, $ec] = D::ETAPAS[$p['expedicao']] ?? [$p['expedicao'], 'cinza']; ?>
    <article class="dep-cartao <?= $p['expedicao_prioridade'] ? 'urgente' : '' ?> <?= $atrasado($p) ? 'atrasado' : '' ?>" data-pedido="<?= (int) $p['id'] ?>">
      <a class="dep-cartao-link" href="<?= url('/deposito/' . (int) $p['id']) ?>">
        <span class="dep-cartao-topo"><strong>#<?= (int) $p['id'] ?></strong>
          <?= $p['numero_nf'] ? '<small>NF ' . e($p['numero_nf']) . '</small>' : '' ?>
          <span class="selo <?= $ec ?>"><?= e($er) ?></span>
          <?= $p['expedicao_prioridade'] ? '<span class="selo vermelho">urgente</span>' : '' ?>
          <?= $p['expedicao_aguarda_produto'] ? '<span class="selo laranja">aguardando produto</span>' : '' ?>
          <?= $p['terceirizados'] ? '<span class="selo cinza">terceirização</span>' : '' ?>
          <?= $p['falta'] ? '<span class="selo laranja">saiu com falta</span>' : '' ?></span>
        <span class="dep-cliente"><?= e($p['cliente']) ?></span>
        <small><?= e($p['cidade']) ?>/<?= e($p['uf']) ?> · <?= (int) $p['itens'] ?> item(ns), <?= (int) $p['unidades'] ?> un · vendedor <?= e($p['vendedor'] ?? '—') ?></small>
        <small class="suave"><?php if ($p['expedicao'] === 'despachado'): ?>Despachado <?= data_br($p['despachado_em'], true) ?> · <?= e($p['transportadora']) ?><?= $p['rastreio'] ? ' · ' . e($p['rastreio']) : '' ?>
          <?php else: ?>Na fila desde <?= data_br($p['expedicao_desde'], true) ?><?= $p['separador'] ? ' · com ' . e($p['separador']) : '' ?><?php endif; ?></small>
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
