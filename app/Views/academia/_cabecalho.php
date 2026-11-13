<?php
/**
 * Cabeçalho da Academia: título, progresso de estudo e abas.
 * Variáveis: $aba, $sobretitulo, $titulo_pagina, $chamada, $percentual, $feitos, $totalItens
 */
$abas = [
    'inicio'      => ['Início', '/academia'],
    'trilha'      => ['Trilha de treinamento', '/academia/trilha'],
    'produtos'    => ['Produtos', '/academia/produtos'],
    'atendimento' => ['Guia de atendimento', '/academia/atendimento'],
    'sistema'     => ['Como usar o One', '/academia/sistema'],
];
?>
<div class="cabecalho-secao">
  <div>
    <div class="sobretitulo"><?= e($sobretitulo) ?></div>
    <h1><?= e($titulo_pagina) ?></h1>
    <?php if (!empty($chamada)): ?><p><?= e($chamada) ?></p><?php endif; ?>
  </div>
  <a class="progresso-estudo" href="<?= url('/academia/trilha') ?>" title="Seu progresso no treinamento">
    <span class="anel" style="--p: <?= (int) $percentual ?>"><b><?= (int) $percentual ?>%</b></span>
    <span><strong><?= (int) $feitos ?> de <?= (int) $totalItens ?></strong> módulos e aulas estudados</span>
  </a>
</div>
<nav class="abas" aria-label="Seções da Academia">
  <?php foreach ($abas as $chave => [$nome, $link]): ?>
    <a href="<?= url($link) ?>" class="<?= $chave === $aba ? 'ativa' : '' ?>"><?= e($nome) ?></a>
  <?php endforeach; ?>
</nav>
