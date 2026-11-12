<?php
/** Calendário do mês. Variáveis: $mes, $dia, $eu, $tarefas, $link */
use App\Services\MetaService;

$c = $eu['config'];
$inicio = (int) date('w', strtotime("$mes-01"));
$folga = in_array($dia, $c['folgas'], true);
$tarefasDia = array_filter($tarefas, fn($t) => $t['vence_em'] === $dia);
$vendasDia = array_filter($eu['vendas'], fn($v) => $v['data'] === $dia);
?>
<div class="calendario-layout">
  <section class="card">
    <div class="calendario-semana"><?php foreach (['DOM', 'SEG', 'TER', 'QUA', 'QUI', 'SEX', 'SÁB'] as $d): ?><span><?= $d ?></span><?php endforeach; ?></div>
    <div class="calendario-grade">
      <?php for ($i = 0; $i < $inicio; $i++): ?><span class="vazio-cal"></span><?php endfor; ?>
      <?php foreach (MetaService::diasDoMes($mes) as $d):
          $trabalho = in_array($d, $eu['trabalho'], true);
          $venda = $eu['por_dia'][$d] ?? 0;
          $qtdTarefas = count(array_filter($tarefas, fn($t) => $t['vence_em'] === $d && !$t['concluida_em'])); ?>
        <a href="<?= $link(['aba' => 'calendario', 'dia' => $d]) ?>" class="<?= trim(($d === date('Y-m-d') ? 'hoje ' : '') . ($d === $dia ? 'selecionado ' : '') . ($trabalho ? '' : 'folga')) ?>">
          <strong><?= (int) substr($d, 8) ?></strong>
          <?php if (in_array($d, $c['folgas'], true)): ?><small>Folga</small><?php endif; ?>
          <?php if ($venda): ?><small class="valor-dia"><?= dinheiro($venda) ?></small><?php endif; ?>
          <?php if ($qtdTarefas): ?><i class="ponto-tarefa" title="<?= $qtdTarefas ?> tarefa(s)"></i><?php endif; ?>
        </a>
      <?php endforeach; ?>
    </div>
    <p class="pequeno suave">Dias em cinza ficam fora do cálculo de dias de trabalho. Clique num dia para ver detalhes ou marcar folga.</p>
  </section>

  <aside class="card">
    <span class="sobretitulo">DIA SELECIONADO</span>
    <h2><?= data_br($dia) ?></h2>
    <form method="post" action="<?= url('/meu-mes/folga') ?>"><?= csrf_campo() ?><input type="hidden" name="data" value="<?= e($dia) ?>">
      <button class="botao contorno pequeno" type="submit"><?= $folga ? 'Retirar folga' : 'Marcar folga neste dia' ?></button></form>
    <p class="linha-valores"><span>Vendas no dia</span><strong><?= dinheiro(array_sum(array_column($vendasDia, 'valor'))) ?></strong></p>
    <h3>Tarefas e compromissos</h3>
    <?php if (!$tarefasDia): ?><p class="suave pequeno">Nada agendado.</p><?php endif; ?>
    <ul class="lista-tarefas">
      <?php foreach ($tarefasDia as $t): ?><li class="<?= $t['concluida_em'] ? 'feita' : '' ?>"><strong><?= $t['hora'] ? substr($t['hora'], 0, 5) . ' · ' : '' ?><?= e($t['titulo']) ?></strong><?php if ($t['notas']): ?><small><?= e($t['notas']) ?></small><?php endif; ?></li><?php endforeach; ?>
    </ul>
    <details class="form-meta"><summary>+ Novo compromisso neste dia</summary>
      <form method="post" action="<?= url('/meu-mes/tarefas') ?>">
        <?= csrf_campo() ?><input type="hidden" name="tipo" value="compromisso"><input type="hidden" name="vence_em" value="<?= e($dia) ?>"><input type="hidden" name="voltar_aba" value="calendario">
        <div class="campo"><label for="c-titulo">O quê</label><input type="text" id="c-titulo" name="titulo" required maxlength="200"></div>
        <div class="campo"><label for="c-hora">Horário</label><input type="time" id="c-hora" name="hora"></div>
        <div class="campo"><label for="c-notas">Observação</label><input type="text" id="c-notas" name="notas" maxlength="1000"></div>
        <button class="botao" type="submit">Agendar</button>
      </form>
    </details>
  </aside>
</div>
