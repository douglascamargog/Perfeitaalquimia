<?php
/** Abas do Depósito. Variável: $atual */
$abas = ['/deposito' => 'Separação', '/deposito/recebimento' => 'Recebimento', '/deposito/lotes' => 'Rastrear lote'];
?>
<nav class="pr-abas nao-imprimir" aria-label="Depósito">
  <?php foreach ($abas as $link => $nome): ?><a href="<?= url($link) ?>" class="<?= $atual === $link ? 'ativo' : '' ?>"><?= $nome ?></a><?php endforeach; ?>
</nav>
