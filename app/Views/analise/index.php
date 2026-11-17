<?php
/**
 * Gestão › Análise de clientes: todos os clientes da empresa com filtros, agrupamento e lista.
 * Variáveis: $f (filtros), $opcoes, $resumo, $grupos, $lista
 */
use App\Services\AnaliseClientesService as A;

$icones = asset('img/icones.svg');
$n = fn($v) => number_format((float) $v, 0, ',', '.');
$sel = fn(string $campo, $valor) => (string) ($f[$campo] ?? '') === (string) $valor ? 'selected' : '';
[$rotuloPeriodo, , , $meses] = A::periodos()[$f['periodo']];
$filtrosAtivos = array_filter(array_diff_key($f, ['periodo' => 1, 'grupo' => 1, 'ordem' => 1]), fn($v) => $v !== null && $v !== '');
$base = array_filter($f, fn($v) => $v !== null && $v !== '');
$link = fn(array $mudar) => url('/analise') . '?' . e(http_build_query(array_filter($mudar + $base, fn($v) => $v !== null && $v !== '')));
$valorCampo = fn($v) => $v === null ? '' : number_format((float) $v, 2, ',', '.');
?>
<div class="cabecalho-secao">
  <div>
    <div class="sobretitulo">GESTÃO · TODOS OS CLIENTES</div>
    <h1>Análise de clientes</h1>
    <p><?= e($rotuloPeriodo) ?> · venda = pedido faturado · média mensal = o que comprou no período ÷ <?= $meses ?> <?= $meses === 1 ? 'mês' : 'meses' ?></p>
  </div>
  <div class="acoes nao-imprimir">
    <button class="botao contorno" type="button" onclick="window.print()"><svg class="icone"><use href="<?= $icones ?>#imprimir"/></svg>Imprimir</button>
    <a class="botao contorno" href="<?= url('/analise/exportar.csv') ?>?<?= e(http_build_query($base)) ?>" data-exportar><svg class="icone"><use href="<?= $icones ?>#baixar"/></svg>CSV</a>
  </div>
</div>
<?= \App\Core\Visao::renderizar('layout/_abas_grupo', ['grupo' => 'resultados', 'atual' => '/analise']) ?>

<form class="filtros card nao-imprimir" method="get" action="<?= url('/analise') ?>" data-filtros-analise>
  <div class="filtros-busca">
    <svg class="icone"><use href="<?= $icones ?>#lupa"/></svg>
    <input type="search" name="q" value="<?= e($f['q']) ?>" placeholder="Buscar loja por nome ou CNPJ…" aria-label="Buscar">
  </div>
  <div class="filtros-grade">
    <select name="periodo" aria-label="Período"><?php foreach (A::periodos() as $k => $p): ?><option value="<?= $k ?>" <?= $sel('periodo', $k) ?>><?= e($p[0]) ?></option><?php endforeach; ?></select>
    <select name="uf" aria-label="UF" data-limpa-cidade><option value="">Todas as UFs</option><?php foreach ($opcoes['ufs'] as $uf): ?><option <?= $sel('uf', $uf) ?>><?= e($uf) ?></option><?php endforeach; ?></select>
    <select name="cidade" aria-label="Cidade"><option value="">Todas as cidades</option><?php foreach ($opcoes['cidades'] as $c): ?><option value="<?= e($c['cidade']) ?>" <?= $sel('cidade', $c['cidade']) ?>><?= e($c['cidade']) ?><?= $f['uf'] ? '' : ' / ' . e($c['uf']) ?></option><?php endforeach; ?></select>
    <select name="regiao" aria-label="Região"><option value="">Todas as regiões</option><?php foreach ($opcoes['regioes'] as $o): ?><option value="<?= (int) $o['id'] ?>" <?= $sel('regiao', $o['id']) ?>><?= e($o['nome']) ?></option><?php endforeach; ?></select>
    <select name="segmento" aria-label="Segmento"><option value="">Todos os segmentos</option><?php foreach ($opcoes['segmentos'] as $o): ?><option value="<?= (int) $o['id'] ?>" <?= $sel('segmento', $o['id']) ?>><?= e($o['nome']) ?></option><?php endforeach; ?></select>
    <select name="vendedor" aria-label="Vendedor(a)"><option value="">Todos os vendedores</option><?php foreach ($opcoes['vendedores'] as $o): ?><option value="<?= (int) $o['id'] ?>" <?= $sel('vendedor', $o['id']) ?>><?= e($o['nome']) ?></option><?php endforeach; ?></select>
    <select name="carteira" aria-label="Carteira"><option value="">Todas as carteiras</option><?php foreach ($opcoes['carteiras'] as $o): ?><option value="<?= (int) $o['id'] ?>" <?= $sel('carteira', $o['id']) ?>><?= e($o['nome']) ?></option><?php endforeach; ?></select>
    <select name="etapa" aria-label="Etapa do funil"><option value="">Todas as etapas</option><?php foreach ($opcoes['etapas'] as $o): ?><option value="<?= (int) $o['id'] ?>" <?= $sel('etapa', $o['id']) ?>><?= e($o['nome']) ?></option><?php endforeach; ?></select>
    <select name="tipo" aria-label="Cliente ou lead"><option value="">Clientes e leads</option><option value="cliente" <?= $sel('tipo', 'cliente') ?>>Só clientes</option><option value="lead" <?= $sel('tipo', 'lead') ?>>Só leads</option></select>
    <select name="compra" aria-label="Situação de compra">
      <option value="">Qualquer situação de compra</option>
      <?php foreach (['comprou' => 'Comprou no período', 'nao_comprou' => 'Não comprou no período', 'sem30' => 'Sem compra há 30+ dias', 'sem60' => 'Sem compra há 60+ dias',
                      'sem90' => 'Sem compra há 90+ dias', 'nunca' => 'Nunca comprou'] as $k => $r): ?><option value="<?= $k ?>" <?= $sel('compra', $k) ?>><?= $r ?></option><?php endforeach; ?>
    </select>
    <input type="text" inputmode="decimal" name="media_min" value="<?= e($valorCampo($f['media_min'])) ?>" placeholder="Média mensal de R$" aria-label="Média mensal mínima">
    <input type="text" inputmode="decimal" name="media_max" value="<?= e($valorCampo($f['media_max'])) ?>" placeholder="até R$" aria-label="Média mensal máxima">
  </div>
  <div class="an-filtro-rodape">
    <button class="botao pequeno" type="submit">Filtrar</button>
    <?php if ($filtrosAtivos || $f['periodo'] !== '12m'): ?><a class="pequeno" href="<?= url('/analise') ?>">Limpar filtros</a><?php endif; ?>
    <input type="hidden" name="grupo" value="<?= e($f['grupo']) ?>"><input type="hidden" name="ordem" value="<?= e($f['ordem']) ?>">
  </div>
