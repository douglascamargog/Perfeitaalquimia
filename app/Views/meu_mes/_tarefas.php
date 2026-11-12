<?php /** Tarefas do mês. Variáveis: $mes, $tarefas */
$pendentes = array_filter($tarefas, fn($t) => !$t['concluida_em']);
$feitas = array_filter($tarefas, fn($t) => $t['concluida_em']);
$linha = function (array $t): string {
    $classe = $t['concluida_em'] ? 'feita' : ($t['vence_em'] < date('Y-m-d') ? 'atrasada' : '');
    $html = '<li class="' . $classe . '"><div><strong>' . e($t['titulo']) . '</strong><small>' . data_br($t['vence_em']) . ($t['hora'] ? ' · ' . substr($t['hora'], 0, 5) : '')
        . ($t['tipo'] === 'compromisso' ? ' · compromisso' : '') . ($t['prioridade'] === 'alta' ? ' · prioridade alta' : '') . ($t['razao_social'] ? ' · ' . e($t['razao_social']) : '') . '</small></div><div class="acoes-tarefa">';
    $html .= '<form method="post" action="' . url('/meu-mes/tarefas/' . (int) $t['id'] . '/concluir') . '">' . csrf_campo() . '<button class="botao contorno pequeno" type="submit">' . ($t['concluida_em'] ? 'Reabrir' : 'Concluir') . '</button></form>';
    if (!$t['cliente_id']) {
        $html .= '<form method="post" action="' . url('/meu-mes/tarefas/' . (int) $t['id'] . '/excluir') . '" data-confirmar="Excluir?">' . csrf_campo() . '<button class="botao contorno pequeno" type="submit">Excluir</button></form>';
    }
    return $html . '</div></li>';
};
?>
<div class="grade-meu-mes">
  <section class="card">
    <h2>Nova tarefa</h2>
    <form method="post" action="<?= url('/meu-mes/tarefas') ?>">
      <?= csrf_campo() ?>
      <div class="campo"><label for="t-titulo">O que precisa ser feito</label><input type="text" id="t-titulo" name="titulo" required maxlength="200"></div>
      <div class="grade-campos">
        <div class="campo"><label for="t-data">Prazo</label><input type="date" id="t-data" name="vence_em" value="<?= e(max(date('Y-m-d'), "$mes-01")) ?>" required></div>
        <div class="campo"><label for="t-prio">Prioridade</label><select id="t-prio" name="prioridade"><option value="normal">Normal</option><option value="alta">Alta</option></select></div>
      </div>
      <button class="botao" type="submit">Criar tarefa</button>
    </form>
    <p class="pequeno suave">Os retornos da Carteira também aparecem aqui e na Agenda.</p>
  </section>
  <section class="card">
    <h2>Pendentes <span class="selo"><?= count($pendentes) ?></span></h2>
    <?php if (!$pendentes): ?><p class="suave">Tudo em dia. 👏</p><?php endif; ?>
    <ul class="lista-tarefas com-acoes"><?= implode('', array_map($linha, $pendentes)) ?></ul>
    <?php if ($feitas): ?><details><summary class="pequeno">Concluídas (<?= count($feitas) ?>)</summary><ul class="lista-tarefas com-acoes"><?= implode('', array_map($linha, $feitas)) ?></ul></details><?php endif; ?>
  </section>
</div>
