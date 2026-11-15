<?php
/** Página da ordem de produção. Variáveis: $o, $previa (planejada), $consumo e $perdas (concluída), $pode */
use App\Core\Visao;
use App\Services\ProducaoService as P;

$icones = asset('img/icones.svg');
[$rotulo, $cor] = P::SITUACOES[$o['situacao']];
?>
<div class="cabecalho-secao">
  <div><div class="sobretitulo"><a href="<?= url('/producao/ordens') ?>">ORDENS DE PRODUÇÃO</a> · <?= $o['origem'] === 'reabastecimento' ? 'REABASTECIMENTO' : 'MANUAL' ?></div>
    <h1>OP <?= (int) $o['id'] ?> · <?= e($o['produto']) ?> <span class="selo <?= $cor ?>" data-situacao><?= $rotulo ?></span></h1>
    <?php if (!empty($o['cliente_nome'])): ?><p><span class="selo verde">Terceirização</span> para <strong><?= e($o['cliente_nome']) ?></strong> · rótulo "<?= e($o['nome_rotulo']) ?>"
      <?php if ($o['situacao'] === 'planejada'): ?>· <span class="<?= (int) $o['rotulos_estoque'] < (int) $o['quantidade'] ? 'pn-vermelho' : '' ?>"><?= (int) $o['rotulos_estoque'] ?> rótulos em estoque</span><?php endif; ?></p><?php endif; ?>
    <p><?= number_format((int) $o['quantidade'], 0, ',', '.') ?> unidade(s)<?= $o['lote'] ? ' · lote ' . e($o['lote']) : '' ?><?= $o['validade'] ? ' · validade ' . data_br($o['validade']) : '' ?>
      · criada por <?= pessoa($o['criador'] ?? null, null, false, 18, '—') ?> em <?= data_br($o['criado_em'], true) ?></p></div>
  <div class="acoes"><a class="botao contorno" href="<?= url('/producao/ordens/' . (int) $o['id'] . '/imprimir') ?>" target="_blank" rel="noopener"><svg class="icone"><use href="<?= $icones ?>#imprimir"/></svg>Imprimir ordem</a></div>
</div>
<?= Visao::renderizar('producao/_abas', ['atual' => '/producao/ordens']) ?>

<?php if ($o['situacao'] === 'planejada' && $previa): ?>
  <section class="card">
    <h2>Conferência do estoque</h2>
    <table class="tabela pr-tabela" data-previa-ordem>
      <thead><tr><th>Item</th><th class="direita">Por unidade</th><th class="direita">Vai usar</th><th class="direita">Em estoque</th><th class="direita">Falta</th><?php if ($pode): ?><th class="direita">Usado de verdade</th><?php endif; ?></tr></thead>
      <tbody>
      <?php foreach ($previa['linhas'] as $l): ?>
        <tr class="<?= $l['falta'] > 0 ? 'pr-falta' : '' ?>">
          <td><a href="<?= url('/producao/materias/' . $l['materia_prima_id']) ?>"><?= e($l['nome']) ?></a><?= $l['tipo'] === 'embalagem' ? ' <small class="suave">embalagem</small>' : '' ?>
            <?= $l['perda_pct'] > 0 ? '<small class="pr-perda">+' . number_format($l['perda_pct'], 1, ',', '.') . '% de perda' . ($l['perda_origem'] === 'materia' ? ' (média da matéria-prima)' : '') . '</small>' : '' ?></td>
          <td class="direita pequeno"><?= P::qtd($l['por_unidade'], $l['unidade']) ?></td>
          <td class="direita"><strong><?= P::qtd($l['precisa'], $l['unidade']) ?></strong></td>
          <td class="direita"><?= P::qtd($l['estoque'], $l['unidade']) ?></td>
          <td class="direita"><?= $l['falta'] > 0 ? '<strong class="pn-vermelho">' . P::qtd($l['falta'], $l['unidade']) . '</strong>' : '<span class="pn-verde">ok</span>' ?></td>
          <?php if ($pode): ?><td class="direita"><input form="form-concluir" type="text" inputmode="decimal" name="usado[<?= (int) $l['materia_prima_id'] ?>]" class="pr-usado"
            placeholder="<?= e(rtrim(rtrim(number_format($l['precisa'], 3, ',', ''), '0'), ',')) ?>" aria-label="Quanto de <?= e($l['nome']) ?> foi usado (<?= e($l['unidade']) ?>)"> <small><?= e($l['unidade']) ?></small></td><?php endif; ?>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <p><span class="pr-maximo" data-maximo="<?= (int) $previa['maximo'] ?>">Com o estoque de hoje dá para produzir até <strong><?= number_format((int) $previa['maximo'], 0, ',', '.') ?> unidades</strong><?= $previa['limita'] ? ' (quem limita: ' . e($previa['limita']) . ')' : '' ?>.</span>
      Custo de matéria-prima: <strong><?= dinheiro($previa['custo']) ?></strong> (<?= dinheiro($previa['custo'] / max(1, (int) $o['quantidade'])) ?> por unidade).</p>
    <?php if ($pode): ?><p class="pequeno suave">Ao concluir, preencha "Usado de verdade" com o que foi pesado/contado de cada item: isso mede o desperdício, que passa a ser somado nas próximas ordens. Em branco = baixa a previsão.</p><?php endif; ?>
    <?php if (!$previa['ok']): ?><div class="aviso erro" data-falta>Falta matéria-prima para esta quantidade. Registre a entrada de compra ou diminua a quantidade.</div><?php endif; ?>
    <?php if ($pode): ?>
      <div class="pr-acoes-ordem">
        <form method="post" action="<?= url('/producao/ordens/' . (int) $o['id'] . '/concluir') ?>" data-form-concluir id="form-concluir"
              onsubmit="return confirm('Concluir a ordem? As matérias-primas serão baixadas e o produto somado ao estoque.')">
          <?= csrf_campo() ?>
          <label for="quantidade_real">Unidades fabricadas</label>
          <input id="quantidade_real" name="quantidade_real" type="number" min="1" value="<?= (int) $o['quantidade'] ?>" class="pr-num">
          <button class="botao lima" type="submit"><svg class="icone"><use href="<?= $icones ?>#check"/></svg>Concluir produção</button>
        </form>
        <form method="post" action="<?= url('/producao/ordens/' . (int) $o['id'] . '/cancelar') ?>" data-form-cancelar onsubmit="return confirm('Cancelar esta ordem?')">
          <?= csrf_campo() ?><input type="text" name="motivo" maxlength="200" placeholder="Motivo do cancelamento" aria-label="Motivo do cancelamento">
          <button class="botao contorno" type="submit">Cancelar ordem</button>
        </form>
      </div>
    <?php endif; ?>
  </section>
