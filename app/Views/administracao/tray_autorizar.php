<?php /** Etapa "Autorizar" da instalação do aplicativo na loja Tray. Variáveis: $loja, $autorizar */ ?>
<div class="cabecalho-secao"><div><div class="sobretitulo">ADMINISTRAÇÃO › TRAY</div><h1>Autorizar a loja Tray</h1>
  <p>O aplicativo foi instalado em <strong><?= e(parse_url($loja, PHP_URL_HOST)) ?></strong>. Falta só autorizar o One a ler os pedidos da loja.</p></div></div>
<section class="card" data-tray-autorizar>
  <p>Ao clicar, abre a tela da Tray para confirmar. Depois ela volta sozinha para o One e a loja fica conectada.</p>
  <a class="botao" href="<?= e($autorizar) ?>" data-tray-autorizar-botao>Autorizar o One na loja</a>
  <a class="botao contorno" href="<?= url('/administracao#tray') ?>">Agora não</a>
</section>
