<?php
/** Editor da ficha técnica. Variáveis: $produto, $linhas, $materias, $pode, $tabela (tabela nutricional) */
use App\Core\Visao;
use App\Services\ProducaoService as P;

$num = fn($valor) => rtrim(rtrim(number_format((float) $valor, 5, ',', ''), '0'), ',');
$total = array_sum(array_map(fn($l) => (float) $l['custo'], $linhas));
?>
<div class="cabecalho-secao">
  <div><div class="sobretitulo"><a href="<?= url('/producao/fichas') ?>">FICHAS TÉCNICAS</a></div>
    <h1><?= e($produto['nome']) ?></h1><p>Quantidade de cada matéria-prima para fabricar <strong>1 unidade</strong><?= $produto['embalagem'] ? ' (' . e($produto['embalagem']) . ')' : '' ?>.</p></div>
  <div class="acoes"><a class="botao contorno" href="<?= url('/producao/fichas/' . (int) $produto['id'] . '/imprimir') ?>" target="_blank" rel="noopener" data-imprimir-ficha>🖨 Imprimir ficha técnica</a>
    <?php if ($linhas && !empty($podeOrdem)): ?><a class="botao" href="<?= url('/producao/ordens/nova?produto=' . (int) $produto['id']) ?>">Nova ordem deste produto</a><?php endif; ?></div>
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

<?php // Tabela nutricional (modelo Anvisa): editar à esquerda, prévia ao vivo à direita, baixar em PNG ou PDF
$tl = $tabela['linhas'] ?: [['nutriente' => '', 'unidade' => 'mg', 'quantidade' => '', 'vd' => '']]; ?>
<section class="card" id="tabela-nutricional" data-editor-nutricional data-nome="<?= e($produto['nome']) ?>" data-vdr='<?= e(json_encode(\App\Services\TabelaNutricionalService::VDR, JSON_UNESCAPED_UNICODE)) ?>'>
  <h2>Tabela nutricional</h2>
  <p class="ajuda">Modelo da Anvisa (RDC 429/2020). O %VD é calculado pelos valores diários de referência para adultos; deixe em branco para calcular ou digite para usar outro valor.</p>
  <div class="tn-editor">
    <form method="post" action="<?= url('/producao/fichas/' . (int) $produto['id'] . '/nutricional') ?>" data-form-nutricional>
      <?= csrf_campo() ?>
      <div class="grade-campos">
        <div class="campo"><label for="tn-porcoes">Porções por embalagem</label><input type="text" id="tn-porcoes" name="porcoes_embalagem" value="<?= e((string) $tabela['porcoes_embalagem']) ?>" placeholder="30" <?= $pode ? '' : 'disabled' ?>></div>
        <div class="campo"><label for="tn-porcao">Porção</label><input type="text" id="tn-porcao" name="porcao" value="<?= e((string) $tabela['porcao']) ?>" placeholder="1,1 g (2 cápsulas)" <?= $pode ? '' : 'disabled' ?>></div>
        <div class="campo"><label for="tn-coluna">Cabeçalho da coluna</label><input type="text" id="tn-coluna" name="porcao_coluna" value="<?= e((string) $tabela['porcao_coluna']) ?>" placeholder="<?= e($tabela['coluna']) ?>" <?= $pode ? '' : 'disabled' ?>></div>
      </div>
      <div class="tn-linhas" data-tn-linhas>
        <div class="tn-linha tn-cabecalho"><span>Nutriente</span><span>Unidade</span><span>Por porção</span><span>%VD</span><span></span></div>
        <?php foreach ($tl as $l): ?>
          <div class="tn-linha" data-tn-linha>
            <input type="text" name="nutriente[]" value="<?= e($l['nutriente']) ?>" placeholder="Ex.: Magnésio" aria-label="Nutriente" <?= $pode ? '' : 'disabled' ?>>
            <input type="text" name="unidade[]" value="<?= e($l['unidade']) ?>" list="tn-unidades" aria-label="Unidade" <?= $pode ? '' : 'disabled' ?>>
            <input type="text" name="quantidade[]" value="<?= e((string) $l['quantidade']) ?>" inputmode="decimal" aria-label="Quantidade por porção" <?= $pode ? '' : 'disabled' ?>>
            <input type="text" name="vd[]" value="<?= e((string) ($l['vd'] ?? '')) ?>" placeholder="auto" aria-label="%VD (vazio = automático)" <?= $pode ? '' : 'disabled' ?>>
            <?php if ($pode): ?><button type="button" class="tn-tirar" data-tn-tirar aria-label="Tirar nutriente">×</button><?php else: ?><span></span><?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
      <datalist id="tn-unidades"><option value="mg"><option value="µg"><option value="g"><option value="kcal"></datalist>
      <?php if ($pode): ?><button type="button" class="dep-outro-lote" data-tn-mais>+ nutriente</button><?php endif; ?>
      <div class="campo"><label for="tn-nota">Frase abaixo da tabela</label><textarea id="tn-nota" name="nota" rows="3" <?= $pode ? '' : 'disabled' ?>><?= e((string) ($tabela['nota'] ?: \App\Services\TabelaNutricionalService::NOTA_PADRAO)) ?></textarea></div>
      <?php if ($pode): ?><button class="botao" type="submit">Salvar tabela nutricional</button><?php endif; ?>
    </form>
    <div class="tn-previa">
      <?= Visao::renderizar('producao/_tabela_nutricional', ['t' => $tabela]) ?>
      <div class="tn-botoes">
        <button type="button" class="botao contorno pequeno" data-tn-png>⬇ Baixar imagem (PNG)</button>
        <a class="botao contorno pequeno" href="<?= url('/producao/fichas/' . (int) $produto['id'] . '/nutricional/pdf') ?>" data-tn-pdf>⬇ Baixar PDF</a>
      </div>
      <small class="suave">O PDF sai da tabela salva; a imagem sai do que está na tela.</small>
    </div>
  </div>
</section>
