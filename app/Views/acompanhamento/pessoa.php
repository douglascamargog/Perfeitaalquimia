<?php
/**
 * Acompanhamento de uma vendedora, em abas.
 * Variáveis: $p, $resumo, $antes, $det (porDia, ligacoes, contatos, reativacoes), $extra (carteira, funil, etapas, canais, prontas, agenda, atrasadas, pedidos),
 *            $meta (linha de Gestão › Metas ou null), $aba, $de, $ate, $periodo
 */
use App\Core\Visao;
use App\Models\ClienteEvento;
use App\Services\TelefoniaService as T;

$r = $resumo;
$x = $extra;
$d = fn($a, $b, bool $inverso = false) => Visao::renderizar('acompanhamento/_delta', ['a' => $a, 'b' => $b, 'inverso' => $inverso]);
$q = 'periodo=' . e($periodo) . '&de=' . e($de) . '&ate=' . e($ate);
$abas = ['resumo' => 'Resumo', 'ligacoes' => 'Ligações e gravações', 'carteira' => 'Carteira e funil', 'agenda' => 'Agenda', 'contatos' => 'WhatsApp e contatos', 'pedidos' => 'Reativações e pedidos'];
$situacoesPedido = ['aguardando_aprovacao' => 'Aguardando', 'aprovado' => 'Aprovado', 'faturado' => 'Faturado', 'recusado' => 'Recusado', 'cancelado' => 'Cancelado'];
?>
<a class="voltar-link" href="<?= url('/acompanhamento') ?>?<?= $q ?>">← Toda a equipe</a>
<div class="cabecalho-secao"><div><div class="sobretitulo">ACOMPANHAMENTO</div><h1><?= e($p['nome']) ?></h1><p><?= data_br($de) ?> a <?= data_br($ate) ?></p></div></div>
<?= Visao::renderizar('acompanhamento/_periodo', ['acao' => '/acompanhamento/pessoa/' . (int) $p['id'], 'periodo' => $periodo, 'de' => $de, 'ate' => $ate, 'extra' => ['aba' => $aba]]) ?>

<div class="ac-cards">
  <div class="card"><span>Meta do mês</span><strong data-pct-meta><?= $meta && $meta['meta'] > 0 ? round($meta['percentual'] ?? ($meta['vendido'] / $meta['meta'] * 100)) . '%' : '—' ?></strong>
    <small><?= $meta && $meta['meta'] > 0 ? dinheiro($meta['vendido']) . ' de ' . dinheiro($meta['meta']) : 'sem meta em Gestão › Metas' ?></small></div>
  <div class="card"><span>Ligações</span><strong><?= (int) ($r['ligacoes'] ?? 0) ?></strong><small><?= (int) ($r['taxa'] ?? 0) ?>% atendidas <?= $d($r['ligacoes'] ?? 0, $antes['ligacoes'] ?? 0) ?></small></div>
  <div class="card"><span>Tempo falado</span><strong><?= T::duracao((int) ($r['falado'] ?? 0)) ?></strong><small>média <?= T::duracao((int) ($r['medio'] ?? 0)) ?></small></div>
  <div class="card"><span>Contatos</span><strong><?= (int) ($r['contatos'] ?? 0) ?></strong><small><?= (int) ($r['clientes_contatados'] ?? 0) ?> clientes <?= $d($r['contatos'] ?? 0, $antes['contatos'] ?? 0) ?></small></div>
  <div class="card"><span>Reativações</span><strong><?= (int) ($r['reativacoes'] ?? 0) ?></strong><small>voltaram a comprar</small></div>
  <div class="card"><span>Pedidos</span><strong><?= (int) ($r['pedidos'] ?? 0) ?></strong><small><?= dinheiro((float) ($r['valor'] ?? 0)) ?> <?= $d($r['valor'] ?? 0, $antes['valor'] ?? 0) ?></small></div>
