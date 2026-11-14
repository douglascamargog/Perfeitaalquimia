<?php
/**
 * Página do projeto (o "jogo"). Variáveis: $p, $linha, $jogo, $gavetas, $historico, $ficha, $ordem, $podeGerenciar
 */
$acao = fn(string $a) => url('/desenvolvimento/' . (int) $p['id'] . '/' . $a);
$aba = fn(string $a) => url('/produtos/' . e($p['produto_slug'])) . '?aba=' . $a;
$atalhos = ['ficha' => ['ficha', 'Preencher a ficha técnica do produto'], 'composicao' => ['composicao', 'Preencher a composição do produto'], 'arte' => ['documentos', 'Ver os documentos do produto']];
$tec = \App\Core\Banco::um('SELECT ean, ncm FROM produto_tecnico WHERE produto_id = ?', [$p['produto_id']]) ?: [];
$situacoes = ['andamento' => ['Em andamento', 'lima'], 'pausado' => ['Pausado', 'cinza'], 'lancado' => ['Lançado', 'lima'], 'cancelado' => ['Cancelado', 'vermelho']];
?>
<a class="voltar-link" href="<?= url('/desenvolvimento') ?>">← Todos os projetos</a>
<div class="cabecalho-secao">
  <div><div class="sobretitulo">DESENVOLVIMENTO DE PRODUTO<?= $p['cliente'] ? ' · TERCEIRIZAÇÃO' : '' ?></div><h1><?= e($p['nome']) ?></h1>
    <p><span class="selo <?= $situacoes[$p['situacao']][1] ?>" data-situacao><?= $situacoes[$p['situacao']][0] ?></span>
      <?= $p['cliente'] ? ' Cliente: <strong>' . e($p['cliente']) . '</strong> ·' : '' ?>
      <?= $p['prazo'] ? ' Prazo: <strong>' . data_br($p['prazo']) . '</strong> ·' : '' ?>
      <a href="<?= url('/produtos/' . e($p['produto_slug'])) ?>">Ver o produto<?= $p['situacao'] !== 'lancado' ? ' (ainda oculto)' : '' ?> →</a></p></div>
  <?php if ($podeGerenciar && $p['situacao'] !== 'lancado'): ?>
  <div class="acoes">
    <form method="post" action="<?= $acao('situacao') ?>"><?= csrf_campo() ?><input type="hidden" name="situacao" value="<?= $p['situacao'] === 'pausado' ? 'andamento' : 'pausado' ?>">
      <button class="botao contorno pequeno" type="submit"><?= $p['situacao'] === 'pausado' ? 'Retomar' : 'Pausar' ?></button></form>
    <form method="post" action="<?= $acao('voltar') ?>" data-confirmar="Voltar o projeto uma etapa?"><?= csrf_campo() ?><input type="hidden" name="motivo" value="">
      <button class="botao contorno pequeno" type="submit">Voltar uma etapa</button></form>
  </div>
  <?php endif; ?>
</div>

<ol class="dp-linha so-impressao" aria-label="Etapas">
  <?php foreach ($linha as $e): ?>
    <li class="<?= e($e['situacao'] ?? 'pendente') ?>" title="<?= e($e['funcao'] ?: 'gestão') ?>">
      <b><?= (int) $e['ordem'] ?></b><span><?= e($e['nome']) ?></span>
      <small><?= $e['situacao'] === 'concluida' ? '✓ ' . e($e['concluida_por'] ?? '') . ' · ' . data_br($e['concluida_em']) : e($e['funcao'] ?: 'gestão') ?></small>
    </li>
  <?php endforeach; ?>
</ol>

