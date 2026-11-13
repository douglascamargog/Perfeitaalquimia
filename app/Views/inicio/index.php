<?php
/**
 * Tela inicial em blocos ("bento grid").
 * Variáveis: $usuario, $modulos, $saudacao, $falas (One), $painel (App\Services\PainelInicio::montar)
 * Cada bloco só aparece quando o perfil tem acesso ao dado.
 */
use App\Core\Auth;

$icones = asset('img/icones.svg');
$primeiroNome = explode(' ', $usuario['nome'])[0];
$p = $painel;
$n = fn($v) => number_format((float) $v, 0, ',', '.');
$pct = fn($v) => number_format(min(100, max(0, (float) $v)), 0, ',', '.');
$curto = fn(float $v) => $v >= 1000000 ? 'R$ ' . number_format($v / 1000000, 1, ',', '.') . ' mi' : ($v >= 10000 ? 'R$ ' . number_format($v / 1000, 1, ',', '.') . ' mil' : dinheiro($v));

// Agenda: atrasados + hoje + próximos (até 6)
$agenda = $p['agenda'];
$proximos = $agenda ? array_slice([...$agenda['atrasadas'], ...$agenda['hoje'], ...$agenda['futuras']], 0, 6) : [];
$hoje = date('Y-m-d');

// Atividade dos últimos 7 dias (preenche os dias sem registro)
$semana = [];
if ($p['carteira']) {
    $porDia = array_column($p['carteira']['semana'], 'contatos', 'dia');
    for ($i = 6; $i >= 0; $i--) {
        $d = date('Y-m-d', strtotime("-$i days"));
        $semana[] = ['dia' => $d, 'total' => (int) ($porDia[$d] ?? 0)];
    }
}
$maxSemana = max(1, ...array_column($semana ?: [['total' => 1]], 'total'));

