<?php
/**
 * Cabeçalho das abas da Carteira (título, abas e botões).
 * Variáveis: $aba ('visao'|'lista'|'funil'|'mapa'), $filtros, $campanha (null ou [titulo, chamada])
 */
use App\Core\Auth;

$icones = asset('img/icones.svg');
$query = http_build_query(array_filter($filtros));
$abas = ['visao' => ['Visão geral', '/carteira'], 'lista' => ['Lista', '/carteira/lista'], 'funil' => ['Funil', '/carteira/funil'], 'mapa' => ['Mapa', '/carteira/mapa']];
$textos = [
    'visao' => ['PAINEL COMERCIAL', 'Visão geral', 'Seus contatos, negociações e resultados em um só lugar.'],
    'lista' => ['PROSPECÇÃO E RELACIONAMENTO', 'Carteira de clientes', 'Consulte os estabelecimentos, atualize a etapa e registre contatos.'],
    'funil' => ['FUNIL DE VENDAS', 'Funil', 'Arraste os cards para mudar a etapa.'],
    'mapa'  => ['COBERTURA GEOGRÁFICA', 'Mapa da carteira', 'Estados, cidades e lojas. Aproxime para ver cada ponto.'],
][$aba];
?>
<div class="cabecalho-secao">
  <div>
    <div class="sobretitulo"><?= e($textos[0]) ?></div>
    <h1><?= e($textos[1]) ?></h1>
    <p><?= e($textos[2]) ?></p>
  </div>
  <div class="acoes">
    <a class="botao" href="<?= url('/carteira/clientes/novo') ?>"><svg class="icone"><use href="<?= $icones ?>#mais"/></svg>Novo estabelecimento</a>
    <?php if (Auth::pode('carteira.importar')): ?>
      <a class="botao contorno" href="<?= url('/carteira/importar') ?>">Importar</a>
      <a class="botao contorno" href="<?= url('/carteira/exportar.csv') . ($query ? '?' . e($query) : '') ?>"><svg class="icone"><use href="<?= $icones ?>#baixar"/></svg>Exportar CSV</a>
    <?php endif; ?>
  </div>
</div>

<?php if (!empty($campanha)):
    // "Black Friday 2026" → ano à parte, última palavra em destaque (como na Carteira V4)
    preg_match('/^(.*?)\s*(\d{4})?$/u', trim($campanha['titulo']), $partes);
    $palavras = explode(' ', mb_strtoupper($partes[1]));
    $ultima = count($palavras) > 1 ? array_pop($palavras) : '';
    $semCampanha = array_diff_key(array_filter($filtros), ['origem' => 1]); ?>
  <section class="faixa-campanha" aria-label="Campanha em destaque">
    <div>
      <span class="faixa-campanha-sobre">CAMPANHA<?= !empty($partes[2]) ? ' ' . e($partes[2]) : '' ?></span>
      <strong><?= e(implode(' ', $palavras)) ?> <em><?= e($ultima) ?></em></strong>
      <?php if ($campanha['chamada'] !== ''): ?><small><?= e(mb_strtoupper($campanha['chamada'])) ?></small><?php endif; ?>
    </div>
    <a class="botao contorno pequeno" href="<?= url($abas[$aba][1]) . ($semCampanha ? '?' . e(http_build_query($semCampanha)) : '') ?>">Ver toda a carteira</a>
  </section>
<?php endif; ?>

<nav class="abas" aria-label="Visões da carteira">
  <?php foreach ($abas as $chave => [$nome, $link]): ?>
    <a href="<?= url($link) . ($query ? '?' . e($query) : '') ?>" class="<?= $chave === $aba ? 'ativa' : '' ?>"><?= e($nome) ?></a>
  <?php endforeach; ?>
</nav>
