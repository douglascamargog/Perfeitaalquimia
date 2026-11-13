<?php
/** Abas da Prospecção. Variável: $atual ('fila' | 'empresas') */
use App\Core\Auth;
?>
<nav class="pr-abas" aria-label="Prospecção">
  <a href="<?= url('/prospeccao') ?>" class="<?= $atual === 'fila' ? 'ativo' : '' ?>">Para abordar</a>
  <a href="<?= url('/prospeccao/empresas') ?>" class="<?= $atual === 'empresas' ? 'ativo' : '' ?>">Encontrar empresas</a>
  <?php if (Auth::pode('leads.ver')): ?><a href="<?= url('/leads') ?>" class="<?= $atual === 'leads' ? 'ativo' : '' ?>">Leads do site</a><?php endif; ?>
</nav>
