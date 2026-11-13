<?php
/** Visão do mês (individual). Variáveis: $mes, $eu, $equipe, $tarefas, $participacao, $nomeMes, $pct, $ou, $link, $clientes */
use App\Services\MetaService;

$c = $eu['config'];
$temMeta = $c['meta'] > 0;
$maxDia = max(1, ...array_values($eu['por_dia'] ?: [1]));
$pendentes = array_slice(array_filter($tarefas, fn($t) => !$t['concluida_em']), 0, 4);
?>
<div class="grade-meu-mes">
  <section class="card meta-individual <?= $eu['atingida'] ? 'atingida' : '' ?>">
    <div class="meta-equipe-topo">
      <div>
        <span class="sobretitulo"><?= $eu['atingida'] ? 'META ALCANÇADA' : 'MINHA META DE ' . e(mb_strtoupper($nomeMes($mes))) ?></span>
        <h2><?= $temMeta ? dinheiro($c['meta']) : 'Defina sua meta' ?></h2>
        <p class="suave"><?= $eu['atingida'] ? 'Você chegou à meta. Continue registrando suas vendas.' : ($eu['passado'] ? 'Mês encerrado. Consulte seus registros.' : ($eu['futuro'] ? 'Prepare o mês e organize seus próximos contatos.' : 'Cada venda registrada atualiza o seu progresso.')) ?></p>
      </div>
      <?php if ($temMeta): ?><div class="meta-equipe-pct"><strong><?= $pct($eu['percentual']) ?></strong><span>da minha meta</span></div><?php endif; ?>
    </div>
    <?php if ($temMeta): ?>
      <div class="barra-meta clara"><span style="width: <?= min(100, $eu['percentual']) ?>%"></span>
        <?php if ($eu['projecao_mes']): ?><i class="marca-projecao" style="left: <?= min(100, $eu['projecao_mes'] / $c['meta'] * 100) ?>%" title="Projeção de fechamento"></i><?php endif; ?></div>
      <div class="linha-valores"><div><span>Vendido no mês</span><strong><?= dinheiro($eu['vendido']) ?></strong></div>
        <div><span><?= $eu['atingida'] ? 'Acima da meta' : 'Falta para chegar lá' ?></span><strong><?= dinheiro($eu['atingida'] ? $eu['acima'] : $eu['falta']) ?></strong></div></div>
    <?php endif; ?>

    <details class="form-meta" <?= $temMeta ? '' : 'open' ?>>
      <summary><?= $temMeta ? 'Editar minha meta e dias de trabalho' : 'Cadastrar minha meta' ?></summary>
      <form method="post" action="<?= url('/meu-mes/meta') ?>">
        <?= csrf_campo() ?><input type="hidden" name="mes" value="<?= e($mes) ?>">
        <div class="campo"><label for="mm-meta">Minha meta do mês (R$)</label><input type="text" inputmode="decimal" id="mm-meta" name="meta" value="<?= $temMeta ? number_format($c['meta'], 2, ',', '.') : '' ?>" placeholder="70.000,00" required></div>
        <fieldset class="dias-semana"><legend>Meus dias de trabalho</legend>
          <?php foreach (MetaService::DIAS_SEMANA as $n => $rotulo): ?><label class="opcao"><input type="checkbox" name="dias_semana[]" value="<?= $n ?>" <?= in_array($n, $c['dias_semana'], true) ? 'checked' : '' ?>> <?= $rotulo ?></label><?php endforeach; ?>
        </fieldset>
        <div class="campo"><label for="mm-notas">Anotações do mês</label><textarea id="mm-notas" name="anotacoes" rows="2"><?= e($c['anotacoes']) ?></textarea></div>
        <button class="botao" type="submit">Salvar</button>
      </form>
    </details>
  </section>

  <section class="card registrar-venda">
    <h2>Registrar venda</h2>
    <?= \App\Core\Visao::renderizar('meu_mes/_form_venda', ['mes' => $mes, 'voltar' => 'painel']) ?>
    <?php if ($c['anotacoes']): ?><p class="nota-mes"><strong>Anotações:</strong> <?= nl2br(e($c['anotacoes'])) ?></p><?php endif; ?>
  </section>
</div>