</div>

<nav class="abas" aria-label="Detalhes">
  <?php foreach ($abas as $k => $nome): ?><a class="<?= $k === $aba ? 'ativa' : '' ?>" href="<?= url('/acompanhamento/pessoa/' . (int) $p['id']) ?>?aba=<?= $k ?>&<?= $q ?>"><?= e($nome) ?></a><?php endforeach; ?>
</nav>
<div class="ac-aba">
<?php if ($aba === 'resumo'):
  $temMeta = $meta && $meta['meta'] > 0;
  $pctMeta = $temMeta ? $meta['vendido'] / $meta['meta'] * 100 : 0;
  $estado = !$temMeta ? 'neutro' : (!empty($meta['atingida']) ? 'batida' : (($meta['projecao_mes'] ?? null) !== null && $meta['projecao_mes'] < $meta['meta'] ? 'atras' : 'ok'));
  $canais = $x['canais'] ?? [];
  $maxCanal = max(1, ...array_map(fn($c) => (int) $c['qtd'], $canais ?: [['qtd' => 1]]));
  $naoAtendidas = (int) ($r['ligacoes'] ?? 0) ? 100 - (int) ($r['taxa'] ?? 0) : 0;
  $atencao = array_filter([
      (int) ($x['agenda']['atrasadas'] ?? 0) ? ['⏰', (int) $x['agenda']['atrasadas'] . ' retorno(s) atrasado(s) na agenda', 'agenda'] : null,
      $naoAtendidas >= 40 ? ['📵', $naoAtendidas . '% das ligações não foram atendidas', 'ligacoes'] : null,
      (int) ($x['carteira']['sem_90'] ?? 0) ? ['💤', (int) $x['carteira']['sem_90'] . ' cliente(s) sem comprar há 90 dias ou mais', 'carteira'] : null,
      !(int) ($r['contatos'] ?? 0) ? ['💬', 'Nenhum contato registrado no período', 'contatos'] : null,
      $temMeta && $estado === 'atras' && !empty($meta['necessario_dia']) ? ['🎯', 'Precisa de ' . dinheiro((float) $meta['necessario_dia']) . ' por dia para bater a meta', 'pedidos'] : null,
  ]);
