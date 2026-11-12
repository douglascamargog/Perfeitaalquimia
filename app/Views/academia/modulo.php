<?php
/**
 * Um módulo da trilha. Variáveis: $m, $anterior, $proximo, $progresso + cabeçalho
 * Módulos especiais: 'empresa' ($pagina, $produtos), 'midias-marketing' ($pagina, $imagens, $linkCatalogo, $linkGrupo),
 * treinamento técnico de produto ($produto).
 */
use App\Core\Visao;

$feito = isset($progresso['modulo:' . $m['id']]);
$numero = str_pad((string) ($m['indice'] + 1), 2, '0', STR_PAD_LEFT);
?>
<?= Visao::renderizar('academia/_cabecalho', get_defined_vars() + ['sobretitulo' => "Módulo $numero · {$m['time']} de leitura", 'titulo_pagina' => $m['title'], 'chamada' => $m['lead'] ?? '']) ?>

<a class="voltar-link" href="<?= url('/academia/trilha') ?>">← Trilha de treinamento</a>

<?php if ($m['id'] === 'empresa'): ?>
  <?= Visao::renderizar('academia/_empresa', ['pg' => $pagina, 'produtos' => $produtos]) ?>
<?php elseif ($m['id'] === 'midias-marketing'): ?>
  <?= Visao::renderizar('academia/_marketing', ['pg' => $pagina, 'imagens' => $imagens, 'linkCatalogo' => $linkCatalogo, 'linkGrupo' => $linkGrupo]) ?>
<?php elseif (!empty($produto['technicalTraining'])): ?>
  <?= Visao::renderizar('academia/_treinamento_tecnico', ['p' => $produto]) ?>
<?php else: ?>
  <article class="aula">
    <?php foreach ($m['sections'] as [$tituloSecao, $itens]): ?>
      <section class="card aula-secao">
        <h2><?= e($tituloSecao) ?></h2>
        <ul class="lista-aula">
          <?php foreach ($itens as $item): ?><li><?= e($item) ?></li><?php endforeach; ?>
        </ul>
      </section>
    <?php endforeach; ?>
  </article>
<?php endif; ?>

<?php if (!empty($m['practice'])): ?>
  <section class="pratica">
    <span class="sobretitulo">AGORA É SUA VEZ</span>
    <p><?= e($m['practice']) ?></p>
  </section>
<?php endif; ?>

<div class="fim-aula">
  <?= Visao::renderizar('academia/_marcar', ['item' => 'modulo:' . $m['id'], 'feito' => $feito, 'rotulo' => 'Já estudei este módulo', 'voltar' => '/academia/trilha/' . $m['id']]) ?>
  <div class="acoes-form">
    <?php if ($anterior): ?><a class="botao contorno" href="<?= url('/academia/trilha/' . e($anterior['id'])) ?>">← <?= e($anterior['title']) ?></a><?php endif; ?>
    <?php if ($proximo): ?><a class="botao" href="<?= url('/academia/trilha/' . e($proximo['id'])) ?>">Próximo: <?= e($proximo['title']) ?> →</a><?php endif; ?>
  </div>
</div>
