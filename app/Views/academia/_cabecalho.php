<?php
/**
 * Cabeçalho da Biblioteca de produtos (parte do Treinamento).
 * Variáveis: $sobretitulo, $titulo_pagina, $chamada
 */
?>
<div class="cabecalho-secao">
  <div>
    <div class="sobretitulo"><?= e($sobretitulo) ?></div>
    <h1><?= e($titulo_pagina) ?></h1>
    <?php if (!empty($chamada)): ?><p><?= e($chamada) ?></p><?php endif; ?>
  </div>
  <div class="acoes"><a class="botao contorno" href="<?= url('/treinamento') ?>">← Meu treinamento</a></div>
</div>
