<?php /** Carteira › Lojas excluídas (para restaurar). Variáveis: $lojas */ ?>
<a class="voltar-link" href="<?= url('/carteira/lista') ?>">← Carteira</a>
<div class="cabecalho-secao">
  <div><div class="sobretitulo">CARTEIRA</div><h1>Lojas excluídas</h1>
    <p>Lojas que saíram da Carteira (excluídas ou juntadas a outra). O histórico continua guardado; "Restaurar" traz a loja de volta.</p></div>
</div>
<section class="card sem-espaco">
  <div class="tabela-rolagem">
    <table class="tabela">
      <thead><tr><th>Loja</th><th>Cidade</th><th>Responsável</th><th>Excluída em</th><th>Motivo</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($lojas as $l): ?>
        <tr data-excluida="<?= (int) $l['id'] ?>"><td><strong><?= e($l['nome_fantasia'] ?: $l['razao_social']) ?></strong> <small class="suave">#<?= (int) $l['id'] ?></small></td>
          <td><?= e($l['cidade']) ?>/<?= e($l['uf']) ?></td><td><?= e($l['responsavel'] ?? '—') ?></td><td><?= data_br($l['excluido_em'], true) ?></td>
          <td class="pequeno"><?= e(preg_replace('/^Loja excluída da Carteira\. Motivo: /', '', (string) $l['motivo'])) ?></td>
          <td><form method="post" action="<?= url('/carteira/excluidas/' . (int) $l['id'] . '/restaurar') ?>" data-confirmar="Restaurar esta loja na Carteira?"><?= csrf_campo() ?>
            <button class="botao contorno pequeno" type="submit">Restaurar</button></form></td></tr>
      <?php endforeach; ?>
      <?php if (!$lojas): ?><tr><td colspan="6" class="vazio">Nenhuma loja excluída.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</section>