<?php
// ---------- Palco do "jogo": a embalagem vai se formando a cada etapa concluída ----------
$tem = array_map(fn($e) => 'tem-' . preg_replace('/[^a-z]/', '', (string) $e['checagem']), array_filter($linha, fn($e) => $e['situacao'] === 'concluida'));
$comGaveta = [];
foreach ($gavetas as $g) { $comGaveta[(int) $g['pe']['etapa_id']] = $g; }
// Com várias etapas abertas ao mesmo tempo, o balão mostra só quantas coisas faltam (a lista fica na gaveta)
$compacto = count(array_filter($gavetas, fn($g) => $g['aberta'])) > 2;
?>
<section class="dp-jogo <?= $p['situacao'] === 'lancado' ? 'lancado' : '' ?>" data-jogo aria-label="Como está o produto">
  <div class="dp-jogo-topo">
    <div><span class="sobretitulo"><?= e(\App\Services\DesenvolvimentoService::TIPOS[$jogo['tipo']]) ?><?= $p['embalagem'] ? ' · ' . e($p['embalagem']) : '' ?></span>
      <strong class="dp-pct" data-pct><?= (int) $jogo['progresso'] ?>% formado</strong></div>
    <span class="dp-barra grande"><i style="width: <?= (int) $jogo['progresso'] ?>%"></i></span>
    <?php if ($podeGerenciar && $p['situacao'] !== 'lancado'): ?>
      <details class="dp-personalizar"><summary>🎨 Embalagem e cores</summary>
        <form method="post" action="<?= $acao('tipo') ?>" class="pd-form" data-enviar-ao-mudar-form><?= csrf_campo() ?>
          <label>Tipo <select name="tipo" data-enviar-ao-mudar><?php foreach (\App\Services\DesenvolvimentoService::TIPOS as $slug => $nomeTipo): ?>
            <option value="<?= e($slug) ?>" <?= $slug === $jogo['tipo'] ? 'selected' : '' ?>><?= e($nomeTipo) ?></option><?php endforeach; ?></select></label>
          <?= \App\Core\Visao::renderizar('desenvolvimento/_cores', ['tipo' => $jogo['tipo'], 'cor' => $p['cor_embalagem'], 'tampa' => $p['cor_tampa']]) ?>
          <noscript><button class="botao contorno pequeno" type="submit">Salvar</button></noscript>
        </form>
      </details>
    <?php endif; ?>
  </div>
  <div class="dp-palco">
    <div class="dp-cena <?= e(implode(' ', $tem)) ?>" data-cena style="<?= e(\App\Services\DesenvolvimentoService::estiloCores($p)) ?>">
      <?= \App\Core\Visao::renderizar('desenvolvimento/_tipos', ['tipo' => $jogo['tipo'], 'classe' => 'reserva', 'arte' => $jogo['arte']]) ?>
    </div>
    <ol class="dp-baloes <?= $compacto ? 'compacto' : '' ?>" data-baloes style="--n: <?= count($linha) ?>">
      <?php foreach ($linha as $i => $e): $sit = $e['situacao'] ?: 'pendente'; ?>
        <li class="dp-balao <?= e($sit) ?>" style="--i: <?= $i ?>" data-checagem="<?= e((string) $e['checagem']) ?>">
          <?php $g = $comGaveta[(int) $e['id']] ?? null; if ($g && $g['aberta']): $f = $g['falta']; ?>
            <button type="button" data-abrir-etapa="<?= (int) $e['id'] ?>" aria-controls="dp-etapa-<?= (int) $e['id'] ?>">
              <b><?= e($e['nome']) ?></b><small><?= e($e['funcao'] ?: 'Gestão') ?></small>
              <?php if ($f && $compacto): ?><em class="dp-faltam"><?= count($f) ?> <?= count($f) === 1 ? 'coisa falta' : 'coisas faltam' ?></em>
              <?php elseif ($f): ?><ul><?php foreach (array_slice($f, 0, 3) as $x): ?><li><?= e($x) ?></li><?php endforeach; ?><?= count($f) > 3 ? '<li>…</li>' : '' ?></ul>
              <?php else: ?><em>Tudo pronto — concluir!</em><?php endif; ?>
              <span class="dp-balao-acao">Abrir etapa</span>
            </button>
          <?php elseif ($g): ?>
            <button type="button" data-abrir-etapa="<?= (int) $e['id'] ?>" aria-controls="dp-etapa-<?= (int) $e['id'] ?>" title="Ver ou corrigir">
              <b>✓ <?= e($e['nome']) ?></b>
              <small><?= e(trim(($e['concluida_por'] ?? '') . ' · ' . data_br($e['concluida_em']), ' ·')) ?></small><span class="dp-editar">editar</span>
            </button>
          <?php else: ?>
            <div><b><?= $sit === 'concluida' ? '✓ ' : '' ?><?= e($e['nome']) ?></b>
              <small><?= $sit === 'concluida' ? e(trim(($e['concluida_por'] ?? '') . ' · ' . data_br($e['concluida_em']), ' ·')) : e($e['funcao'] ?: 'Gestão') . ($sit === 'andamento' ? '' : ' · aguardando') ?></small></div>
          <?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ol>
  </div>
  <script type="application/json" id="dp-dados"><?= json_encode($jogo, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
</section>

<div class="dp-gavetas">
<?php foreach ($gavetas as $g): ?>
  <?= \App\Core\Visao::renderizar('desenvolvimento/_etapa', ['p' => $g['pe'], 'falta' => $g['falta'], 'aberta' => $g['aberta'], 'linhaEtapa' => $g['linha'],
      'podeConcluir' => $g['podeConcluir'], 'aprovacoes' => $g['aprovacoes'], 'artePdf' => $jogo['arte_pdf']] + compact('ficha', 'ordem', 'acao', 'aba', 'atalhos', 'tec')) ?>
<?php endforeach; ?>
</div>

<div class="ac-duas dp-duas">
  <section class="card"><h2>Briefing</h2>
    <p><?= $p['briefing'] ? nl2br(e($p['briefing'])) : '<span class="suave">Sem briefing.</span>' ?></p>
    <dl class="pd-dados"><div><dt>Apresentação</dt><dd><?= e($p['categoria'] ?? '—') ?></dd></div><div><dt>Embalagem</dt><dd><?= e($p['embalagem'] ?? '—') ?></dd></div>
      <?php if ($p['grafica']): ?><div><dt>Gráfica</dt><dd><?= e($p['grafica']) ?> · <?= data_br($p['grafica_pedido_em']) ?></dd></div><?php endif; ?></dl>
  </section>
  <section class="card"><h2>Histórico</h2>
    <form method="post" action="<?= $acao('comentar') ?>" class="pd-form dp-inline"><?= csrf_campo() ?>
      <input type="text" name="texto" maxlength="1000" placeholder="Escreva um comentário para a equipe…" aria-label="Comentário" required><button class="botao contorno pequeno" type="submit">Comentar</button></form>
    <ul class="ac-lista dp-historico"><?php foreach ($historico as $h): ?>
      <li class="<?= e($h['tipo']) ?>"><time><?= data_br($h['criado_em'], true) ?></time> <strong><?= e($h['usuario'] ?? 'Sistema') ?>:</strong> <?= e($h['texto']) ?></li>
    <?php endforeach; ?></ul>
  </section>
</div>
