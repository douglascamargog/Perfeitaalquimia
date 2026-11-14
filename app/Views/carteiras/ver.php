<?php
/** Uma carteira: clientes (mover selecionados), passar, juntar, renomear, importar, exportar. Variáveis: $k, $busca, $pagina, $resultado, $outras, $vendedores, $setores */
$icones = asset('img/icones.svg');
$r = $resultado;
$link = fn(int $p) => url('/carteiras/' . (int) $k['id']) . '?' . e(http_build_query(array_filter(['q' => $busca, 'pagina' => $p > 1 ? $p : null])));
$opcoesCarteiras = fn() => implode('', array_map(fn($o) => '<option value="' . (int) $o['id'] . '">' . e($o['nome']) . ' — ' . e($o['vendedor'] ?? 'sem vendedor(a)') . '</option>', $outras));
?>
<a class="voltar-link" href="<?= url('/carteiras') ?>">← Todas as carteiras</a>
<div class="cabecalho-secao">
  <div><div class="sobretitulo">CARTEIRA · <?= e(mb_strtoupper((string) $k['setor'])) ?></div><h1><?= e($k['nome']) ?></h1>
    <p><?= $k['responsavel_id'] ? 'Vendedor(a): <strong>' . e($k['vendedor']) . '</strong>' : '<span class="selo vermelho">Sem vendedor(a)</span> os clientes ficam parados até alguém assumir.' ?>
      · <?= (int) $r['total'] ?> cliente(s)</p></div>
  <div class="acoes"><a class="botao contorno" href="<?= url('/carteiras/' . (int) $k['id'] . '/exportar.csv') ?>"><svg class="icone"><use href="<?= $icones ?>#baixar"/></svg>Exportar CSV</a></div>
</div>

<div class="ct-ferramentas">
  <form method="post" action="<?= url('/carteiras/' . (int) $k['id'] . '/passar') ?>" class="card pd-form" data-confirmar="Passar esta carteira inteira (<?= (int) $r['total'] ?> clientes)?">
    <?= csrf_campo() ?><h3>Passar a carteira inteira</h3>
    <select name="vendedor_id" aria-label="Passar a carteira para"><option value="">— Deixar sem vendedor(a)</option>
      <?php foreach ($vendedores as $v): ?><option value="<?= (int) $v['id'] ?>" <?= (int) $v['id'] === (int) $k['responsavel_id'] ? 'selected' : '' ?>><?= e($v['nome']) ?></option><?php endforeach; ?></select>
    <button class="botao" type="submit">Passar</button>
  </form>
  <form method="post" action="<?= url('/carteiras/' . (int) $k['id'] . '/importar') ?>" enctype="multipart/form-data" class="card pd-form">
    <?= csrf_campo() ?><h3>Importar para esta carteira</h3>
    <input type="file" name="arquivo" accept=".csv,.xlsx,.xml" required aria-label="Arquivo CSV, XLSX ou XML">
    <p class="ajuda suave">CSV, XLSX ou XML. Clientes que já existem (mesmo CNPJ) mudam para esta carteira; os novos são cadastrados nela. Você confere antes de gravar.</p>
    <button class="botao contorno" type="submit">Ver prévia</button>
  </form>
  <?php if ($outras): ?>
  <form method="post" action="<?= url('/carteiras/' . (int) $k['id'] . '/juntar') ?>" class="card pd-form" data-confirmar="Mover TODOS os clientes desta carteira para a escolhida? Esta carteira fica inativa.">
    <?= csrf_campo() ?><h3>Juntar com outra carteira</h3>
    <select name="destino_id" aria-label="Juntar com a carteira"><?= $opcoesCarteiras() ?></select>
    <button class="botao contorno" type="submit">Juntar</button>
  </form>
  <?php endif; ?>
  <form method="post" action="<?= url('/telefonia/discador') ?>" class="card pd-form" data-confirmar="Enviar os clientes desta carteira com telefone para o discador do Ligue Talk?">
    <?= csrf_campo() ?><h3>Ligue Talk</h3>
    <input type="hidden" name="carteira_id" value="<?= (int) $k['id'] ?>"><input type="hidden" name="voltar" value="/carteiras/<?= (int) $k['id'] ?>">
    <input type="text" name="lista" maxlength="100" value="<?= e($k['nome']) ?>" aria-label="Nome da lista no discador">
    <p class="ajuda suave">Manda os clientes com telefone para o discador como uma lista (duplicados são ignorados).</p>
    <button class="botao contorno" type="submit">Enviar para o discador</button>
  </form>
  <form method="post" action="<?= url('/carteiras/' . (int) $k['id']) ?>" class="card pd-form">
    <?= csrf_campo() ?><h3>Nome e setor</h3>
    <input type="text" name="nome" value="<?= e($k['nome']) ?>" maxlength="120" aria-label="Nome da carteira">
    <select name="funcao_id" aria-label="Setor"><?php foreach ($setores as $s): ?><option value="<?= (int) $s['id'] ?>" <?= (int) $s['id'] === (int) $k['funcao_id'] ? 'selected' : '' ?>><?= e($s['nome']) ?></option><?php endforeach; ?></select>
    <button class="botao contorno" type="submit">Salvar</button>
  </form>
