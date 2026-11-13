<?php
/**
 * Visão geral da carteira (layout da Carteira V4).
 * Variáveis: $filtros, $opcoes, $resumo, $segmentos, $cidades, $semana, $pendentes, $agenda
 */
use App\Core\Visao;

$icones = asset('img/icones.svg');
$n = fn($v) => number_format((int) $v, 0, ',', '.');
$pct = fn($parte, $todo) => $todo ? number_format($parte / $todo * 100, 1, ',', '.') : '0,0';
$total = max(1, $resumo['total']);
$coresSegmento = ['Produtos naturais' => '#baff4d', 'Suplementos' => '#429b77', 'Farmácia' => '#edbd78', 'Outros' => '#9aa79c'];
$maxDia = max(1, ...array_map(fn($d) => max($d['contatos'], $d['pedidos']), $semana));
$diasSemana = ['dom', 'seg', 'ter', 'qua', 'qui', 'sex', 'sáb'];
$maxCidade = max(1, (int) ($cidades[0]['total'] ?? 1));

// Gráfico radial: um arco por segmento (até 4), proporcional ao total
$arcos = '';
foreach (array_slice($segmentos, 0, 4) as $i => $s) {
    $raio = 93 - $i * 21;
    $comprimento = 2 * M_PI * $raio;
    $arco = $comprimento * 0.76;
    $frente = $arco * ($s['total'] / $total);
    $cor = $coresSegmento[$s['chave']] ?? '#9aa79c';
    $arcos .= sprintf('<circle cx="154" cy="122" r="%d" fill="none" stroke="#edf2e9" stroke-width="13" stroke-linecap="round" stroke-dasharray="%.1f %.1f" transform="rotate(133 154 122)"/>', $raio, $arco, $comprimento);
    $arcos .= sprintf('<circle cx="154" cy="122" r="%d" fill="none" stroke="%s" stroke-width="13" stroke-linecap="round" stroke-dasharray="%.1f %.1f" transform="rotate(133 154 122)"/>', $raio, $cor, $frente, $comprimento);
}
$query = http_build_query(array_filter($filtros));
$linkLista = fn(array $extra = []) => url('/carteira/lista') . '?' . e(http_build_query(array_filter($filtros) + $extra));
?>
<?= Visao::renderizar('carteira/_cabecalho', ['aba' => 'visao', 'filtros' => $filtros]) ?>

