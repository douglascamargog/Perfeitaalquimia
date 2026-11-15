<?php
/** Visão do mês (individual). Variáveis: $mes, $eu, $equipe, $tarefas, $participacao, $nomeMes, $pct, $ou, $link, $clientes */
use App\Services\MetaService;

$c = $eu['config'];
$temMeta = $c['meta'] > 0;
$pendentes = array_slice(array_filter($tarefas, fn($t) => !$t['concluida_em']), 0, 4);
?>
<div class="grade-meu-mes">
  <section class="card meta-individual mm-hero <?= $eu['atingida'] ? 'atingida' : '' ?>" data-mm-hero>
    <div class="mm-hero-linha">
      <?= \App\Core\Visao::renderizar('graficos/_anel', ['pct' => $eu['percentual'], 'rotulo' => $temMeta ? 'da minha meta' : 'sem meta', 'tamanho' => 150,
          'estado' => !$temMeta ? 'neutro' : ($eu['atingida'] ? 'batida' : ($eu['projecao_mes'] !== null && $eu['projecao_mes'] < $c['meta'] && !$eu['passado'] ? 'atras' : 'ok'))]) ?>
      <div class="mm-hero-corpo">
        <span class="sobretitulo"><?= $eu['atingida'] ? 'META ALCANÇADA' : 'MINHA META DE ' . e(mb_strtoupper($nomeMes($mes))) ?></span>
        <h2><?= $temMeta ? dinheiro($eu['vendido']) . ' <small>de ' . dinheiro($c['meta']) . '</small>' : 'Defina sua meta' ?></h2>
        <?= \App\Core\Visao::renderizar('graficos/_ritmo', ['m' => $eu]) ?>
        <?php if ($temMeta): ?>
          <?= \App\Core\Visao::renderizar('graficos/_barra_meta', ['m' => $eu]) ?>
          <p class="mm-hero-frase"><?php if ($eu['atingida']): ?>Você passou da meta em <strong><?= dinheiro($eu['acima']) ?></strong>. Continue registrando as vendas!
            <?php elseif ($eu['passado']): ?>Mês encerrado: faltaram <strong><?= dinheiro($eu['falta']) ?></strong>.
            <?php else: ?>Falta <strong><?= dinheiro($eu['falta']) ?></strong><?= $eu['necessario_dia'] ? ' · precisa de <strong>' . dinheiro($eu['necessario_dia']) . '</strong> por dia de trabalho (' . (int) $eu['restantes'] . ' restantes)' : '' ?>.<?php endif; ?></p>
        <?php else: ?><p class="suave">Cadastre a sua meta para ver o ritmo e quanto falta por dia.</p><?php endif; ?>
      </div>
    </div>
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

<?php if (!empty($hoje)): ?>
<section class="card mm-hoje" data-para-hoje>
  <h2>Para fazer hoje</h2>
  <?php if (!$hoje['tarefas'] && !$hoje['recompra']): ?><p class="suave">Tudo em dia por aqui. 🎉 Que tal ligar para um cliente que está parado?</p><?php endif; ?>
  <ul class="mm-hoje-lista">
    <?php foreach ($hoje['tarefas'] as $t): $atrasada = $t['vence_em'] < date('Y-m-d'); $wa = preg_replace('/\D/', '', (string) ($t['whatsapp'] ?: $t['telefone'])); ?>
      <li class="<?= $atrasada ? 'atrasada' : '' ?>"><span class="mm-hoje-icone" aria-hidden="true"><?= $atrasada ? '⏰' : '📞' ?></span>
        <div><strong><?= e($t['titulo']) ?></strong><small><?= $t['cliente'] ? e($t['cliente']) . ' · ' : '' ?><?= $atrasada ? 'atrasada desde ' . data_br($t['vence_em']) : 'hoje' . ($t['hora'] ? ' às ' . substr($t['hora'], 0, 5) : '') ?></small></div>
        <span class="mm-hoje-acoes"><?php if ($t['cliente_id']): ?><a class="botao contorno pequeno" href="<?= url('/carteira/clientes/' . (int) $t['cliente_id']) ?>">Abrir</a><?php endif; ?>
          <?php if ($wa): ?><a class="botao contorno pequeno" href="https://wa.me/55<?= e($wa) ?>" target="_blank" rel="noopener noreferrer">WhatsApp</a><?php endif; ?></span></li>
    <?php endforeach; ?>
    <?php foreach ($hoje['recompra'] as $r): $wa = preg_replace('/\D/', '', (string) ($r['whatsapp'] ?: $r['telefone'])); ?>
      <li class="recompra"><span class="mm-hoje-icone" aria-hidden="true">🔁</span>
        <div><strong>Recompra: <?= e($r['nome_fantasia'] ?: $r['razao_social']) ?></strong><small>costuma comprar a cada <?= (int) $r['intervalo_medio_dias'] ?> dias · <?= (int) $r['dias_atraso'] ?> dia(s) de atraso<?= $r['ticket'] ? ' · ticket ' . dinheiro($r['ticket']) : '' ?></small></div>
        <span class="mm-hoje-acoes"><a class="botao contorno pequeno" href="<?= url('/carteira/clientes/' . (int) $r['id']) ?>">Abrir</a>
          <?php if ($wa): ?><a class="botao contorno pequeno" href="https://wa.me/55<?= e($wa) ?>" target="_blank" rel="noopener noreferrer">WhatsApp</a><?php endif; ?></span></li>
    <?php endforeach; ?>
  </ul>
