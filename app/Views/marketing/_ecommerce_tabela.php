<?php
/**
 * Tabela de pedidos do e-commerce (usada no painel e na lista). Sem dados pessoais (só cidade/UF).
 * Variáveis: $linhas (ecommerce_pedidos + resumo_itens), $compacta (bool)
 */
use App\Integrations\Tray\TrayTexto;

$compacta ??= false;
?>
<?php if (!$linhas): ?><p class="b-vazio">Nenhum pedido encontrado.</p><?php else: ?>
<div class="tabela-rolagem">
  <table class="b-tabela eco-pedidos <?= $compacta ? 'compacta' : '' ?>" data-eco-tabela>
    <thead><tr>
      <th>Pedido</th><th>Quando</th><th>Canal</th>
      <?php if (!$compacta): ?><th>Produtos</th><?php endif; ?>
      <th>Cidade</th><th class="direita">Total</th>
      <?php if (!$compacta): ?><th>Pagamento</th><?php endif; ?>
      <th>Situação</th>
      <?php if (!$compacta): ?><th>Nota</th><th>Envio</th><?php endif; ?>
    </tr></thead>
    <tbody>
    <?php foreach ($linhas as $l): [$sitNome, $sitCor] = TrayTexto::SITUACOES[$l['status_tipo']] ?? [$l['status'], 'cinza']; ?>
      <tr data-eco-pedido="<?= e($l['tray_id']) ?>">
        <td><a href="<?= url('/marketing/ecommerce/pedidos/' . (int) $l['id']) ?>"><strong>#<?= e($l['tray_id']) ?></strong></a>
          <?php if ($l['marketplace_pedido']): ?><br><small class="suave" title="Número no marketplace"><?= e($l['marketplace_pedido']) ?></small><?php endif; ?></td>
        <td class="nao-quebra"><?= date('d/m/Y', strtotime($l['data_hora'])) ?><?php if ($l['detalhado']): ?> <small class="suave"><?= date('H:i', strtotime($l['data_hora'])) ?></small><?php endif; ?></td>
        <td><span class="eco-canal canal-<?= e($l['canal']) ?>"><?= e(TrayTexto::nomeCanal($l['canal'], $l['canal_nome'])) ?></span></td>
        <?php if (!$compacta): ?><td class="eco-itens-resumo"><?= e(mb_strimwidth((string) ($l['resumo_itens'] ?? ''), 0, 90, '…')) ?: '<span class="suave">—</span>' ?></td><?php endif; ?>
        <td class="suave"><?= $l['cidade'] ? e($l['cidade']) . '/' . e($l['uf']) : '—' ?></td>
        <td class="direita"><strong><?= dinheiro((float) $l['total']) ?></strong></td>
        <?php if (!$compacta): ?><td class="pequeno"><?= e($l['pagamento'] ?: '—') ?></td><?php endif; ?>
        <td><span class="selo eco-sit sit-<?= e($l['status_tipo']) ?>" title="Na loja: <?= e($l['status']) ?>"><?= e($sitNome) ?></span></td>
        <?php if (!$compacta): ?>
          <td><?= $l['tem_nf'] ? '✅' . ($l['nf_numero'] ? ' <small>' . e($l['nf_numero']) . '</small>' : '') : '<span class="suave">—</span>' ?></td>
          <td class="pequeno"><?= e($l['envio'] ?: '—') ?><?= $l['enviado_em'] ? '<br><small class="suave">enviado ' . date('d/m', strtotime($l['enviado_em'])) . '</small>' : '' ?></td>
        <?php endif; ?>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>
