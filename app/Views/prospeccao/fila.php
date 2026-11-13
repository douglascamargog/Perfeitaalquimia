<?php
/**
 * Prospecção › Para abordar: a fila única de leads (de qualquer origem).
 * Variáveis: $f (filtros da carteira), $p (ProspeccaoService::painelFila), $origens, $cidades, $prioridades, $vendedores
 */
use App\Core\Auth;
use App\Core\Visao;
use App\Services\Normalizar;

$icones = asset('img/icones.svg');
$n = fn($v) => number_format((float) $v, 0, ',', '.');
$filtrosUrl = array_filter(['origem' => $f['origem'], 'cidade' => $f['cidade'], 'prioridade' => $f['prioridade'], 'responsavel' => $f['responsavel']]);
$linkCarteira = fn(array $extra = []) => url('/carteira/lista') . '?' . e(http_build_query(['tipo' => 'lead'] + $filtrosUrl + $extra));
$linkPagina = fn(int $pg) => url('/prospeccao') . '?' . e(http_build_query($filtrosUrl + ['pagina' => $pg]));
$maxFunil = max(1, ...array_column($p['funil'], 'total') ?: [1]);
?>
<div class="cabecalho-secao">
  <div><div class="sobretitulo">COMERCIAL · PROSPECÇÃO</div><h1>Prospecção</h1>
    <p>Todos os leads que ainda não compram, venham de onde vierem: listas importadas, base de CNPJs, site, anúncios, indicação.</p></div>
  <div class="acoes">
    <a class="botao" href="<?= url('/prospeccao/empresas?esconder=1') ?>"><svg class="icone"><use href="<?= $icones ?>#lupa"/></svg>Encontrar empresas</a>
    <a class="botao contorno" href="<?= url('/carteira/funil') ?>?tipo=lead"><svg class="icone"><use href="<?= $icones ?>#pessoas"/></svg>Funil dos leads</a>
  </div>
</div>
<?= Visao::renderizar('prospeccao/_abas', ['atual' => 'fila']) ?>

<form class="filtros card" method="get" action="<?= url('/prospeccao') ?>" data-filtros>
  <div class="filtros-grade">
    <select name="origem" aria-label="Origem"><option value="">Todas as origens</option>
      <?php foreach ($origens as $o): ?><option value="<?= (int) $o['id'] ?>" <?= (string) $f['origem'] === (string) $o['id'] ? 'selected' : '' ?>><?= e($o['nome']) ?></option><?php endforeach; ?></select>
    <select name="cidade" aria-label="Cidade"><option value="">Todas as cidades</option>
      <?php foreach ($cidades as $c): $nome = is_array($c) ? ($c['cidade'] ?? $c['nome'] ?? '') : $c; ?><option <?= $f['cidade'] === $nome ? 'selected' : '' ?>><?= e($nome) ?></option><?php endforeach; ?></select>
    <select name="prioridade" aria-label="Prioridade"><option value="">Todas as prioridades</option>
      <?php foreach ($prioridades as $pr): ?><option <?= $f['prioridade'] === $pr ? 'selected' : '' ?>><?= e($pr) ?></option><?php endforeach; ?></select>
    <?php if ($vendedores): ?><select name="responsavel" aria-label="Responsável"><option value="">Toda a equipe</option>
      <?php foreach ($vendedores as $v): ?><option value="<?= (int) $v['id'] ?>" <?= (string) $f['responsavel'] === (string) $v['id'] ? 'selected' : '' ?>><?= e($v['nome']) ?></option><?php endforeach; ?></select><?php endif; ?>
  </div>
  <?php if ($filtrosUrl): ?><a class="pequeno" href="<?= url('/prospeccao') ?>">Limpar filtros</a><?php endif; ?>
</form>

