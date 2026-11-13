<?php
/**
 * Ordem de produção para imprimir (A4, chão de fábrica). Página própria, sem menu.
 * Variáveis: $o, $linhas (prévia se planejada; consumo real se concluída)
 */
use App\Services\ProducaoService as P;

$empresa = config('empresa_nome', 'Perfeita Alquimia');
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>OP <?= (int) $o['id'] ?> · <?= e($o['produto']) ?></title>
  <link rel="stylesheet" href="<?= asset('css/producao.css') ?>">
</head>
<body class="pr-impressao">
  <?php if (config('modo_demo')): ?><p class="pr-demo">Ambiente de demonstração — dados simulados</p><?php endif; ?>
  <header>
    <div><strong><?= e($empresa) ?></strong><br><span>Ordem de produção</span></div>
    <div class="pr-imp-numero">OP <?= (int) $o['id'] ?><br><small><?= P::SITUACOES[$o['situacao']][0] ?></small></div>
  </header>
  <table class="pr-imp-dados">
    <tr><th>Produto</th><td colspan="3"><?= e($o['produto']) ?><?= $o['embalagem'] ? ' — ' . e($o['embalagem']) : '' ?></td></tr>
    <tr><th>Quantidade</th><td><?= number_format((int) $o['quantidade'], 0, ',', '.') ?> unidades</td><th>Lote</th><td><?= e($o['lote'] ?? '') ?></td></tr>
    <tr><th>Previsto para</th><td><?= data_br($o['previsto_para']) ?: '—' ?></td><th>Validade</th><td><?= data_br($o['validade']) ?: '—' ?></td></tr>
    <tr><th>Emitida</th><td><?= data_br($o['criado_em'], true) ?></td><th>Concluída</th><td><?= $o['concluida_em'] ? data_br($o['concluida_em'], true) : '' ?></td></tr>
  </table>
  <h2>Matérias-primas</h2>
  <table class="pr-imp-tabela">
    <thead><tr><th>Matéria-prima</th><th>Quantidade</th><th>Lote da MP</th><th>Conferido</th></tr></thead>
    <tbody>
    <?php foreach ($linhas as $l): $q = (float) ($l['precisa'] ?? $l['quantidade']); ?>
      <tr><td><?= e($l['nome']) ?></td><td><?= P::qtd($q, $l['unidade']) ?></td><td></td><td>☐</td></tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <h2>Produção</h2>
  <table class="pr-imp-dados">
    <tr><th>Unidades boas</th><td></td><th>Perdas</th><td></td></tr>
    <tr><th>Início</th><td></td><th>Fim</th><td></td></tr>
  </table>
  <?php if ($o['observacoes']): ?><h2>Observações</h2><p><?= nl2br(e($o['observacoes'])) ?></p><?php endif; ?>
  <div class="pr-assinaturas"><div>Responsável pela produção</div><div>Controle de qualidade</div></div>
  <script>window.addEventListener('load', () => window.print());</script>
</body>
</html>
