<?php
/** Lista das mensagens prontas. Variáveis: $modelos, $categorias */
$icones = asset('img/icones.svg');
$porCategoria = [];
foreach ($modelos as $m) {
    $porCategoria[$m['categoria']][] = $m;
}
?>
<div class="cabecalho-secao">
  <div><div class="sobretitulo">CONTEÚDO · WHATSAPP</div><h1>Mensagens prontas</h1>
    <p>Modelos que a equipe usa na ficha do cliente: o texto já vem com o nome da loja e o link do vendedor, e abre direto no WhatsApp.</p></div>
  <div class="acoes"><a class="botao" href="<?= url('/mensagens/nova') ?>"><svg class="icone"><use href="<?= $icones ?>#mais"/></svg>Nova mensagem</a></div>
</div>
<?php if (!$modelos): ?><div class="card"><p class="suave">Nenhuma mensagem ainda.</p></div><?php endif; ?>
<?php foreach ($porCategoria as $categoria => $lista): ?>
  <section class="card msg-categoria">
    <h2><?= e($categoria) ?></h2>
    <div class="msg-grade">
      <?php foreach ($lista as $m): ?>
        <a class="msg-cartao <?= $m['ativo'] ? '' : 'inativo' ?>" href="<?= url('/mensagens/' . (int) $m['id']) ?>" data-mensagem="<?= (int) $m['id'] ?>">
          <strong><?= e($m['titulo']) ?><?= $m['ativo'] ? '' : ' <span class="selo cinza">inativa</span>' ?></strong>
          <p><?= nl2br(e(mb_strimwidth($m['texto'], 0, 220, '…'))) ?></p>
          <small class="suave">usada <?= (int) $m['usos'] ?> vez(es)</small>
        </a>
      <?php endforeach; ?>
    </div>
  </section>
<?php endforeach; ?>