<div class="bento">
  <section class="b-card b-escuro span-4" data-card="aguardando"><header class="b-topo"><span>Aguardam o primeiro contato</span><span class="b-icone-redondo lima"><svg class="icone"><use href="<?= $icones ?>#alvo"/></svg></span></header>
    <strong class="b-numero-grande" data-aguardando><?= $n($p['aguardando']) ?></strong>
    <div class="b-barra"><i style="width: <?= $p['leads'] ? round(($p['leads'] - $p['aguardando']) / $p['leads'] * 100) : 0 ?>%"></i></div>
    <p class="b-sub"><?= $n($p['leads'] - $p['aguardando']) ?> de <?= $n($p['leads']) ?> leads já abordados</p></section>
  <section class="b-card b-kpi span-2"><header class="b-topo"><span>Abordados em 7 dias</span></header><strong class="b-valor" data-abordados><?= $n($p['abordados_7d']) ?></strong><small>leads com contato registrado</small></section>
  <section class="b-card b-kpi span-2"><header class="b-topo"><span>Em negociação</span></header><strong class="b-valor"><?= $n($p['negociacao']) ?></strong><small><a href="<?= $linkCarteira() ?>">ver na carteira →</a></small></section>
  <section class="b-card b-kpi b-lima span-2"><header class="b-topo"><span>Viraram clientes</span></header><strong class="b-valor" data-convertidos><?= $n($p['clientes']) ?></strong><small>das mesmas origens</small></section>
  <section class="b-card b-kpi span-2"><header class="b-topo"><span>Leads na fila</span></header><strong class="b-valor"><?= $n($p['leads']) ?></strong><small><?= count($p['origens']) ?> origem(ns)</small></section>

  <section class="b-card span-8" data-card="origens"><header class="b-topo"><h2>Qual origem funciona melhor</h2><span class="b-chip">leads + clientes de cada origem</span></header>
    <div class="tabela-rolagem">
      <table class="tabela pr-tabela ps-origens">
        <thead><tr><th>Origem</th><th class="direita">Total</th><th class="direita">Aguardando</th><th class="direita">Abordados</th><th class="direita">Clientes</th><th>Conversão</th></tr></thead>
        <tbody>
        <?php foreach ($p['origens'] as $o): ?>
          <tr><td><?= $o['origem_id'] ? '<a href="' . url('/prospeccao') . '?origem=' . (int) $o['origem_id'] . '">' . e($o['origem']) . '</a>' : e($o['origem']) ?></td>
            <td class="direita"><?= $n($o['total']) ?></td><td class="direita"><?= $n($o['aguardando']) ?></td><td class="direita"><?= $n($o['abordados']) ?></td>
            <td class="direita"><strong><?= $n($o['clientes']) ?></strong></td>
            <td><div class="ps-conv"><i><b style="width: <?= min(100, $o['conversao']) ?>%"></b></i><span><?= number_format($o['conversao'], 1, ',', '.') ?>%</span></div></td></tr>
        <?php endforeach; ?>
        <?php if (!$p['origens']): ?><tr><td colspan="6" class="vazio">Nenhum lead com esses filtros.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div></section>
  <section class="b-card span-4"><header class="b-topo"><h2>Funil dos leads</h2></header>
    <ul class="ps-funil">
      <?php foreach ($p['funil'] as $e): ?>
        <li><a href="<?= $linkCarteira(['etapa' => $e['id']]) ?>"><span><?= e($e['nome']) ?></span>
          <i><b style="width: <?= round($e['total'] / $maxFunil * 100) ?>%; background: <?= e($e['cor'] ?: '#b6e45a') ?>"></b></i><strong><?= $n($e['total']) ?></strong></a></li>
      <?php endforeach; ?>
    </ul></section>

  <section class="b-card <?= $p['equipe'] ? 'span-8' : 'span-12' ?>" data-card="proximos">
    <header class="b-topo"><h2>Próximos para abordar</h2><span class="b-chip">prioridade alta primeiro · depois os mais antigos</span></header>
    <?php if (!$p['proximos']): ?><p class="suave">Ninguém aguardando o primeiro contato. 🎉 <a href="<?= url('/prospeccao/empresas?esconder=1') ?>">Encontrar mais empresas →</a></p><?php endif; ?>
    <ul class="pr-alertas ps-proximos">
      <?php foreach ($p['proximos'] as $l): ?>
        <li data-lead="<?= (int) $l['id'] ?>">
          <div class="ps-lead-nome"><button class="nome-cliente" type="button" data-abrir-cliente="<?= (int) $l['id'] ?>"><strong><?= e($l['nome_fantasia'] ?: $l['razao_social']) ?></strong></button><?= \App\Services\NotificacaoService::marca((int) $l['id']) ?>
          <small class="suave ps-proximo-info"><?= e(trim(($l['bairro'] ? $l['bairro'] . ' · ' : '') . $l['cidade'] . '/' . $l['uf'])) ?> · <?= e($l['origem'] ?? 'sem origem') ?><?= Auth::pode('carteira.ver_todas') && $l['responsavel'] ? ' · ' . e($l['responsavel']) : '' ?></small></div>
          <span class="selo <?= $l['prioridade'] === ($prioridades[0] ?? 'Alta') ? 'vermelho' : 'cinza' ?>"><?= e($l['prioridade']) ?></span>
          <?php if ($l['telefone']): ?><a class="botao contorno pequeno" href="tel:+55<?= e($l['telefone']) ?>"><svg class="icone"><use href="<?= $icones ?>#telefone"/></svg><?= e(Normalizar::telefoneFormatado($l['telefone'])) ?></a><?php else: ?><span></span><?php endif; ?>
          <button class="botao lima pequeno" type="button" data-abrir-cliente="<?= (int) $l['id'] ?>">Abordar</button>
        </li>
      <?php endforeach; ?>
    </ul>
    <?php if ($p['paginas'] > 1): ?>
      <nav class="paginacao" aria-label="Páginas">
        <?php if ($p['pagina'] > 1): ?><a class="botao contorno pequeno" href="<?= $linkPagina($p['pagina'] - 1) ?>">‹ Anterior</a><?php endif; ?>
        <span class="pequeno">Página <?= $p['pagina'] ?> de <?= $p['paginas'] ?></span>
        <?php if ($p['pagina'] < $p['paginas']): ?><a class="botao contorno pequeno" href="<?= $linkPagina($p['pagina'] + 1) ?>">Próxima ›</a><?php endif; ?>
      </nav>
    <?php endif; ?>
  </section>
  <?php if ($p['equipe']): ?>
    <section class="b-card span-4" data-card="equipe"><header class="b-topo"><h2>Abordagens da equipe</h2><span class="b-chip">7 dias</span></header>
      <ol class="mk-ranking">
        <?php foreach ($p['equipe'] as $i => $u): ?>
          <li><span class="mk-posicao"><?= $i + 1 ?></span><div><strong><?= e($u['nome']) ?></strong></div>
            <div class="mk-ranking-num"><b><?= $n($u['contatos']) ?> contatos</b><small><?= $n($u['lojas']) ?> leads</small></div></li>
        <?php endforeach; ?>
      </ol>
      <p class="suave pequeno">Contatos registrados na linha do tempo dos leads (ligação, WhatsApp, visita, e-mail).</p>
    </section>
  <?php endif; ?>

  <section class="b-card span-12"><header class="b-topo"><h2>Leads por cidade</h2></header>
    <div class="ps-cidades-chips">
      <?php foreach ($p['cidades'] as $c): ?><a href="<?= url('/prospeccao') ?>?<?= e(http_build_query(['cidade' => $c['chave']] + array_diff_key($filtrosUrl, ['cidade' => 1]))) ?>"><?= e($c['chave']) ?> <b><?= $n($c['total']) ?></b></a><?php endforeach; ?>
    </div></section>
</div>
<?= Visao::renderizar('carteira/_drawer') ?>
