<?php
/** Estoque acabado de um produto. Variáveis: $a, $movimentos, $pode */
use App\Core\Visao;
use App\Services\ProducaoService as P;

$n = fn($v) => number_format((float) $v, 0, ',', '.');
?>
<div class="cabecalho-secao">
  <div><div class="sobretitulo"><a href="<?= url('/producao/acabados') ?>">ESTOQUE ACABADO</a></div><h1><?= e($a['nome']) ?></h1>
    <p><?= e($a['embalagem'] ?? '') ?> · <a href="<?= url('/producao/fichas/' . (int) $a['id']) ?>">ficha técnica</a></p></div>
  <?php if ($pode && $a['tem_ficha']): ?><div class="acoes"><a class="botao" href="<?= url('/producao/ordens/nova?produto=' . (int) $a['id']) ?>">Nova ordem</a></div><?php endif; ?>
</div>
<?= Visao::renderizar('producao/_abas', ['atual' => '/producao/acabados']) ?>
<div class="bento">
  <section class="b-card b-escuro span-4"><header class="b-topo"><span>Em estoque</span></header>
    <strong class="b-numero-grande" data-quantidade><?= $n($a['quantidade']) ?> <small>un</small></strong>
    <p class="b-sub">Mínimo <?= $n($a['minimo']) ?><?= $a['abaixo'] ? ' · <strong class="mk-lima">abaixo do mínimo</strong>' : '' ?></p></section>
  <section class="b-card b-kpi span-3"><header class="b-topo"><span>Venda média</span></header><strong class="b-valor"><?= number_format($a['venda_dia'], 1, ',', '.') ?></strong><small>unidades por dia (faturado)</small></section>
  <section class="b-card b-kpi span-3"><header class="b-topo"><span>Cobertura</span></header><strong class="b-valor"><?= $a['dias_cobertura'] !== null ? '~' . $a['dias_cobertura'] . ' dias' : '—' ?></strong><small>no ritmo atual de vendas</small></section>
  <section class="b-card b-kpi b-lima span-2"><header class="b-topo"><span>Planejado</span></header><strong class="b-valor"><?= $n($a['planejado']) ?></strong><small>em ordens abertas</small></section>

  <?php if ($pode): ?>
    <section class="b-card span-6"><header class="b-topo"><h2>Estoque mínimo</h2></header>
      <form method="post" action="<?= url('/producao/acabados/' . (int) $a['id'] . '/minimo') ?>" class="pr-linha-form"><?= csrf_campo() ?>
        <input type="number" name="minimo" min="0" value="<?= (int) $a['minimo'] ?>" aria-label="Estoque mínimo" class="pr-num"><button class="botao contorno" type="submit">Salvar mínimo</button></form>
      <?php if ($a['abaixo'] && $a['sugestao'] > 0 && $a['tem_ficha']): ?>
        <form method="post" action="<?= url('/producao/reabastecer/' . (int) $a['id']) ?>" class="pr-linha-form"><?= csrf_campo() ?>
          <button class="botao lima" type="submit">Gerar ordem de reabastecimento (<?= $n($a['sugestao']) ?> un)</button></form>
      <?php endif; ?>
    </section>
    <section class="b-card span-6"><header class="b-topo"><h2>Ajuste de inventário</h2></header>
      <form method="post" action="<?= url('/producao/acabados/' . (int) $a['id'] . '/ajuste') ?>"><?= csrf_campo() ?>
        <div class="grade-campos">
          <div class="campo"><label for="contagem">Unidades contadas</label><input id="contagem" name="contagem" type="number" min="0" required></div>
          <div class="campo"><label for="motivo">Motivo</label><input type="text" id="motivo" name="motivo" maxlength="200" placeholder="contagem, avaria, amostra…"></div>
        </div>
        <button class="botao contorno" type="submit">Ajustar estoque</button></form>
    </section>
  <?php endif; ?>

  <section class="b-card span-12"><header class="b-topo"><h2>Histórico</h2><span class="b-chip">últimos 100</span></header>
    <div class="tabela-rolagem">
      <table class="tabela pr-tabela" data-movimentos>
        <thead><tr><th>Data</th><th>Tipo</th><th class="direita">Quantidade</th><th class="direita">Saldo</th><th>Referência</th><th>Quem</th></tr></thead>
        <tbody>
        <?php foreach ($movimentos as $mv): $q = (int) $mv['quantidade']; ?>
          <tr>
            <td class="pequeno"><?= data_br($mv['criado_em'], true) ?></td>
            <td><span class="selo <?= ['producao' => 'verde', 'venda' => 'cinza', 'ajuste' => 'laranja'][$mv['tipo']] ?? 'cinza' ?>"><?= P::TIPOS_ACABADO[$mv['tipo']] ?? e($mv['tipo']) ?></span></td>
            <td class="direita <?= $q < 0 ? 'pn-vermelho' : 'pn-verde' ?>"><?= ($q > 0 ? '+' : '') . $n($q) ?></td>
            <td class="direita"><?= $n($mv['saldo']) ?></td>
            <td class="pequeno">
              <?php if ($mv['ordem_id']): ?><a href="<?= url('/producao/ordens/' . (int) $mv['ordem_id']) ?>"><?= e($mv['referencia']) ?></a>
              <?php elseif ($mv['pedido_id'] && \App\Core\Auth::pode('pedidos.ver_todos')): ?><a href="<?= url('/pedidos/' . (int) $mv['pedido_id']) ?>"><?= e($mv['referencia']) ?></a>
              <?php else: ?><?= e($mv['referencia'] ?? '') ?><?php endif; ?><?= $mv['observacao'] ? ' <span class="suave">' . e($mv['observacao']) . '</span>' : '' ?></td>
            <td class="pequeno"><?= e($mv['usuario'] ?? ($mv['tipo'] === 'venda' ? 'faturamento' : '—')) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$movimentos): ?><tr><td colspan="6" class="vazio">Nenhum movimento ainda.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>
</div>
