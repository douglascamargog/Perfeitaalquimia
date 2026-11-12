<?php
/** Prévia do feed do Instagram (como o perfil vai ficar). Variáveis: $mes, $posts (mais novo primeiro, sem stories) */
use App\Core\Visao;
use App\Models\Post;
?>
<?= Visao::renderizar('marketing/_cabecalho', ['aba' => 'feed', 'mes' => $mes, 'titulo' => 'Prévia do feed',
    'subtitulo' => 'Feed, carrosséis e reels do mês na ordem em que vão aparecer no perfil (o mais novo em cima). Confira a harmonia das cores.']) ?>

<div class="mk-feed-moldura">
  <div class="mk-feed-perfil">
    <img src="<?= asset('img/logo.png') ?>" alt="" width="64" height="64">
    <div><strong>perfeitaalquimia</strong><small><?= count($posts) ?> publicações neste mês</small></div>
  </div>
  <?php if (!$posts): ?>
    <p class="b-vazio">Nenhum post de feed ou reels neste mês ainda.</p>
  <?php endif; ?>
  <div class="mk-feed">
    <?php foreach ($posts as $p): ?>
      <button type="button" class="mk-feed-item" data-abrir-cliente="<?= (int) $p['id'] ?>" title="<?= e($p['titulo']) ?> · <?= data_br($p['data_publicacao']) ?>">
        <?php if ($p['imagem']): ?><img src="<?= url('/' . $p['imagem']) ?>" alt="<?= e($p['titulo']) ?>" loading="lazy"><?php else: ?><span><?= e($p['titulo']) ?></span><?php endif; ?>
        <i class="mk-feed-etapa" style="--cor: <?= e($p['etapa_cor']) ?>"><?= e($p['etapa']) ?></i>
        <?php if ($p['formato'] !== 'feed'): ?><b class="mk-feed-tipo"><?= Post::FORMATOS[$p['formato']] ?></b><?php endif; ?>
      </button>
    <?php endforeach; ?>
  </div>
</div>

<?= Visao::renderizar('carteira/_drawer', ['url' => '/marketing/posts/{id}', 'rotulo' => 'Post do Instagram']) ?>
