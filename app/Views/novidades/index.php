<?php
/** Histórico de versões do One. Variáveis: $versoes, $atual */
use App\Core\Visao;
?>
<div class="cabecalho-secao">
  <div><div class="sobretitulo">SISTEMA · NOVIDADES</div><h1>Novidades do One</h1>
    <p>Versão instalada: <strong><?= e($atual) ?></strong>. Atualizações pequenas mudam a decimal (V2.1, V2.2); as grandes mudam o número (V3.0).</p></div>
</div>
<div class="nov-historico">
  <?php foreach ($versoes as $versao): ?>
    <div class="card"><?= Visao::renderizar('novidades/_versao', ['versao' => $versao]) ?></div>
  <?php endforeach; ?>
</div>
