<?php
/**
 * Prévia ao vivo do alto do catálogo (o JS atualiza ao digitar e ao trocar as cores).
 * Variáveis: $v (CampanhaService::visual), $faixas (opcional)
 */
use App\Services\CampanhaService as C;
?>
<aside class="cp-previa-caixa">
  <div class="pequeno suave">Prévia do catálogo</div>
  <div class="cp-previa <?= $v['tema'] === 'escuro' ? 'escuro' : '' ?>" data-previa style="--p:<?= e($v['primaria']) ?>;--d:<?= e($v['destaque']) ?>">
    <div class="cp-previa-topo"><img src="<?= asset('img/logo.png') ?>" alt=""><span>Catálogo</span><i>Entrar</i></div>
    <div class="cp-previa-hero" <?= $v['banner'] ? 'style="--banner:url(\'' . e(url('/' . $v['banner'])) . '\')"' : '' ?> data-previa-banner>
      <span class="cp-previa-selo" data-prev-out="selo" <?= $v['selo'] ? '' : 'hidden' ?>><?= e((string) $v['selo']) ?></span>
      <b data-prev-out="titulo"><?= e($v['titulo']) ?></b>
      <p data-prev-out="chamada"><?= e($v['chamada']) ?></p>
      <span class="cp-previa-botao">Entrar com o CNPJ</span>
    </div>
    <div class="cp-previa-faixas" data-prev-faixas>
      <?php foreach ($faixas as $f): ?><span><small>acima de <?= dinheiro((float) $f['a_partir_de']) ?></small><?= e(C::textoFaixa($f)) ?></span><?php endforeach; ?>
    </div>
    <div class="cp-previa-grade"><i></i><i></i><i></i><i></i></div>
  </div>
</aside>
