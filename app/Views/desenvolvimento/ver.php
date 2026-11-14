<?php
/**
 * Página do projeto. Variáveis: $p, $linha, $historico, $falta, $podeConcluir, $aprovacoes, $ficha, $ordem, $podeGerenciar
 */
$acao = fn(string $a) => url('/desenvolvimento/' . (int) $p['id'] . '/' . $a);
$aba = fn(string $a) => url('/produtos/' . e($p['produto_slug'])) . '?aba=' . $a;
$atalhos = ['ficha' => ['ficha', 'Preencher a ficha técnica do produto'], 'composicao' => ['composicao', 'Preencher a composição do produto'], 'arte' => ['documentos', 'Ver os documentos do produto']];
$tec = \App\Core\Banco::um('SELECT ean, ncm FROM produto_tecnico WHERE produto_id = ?', [$p['produto_id']]) ?: [];
$situacoes = ['andamento' => ['Em andamento', 'lima'], 'pausado' => ['Pausado', 'cinza'], 'lancado' => ['Lançado', 'lima'], 'cancelado' => ['Cancelado', 'vermelho']];
$ch = (string) $p['checagem'];
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

<ol class="dp-linha" aria-label="Etapas">
  <?php foreach ($linha as $e): ?>
    <li class="<?= e($e['situacao'] ?? 'pendente') ?>" title="<?= e($e['funcao'] ?: 'gestão') ?>">
      <b><?= (int) $e['ordem'] ?></b><span><?= e($e['nome']) ?></span>
      <small><?= $e['situacao'] === 'concluida' ? '✓ ' . e($e['concluida_por'] ?? '') . ' · ' . data_br($e['concluida_em']) : e($e['funcao'] ?: 'gestão') ?></small>
    </li>
  <?php endforeach; ?>
</ol>

