<?php
/**
 * Meta da EQUIPE (todos veem; só o gestor altera e lança o vendido).
 * Variáveis: $mes, $equipe (ou null), $podeEquipe, $nomeMes, $pct, $ou
 */
use App\Services\MetaService;

$q = $equipe;
$dias = $q['dias_semana'] ?? [1, 2, 3, 4, 5];
$feriados = implode(', ', array_map(fn($d) => substr($d, 8, 2) . '/' . substr($d, 5, 2), $q['folgas'] ?? []));
?>
<section class="meta-equipe">
  <div class="meta-equipe-topo">
    <div>
      <span class="sobretitulo">META DA EQUIPE · <?= e(mb_strtoupper($nomeMes($mes))) ?></span>
      <?php if ($q): ?>
        <h2><?= dinheiro($q['meta']) ?></h2>
        <p><?= $q['atingida'] ? 'Meta da equipe alcançada! 🎉' : ($q['passado'] ? 'Mês encerrado.' : 'Vendido pela equipe até ' . data_br($q['referencia']) . '.') ?></p>
      <?php else: ?>
        <h2>Meta ainda não definida</h2>
        <p><?= $podeEquipe ? 'Cadastre a meta da equipe para todos acompanharem as projeções.' : 'O supervisor de vendas ainda não cadastrou a meta da equipe deste mês.' ?></p>
      <?php endif; ?>
    </div>
    <?php if ($q): ?>
      <div class="meta-equipe-pct"><strong><?= $pct($q['percentual']) ?></strong><span>da meta da equipe</span></div>
    <?php endif; ?>
  </div>

  <?php if ($q): ?>
    <div class="barra-meta" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= round(min(100, $q['percentual'])) ?>">
      <span style="width: <?= min(100, $q['percentual']) ?>%"></span>
      <?php if ($q['projecao_mes'] && $q['meta'] > 0): ?><i class="marca-projecao" style="left: <?= min(100, $q['projecao_mes'] / $q['meta'] * 100) ?>%" title="Projeção de fechamento"></i><?php endif; ?>
    </div>
    <div class="meta-equipe-numeros">
      <div><span>Vendido até o momento</span><strong><?= dinheiro($q['vendido']) ?></strong></div>
      <div><span><?= $q['atingida'] ? 'Acima da meta' : 'Falta' ?></span><strong><?= dinheiro($q['atingida'] ? $q['acima'] : $q['falta']) ?></strong></div>
      <div class="destaque"><span>Projeção do mês</span><strong><?= $ou($q['projecao_mes']) ?></strong>
        <?php if ($q['projecao_mes'] !== null && $q['meta'] > 0 && !$q['passado']): ?><small><?= $q['projecao_mes'] >= $q['meta'] ? 'No ritmo para bater a meta' : 'Abaixo da meta em ' . dinheiro($q['meta'] - $q['projecao_mes']) ?></small><?php endif; ?></div>
      <div><span>Projeção do dia (ritmo atual)</span><strong><?= $ou($q['ritmo_dia']) ?></strong><small>média por dia de trabalho</small></div>
      <div><span>Necessário por dia</span><strong><?= $ou($q['necessario_dia']) ?></strong><small><?= (int) $q['restantes'] ?> de <?= (int) $q['dias_trabalho'] ?> dias de trabalho restantes</small></div>
    </div>
    <div class="meta-equipe-rodape">
      <details>
        <summary>Ver cálculo</summary>
        <p><strong><?= dinheiro($q['vendido']) ?></strong> vendidos até <?= data_br($q['referencia']) ?> ÷ <strong><?= (int) $q['decorridos'] ?></strong> dia(s) de trabalho decorridos
          = <strong><?= $ou($q['ritmo_dia']) ?></strong> por dia × <strong><?= (int) $q['dias_trabalho'] ?></strong> dias de trabalho no mês = <strong><?= $ou($q['projecao_mes']) ?></strong>.</p>
        <p>Dias de trabalho: <?= e(implode(', ', array_map(fn($d) => MetaService::DIAS_SEMANA[$d], $dias))) ?><?= $feriados ? '; sem expediente em ' . e($feriados) : '' ?>. O dia de referência conta como dia completo.</p>
      </details>
      <span class="pequeno">Atualizado por <?= e($q['linha']['atualizado_por_nome'] ?? '—') ?> em <?= data_br($q['linha']['atualizado_em'], true) ?></span>
    </div>

    <?php if ($q['lancamentos']): // Evolução: vendido acumulado (último lançamento de cada dia) × ritmo necessário até a meta
        $ultimoDoDia = [];
        foreach ($q['lancamentos'] as $lan) {
            $ultimoDoDia[$lan['data']] = (float) $lan['vendido']; // correções no mesmo dia: vale o último valor
        }
        $dadosEvolucao = ['dias' => array_values($q['trabalho']), 'meta' => (float) $q['meta'], 'vendido' => $ultimoDoDia];
    ?>
      <div class="grafico-evolucao"><canvas id="grafico-evolucao" aria-label="Evolução do vendido pela equipe no mês"></canvas></div>
      <script type="application/json" id="dados-evolucao"><?= json_encode($dadosEvolucao, JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
      <div class="legenda-evolucao"><span><i class="real"></i>Vendido (lançamentos do gestor)</span><span><i class="ideal"></i>Ritmo necessário para a meta</span></div>
    <?php endif; ?>
  <?php endif; ?>

  <?php if ($podeEquipe): ?>
    <details class="form-equipe" <?= $q ? '' : 'open' ?>>
      <summary><?= $q ? 'Atualizar meta e vendido da equipe' : 'Cadastrar meta da equipe' ?></summary>
      <form method="post" action="<?= url('/meu-mes/equipe') ?>">
        <?= csrf_campo() ?>
        <input type="hidden" name="mes" value="<?= e($mes) ?>">
        <div class="grade-campos">
          <div class="campo"><label for="eq-meta">Meta da equipe (R$)</label><input type="text" inputmode="decimal" id="eq-meta" name="meta" value="<?= $q ? number_format($q['meta'], 2, ',', '.') : '' ?>" placeholder="450.000,00" required></div>
          <div class="campo"><label for="eq-vendido">Vendido pela equipe até o momento (R$)</label><input type="text" inputmode="decimal" id="eq-vendido" name="vendido" value="<?= $q ? number_format($q['vendido'], 2, ',', '.') : '0,00' ?>" required>
            <div class="ajuda">Total acumulado no mês. Enquanto não há integração com o sistema de vendas, o gestor digita aqui.</div></div>
          <div class="campo"><label for="eq-data">Vendido até o dia</label><input type="date" id="eq-data" name="data_referencia" value="<?= e(min(date('Y-m-d'), date('Y-m-t', strtotime("$mes-01")))) ?>" min="<?= e("$mes-01") ?>" max="<?= e(min(date('Y-m-d'), date('Y-m-t', strtotime("$mes-01")))) ?>"></div>
          <div class="campo"><label for="eq-feriados">Feriados / sem expediente</label><input type="text" id="eq-feriados" name="feriados" value="<?= e($feriados) ?>" placeholder="Ex.: 02/11, 15/11, 20/11"></div>
        </div>
        <fieldset class="dias-semana"><legend>Dias de trabalho da equipe</legend>
          <?php foreach (MetaService::DIAS_SEMANA as $n => $rotulo): ?><label class="opcao"><input type="checkbox" name="dias_semana[]" value="<?= $n ?>" <?= in_array($n, $dias, true) ? 'checked' : '' ?>> <?= $rotulo ?></label><?php endforeach; ?>
        </fieldset>
        <button class="botao lima" type="submit">Salvar e recalcular projeções</button>
      </form>
    </details>
  <?php endif; ?>
</section>
