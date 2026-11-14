<?php
/**
 * Gaveta de uma etapa (formulários e o botão Concluir). Uma por etapa aberta — várias ao mesmo tempo — e pelas
 * concluídas que ainda dá para editar (ficha, rotulagem, arte, compras, código, gráfica...).
 * Na página do projeto abre ao clicar no balão (dp-jogo.js); sem JS fica visível na página.
 * Variáveis: $p (projeto visto nesta etapa), $falta, $aberta, $linhaEtapa, $podeConcluir, $aprovacoes, $ficha, $ordem,
 *            $acao, $aba, $atalhos, $tec
 */
$ch = (string) $p['checagem'];
?>
<section class="card dp-atual" id="dp-etapa-<?= (int) $p['etapa_id'] ?>" data-etapa="<?= (int) $p['etapa_id'] ?>" data-etapa-atual="<?= e($ch) ?>">
  <span class="sobretitulo"><?= $aberta ? 'ETAPA EM ANDAMENTO' : 'ETAPA CONCLUÍDA' ?> · <?= e(mb_strtoupper((string) ($p['funcao'] ?: 'gestão do desenvolvimento'))) ?></span>
  <h2><?= e($p['etapa']) ?></h2>
  <?php if (!$aberta): ?><p class="dp-ok">✓ Concluída por <?= e($linhaEtapa['concluida_por'] ?? '—') ?> em <?= data_br($linhaEtapa['concluida_em']) ?>. Você ainda pode corrigir as informações abaixo.</p>
  <?php elseif ($falta): ?><ul class="dp-falta" data-falta><?php foreach ($falta as $x): ?><li><?= e($x) ?></li><?php endforeach; ?></ul>
  <?php elseif ($ch !== 'lancamento'): ?><p class="dp-ok">Tudo pronto nesta etapa.</p><?php endif; ?>
  <?php if (isset($atalhos[$ch])): ?><p><a class="botao contorno pequeno" href="<?= $aba($atalhos[$ch][0]) ?>"><?= $atalhos[$ch][1] ?> →</a></p><?php endif; ?>

  <?php if ($ch === 'codigo'): ?>
    <form method="post" action="<?= $acao('codigo') ?>" class="pd-form dp-inline"><?= csrf_campo() ?>
      <label>Código de barras (EAN)<input type="text" name="ean" inputmode="numeric" maxlength="20" value="<?= e($tec['ean'] ?? '') ?>" required></label>
      <label>NCM<input type="text" name="ncm" inputmode="numeric" maxlength="12" value="<?= e($tec['ncm'] ?? '') ?>" placeholder="0000.00.00" required></label>
      <button class="botao contorno" type="submit">Salvar código</button></form>
  <?php elseif ($ch === 'arte'): ?>
    <form method="post" action="<?= $acao('arte') ?>" enctype="multipart/form-data" class="pd-form dp-inline"><?= csrf_campo() ?>
      <label>Arte do rótulo <small>PNG ou JPG: já aparece envolvendo a embalagem 3D (a arte inteira, planificada)</small><input type="file" name="arquivo" accept="image/png,image/jpeg,application/pdf" required></label>
      <button class="botao contorno" type="submit">Enviar arte</button></form>
    <?php if (!empty($artePdf)): ?><p class="pequeno aviso-texto">A arte foi enviada em PDF. Para aparecer na embalagem 3D, envie também em PNG ou JPG.</p><?php endif; ?>
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
  <?php elseif ($ch === 'aprovacao' && $aberta): ?>
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

  <?php if ($aberta && $podeConcluir && $ch !== 'aprovacao'): ?>
    <form method="post" action="<?= $acao('concluir') ?>" class="pd-form dp-concluir"><?= csrf_campo() ?><input type="hidden" name="etapa" value="<?= (int) $p['etapa_id'] ?>">
      <label>Observação <small>opcional</small><input type="text" name="observacao" maxlength="500"></label>
      <?php if ($ch === 'piloto'): ?><label class="caixa-marcar"><input type="checkbox" name="manual" value="1"> Concluir sem ordem no sistema (explique na observação)</label><?php endif; ?>
      <button class="botao lima" type="submit" <?= $falta && $ch !== 'piloto' ? 'disabled title="Complete o que falta"' : '' ?>><?= $ch === 'lancamento' ? 'Lançar o produto' : 'Concluir etapa' ?></button>
    </form>
  <?php elseif ($aberta && $ch !== 'aprovacao'): ?>
    <p class="pequeno suave">Esta etapa é concluída por <?= e($p['funcao'] ?: 'quem gerencia o desenvolvimento') ?>.</p>
  <?php endif; ?>
</section>
