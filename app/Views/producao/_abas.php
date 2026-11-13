<?php
/** Abas da Produção (aparecem no topo de todas as telas do módulo). Variável: $atual */
$abas = ['/producao' => 'Painel', '/producao/materias' => 'Matérias-primas', '/producao/fichas' => 'Fichas técnicas',
    '/producao/ordens' => 'Ordens de produção', '/producao/acabados' => 'Estoque acabado'];
?>
<nav class="pr-abas nao-imprimir" aria-label="Produção">
  <?php foreach ($abas as $link => $nome): ?>
    <a href="<?= url($link) ?>" class="<?= $atual === $link ? 'ativo' : '' ?>"><?= $nome ?></a>
  <?php endforeach; ?>
</nav>
