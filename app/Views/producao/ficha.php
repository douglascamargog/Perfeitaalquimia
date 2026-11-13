<?php
/** Editor da ficha técnica. Variáveis: $produto, $linhas, $materias, $pode */
use App\Core\Visao;
use App\Services\ProducaoService as P;

$num = fn($valor) => rtrim(rtrim(number_format((float) $valor, 5, ',', ''), '0'), ',');
$total = array_sum(array_map(fn($l) => (float) $l['custo'], $linhas));
?>
<div class="cabecalho-secao">
  <div><div class="sobretitulo"><a href="<?= url('/producao/fichas') ?>">FICHAS TÉCNICAS</a></div>
    <h1><?= e($produto['nome']) ?></h1><p>Quantidade de cada matéria-prima para fabricar <strong>1 unidade</strong><?= $produto['embalagem'] ? ' (' . e($produto['embalagem']) . ')' : '' ?>.</p></div>
  <?php if ($linhas && $pode): ?><div class="acoes"><a class="botao" href="<?= url('/producao/ordens/nova?produto=' . (int) $produto['id']) ?>">Nova ordem deste produto</a></div><?php endif; ?>
</div>
<?= Visao::renderizar('producao/_abas', ['atual' => '/producao/fichas']) ?>

<form class="card" method="post" action="<?= url('/producao/fichas/' . (int) $produto['id']) ?>" data-ficha>
  <?= csrf_campo() ?>
  <div class="tabela-rolagem">
    <table class="tabela pr-tabela pr-ficha">
      <thead><tr><th>Matéria-prima</th><th class="direita">Quantidade por unidade</th><th>Unid.</th><th class="direita">Custo/unid. da MP</th><th class="direita">Custo na ficha</th><?php if ($pode): ?><th></th><?php endif; ?></tr></thead>
      <tbody data-linhas>
      <?php foreach ($linhas as $l): ?>
        <tr data-linha>
          <td><?php if ($pode): ?>
              <select name="mp[]" aria-label="Matéria-prima" data-mp>
                <?php foreach ($materias as $mp): ?><option value="<?= (int) $mp['id'] ?>" data-unidade="<?= e($mp['unidade']) ?>" data-custo="<?= (float) $mp['custo_unitario'] ?>" <?= (int) $mp['id'] === (int) $l['materia_prima_id'] ? 'selected' : '' ?>><?= e($mp['nome']) ?></option><?php endforeach; ?>
              </select>
            <?php else: ?><a href="<?= url('/producao/materias/' . (int) $l['materia_prima_id']) ?>"><?= e($l['nome']) ?></a><?php endif; ?></td>
          <td class="direita"><?php if ($pode): ?><input type="text" name="qtd[]" inputmode="decimal" value="<?= $num($l['quantidade']) ?>" aria-label="Quantidade por unidade" class="pr-num" data-qtd><?php else: ?><?= P::qtd((float) $l['quantidade']) ?><?php endif; ?></td>
          <td data-unidade><?= e($l['unidade']) ?></td>
          <td class="direita pequeno" data-custo-mp><?= dinheiro((float) $l['custo_unitario']) ?></td>
          <td class="direita" data-custo-linha><?= dinheiro((float) $l['custo']) ?></td>
          <?php if ($pode): ?><td><button class="botao contorno pequeno" type="button" data-remover aria-label="Tirar da ficha">✕</button></td><?php endif; ?>
        </tr>
      <?php endforeach; ?>
      </tbody>
      <tfoot><tr><td colspan="4" class="direita"><strong>Custo de matéria-prima por unidade</strong></td><td class="direita"><strong data-custo-total><?= dinheiro($total) ?></strong></td><?php if ($pode): ?><td></td><?php endif; ?></tr></tfoot>
    </table>
  </div>
  <?php if (!$linhas): ?><p class="suave" data-sem-linhas>Esta ficha ainda está vazia<?= $pode ? '. Acrescente as matérias-primas abaixo.' : '.' ?></p><?php endif; ?>
  <?php if ($pode): ?>
    <template data-modelo>
      <tr data-linha>
        <td><select name="mp[]" aria-label="Matéria-prima" data-mp><option value="">Escolha…</option>
          <?php foreach ($materias as $mp): ?><option value="<?= (int) $mp['id'] ?>" data-unidade="<?= e($mp['unidade']) ?>" data-custo="<?= (float) $mp['custo_unitario'] ?>"><?= e($mp['nome']) ?></option><?php endforeach; ?></select></td>
        <td class="direita"><input type="text" name="qtd[]" inputmode="decimal" aria-label="Quantidade por unidade" class="pr-num" data-qtd></td>
        <td data-unidade></td><td class="direita pequeno" data-custo-mp></td><td class="direita" data-custo-linha></td>
        <td><button class="botao contorno pequeno" type="button" data-remover aria-label="Tirar da ficha">✕</button></td>
      </tr>
    </template>
    <div class="acoes-form">
      <button class="botao contorno" type="button" data-acrescentar>+ Matéria-prima</button>
      <button class="botao" type="submit">Salvar ficha técnica</button>
    </div>
    <p class="ajuda suave">Use a unidade da matéria-prima: 60 cápsulas = <strong>60</strong> (un); 30 g de pó = <strong>0,03</strong> (kg).</p>
  <?php endif; ?>
</form>