// Atalhos do topo (conforme o perfil; a diretoria tem os seus)
$focos = $p['focos'];
$atalhosFoco = [ // atalhos da área principal da pessoa (quando não é do comercial)
    'marketing' => [['Mural de posts', '/marketing', 'calendario', 'marketing.ver'], ['Planejamento', '/marketing/planejamento', 'alvo', 'marketing.ver'], ['Leads do site', '/leads', 'pessoas', 'leads.ver']],
    'producao'  => [['Produção', '/producao', 'fabrica', 'producao.ver'], ['Ordens de produção', '/producao/ordens', 'check', 'producao.ver'], ['Matérias-primas', '/producao/materias', 'caixa', 'producao.ver']],
    'aprovacao' => [['Aprovação', '/aprovacao', 'check', 'pedidos.aprovar'], ['Pedidos', '/pedidos', 'carrinho', 'pedidos.ver_todos'], ['Cobrança', '/cobranca', 'alerta', 'cobranca.ver']],
    'cobranca'  => [['Cobrança', '/cobranca', 'alerta', 'cobranca.ver'], ['Aprovação', '/aprovacao', 'check', 'pedidos.aprovar'], ['Pedidos', '/pedidos', 'carrinho', 'pedidos.ver_todos']],
    'diretoria' => [['Painel de vendas', '/painel', 'grafico', 'painel.ver'], ['Aprovação', '/aprovacao', 'check', 'pedidos.aprovar'], ['Usuários', '/usuarios', 'usuario', 'usuarios.gerenciar']],
];
$principal = in_array('comercial', $focos, true) ? null : ($focos[0] ?? null);
$atalhos = $principal && isset($atalhosFoco[$principal])
    ? array_values(array_map(fn($a) => array_slice($a, 0, 3), array_filter($atalhosFoco[$principal], fn($a) => Auth::pode($a[3]))))
    : array_filter([
    Auth::pode('carteira.ver_propria') || Auth::pode('carteira.ver_todas') ? ['Novo estabelecimento', '/carteira/clientes/novo', 'mais'] : null,
    Auth::pode('meu_mes.usar') ? ['Registrar venda', '/meu-mes', 'grafico'] : null,
    Auth::pode('cobranca.ver') ? ['Cobrança', '/cobranca', 'alerta'] : null,
    Auth::pode('academia.ver') ? ['Academia', '/academia', 'livro'] : null,
]);
?>
<div class="bento">

  <!-- ===== Saudação ===== -->
  <section class="b-card b-hero span-6" aria-label="Saudação">
    <div class="b-hero-texto">
      <span class="b-etiqueta"><?= e(ucfirst($p['data_extenso'])) ?></span>
      <h1><?= e(explode(',', explode('!', $saudacao['texto'])[0])[0]) ?>,<br><span><?= e($primeiroNome) ?></span></h1>
      <p>Eu sou o One, seu ajudante. Separei abaixo o que merece sua atenção hoje.</p>
      <div class="b-atalhos">
        <?php foreach (array_slice($atalhos, 0, 3) as $i => [$rotulo, $link, $icone]): ?>
          <a class="<?= $i === 0 ? 'b-atalho-forte' : '' ?>" href="<?= url($link) ?>"><svg class="icone"><use href="<?= $icones ?>#<?= $icone ?>"/></svg><?= e($rotulo) ?></a>
        <?php endforeach; ?>
      </div>
    </div>
    <img class="b-hero-one" src="<?= asset('img/one/one-' . e($saudacao['humor']) . '-invertido.svg') ?>" alt="One, o ajudante" width="170" height="187">
  </section>

  <!-- ===== Previsão do tempo (carregada pelo navegador) ===== -->
  <?php if ($p['clima']): ?>
    <section class="b-card b-clima span-3" data-clima data-lat="<?= e((string) $p['clima']['lat']) ?>" data-lng="<?= e((string) $p['clima']['lng']) ?>" aria-label="Previsão do tempo">
      <header class="b-topo"><span>Tempo · <?= e($p['clima']['cidade']) ?></span><span class="b-icone-redondo" data-clima-icone></span></header>
      <div class="b-clima-agora">
        <strong data-clima-temp>--°</strong>
        <div><b data-clima-texto>Carregando…</b><small data-clima-extra>&nbsp;</small></div>
      </div>
      <ol class="b-clima-dias" data-clima-dias></ol>
      <p class="b-clima-dica" data-clima-dica hidden></p>
    </section>
  <?php endif; ?>

  <!-- ===== Hoje (agenda) — ou, para a diretoria, os pedidos que aguardam aprovação ===== -->
  <?php if ($p['aprovacao']): $ap = $p['aprovacao']; ?>
    <a class="b-card b-escuro span-3" href="<?= url('/aprovacao') ?>" data-card="aprovacao">
      <header class="b-topo"><span>Pedidos para aprovar</span><span class="b-icone-redondo lima"><svg class="icone"><use href="<?= $icones ?>#check"/></svg></span></header>
      <strong class="b-numero-grande"><?= $n($ap['qtd']) ?></strong>
      <p class="b-sub"><?= $ap['qtd'] ? $curto((float) $ap['valor']) . ' aguardando o Financeiro' : 'Nenhum pedido parado na aprovação.' ?></p>
      <span class="b-link">Abrir aprovação →</span>
    </a>
  <?php elseif ($agenda !== null): ?>
    <a class="b-card b-escuro span-3" href="<?= url('/agenda') ?>" aria-label="Agenda de hoje">
      <header class="b-topo"><span>Retornos de hoje</span><span class="b-icone-redondo lima"><svg class="icone"><use href="<?= $icones ?>#calendario"/></svg></span></header>
      <strong class="b-numero-grande"><?= count($agenda['hoje']) ?></strong>
      <p class="b-sub"><?= count($agenda['atrasadas']) ? '<span class="b-alerta">' . count($agenda['atrasadas']) . ' atrasado(s)</span> · ' : '' ?><?= count($agenda['futuras']) ?> nos próximos dias</p>
      <?php $urgentes = array_slice([...$agenda['atrasadas'], ...$agenda['hoje']], 0, 3); ?>
      <?php if ($urgentes): ?>
        <ul class="b-lista-escura">
          <?php foreach ($urgentes as $t): ?><li><span class="<?= $t['vence_em'] < $hoje ? 'b-alerta' : '' ?>"><?= $t['vence_em'] < $hoje ? 'atrasado' : 'hoje' ?></span><?= e(mb_strimwidth((string) ($t['razao_social'] ?? $t['titulo']), 0, 30, '…')) ?></li><?php endforeach; ?>
        </ul>
      <?php else: ?>
        <p class="b-sub suave-escuro">Agenda livre. Bom momento para prospectar ou reativar clientes.</p>
      <?php endif; ?>
      <span class="b-link">Abrir agenda →</span>
    </a>
  <?php elseif ($p['marketing']): $mk = $p['marketing']; ?>
    <a class="b-card b-escuro span-3" href="<?= url('/marketing') ?>" aria-label="Posts aguardando aprovação">
      <header class="b-topo"><span>Aguardando aprovação</span><span class="b-icone-redondo lima"><svg class="icone"><use href="<?= $icones ?>#check"/></svg></span></header>
      <strong class="b-numero-grande"><?= $mk['aprovacao'] ?></strong>
      <p class="b-sub"><?= $mk['ajuste'] ? '<span class="b-alerta">' . $mk['ajuste'] . ' com ajuste pedido</span>' : 'nenhum ajuste pendente' ?></p>
      <?php if ($mk['proximos']): ?>
        <ul class="b-lista-escura">
          <?php foreach ($mk['proximos'] as $pp): ?><li><span><?= date('d/m', strtotime($pp['data_publicacao'])) ?></span><?= e(mb_strimwidth($pp['titulo'], 0, 30, '…')) ?></li><?php endforeach; ?>
        </ul>
      <?php endif; ?>
      <span class="b-link">Abrir mural →</span>
    </a>
  <?php elseif ($p['cobranca']): ?>
    <a class="b-card b-escuro span-3" href="<?= url('/cobranca') ?>">
      <header class="b-topo"><span>Promessas para hoje</span><span class="b-icone-redondo lima"><svg class="icone"><use href="<?= $icones ?>#moeda"/></svg></span></header>
      <strong class="b-numero-grande"><?= $n($p['cobranca']['promessas_hoje']) ?></strong>
      <p class="b-sub"><?= $n($p['cobranca']['sem_contato']) ?> cliente(s) sem contato</p>
      <span class="b-link">Abrir cobrança →</span>
    </a>
  <?php endif; ?>

  <!-- ===== Indicadores ===== -->
  <!-- ===== Aniversariantes do mês (só dia e mês, nunca o ano) ===== -->
  <?php if ($p['aniversariantes']): ?>
    <section class="b-card span-3 b-aniver" data-card="aniversariantes">
      <header class="b-topo"><span>Aniversariantes de <?= e(['janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'][(int) date('n') - 1]) ?></span><span class="b-icone-redondo" aria-hidden="true">🎂</span></header>
      <ul class="b-aniver-lista">
        <?php foreach (array_slice($p['aniversariantes'], 0, 6) as $a): ?>
          <li class="<?= $a['hoje'] ? 'hoje' : ($a['passou'] ? 'passou' : '') ?>">
            <?= \App\Services\EquipeService::avatar($a, 34) ?>
            <span><strong><?= e($a['apelido'] ?: explode(' ', $a['nome'])[0]) ?></strong><small><?= e($a['nome']) ?></small></span>
            <b><?= $a['hoje'] ? 'Hoje! 🎉' : str_pad((string) $a['dia'], 2, '0', STR_PAD_LEFT) . '/' . date('m') ?></b>
          </li>
        <?php endforeach; ?>
      </ul>
      <?php if (count($p['aniversariantes']) > 6): ?><small class="suave">e mais <?= count($p['aniversariantes']) - 6 ?> pessoa(s)</small><?php endif; ?>
    </section>
  <?php endif; ?>

  <?php if ($p['meta']): $m = $p['meta']; ?>
    <a class="b-card b-kpi span-3" href="<?= url('/meu-mes') ?>">
      <header class="b-topo"><span>Minha meta</span><span class="b-icone-redondo"><svg class="icone"><use href="<?= $icones ?>#alvo"/></svg></span></header>
      <strong class="b-valor"><?= $curto((float) $m['vendido']) ?></strong>
      <div class="b-barra"><i style="width: <?= $pct($m['percentual']) ?>%"></i></div>
      <small><?= $pct($m['percentual']) ?>% de <?= $curto((float) $m['meta']) ?> · <?= $n($m['qtd_vendas']) ?> venda(s)<?= $m['qtd_vendas'] ? ' · ticket ' . $curto((float) $m['ticket_medio']) : '' ?></small>
    </a>
  <?php endif; ?>

  <?php if ($p['equipe']): $q = $p['equipe']; ?>
    <a class="b-card b-kpi span-3 <?= $q['projecao_mes'] !== null && $q['projecao_mes'] >= $q['meta'] ? 'b-verde' : '' ?>" href="<?= url('/meu-mes') ?>">
      <header class="b-topo"><span>Meta da equipe</span><span class="b-icone-redondo"><svg class="icone"><use href="<?= $icones ?>#pessoas"/></svg></span></header>
      <strong class="b-valor"><?= $pct($q['percentual']) ?>%</strong>
      <div class="b-barra"><i style="width: <?= $pct($q['percentual']) ?>%"></i><?php if ($q['projecao_mes'] && $q['meta'] > 0): ?><b style="left: <?= $pct($q['projecao_mes'] / $q['meta'] * 100) ?>%" title="Projeção"></b><?php endif; ?></div>
      <small>Projeção <?= $q['projecao_mes'] !== null ? $curto((float) $q['projecao_mes']) : '—' ?> de <?= $curto((float) $q['meta']) ?></small>
    </a>
  <?php endif; ?>

  <?php if ($p['carteira']): $c = $p['carteira']; ?>
    <a class="b-card b-kpi span-3" href="<?= url('/carteira') ?>">
      <header class="b-topo"><span><?= Auth::pode('carteira.ver_todas') ? 'Carteira' : 'Minha carteira' ?></span><span class="b-icone-redondo"><svg class="icone"><use href="<?= $icones ?>#loja"/></svg></span></header>
      <strong class="b-valor"><?= $n($c['total']) ?> <small>lojas</small></strong>
      <div class="b-mini-grafico" aria-label="Contatos nos últimos 7 dias">
        <?php foreach ($semana as $d): ?><i style="height: <?= max(6, round($d['total'] / $maxSemana * 100)) ?>%" title="<?= data_br($d['dia']) ?>: <?= $d['total'] ?> contato(s)"></i><?php endforeach; ?>
      </div>
      <small><?= $n($c['clientes']) ?> clientes · <?= $n($c['recompra']) ?> passaram do tempo de recompra</small>
    </a>
  <?php endif; ?>

  <?php if ($p['prospeccao']): $ps = $p['prospeccao']; ?>
    <a class="b-card b-kpi b-lima span-3" href="<?= url('/prospeccao') ?>" data-card="prospeccao">
      <header class="b-topo"><span>Prospecção</span><span class="b-icone-redondo escuro"><svg class="icone"><use href="<?= $icones ?>#alvo"/></svg></span></header>
      <strong class="b-valor"><?= $n($ps['aguardando']) ?> <small>leads</small></strong>
      <div class="b-barra"><i style="width: <?= $pct(($ps['leads'] - $ps['aguardando']) / $ps['leads'] * 100) ?>%"></i></div>
      <small>aguardam o primeiro contato · <?= $n($ps['leads'] - $ps['aguardando']) ?> de <?= $n($ps['leads']) ?> abordados</small>
    </a>
  <?php endif; ?>

  <?php if ($p['cobranca']): $cb = $p['cobranca']; ?>
    <a class="b-card b-kpi span-3" href="<?= url('/cobranca') ?>">
      <header class="b-topo"><span>Cobrança em aberto</span><span class="b-icone-redondo"><svg class="icone"><use href="<?= $icones ?>#moeda"/></svg></span></header>
      <strong class="b-valor"><?= $curto((float) $cb['total']) ?></strong>
      <div class="b-barra vermelha"><i style="width: <?= $cb['casos'] ? $pct($cb['mais90'] / $cb['casos'] * 100) : 0 ?>%"></i></div>
      <small><?= $n($cb['casos']) ?> caso(s) · <?= $n($cb['mais90']) ?> com mais de 90 dias · recuperado <?= $curto((float) $cb['recuperado_mes']) ?></small>
    </a>
  <?php endif; ?>

  <?php if ($p['vendas']): $vd = $p['vendas']; ?>
    <a class="b-card b-kpi span-3" href="<?= url('/painel') ?>">
      <header class="b-topo"><span>Faturado no mês</span><span class="b-icone-redondo"><svg class="icone"><use href="<?= $icones ?>#grafico"/></svg></span></header>
      <strong class="b-valor"><?= $curto((float) $vd['faturado']) ?></strong>
      <?php if ($vd['meta'] > 0): ?><div class="b-barra"><i style="width: <?= $pct($vd['faturado'] / $vd['meta'] * 100) ?>%"></i></div><?php endif; ?>
      <?php if ($vd['anterior'] !== null && $vd['anterior'] > 0): $var = ($vd['faturado'] - $vd['anterior']) / $vd['anterior'] * 100; ?>
        <small class="<?= $var >= 0 ? 'b-sobe' : 'b-desce' ?>"><?= $var >= 0 ? '▲' : '▼' ?> <?= number_format(abs($var), 1, ',', '.') ?>% vs. o mesmo período do mês passado</small>
      <?php endif; ?>
      <small><?= $vd['meta'] > 0 ? $pct($vd['faturado'] / $vd['meta'] * 100) . '% da meta da equipe · ' : '' ?><?= $n($vd['pedidos']) ?> pedido(s) faturado(s)<?= $vd['a_aprovar'] ? ' · ' . $vd['a_aprovar'] . ' para aprovar' : '' ?></small>
    </a>
  <?php endif; ?>

  <?php if ($p['recompra'] !== null): ?>
    <a class="b-card b-kpi span-3" href="<?= url('/recompra') ?>">
      <header class="b-topo"><span>Recompra</span><span class="b-icone-redondo"><svg class="icone"><use href="<?= $icones ?>#relogio"/></svg></span></header>
      <strong class="b-valor"><?= $n($p['recompra']) ?> <small>clientes</small></strong>
      <small>passaram do tempo normal de recompra · ligue antes que esfriem</small>
    </a>
  <?php endif; ?>

  <?php if ($p['producao']): $pr = $p['producao']; ?>
    <a class="b-card b-kpi span-3 <?= $pr['mp_abaixo'] ? 'b-alerta-suave' : '' ?>" href="<?= url('/producao/materias?situacao=alerta') ?>" data-card="producao-mp">
      <header class="b-topo"><span>Matérias-primas</span><span class="b-icone-redondo"><svg class="icone"><use href="<?= $icones ?>#fabrica"/></svg></span></header>
      <strong class="b-valor"><?= $n($pr['mp_abaixo']) ?> <small>abaixo do mínimo</small></strong>
      <small><?= $n($pr['mp_acabando']) ?> acabando em breve · comprar antes de parar a produção</small>
    </a>
    <a class="b-card b-kpi span-3" href="<?= url('/producao') ?>" data-card="producao-reabastecer">
      <header class="b-topo"><span>Reabastecimento</span><span class="b-icone-redondo"><svg class="icone"><use href="<?= $icones ?>#caixa"/></svg></span></header>
      <strong class="b-valor"><?= $n($pr['reabastecer']) ?> <small>produto(s)</small></strong>
      <small>abaixo do estoque mínimo · <?= $n($pr['planejadas']) ?> ordem(ns) planejada(s)</small>
    </a>
  <?php endif; ?>

  <?php if ($p['marketing']): $mk = $p['marketing']; ?>
    <a class="b-card b-kpi span-3" href="<?= url('/marketing') ?>">
      <header class="b-topo"><span>Posts desta semana</span><span class="b-icone-redondo"><svg class="icone"><use href="<?= $icones ?>#calendario"/></svg></span></header>
      <strong class="b-valor"><?= $mk['semana'] ?> <small>de <?= $mk['meta'] ?></small></strong>
      <div class="b-barra"><i style="width: <?= $mk['meta'] ? $pct($mk['semana'] / $mk['meta'] * 100) : 0 ?>%"></i></div>
      <small><?= $mk['publicados_semana'] ?> publicado(s) · <?= $mk['aprovacao'] ?> aguardando aprovação</small>
    </a>
  <?php endif; ?>

  <?php if ($p['ecommerce']): $ec = $p['ecommerce']; $v = $ec['variacao']['faturamento']; ?>
    <a class="b-card b-kpi span-3" href="<?= url('/marketing/ecommerce') ?>">
      <header class="b-topo"><span>E-commerce no mês</span><span class="b-icone-redondo"><svg class="icone"><use href="<?= $icones ?>#carrinho"/></svg></span></header>
      <strong class="b-valor"><?= $curto((float) $ec['atual']['faturamento']) ?></strong>
      <small><?php if ($v !== null): ?><b style="color: <?= $v >= 0 ? '#16a34a' : '#dc2626' ?>"><?= $v >= 0 ? '▲' : '▼' ?> <?= number_format(abs($v), 1, ',', '.') ?>%</b> vs. mês anterior · <?php endif; ?>hoje <?= $curto((float) $ec['hoje']['faturamento']) ?> em <?= $n($ec['hoje']['pedidos']) ?> pedido(s)</small>
    </a>
  <?php endif; ?>

  <!-- ===== Funil (trilha de etapas) ===== -->
  <?php if ($p['funil']): $etapasAbertas = array_values(array_filter($p['funil'], fn($e) => in_array($e['tipo'], ['aberta', 'ganho'], true))); ?>
    <section class="b-card span-8" aria-label="Funil de vendas">
      <header class="b-topo"><h2>Funil de vendas</h2><a class="b-chip" href="<?= url('/carteira/funil') ?>">Abrir funil →</a></header>
      <ol class="b-trilha">
        <?php foreach ($etapasAbertas as $i => $e): ?>
          <li style="--cor: <?= e($e['cor']) ?>">
            <a href="<?= url('/carteira/lista') ?>?etapa=<?= $e['id'] ?>">
              <span class="b-trilha-ponto"><?= $e['tipo'] === 'ganho' ? '<svg class="icone"><use href="' . $icones . '#check"/></svg>' : $i + 1 ?></span>
              <strong><?= $n($e['total']) ?></strong>
              <small><?= e($e['nome']) ?></small>
            </a>
          </li>
        <?php endforeach; ?>
      </ol>
      <?php
        $totalFunil = array_sum(array_column($p['funil'], 'total'));
        $ganhos = array_sum(array_column(array_filter($p['funil'], fn($e) => $e['tipo'] === 'ganho'), 'total'));
        $negociando = array_sum(array_column(array_filter($p['funil'], fn($e) => in_array($e['nome'], ['Em negociação', 'Proposta enviada', 'Aguardando retorno'], true)), 'total'));
      ?>
      <div class="b-funil-rodape">
        <div><small>Total no funil</small><strong><?= $n($totalFunil) ?></strong></div>
        <div><small>Em negociação</small><strong><?= $n($negociando) ?></strong></div>
        <div><small>Clientes ativos</small><strong><?= $n($ganhos) ?></strong></div>
        <div class="destaque"><small>Conversão</small><strong><?= $totalFunil ? number_format($ganhos / $totalFunil * 100, 1, ',', '.') : '0' ?>%</strong></div>
      </div>
    </section>
  <?php endif; ?>

  <!-- ===== One sugere ===== -->
  <section class="b-card b-one <?= $p['funil'] ? 'span-4' : 'span-12' ?>" aria-label="Sugestões do One">
    <header class="b-topo"><h2><img src="<?= asset('img/one/one-ideia-padrao.svg') ?>" alt="" width="28" height="31"> One sugere</h2><span class="b-chip"><?= count($falas) ?></span></header>
    <ul class="b-sugestoes">
      <?php foreach ($falas as $f): ?>
        <li>
          <p><?= e($f['texto']) ?></p>
          <?php if (!empty($f['link'])): ?><a href="<?= url($f['link']) ?>"><?= e($f['acao'] ?? 'Abrir') ?> →</a><?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ul>
  </section>

  <!-- ===== Próximos retornos ===== -->
  <?php if ($agenda !== null): ?>
    <section class="b-card span-8" aria-label="Próximos retornos">
      <header class="b-topo"><h2>Próximos retornos</h2><a class="b-chip" href="<?= url('/agenda') ?>">Ver todos →</a></header>
      <?php if (!$proximos): ?>
        <div class="b-vazio">
          <img src="<?= asset('img/one/one-piscando-padrao.svg') ?>" alt="" width="70" height="77">
          <p>Nenhum retorno agendado. Ao registrar um contato, marque o <strong>próximo contato</strong> e ele aparece aqui.</p>
        </div>
      <?php else: ?>
        <div class="tabela-rolagem">
          <table class="b-tabela">
            <thead><tr><th>Quando</th><th>Cliente</th><th>Cidade</th><th>Tarefa</th><th>Situação</th></tr></thead>
            <tbody>
            <?php foreach ($proximos as $t): $situacao = $t['vence_em'] < $hoje ? ['Atrasado', 'vermelho'] : ($t['vence_em'] === $hoje ? ['Hoje', 'lima'] : ['Agendado', 'cinza']); ?>
              <tr>
                <td><?= data_br($t['vence_em']) ?></td>
                <td><strong><?= e($t['razao_social'] ?? '—') ?></strong></td>
                <td class="suave"><?= e(trim(($t['cidade'] ?? '') . ' / ' . ($t['uf'] ?? ''), ' /')) ?></td>
                <td><?= e($t['titulo']) ?></td>
                <td><span class="selo <?= $situacao[1] ?>"><?= $situacao[0] ?></span></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </section>
  <?php endif; ?>

  <!-- ===== Módulos ===== -->
  <section class="b-card <?= $agenda !== null ? 'span-4' : 'span-12' ?>" aria-label="Módulos">
    <header class="b-topo"><h2>Seus módulos</h2></header>
    <ul class="b-modulos">
      <?php foreach ($modulos as $mod): ?>
        <li>
          <?php if ($mod['pronto']): ?><a href="<?= url($mod['url']) ?>"><?php else: ?><span class="em-breve"><?php endif; ?>
            <span class="b-icone-redondo"><svg class="icone"><use href="<?= $icones ?>#<?= e($mod['icone']) ?>"/></svg></span>
            <span><strong><?= e($mod['titulo']) ?></strong><small><?= $mod['pronto'] ? e($mod['descricao']) : 'Em breve' ?></small></span>
          <?= $mod['pronto'] ? '</a>' : '</span>' ?>
        </li>
      <?php endforeach; ?>
    </ul>
  </section>
</div>
