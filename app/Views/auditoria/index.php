<?php
/** Log de auditoria. Variáveis: $registros, $entidades, $entidade, $pagina, $paginas, $total */
$mostrarJson = function (?string $json): string {
    if (!$json) {
        return '';
    }
    $dados = json_decode($json, true) ?: [];
    $linhas = [];
    foreach ($dados as $campo => $valor) {
        $linhas[] = $campo . ': ' . (is_scalar($valor) || $valor === null ? (string) ($valor ?? '—') : json_encode($valor, JSON_UNESCAPED_UNICODE));
    }
    return e(implode("\n", $linhas));
};
?>
<div class="cabecalho-pagina">
  <p class="suave"><?= (int) $total ?> registros. Somente o perfil Admin vê esta tela.</p>
  <form method="get" action="<?= url('/auditoria') ?>" style="display:flex;gap:8px">
    <select name="entidade" aria-label="Filtrar por tipo">
      <option value="">Todos os tipos</option>
      <?php foreach ($entidades as $ent): ?>
        <option value="<?= e($ent) ?>" <?= $ent === $entidade ? 'selected' : '' ?>><?= e($ent) ?></option>
      <?php endforeach; ?>
    </select>
    <button class="botao contorno" type="submit">Filtrar</button>
  </form>
</div>

<div class="card">
  <div class="tabela-rolagem">
    <table class="tabela">
      <thead><tr><th>Quando</th><th>Quem</th><th>O quê</th><th>Antes</th><th>Depois</th><th>IP</th></tr></thead>
      <tbody>
      <?php foreach ($registros as $r): ?>
        <tr>
          <td><?= data_br($r['criado_em'], true) ?></td>
          <td><?= e($r['usuario_nome'] ?? 'Sistema') ?></td>
          <td><span class="selo"><?= e($r['entidade']) ?><?= $r['entidade_id'] ? ' #' . (int) $r['entidade_id'] : '' ?></span> <?= e($r['acao']) ?></td>
          <td class="json-mudanca"><?= $mostrarJson($r['antes_json']) ?></td>
          <td class="json-mudanca"><?= $mostrarJson($r['depois_json']) ?></td>
          <td class="pequeno suave"><?= e($r['ip']) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$registros): ?><tr><td colspan="6" class="suave">Nenhum registro.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
  <?php if ($paginas > 1): ?>
    <nav class="paginacao" aria-label="Páginas">
      <?php for ($p = 1; $p <= $paginas; $p++): ?>
        <a class="botao <?= $p === $pagina ? '' : 'contorno' ?> pequeno" href="<?= url('/auditoria') ?>?<?= e(http_build_query(['entidade' => $entidade, 'pagina' => $p])) ?>"><?= $p ?></a>
      <?php endfor; ?>
    </nav>
  <?php endif; ?>
</div>
