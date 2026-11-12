<?php /** Fala/mensagem com botão de copiar. Variáveis: $texto, $titulo (opcional), $dica (opcional) */ ?>
<div class="mensagem-copiavel">
  <?php if (!empty($titulo)): ?><h3><?= e($titulo) ?></h3><?php endif; ?>
  <blockquote data-texto><?= e($texto) ?></blockquote>
  <div class="mensagem-rodape">
    <?php if (!empty($dica)): ?><small><?= e($dica) ?></small><?php endif; ?>
    <button type="button" class="botao contorno pequeno" data-copiar>Copiar texto</button>
  </div>
</div>
