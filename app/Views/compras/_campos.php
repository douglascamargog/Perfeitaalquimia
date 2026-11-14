<?php
/** Campos do orçamento (novo e editar). Variáveis: $c, $materias, $projetos */
use App\Services\CompraService as C;

$v = fn(string $k) => e((string) (\App\Core\Sessao::antigo($k, $c[$k] ?? '')));
$tipo = (string) \App\Core\Sessao::antigo('tipo', $c['tipo'] ?? 'materia_prima');
$mpAtual = (int) \App\Core\Sessao::antigo('materia_prima_id', $c['materia_prima_id'] ?? 0);
?>
<div class="pd-duas">
  <label>Tipo<select name="tipo" data-cp-tipo><?php foreach (C::TIPOS as $k => $n): ?><option value="<?= e($k) ?>" <?= $k === $tipo ? 'selected' : '' ?>><?= e($n) ?></option><?php endforeach; ?></select></label>
  <label data-cp-mp>Matéria-prima, embalagem ou rótulo <small>entra no estoque quando chegar</small>
    <select name="materia_prima_id"><option value="">— escolha</option><?php foreach ($materias as $m): ?>
      <option value="<?= (int) $m['id'] ?>" <?= (int) $m['id'] === $mpAtual ? 'selected' : '' ?>><?= e($m['nome']) ?> (<?= e($m['unidade']) ?>)</option><?php endforeach; ?></select></label>
</div>
<label>O que comprar <small>no item avulso, descreva; com matéria-prima, pode deixar em branco</small><input type="text" name="titulo" maxlength="160" value="<?= $v('titulo') ?>"></label>
<div class="pd-duas">
  <label>Quantidade<input type="text" name="quantidade" inputmode="decimal" required value="<?= $v('quantidade') ?>"></label>
  <label>Unidade <small>do item avulso</small><input type="text" name="unidade" maxlength="10" value="<?= $v('unidade') ?: 'un' ?>"></label>
</div>
<div class="pd-duas">
  <label>Precisa até<input type="date" name="necessario_ate" value="<?= $v('necessario_ate') ?>"></label>
  <label>Projeto do Desenvolvimento <small>opcional</small><select name="dp_projeto_id"><option value="">—</option><?php foreach ($projetos as $pj): ?>
    <option value="<?= (int) $pj['id'] ?>" <?= (int) $pj['id'] === (int) ($c['dp_projeto_id'] ?? 0) ? 'selected' : '' ?>><?= e($pj['nome']) ?></option><?php endforeach; ?></select></label>
</div>
<label>Para quê <small>o motivo ajuda quem aprova</small><textarea name="motivo" rows="2"><?= $v('motivo') ?></textarea></label>
