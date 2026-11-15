<?php
/**
 * Página do item comprado (matéria-prima, embalagem, rótulo ou uso e consumo), em abas:
 * Resumo (estoque, dura em média, recorrência, gastos) · Fornecedores · Compras (NFs) · Documentos · Estoque (movimentos).
 * Variáveis: $m (com previsão), $aba, $movimentos, $unidades, $produtos, $fornecedores, $documentos, $compras, $numeros, $categorias, $pode, $podeCadastro
 */
use App\Core\Visao;
use App\Services\ItemCompraService as I;
use App\Services\ProducaoService as P;

$u = $m['unidade'];
$id = (int) $m['id'];
$num = fn($valor) => rtrim(rtrim(number_format((float) $valor, 4, ',', ''), '0'), ',');
$n = $numeros;
$base = url('/producao/materias/' . $id);
$principal = current(array_filter($fornecedores, fn($f) => $f['principal'])) ?: null;
$menorPreco = $fornecedores ? min(array_map(fn($f) => $f['ultimo_preco'] !== null ? (float) $f['ultimo_preco'] : PHP_FLOAT_MAX, $fornecedores)) : null;
$docAlerta = count(array_filter($documentos, fn($d) => in_array(I::situacaoValidade($d['validade']), ['vencido', 'vencendo'], true)));
$abas = ['resumo' => 'Resumo', 'fornecedores' => 'Fornecedores (' . count($fornecedores) . ')', 'compras' => 'Compras (' . count($compras) . ')',
    'documentos' => 'Documentos (' . count($documentos) . ')' . ($docAlerta ? ' ⚠' : ''), 'estoque' => 'Estoque'];
$pctEstoque = $m['estoque_minimo'] > 0 ? min(100, $m['estoque'] / ($m['estoque_minimo'] * 2) * 100) : 100;
$voltar = $m['tipo'] === 'consumo' ? ['/financeiro/compras?aba=itens', 'COMPRAS DA EMPRESA'] : ['/producao/materias', 'MATÉRIAS-PRIMAS'];
// Formulários de estoque (entrada de compra e ajuste): Produção
$formsEstoque = function () use ($pode, $m, $u, $num, $fornecedores, $id) {
    if (!$pode) {
        return '';
    }
    ob_start(); ?>
    <div class="it-duas">
      <section class="card it-form">
        <h2>Entrada de compra</h2>
        <form method="post" action="<?= url('/producao/materias/' . $id . '/entrada') ?>" enctype="multipart/form-data" data-form-entrada>
          <?= csrf_campo() ?>
          <div class="grade-campos">
            <div class="campo"><label for="e-qtd">Quantidade (<?= e($u) ?>)</label><input type="text" id="e-qtd" name="quantidade" inputmode="decimal" required></div>
            <div class="campo"><label for="e-custo">Custo por <?= e($u) ?> (R$)</label><input type="text" id="e-custo" name="custo_unitario" inputmode="decimal" value="<?= $num($m['custo_unitario']) ?>"></div>
            <div class="campo"><label for="e-forn">Fornecedor <small>opcional</small></label><input type="text" id="e-forn" name="fornecedor" maxlength="160" list="forn-<?= $id ?>">
              <datalist id="forn-<?= $id ?>"><?php foreach ($fornecedores as $f): ?><option value="<?= e($f['fornecedor']) ?>"><?php endforeach; ?></datalist></div>
            <div class="campo"><label for="e-nota">Nº da nota fiscal</label><input type="text" id="e-nota" name="nota" maxlength="60"></div>
          </div>
          <div class="grade-campos">
            <div class="campo"><label for="e-arq">Arquivo da NF <small>PDF, imagem ou XML</small></label><input type="file" id="e-arq" name="arquivo_nf" accept="application/pdf,image/*,.xml,text/xml"></div>
            <div class="campo"><label for="e-obs">Observação</label><input type="text" id="e-obs" name="observacao" maxlength="200"></div>
          </div>
          <button class="botao" type="submit">Registrar entrada</button>
        </form>
      </section>
      <section class="card it-form">
        <h2>Ajuste de inventário</h2>
        <form method="post" action="<?= url('/producao/materias/' . $id . '/ajuste') ?>" data-form-ajuste>
          <?= csrf_campo() ?>
          <div class="grade-campos">
            <div class="campo"><label for="a-contagem">Quantidade contada (<?= e($u) ?>)</label><input type="text" id="a-contagem" name="contagem" inputmode="decimal" required></div>
            <div class="campo"><label for="a-motivo">Motivo</label><input type="text" id="a-motivo" name="motivo" maxlength="200" placeholder="contagem, perda, vencido…"></div>
          </div>
          <p class="ajuda suave">O sistema grava a diferença entre a contagem e o estoque atual (<?= P::qtd($m['estoque'], $u) ?>).</p>
          <button class="botao contorno" type="submit">Ajustar estoque</button>
        </form>
      </section>
    </div>
    <?php return ob_get_clean();
};
?>
<div class="cabecalho-secao">
  <div><div class="sobretitulo"><a href="<?= url($voltar[0]) ?>"><?= $voltar[1] ?></a> · <?= e(mb_strtoupper(I::tipoDe($m))) ?><?= $m['categoria'] ? ' · ' . e(mb_strtoupper($m['categoria'])) : '' ?> · <?= e($m['codigo'] ?: 'SEM CÓDIGO') ?></div>
    <h1><?= e($m['nome']) ?></h1>
    <p><?= $principal ? 'Fornecedor principal: <strong>' . e($principal['fornecedor']) . '</strong>' . (count($fornecedores) > 1 ? ' · +' . (count($fornecedores) - 1) . ' fornecedor(es)' : '') : 'Nenhum fornecedor cadastrado' ?>
      <?= $m['ativo'] ? '' : ' · <strong>inativo</strong>' ?><?= $m['omie_codigo'] ? ' · <span class="selo cinza">ligado ao Omie</span>' : '' ?></p></div>