</form>

<div class="an-kpis" data-analise-resumo>
  <div class="card an-kpi"><span>Lojas no filtro</span><strong data-kpi="clientes"><?= $n($resumo['clientes']) ?></strong><small><?= $n($resumo['compraram']) ?> compraram no período</small></div>
  <div class="card an-kpi"><span>Faturamento</span><strong data-kpi="faturamento"><?= dinheiro($resumo['faturamento']) ?></strong><small><?= dinheiro($resumo['media_empresa']) ?> por mês, somando todas</small></div>
  <div class="card an-kpi an-destaque"><span>Média mensal por cliente</span><strong data-kpi="media"><?= $resumo['compraram'] ? dinheiro($resumo['media_cliente']) : '—' ?></strong><small>de quem comprou · ÷ <?= $meses ?> <?= $meses === 1 ? 'mês' : 'meses' ?></small></div>
  <div class="card an-kpi"><span>Ticket médio</span><strong data-kpi="ticket"><?= $resumo['pedidos'] ? dinheiro($resumo['ticket']) : '—' ?></strong><small>por pedido · <?= $n($resumo['pedidos']) ?> pedido(s)</small></div>
  <div class="card an-kpi"><span>Pedidos por cliente</span><strong><?= $resumo['compraram'] ? number_format($resumo['pedidos_cliente'], 1, ',', '.') : '—' ?></strong><small>no período, de quem comprou</small></div>
</div>