?>
  <section class="card ac-hero" data-resumo-meta>
    <?= Visao::renderizar('graficos/_anel', ['pct' => $pctMeta, 'rotulo' => $temMeta ? 'da meta' : 'sem meta', 'estado' => $estado, 'tamanho' => 150]) ?>
    <div class="ac-hero-corpo">
      <div class="ac-hero-topo"><h2>Meta de <?= e(substr($ate, 5, 2) . '/' . substr($ate, 0, 4)) ?></h2><?= Visao::renderizar('graficos/_ritmo', ['m' => $meta ?? []]) ?></div>
      <?php if ($temMeta): ?>
        <?= Visao::renderizar('graficos/_barra_meta', ['m' => $meta]) ?>
        <div class="ac-hero-numeros">
          <div><span>Faturado</span><strong><?= dinheiro($meta['vendido']) ?></strong></div>
          <div><span>Meta</span><strong><?= dinheiro($meta['meta']) ?></strong></div>
          <div><span><?= !empty($meta['atingida']) ? 'Acima da meta' : 'Falta' ?></span><strong><?= dinheiro(!empty($meta['atingida']) ? $meta['vendido'] - $meta['meta'] : max(0, $meta['meta'] - $meta['vendido'])) ?></strong></div>
          <div><span>Projeção do mês</span><strong><?= isset($meta['projecao_mes']) ? dinheiro((float) $meta['projecao_mes']) : '—' ?></strong></div>
        </div>
        <p class="pequeno suave">A marca na barra mostra onde deveria estar hoje (<?= (int) ($meta['decorridos'] ?? 0) ?> de <?= (int) ($meta['dias_trabalho'] ?? 0) ?> dias úteis).</p>
      <?php else: ?>
        <p class="suave">Sem meta cadastrada para este mês em Gestão › Metas.</p>
      <?php endif; ?>
    </div>
  </section>

  <div class="ac-duas">
    <section class="card">
      <h2>Ligações por dia</h2>
      <?= Visao::renderizar('graficos/_barras_dia', [
          'dias' => array_map(fn($d) => ['dia' => $d['dia'], 'valores' => [(int) $d['atendidas'], (int) $d['total'] - (int) $d['atendidas']]], $det['porDia']),
          'series' => [['nome' => 'Atendidas', 'classe' => 's1'], ['nome' => 'Não atendidas', 'classe' => 's2']],
      ]) ?>
    </section>
    <section class="card">
      <h2>Pontos de atenção</h2>
      <?php if (!$atencao): ?><p class="ac-tudo-certo">✅ Nada fora do lugar neste período.</p><?php endif; ?>
      <ul class="ac-atencao" data-atencao>
        <?php foreach ($atencao as [$icone, $texto, $abaLink]): ?>
          <li><span aria-hidden="true"><?= $icone ?></span><a href="<?= url('/acompanhamento/pessoa/' . (int) $p['id']) ?>?aba=<?= $abaLink ?>&<?= $q ?>"><?= e($texto) ?></a></li>
        <?php endforeach; ?>
      </ul>
      <h2 class="ac-sub">Contatos por canal</h2>
      <?php if (!$canais): ?><p class="suave pequeno">Nenhum contato registrado no período.</p><?php endif; ?>
      <div class="ac-canais">
        <?php foreach ($canais as $c): ?>
          <div title="<?= e((ClienteEvento::TIPOS[$c['tipo']] ?? $c['tipo']) . ': ' . (int) $c['qtd']) ?>"><span><?= e(ClienteEvento::TIPOS[$c['tipo']] ?? $c['tipo']) ?></span>
            <i><b style="width: <?= round((int) $c['qtd'] / $maxCanal * 100) ?>%"></b></i><strong><?= (int) $c['qtd'] ?></strong></div>
        <?php endforeach; ?>
      </div>
    </section>
  </div>

<?php elseif ($aba === 'ligacoes'): ?>
  <section class="card sem-espaco">
    <div class="tabela-rolagem"><table class="tabela">
      <thead><tr><th>Quando</th><th>Cliente</th><th>Situação</th><th class="direita">Duração</th><th>Gravação</th></tr></thead>
      <tbody>
      <?php foreach ($det['ligacoes'] as $l): ?>
        <tr><td><?= data_br($l['inicio'], true) ?></td>
          <td><?= $l['cliente_id'] ? '<a href="' . url('/carteira/clientes/' . (int) $l['cliente_id']) . '">' . e($l['cliente']) . '</a>' : '<span class="suave">número não cadastrado</span>' ?></td>
          <td><span class="selo <?= $l['situacao'] === 'atendida' ? 'lima' : 'cinza' ?>"><?= e(T::SITUACOES[$l['situacao']] ?? $l['situacao']) ?></span></td>
          <td class="direita"><?= $l['duracao_seg'] ? T::duracao((int) $l['duracao_seg']) : '—' ?></td>
          <td><?php if ($l['gravacao']): ?><audio controls preload="none" src="<?= url('/ligacoes/' . (int) $l['id'] . '/gravacao') ?>" data-gravacao></audio><?php else: ?>—<?php endif; ?></td></tr>
      <?php endforeach; ?>
      <?php if (!$det['ligacoes']): ?><tr><td colspan="5" class="suave">Nenhuma ligação no período.</td></tr><?php endif; ?>
      </tbody>
    </table></div>
  </section>

