<?php
/** Gestão › Acompanhamento (Vendas). Variáveis: $linhas, $antes (período anterior por id), $visoes, $de, $ate, $periodo, $setor, $setores, $modo */
use App\Core\Visao;
use App\Services\TelefoniaService as T;

$tot = fn(string $k) => array_sum(array_column($linhas, $k));
$totA = fn(string $k) => array_sum(array_column($antes, $k));
$d = fn($a, $b, bool $inverso = false) => Visao::renderizar('acompanhamento/_delta', ['a' => $a, 'b' => $b, 'inverso' => $inverso]);
$modos = ['simulado' => 'Demonstração: ligações e gravações simuladas', 'manual' => 'Ligue Talk em modo manual (ligações registradas com 1 clique)', 'api' => 'Ligue Talk conectado'];
?>
<div class="cabecalho-secao">
  <div><div class="sobretitulo">GESTÃO · ACOMPANHAMENTO</div><h1>Acompanhamento da equipe</h1>
    <p>Ligações e gravações do Ligue Talk, contatos registrados, reativações e pedidos de cada vendedora. <?= data_br($de) ?> a <?= data_br($ate) ?>.</p></div>
  <div class="acoes"><span class="selo <?= $modo === 'api' ? 'lima' : 'cinza' ?>"><?= e($modos[$modo] ?? $modo) ?></span>
    <?php if (\App\Core\Auth::pode('integracao.gerenciar')): ?>
      <form method="post" action="<?= url('/telefonia/testar') ?>"><?= csrf_campo() ?><button class="botao contorno pequeno" type="submit">Testar Ligue Talk</button></form>
      <?php if ($modo === 'api'): ?><form method="post" action="<?= url('/telefonia/sincronizar') ?>"><?= csrf_campo() ?><button class="botao contorno pequeno" type="submit">Buscar ligações agora</button></form><?php endif; ?>
    <?php endif; ?></div>
</div>
<?= \App\Core\Visao::renderizar('layout/_abas_grupo', ['grupo' => 'resultados', 'atual' => '/acompanhamento']) ?>

<?= Visao::renderizar('acompanhamento/_visoes', ['visoes' => $visoes, 'atual' => 'vendas', 'periodo' => $periodo, 'de' => $de, 'ate' => $ate]) ?>
<?= Visao::renderizar('acompanhamento/_periodo', ['acao' => '/acompanhamento', 'periodo' => $periodo, 'de' => $de, 'ate' => $ate, 'extra' => array_filter(['setor' => $setor])]) ?>

<?php if (count($setores) > 1): ?>
<div class="chips ac-setores">
  <a class="chip <?= !$setor ? 'ativo' : '' ?>" href="<?= url('/acompanhamento') ?>?periodo=<?= e($periodo) ?>&de=<?= e($de) ?>&ate=<?= e($ate) ?>">Todos os setores</a>
  <?php foreach ($setores as $s): ?><a class="chip <?= (int) $setor === (int) $s['id'] ? 'ativo' : '' ?>" href="<?= url('/acompanhamento') ?>?setor=<?= (int) $s['id'] ?>&periodo=<?= e($periodo) ?>&de=<?= e($de) ?>&ate=<?= e($ate) ?>"><?= e($s['nome']) ?></a><?php endforeach; ?>
</div>
<?php endif; ?>

<div class="ac-cards">
  <div class="card"><span>Ligações</span><strong data-total-ligacoes><?= $tot('ligacoes') ?></strong><small><?= $tot('ligacoes') ? round($tot('atendidas') / $tot('ligacoes') * 100) : 0 ?>% atendidas <?= $d($tot('ligacoes'), $totA('ligacoes')) ?></small></div>
  <div class="card"><span>Tempo falado</span><strong><?= T::duracao($tot('falado')) ?></strong><small><?= $tot('gravacoes') ?> gravação(ões) <?= $d($tot('falado'), $totA('falado')) ?></small></div>
  <div class="card"><span>Contatos</span><strong><?= $tot('contatos') ?></strong><small><?= $tot('whatsapp') ?> por WhatsApp <?= $d($tot('contatos'), $totA('contatos')) ?></small></div>
  <div class="card"><span>Agenda</span><strong><?= $tot('tarefas_feitas') ?></strong><small>retornos feitos · <?= $tot('tarefas_atrasadas') ?> atrasados</small></div>
  <div class="card"><span>Reativações</span><strong><?= $tot('reativacoes') ?></strong><small>voltaram a comprar <?= $d($tot('reativacoes'), $totA('reativacoes')) ?></small></div>
  <div class="card"><span>Pedidos</span><strong><?= $tot('pedidos') ?></strong><small><?= dinheiro((float) array_sum(array_column($linhas, 'valor'))) ?> <?= $d(array_sum(array_column($linhas, 'valor')), array_sum(array_column($antes, 'valor'))) ?></small></div>