</div>
<?php if ($m['tipo'] !== 'consumo'): ?><?= Visao::renderizar('producao/_abas', ['atual' => '/producao/materias']) ?><?php endif; ?>

<div class="it-numeros">
  <div class="card it-num escuro"><span>Em estoque</span><strong data-estoque><?= P::qtd($m['estoque'], $u) ?></strong>
    <div class="it-barra <?= $m['abaixo'] ? 'baixo' : '' ?>"><i style="width: <?= round($pctEstoque) ?>%"></i></div>
    <small>mínimo <?= P::qtd($m['estoque_minimo'], $u) ?><?= $m['abaixo'] ? ' · <b>abaixo do mínimo</b>' : '' ?></small></div>
  <?php if ($m['dias_restantes'] !== null || $m['tipo'] !== 'consumo' || !$n['intervalo']): ?>
  <div class="card it-num <?= $m['acabando'] ? 'alerta' : '' ?>"><span>Acaba em</span><strong data-previsao><?= $m['dias_restantes'] !== null ? '~' . (int) $m['dias_restantes'] . ' dias' : '—' ?></strong>
    <small><?= $m['acaba_em'] ? data_br($m['acaba_em']) . ' · consumo ' . P::qtd(round($m['consumo_dia'], 3), $u) . '/dia' : 'sem consumo registrado' ?></small></div>
  <?php else: ?>
  <div class="card it-num" data-dura><span>Dura em média</span><strong>~<?= (int) $n['intervalo'] ?> dias</strong><small>uma compra costuma durar isso (intervalo entre as compras)</small></div>
  <?php endif; ?>
  <div class="card it-num" data-recorrencia><span>Compra a cada</span><strong><?= $n['intervalo'] ? '~' . $n['intervalo'] . ' dias' : '—' ?></strong>
    <small><?= $n['proxima'] ? 'próxima compra prevista ' . data_br($n['proxima']) : ($n['ultima_compra'] ? 'última compra ' . data_br($n['ultima_compra']) : 'sem compras lançadas') ?></small></div>
  <div class="card it-num lima"><span>Último preço</span><strong><?= dinheiro((float) $m['custo_unitario']) ?></strong>
    <small>por <?= e($u) ?><?= $n['preco_medio'] ? ' · média 12 meses ' . dinheiro($n['preco_medio']) : '' ?></small></div>
  <div class="card it-num"><span>Gasto em 12 meses</span><strong><?= dinheiro($n['gasto_12m']) ?></strong><small><?= (int) $n['compras_12m'] ?> compra(s)</small></div>
</div>

<nav class="abas it-abas" aria-label="Item">
  <?php foreach ($abas as $k => $nome): ?><a class="<?= $k === $aba ? 'ativa' : '' ?>" href="<?= $base ?>?aba=<?= $k ?>" data-aba-item="<?= $k ?>"><?= e($nome) ?></a><?php endforeach; ?>
</nav>

