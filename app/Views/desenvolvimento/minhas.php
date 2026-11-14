<?php /** Projetos esperando as funções da pessoa. Variáveis: $projetos */ use App\Core\Visao; ?>
<div class="cabecalho-secao">
  <div><div class="sobretitulo">DESENVOLVIMENTO DE PRODUTO</div><h1>Minhas tarefas</h1>
    <p>Projetos de produto novo que estão na etapa da sua área (ou esperando a sua aprovação).</p></div>
  <div class="acoes"><a class="botao contorno" href="<?= url('/desenvolvimento') ?>">Todos os projetos</a></div>
</div>
<?php if (!$projetos): ?>
  <div class="card vazio"><p>Nada esperando você agora. 🎉</p></div>
<?php else: ?>
  <div class="dp-grade">
    <?php foreach ($projetos as $p): ?>
      <div class="dp-tarefa"><span class="selo lima"><?= e($p['etapa']) ?></span><?= Visao::renderizar('desenvolvimento/_cartao', ['p' => $p, 'progresso' => $progressos[(int) $p['id']] ?? 0]) ?></div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
