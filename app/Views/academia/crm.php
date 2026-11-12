<?php
/** CRM na prática (aulas do Leads2b). Variáveis: $aulas, $indice, $midias, $progresso + cabeçalho */
use App\Core\Visao;

$a = $aulas[$indice];
$feito = isset($progresso['crm:' . $a['id']]);
$proxima = $aulas[$indice + 1] ?? null;
?>
<?= Visao::renderizar('academia/_cabecalho', get_defined_vars() + ['sobretitulo' => 'Aprender fazendo', 'titulo_pagina' => 'CRM na prática', 'chamada' => 'Aulas sobre a rotina no Leads2b, com demonstrações. Os conceitos (cliente, negociação, funil e próxima atividade) valem também para a Carteira deste sistema.']) ?>

<div class="crm-layout">
  <nav class="card crm-indice" aria-label="Aulas">
    <?php foreach ($aulas as $i => $x): $ok = isset($progresso['crm:' . $x['id']]); ?>
      <a href="<?= url('/academia/crm/' . e($x['id'])) ?>" class="<?= $i === $indice ? 'ativa' : '' ?>">
        <span class="trilha-numero"><?= $ok ? '✓' : str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) ?></span>
        <span><strong><?= e($x['title']) ?></strong><small><?= e($x['short']) ?></small></span>
      </a>
    <?php endforeach; ?>
  </nav>

  <article>
    <section class="card">
      <span class="sobretitulo">AULA <?= str_pad((string) ($indice + 1), 2, '0', STR_PAD_LEFT) ?> DE <?= str_pad((string) count($aulas), 2, '0', STR_PAD_LEFT) ?> · LEADS2B</span>
      <h2><?= e($a['title']) ?></h2>
      <p class="nota-destaque"><strong>Ao terminar, você vai conseguir: </strong><?= e(mb_strtolower($a['goal'])) ?></p>
      <?php foreach ($a['blocks'] as [$titulo, $itens]): ?>
        <h3><?= e($titulo) ?></h3>
        <?php if (is_array($itens[0] ?? null)): ?>
          <dl class="ficha-dados termos"><?php foreach ($itens as [$termo, $explicacao]): ?><div><dt><?= e($termo) ?></dt><dd><?= e($explicacao) ?></dd></div><?php endforeach; ?></dl>
        <?php else: ?>
          <ol class="lista-aula"><?php foreach ($itens as $item): ?><li><?= e($item) ?></li><?php endforeach; ?></ol>
        <?php endif; ?>
      <?php endforeach; ?>
    </section>

    <?php if (!empty($a['media'])): ?>
      <section class="card">
        <h2>Veja na tela</h2>
        <?php foreach ($a['media'] as $idMidia): $md = $midias[$idMidia] ?? null; if (!$md) continue; ?>
          <figure class="figura-aula">
            <?php if (($md['type'] ?? '') === 'video'): ?>
              <video src="<?= e($md['src']) ?>" controls preload="metadata" playsinline></video>
            <?php else: ?>
              <a href="<?= e($md['src']) ?>" target="_blank" rel="noopener"><img src="<?= e($md['src']) ?>" alt="<?= e($md['title'] ?? '') ?>" loading="lazy"></a>
            <?php endif; ?>
            <figcaption><?= e($md['title'] ?? '') ?></figcaption>
          </figure>
        <?php endforeach; ?>
      </section>
    <?php endif; ?>

    <section class="pratica">
      <span class="sobretitulo">AGORA É SUA VEZ</span>
      <p><?= e($a['practice']) ?></p>
      <details class="faq"><summary>Conferir a explicação</summary><p><?= e($a['answer']) ?></p></details>
    </section>

    <div class="fim-aula">
      <?= Visao::renderizar('academia/_marcar', ['item' => 'crm:' . $a['id'], 'feito' => $feito, 'rotulo' => 'Já estudei esta aula', 'voltar' => '/academia/crm/' . $a['id']]) ?>
      <?php if ($proxima): ?><a class="botao" href="<?= url('/academia/crm/' . e($proxima['id'])) ?>">Próxima aula →</a><?php else: ?><a class="botao" href="<?= url('/academia/trilha') ?>">Voltar à trilha</a><?php endif; ?>
    </div>
  </article>
</div>
