<?php
/** Trilha de treinamento. Variáveis: $modulos, $progresso + cabeçalho */
use App\Core\Visao;
?>
<?= Visao::renderizar('academia/_cabecalho', get_defined_vars() + ['sobretitulo' => 'Integração de novas pessoas', 'titulo_pagina' => 'Trilha de treinamento', 'chamada' => 'Leia o módulo, pratique com supervisão e marque o conteúdo que estudou. Tempo de leitura estimado; a prática é adicional.']) ?>

<ol class="trilha">
  <?php foreach ($modulos as $i => $m): $feito = isset($progresso['modulo:' . $m['id']]); ?>
    <li class="<?= $feito ? 'feito' : '' ?>">
      <a href="<?= url('/academia/trilha/' . e($m['id'])) ?>">
        <span class="trilha-numero"><?= $feito ? '✓' : str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) ?></span>
        <span class="trilha-texto">
          <strong><?= e($m['title']) ?></strong>
          <small><?= e($m['lead'] ?? '') ?></small>
        </span>
        <span class="selo cinza"><?= e($m['time']) ?></span>
        <b aria-hidden="true">→</b>
      </a>
    </li>
  <?php endforeach; ?>
</ol>
<p class="pequeno suave">Os módulos são uma proposta de treinamento. Metas, alçadas, etapas internas e procedimentos devem ser validados pela empresa.</p>
