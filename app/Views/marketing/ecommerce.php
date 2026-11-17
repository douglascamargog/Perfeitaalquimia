<?php
/**
 * Painel do e-commerce (Tray: loja própria + marketplaces), em blocos como a Início — V14.0.
 * Variáveis: $painel (EcommerceService::painel)
 */
use App\Core\Visao;
use App\Integrations\Tray\TrayTexto;

$p = $painel;
$f = $p['filtros'];
$a = $p['atual'];
$icones = asset('img/icones.svg');
$n = fn($v) => number_format((float) $v, 0, ',', '.');
$pct = fn($v, $casas = 1) => number_format((float) $v, $casas, ',', '.') . '%';
$var = function (?float $v, bool $inverso = false, bool $pontos = false): string {
    if ($v === null) {
        return '<span class="mk-var neutra">sem comparação</span>';
    }
    $bom = $inverso ? $v <= 0 : $v >= 0;
    return '<span class="mk-var ' . ($bom ? 'sobe' : 'desce') . '">' . ($v >= 0 ? '▲' : '▼') . ' ' . number_format(abs($v), 1, ',', '.') . ($pontos ? ' p.p.' : '%') . '</span>';
};
$qs = fn(array $mudar = []) => http_build_query(array_filter(array_merge(['periodo' => $f['periodo'], 'de' => $f['periodo'] === 'livre' ? $f['de'] : null,
    'ate' => $f['periodo'] === 'livre' ? $f['ate'] : null, 'canal' => $f['canal'], 'uf' => $f['uf'], 'situacao' => $f['situacao']], $mudar), fn($v) => $v !== null && $v !== ''));
$voltar = '?' . $qs();
$acoes = (\App\Core\Auth::pode('config.gerenciar') ? '<form method="post" action="' . url('/marketing/ecommerce/testar') . '">' . csrf_campo() . '<button class="botao contorno" type="submit">Testar conexão</button></form>' : '')
    . '<form method="post" action="' . url('/marketing/ecommerce/atualizar') . '">' . csrf_campo() . '<input type="hidden" name="voltar" value="' . e($voltar) . '"><button class="botao contorno" type="submit" data-eco-atualizar><svg class="icone"><use href="' . $icones . '#nuvem"/></svg>Atualizar agora</button></form>'
    . '<a class="botao contorno" href="' . url('/marketing/ecommerce/pedidos') . '?' . e($qs()) . '" data-eco-lista><svg class="icone"><use href="' . $icones . '#folha"/></svg>Todos os pedidos</a>'
    . '<a class="botao contorno" href="' . url('/marketing/ecommerce/pedidos.csv') . '?' . e($qs()) . '"><svg class="icone"><use href="' . $icones . '#baixar"/></svg>Planilha</a>'
    . '<button class="botao contorno" type="button" onclick="window.print()"><svg class="icone"><use href="' . $icones . '#imprimir"/></svg>Imprimir</button>';
$selo = fn(string $canal, ?string $nome = null) => '<span class="eco-canal canal-' . e($canal) . '">' . e(TrayTexto::nomeCanal($canal, $nome)) . '</span>';
$maxProduto = max(1, (float) ($p['produtos'][0]['faturamento'] ?? 1));
$maxCategoria = max(1, (float) ($p['categorias'][0]['faturamento'] ?? 1));
$maxCidade = max(1, (float) ($p['cidades'][0]['faturamento'] ?? 1));
$ufMax = max(1, (float) ($p['ufs'][0]['faturamento'] ?? 1));
$ufFat = array_column($p['ufs'], null, 'uf');
$totalUf = array_sum(array_column($p['ufs'], 'faturamento')) ?: 1;
// Mapa do Brasil em quadradinhos: UF => [coluna, linha]
$mapa = ['RR' => [3, 1], 'AP' => [5, 1], 'AM' => [2, 2], 'PA' => [4, 2], 'MA' => [5, 2], 'CE' => [6, 2], 'RN' => [7, 2], 'AC' => [1, 3], 'RO' => [2, 3],
    'MT' => [3, 3], 'TO' => [4, 3], 'PI' => [5, 3], 'PB' => [7, 3], 'GO' => [4, 4], 'DF' => [5, 4], 'BA' => [6, 4], 'PE' => [7, 4], 'MS' => [3, 5],
    'MG' => [5, 5], 'ES' => [6, 5], 'AL' => [7, 5], 'SP' => [4, 6], 'RJ' => [5, 6], 'SE' => [7, 6], 'PR' => [4, 7], 'SC' => [4, 8], 'RS' => [4, 9]];
