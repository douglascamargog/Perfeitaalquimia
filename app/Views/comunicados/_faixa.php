<?php
/**
 * Faixa de comunicados no topo (não lidos da pessoa). Variável: $comunicados
 * O texto completo de cada um vai num <template>, aberto na janela pelo comunicados.js.
 */
if (!$comunicados) {
    return;
}
$c = $comunicados[0];
$mais = count($comunicados) - 1;
$icones = asset('img/icones.svg');
?>
<button type="button" class="com-faixa <?= $c['prioridade'] === 'importante' ? 'importante' : '' ?>" data-com-abrir="<?= (int) $c['id'] ?>" title="Abrir comunicado">
  <svg class="icone" aria-hidden="true"><use href="<?= $icones ?>#megafone"/></svg>
  <span class="com-titulo"><?= $c['prioridade'] === 'importante' ? '<b>Importante:</b> ' : '' ?><?= e($c['titulo']) ?></span>
  <?php if ($mais > 0): ?><span class="com-mais">+<?= $mais ?></span><?php endif; ?>
  <span class="com-ver">ver</span>
</button>
<?php foreach ($comunicados as $c): ?>
  <template data-com-texto="<?= (int) $c['id'] ?>">
    <article class="com-janela-conteudo <?= $c['prioridade'] === 'importante' ? 'importante' : '' ?>">
      <span class="sobretitulo"><?= $c['prioridade'] === 'importante' ? 'COMUNICADO IMPORTANTE' : 'COMUNICADO' ?></span>
      <h2><?= e($c['titulo']) ?></h2>
      <p class="com-quem"><?= e($c['autor'] ?? 'Administração') ?> · <?= data_br($c['inicio'], true) ?></p>
      <div class="com-texto"><?= nl2br(e($c['texto'])) ?></div>
    </article>
  </template>
<?php endforeach; ?>
