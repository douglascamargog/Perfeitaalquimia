<?php
/**
 * Cabeçalho comum do Marketing: título, navegação de mês e abas.
 * Variáveis: $aba ('mural'|'feed'|'planejamento'|'ecommerce'), $mes (AAAA-MM), $titulo, $subtitulo, $acoes (HTML opcional)
 */
use App\Core\Auth;

$meses = ['janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
$nomeMes = ucfirst($meses[(int) substr($mes, 5, 2) - 1]) . ' ' . substr($mes, 0, 4);
$anterior = date('Y-m', strtotime("$mes-01 -1 month"));
$proximo = date('Y-m', strtotime("$mes-01 +1 month"));
$links = array_filter([
    'mural' => Auth::pode('marketing.ver') ? ['Mural', '/marketing'] : null,
    'feed' => Auth::pode('marketing.ver') ? ['Prévia do feed', '/marketing/feed'] : null,
    'planejamento' => Auth::pode('marketing.ver') ? ['Planejamento', '/marketing/planejamento'] : null,
    'ecommerce' => Auth::pode('ecommerce.ver') ? ['E-commerce', '/marketing/ecommerce'] : null,
]);
$caminho = $links[$aba][1] ?? '/marketing';
?>
<div class="cabecalho-secao">
  <div>
    <div class="sobretitulo"><?= e($sobretitulo ?? 'MARKETING · INSTAGRAM') ?></div>
    <h1><?= e($titulo) ?></h1>
    <p><?= e($subtitulo ?? '') ?></p>
  </div>
  <div class="acoes"><?= $acoes ?? '' ?></div>
</div>
<div class="mk-barra">
  <nav class="abas" aria-label="Seções do Marketing">
    <?php foreach ($links as $chave => [$nome, $link]): ?>
      <a href="<?= url($link) ?>?mes=<?= e($mes) ?>" class="<?= $chave === $aba ? 'ativa' : '' ?>"><?= e($nome) ?></a>
    <?php endforeach; ?>
  </nav>
  <div class="mk-mes">
    <a class="botao-icone" href="<?= url($caminho) ?>?mes=<?= $anterior ?>" aria-label="Mês anterior">‹</a>
    <strong><?= e($nomeMes) ?></strong>
    <a class="botao-icone" href="<?= url($caminho) ?>?mes=<?= $proximo ?>" aria-label="Próximo mês">›</a>
    <?php if ($mes !== date('Y-m')): ?><a class="b-chip" href="<?= url($caminho) ?>">Hoje</a><?php endif; ?>
  </div>
</div>