<?php if ($aba === 'resumo'): ?>
  <div class="it-duas">
    <section class="card">
      <h2>Compras por mês <small class="suave">últimos 12 meses</small></h2>
      <?php $nomesMes = ['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez']; $meses = [];
        for ($i = 11; $i >= 0; $i--) { $mm = date('Y-m', strtotime(date('Y-m-01') . " -$i months"));
          $meses[] = ['dia' => $mm . '-01', 'rotulo' => $nomesMes[(int) substr($mm, 5) - 1], 'dica' => $nomesMes[(int) substr($mm, 5) - 1] . '/' . substr($mm, 0, 4), 'valores' => [$n['por_mes'][$mm] ?? 0]]; } ?>
      <?= Visao::renderizar('graficos/_barras_dia', ['dias' => $meses, 'series' => [['nome' => 'Comprado', 'classe' => 's1']], 'dinheiro' => true]) ?>
      <p class="pequeno suave">Passe o mouse na barra para ver o valor do mês.</p>
    </section>
    <section class="card">
      <h2>Fornecedores</h2>
      <?php if (!$fornecedores): ?><p class="suave">Nenhum fornecedor ainda. <a href="<?= $base ?>?aba=fornecedores">Cadastrar</a></p><?php endif; ?>
      <ul class="it-lista">
        <?php foreach (array_slice($fornecedores, 0, 4) as $f): ?>
          <li><strong><?= e($f['fornecedor']) ?></strong><?= $f['principal'] ? ' <span class="selo lima">principal</span>' : '' ?>
            <small><?= $f['ultimo_preco'] !== null ? dinheiro((float) $f['ultimo_preco']) . '/' . e($u) : 'sem preço' ?><?= $f['ultima_compra_em'] ? ' · ' . data_br($f['ultima_compra_em']) : '' ?></small></li>
        <?php endforeach; ?>
      </ul>
      <?php if ($m['tipo'] !== 'consumo'): ?>
        <h2 class="it-sub">Usada em</h2>
        <?php if (!$produtos): ?><p class="suave pequeno">Nenhuma ficha técnica usa este item.</p><?php endif; ?>
        <ul class="it-lista">
          <?php foreach ($produtos as $p): ?><li><a href="<?= url('/producao/fichas/' . (int) $p['id']) ?>"><strong><?= e($p['nome']) ?></strong></a><small><?= P::qtd((float) $p['quantidade'], $u) ?> por unidade</small></li><?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>
  </div>
  <?= $formsEstoque() ?>

<?php elseif ($aba === 'fornecedores'): ?>
  <section class="card sem-espaco">
    <div class="tabela-rolagem"><table class="tabela" data-fornecedores>
      <thead><tr><th>Fornecedor</th><th>Contato</th><th class="direita">Último preço</th><th>Última compra</th><th>Prazo</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($fornecedores as $f): $barato = $f['ultimo_preco'] !== null && abs((float) $f['ultimo_preco'] - $menorPreco) < 0.00005 && count($fornecedores) > 1; ?>
        <tr data-fornecedor="<?= (int) $f['id'] ?>">
          <td><?php if (\App\Core\Auth::pode('compras.itens') || \App\Core\Auth::pode('compras.ver')): ?><a href="<?= url('/financeiro/fornecedores/ver') ?>?nome=<?= e(rawurlencode($f['fornecedor'])) ?>" title="Ver tudo deste fornecedor"><strong><?= e($f['fornecedor']) ?></strong></a><?php else: ?><strong><?= e($f['fornecedor']) ?></strong><?php endif; ?><?= $f['principal'] ? ' <span class="selo lima">principal</span>' : '' ?><?= $barato ? ' <span class="selo verde" data-menor-preco>menor preço</span>' : '' ?>
            <small class="suave" style="display:block"><?= e(trim(($f['cnpj'] ?? '') . ($f['codigo_no_fornecedor'] ? ' · cód. ' . $f['codigo_no_fornecedor'] : ''), ' ·')) ?></small></td>
          <td class="pequeno"><?= e(implode(' · ', array_filter([$f['contato'], $f['telefone'], $f['email']]))) ?: '—' ?></td>
          <td class="direita"><?= $f['ultimo_preco'] !== null ? dinheiro((float) $f['ultimo_preco']) . '/' . e($u) : '—' ?></td>
          <td class="pequeno"><?= $f['ultima_compra_em'] ? data_br($f['ultima_compra_em']) : '—' ?></td>
          <td class="pequeno"><?= $f['prazo_dias'] !== null ? (int) $f['prazo_dias'] . ' dia(s)' : '—' ?></td>
          <td class="it-acoes"><?php if ($podeCadastro): ?>
            <?php if (!$f['principal']): ?><form method="post" action="<?= $base ?>/fornecedores/<?= (int) $f['id'] ?>/principal"><?= csrf_campo() ?><button class="botao contorno pequeno" type="submit" data-principal>Tornar principal</button></form><?php endif; ?>
            <details class="it-editar"><summary class="botao contorno pequeno">Editar</summary>
              <form method="post" action="<?= $base ?>/fornecedores/<?= (int) $f['id'] ?>" class="it-form-forn"><?= csrf_campo() ?><?= Visao::renderizar('producao/_fornecedor_campos', ['f' => $f]) ?><button class="botao pequeno" type="submit">Salvar</button></form></details>
            <form method="post" action="<?= $base ?>/fornecedores/<?= (int) $f['id'] ?>/remover" data-confirmar="Tirar este fornecedor da lista?"><?= csrf_campo() ?><button class="botao contorno pequeno" type="submit">Tirar</button></form>
          <?php endif; ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$fornecedores): ?><tr><td colspan="6" class="suave">Nenhum fornecedor ainda.</td></tr><?php endif; ?>
      </tbody>
    </table></div>
  </section>
  <?php if ($podeCadastro): ?>
    <section class="card">
      <h2>Adicionar fornecedor</h2>
      <form method="post" action="<?= $base ?>/fornecedores" class="it-form-forn" data-novo-fornecedor><?= csrf_campo() ?><?= Visao::renderizar('producao/_fornecedor_campos', ['f' => null]) ?><button class="botao" type="submit">Adicionar</button></form>
      <p class="pequeno suave">O último preço e a data da compra se atualizam sozinhos a cada compra lançada com este fornecedor.</p>
    </section>
  <?php endif; ?>