<?php elseif ($aba === 'carteira'): $c = $x['carteira']; ?>
  <div class="ac-cards">
    <div class="card"><span>Na carteira</span><strong data-carteira-total><?= (int) ($c['total'] ?? 0) ?></strong><small><?= (int) ($c['clientes'] ?? 0) ?> clientes · <?= (int) ($c['leads'] ?? 0) ?> leads</small></div>
    <div class="card"><span>Compraram em 30 dias</span><strong><?= (int) ($c['compraram_30'] ?? 0) ?></strong><small>clientes ativos</small></div>
    <div class="card"><span>Sem compra</span><strong><?= (int) ($c['sem_30'] ?? 0) ?> · <?= (int) ($c['sem_60'] ?? 0) ?> · <?= (int) ($c['sem_90'] ?? 0) ?></strong><small>30 · 60 · 90+ dias</small></div>
    <div class="card"><span>Novos no período</span><strong><?= (int) ($c['novos'] ?? 0) ?></strong><small>clientes e leads cadastrados</small></div>
  </div>
  <div class="ac-duas">
    <section class="card"><h2>Funil da carteira agora</h2>
      <ul class="ac-funil"><?php $max = max(1, ...array_map(fn($f) => (int) $f['qtd'], $x['funil'] ?: [['qtd' => 1]])); foreach ($x['funil'] as $f): ?>
        <li><span><?= e($f['nome']) ?></span><i style="width: <?= round((int) $f['qtd'] / $max * 100) ?>%; background: <?= e($f['cor'] ?: 'var(--secundaria)') ?>"></i><b><?= (int) $f['qtd'] ?></b></li>
      <?php endforeach; ?></ul></section>
    <section class="card"><h2>Mudanças de etapa no período</h2>
      <ul class="ac-lista"><?php foreach ($x['etapas'] as $e): ?><li><strong><?= (int) $e['qtd'] ?>×</strong> <?= e($e['descricao']) ?></li><?php endforeach; ?>
        <?php if (!$x['etapas']): ?><li class="suave">Nenhuma mudança de etapa.</li><?php endif; ?></ul></section>
  </div>

<?php elseif ($aba === 'agenda'): $g = $x['agenda']; $feitas = (int) ($g['feitas'] ?? 0); ?>
  <div class="ac-cards">
    <div class="card"><span>Retornos feitos</span><strong data-feitas><?= $feitas ?></strong><small>no período</small></div>
    <div class="card"><span>No prazo</span><strong><?= $feitas ? round((int) $g['no_prazo'] / $feitas * 100) : 0 ?>%</strong><small><?= (int) ($g['no_prazo'] ?? 0) ?> feitos até a data</small></div>
    <div class="card"><span>Atrasados agora</span><strong class="<?= ($g['atrasadas'] ?? 0) ? 'ac-atraso' : '' ?>"><?= (int) ($g['atrasadas'] ?? 0) ?></strong><small>em aberto</small></div>
    <div class="card"><span>Hoje · futuros</span><strong><?= (int) ($g['hoje'] ?? 0) ?> · <?= (int) ($g['futuras'] ?? 0) ?></strong><small>na agenda</small></div>
  </div>
  <section class="card"><h2>Retornos atrasados</h2>
    <ul class="ac-lista"><?php foreach ($x['atrasadas'] as $t): ?>
      <li><time><?= data_br($t['vence_em']) ?></time> <?= e($t['titulo']) ?><?= $t['cliente_id'] ? ' · <a href="' . url('/carteira/clientes/' . (int) $t['cliente_id']) . '">' . e($t['cliente']) . '</a>' : '' ?></li>
    <?php endforeach; ?><?php if (!$x['atrasadas']): ?><li class="suave">Nada atrasado.</li><?php endif; ?></ul></section>

