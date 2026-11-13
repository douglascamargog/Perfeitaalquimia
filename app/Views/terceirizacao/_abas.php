<?php
/** Cabeçalho e abas da Terceirização. Variáveis: $atual, $titulo2, $texto, $alertas (rótulos em alerta, opcional) */
$abas = ['/terceirizacao' => 'Clientes', '/terceirizacao/pedidos' => 'Pedidos', '/terceirizacao/produtos' => 'Produtos por cliente', '/terceirizacao/rotulos' => 'Rótulos'];
?>
<div class="cabecalho-secao">
  <div><div class="sobretitulo">COMERCIAL · TERCEIRIZAÇÃO</div><h1><?= e($titulo2) ?></h1><p><?= e($texto) ?></p></div>
</div>
<nav class="abas" aria-label="Terceirização">
  <?php foreach ($abas as $link => $nome): ?>
    <a href="<?= url($link) ?>" class="<?= $atual === $link ? 'ativa' : '' ?>"><?= $nome ?><?= $link === '/terceirizacao/rotulos' && !empty($alertas) ? ' <b class="contador">' . (int) $alertas . '</b>' : '' ?></a>
  <?php endforeach; ?>
</nav>