<section class="card an-bloco" data-analise-grupos>
  <header class="an-topo">
    <h2>Por <?= e(mb_strtolower(A::GRUPOS[$f['grupo']][0])) ?></h2>
    <nav class="an-chips nao-imprimir" aria-label="Agrupar por">
      <?php foreach (A::GRUPOS as $k => [$rotulo]): ?><a href="<?= $link(['grupo' => $k]) ?>" class="<?= $f['grupo'] === $k ? 'ativo' : '' ?>"><?= e($rotulo) ?></a><?php endforeach; ?>
    </nav>
  </header>
  <div class="tabela-rolagem">
    <table class="b-tabela an-tabela">
      <thead><tr><th><?= e(A::GRUPOS[$f['grupo']][0]) ?></th><th class="direita">Lojas</th><th class="direita">Compraram</th><th class="direita">Faturamento</th>
        <th class="direita">Pedidos</th><th class="direita">Ticket médio</th><th class="direita">Média mensal por cliente</th></tr></thead>
      <tbody>
      <?php foreach ($grupos as $g): $filtro = array_filter(A::filtroDoGrupo($f['grupo'], $g), fn($v) => $v !== null && $v !== ''); ?>
        <tr><td><?php if ($filtro): ?><a href="<?= $link($filtro + ['grupo' => $f['grupo'] === 'uf' ? 'cidade' : $f['grupo']]) ?>"><?= e($g['grupo']) ?></a><?php else: ?><?= e($g['grupo']) ?><?php endif; ?></td>
          <td class="direita"><?= $n($g['clientes']) ?></td><td class="direita"><?= $n($g['compraram']) ?></td>
          <td class="direita"><strong><?= dinheiro($g['faturamento']) ?></strong></td><td class="direita"><?= $n($g['pedidos']) ?></td>
          <td class="direita"><?= $g['pedidos'] ? dinheiro($g['ticket']) : '—' ?></td><td class="direita"><?= $g['compraram'] ? dinheiro($g['media_cliente']) : '—' ?></td></tr>
      <?php endforeach; ?>
      <?php if (!$grupos): ?><tr><td colspan="7" class="suave">Nenhuma loja com esses filtros.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</section>

<section class="card an-bloco" data-analise-lista>
  <header class="an-topo">
    <h2>Lojas <small class="suave"><?= $n($lista['total']) ?></small></h2>
    <nav class="an-chips nao-imprimir" aria-label="Ordenar por">
      <?php foreach (A::ORDENS as $k => [$rotulo]): ?><a href="<?= $link(['ordem' => $k]) ?>" class="<?= $f['ordem'] === $k ? 'ativo' : '' ?>"><?= e($rotulo) ?></a><?php endforeach; ?>
    </nav>
  </header>
  <div class="tabela-rolagem">
    <table class="b-tabela an-tabela">
      <thead><tr><th>Loja</th><th>Cidade</th><th>Vendedor(a)</th><th class="direita">Comprou</th><th class="direita">Pedidos</th>
        <th class="direita">Ticket médio</th><th class="direita">Média mensal</th><th>Última compra</th></tr></thead>
      <tbody>
      <?php foreach ($lista['itens'] as $l): ?>
        <tr data-loja="<?= (int) $l['id'] ?>"><td><a href="<?= url('/carteira/clientes/' . (int) $l['id']) ?>"><strong><?= e($l['nome']) ?></strong></a><?= $l['tipo'] === 'lead' ? ' <span class="selo">lead</span>' : '' ?>
            <br><small class="suave"><?= e($l['segmento'] ?? 'Sem segmento') ?><?= $l['carteira'] ? ' · ' . e($l['carteira']) : '' ?></small></td>
          <td><?= e($l['cidade']) ?>/<?= e($l['uf']) ?></td><td><?= e($l['vendedor'] ?? '—') ?></td>
          <td class="direita"><?= (float) $l['total'] > 0 ? dinheiro($l['total']) : '—' ?></td><td class="direita"><?= $n($l['pedidos']) ?></td>
          <td class="direita"><?= (float) $l['ticket'] > 0 ? dinheiro($l['ticket']) : '—' ?></td>
          <td class="direita"><strong data-media><?= (float) $l['media'] > 0 ? dinheiro($l['media']) : '—' ?></strong></td>
          <td><?= $l['ultima_compra_em'] ? data_br($l['ultima_compra_em']) : '<span class="suave">nunca</span>' ?></td></tr>
      <?php endforeach; ?>
      <?php if (!$lista['itens']): ?><tr><td colspan="8" class="suave">Nenhuma loja com esses filtros.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
  <?php if ($lista['paginas'] > 1): ?>
    <nav class="paginacao nao-imprimir" aria-label="Páginas" style="margin-top:12px">
      <?php if ($lista['pagina'] > 1): ?><a class="botao contorno pequeno" href="<?= $link(['pagina' => $lista['pagina'] - 1]) ?>">‹ Anterior</a><?php endif; ?>
      <span class="pequeno">Página <?= $lista['pagina'] ?> de <?= $lista['paginas'] ?></span>
      <?php if ($lista['pagina'] < $lista['paginas']): ?><a class="botao contorno pequeno" href="<?= $link(['pagina' => $lista['pagina'] + 1]) ?>">Próxima ›</a><?php endif; ?>
    </nav>
  <?php endif; ?>
</section>
<script>
  // Filtra ao trocar um campo (a UF nova limpa a cidade)
  document.querySelectorAll('[data-filtros-analise] select').forEach((s) => s.addEventListener('change', () => {
    if (s.hasAttribute('data-limpa-cidade')) { const c = s.form.querySelector('[name=cidade]'); if (c) c.value = ''; }
    s.form.submit();
  }));
</script>
