<?php
/**
 * Tabela/editor de uma parte da ficha técnica (a mesma tabela da Produção).
 * Variáveis: $p, $linhas, $opcoes (matérias-primas do tipo), $tipo ('materia_prima'|'embalagem'), $podeTecnico, $veCusto
 */
use App\Services\ProducaoService as P;

$num = fn($valor) => rtrim(rtrim(number_format((float) $valor, 5, ',', ''), '0'), ',');
$total = array_sum(array_map(fn($l) => (float) $l['custo'], $linhas));
$nomeItem = $tipo === 'embalagem' ? 'Embalagem' : 'Matéria-prima';
$opcao = fn(array $mp, ?int $sel = null) => '<option value="' . (int) $mp['id'] . '" data-unidade="' . e($mp['unidade']) . '" data-custo="' . (float) $mp['custo_unitario'] . '"'
    . ((int) $mp['id'] === $sel ? ' selected' : '') . '>' . e($mp['nome']) . ($mp['codigo'] ? ' (' . e($mp['codigo']) . ')' : '') . '</option>';
?>
<?php if ($podeTecnico): ?>
<form class="card" method="post" action="<?= url('/produtos/' . e($p['slug']) . '/ficha') ?>" data-ficha>
  <?= csrf_campo() ?>
  <input type="hidden" name="voltar" value="<?= $tipo === 'embalagem' ? 'embalagens' : 'ficha' ?>">
  <div class="tabela-rolagem">
    <table class="tabela pr-tabela pr-ficha">
      <thead><tr><th><?= $nomeItem ?></th><th class="direita">Por unidade</th><th>Unid.</th><th class="direita">Custo/unid.</th><th class="direita">Custo na ficha</th><th></th></tr></thead>
      <tbody data-linhas>
      <?php foreach ($linhas as $l): ?>
        <tr data-linha>
          <td><select name="mp[]" aria-label="<?= $nomeItem ?>" data-mp><?php foreach ($opcoes as $mp): ?><?= $opcao($mp, (int) $l['materia_prima_id']) ?><?php endforeach; ?></select></td>
          <td class="direita"><input type="text" name="qtd[]" inputmode="decimal" value="<?= $num($l['quantidade']) ?>" aria-label="Quantidade por unidade" class="pr-num" data-qtd></td>
          <td data-unidade><?= e($l['unidade']) ?></td>
          <td class="direita pequeno" data-custo-mp><?= dinheiro((float) $l['custo_unitario']) ?></td>
          <td class="direita" data-custo-linha><?= dinheiro((float) $l['custo']) ?></td>
          <td><button class="botao contorno pequeno" type="button" data-remover aria-label="Tirar da ficha">✕</button></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
      <tfoot><tr><td colspan="4" class="direita"><strong>Custo por unidade (<?= $tipo === 'embalagem' ? 'embalagens' : 'matérias-primas' ?>)</strong></td><td class="direita"><strong data-custo-total><?= dinheiro($total) ?></strong></td><td></td></tr></tfoot>
    </table>
  </div>
  <?php if (!$linhas): ?><p class="suave" data-sem-linhas>Nada cadastrado ainda. Acrescente abaixo.</p><?php endif; ?>
  <template data-modelo>
    <tr data-linha>
      <td><select name="mp[]" aria-label="<?= $nomeItem ?>" data-mp><option value="">Escolha…</option><?php foreach ($opcoes as $mp): ?><?= $opcao($mp) ?><?php endforeach; ?></select></td>
      <td class="direita"><input type="text" name="qtd[]" inputmode="decimal" aria-label="Quantidade por unidade" class="pr-num" data-qtd></td>
      <td data-unidade></td><td class="direita pequeno" data-custo-mp></td><td class="direita" data-custo-linha></td>
      <td><button class="botao contorno pequeno" type="button" data-remover aria-label="Tirar da ficha">✕</button></td>
    </tr>
  </template>
  <div class="acoes-form">
    <button class="botao contorno" type="button" data-acrescentar>+ <?= $nomeItem ?></button>
    <button class="botao" type="submit">Salvar <?= $tipo === 'embalagem' ? 'embalagens' : 'ficha técnica' ?></button>
  </div>
  <p class="ajuda suave">Quantidade para fabricar <strong>1 unidade</strong>, na unidade do item: 60 cápsulas = <strong>60</strong> (un); 30 g de pó = <strong>0,03</strong> (kg).
    Item novo? Cadastre em <a href="<?= url('/producao/materias') ?>">Produção › Matérias-primas</a>.</p>
</form>
<?php else: ?>
<section class="card">
  <?php if (!$linhas): ?><p class="suave">Nada cadastrado ainda.</p><?php else: ?>
  <div class="tabela-rolagem">
    <table class="tabela pr-tabela">
      <thead><tr><th><?= $nomeItem ?></th><th class="direita">Por unidade</th><th class="direita">Em estoque</th><th class="direita">Dá para fazer</th><?php if ($veCusto): ?><th class="direita">Custo na ficha</th><?php endif; ?></tr></thead>
      <tbody>
      <?php foreach ($linhas as $l): ?>
        <tr><td><?= e($l['nome']) ?><?= $l['codigo'] ? ' <small class="suave">' . e($l['codigo']) . '</small>' : '' ?></td>
          <td class="direita"><?= $num($l['quantidade']) . ' ' . e($l['unidade']) ?></td>
          <td class="direita <?= (float) $l['estoque'] < (float) $l['estoque_minimo'] ? 'pd-baixo' : '' ?>"><?= P::qtd((float) $l['estoque'], $l['unidade']) ?></td>
          <td class="direita"><?= $l['rende'] !== null ? number_format($l['rende'], 0, ',', '.') . ' un' : '—' ?></td>
          <?php if ($veCusto): ?><td class="direita"><?= dinheiro((float) $l['custo']) ?></td><?php endif; ?></tr>
      <?php endforeach; ?>
      </tbody>
      <?php if ($veCusto): ?><tfoot><tr><td colspan="4" class="direita"><strong>Custo por unidade</strong></td><td class="direita"><strong><?= dinheiro($total) ?></strong></td></tr></tfoot><?php endif; ?>
    </table>
  </div>
  <?php endif; ?>
</section>
<?php endif; ?>
