<?php
/** Administração (engrenagem do topo). Variáveis: $itens (telas de administração que a pessoa pode abrir) */
$icones = asset('img/icones.svg');
?>
<div class="cabecalho-secao">
  <div><div class="sobretitulo">SISTEMA</div><h1>Administração</h1>
    <p>Pessoas e acessos, regras do sistema, integrações e atualizações. Escolha o que quer ajustar.</p></div>
</div>
<div class="adm-grade">
  <?php foreach ($itens as $i): ?>
    <a class="adm-cartao" href="<?= url($i['url']) ?>">
      <span class="adm-icone"><svg class="icone"><use href="<?= $icones ?>#<?= e($i['icone']) ?>"/></svg></span>
      <span><strong><?= e($i['titulo']) ?></strong><small><?= e($i['descricao'] ?? '') ?></small></span>
      <?php if ($i['leitura']): ?><span class="adm-leitura" title="Só leitura">👁</span><?php endif; ?>
    </a>
  <?php endforeach; ?>
</div>
