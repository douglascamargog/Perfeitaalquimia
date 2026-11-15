<?php
/**
 * Cartão de uma tarefa da Agenda (cor pelo tipo), com Abrir ficha, WhatsApp, Reagendar e Concluir.
 * Variáveis: $t, $verTodas, $voltar (URL da agenda para voltar), $compacto (semana)
 */
use App\Services\Normalizar;

$tipos = ['retorno' => 'Retorno', 'compromisso' => 'Compromisso', 'cobranca' => 'Cobrança', 'outro' => 'Tarefa'];
$tipo = array_key_exists($t['tipo'] ?? '', $tipos) ? $t['tipo'] : 'outro';
$wa = preg_replace('/\D/', '', (string) ($t['whatsapp'] ?: $t['telefone']));
$hoje = date('Y-m-d');
?>
<article class="ag-cartao ag-<?= $tipo ?> <?= $t['vence_em'] < $hoje ? 'atrasada' : '' ?>" data-tarefa="<?= (int) $t['id'] ?>" data-dia="<?= e($t['vence_em']) ?>">
  <header>
    <span class="ag-tipo"><?= $t['hora'] ? '<b>' . substr($t['hora'], 0, 5) . '</b> · ' : '' ?><?= $tipos[$tipo] ?></span>
    <?php if (empty($compacto)): ?><span class="ag-data"><?= $t['vence_em'] < $hoje ? 'atrasada · ' : '' ?><?= data_br($t['vence_em']) ?></span><?php endif; ?>
  </header>
  <strong class="ag-titulo"><?= e($t['titulo']) ?></strong>
  <?php if ($t['cliente_id']): ?>
    <button type="button" class="ag-cliente" data-abrir-cliente="<?= (int) $t['cliente_id'] ?>"><?= e($t['razao_social']) ?></button>
    <small class="suave"><?= e($t['cidade']) ?>/<?= e($t['uf']) ?><?= $wa ? ' · ' . e(Normalizar::telefoneFormatado($wa)) : '' ?></small>
  <?php endif; ?>
  <?php if ($verTodas): ?><small class="ag-quem"><?= pessoa($t['usuario_nome'] ?? null, null, false, 18, '—') ?></small><?php endif; ?>
  <footer class="ag-acoes">
    <?php if ($wa): ?><a class="ag-botao" href="https://wa.me/55<?= e($wa) ?>" target="_blank" rel="noopener noreferrer" title="WhatsApp">💬</a><?php endif; ?>
    <details class="ag-reagendar"><summary class="ag-botao" title="Reagendar">📅</summary>
      <form method="post" action="<?= url('/agenda/' . (int) $t['id'] . '/reagendar') ?>" data-reagendar><?= csrf_campo() ?>
        <input type="hidden" name="voltar" value="<?= e($voltar) ?>">
        <button class="botao contorno pequeno" type="submit" name="para" value="hoje">Hoje</button>
        <button class="botao contorno pequeno" type="submit" name="para" value="amanha">Amanhã</button>
        <input type="date" name="data" min="<?= $hoje ?>" aria-label="Outra data"><button class="botao pequeno" type="submit" name="para" value="data">Ok</button>
      </form>
    </details>
    <form method="post" action="<?= url('/agenda/' . (int) $t['id'] . '/concluir') ?>"><?= csrf_campo() ?><input type="hidden" name="voltar" value="<?= e($voltar) ?>">
      <button class="ag-botao ag-concluir" type="submit" title="Concluir" data-concluir>✓ Feito</button></form>
  </footer>
</article>
