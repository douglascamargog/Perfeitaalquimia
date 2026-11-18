<?php
/** Página da ordem de produção. Variáveis: $o, $previa (planejada), $consumo e $perdas (concluída), $pode, $podeAprovar, $lotes (lotes de MP) */
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

<?php // Revisão/aprovação (Responsável Técnica) ?>
<section class="card pr-aprovacao" data-aprovacao>
  <?php if ($o['aprovada_em']): ?>
    <p>✅ Revisada e aprovada por <?= pessoa($o['aprovador'] ?? null, null, false, 20) ?> em <?= data_br($o['aprovada_em'], true) ?></p>
  <?php else: ?>
    <p>⏳ Aguardando a revisão e aprovação da Responsável Técnica.</p>
    <?php if (!empty($podeAprovar) && $o['situacao'] !== 'cancelada'): ?>
      <form method="post" action="<?= url('/producao/ordens/' . (int) $o['id'] . '/aprovar') ?>"><?= csrf_campo() ?><button class="botao lima pequeno" type="submit" data-aprovar-op>Revisar e aprovar</button></form>
    <?php endif; ?>
  <?php endif; ?>
</section>

<?php if ($o['situacao'] !== 'cancelada' && ($lotes || $o['situacao'] === 'planejada')):
  $linhasLote = $o['situacao'] === 'planejada' && $previa ? $previa['linhas'] : array_map(fn($c) => ['materia_prima_id' => $c['materia_prima_id'] ?? 0, 'nome' => $c['nome'], 'unidade' => $c['unidade'], 'precisa' => $c['quantidade']], $consumo); ?>
<section class="card" data-lotes-mp>
  <h2>Lotes das matérias-primas</h2>
  <p class="ajuda">O One sugere o lote que vence primeiro. Para usar outro, escolha e salve. Na conclusão, o saldo do lote baixa e fica registrado de qual lote veio cada matéria-prima.</p>
  <form method="post" action="<?= url('/producao/ordens/' . (int) $o['id'] . '/lotes') ?>"><?= csrf_campo() ?>
  <div class="tabela-rolagem"><table class="tabela">
    <thead><tr><th>Matéria-prima</th><th>Precisa</th><th>Lote</th><th>Fabricante</th><th>Fabricação</th><th>Validade</th></tr></thead>
    <tbody>
    <?php foreach ($linhasLote as $l): $mp = (int) $l['materia_prima_id']; $sel = $lotes[$mp] ?? []; $primeiro = $sel[0] ?? null; ?>
      <tr data-mp="<?= $mp ?>"><td><?= e($l['nome']) ?></td><td><?= P::qtd((float) $l['precisa'], (string) $l['unidade']) ?></td>
        <td><?php if ($pode && $o['situacao'] === 'planejada'): $opcoes = P::lotesMp($mp); ?>
          <?php if ($opcoes): ?><select name="lote[<?= $mp ?>]" aria-label="Lote de <?= e($l['nome']) ?>">
            <?php foreach ($opcoes as $op): ?><option value="<?= (int) $op['id'] ?>" <?= $primeiro && (int) $primeiro['lote_id'] === (int) $op['id'] ? 'selected' : '' ?>><?= e($op['lote']) ?> · saldo <?= P::qtd((float) $op['saldo'], (string) $l['unidade']) ?><?= $op['validade'] ? ' · vence ' . data_br($op['validade']) : '' ?></option><?php endforeach; ?>
          </select><?= count($sel) > 1 ? '<small class="suave"> + ' . (count($sel) - 1) . ' lote(s) para completar</small>' : '' ?>
          <?php else: ?><span class="selo laranja">sem lote em estoque</span><?php endif; ?>
        <?php else: ?><?= e(implode(', ', array_map(fn($x) => (string) $x['lote'], $sel)) ?: '—') ?><?php endif; ?></td>
        <td><?= e((string) ($primeiro['fabricante'] ?? '—')) ?></td><td><?= $primeiro && $primeiro['fabricacao'] ? data_br($primeiro['fabricacao']) : '—' ?></td>
        <td><?= $primeiro && $primeiro['validade'] ? data_br($primeiro['validade']) : '—' ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php if ($pode && $o['situacao'] === 'planejada'): ?><button class="botao contorno pequeno" type="submit">Salvar lotes</button><?php endif; ?>
  </form>
</section>
<?php endif; ?>

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
      <details class="card" data-editar-ordem style="margin-top:12px">
        <summary><strong>✏️ Editar a ordem</strong> <small class="suave">quantidade, datas, lote e observações</small></summary>
        <form method="post" action="<?= url('/producao/ordens/' . (int) $o['id'] . '/editar') ?>" style="margin-top:10px"><?= csrf_campo() ?>
          <div class="grade-campos">
            <div class="campo"><label>Quantidade<input type="number" name="quantidade" min="1" value="<?= (int) $o['quantidade'] ?>" required></label></div>
            <div class="campo"><label>Entrada na produção<input type="date" name="entrada_producao" value="<?= e((string) $o['entrada_producao']) ?>"></label></div>
            <div class="campo"><label>Previsto para<input type="date" name="previsto_para" value="<?= e((string) $o['previsto_para']) ?>"></label></div>
            <div class="campo"><label>Lote<input type="text" name="lote" maxlength="40" value="<?= e((string) $o['lote']) ?>"></label></div>
            <div class="campo"><label>Validade<input type="date" name="validade" value="<?= e((string) $o['validade']) ?>"></label></div>
          </div>
          <div class="campo"><label>Observações<textarea name="observacoes" rows="2"><?= e((string) $o['observacoes']) ?></textarea></label></div>
          <?php if ($o['aprovada_em']): ?><p class="pequeno suave">Mudar a quantidade tira a aprovação da Responsável Técnica: ela confere de novo.</p><?php endif; ?>
          <button class="botao pequeno" type="submit">Salvar</button>
        </form>
      </details>
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
