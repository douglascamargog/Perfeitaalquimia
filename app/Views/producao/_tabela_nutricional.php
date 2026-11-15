<?php
/** Tabela nutricional no modelo Anvisa (tela, prévia ao vivo e ficha impressa). Variável: $t (TabelaNutricionalService::doProduto) */
use App\Services\TabelaNutricionalService as TN;
?>
<div class="tn" data-tabela-nutricional>
  <div class="tn-titulo">INFORMAÇÃO NUTRICIONAL</div>
  <div class="tn-porcao"><div>Porções por embalagem: <span data-tn="porcoes"><?= e((string) $t['porcoes_embalagem']) ?></span></div>
    <div>Porção: <span data-tn="porcao"><?= e((string) $t['porcao']) ?></span></div></div>
  <table>
    <thead><tr><th></th><th data-tn="coluna"><?= e((string) $t['coluna']) ?></th><th>%VD*</th></tr></thead>
    <tbody data-tn="linhas">
    <?php foreach ($t['linhas'] as $l): ?>
      <tr><td><?= e($l['nutriente']) ?><?= $l['unidade'] !== '' ? ' (' . e($l['unidade']) . ')' : '' ?></td><td><?= e((string) $l['quantidade']) ?></td><td><?= e($l['vd_final']) ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <div class="tn-nota" data-tn="nota"><?= e((string) ($t['nota'] ?: TN::NOTA_PADRAO)) ?></div>
  <div class="tn-rodape"><?= e(TN::RODAPE) ?></div>
</div>