<?php if ($p['situacao'] === 'andamento'): ?>
<section class="card dp-atual" data-etapa-atual="<?= e($ch) ?>">
  <span class="sobretitulo">ETAPA ATUAL · <?= e(mb_strtoupper((string) ($p['funcao'] ?: 'gestão do desenvolvimento'))) ?></span>
  <h2><?= e($p['etapa']) ?></h2>
  <?php if ($falta): ?><ul class="dp-falta" data-falta><?php foreach ($falta as $x): ?><li><?= e($x) ?></li><?php endforeach; ?></ul>
  <?php elseif ($ch !== 'lancamento'): ?><p class="dp-ok">Tudo pronto nesta etapa.</p><?php endif; ?>
  <?php if (isset($atalhos[$ch])): ?><p><a class="botao contorno pequeno" href="<?= $aba($atalhos[$ch][0]) ?>"><?= $atalhos[$ch][1] ?> →</a></p><?php endif; ?>

  <?php if ($ch === 'codigo'): ?>
    <form method="post" action="<?= $acao('codigo') ?>" class="pd-form dp-inline"><?= csrf_campo() ?>
      <label>Código de barras (EAN)<input type="text" name="ean" inputmode="numeric" maxlength="20" value="<?= e($tec['ean'] ?? '') ?>" required></label>
      <label>NCM<input type="text" name="ncm" inputmode="numeric" maxlength="12" value="<?= e($tec['ncm'] ?? '') ?>" placeholder="0000.00.00" required></label>
      <button class="botao contorno" type="submit">Salvar código</button></form>
  <?php elseif ($ch === 'arte'): ?>
    <form method="post" action="<?= $acao('arte') ?>" enctype="multipart/form-data" class="pd-form dp-inline"><?= csrf_campo() ?>
      <label>Arte do rótulo (PDF, JPG ou PNG)<input type="file" name="arquivo" accept="application/pdf,image/jpeg,image/png" required></label>
      <button class="botao contorno" type="submit">Enviar arte</button></form>
  <?php elseif ($ch === 'compras'): ?>
    <ul class="dp-compras"><?php foreach ($ficha as $it): $ok = in_array((int) $it['materia_prima_id'], array_map('intval', $p['compras_ok']), true); ?>
      <li><form method="post" action="<?= $acao('compra') ?>"><?= csrf_campo() ?><input type="hidden" name="mp" value="<?= (int) $it['materia_prima_id'] ?>"><input type="hidden" name="comprado" value="<?= $ok ? '0' : '1' ?>">
        <button type="submit" class="dp-check <?= $ok ? 'ok' : '' ?>" aria-label="<?= $ok ? 'Desmarcar' : 'Marcar como comprado' ?>"><?= $ok ? '✓' : '' ?></button></form>
        <?= e($it['nome']) ?> <small class="suave"><?= $it['tipo'] === 'embalagem' ? 'embalagem' : 'matéria-prima' ?> · estoque <?= rtrim(rtrim(number_format((float) $it['estoque'], 3, ',', '.'), '0'), ',') ?> <?= e($it['unidade']) ?></small></li>
    <?php endforeach; ?></ul>
    <p class="pequeno suave">Registre a entrada da compra em <a href="<?= url('/producao/materias') ?>">Produção › Matérias-primas</a>.</p>
  <?php elseif ($ch === 'grafica'): ?>
    <form method="post" action="<?= $acao('grafica') ?>" class="pd-form dp-inline"><?= csrf_campo() ?>
      <label>Gráfica<input type="text" name="grafica" value="<?= e($p['grafica']) ?>" maxlength="160" required></label>
      <label>Pedido enviado em<input type="date" name="data" value="<?= e($p['grafica_pedido_em'] ?: date('Y-m-d')) ?>" required></label>
      <button class="botao contorno" type="submit">Salvar pedido</button></form>
  <?php elseif ($ch === 'aprovacao'): ?>
    <form method="post" action="<?= $acao('prova') ?>" enctype="multipart/form-data" class="pd-form dp-inline"><?= csrf_campo() ?>
      <label>Prova que a gráfica mandou (PDF ou imagem)<input type="file" name="arquivo" accept="application/pdf,image/jpeg,image/png" required></label>
      <button class="botao contorno" type="submit">Anexar prova</button></form>
    <p>Aprovações desta prova: <?= $aprovacoes ? e(implode(', ', array_map(fn($a) => $a['usuario'] . ' (' . $a['funcao_slug'] . ')', $aprovacoes))) : 'nenhuma ainda' ?>.</p>
    <form method="post" action="<?= $acao('aprovar') ?>" class="pd-form" data-aprovar><?= csrf_campo() ?>
      <label>Comentário <small>obrigatório para pedir ajuste</small><textarea name="comentario" rows="2"></textarea></label>
      <div class="acoes-form"><button class="botao" type="submit" name="decisao" value="aprovado">Aprovar a prova</button>
        <button class="botao contorno" type="submit" name="decisao" value="ajustar">Pedir ajuste</button></div></form>
  <?php elseif ($ch === 'recebimento'): ?>
    <p>Recebidos até agora: <strong data-recebidos><?= number_format((int) $p['rotulos_recebidos'], 0, ',', '.') ?></strong></p>
    <form method="post" action="<?= $acao('rotulos') ?>" class="pd-form dp-inline"><?= csrf_campo() ?>
      <label>Rótulos que chegaram<input type="number" name="quantidade" min="1" required></label>
      <button class="botao contorno" type="submit"><?= $p['cliente'] ? 'Registrar recebimento' : 'Dar entrada no estoque' ?></button></form>
  <?php elseif ($ch === 'piloto'): ?>
    <?php if ($ordem): ?><p>Ordem <a href="<?= url('/producao/ordens/' . (int) $ordem['id']) ?>">OP <?= (int) $ordem['id'] ?></a> · <?= (int) $ordem['quantidade'] ?> un · <strong><?= e($ordem['situacao']) ?></strong></p>
    <?php else: ?>
      <form method="post" action="<?= $acao('piloto') ?>" class="pd-form dp-inline"><?= csrf_campo() ?>
        <label>Quantidade do lote piloto<input type="number" name="quantidade" min="1" value="50" required></label>
        <button class="botao contorno" type="submit">Criar ordem de produção</button></form>
    <?php endif; ?>
  <?php elseif ($ch === 'lancamento'): ?>
    <p><?= $p['cliente'] ? 'Ao concluir, o produto vira um produto exclusivo de <strong>' . e($p['cliente']) . '</strong> (Terceirização), com os rótulos recebidos.' : 'Ao concluir, o produto fica ativo e aparece no <strong>catálogo</strong> e na <strong>Academia</strong>.' ?></p>
  <?php endif; ?>

  <?php if ($podeConcluir && $ch !== 'aprovacao'): ?>
    <form method="post" action="<?= $acao('concluir') ?>" class="pd-form dp-concluir"><?= csrf_campo() ?>
      <label>Observação <small>opcional</small><input type="text" name="observacao" maxlength="500"></label>
      <?php if ($ch === 'piloto'): ?><label class="caixa-marcar"><input type="checkbox" name="manual" value="1"> Concluir sem ordem no sistema (explique na observação)</label><?php endif; ?>
      <button class="botao lima" type="submit" <?= $falta && $ch !== 'piloto' ? 'disabled title="Complete o que falta"' : '' ?>><?= $ch === 'lancamento' ? 'Lançar o produto' : 'Concluir etapa' ?></button>
    </form>
  <?php elseif (!$podeConcluir && $ch !== 'aprovacao'): ?>
    <p class="pequeno suave">Esta etapa é concluída por <?= e($p['funcao'] ?: 'quem gerencia o desenvolvimento') ?>.</p>
  <?php endif; ?>
</section>
<?php endif; ?>

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
