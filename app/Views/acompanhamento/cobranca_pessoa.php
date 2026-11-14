<?php
/** Detalhe de quem cuida da Cobrança. Variáveis: $p, $r, $det (etapas, atrasos, porDia, semana, promessas, ligacoes), $de, $ate, $periodo */
use App\Core\Visao;
use App\Services\TelefoniaService as T;

$q = 'visao=cobranca&periodo=' . e($periodo) . '&de=' . e($de) . '&ate=' . e($ate);
$rotulo = ['cumprida' => ['Cumprida', 'lima'], 'quebrada' => ['Quebrada', 'vermelho'], 'aguardando' => ['Aguardando', 'cinza']];
?>
<a class="voltar-link" href="<?= url('/acompanhamento') ?>?<?= $q ?>">← Toda a Cobrança</a>
<div class="cabecalho-secao"><div><div class="sobretitulo">ACOMPANHAMENTO · COBRANÇA</div><h1><?= e($p['nome']) ?></h1><p><?= data_br($de) ?> a <?= data_br($ate) ?></p></div></div>
<?= Visao::renderizar('acompanhamento/_periodo', ['acao' => '/acompanhamento/cobranca/' . (int) $p['id'], 'periodo' => $periodo, 'de' => $de, 'ate' => $ate]) ?>

<div class="ac-cards">
  <div class="card"><span>Recuperado</span><strong><?= dinheiro((float) ($r['recuperado'] ?? 0)) ?></strong><small><?= (int) ($r['titulos_pagos'] ?? 0) ?> título(s)</small></div>
  <div class="card"><span>Tentativas</span><strong><?= (int) ($r['tentativas'] ?? 0) ?></strong><small><?= (int) ($r['com_sucesso'] ?? 0) ?> com sucesso</small></div>
  <div class="card"><span>Promessas</span><strong><?= (int) ($r['promessas'] ?? 0) ?></strong><small><?= (int) ($r['cumprida'] ?? 0) ?> cumpridas · <?= (int) ($r['quebrada'] ?? 0) ?> quebradas</small></div>
  <div class="card"><span>Em atraso agora</span><strong><?= dinheiro((float) ($r['em_aberto'] ?? 0)) ?></strong><small><?= (int) ($r['casos'] ?? 0) ?> casos · média <?= (int) round($r['atraso_medio'] ?? 0) ?> dias</small></div>
  <div class="card"><span>Ligações</span><strong><?= (int) ($r['ligacoes'] ?? 0) ?></strong><small><?= T::duracao((int) ($r['falado'] ?? 0)) ?></small></div>
</div>

<section class="card">
  <h2>Recuperado por dia</h2>
  <div class="ac-grafico"><canvas id="grafico-recuperado" data-dados="<?= e(json_encode($det['porDia'])) ?>" aria-label="Recuperado por dia"></canvas></div>
</section>

<div class="ac-duas">
  <section class="card"><h2>Casos por etapa</h2>
    <ul class="ac-funil"><?php $max = max(1, ...array_map(fn($f) => (int) $f['casos'], $det['etapas'] ?: [['casos' => 1]])); foreach ($det['etapas'] as $e): ?>
      <li><span><?= e($e['nome']) ?></span><i style="width: <?= round((int) $e['casos'] / $max * 100) ?>%; background: <?= e($e['cor'] ?: 'var(--secundaria)') ?>"></i><b><?= (int) $e['casos'] ?></b></li>
    <?php endforeach; ?></ul></section>
  <section class="card"><h2>Promessas para os próximos 7 dias</h2>
    <ul class="ac-lista"><?php foreach ($det['semana'] as $s): ?>
      <li><time><?= data_br($s['promessa_em']) ?></time> <a href="<?= url('/carteira/clientes/' . (int) $s['cliente_id']) ?>"><?= e($s['cliente']) ?></a> · <?= dinheiro((float) $s['promessa_valor']) ?></li>
    <?php endforeach; ?><?php if (!$det['semana']): ?><li class="suave">Nenhuma promessa para os próximos dias.</li><?php endif; ?></ul></section>
</div>

<div class="ac-duas">
  <section class="card"><h2>Maiores atrasos</h2>
    <ul class="ac-lista"><?php foreach ($det['atrasos'] as $a): ?>
      <li><a href="<?= url('/carteira/clientes/' . (int) $a['cliente_id']) ?>"><strong><?= e($a['cliente']) ?></strong></a> · <?= dinheiro((float) $a['valor_total']) ?>
        <span class="ac-atraso"><?= (int) $a['dias'] ?> dias</span> · <small class="suave"><?= e($a['etapa']) ?></small></li>
    <?php endforeach; ?><?php if (!$det['atrasos']): ?><li class="suave">Nenhum caso em aberto.</li><?php endif; ?></ul></section>
  <section class="card"><h2>Promessas do período</h2>
    <ul class="ac-lista"><?php foreach ($det['promessas'] as $pr): ?>
      <li><time><?= data_br($pr['ocorrido_em']) ?></time> <?= e($pr['cliente']) ?> · para <?= data_br($pr['data_prometida']) ?>
        <span class="selo <?= $rotulo[$pr['situacao']][1] ?>"><?= $rotulo[$pr['situacao']][0] ?></span></li>
    <?php endforeach; ?><?php if (!$det['promessas']): ?><li class="suave">Nenhuma promessa no período.</li><?php endif; ?></ul></section>
</div>

<section class="card sem-espaco">
  <h2 class="ac-titulo">Ligações e gravações</h2>
  <div class="tabela-rolagem"><table class="tabela">
    <thead><tr><th>Quando</th><th>Cliente</th><th>Situação</th><th class="direita">Duração</th><th>Gravação</th></tr></thead>
    <tbody>
    <?php foreach ($det['ligacoes'] as $l): ?>
      <tr><td><?= data_br($l['inicio'], true) ?></td><td><?= e($l['cliente'] ?? '—') ?></td>
        <td><span class="selo <?= $l['situacao'] === 'atendida' ? 'lima' : 'cinza' ?>"><?= e(T::SITUACOES[$l['situacao']] ?? $l['situacao']) ?></span></td>
        <td class="direita"><?= $l['duracao_seg'] ? T::duracao((int) $l['duracao_seg']) : '—' ?></td>
        <td><?php if ($l['gravacao']): ?><audio controls preload="none" src="<?= url('/ligacoes/' . (int) $l['id'] . '/gravacao') ?>" data-gravacao></audio><?php else: ?>—<?php endif; ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$det['ligacoes']): ?><tr><td colspan="5" class="suave">Nenhuma ligação no período.</td></tr><?php endif; ?>
    </tbody>
  </table></div>
</section>
