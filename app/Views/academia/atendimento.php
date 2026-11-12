<?php
/** Guia de atendimento. Variáveis: $pagina + cabeçalho */
use App\Core\Visao;
?>
<?= Visao::renderizar('academia/_cabecalho', get_defined_vars() + ['sobretitulo' => 'Apoio à conversa', 'titulo_pagina' => 'Guia de atendimento', 'chamada' => $pagina['chamada']]) ?>

<div class="grade-falas">
  <?php foreach ($pagina['falas'] as [$situacao, $fala, $orientacao]): ?>
    <section class="card">
      <h2><?= e($situacao) ?></h2>
      <?= Visao::renderizar('academia/_mensagem', ['texto' => $fala, 'dica' => $orientacao]) ?>
    </section>
  <?php endforeach; ?>
</div>

<section class="card">
  <h2>Condições comerciais</h2>
  <?php foreach ($pagina['condicoes'] as $texto): ?><p><?= e($texto) ?></p><?php endforeach; ?>
</section>