<div class="kpis-meu-mes">
  <div class="kpi destaque"><span>Vendas no mês</span><strong><?= (int) $eu['qtd_vendas'] ?></strong><small><?= (int) $eu['dias_com_venda'] ?> dia(s) com venda</small></div>
  <div class="kpi destaque"><span>Ticket médio</span><strong><?= dinheiro($eu['ticket_medio']) ?></strong><small>valor médio por venda</small></div>
  <div class="kpi"><span>Projeção de fechamento</span><strong><?= $ou($eu['projecao_mes']) ?></strong><small><?= $eu['projecao_mes'] === null ? 'Registre vendas para ver a projeção' : ($temMeta && !$eu['passado'] ? ($eu['projecao_mes'] >= $c['meta'] ? 'No ritmo para bater a meta' : 'Abaixo da meta em ' . dinheiro($c['meta'] - $eu['projecao_mes'])) : 'pelo ritmo das vendas') ?></small></div>
  <div class="kpi"><span>Projeção do dia (ritmo)</span><strong><?= $ou($eu['ritmo_dia']) ?></strong><small>média por dia de trabalho</small></div>
  <div class="kpi"><span>Necessário por dia</span><strong><?= $temMeta ? $ou($eu['necessario_dia']) : '—' ?></strong><small><?= (int) $eu['restantes'] ?> de <?= (int) $eu['dias_trabalho'] ?> dias de trabalho restantes</small></div>
  <div class="kpi"><span>Maior venda</span><strong><?= $eu['maior_venda'] ? dinheiro($eu['maior_venda']) : '—' ?></strong><small><?= $eu['melhor_dia'] ? 'Melhor dia: ' . data_br($eu['melhor_dia']['data']) . ' (' . dinheiro($eu['melhor_dia']['valor']) . ')' : 'sem vendas ainda' ?></small></div>
  <div class="kpi"><span>Comparado ao mês anterior</span><strong class="<?= ($eu['variacao'] ?? 0) >= 0 ? 'positivo' : 'negativo' ?>"><?= $eu['variacao'] === null ? '—' : ($eu['variacao'] >= 0 ? '+' : '') . $pct($eu['variacao']) ?></strong><small>mês anterior: <?= dinheiro($eu['mes_anterior']) ?></small></div>
  <?php if (!empty($faturadoSistema)): ?>
    <div class="kpi"><span>Faturado no sistema</span><strong><?= dinheiro($faturadoSistema['valor']) ?></strong><small><?= (int) $faturadoSistema['pedidos'] ?> pedido(s) faturado(s) com você como vendedor(a)</small></div>
  <?php endif; ?>
  <div class="kpi mm-top-clientes" data-top-mes><span>Seus melhores clientes do mês</span>
    <?php if (!empty($clientes['do_mes'])): ?><ol><?php foreach ($clientes['do_mes'] as $c): ?><li><?= e($c['nome']) ?> <b><?= dinheiro($c['valor']) ?></b></li><?php endforeach; ?></ol>
    <?php else: ?><small>sem pedidos faturados ainda</small><?php endif; ?>
    <a class="pequeno" href="<?= $link(['aba' => 'clientes']) ?>">Ver meus clientes →</a></div>
  <div class="kpi"><span>Minha participação na equipe</span><strong><?= $participacao === null ? '—' : $pct($participacao) ?></strong><small><?= $equipe ? 'do vendido pela equipe' : 'meta da equipe não cadastrada' ?></small></div>
</div>

<div class="grade-meu-mes">
  <section class="card">
    <h2>Vendas por dia</h2>
    <div class="barras-dias">
      <?php foreach ($eu['trabalho'] as $d): $v = $eu['por_dia'][$d] ?? 0; ?>
        <div class="<?= $d === date('Y-m-d') ? 'hoje' : '' ?>" title="<?= data_br($d) ?>: <?= dinheiro($v) ?>">
          <span style="height: <?= $v ? max(4, $v / $maxDia * 100) : 1 ?>%"></span><small><?= (int) substr($d, 8) ?></small>
        </div>
      <?php endforeach; ?>
    </div>
    <p class="pequeno suave">Somente dias de trabalho. Folgas e dias fora da sua escala ficam de fora.</p>
  </section>
  <section class="card">
    <div class="v4-painel-topo"><h2>Próximas tarefas</h2><a class="pequeno" href="<?= $link(['aba' => 'tarefas']) ?>">Ver todas →</a></div>
    <?php if (!$pendentes): ?><p class="suave">Nenhuma tarefa pendente neste mês.</p><?php endif; ?>
    <ul class="lista-tarefas">
      <?php foreach ($pendentes as $t): ?>
        <li class="<?= $t['vence_em'] < date('Y-m-d') ? 'atrasada' : '' ?>"><strong><?= e($t['titulo']) ?></strong><small><?= data_br($t['vence_em']) ?><?= $t['hora'] ? ' · ' . substr($t['hora'], 0, 5) : '' ?><?= $t['razao_social'] ? ' · ' . e($t['razao_social']) : '' ?><?= $t['prioridade'] === 'alta' ? ' · prioridade alta' : '' ?></small></li>
      <?php endforeach; ?>
    </ul>
  </section>
</div>
