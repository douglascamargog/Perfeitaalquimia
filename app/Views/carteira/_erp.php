<?php
/**
 * Página do cliente: o que vem do ERP (Nota10, depois Omie): cadastro completo, pessoas da loja, rede e títulos.
 * Cada bloco só aparece quando tem informação. Variáveis: $c, $contatos, $titulos, $matriz, $filiais
 */
$campo = fn(string $rotulo, $valor) => $valor === null || $valor === '' ? '' : '<div><dt>' . e($rotulo) . '</dt><dd>' . $valor . '</dd></div>';
$pct = fn($v) => $v === null ? null : number_format((float) $v, 2, ',', '.') . '%';
$contribuinte = ['contribuinte' => 'Contribuinte do ICMS', 'isento' => 'Isento', 'nao_contribuinte' => 'Não contribuinte'];
$endEntrega = trim(implode(', ', array_filter([$c['entrega_endereco'], $c['entrega_numero'], $c['entrega_complemento'], $c['entrega_bairro'],
    $c['entrega_cidade'] ? $c['entrega_cidade'] . '/' . $c['entrega_uf'] : null, $c['entrega_cep']])));
$cadastro = implode('', [
    $campo('Inscrição estadual', e($c['inscricao_estadual'])),
    $campo('Inscrição municipal', e($c['inscricao_municipal'])),
    $campo('ICMS', e($contribuinte[$c['contribuinte']] ?? $c['contribuinte'])),
    $campo('SUFRAMA', e($c['suframa'])),
    $campo('Número / complemento', e(trim(($c['end_numero'] ?? '') . ' ' . ($c['end_complemento'] ?? '')))),
    $campo('Contato principal', e($c['contato_nome'])),
    $campo('Celular 2', e($c['celular2'])),
    $campo('E-mail para NF-e', e($c['email_nfe'])),
    $campo('E-mail do financeiro', e($c['email_financeiro'])),
    $campo('Fundação', $c['data_fundacao'] ? data_br($c['data_fundacao']) : null),
    $campo('Cliente desde', $c['cliente_desde'] ? data_br($c['cliente_desde']) : null),
    $campo('Limite de crédito', $c['limite_credito'] !== null ? e(dinheiro($c['limite_credito'])) : null),
    $campo('Desconto padrão', e($pct($c['desconto_padrao']))),
    $campo('Comissão', e($pct($c['comissao_pct']))),
    $campo('Transportadora preferida', e($c['transportadora_preferida'])),
    $campo('Situação no ERP', e(trim(($c['situacao_erp'] ?? '') . ($c['motivo_inativo'] ? ' · ' . $c['motivo_inativo'] : '')))),
    $campo('Entrega em', $endEntrega !== '' ? e(($c['entrega_razao'] ? $c['entrega_razao'] . ' · ' : '') . $endEntrega) : null),
]);
$abertos = array_filter($titulos, fn($t) => $t['situacao'] === 'aberto');
$vencidos = array_filter($abertos, fn($t) => $t['vencimento'] && $t['vencimento'] < date('Y-m-d'));
?>
<?php if ($cadastro !== '' || $matriz || $filiais): ?>
<section class="card cl-bloco" data-cadastro-erp>
  <header class="cl-bloco-topo"><h2>Cadastro completo</h2><span class="suave pequeno">dados do ERP</span></header>
  <?php if ($cadastro !== ''): ?><dl class="cl-erp"><?= $cadastro ?></dl><?php endif; ?>
  <?php if ($matriz): ?><p class="pequeno" data-matriz>Filial da rede <a href="<?= url('/carteira/clientes/' . (int) $matriz['id']) ?>"><strong><?= e($matriz['nome_fantasia'] ?: $matriz['razao_social']) ?></strong></a> (<?= e($matriz['cidade']) ?>/<?= e($matriz['uf']) ?>)</p><?php endif; ?>
  <?php if ($filiais): ?><p class="pequeno" data-filiais>Matriz de uma rede com <?= count($filiais) ?> filial(is):
    <?= implode(', ', array_map(fn($f) => '<a href="' . url('/carteira/clientes/' . (int) $f['id']) . '">' . e($f['nome_fantasia'] ?: $f['razao_social']) . '</a>', $filiais)) ?></p><?php endif; ?>
