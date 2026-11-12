<?php
/** Relatório de cobrança para imprimir em A4. Variáveis: $casos, $resumo, $filtros */
?>
<div class="cabecalho-pagina nao-imprimir">
  <p class="suave">Relatório dos casos de cobrança <?= array_filter($filtros) ? 'com os filtros escolhidos' : 'em aberto' ?>.</p>
  <div class="acoes">
    <button class="botao" type="button" onclick="window.print()">Imprimir</button>
    <a class="botao contorno" href="<?= url('/cobranca') ?>">Voltar</a>
  </div>
</div>
<section class="card cob-relatorio">
  <h2>Relatório de cobrança · <?= date('d/m/Y') ?></h2>
  <p class="pequeno">Total em aberto: <strong><?= dinheiro((float) $resumo['total']) ?></strong> · <?= (int) $resumo['casos'] ?> caso(s) ·
    <?= (int) $resumo['mais90'] ?> com mais de 90 dias · recuperado no mês: <?= dinheiro((float) $resumo['recuperado_mes']) ?></p>
  <table>
    <thead><tr><th>Cliente</th><th>Cidade/UF</th><th class="direita">Em aberto</th><th>Vencimento</th><th class="direita">Dias</th><th>Etapa</th><th>Promessa</th><th>Responsável</th></tr></thead>
    <tbody>
    <?php $soma = 0; foreach ($casos as $c): $soma += (float) $c['valor_total']; ?>
      <tr>
        <td><?= e($c['nome_fantasia'] ?: $c['razao_social']) ?></td>
        <td><?= e($c['cidade']) ?>/<?= e($c['uf']) ?></td>
        <td class="direita"><?= dinheiro((float) $c['valor_total']) ?></td>
        <td><?= $c['vencimento_mais_antigo'] ? data_br($c['vencimento_mais_antigo']) : '—' ?></td>
        <td class="direita"><?= $c['situacao'] === 'aberta' ? (int) $c['dias_atraso'] : '—' ?></td>
        <td><?= e($c['etapa']) ?></td>
        <td><?= $c['promessa_em'] ? data_br($c['promessa_em']) : '' ?></td>
        <td><?= e($c['responsavel']) ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$casos): ?><tr><td colspan="8">Nenhum caso.</td></tr><?php endif; ?>
    </tbody>
    <tfoot><tr><th colspan="2">Total</th><th class="direita"><?= dinheiro($soma) ?></th><th colspan="5"></th></tr></tfoot>
  </table>
</section>