</div>

<form method="get" action="<?= url('/carteiras/' . (int) $k['id']) ?>" class="card filtros-produtos">
  <div class="filtros-busca"><svg class="icone"><use href="<?= $icones ?>#lupa"/></svg>
    <input type="search" name="q" value="<?= e($busca) ?>" placeholder="Nome, cidade ou CNPJ" aria-label="Buscar cliente na carteira"></div>
  <button class="botao" type="submit">Buscar</button>
</form>

<form method="post" action="<?= url('/carteiras/' . (int) $k['id'] . '/mover') ?>" class="card sem-espaco" data-mover>
  <?= csrf_campo() ?>
  <div class="ct-mover">
    <label class="opcao"><input type="checkbox" data-todos> Marcar todos desta página</label>
    <?php if ($outras): ?>
      <span>Mover <b data-qtd-marcados>0</b> marcado(s) para</span>
      <select name="destino_id" aria-label="Mover para a carteira"><?= $opcoesCarteiras() ?></select>
      <button class="botao pequeno" type="submit" data-botao-mover disabled>Mover</button>
    <?php endif; ?>
  </div>
  <div class="tabela-rolagem"><table class="tabela">
    <thead><tr><th></th><th>Cliente</th><th>Cidade</th><th>CNPJ</th><th>Situação</th><th>Última compra</th></tr></thead>
    <tbody>
    <?php foreach ($r['itens'] as $c): ?>
      <tr><td><input type="checkbox" name="cliente[]" value="<?= (int) $c['id'] ?>" aria-label="Marcar <?= e($c['razao_social']) ?>"></td>
        <td><a href="<?= url('/carteira/clientes/' . (int) $c['id']) ?>"><?= e($c['nome_fantasia'] ?: $c['razao_social']) ?></a></td>
        <td><?= e($c['cidade']) ?>/<?= e($c['uf']) ?></td><td class="pequeno"><?= e(cnpj_formatado($c['cnpj'])) ?></td>
        <td><?= $c['tipo'] === 'cliente' ? 'Cliente' : 'Lead' ?></td><td><?= $c['ultima_compra_em'] ? data_br($c['ultima_compra_em']) : '—' ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$r['itens']): ?><tr><td colspan="6" class="suave">Nenhum cliente nesta carteira<?= $busca ? ' com essa busca' : '' ?>.</td></tr><?php endif; ?>
    </tbody>
  </table></div>
</form>
<?php if ($r['paginas'] > 1): ?>
  <nav class="paginacao"><?php if ($pagina > 1): ?><a class="botao contorno pequeno" href="<?= $link($pagina - 1) ?>">← Anterior</a><?php endif; ?>
    <span>Página <?= $pagina ?> de <?= $r['paginas'] ?></span>
    <?php if ($pagina < $r['paginas']): ?><a class="botao contorno pequeno" href="<?= $link($pagina + 1) ?>">Próxima →</a><?php endif; ?></nav>
<?php endif; ?>
