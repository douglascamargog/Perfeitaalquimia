<?php
/**
 * Escolha das cores do mockup: cor da embalagem (branco/preto; âmbar só no frasco) e cor da tampa (sugestões + qualquer cor).
 * Variáveis: $tipo, $cor, $tampa, $prefixo (id único na página). O desenho ao lado muda na hora (dp-cores.js).
 */
use App\Services\DesenvolvimentoService as D;

[$cor, $tampa] = D::cores($tipo, (string) $cor, (string) $tampa);
?>
<div class="dp-cores" data-cores>
  <fieldset class="dp-cor-pote"><legend>Cor da embalagem</legend>
    <?php foreach (D::CORES as $slug => $nome): ?>
      <label class="dp-cor" data-so-frasco="<?= $slug === 'ambar' ? '1' : '0' ?>" <?= $slug === 'ambar' && $tipo !== 'frasco' ? 'hidden' : '' ?>>
        <input type="radio" name="cor_embalagem" value="<?= e($slug) ?>" data-hex="<?= e(D::HEX[$slug]) ?>" <?= $slug === $cor ? 'checked' : '' ?>>
        <i style="background: <?= e(D::HEX[$slug]) ?>"></i><?= e($nome) ?></label>
    <?php endforeach; ?>
  </fieldset>
  <fieldset class="dp-cor-tampa"><legend>Cor da tampa</legend>
    <div class="dp-amostras">
      <?php foreach (D::TAMPAS as $hex => $nome): ?>
        <button type="button" class="dp-amostra <?= $hex === $tampa ? 'ativa' : '' ?>" data-tampa="<?= e($hex) ?>" style="background: <?= e($hex) ?>" title="<?= e($nome) ?>" aria-label="Tampa <?= e($nome) ?>"></button>
      <?php endforeach; ?>
      <label class="dp-outra" title="Outra cor">Outra<input type="color" name="cor_tampa" value="<?= e($tampa) ?>" aria-label="Cor da tampa"></label>
    </div>
  </fieldset>
</div>
