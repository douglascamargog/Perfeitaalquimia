<?php
/**
 * Agenda: semana em colunas (padrão) ou lista, com a faixa de atrasados no topo.
 * Variáveis: $grupos (atrasadas, hoje, futuras), $verTodas, $pessoa, $vendedores, $ver, $segunda, $feitasHoje
 */
use App\Core\Visao;

$hoje = date('Y-m-d');
$voltar = '/agenda?' . http_build_query(array_filter(['pessoa' => $pessoa, 'ver' => $ver, 'semana' => $segunda], fn($v) => $v !== null && $v !== ''));
$dias = array_map(fn($i) => date('Y-m-d', strtotime("$segunda +$i days")), range(0, 6));
$abertas = array_merge($grupos['hoje'], $grupos['futuras']);
$daSemana = array_filter($abertas, fn($t) => $t['vence_em'] >= $dias[0] && $t['vence_em'] <= $dias[6]);
$porDia = [];
foreach ($daSemana as $t) {
    $porDia[$t['vence_em']][] = $t;
}
foreach ($porDia as &$lista) {
    usort($lista, fn($a, $b) => [$a['hora'] === null, $a['hora']] <=> [$b['hora'] === null, $b['hora']]);
}
unset($lista);
$totalHoje = count($grupos['hoje']) + $feitasHoje;
$semanas = ['seg', 'ter', 'qua', 'qui', 'sex', 'sáb', 'dom'];
?>
<div class="cabecalho-secao">
  <div><div class="sobretitulo">ACOMPANHAMENTO</div><h1>Agenda</h1><p>Retornos e compromissos combinados com os clientes.</p></div>
  <div class="acoes">
    <nav class="ag-visao" aria-label="Visão">
      <a href="<?= url('/agenda') ?>?<?= e(http_build_query(array_filter(['pessoa' => $pessoa, 'ver' => 'semana']))) ?>" class="<?= $ver === 'semana' ? 'ativo' : '' ?>">Semana</a>
      <a href="<?= url('/agenda') ?>?<?= e(http_build_query(array_filter(['pessoa' => $pessoa, 'ver' => 'lista']))) ?>" class="<?= $ver === 'lista' ? 'ativo' : '' ?>" data-ver-lista>Lista</a>
    </nav>
    <?php if ($verTodas): ?>
      <form method="get" action="<?= url('/agenda') ?>">
        <input type="hidden" name="ver" value="<?= e($ver) ?>">
        <select name="pessoa" aria-label="Pessoa" onchange="this.form.submit()">
          <option value="">Todas as pessoas</option>
          <?php foreach ($vendedores as $v): ?><option value="<?= (int) $v['id'] ?>" <?= (int) $pessoa === (int) $v['id'] ? 'selected' : '' ?>><?= e($v['nome']) ?></option><?php endforeach; ?>
        </select>
        <noscript><button class="botao pequeno">Filtrar</button></noscript>
      </form>
    <?php endif; ?>
  </div>
</div>

<div class="agenda-resumo ag-resumo">
  <div class="card <?= $grupos['atrasadas'] ? 'ag-alerta' : '' ?>"><span>Atrasados</span><strong class="<?= $grupos['atrasadas'] ? 'vermelho' : '' ?>"><?= count($grupos['atrasadas']) ?></strong></div>
  <div class="card ag-hoje-card"><span>Para hoje</span><strong class="lima"><?= count($grupos['hoje']) ?></strong>
    <?php if ($totalHoje): ?><div class="ag-progresso" title="<?= $feitasHoje ?> de <?= $totalHoje ?> feitos hoje"><i style="width: <?= round($feitasHoje / $totalHoje * 100) ?>%"></i></div><small><?= $feitasHoje ?> de <?= $totalHoje ?> feitos hoje</small><?php endif; ?></div>
  <div class="card"><span>Próximos</span><strong><?= count($grupos['futuras']) ?></strong></div>
</div>

<?php if ($grupos['atrasadas']): ?>
<section class="ag-atrasadas" data-atrasadas>
  <h2>⏰ Atrasados <small><?= count($grupos['atrasadas']) ?> · resolva ou reagende</small></h2>
  <div class="ag-faixa"><?php foreach ($grupos['atrasadas'] as $t): ?><?= Visao::renderizar('agenda/_cartao', ['t' => $t, 'verTodas' => $verTodas, 'voltar' => $voltar]) ?><?php endforeach; ?></div>
</section>
<?php endif; ?>

<?php if ($ver === 'semana'): ?>
<section class="ag-semana-caixa" data-semana="<?= e($segunda) ?>">
  <div class="ag-semana-topo">
    <a class="botao-icone" href="<?= url('/agenda') ?>?<?= e(http_build_query(array_filter(['pessoa' => $pessoa, 'semana' => date('Y-m-d', strtotime("$segunda -7 days"))]))) ?>" aria-label="Semana anterior">‹</a>
    <strong><?= data_br($dias[0]) ?> a <?= data_br($dias[6]) ?></strong>
    <a class="botao-icone" href="<?= url('/agenda') ?>?<?= e(http_build_query(array_filter(['pessoa' => $pessoa, 'semana' => date('Y-m-d', strtotime("$segunda +7 days"))]))) ?>" aria-label="Próxima semana">›</a>
    <?php if ($segunda !== date('Y-m-d', strtotime('monday this week'))): ?><a class="b-chip" href="<?= url('/agenda') ?>?<?= e(http_build_query(array_filter(['pessoa' => $pessoa]))) ?>">Esta semana</a><?php endif; ?>
    <span class="ag-legenda"><i class="ag-retorno"></i>Retorno <i class="ag-compromisso"></i>Compromisso <i class="ag-cobranca"></i>Cobrança <i class="ag-outro"></i>Tarefa</span>
  </div>
  <div class="ag-semana">
    <?php foreach ($dias as $n => $dia): $itens = $porDia[$dia] ?? []; ?>
      <div class="ag-dia <?= $dia === $hoje ? 'hoje' : '' ?> <?= $dia < $hoje ? 'passado' : '' ?>" data-dia-coluna="<?= $dia ?>">
        <h3><span><?= $semanas[$n] ?></span> <?= (int) substr($dia, 8) ?><?= $dia === $hoje ? ' <em>hoje</em>' : '' ?><?= $itens ? ' <b>' . count($itens) . '</b>' : '' ?></h3>
        <?php foreach ($itens as $t): ?><?= Visao::renderizar('agenda/_cartao', ['t' => $t, 'verTodas' => $verTodas, 'voltar' => $voltar, 'compacto' => true]) ?><?php endforeach; ?>
        <?php if (!$itens): ?><p class="ag-vazio">—</p><?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
</section>
<?php else: ?>
  <?php foreach (['hoje' => 'Para hoje', 'futuras' => 'Próximos'] as $chave => $nome): ?>
    <section class="ag-lista-secao">
      <h2><?= $nome ?> <span class="selo <?= $chave === 'hoje' ? 'lima' : '' ?>"><?= count($grupos[$chave]) ?></span></h2>
      <?php if (!$grupos[$chave]): ?><p class="suave">Nada por aqui.</p><?php endif; ?>
      <div class="ag-lista"><?php foreach ($grupos[$chave] as $t): ?><?= Visao::renderizar('agenda/_cartao', ['t' => $t, 'verTodas' => $verTodas, 'voltar' => $voltar]) ?><?php endforeach; ?></div>
    </section>
  <?php endforeach; ?>
<?php endif; ?>

<?= Visao::renderizar('carteira/_drawer') ?>
