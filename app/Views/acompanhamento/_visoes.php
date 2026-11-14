<?php /** Abas Vendas / Cobrança. Variáveis: $visoes, $atual, $periodo, $de, $ate */ ?>
<?php if (count($visoes) > 1): ?>
<nav class="abas ac-visoes" aria-label="Visão do acompanhamento">
  <?php foreach ($visoes as $k => $nome): ?>
    <a class="<?= $k === $atual ? 'ativa' : '' ?>" href="<?= url('/acompanhamento') ?>?visao=<?= e($k) ?>&periodo=<?= e($periodo) ?>&de=<?= e($de) ?>&ate=<?= e($ate) ?>"><?= e($nome) ?></a>
  <?php endforeach; ?>
</nav>
<?php endif; ?>