<?php elseif ($aba === 'compras'): ?>
  <section class="card sem-espaco">
    <div class="tabela-rolagem"><table class="tabela" data-compras-item>
      <thead><tr><th>Data</th><th>Fornecedor</th><th>NF</th><th class="direita">Quantidade</th><th class="direita">Preço</th><th class="direita">Total</th></tr></thead>
      <tbody>
      <?php foreach ($compras as $c): ?>
        <tr><td><?= data_br($c['emissao']) ?></td><td><?= e($c['fornecedor']) ?><small class="suave" style="display:block"><?= e(\App\Services\CompraLancamentoService::ORIGENS[$c['origem']] ?? $c['origem']) ?></small></td>
          <td><a href="<?= url('/financeiro/compras/' . (int) $c['lancamento']) ?>"><?= e($c['numero_nf'] ?: 'ver') ?></a><?= $c['arquivo'] ? ' 📎' : '' ?></td>
          <td class="direita"><?= P::qtd((float) $c['quantidade'], $u) ?></td><td class="direita"><?= dinheiro((float) $c['valor_unitario']) ?></td><td class="direita"><strong><?= dinheiro((float) $c['total']) ?></strong></td></tr>
      <?php endforeach; ?>
      <?php if (!$compras): ?><tr><td colspan="6" class="suave">Nenhuma compra lançada ainda. As compras entram por Financeiro › Compras da empresa, pelas notas do Omie ou pela entrada de compra com fornecedor.</td></tr><?php endif; ?>
      </tbody>
    </table></div>
  </section>

