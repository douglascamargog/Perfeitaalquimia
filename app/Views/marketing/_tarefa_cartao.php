<?php
/**
 * Cartão da lista de tarefas do Marketing. Variáveis: $c (tarefa, post ou etapa de projeto), $pode, $podeAprovar
 */
$hoje = date('Y-m-d');
$atrasada = !empty($c['prazo']) && $c['prazo'] < $hoje && ($c['situacao'] ?? '') !== 'feito';
$link = $c['tipo'] === 'tarefa' ? '/marketing/tarefas/' . (int) $c['id'] : $c['link'];
$acao = fn(string $a, string $rotulo, string $classe = 'contorno') => '<form method="post" action="' . url('/marketing/tarefas/' . (int) $c['id'] . '/mudar') . '">'
    . csrf_campo() . '<input type="hidden" name="acao" value="' . $a . '"><input type="hidden" name="voltar" value="inicio">'
    . '<button class="botao ' . $classe . ' pequeno" type="submit" data-acao="' . $a . '">' . $rotulo . '</button></form>';
?>
<article class="mt-cartao <?= $c['tipo'] !== 'tarefa' ? 'mt-' . $c['tipo'] : '' ?> <?= !empty($c['urgente']) ? 'urgente' : '' ?> <?= $atrasada ? 'atrasada' : '' ?>"
  data-cartao="<?= e($c['tipo']) ?>-<?= (int) $c['id'] ?>">
  <a class="mt-titulo" href="<?= url($link) ?>"><?= e($c['titulo']) ?></a>
  <div class="mt-meta">
    <?php if ($c['tipo'] === 'post'): ?><span class="mt-etiqueta post">Post</span>
    <?php elseif ($c['tipo'] === 'projeto'): ?><span class="mt-etiqueta projeto">Desenvolvimento</span>
    <?php else: ?><span class="mt-etiqueta"><?= e((string) $c['tipo_nome']) ?></span><?php endif; ?>
    <?php if (!empty($c['urgente'])): ?><span class="mt-etiqueta urgente">urgente</span><?php endif; ?>
    <?php if (!empty($c['prazo'])): ?><span class="mt-prazo"><?= $atrasada ? 'atrasada · ' : '' ?><?= data_br($c['prazo']) ?></span><?php endif; ?>
  </div>
  <?php if ($c['tipo'] === 'tarefa'): ?>
    <small class="mt-quem"><?= e((string) $c['funcao']) ?><?= $c['responsavel'] ? ' · ' . e($c['responsavel']) : '' ?><?= $c['setor'] ? ' · pedido de ' . e($c['setor']) : '' ?></small>
    <?php if ($pode || $podeAprovar): ?><div class="mt-acoes">
      <?php if ($pode && $c['situacao'] === 'a_fazer'): ?><?= $acao('comecar', 'Começar') ?><?php endif; ?>
      <?php if ($pode && in_array($c['situacao'], ['a_fazer', 'fazendo'], true)): ?><?= $acao('aprovacao', 'Para aprovação') ?><?= $acao('concluir', 'Concluir', 'lima') ?><?php endif; ?>
      <?php if ($podeAprovar && $c['situacao'] === 'aprovacao'): ?><?= $acao('aprovar', 'Aprovar', 'lima') ?><a class="botao contorno pequeno" href="<?= url($link) ?>#decidir">Pedir ajuste</a><?php endif; ?>
    </div><?php endif; ?>
  <?php elseif ($c['tipo'] === 'projeto'): ?>
    <small class="mt-quem"><?= e((string) ($c['funcao'] ?? '')) ?></small>
  <?php endif; ?>
</article>
