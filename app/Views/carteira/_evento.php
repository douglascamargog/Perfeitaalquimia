<?php
/**
 * Um item da linha do tempo (gaveta e página do cliente), com as miniaturas dos prints/arquivos.
 * Variáveis: $ev, $anexos (deste evento), $clienteId
 */
use App\Models\ClienteEvento;

// grupo para os filtros da linha do tempo (página do cliente)
$grupo = ['contato' => 'contato', 'ligacao' => 'contato', 'whatsapp' => 'contato', 'visita' => 'contato', 'email' => 'contato', 'busca_contato' => 'contato',
    'nota' => 'nota', 'pedido' => 'pedido', 'orcamento' => 'pedido', 'cobranca' => 'cobranca', 'promessa' => 'cobranca'][$ev['tipo']] ?? 'etapa';
?>
<li class="tipo-<?= e($ev['tipo']) ?>" data-grupo="<?= $grupo ?>">
  <strong><?= e(ClienteEvento::TIPOS[$ev['tipo']] ?? $ev['tipo']) ?><?= $ev['canal'] && !in_array($ev['tipo'], ['ligacao', 'nota'], true) ? ' · ' . e($ev['canal']) : '' ?></strong>
  <?php if ($ev['descricao']): ?><span><?= nl2br(e($ev['descricao'])) ?></span><?php endif; ?>
  <?php if ($anexos): ?>
    <div class="lt-anexos" data-anexos>
      <?php foreach ($anexos as $a): $link = url('/carteira/clientes/' . (int) $clienteId . '/anexos/' . (int) $a['id']); ?>
        <?php if (str_starts_with($a['mime'], 'image/')): ?>
          <a href="<?= $link ?>" target="_blank" rel="noopener" title="<?= e($a['nome']) ?>"><img src="<?= $link ?>" alt="<?= e($a['nome']) ?>" loading="lazy"></a>
        <?php else: ?>
          <a class="lt-pdf" href="<?= $link ?>" target="_blank" rel="noopener">📄 <?= e($a['nome']) ?></a>
        <?php endif; ?>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
  <small><?= data_br($ev['ocorrido_em'], true) ?> · <?= pessoa($ev['usuario_nome'] ?? null, null, false, 18, 'Sistema') ?></small>
</li>
