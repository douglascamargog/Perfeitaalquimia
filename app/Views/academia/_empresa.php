<?php
/** Página "Nossa empresa". Variáveis: $pg (conteúdo), $produtos (por slug, para as fotos das linhas) */
?>
<section class="academia-hero empresa">
  <div>
    <span class="sobretitulo"><?= e(mb_strtoupper($pg['sobretitulo'])) ?></span>
    <h2><?= e($pg['titulo']) ?></h2>
    <p><?= e($pg['chamada']) ?></p>
    <div class="chips claros"><?php foreach ($pg['selos'] as $s): ?><span class="chip"><?= e($s) ?></span><?php endforeach; ?></div>
  </div>
  <img class="empresa-logo" src="<?= asset('img/logo.png') ?>" alt="Logo Perfeita Alquimia">
</section>

<section class="card">
  <span class="sobretitulo">QUEM SOMOS</span>
  <h2><?= e($pg['quem_somos']['titulo']) ?></h2>
  <?php foreach ($pg['quem_somos']['paragrafos'] as $p): ?><p><?= e($p) ?></p><?php endforeach; ?>
</section>

<section class="card">
  <span class="sobretitulo">NOSSA ATUAÇÃO</span>
  <h2><?= e($pg['frentes']['titulo']) ?></h2>
  <div class="grade-3">
    <?php foreach ($pg['frentes']['itens'] as [$titulo, $texto]): ?><div class="bloco-claro"><h3><?= e($titulo) ?></h3><p><?= e($texto) ?></p></div><?php endforeach; ?>
  </div>
  <p class="pequeno suave"><?= e($pg['frentes']['nota']) ?></p>
</section>

<section class="card">
  <div class="v4-painel-topo"><div><span class="sobretitulo">O QUE OFERECEMOS</span><h2><?= e($pg['linhas']['titulo']) ?></h2></div>
    <a class="botao contorno pequeno" href="<?= url('/academia/produtos') ?>">Explorar todos os produtos →</a></div>
  <div class="grade-linhas">
    <?php foreach ($pg['linhas']['itens'] as [$linha, $texto, $slug]): $exemplo = $produtos[$slug] ?? null; ?>
      <a class="cartao-linha" href="<?= $exemplo ? url('/academia/produtos/' . e($slug)) : url('/academia/produtos') ?>">
        <span class="foto"><?php if (!empty($exemplo['imagem'])): ?><img src="<?= e($exemplo['imagem']) ?>" alt="<?= e($exemplo['nome']) ?>" loading="lazy"><?php endif; ?></span>
        <strong><?= e($linha) ?></strong><small><?= e($texto) ?></small><span class="pequeno">Ver um exemplo →</span>
      </a>
    <?php endforeach; ?>
  </div>
</section>

<section class="card">
  <span class="sobretitulo">SEU LUGAR NA EQUIPE</span>
  <h2><?= e($pg['papel']['titulo']) ?></h2>
  <p><?= e($pg['papel']['chamada']) ?></p>
  <div class="grade-3">
    <?php foreach ($pg['papel']['itens'] as $i => [$titulo, $texto]): ?><div class="bloco-claro"><span class="numero"><?= $i + 1 ?></span><h3><?= e($titulo) ?></h3><p><?= e($texto) ?></p></div><?php endforeach; ?>
  </div>
</section>

<section class="card">
  <span class="sobretitulo">PARA O SEU PRIMEIRO CONTATO</span>
  <h2><?= e($pg['apresentacao']['titulo']) ?></h2>
  <p><?= e($pg['apresentacao']['chamada']) ?></p>
  <?= \App\Core\Visao::renderizar('academia/_mensagem', ['texto' => $pg['apresentacao']['fala']]) ?>
</section>
