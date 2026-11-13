<?php
/**
 * Fotinhas de quem está online (topo) e a lista ao clicar. Variável: $online (Presenca::online)
 * Mostra só o nome da aba onde a pessoa está, nunca o cliente aberto.
 */
use App\Services\EquipeService;

$mostrar = array_slice($online, 0, 5);
$resto = count($online) - count($mostrar);
?>
<summary class="online-pilha" aria-label="<?= count($online) ?> pessoa(s) online" title="Quem está online">
  <?php foreach ($mostrar as $u): ?>
    <span class="online-foto" title="<?= e(($u['apelido'] ?: $u['nome']) . ' · ' . $u['visto_onde']) ?>"><?= EquipeService::avatar($u, 30) ?><i aria-hidden="true"></i></span>
  <?php endforeach; ?>
  <?php if ($resto > 0): ?><span class="online-mais">+<?= $resto ?></span><?php endif; ?>
</summary>
<div class="online-painel" role="dialog" aria-label="Quem está online">
  <header><strong>Online agora</strong><span class="suave pequeno"><?= count($online) ?> pessoa(s)</span></header>
  <?php foreach ($online as $u): ?>
    <div class="online-item">
      <span class="online-foto"><?= EquipeService::avatar($u, 34) ?><i aria-hidden="true"></i></span>
      <span><strong><?= e($u['apelido'] ?: $u['nome']) ?></strong><small><?= e($u['funcoes'] ?: '') ?></small></span>
      <span class="online-onde"><b><?= e($u['visto_onde']) ?></b><small><?= e($u['ha']) ?></small></span>
    </div>
  <?php endforeach; ?>
</div>
