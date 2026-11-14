<?php
/** Gestão › Carteiras. Variáveis: $carteiras, $setor, $setores, $vendedores */
$icones = asset('img/icones.svg');
$semVendedor = array_filter($carteiras, fn($k) => !$k['responsavel_id']);
$porSetor = [];
foreach ($carteiras as $k) {
    $porSetor[$k['setor'] ?? 'Sem setor'][] = $k;
}
?>
<div class="cabecalho-secao">
  <div><div class="sobretitulo">GESTÃO · CARTEIRAS</div><h1>Carteiras de clientes</h1>
    <p>A carteira de cada vendedor(a) e as que estão sem ninguém. Passe a carteira inteira quando alguém sai, mova clientes entre carteiras ou importe uma planilha.</p></div>
  <div class="acoes"><button class="botao" type="button" data-abrir="#nova-carteira"><svg class="icone"><use href="<?= $icones ?>#mais"/></svg>Nova carteira</button></div>
</div>

<?php if ($semVendedor): ?>
  <div class="aviso erro ct-alerta" data-sem-vendedor><strong><?= count($semVendedor) ?> carteira(s) sem vendedor(a)</strong> com
    <?= array_sum(array_column($semVendedor, 'clientes')) ?> cliente(s) parados. Passe para alguém da equipe.</div>
<?php endif; ?>

<form method="get" action="<?= url('/carteiras') ?>" class="chips ct-filtro">
  <a class="chip <?= !$setor ? 'ativo' : '' ?>" href="<?= url('/carteiras') ?>">Todos os setores</a>
  <?php foreach ($setores as $s): ?><a class="chip <?= (int) $setor === (int) $s['id'] ? 'ativo' : '' ?>" href="<?= url('/carteiras?setor=' . (int) $s['id']) ?>"><?= e($s['nome']) ?></a><?php endforeach; ?>
</form>

<?php foreach ($porSetor as $nomeSetor => $lista): ?>
  <h2 class="ct-setor"><?= e($nomeSetor) ?> <small><?= count($lista) ?> carteira(s) · <?= array_sum(array_column($lista, 'clientes')) ?> clientes</small></h2>
  <div class="ct-grade">
    <?php foreach ($lista as $k): ?>
      <article class="card ct-cartao <?= $k['responsavel_id'] ? '' : 'sem' ?>" data-carteira="<?= (int) $k['id'] ?>">
        <header>
          <a href="<?= url('/carteiras/' . (int) $k['id']) ?>"><strong><?= e($k['nome']) ?></strong></a>
          <?php if ($k['responsavel_id']): ?><span class="ct-vendedor"><svg class="icone"><use href="<?= $icones ?>#usuario"/></svg><?= e($k['vendedor']) ?></span>
          <?php else: ?><span class="selo vermelho">Sem vendedor(a)</span><?php endif; ?>
        </header>
        <dl class="ct-numeros">
          <div><dt>Clientes</dt><dd><?= (int) $k['clientes'] ?></dd></div>
          <div><dt>Compram</dt><dd><?= (int) $k['ativos'] ?></dd></div>
          <div><dt>Faturado 12m</dt><dd><?= dinheiro((float) $k['faturado']) ?></dd></div>
          <div><dt>Última compra</dt><dd><?= $k['ultima_compra'] ? data_br($k['ultima_compra']) : '—' ?></dd></div>
        </dl>
        <form method="post" action="<?= url('/carteiras/' . (int) $k['id'] . '/passar') ?>" class="ct-passar" data-confirmar="Passar a carteira &quot;<?= e($k['nome']) ?>&quot; (<?= (int) $k['clientes'] ?> clientes)?">
          <?= csrf_campo() ?>
          <select name="vendedor_id" aria-label="Passar a carteira <?= e($k['nome']) ?> para">
            <option value="">— Deixar sem vendedor(a)</option>
            <?php foreach ($vendedores as $v): ?><option value="<?= (int) $v['id'] ?>" <?= (int) $v['id'] === (int) $k['responsavel_id'] ? 'selected' : '' ?>><?= e($v['nome']) ?></option><?php endforeach; ?>
          </select>
          <button class="botao contorno pequeno" type="submit">Passar</button>
        </form>
        <a class="botao contorno pequeno" href="<?= url('/carteiras/' . (int) $k['id']) ?>">Abrir clientes, mover e importar →</a>
      </article>
    <?php endforeach; ?>
  </div>
<?php endforeach; ?>
<?php if (!$carteiras): ?><div class="card vazio"><p>Nenhuma carteira ainda.</p></div><?php endif; ?>

<dialog id="nova-carteira" class="ct-dialogo">
  <form method="post" action="<?= url('/carteiras') ?>" class="pd-form">
    <?= csrf_campo() ?>
    <h2>Nova carteira</h2>
    <label>Nome<input type="text" name="nome" maxlength="120" required placeholder="Ex.: Televendas · Carteira Serra Gaúcha"></label>
    <label>Setor<select name="funcao_id" required><?php foreach ($setores as $s): ?><option value="<?= (int) $s['id'] ?>"><?= e($s['nome']) ?></option><?php endforeach; ?></select></label>
    <label>Vendedor(a) <small>pode ficar sem ninguém por enquanto</small><select name="vendedor_id"><option value="">— Sem vendedor(a)</option>
      <?php foreach ($vendedores as $v): ?><option value="<?= (int) $v['id'] ?>"><?= e($v['nome']) ?></option><?php endforeach; ?></select></label>
    <div class="acoes-form"><button class="botao contorno" type="button" data-fechar>Cancelar</button><button class="botao" type="submit">Criar carteira</button></div>
  </form>
</dialog>
<script type="module" src="<?= asset('js/carteiras.js') ?>"></script>
