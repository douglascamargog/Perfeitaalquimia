<?php
/**
 * Um módulo: texto + prova. Depois de enviar, mostra a nota e a explicação de cada pergunta.
 * Variáveis: $m, $perguntas, $resultado (null ou ['nota','aprovado','correcao']), $meu, $anterior, $proximo, $notaMinima
 */
use App\Core\Visao;
use App\Services\TreinamentoService as T;

$aprovado = !empty($meu['aprovado_em']);
?>
<div class="cabecalho-secao">
  <div><div class="sobretitulo"><?= e(($m['trilha_icone'] ?? '') . ' ' . $m['trilha_titulo']) ?><?= $m['tipo'] === 'vizinho' ? ' · área vizinha' : '' ?></div>
    <h1><?= e($m['titulo']) ?></h1><?php if ($m['resumo']): ?><p><?= e($m['resumo']) ?></p><?php endif; ?></div>
  <div class="acoes"><span class="selo cinza"><?= (int) $m['tempo_min'] ?> min</span><?php if ($aprovado): ?><span class="selo verde">Concluído · nota <?= (int) $meu['nota'] ?>%</span><?php endif; ?></div>
</div>
<?= Visao::renderizar('treinamento/_abas', ['aba' => 'meu']) ?>

<article class="card tr-conteudo" data-tr-conteudo><?= T::html($m['conteudo']) ?></article>

<?php if ($perguntas): ?>
  <section class="card tr-prova" id="prova" data-tr-prova>
    <h2>Prova do módulo</h2>
    <?php if ($resultado): ?>
      <p class="tr-nota <?= $resultado['aprovado'] ? 'ok' : 'nao' ?>" data-tr-nota="<?= (int) $resultado['nota'] ?>">
        <?= $resultado['aprovado'] ? '🎉 Você acertou ' . (int) $resultado['nota'] . '%: módulo concluído!' : 'Você acertou ' . (int) $resultado['nota'] . '%. Precisa de ' . $notaMinima . '% para concluir: releia o texto e tente de novo.' ?></p>
    <?php else: ?>
      <p class="suave">Responda para concluir o módulo (precisa acertar <?= $notaMinima ?>%). Pode refazer quantas vezes quiser.</p>
    <?php endif; ?>
    <form method="post" action="<?= url('/treinamento/modulo/' . (int) $m['id'] . '/prova') ?>#prova"><?= csrf_campo() ?>
      <?php foreach ($perguntas as $i => $p): $c = $resultado['correcao'][(int) $p['id']] ?? null; ?>
        <fieldset class="tr-pergunta <?= $c ? ($c['certa'] ? 'certa' : 'errada') : '' ?>">
          <legend><?= $i + 1 ?>. <?= e($p['pergunta']) ?></legend>
          <?php foreach ($p['opcoes'] as $k => $op): ?>
            <label class="<?= $c && $k === $c['correta'] ? 'tr-correta' : '' ?>"><input type="radio" name="resposta[<?= (int) $p['id'] ?>]" value="<?= $k ?>" required <?= $c && $c['marcada'] === $k ? 'checked' : '' ?>> <?= e($op) ?></label>
          <?php endforeach; ?>
          <?php if ($c && !empty($c['explicacao'])): ?><p class="tr-explicacao"><?= $c['certa'] ? '✓' : '✗' ?> <?= e($c['explicacao']) ?></p><?php endif; ?>
        </fieldset>
      <?php endforeach; ?>
      <button class="botao" type="submit" data-tr-enviar><?= $resultado ? 'Refazer a prova' : 'Enviar respostas' ?></button>
    </form>
  </section>
<?php else: ?>
  <form method="post" action="<?= url('/treinamento/modulo/' . (int) $m['id'] . '/prova') ?>" class="tr-sem-prova"><?= csrf_campo() ?>
    <button class="botao" type="submit" data-tr-enviar><?= $aprovado ? 'Concluído ✓' : 'Marcar como concluído' ?></button></form>
<?php endif; ?>

<nav class="tr-navegar">
  <?php if ($anterior): ?><a class="botao contorno" href="<?= url('/treinamento/modulo/' . (int) $anterior['id']) ?>">← <?= e($anterior['titulo']) ?></a><?php else: ?><span></span><?php endif; ?>
  <?php if ($proximo): ?><a class="botao contorno" href="<?= url('/treinamento/modulo/' . (int) $proximo['id']) ?>" data-tr-proximo><?= e($proximo['titulo']) ?> →</a><?php else: ?><a class="botao contorno" href="<?= url('/treinamento') ?>">Voltar ao treinamento</a><?php endif; ?>
</nav>
