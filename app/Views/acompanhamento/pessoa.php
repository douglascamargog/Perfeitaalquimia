<?php
/** Acompanhamento de uma pessoa. Variáveis: $p, $resumo, $det (porDia, ligacoes, contatos, reativacoes), $de, $ate, $periodo */
use App\Core\Visao;
use App\Models\ClienteEvento;
use App\Services\TelefoniaService as T;

$r = $resumo;
?>
<a class="voltar-link" href="<?= url('/acompanhamento') ?>?periodo=<?= e($periodo) ?>&de=<?= e($de) ?>&ate=<?= e($ate) ?>">← Toda a equipe</a>
<div class="cabecalho-secao"><div><div class="sobretitulo">ACOMPANHAMENTO</div><h1><?= e($p['nome']) ?></h1><p><?= data_br($de) ?> a <?= data_br($ate) ?></p></div></div>
<?= Visao::renderizar('acompanhamento/_periodo', ['acao' => '/acompanhamento/pessoa/' . (int) $p['id'], 'periodo' => $periodo, 'de' => $de, 'ate' => $ate]) ?>

<div class="ac-cards">
  <div class="card"><span>Ligações</span><strong><?= (int) ($r['ligacoes'] ?? 0) ?></strong><small><?= (int) ($r['taxa'] ?? 0) ?>% atendidas</small></div>
  <div class="card"><span>Tempo falado</span><strong><?= T::duracao((int) ($r['falado'] ?? 0)) ?></strong><small>média <?= T::duracao((int) ($r['medio'] ?? 0)) ?></small></div>
  <div class="card"><span>Contatos</span><strong><?= (int) ($r['contatos'] ?? 0) ?></strong><small><?= (int) ($r['clientes_contatados'] ?? 0) ?> clientes</small></div>
  <div class="card"><span>Reativações</span><strong><?= (int) ($r['reativacoes'] ?? 0) ?></strong><small>voltaram a comprar</small></div>
  <div class="card"><span>Pedidos</span><strong><?= (int) ($r['pedidos'] ?? 0) ?></strong><small><?= dinheiro((float) ($r['valor'] ?? 0)) ?></small></div>
</div>

<section class="card">
  <h2>Ligações por dia</h2>
  <div class="ac-grafico"><canvas id="grafico-ligacoes" data-dados="<?= e(json_encode($det['porDia'])) ?>" aria-label="Ligações por dia"></canvas></div>
</section>

<section class="card sem-espaco">
  <h2 class="ac-titulo">Ligações e gravações</h2>
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

<div class="ac-duas">
  <section class="card">
    <h2>Contatos registrados</h2>
    <ul class="ac-lista">
      <?php foreach ($det['contatos'] as $c): ?>
        <li><time><?= data_br($c['ocorrido_em'], true) ?></time> <strong><?= e(ClienteEvento::TIPOS[$c['tipo']] ?? $c['tipo']) ?></strong> ·
          <a href="<?= url('/carteira/clientes/' . (int) $c['cliente_id']) ?>"><?= e($c['cliente']) ?></a><?php if ($c['descricao']): ?><br><small class="suave"><?= e(mb_strimwidth((string) $c['descricao'], 0, 140, '…')) ?></small><?php endif; ?></li>
      <?php endforeach; ?>
      <?php if (!$det['contatos']): ?><li class="suave">Nenhum contato registrado.</li><?php endif; ?>
    </ul>
  </section>
  <section class="card">
    <h2>Reativações</h2>
    <ul class="ac-lista">
      <?php foreach ($det['reativacoes'] as $x): ?>
        <li><a href="<?= url('/carteira/clientes/' . (int) $x['cliente_id']) ?>"><strong><?= e($x['cliente']) ?></strong></a> — comprou em <?= data_br($x['faturado_em']) ?>
          (<?= dinheiro((float) $x['total']) ?>), estava parado desde <?= data_br($x['anterior']) ?></li>
      <?php endforeach; ?>
      <?php if (!$det['reativacoes']): ?><li class="suave">Nenhuma reativação no período.</li><?php endif; ?>
    </ul>
  </section>
</div>