</div>

<?php
// Ordem: quem está mais perto (ou acima) da meta primeiro; sem meta no fim
usort($linhas, fn($x, $y) => [$y['pct_meta'] !== null, $y['pct_meta'] ?? 0, $y['valor']] <=> [$x['pct_meta'] !== null, $x['pct_meta'] ?? 0, $x['valor']]);
$posicao = 0;
?>
<section class="ac-equipe" data-equipe>
  <?php foreach ($linhas as $l): $a = $antes[$l['id']] ?? []; $temMeta = $l['pct_meta'] !== null; $posicao += $temMeta ? 1 : 0;
    $link = url('/acompanhamento/pessoa/' . (int) $l['id']) . '?periodo=' . e($periodo) . '&de=' . e($de) . '&ate=' . e($ate); ?>
    <article class="card ac-pessoa <?= $temMeta && $posicao <= 3 ? 'podio p' . $posicao : '' ?>" data-pessoa="<?= (int) $l['id'] ?>">
      <a class="ac-pessoa-nome" href="<?= $link ?>">
        <?= \App\Services\EquipeService::avatar(['nome' => $l['nome'], 'foto' => $l['foto'] ?? null], 44) ?>
        <span><strong><?= e($l['nome']) ?></strong><small><?= $temMeta && $posicao <= 3 ? ['🥇', '🥈', '🥉'][$posicao - 1] . ' ' : '' ?><?= $l['pedidos'] ?> pedido(s) · <?= dinheiro($l['valor']) ?> <?= $d($l['valor'], $a['valor'] ?? 0) ?></small></span>
      </a>
      <div class="ac-pessoa-meta" data-meta>
        <?php if ($temMeta): ?>
          <div class="ac-pessoa-meta-topo"><strong><?= $l['pct_meta'] ?>%</strong><small><?= dinheiro($l['faturado_mes']) ?> de <?= dinheiro($l['meta']) ?></small></div>
          <?= Visao::renderizar('graficos/_barra_meta', ['m' => ['meta' => $l['meta'], 'vendido' => $l['faturado_mes'], 'atingida' => $l['faturado_mes'] >= $l['meta']]]) ?>
        <?php else: ?><small class="suave">sem meta</small><?php endif; ?>
      </div>
      <ul class="ac-pilulas">
        <li data-ligacoes title="Ligações (% atendidas)"><span>Ligações</span><strong><?= $l['ligacoes'] ?></strong><small><?= $l['taxa'] ?>% atendidas</small></li>
        <li title="Tempo falado"><span>Falado</span><strong><?= T::duracao($l['falado']) ?></strong><small>média <?= $l['medio'] ? T::duracao($l['medio']) : '—' ?></small></li>
        <li data-contatos><span>Contatos</span><strong><?= $l['contatos'] ?></strong><small><?= $d($l['contatos'], $a['contatos'] ?? 0) ?></small></li>
        <li data-whatsapp><span>WhatsApp</span><strong><?= $l['whatsapp'] ?></strong><small><?= $l['mensagens_prontas'] ?> prontas</small></li>
        <li data-tarefas class="<?= $l['tarefas_atrasadas'] ? 'alerta' : '' ?>"><span>Retornos</span><strong><?= $l['tarefas_feitas'] ?></strong><small class="<?= $l['tarefas_atrasadas'] ? 'ac-atraso' : '' ?>"><?= $l['tarefas_atrasadas'] ?> atrasado(s)</small></li>
        <li data-reativacoes><span>Reativações</span><strong><?= $l['reativacoes'] ?></strong><small>voltaram</small></li>
      </ul>
    </article>
  <?php endforeach; ?>
  <?php if (!$linhas): ?><p class="card suave">Ninguém para acompanhar neste setor.</p><?php endif; ?>
</section>
<p class="pequeno suave">▲ ▼ comparam com o período anterior do mesmo tamanho. Meta: Gestão › Metas (mês da data final). Reativação: pedido faturado no período de um cliente que estava sem comprar há mais tempo que o limite de inatividade (Configurações). Clique na vendedora para ver as ligações, ouvir as gravações e os contatos.</p>