$pagNomes = ['pix' => 'Pix', 'cartao' => 'Cartão', 'boleto' => 'Boleto', 'outro' => 'Pago no marketplace / outro'];
$fu = $p['funil'];
$etapas = [['Pedidos feitos', $fu['todos'], 'cinza'], ['Aguardando pagamento', $fu['aguardando'], 'amarelo'], ['Pagos', $fu['pagos'], 'verde'],
    ['Com nota fiscal', $fu['com_nf'], 'verde'], ['Enviados', $fu['enviados'], 'azul'], ['Entregues', $fu['entregues'], 'azul'], ['Cancelados', $fu['cancelados'], 'vermelho']];
$prazoPct = $p['prazo']['com_prazo'] ? $p['prazo']['no_prazo'] / $p['prazo']['com_prazo'] * 100 : null;
$periodoNome = $f['periodo'] === 'livre' ? data_br($f['de']) . ' a ' . data_br($f['ate']) : \App\Services\EcommerceService::periodos()[$f['periodo']][0];
?>
<?= Visao::renderizar('marketing/_cabecalho', ['aba' => 'ecommerce', 'mes' => date('Y-m'), 'semMes' => true, 'titulo' => 'E-commerce',
    'sobretitulo' => 'LOJA VIRTUAL · TRAY · MARKETPLACES', 'acoes' => $acoes,
    'subtitulo' => 'Vendas da loja própria e dos marketplaces. Atualizado em ' . ($p['ultima_sincronizacao'] ? data_br($p['ultima_sincronizacao'], true) : '—') . '.']) ?>

<?php if ($p['simulado']): ?>
  <p class="aviso mk-aviso-simulado">Dados simulados da loja (demonstração). Para ver a loja de verdade, o admin liga a Tray em ⚙️ Administração › Tray.</p>
<?php endif; ?>
<?php if ($p['importando']): ?>
  <p class="aviso eco-importando" data-eco-importando>⏳ Importando o histórico da loja: <?= $n($p['total_local'] - $p['importando']) ?> de <?= $n($p['total_local']) ?> pedidos completos.
    Os números já aparecem e ficam mais completos a cada rodada (a Tray limita as consultas por dia).</p>
<?php endif; ?>

<!-- Filtros -->
<form class="eco-filtros no-print" method="get" action="<?= url('/marketing/ecommerce') ?>" data-eco-filtros>
  <div class="eco-periodos" role="group" aria-label="Período">
    <?php foreach (\App\Services\EcommerceService::periodos() as $chave => [$nome]): ?>
      <a class="<?= $f['periodo'] === $chave ? 'ativo' : '' ?>" href="<?= url('/marketing/ecommerce') ?>?<?= e($qs(['periodo' => $chave, 'de' => null, 'ate' => null])) ?>"><?= e($nome) ?></a>
    <?php endforeach; ?>
  </div>
  <label class="eco-datas">de <input type="date" name="de" value="<?= e($f['de']) ?>"> até <input type="date" name="ate" value="<?= e($f['ate']) ?>"></label>
  <select name="canal" aria-label="Canal" data-eco-canal>
    <option value="">Todos os canais</option>
    <?php foreach (TrayTexto::CANAIS as $chave => [$nome]): ?><option value="<?= $chave ?>" <?= $f['canal'] === $chave ? 'selected' : '' ?>><?= e($nome) ?></option><?php endforeach; ?>
  </select>
  <select name="uf" aria-label="Estado">
    <option value="">Todo o Brasil</option>
    <?php foreach (array_keys($mapa) as $uf): ?><option <?= $f['uf'] === $uf ? 'selected' : '' ?>><?= $uf ?></option><?php endforeach; ?>
  </select>
  <select name="situacao" aria-label="Situação">
    <option value="">Todas as situações</option>
    <?php foreach (TrayTexto::SITUACOES as $chave => [$nome]): ?><option value="<?= $chave ?>" <?= $f['situacao'] === $chave ? 'selected' : '' ?>><?= e($nome) ?></option><?php endforeach; ?>
  </select>
  <button class="botao pequeno" type="submit">Aplicar</button>
  <?php if ($f['canal'] || $f['uf'] || $f['situacao'] || $f['periodo'] !== 'mes'): ?><a class="b-chip" href="<?= url('/marketing/ecommerce') ?>">Limpar</a><?php endif; ?>
