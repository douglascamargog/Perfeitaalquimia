<?php
/** Comunicados. Variáveis: $pode (emitir), $recebidos, $emitidos, $funcoes */
$situacao = ['no ar' => 'verde', 'agendado' => 'laranja', 'vencido' => 'cinza', 'encerrado' => 'cinza'];
?>
<div class="cabecalho-secao">
  <div><div class="sobretitulo">EMPRESA · COMUNICADOS</div><h1>Comunicados</h1>
    <p>Recados da administração. Os novos aparecem em destaque no topo da tela até você clicar em "Li e entendi".</p></div>
</div>

<?php if ($pode): ?>
<div class="com-grade">
  <form method="post" action="<?= url('/comunicados') ?>" class="card" data-form-comunicado>
    <?= csrf_campo() ?>
    <h2>Novo comunicado</h2>
    <div class="campo"><label for="com-titulo">Título</label><input type="text" id="com-titulo" name="titulo" maxlength="140" required value="<?= antigo('titulo') ?>" placeholder="Ex.: Reunião geral sexta às 9h"></div>
    <div class="campo"><label for="com-texto">Mensagem</label><textarea id="com-texto" name="texto" rows="5" maxlength="5000" required><?= antigo('texto') ?></textarea></div>
    <div class="campo"><span class="rotulo-campo">Quem recebe</span>
      <label class="opcao"><input type="checkbox" name="toda_empresa" value="1" data-toda-empresa> <strong>Toda a empresa</strong></label>
      <div class="com-setores" data-setores>
        <?php foreach ($funcoes as $f): ?><label class="opcao"><input type="checkbox" name="funcoes[]" value="<?= (int) $f['id'] ?>"> <?= e($f['nome']) ?></label><?php endforeach; ?>
      </div>
      <div class="ajuda">Os setores são as funções de cada pessoa (Administração › Funções).</div></div>
    <div class="grade-campos">
      <div class="campo"><label for="com-prioridade">Prioridade</label><select id="com-prioridade" name="prioridade"><option value="normal">Normal</option><option value="importante">Importante (destaque em alerta)</option></select></div>
      <div class="campo"><label for="com-inicio">Começa em</label><input type="datetime-local" id="com-inicio" name="inicio"><div class="ajuda">Vazio = agora</div></div>
      <div class="campo"><label for="com-fim">Sai do topo em</label><input type="date" id="com-fim" name="fim"><div class="ajuda">Vazio = até cada um ler</div></div>
    </div>
    <div class="acoes-form"><button class="botao" type="submit">Enviar comunicado</button></div>
  </form>

  <section class="card sem-espaco">
    <h2 class="com-h2">Enviados</h2>
    <div class="tabela-rolagem">
      <table class="tabela" data-emitidos>
        <thead><tr><th>Comunicado</th><th>Para</th><th>Leitura</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($emitidos as $c): $pct = $c['total'] ? round($c['leram'] / $c['total'] * 100) : 0; ?>
          <tr data-comunicado="<?= (int) $c['id'] ?>">
            <td><strong><?= e($c['titulo']) ?></strong> <span class="selo <?= $situacao[$c['situacao']] ?>"><?= e($c['situacao']) ?></span><?= $c['prioridade'] === 'importante' ? ' <span class="selo vermelho">importante</span>' : '' ?>
              <small class="suave"><?= data_br($c['inicio'], true) ?><?= $c['fim'] ? ' até ' . data_br($c['fim']) : '' ?> · <?= e($c['autor'] ?? '') ?></small></td>
            <td class="pequeno"><?= e($c['setores']) ?></td>
            <td><div class="com-leitura"><div class="barra-meta clara"><span style="width: <?= $pct ?>%"></span></div>
              <small data-leitura><?= (int) $c['leram'] ?> de <?= (int) $c['total'] ?> leram</small>
              <?php if ($c['faltam']): ?><details><summary class="pequeno">quem falta</summary><small><?= e(implode(', ', $c['faltam'])) ?></small></details><?php endif; ?></div></td>
            <td><?php if (in_array($c['situacao'], ['no ar', 'agendado'], true)): ?>
              <form method="post" action="<?= url('/comunicados/' . (int) $c['id'] . '/encerrar') ?>" onsubmit="return confirm('Tirar este comunicado do topo da tela de todos?')"><?= csrf_campo() ?><button class="botao contorno pequeno" type="submit">Encerrar</button></form>
            <?php endif; ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$emitidos): ?><tr><td colspan="4" class="suave">Nenhum comunicado enviado ainda.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>
</div>
<script>
  // "Toda a empresa" desliga a escolha de setores
  (() => { const t = document.querySelector('[data-toda-empresa]'), s = document.querySelector('[data-setores]');
    const f = () => s.classList.toggle('desligado', t.checked); t.addEventListener('change', f); f(); })();
</script>
<?php endif; ?>

<section class="card">
  <h2>Recebidos</h2>
  <?php if (!$recebidos): ?><p class="suave">Nenhum comunicado ainda.</p><?php endif; ?>
  <div class="com-lista">
    <?php foreach ($recebidos as $c): ?>
      <article class="com-item <?= $c['lida_em'] ? 'lido' : '' ?> <?= $c['prioridade'] === 'importante' ? 'importante' : '' ?>" id="c<?= (int) $c['id'] ?>">
        <header><strong><?= e($c['titulo']) ?></strong><?= $c['lida_em'] ? '' : ' <span class="selo laranja">não lido</span>' ?>
          <small class="suave"><?= e($c['autor'] ?? 'Administração') ?> · <?= data_br($c['inicio'], true) ?></small></header>
        <div class="com-texto"><?= nl2br(e($c['texto'])) ?></div>
        <?php if (!$c['lida_em']): ?><button type="button" class="botao contorno pequeno" data-com-ler="<?= (int) $c['id'] ?>">Li e entendi</button><?php endif; ?>
      </article>
    <?php endforeach; ?>
  </div>
</section>