</section>
<?php endif; ?>

<?php if ($contatos): ?>
<section class="card cl-bloco" data-contatos-loja>
  <header class="cl-bloco-topo"><h2>Pessoas da loja</h2><span class="cl-contador"><?= count($contatos) ?></span></header>
  <ul class="cl-pessoas">
    <?php foreach ($contatos as $p): $zap = preg_replace('/\D/', '', (string) ($p['celular'] ?: '')); ?>
      <li><strong><?= e($p['nome']) ?></strong><?= $p['cargo'] ? ' <span class="suave">· ' . e($p['cargo']) . '</span>' : '' ?>
        <?php if ($p['aniversario_dia'] && $p['aniversario_mes']): ?><span class="selo">🎂 <?= sprintf('%02d/%02d', $p['aniversario_dia'], $p['aniversario_mes']) ?></span><?php endif; ?>
        <br><small class="suave"><?= implode(' · ', array_map('e', array_filter([$p['telefone'], $p['celular'], $p['email']]))) ?></small>
        <?php if (strlen($zap) >= 10): ?> <a class="pequeno" href="https://wa.me/55<?= e(ltrim($zap, '0')) ?>" target="_blank" rel="noopener">WhatsApp</a><?php endif; ?></li>
    <?php endforeach; ?>
  </ul>
</section>
<?php endif; ?>

<?php if ($titulos): ?>
<section class="card sem-espaco cl-bloco" data-titulos-cliente>
  <header class="cl-secao-topo"><h2>Títulos</h2>
    <span class="pequeno <?= $vencidos ? 'cl-vencido' : 'suave' ?>"><?= count($abertos) ?> em aberto<?= $vencidos ? ' · ' . count($vencidos) . ' vencido(s): ' . e(dinheiro(array_sum(array_column($vencidos, 'valor')))) : '' ?></span></header>
  <div class="tabela-rolagem">
    <table class="tabela">
      <thead><tr><th>Documento</th><th>Vencimento</th><th>Pagamento</th><th>Forma</th><th>Situação</th><th class="direita">Valor</th></tr></thead>
      <tbody>
      <?php foreach ($titulos as $t): $atrasado = $t['situacao'] === 'aberto' && $t['vencimento'] && $t['vencimento'] < date('Y-m-d');
          $dias = $t['pago_em'] && $t['vencimento'] ? (int) round((strtotime($t['pago_em']) - strtotime($t['vencimento'])) / 86400) : null; ?>
        <tr><td><?= e(trim($t['documento'] . ($t['parcela'] ? ' · ' . $t['parcela'] : ''))) ?: '—' ?></td>
          <td><?= $t['vencimento'] ? data_br($t['vencimento']) : '—' ?></td>
          <td><?= $t['pago_em'] ? data_br($t['pago_em']) . ($dias > 0 ? ' <small class="cl-vencido">(' . $dias . ' dia(s) depois)</small>' : '') : '—' ?></td>
          <td class="pequeno"><?= e($t['forma_pagamento'] ?? '') ?></td>
          <td><span class="selo <?= $atrasado ? 'vermelho' : ($t['situacao'] === 'pago' ? 'verde' : ($t['situacao'] === 'cancelado' ? '' : 'laranja')) ?>"><?= $atrasado ? 'vencido' : e($t['situacao']) ?></span>
            <?php if (!empty($t['cobranca_id'])): ?> <a class="selo vermelho" href="<?= url('/cobranca?abrir=' . (int) $t['cobranca_id']) ?>" data-titulo-na-cobranca>na Cobrança</a><?php endif; ?></td>
          <td class="direita"><strong><?= e(dinheiro((float) $t['valor'])) ?></strong></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>
<?php endif; ?>
