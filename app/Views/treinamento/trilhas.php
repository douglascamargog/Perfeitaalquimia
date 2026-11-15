<?php
/** Todas as trilhas (consulta). Variáveis: $trilhas (com 'modulos'), $progresso */
use App\Core\Visao;
?>
<div class="cabecalho-secao">
  <div><div class="sobretitulo">TREINAMENTO</div><h1>Todas as trilhas</h1>
    <p>O treinamento de cada setor da empresa. Abra para entender como um colega trabalha ou para estudar uma área nova.</p></div>
</div>
<?= Visao::renderizar('treinamento/_abas', ['aba' => 'trilhas']) ?>
<div class="tr-grade">
  <?php foreach ($trilhas as $t): $feitos = count(array_filter($t['modulos'], fn($m) => !empty($progresso[(int) $m['id']]['aprovado_em']))); ?>
    <details class="card tr-trilha" data-tr-trilha="<?= e($t['chave']) ?>">
      <summary><span class="tr-icone"><?= e($t['icone'] ?: '📘') ?></span><span><strong><?= e($t['titulo']) ?></strong><small><?= e($t['descricao'] ?? '') ?></small></span><span class="selo cinza"><?= $feitos ?>/<?= count($t['modulos']) ?></span></summary>
      <ol class="tr-lista">
        <?php foreach ($t['modulos'] as $m): ?>
          <li><a href="<?= url('/treinamento/modulo/' . (int) $m['id']) ?>"><?= !empty($progresso[(int) $m['id']]['aprovado_em']) ? '✓ ' : '' ?><?= e($m['titulo']) ?></a><?php if ($m['tipo'] === 'vizinho'): ?> <span class="selo azul">para os vizinhos</span><?php endif; ?></li>
        <?php endforeach; ?>
      </ol>
    </details>
  <?php endforeach; ?>
</div>
