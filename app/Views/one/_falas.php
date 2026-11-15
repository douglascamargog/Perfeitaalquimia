<?php
/**
 * Falas do One (usado no painel flutuante e no card do Início).
 * Variáveis: $saudacao, $falas, $ajuda (opcional: a tela atual explicada), $tutorial (o passo a passo da tela, se houver)
 */
$img = fn(string $humor, string $variacao = 'padrao') => asset("img/one/one-$humor-$variacao.svg");
?>
<div class="one-falas">
  <div class="one-fala one-saudacao">
    <img src="<?= $img($saudacao['humor']) ?>" alt="" width="44" height="48">
    <div><p><?= e($saudacao['texto']) ?></p></div>
  </div>
  <?php if (!empty($tutorial)): ?>
    <button type="button" class="one-fala one-ajuda-painel" data-one-ajuda>💡 O que dá pra fazer aqui? <small>Passo a passo de "<?= e($tutorial['titulo']) ?>"</small></button>
  <?php elseif (!empty($ajuda)): ?>
    <details class="one-fala one-ajuda-painel">
      <summary>💡 O que dá pra fazer aqui? <small><?= e($ajuda['titulo']) ?></small></summary>
      <ol><?php foreach (array_filter($ajuda['passos']) as $passo): ?><li><?= e($passo) ?></li><?php endforeach; ?></ol>
    </details>
  <?php endif; ?>
  <?php foreach ($falas as $f): ?>
    <div class="one-fala">
      <img src="<?= $img($f['humor']) ?>" alt="" width="36" height="40">
      <div>
        <p><?= e($f['texto']) ?></p>
        <?php if (!empty($f['acoes'])): ?>
          <div class="one-acoes"><?php foreach ($f['acoes'] as $a): ?>
            <?php if ($a['tipo'] === 'post'): ?><form method="post" action="<?= url($a['url']) ?>" class="one-acao-form"><?= csrf_campo() ?><?php foreach ($a['campos'] ?? [] as $k => $val): ?><input type="hidden" name="<?= e($k) ?>" value="<?= e($val) ?>"><?php endforeach; ?><button type="submit" class="one-acao"><?= e($a['rotulo']) ?></button></form>
            <?php else: ?><a class="one-acao<?= $a['rotulo'] === 'WhatsApp' ? ' whats' : '' ?>" href="<?= $a['tipo'] === 'externo' ? e($a['url']) : url($a['url']) ?>" <?= $a['tipo'] === 'externo' && str_starts_with($a['url'], 'http') ? 'target="_blank" rel="noopener noreferrer"' : '' ?>><?= e($a['rotulo']) ?></a><?php endif; ?>
          <?php endforeach; ?></div>
        <?php elseif (!empty($f['link'])): ?><a href="<?= url($f['link']) ?>"><?= e($f['acao'] ?? 'Abrir') ?> →</a><?php endif; ?>
      </div>
    </div>
  <?php endforeach; ?>
</div>
