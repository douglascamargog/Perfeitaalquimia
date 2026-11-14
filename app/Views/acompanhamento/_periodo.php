<?php /** Filtro de período. Variáveis: $acao, $periodo, $de, $ate, $extra (campos ocultos) */ ?>
<form method="get" action="<?= url($acao) ?>" class="card ac-periodo">
  <?php foreach ($extra ?? [] as $k => $v): ?><input type="hidden" name="<?= e($k) ?>" value="<?= e((string) $v) ?>"><?php endforeach; ?>
  <div class="chips">
    <?php foreach (['hoje' => 'Hoje', '7d' => '7 dias', 'mes' => 'Este mês'] as $k => $nome): ?>
      <button class="chip <?= $periodo === $k ? 'ativo' : '' ?>" type="submit" name="periodo" value="<?= $k ?>"><?= $nome ?></button>
    <?php endforeach; ?>
  </div>
  <label>De <input type="date" name="de" value="<?= e($de) ?>"></label>
  <label>Até <input type="date" name="ate" value="<?= e($ate) ?>"></label>
  <button class="botao contorno pequeno" type="submit" name="periodo" value="outro">Ver período</button>
  <button class="botao contorno pequeno" type="button" onclick="window.print()">Imprimir</button>
</form>