<?php elseif ($aba === 'contatos'): ?>
  <div class="ac-duas">
    <section class="card"><h2>Contatos por canal</h2>
      <dl class="pd-dados"><?php foreach ($x['canais'] as $cn): ?><div><dt><?= e(ClienteEvento::TIPOS[$cn['tipo']] ?? $cn['tipo']) ?></dt><dd><?= (int) $cn['qtd'] ?></dd></div><?php endforeach; ?></dl>
      <?php if (!$x['canais']): ?><p class="suave">Nenhum contato registrado.</p><?php endif; ?></section>
    <section class="card"><h2>Mensagens prontas usadas</h2>
      <ul class="ac-lista"><?php foreach ($x['prontas'] as $m): ?><li><strong><?= (int) $m['qtd'] ?>×</strong> <?= e($m['modelo']) ?></li><?php endforeach; ?>
        <?php if (!$x['prontas']): ?><li class="suave">Nenhuma mensagem pronta no período.</li><?php endif; ?></ul></section>
  </div>
  <section class="card"><h2>Últimos contatos</h2>
    <ul class="ac-lista">
      <?php foreach ($det['contatos'] as $c): ?>
        <li><time><?= data_br($c['ocorrido_em'], true) ?></time> <strong><?= e(ClienteEvento::TIPOS[$c['tipo']] ?? $c['tipo']) ?></strong> ·
          <a href="<?= url('/carteira/clientes/' . (int) $c['cliente_id']) ?>"><?= e($c['cliente']) ?></a><?php if ($c['descricao']): ?><br><small class="suave"><?= e(mb_strimwidth((string) $c['descricao'], 0, 140, '…')) ?></small><?php endif; ?></li>
      <?php endforeach; ?>
      <?php if (!$det['contatos']): ?><li class="suave">Nenhum contato registrado.</li><?php endif; ?>
    </ul></section>

<?php else: $ped = $x['pedidos']; $fat = array_filter($ped, fn($pp) => $pp['situacao'] === 'faturado'); ?>
  <div class="ac-cards">
    <div class="card"><span>Pedidos</span><strong><?= count($ped) ?></strong><small><?= count($fat) ?> faturados · <?= count(array_filter($ped, fn($pp) => $pp['situacao'] === 'recusado')) ?> recusados</small></div>
    <div class="card"><span>Ticket médio</span><strong><?= $fat ? dinheiro(array_sum(array_column($fat, 'total')) / count($fat)) : '—' ?></strong><small>dos faturados</small></div>
    <div class="card"><span>Reativações</span><strong><?= count($det['reativacoes']) ?></strong><small>clientes que voltaram</small></div>
  </div>
  <div class="ac-duas">
    <section class="card"><h2>Reativações</h2>
      <ul class="ac-lista">
        <?php foreach ($det['reativacoes'] as $rv): ?>
          <li><a href="<?= url('/carteira/clientes/' . (int) $rv['cliente_id']) ?>"><strong><?= e($rv['cliente']) ?></strong></a> — comprou em <?= data_br($rv['criado_em']) ?>
            (<?= dinheiro((float) $rv['total']) ?>), estava parado desde <?= data_br($rv['anterior']) ?></li>
        <?php endforeach; ?>
        <?php if (!$det['reativacoes']): ?><li class="suave">Nenhuma reativação no período.</li><?php endif; ?>
      </ul></section>
    <section class="card"><h2>Pedidos do período</h2>
      <ul class="ac-lista"><?php foreach ($ped as $pp): ?>
        <li><a href="<?= url('/pedidos/' . (int) $pp['id']) ?>">#<?= (int) $pp['id'] ?></a> · <?= e($pp['cliente']) ?> · <?= dinheiro((float) $pp['total']) ?>
          <span class="selo <?= $pp['situacao'] === 'faturado' ? 'lima' : 'cinza' ?>"><?= e($situacoesPedido[$pp['situacao']] ?? $pp['situacao']) ?></span></li>
      <?php endforeach; ?><?php if (!$ped): ?><li class="suave">Nenhum pedido.</li><?php endif; ?></ul></section>
  </div>
<?php endif; ?>
</div>