<?php elseif ($o['situacao'] === 'concluida'): ?>
  <section class="card">
    <h2>Concluída em <?= data_br($o['concluida_em'], true) ?> por <?= pessoa($o['concluidor'] ?? null, null, false, 18, '—') ?></h2>
    <table class="tabela pr-tabela" data-consumo>
      <thead><tr><th>Matéria-prima baixada</th><th class="direita">Quantidade</th></tr></thead>
      <tbody><?php foreach ($consumo as $c): ?><tr><td><?= e($c['nome']) ?></td><td class="direita"><?= P::qtd((float) $c['quantidade'], $c['unidade']) ?></td></tr><?php endforeach; ?></tbody>
    </table>
    <p>Custo de matéria-prima: <strong><?= dinheiro((float) $o['custo_total']) ?></strong> · <?= dinheiro((float) $o['custo_total'] / max(1, (int) $o['quantidade'])) ?> por unidade.
      <a href="<?= url('/producao/acabados/' . (int) $o['produto_id']) ?>">Ver estoque acabado →</a></p>
    <?php if ($perdas): ?>
      <h3>Desperdício medido nesta ordem</h3>
      <table class="tabela pr-tabela" data-perdas>
        <thead><tr><th>Item</th><th class="direita">Previsto (sem perda)</th><th class="direita">Usado</th><th class="direita">Perda</th></tr></thead>
        <tbody><?php foreach ($perdas as $pp): ?><tr><td><?= e($pp['nome']) ?></td><td class="direita"><?= P::qtd((float) $pp['teorico'], $pp['unidade']) ?></td>
          <td class="direita"><?= P::qtd((float) $pp['usado'], $pp['unidade']) ?></td>
          <td class="direita <?= (float) $pp['perda_pct'] > 5 ? 'pn-vermelho' : '' ?>"><strong><?= number_format((float) $pp['perda_pct'], 1, ',', '.') ?>%</strong></td></tr><?php endforeach; ?></tbody>
      </table>
    <?php endif; ?>
  </section>
<?php else: ?>
  <section class="card"><h2>Ordem cancelada<?= $o['cancelada_em'] ? ' em ' . data_br($o['cancelada_em'], true) : '' ?></h2><p class="suave">Nenhum estoque foi mexido.</p></section>
<?php endif; ?>
<?php if ($o['observacoes']): ?><section class="card"><h2>Observações</h2><p><?= nl2br(e($o['observacoes'])) ?></p></section><?php endif; ?>