</form>
<p class="eco-periodo-txt">Mostrando <strong><?= e($periodoNome) ?></strong><?= $f['canal'] ? ' · ' . e(TrayTexto::nomeCanal($f['canal'])) : '' ?><?= $f['uf'] ? ' · ' . e($f['uf']) : '' ?>
  · comparado com <?= data_br($p['anterior']['de']) ?> a <?= data_br($p['anterior']['ate']) ?>. Faturamento conta só pedidos pagos, enviados ou entregues.</p>

<?php foreach ($p['alertas'] as [$tipo, $texto, $link]): ?>
  <p class="aviso <?= $tipo === 'erro' ? 'erro' : '' ?> eco-alerta" data-eco-alerta>⚠️ <?= e($texto) ?><?= $link ? ' <a href="' . url($link) . '">Ver pedidos →</a>' : '' ?></p>
<?php endforeach; ?>

<div class="bento">
  <!-- Indicadores -->
  <section class="b-card b-escuro span-4" data-eco-faturamento>
    <header class="b-topo"><span>Faturamento</span><span class="b-icone-redondo lima"><svg class="icone"><use href="<?= $icones ?>#moeda"/></svg></span></header>
    <strong class="b-numero-grande mk-dinheiro"><?= dinheiro((float) $a['faturamento']) ?></strong>
    <p class="b-sub"><?= $var($p['variacao']['faturamento']) ?> vs. período anterior (<?= dinheiro((float) $p['ant']['faturamento']) ?>)</p>
    <?php if ($p['projecao']): ?><p class="b-sub">Projeção do mês: <strong class="mk-lima"><?= dinheiro($p['projecao']) ?></strong></p><?php endif; ?>
  </section>
  <section class="b-card b-kpi span-2" data-eco-kpi="pedidos">
    <header class="b-topo"><span>Pedidos vendidos</span><span class="b-icone-redondo"><svg class="icone"><use href="<?= $icones ?>#carrinho"/></svg></span></header>
    <strong class="b-valor"><?= $n($a['pedidos']) ?></strong><small><?= $var($p['variacao']['pedidos']) ?></small>
  </section>
  <section class="b-card b-kpi span-2">
    <header class="b-topo"><span>Ticket médio</span><span class="b-icone-redondo"><svg class="icone"><use href="<?= $icones ?>#etiqueta"/></svg></span></header>
    <strong class="b-valor"><?= dinheiro((float) $a['ticket']) ?></strong><small><?= $var($p['variacao']['ticket']) ?></small>
  </section>
  <section class="b-card b-kpi span-2">
    <header class="b-topo"><span>Itens por pedido</span><span class="b-icone-redondo"><svg class="icone"><use href="<?= $icones ?>#caixa"/></svg></span></header>
    <strong class="b-valor"><?= number_format((float) $a['itens_por_pedido'], 1, ',', '.') ?></strong><small><?= $n($a['itens']) ?> unidades · <?= $var($p['variacao']['itens_por_pedido']) ?></small>
  </section>
  <section class="b-card b-kpi b-lima span-2">
    <header class="b-topo"><span>Clientes</span><span class="b-icone-redondo escuro"><svg class="icone"><use href="<?= $icones ?>#usuario"/></svg></span></header>
    <strong class="b-valor"><?= $n($a['clientes']) ?></strong><small><?= $n($a['novos']) ?> novos · <?= $n($a['recompra']) ?> voltaram a comprar</small>
  </section>

  <section class="b-card b-kpi span-2">
    <header class="b-topo"><span>Cancelados</span></header>
    <strong class="b-valor <?= $a['cancelados_pct'] >= 8 ? 'eco-ruim' : '' ?>"><?= $pct($a['cancelados_pct']) ?></strong>
    <small><?= $n($a['cancelados']) ?> pedido(s) · <?= dinheiro((float) $a['cancelado_valor']) ?> · <?= $var($p['variacao']['cancelados_pct'], true, true) ?></small>
  </section>
  <section class="b-card b-kpi span-2">
    <header class="b-topo"><span>Aguardando pagamento</span></header>
    <strong class="b-valor"><?= $n($a['aguardando']) ?></strong><small><?= dinheiro((float) $a['aguardando_valor']) ?> ainda não pagos</small>
  </section>
  <section class="b-card b-kpi span-2">
    <header class="b-topo"><span>Frete cobrado</span></header>
    <strong class="b-valor"><?= dinheiro((float) $a['frete']) ?></strong><small><?= $a['pedidos'] ? dinheiro($a['frete'] / $a['pedidos']) . ' por pedido' : '—' ?></small>
  </section>
  <section class="b-card b-kpi span-2">
    <header class="b-topo"><span>Descontos e cupons</span></header>
    <strong class="b-valor"><?= dinheiro((float) $a['desconto']) ?></strong><small><?= $n($a['com_cupom']) ?> pedido(s) com cupom</small>
  </section>
  <section class="b-card b-kpi span-2">
    <header class="b-topo"><span>Margem estimada</span></header>
    <strong class="b-valor"><?= $a['margem_pct'] !== null ? $pct($a['margem_pct']) : '—' ?></strong>
    <small><?= $a['margem_pct'] !== null ? dinheiro((float) $a['margem']) . ' (preço − custo, sem frete)' : 'a loja não tem o custo dos produtos' ?></small>
  </section>
  <section class="b-card b-kpi span-2">
    <header class="b-topo"><span>Envio no prazo</span></header>
    <strong class="b-valor"><?= $prazoPct !== null ? $pct($prazoPct, 0) : '—' ?></strong>
    <small><?= $p['prazo']['dias_para_enviar'] !== null ? 'enviado em ' . number_format((float) $p['prazo']['dias_para_enviar'], 1, ',', '.') . ' dia(s), em média' : 'sem envios no período' ?></small>
  </section>

  <!-- Canais -->
  <section class="b-card span-12" data-eco-canais>
    <header class="b-topo"><h2>Vendas por canal</h2><span class="b-chip">loja própria × marketplaces</span></header>
    <?php if (!$p['canais']): ?><p class="b-vazio">Sem pedidos no período.</p><?php endif; ?>
    <div class="eco-canais">
      <?php foreach ($p['canais'] as $c): ?>
        <a class="eco-canal-card canal-<?= e($c['canal']) ?>" href="<?= url('/marketing/ecommerce') ?>?<?= e($qs(['canal' => $c['canal']])) ?>" data-canal="<?= e($c['canal']) ?>">
          <span class="eco-canal-nome"><?= e($c['nome']) ?></span>
          <strong><?= dinheiro((float) $c['faturamento']) ?></strong>
          <small><?= $n($c['pedidos']) ?> pedido(s) · ticket <?= dinheiro((float) $c['ticket']) ?></small>
          <small><?= $pct($c['participacao'], 0) ?> do total · <?= $var($c['variacao']) ?></small>
          <?php if ($c['cancelados']): ?><small class="eco-ruim"><?= $n($c['cancelados']) ?> cancelado(s)</small><?php endif; ?>
          <i class="eco-barra" style="width: <?= round($c['participacao']) ?>%"></i>
        </a>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="b-card span-8">
    <header class="b-topo"><h2>Faturamento por dia e canal</h2><span class="b-chip"><?= e($periodoNome) ?></span></header>
    <div class="mk-grafico"><canvas id="grafico-diario" aria-label="Faturamento por dia, empilhado por canal"></canvas></div>
  </section>
  <section class="b-card span-4">
    <header class="b-topo"><h2>Últimos 12 meses</h2></header>
    <div class="mk-grafico"><canvas id="grafico-meses" aria-label="Faturamento dos últimos 12 meses"></canvas></div>
  </section>

  <!-- Funil e horários -->
  <section class="b-card span-4" data-eco-funil>
    <header class="b-topo"><h2>Caminho dos pedidos</h2></header>
    <ol class="eco-funil">
      <?php foreach ($etapas as [$nome, $qtd, $cor]): ?>
        <li><span><?= e($nome) ?></span><b><?= $n($qtd) ?></b><i class="cor-<?= $cor ?>" style="width: <?= $fu['todos'] ? round($qtd / $fu['todos'] * 100) : 0 ?>%"></i></li>
      <?php endforeach; ?>
    </ol>
  </section>
  <section class="b-card span-8" data-eco-calor>
    <header class="b-topo"><h2>Quando as pessoas compram</h2><span class="b-chip">dia da semana × hora</span></header>
    <div class="eco-calor" id="eco-calor" aria-label="Mapa de calor dos pedidos por dia da semana e hora"></div>
    <p class="pequeno suave">Use para programar posts, anúncios e promoções nos horários de mais venda.</p>
  </section>

  <!-- Produtos -->
  <section class="b-card span-7" data-eco-produtos>
    <header class="b-topo"><h2>Produtos mais vendidos</h2><span class="b-chip">Top <?= count($p['produtos']) ?></span></header>
    <?php if (!$p['produtos']): ?><p class="b-vazio">Sem vendas no período.</p><?php endif; ?>
    <ol class="mk-ranking eco-produtos">
      <?php foreach ($p['produtos'] as $i => $pr): $foto = $pr['imagem'] ?: ($pr['foto_one'] ? url('/' . $pr['foto_one']) : null); ?>
        <li>
          <span class="mk-posicao"><?= $i + 1 ?></span>
          <?php if ($foto): ?><img class="eco-foto" src="<?= e($foto) ?>" alt="" width="40" height="40" loading="lazy"><?php else: ?><span class="eco-foto vazia"></span><?php endif; ?>
          <div><strong><?= e($pr['nome']) ?></strong>
            <small class="suave"><?= e($pr['categoria'] ?: 'Sem categoria') ?> · mais vende em <?= $selo((string) $pr['canal']) ?> · <?= $n($pr['pedidos']) ?> pedido(s)</small>
            <div class="b-barra"><i style="width: <?= round($pr['faturamento'] / $maxProduto * 100) ?>%"></i></div></div>
          <span class="mk-ranking-num"><b><?= dinheiro((float) $pr['faturamento']) ?></b><small><?= $n($pr['quantidade']) ?> un</small></span>
        </li>
      <?php endforeach; ?>
    </ol>
  </section>
  <section class="b-card span-5">
    <header class="b-topo"><h2>Categorias</h2></header>
    <?php if (!$p['categorias']): ?><p class="b-vazio">Sem vendas no período.</p><?php endif; ?>
    <ul class="eco-barras">
      <?php foreach ($p['categorias'] as $c): ?>
        <li><span><?= e($c['categoria']) ?></span><b><?= dinheiro((float) $c['faturamento']) ?></b><i style="width: <?= round($c['faturamento'] / $maxCategoria * 100) ?>%"></i><small><?= $n($c['quantidade']) ?> un</small></li>
      <?php endforeach; ?>
    </ul>
    <?php if ($p['parados']): ?>
      <h3 class="eco-sub">Pararam de vender</h3>
      <p class="pequeno suave">Venderam no período anterior e nada neste:</p>
      <ul class="eco-parados" data-eco-parados>
        <?php foreach ($p['parados'] as $pr): ?><li><?= e($pr['nome']) ?> <small class="suave">antes <?= dinheiro((float) $pr['faturamento']) ?></small></li><?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>

  <!-- Pagamento e envio -->
  <section class="b-card span-4" data-eco-pagamentos>
    <header class="b-topo"><h2>Formas de pagamento</h2></header>
    <div class="mk-grafico eco-pizza"><canvas id="grafico-pagamentos" aria-label="Faturamento por forma de pagamento"></canvas></div>
    <table class="b-tabela eco-tabela-mini">
      <thead><tr><th>Forma</th><th class="direita">Pedidos</th><th class="direita">Cancel.</th></tr></thead>
      <tbody>
      <?php foreach ($p['pagamentos'] as $pg): $base = $pg['pedidos'] + $pg['cancelados']; ?>
        <tr><td><?= e($pagNomes[$pg['tipo']] ?? $pg['tipo']) ?></td><td class="direita"><?= $n($pg['pedidos']) ?></td>
          <td class="direita <?= $base && $pg['cancelados'] / $base > .08 ? 'eco-ruim' : '' ?>"><?= $base ? $pct($pg['cancelados'] / $base * 100, 0) : '—' ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </section>
  <section class="b-card span-8" data-eco-envios>
    <header class="b-topo"><h2>Envio e transportadoras</h2></header>
    <div class="tabela-rolagem">
      <table class="b-tabela">
        <thead><tr><th>Forma de envio</th><th class="direita">Pedidos</th><th class="direita">Frete médio cobrado</th><th class="direita">Enviados no prazo</th></tr></thead>
        <tbody>
        <?php foreach ($p['envios'] as $en): ?>
          <tr><td><?= e($en['envio']) ?></td><td class="direita"><?= $n($en['pedidos']) ?></td><td class="direita"><?= dinheiro((float) $en['frete_medio']) ?></td>
            <td class="direita"><?= $en['com_prazo'] ? $pct($en['no_prazo'] / $en['com_prazo'] * 100, 0) : '—' ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$p['envios']): ?><tr><td colspan="4" class="suave">Sem envios no período.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>

  <!-- Geografia -->
  <section class="b-card span-6" data-eco-mapa>
    <header class="b-topo"><h2>Vendas por estado</h2><span class="b-chip"><?= count($p['ufs']) ?> estado(s)</span></header>
    <div class="eco-mapa">
      <?php foreach ($mapa as $uf => [$col, $lin]): $fat = (float) ($ufFat[$uf]['faturamento'] ?? 0); ?>
        <a class="eco-uf <?= $fat ? '' : 'sem' ?> <?= $f['uf'] === $uf ? 'ativo' : '' ?>" style="grid-column: <?= $col ?>; grid-row: <?= $lin ?>; --forca: <?= $fat ? max(12, round($fat / $ufMax * 100)) : 0 ?>%"
           href="<?= url('/marketing/ecommerce') ?>?<?= e($qs(['uf' => $f['uf'] === $uf ? null : $uf])) ?>"
           title="<?= $uf ?>: <?= $fat ? dinheiro($fat) . ' · ' . $n($ufFat[$uf]['pedidos']) . ' pedido(s) · ' . $pct($fat / $totalUf * 100) : 'sem vendas' ?>"><?= $uf ?></a>
      <?php endforeach; ?>
    </div>
  </section>
  <section class="b-card span-6">
    <header class="b-topo"><h2>Cidades que mais compram</h2></header>
    <?php if (!$p['cidades']): ?><p class="b-vazio">Sem cidades no período (aparecem depois que o pedido completo é lido).</p><?php endif; ?>
    <ul class="eco-barras">
      <?php foreach ($p['cidades'] as $c): ?>
        <li><span><?= e($c['cidade']) ?>/<?= e($c['uf']) ?></span><b><?= dinheiro((float) $c['faturamento']) ?></b><i style="width: <?= round($c['faturamento'] / $maxCidade * 100) ?>%"></i><small><?= $n($c['pedidos']) ?> pedido(s)</small></li>
      <?php endforeach; ?>
    </ul>
  </section>

  <!-- Cupons e recentes -->
  <section class="b-card span-4" data-eco-cupons>
    <header class="b-topo"><h2>Cupons</h2></header>
    <?php if (!$p['cupons']): ?><p class="b-vazio">Nenhum cupom usado no período.</p><?php else: ?>
      <table class="b-tabela">
        <thead><tr><th>Cupom</th><th class="direita">Pedidos</th><th class="direita">Desconto</th><th class="direita">Vendeu</th></tr></thead>
        <tbody>
        <?php foreach ($p['cupons'] as $c): ?>
          <tr><td><code><?= e($c['cupom']) ?></code></td><td class="direita"><?= $n($c['pedidos']) ?></td><td class="direita"><?= dinheiro((float) $c['desconto']) ?></td><td class="direita"><?= dinheiro((float) $c['faturamento']) ?></td></tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </section>
  <section class="b-card span-8" data-eco-recentes>
    <header class="b-topo"><h2>Pedidos recentes</h2><a class="b-chip" href="<?= url('/marketing/ecommerce/pedidos') ?>?<?= e($qs()) ?>">Ver todos →</a></header>
    <?= Visao::renderizar('marketing/_ecommerce_tabela', ['linhas' => $p['recentes'], 'compacta' => true]) ?>
  </section>
</div>

<script type="application/json" id="dados-ecommerce"><?= json_encode([
    'diario' => array_map(fn($d) => ['dia' => $d['dia'], 'canal' => $d['canal'], 'valor' => (float) $d['faturamento'], 'pedidos' => (int) $d['pedidos']], $p['diario']),
    'de' => $f['de'], 'ate' => min($f['ate'], date('Y-m-d')),
    'canais' => array_map(fn($c) => ['chave' => $c[0] ?? '', 'nome' => $c[1]], array_map(null, array_keys(TrayTexto::CANAIS), array_column(TrayTexto::CANAIS, 0))),
    'meses' => array_map(fn($m) => ['mes' => $m['mes'], 'valor' => (float) $m['faturamento'], 'pedidos' => (int) $m['pedidos']], $p['meses']),
    'mesAtualParcial' => substr($f['ate'], 0, 7) === date('Y-m'),
    'calor' => array_map(fn($c) => [(int) $c['dia_semana'], (int) $c['hora'], (int) $c['pedidos']], $p['calor']),
    'pagamentos' => array_map(fn($pg) => ['nome' => $pagNomes[$pg['tipo']] ?? $pg['tipo'], 'valor' => (float) $pg['faturamento']], $p['pagamentos']),
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?></script>
