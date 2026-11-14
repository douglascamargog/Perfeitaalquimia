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

<section class="card sem-espaco">
  <div class="tabela-rolagem"><table class="tabela ac-tabela">
    <thead><tr><th>Vendedora</th><th class="direita">Meta do mês</th><th class="direita">Ligações</th><th class="direita">Tempo falado</th>
      <th class="direita">Contatos</th><th class="direita">WhatsApp</th><th class="direita">Retornos</th><th class="direita">Reativações</th><th class="direita">Pedidos</th><th class="direita">Valor</th></tr></thead>
    <tbody>
    <?php foreach ($linhas as $l): $a = $antes[$l['id']] ?? []; ?>
      <tr data-pessoa="<?= (int) $l['id'] ?>">
        <td><a href="<?= url('/acompanhamento/pessoa/' . (int) $l['id']) ?>?periodo=<?= e($periodo) ?>&de=<?= e($de) ?>&ate=<?= e($ate) ?>"><strong><?= e($l['nome']) ?></strong></a></td>
        <td class="direita" data-meta><?php if ($l['pct_meta'] !== null): ?><strong><?= $l['pct_meta'] ?>%</strong><br><small class="suave"><?= dinheiro($l['faturado_mes']) ?> de <?= dinheiro($l['meta']) ?></small><?php else: ?><small class="suave">sem meta</small><?php endif; ?></td>
        <td class="direita" data-ligacoes><?= $l['ligacoes'] ?><br><small class="suave"><?= $l['taxa'] ?>% atendidas</small></td>
        <td class="direita"><?= T::duracao($l['falado']) ?><br><small class="suave">média <?= $l['medio'] ? T::duracao($l['medio']) : '—' ?></small></td>
        <td class="direita" data-contatos><?= $l['contatos'] ?> <?= $d($l['contatos'], $a['contatos'] ?? 0) ?></td>
        <td class="direita" data-whatsapp><?= $l['whatsapp'] ?><br><small class="suave"><?= $l['mensagens_prontas'] ?> prontas</small></td>
        <td class="direita" data-tarefas><?= $l['tarefas_feitas'] ?><?= $l['pct_prazo'] !== null ? ' <small class="suave">(' . $l['pct_prazo'] . '% no prazo)</small>' : '' ?><br>
          <small class="<?= $l['tarefas_atrasadas'] ? 'ac-atraso' : 'suave' ?>"><?= $l['tarefas_atrasadas'] ?> atrasado(s)</small></td>
        <td class="direita" data-reativacoes><?= $l['reativacoes'] ?></td>
        <td class="direita"><?= $l['pedidos'] ?></td>
        <td class="direita"><?= dinheiro($l['valor']) ?> <?= $d($l['valor'], $a['valor'] ?? 0) ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$linhas): ?><tr><td colspan="10" class="suave">Ninguém para acompanhar neste setor.</td></tr><?php endif; ?>
    </tbody>
  </table></div>
</section>
<p class="pequeno suave">▲ ▼ comparam com o período anterior do mesmo tamanho. Meta: Gestão › Metas (mês da data final). Reativação: pedido faturado no período de um cliente que estava sem comprar há mais tempo que o limite de inatividade (Configurações). Clique na vendedora para ver as ligações, ouvir as gravações e os contatos.</p>