<?php elseif ($aba === 'documentos'): ?>
  <section class="card sem-espaco">
    <div class="tabela-rolagem"><table class="tabela" data-documentos-item>
      <thead><tr><th>Documento</th><th>Tipo</th><th>Lote</th><th>Validade</th><th>Enviado</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($documentos as $d): $sit = I::situacaoValidade($d['validade']); ?>
        <tr class="<?= $sit === 'vencido' ? 'it-vencido' : '' ?>" data-documento="<?= (int) $d['id'] ?>">
          <td><a href="<?= url('/producao/materias/documento/' . (int) $d['id']) ?>" target="_blank" rel="noopener"><strong><?= e($d['titulo']) ?></strong></a><small class="suave" style="display:block"><?= e($d['nome_original']) ?><?= $d['fornecedor'] ? ' · ' . e($d['fornecedor']) : '' ?></small></td>
          <td><span class="selo <?= $d['tipo'] === 'nf' ? 'cinza' : 'lima' ?>"><?= e(I::TIPOS_DOCUMENTO[$d['tipo']] ?? $d['tipo']) ?></span></td>
          <td class="pequeno"><?= e((string) $d['lote']) ?: '—' ?></td>
          <td><?= $d['validade'] ? data_br($d['validade']) : '—' ?><?= $sit === 'vencido' ? ' <span class="selo vermelho">vencido</span>' : ($sit === 'vencendo' ? ' <span class="selo laranja">vencendo</span>' : '') ?></td>
          <td class="pequeno"><?= data_br($d['criado_em']) ?><?= $d['enviado_por_nome'] ? ' · ' . pessoa($d['enviado_por_nome'] ?? null, null, false, 18, '—') : '' ?></td>
          <td><?php if ($podeCadastro): ?><form method="post" action="<?= $base ?>/documentos/<?= (int) $d['id'] ?>/arquivar" data-confirmar="Arquivar este documento?"><?= csrf_campo() ?><button class="botao contorno pequeno" type="submit">Arquivar</button></form><?php endif; ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$documentos): ?><tr><td colspan="6" class="suave">Nenhum documento ainda.</td></tr><?php endif; ?>
      </tbody>
    </table></div>
  </section>
  <?php if ($podeCadastro): ?>
    <section class="card">
      <h2>Enviar documento</h2>
      <form method="post" action="<?= $base ?>/documentos" enctype="multipart/form-data" class="it-form-forn" data-enviar-documento><?= csrf_campo() ?>
        <label>Tipo<select name="tipo"><?php foreach (I::TIPOS_DOCUMENTO as $k => $nome): ?><option value="<?= $k ?>"><?= e($nome) ?></option><?php endforeach; ?></select></label>
        <label>Título <small>opcional</small><input type="text" name="titulo" maxlength="160" placeholder="Ex.: Laudo lote 2026/11"></label>
        <label>Lote<input type="text" name="lote" maxlength="40"></label>
        <label>Validade<input type="date" name="validade"></label>
        <label>Fornecedor<select name="fornecedor_id"><option value="">—</option><?php foreach ($fornecedores as $f): ?><option value="<?= (int) $f['id'] ?>"><?= e($f['fornecedor']) ?></option><?php endforeach; ?></select></label>
        <label>Arquivo <small>PDF, imagem ou XML</small><input type="file" name="arquivo" accept="application/pdf,image/*,.xml,text/xml" required></label>
        <button class="botao" type="submit">Enviar</button>
      </form>
      <p class="pequeno suave">Documentos com validade avisam quando estão vencendo (Configurações › Produtos: dias de antecedência). As NFs das compras entram aqui sozinhas.</p>
    </section>
  <?php endif; ?>

<?php else: ?>
  <?= $formsEstoque() ?>
  <section class="card sem-espaco">
    <h2 class="pr-titulo-tabela">Histórico de movimentos <small class="suave">últimos 100</small></h2>
    <div class="tabela-rolagem">
      <table class="tabela pr-tabela" data-movimentos>
        <thead><tr><th>Data</th><th>Tipo</th><th class="direita">Quantidade</th><th class="direita">Saldo</th><th>Referência</th><th>Quem</th></tr></thead>
        <tbody>
        <?php foreach ($movimentos as $mv): $q = (float) $mv['quantidade']; ?>
          <tr>
            <td class="pequeno"><?= data_br($mv['criado_em'], true) ?></td>
            <td><span class="selo <?= ['entrada' => 'verde', 'consumo' => 'cinza', 'ajuste' => 'laranja'][$mv['tipo']] ?? 'cinza' ?>"><?= P::TIPOS_MP[$mv['tipo']] ?? e($mv['tipo']) ?></span></td>
            <td class="direita <?= $q < 0 ? 'pn-vermelho' : 'pn-verde' ?>"><?= ($q > 0 ? '+' : '') . P::qtd($q, $u) ?></td>
            <td class="direita"><?= P::qtd((float) $mv['saldo'], $u) ?></td>
            <td class="pequeno"><?php if ($mv['ordem_id']): ?><a href="<?= url('/producao/ordens/' . (int) $mv['ordem_id']) ?>"><?= e($mv['referencia']) ?></a><?php else: ?><?= e($mv['referencia'] ?? '') ?><?php endif; ?>
              <?= $mv['custo_unitario'] ? ' · ' . dinheiro((float) $mv['custo_unitario']) . '/' . e($u) : '' ?><?= $mv['observacao'] ? '<br><span class="suave">' . e($mv['observacao']) . '</span>' : '' ?></td>
            <td class="pequeno"><?= pessoa($mv['usuario'] ?? null, null, false, 18, '—') ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$movimentos): ?><tr><td colspan="6" class="vazio">Nenhum movimento ainda.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>
<?php endif; ?>

<?php if ($podeCadastro): ?>
  <details class="card it-cadastro"><summary><strong>Editar cadastro</strong></summary>
    <form method="post" action="<?= $base ?>" class="pr-form">
      <?= csrf_campo() ?>
      <?= Visao::renderizar('producao/_materia_campos', ['m' => $m, 'unidades' => $unidades, 'categorias' => $categorias]) ?>
      <div class="acoes-form"><button class="botao" type="submit">Salvar cadastro</button></div>
    </form>
  </details>
<?php endif; ?>
