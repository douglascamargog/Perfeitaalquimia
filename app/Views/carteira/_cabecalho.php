<?php
/**
 * Cabeçalho das abas da Carteira (título, abas e botões).
 * Variáveis: $aba ('visao'|'lista'|'funil'|'mapa'), $filtros
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

<nav class="abas" aria-label="Visões da carteira">
  <?php foreach ($abas as $chave => [$nome, $link]): ?>
    <a href="<?= url($link) . ($query ? '?' . e($query) : '') ?>" class="<?= $chave === $aba ? 'ativa' : '' ?>"><?= e($nome) ?></a>
  <?php endforeach; ?>
</nav>
