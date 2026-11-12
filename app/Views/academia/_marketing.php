<?php
/** Módulo "Mídias do marketing". Variáveis: $pg, $imagens (catalog, folders, stories), $linkCatalogo, $linkGrupo */
use App\Core\Visao;

$figura = function (string $chave) use ($imagens): string {
    $img = $imagens[$chave] ?? null;
    if (!$img) {
        return '';
    }
    return '<figure class="figura-aula"><a href="' . e($img['src']) . '" target="_blank" rel="noopener"><img src="' . e($img['src']) . '" alt="' . e($img['alt']) . '" loading="lazy"></a><figcaption>' . e($img['alt']) . '</figcaption></figure>';
};
$passos = function (array $bloco, string $sobre) use ($figura): string {
    $html = '<section class="card"><span class="sobretitulo">' . e($sobre) . '</span><h2>' . e($bloco['titulo']) . '</h2>';
    $html .= !empty($bloco['chamada']) ? '<p>' . e($bloco['chamada']) . '</p>' : '';
    $html .= '<div class="passos-com-figura"><ol class="passos">';
    foreach ($bloco['itens'] as [$t, $d]) {
        $html .= '<li><strong>' . e($t) . '</strong><span>' . e($d) . '</span></li>';
    }
    $html .= '</ol>' . (!empty($bloco['imagem']) ? $figura($bloco['imagem']) : '') . '</div>';
    $html .= !empty($bloco['nota']) ? '<p class="pequeno suave">' . e($bloco['nota']) . '</p>' : '';
    return $html . '</section>';
};
$icones = ['phone' => '📱', 'photo' => '🖼️', 'layers' => '🧩', 'grid' => '▦'];
?>
<section class="academia-hero">
  <div>
    <span class="sobretitulo">MARKETING NA PRÁTICA</span>
    <h2><?= e($pg['titulo']) ?></h2>
    <p><?= e($pg['chamada']) ?></p>
  </div>
</section>

<div class="grade-2">
  <?php foreach ($pg['canais'] as [$sobre, $titulo, $texto, $link, $botao]): $url = $link === 'whatsapp' ? $linkGrupo : $linkCatalogo; ?>
    <section class="card">
      <span class="sobretitulo"><?= e(mb_strtoupper($sobre)) ?></span>
      <h2><?= e($titulo) ?></h2>
      <p><?= e($texto) ?></p>
      <?php if ($url): ?><a class="botao <?= $link === 'whatsapp' ? '' : 'contorno' ?>" href="<?= e($url) ?>" target="_blank" rel="noopener noreferrer"><?= e($botao) ?> ↗</a><?php endif; ?>
    </section>
  <?php endforeach; ?>
</div>

<?= $passos($pg['passos_catalogo'], '01 · COMECE PELO CATÁLOGO DIGITAL') ?>

<section class="card">
  <span class="sobretitulo">02 · DENTRO DA PASTA DO PRODUTO</span>
  <h2><?= e($pg['pastas']['titulo']) ?></h2>
  <p><?= e($pg['pastas']['chamada']) ?></p>
  <div class="grade-pastas">
    <?php foreach ($pg['pastas']['itens'] as [$icone, $pasta, $para, $texto, $dica]): ?>
      <div class="bloco-claro"><span class="pasta-icone" aria-hidden="true"><?= $icones[$icone] ?? '📁' ?></span><h3><?= e($pasta) ?></h3><strong class="pequeno"><?= e($para) ?></strong><p><?= e($texto) ?></p><small class="dica"><?= e($dica) ?></small></div>
    <?php endforeach; ?>
  </div>
  <?= $figura($pg['pastas']['imagem']) ?>
</section>

<?= $passos($pg['baixar'], '03 · ESCOLHA O ARQUIVO') ?>

<section class="card">
  <span class="sobretitulo">LEVE ESSE BENEFÍCIO PARA O ATENDIMENTO</span>
  <h2><?= e($pg['beneficio']['titulo']) ?></h2>
  <p><?= e($pg['beneficio']['chamada']) ?></p>
  <div class="grade-3">
    <?php foreach ($pg['beneficio']['momentos'] as [$t, $d]): ?><div class="bloco-claro"><h3><?= e($t) ?></h3><p><?= e($d) ?></p></div><?php endforeach; ?>
  </div>
  <h3 style="margin-top:18px">Mensagens para adaptar ao seu atendimento</h3>
  <?php foreach ($pg['mensagens'] as [$titulo, $texto]): ?>
    <?= Visao::renderizar('academia/_mensagem', ['titulo' => $titulo, 'texto' => str_replace('{catalogo}', $linkCatalogo, $texto)]) ?>
  <?php endforeach; ?>
  <p class="nota-destaque"><?= e($pg['autonomia']) ?></p>
  <a class="botao contorno pequeno" href="<?= url('/academia/atendimento') ?>">Abrir guia de atendimento →</a>
</section>

<section class="pratica">
  <span class="sobretitulo">FAÇA O CAMINHO UMA VEZ ANTES DE ORIENTAR</span>
  <ol><?php foreach ($pg['pratica'] as $passo): ?><li><?= e($passo) ?></li><?php endforeach; ?></ol>
</section>
