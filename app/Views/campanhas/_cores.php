<?php
/**
 * Escolha de cores do catálogo: combinações prontas + cor principal + cor de destaque + fundo claro/escuro.
 * Variáveis: $prim, $dest (vazio = da empresa), $tema, $pode
 */
use App\Services\Aparencia;
use App\Services\CampanhaService as C;

[$pe, $se] = Aparencia::padrao();
$dis = $pode ? '' : 'disabled';
?>
<fieldset class="cp-cores" data-cp-cores data-padrao-p="<?= e($pe) ?>" data-padrao-d="<?= e($se) ?>">
  <legend>Cores</legend>
  <div class="cp-prontas" role="group" aria-label="Combinações prontas">
    <?php foreach (C::VISUAIS as $k => [$nome, $p, $d, $t]): ?>
      <button type="button" class="cp-pronta" data-pronta data-p="<?= e($p) ?>" data-d="<?= e($d) ?>" data-t="<?= e($t) ?>" title="<?= e($nome) ?>" <?= $dis ?>
        style="--p:<?= e($p ?: $pe) ?>;--d:<?= e($d ?: $se) ?>"><i></i><span><?= e($nome) ?></span></button>
    <?php endforeach; ?>
  </div>
  <div class="cp-cores-linha">
    <label>Cor principal <input type="color" value="<?= e($prim ?: $pe) ?>" data-cor="p" <?= $dis ?>></label>
    <label>Cor de destaque <input type="color" value="<?= e($dest ?: $se) ?>" data-cor="d" <?= $dis ?>></label>
    <input type="hidden" name="cor_primaria" value="<?= e($prim) ?>" data-cor-campo="p">
    <input type="hidden" name="cor_destaque" value="<?= e($dest) ?>" data-cor-campo="d">
    <span class="cp-tema" role="radiogroup" aria-label="Fundo">
      <label><input type="radio" name="tema" value="claro" <?= $tema !== 'escuro' ? 'checked' : '' ?> <?= $dis ?>> Fundo claro</label>
      <label><input type="radio" name="tema" value="escuro" <?= $tema === 'escuro' ? 'checked' : '' ?> <?= $dis ?>> Fundo escuro</label>
    </span>
  </div>
</fieldset>