</section>
<?php endif; ?>

<div class="kpis-meu-mes mm-grupos">
  <section class="card mm-grupo">
    <h3>Minhas vendas</h3>
    <dl>
      <div><dt>Vendas no mês</dt><dd><?= (int) $eu['qtd_vendas'] ?> <small><?= (int) $eu['dias_com_venda'] ?> dia(s) com venda</small></dd></div>
      <div><dt>Ticket médio</dt><dd><?= dinheiro($eu['ticket_medio']) ?></dd></div>
      <div><dt>Maior venda</dt><dd><?= $eu['maior_venda'] ? dinheiro($eu['maior_venda']) : '—' ?> <small><?= $eu['melhor_dia'] ? 'melhor dia ' . data_br($eu['melhor_dia']['data']) : '' ?></small></dd></div>
    </dl>
  </section>
  <section class="card mm-grupo">
    <h3>Projeção</h3>
    <dl>
      <div><dt>Fechamento previsto</dt><dd><?= $ou($eu['projecao_mes']) ?></dd></div>
      <div><dt>Ritmo por dia</dt><dd><?= $ou($eu['ritmo_dia']) ?> <small>necessário <?= $temMeta ? $ou($eu['necessario_dia']) : '—' ?></small></dd></div>
      <div><dt>Mês anterior</dt><dd class="<?= ($eu['variacao'] ?? 0) >= 0 ? 'positivo' : 'negativo' ?>"><?= $eu['variacao'] === null ? '—' : ($eu['variacao'] >= 0 ? '▲ +' : '▼ ') . $pct($eu['variacao']) ?> <small><?= dinheiro($eu['mes_anterior']) ?></small></dd></div>
    </dl>
  </section>
  <section class="card mm-grupo">
    <h3>Na equipe</h3>
    <dl>
      <?php if ($participaEquipe ?? true): // a meta da equipe é só dos setores configurados (ex.: Televendas + Prospecção e Reativação) ?>
        <div data-participacao><dt>Minha participação</dt><dd><?= $participacao === null ? '—' : $pct($participacao) ?> <small><?= $equipe ? 'do vendido pela equipe' : 'meta da equipe não cadastrada' ?></small></dd></div>
      <?php endif; ?>
      <?php if (!empty($faturadoSistema)): ?><div><dt>Faturado no sistema</dt><dd><?= dinheiro($faturadoSistema['valor']) ?> <small><?= (int) $faturadoSistema['pedidos'] ?> pedido(s)</small></dd></div><?php endif; ?>
    </dl>
    <div class="mm-top-clientes" data-top-mes><span class="mm-rotulo">Melhores clientes do mês</span>
      <?php if (!empty($clientes['do_mes'])): ?><ol><?php foreach ($clientes['do_mes'] as $cl): ?><li><?= e($cl['nome']) ?> <b><?= dinheiro($cl['valor']) ?></b></li><?php endforeach; ?></ol>
      <?php else: ?><small class="suave">sem pedidos faturados ainda</small><?php endif; ?>
      <a class="pequeno" href="<?= $link(['aba' => 'clientes']) ?>">Ver meus clientes →</a></div>
  </section>
</div>

<div class="grade-meu-mes">
  <section class="card">
    <h2>Vendas por dia</h2>
    <?= \App\Core\Visao::renderizar('graficos/_barras_dia', ['dias' => array_map(fn($d) => ['dia' => $d, 'valores' => [(float) ($eu['por_dia'][$d] ?? 0)]], $eu['trabalho']),
        'series' => [['nome' => 'Vendido', 'classe' => 's1']], 'dinheiro' => true,
        'referencia' => $temMeta && !$eu['atingida'] && $eu['necessario_dia'] ? (float) $eu['necessario_dia'] : null, 'refRotulo' => 'Necessário por dia']) ?>
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