<div class="painel-v4">
  <div class="v4-topo">
    <div class="v4-pilha">
      <div class="v4-metricas">
        <a class="v4-metrica destaque" href="<?= $linkLista() ?>">
          <div class="v4-metrica-topo"><span class="v4-icone"><svg class="icone"><use href="<?= $icones ?>#loja"/></svg></span><span class="v4-mini">CARTEIRA</span></div>
          <span class="v4-rotulo">Estabelecimentos</span><strong class="v4-valor"><?= $n($resumo['total']) ?></strong>
          <small><?= $n($resumo['cidades']) ?> cidades · <?= $n($resumo['alta']) ?> prioridade alta</small>
        </a>
        <a class="v4-metrica" href="<?= $linkLista(['ordem' => 'contato']) ?>">
          <div class="v4-metrica-topo"><span class="v4-icone"><svg class="icone"><use href="<?= $icones ?>#telefone"/></svg></span><span class="v4-mini calmo"><?= $pct($resumo['contatados'], $resumo['total']) ?>%</span></div>
          <span class="v4-rotulo">Contatos realizados</span><strong class="v4-valor"><?= $n($resumo['contatados']) ?></strong>
          <small><?= $n($resumo['nao_abordados']) ?> aguardando abordagem</small>
        </a>
        <a class="v4-metrica" href="<?= url('/carteira/funil') . ($query ? '?' . e($query) : '') ?>">
          <div class="v4-metrica-topo"><span class="v4-icone"><svg class="icone"><use href="<?= $icones ?>#conversa"/></svg></span><span class="v4-mini neutro">EM ANDAMENTO</span></div>
          <span class="v4-rotulo">Em negociação</span><strong class="v4-valor"><?= $n($resumo['negociacao']) ?></strong>
          <small>Negociação, proposta e retorno</small>
        </a>
        <a class="v4-metrica" href="<?= $linkLista(['tipo' => 'cliente']) ?>">
          <div class="v4-metrica-topo"><span class="v4-icone"><svg class="icone"><use href="<?= $icones ?>#check"/></svg></span><span class="v4-mini calmo"><?= $pct($resumo['clientes'], $resumo['total']) ?>% CONVERSÃO</span></div>
          <span class="v4-rotulo">Clientes</span><strong class="v4-valor"><?= $n($resumo['clientes']) ?></strong>
          <small>Com pelo menos uma compra</small>
        </a>
      </div>
      <div class="v4-faixa">
        <div><span>Retornos pendentes</span><strong><?= $n($pendentes) ?></strong></div>
        <div><span>Contatos nos últimos 7 dias</span><strong><?= $n(array_sum(array_column($semana, 'contatos'))) ?></strong></div>
        <a href="<?= url('/agenda') ?>"><span>Agenda</span><strong>Abrir ↗</strong></a>
      </div>
    </div>

    <section class="card v4-painel">
      <div class="v4-painel-topo"><div><h3>Perfil da carteira</h3><p>Distribuição por segmento</p></div><span class="v4-chip">AO VIVO</span></div>
      <svg class="v4-radial" viewBox="0 0 308 247" role="img" aria-label="Distribuição por segmento"><?= $arcos ?>
        <text x="154" y="224" text-anchor="middle" class="v4-radial-num"><?= $n($resumo['total']) ?></text>
        <text x="154" y="242" text-anchor="middle" class="v4-radial-rot">estabelecimentos</text>
      </svg>
      <div class="v4-legenda">
        <?php foreach ($segmentos as $s): ?>
          <div><i style="background: <?= e($coresSegmento[$s['chave']] ?? '#9aa79c') ?>"></i><?= e($s['chave']) ?><b><?= $n($s['total']) ?></b></div>
        <?php endforeach; ?>
      </div>
    </section>
  </div>

  <div class="v4-topo">
    <section class="card v4-painel">
      <div class="v4-painel-topo"><div><h3>Atividade comercial</h3><p>Contatos e pedidos dos últimos 7 dias</p></div>
        <div class="v4-legenda-linha"><span><i class="lima"></i> Contatos</span><span><i class="escuro"></i> Pedidos</span></div></div>
      <div class="v4-semana">
        <?php foreach ($semana as $d): ?>
          <div class="v4-dia">
            <div class="v4-barras">
              <span class="contato" style="height: <?= $d['contatos'] ? max(7, $d['contatos'] / $maxDia * 100) : 2 ?>%" title="<?= (int) $d['contatos'] ?> contatos"></span>
              <span class="pedido" style="height: <?= $d['pedidos'] ? max(7, $d['pedidos'] / $maxDia * 100) : 2 ?>%" title="<?= (int) $d['pedidos'] ?> pedidos"></span>
            </div>
            <strong><?= (int) $d['contatos'] ?></strong>
            <small><?= $diasSemana[(int) date('w', strtotime($d['dia']))] ?> <?= date('d', strtotime($d['dia'])) ?></small>
          </div>
        <?php endforeach; ?>
      </div>
    </section>

    <section class="card v4-painel">
      <div class="v4-painel-topo"><div><h3>Evolução da carteira</h3><p>Da prospecção à primeira compra</p></div></div>
      <?php
      $bolhas = [['A abordar', $resumo['nao_abordados'], 'lima'], ['Contato feito', max(0, $resumo['contatados'] - $resumo['clientes'] - $resumo['negociacao']), 'verde'],
                 ['Negociação', $resumo['negociacao'], 'ouro'], ['Clientes', $resumo['clientes'], 'escura']];
      $maiorBolha = max(1, ...array_column($bolhas, 1));
      ?>
      <div class="v4-bolhas">
        <?php foreach ($bolhas as [$nome, $valor, $classe]): ?>
          <div class="v4-bolha <?= $classe ?>" style="--d: <?= (int) (70 + 58 * sqrt($valor / $maiorBolha)) ?>px"><strong><?= $n($valor) ?></strong><span><?= e($nome) ?></span></div>
        <?php endforeach; ?>
      </div>
    </section>
  </div>

  <section class="painel-mapa">
    <div class="card painel-mapa-principal">
      <div class="v4-painel-topo">
        <div><div class="sobretitulo">COBERTURA GEOGRÁFICA</div><h3>Mapa da carteira</h3><p>Estados e cidades com contagem; aproxime para ver cada estabelecimento.</p></div>
        <a class="botao contorno pequeno" href="<?= url('/carteira/mapa') . ($query ? '?' . e($query) : '') ?>">Tela cheia ↗</a>
      </div>
      <div class="mapa-moldura"><div class="mapa" data-mapa data-filtros="<?= e($query) ?>" aria-label="Mapa da carteira"></div><div class="mapa-carregando" data-mapa-carregando>Carregando mapa…</div></div>
      <div class="mapa-rodape"><span data-mapa-contagem>—</span><span>Pontos sem endereço exato ficam em posição aproximada dentro da cidade.</span></div>
    </div>
    <div class="card painel-mapa-lado">
      <h4>Principais cidades</h4>
      <p>Concentração de estabelecimentos</p>
      <div class="lista-cidades">
        <?php foreach ($cidades as $cid): ?>
          <div class="linha-cidade"><span><?= e($cid['chave']) ?></span><div><i style="width: <?= round($cid['total'] / $maxCidade * 100) ?>%"></i></div><b><?= $n($cid['total']) ?></b></div>
        <?php endforeach; ?>
      </div>
      <a class="botao largo" href="<?= $linkLista() ?>">Explorar a lista ↗</a>
    </div>
  </section>

  <div class="v4-titulo-acoes"><div><span>SUAS PRIORIDADES</span><h3>O que fazer agora?</h3></div></div>
  <div class="v4-acoes">
    <a class="v4-acao" href="<?= $linkLista(['etapa' => (string) (\App\Models\Listas::idPorNome($opcoes['etapas'], 'Não abordado') ?? '')]) ?>"><span class="v4-acao-icone lima"><svg class="icone"><use href="<?= $icones ?>#loja"/></svg></span><div><strong>Prospectar lojas</strong><small><?= $n($resumo['nao_abordados']) ?> aguardando contato</small></div><b>↗</b></a>
    <a class="v4-acao" href="<?= url('/agenda') ?>"><span class="v4-acao-icone pessego"><svg class="icone"><use href="<?= $icones ?>#calendario"/></svg></span><div><strong>Fazer retornos</strong><small><?= $n(count($agenda['atrasadas'])) ?> atrasados · <?= $n(count($agenda['hoje'])) ?> para hoje</small></div><b>↗</b></a>
    <a class="v4-acao" href="<?= url('/reativacao') ?>"><span class="v4-acao-icone verde"><svg class="icone"><use href="<?= $icones ?>#relogio"/></svg></span><div><strong>Reativar clientes</strong><small>Clientes sem compra recente</small></div><b>↗</b></a>
  </div>
  <p class="v4-aviso">Base de prospecção: confirme atividade, linha de produtos e autonomia de compra antes de abordar.</p>
</div>

<?= Visao::renderizar('carteira/_drawer') ?>
