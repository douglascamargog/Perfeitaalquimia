<?php
/**
 * Financeiro › Compras da empresa: Lançamentos (NFs) · Itens comprados · Lançar compra · Do Omie (itens para ligar).
 * Variáveis: $aba, $f, $pode, $lancamentos, $porCategoria, $itens, $pendentes, $categorias, $unidades, $fornecedores
 */
use App\Core\Visao;
use App\Services\CompraLancamentoService as L;
use App\Services\ItemCompraService as I;
use App\Services\ProducaoService as P;

$abas = ['lancamentos' => 'Lançamentos', 'itens' => 'Itens comprados', 'lancar' => 'Lançar compra', 'omie' => 'Do Omie' . ($pendentes ? ' (' . count($pendentes) . ')' : '')];
if (!$pode) {
    unset($abas['lancar']);
}
$totalPeriodo = array_sum(array_map(fn($c) => (float) $c['total'], $porCategoria));
$maxCat = max(1, ...array_map(fn($c) => (float) $c['total'], $porCategoria ?: [['total' => 1]]));
$q = fn(array $mais) => url('/financeiro/compras') . '?' . e(http_build_query($mais + array_filter(['aba' => $aba, 'de' => $f['de'], 'ate' => $f['ate'], 'q' => $f['q'], 'tipo' => $f['tipo'], 'categoria' => $f['categoria']])));
?>
<div class="cabecalho-secao">
  <div><div class="sobretitulo">FINANCEIRO · COMPRAS</div><h1>Compras da empresa</h1>
    <p>Tudo que a empresa compra: matéria-prima, embalagem, rótulo e uso e consumo (escritório, limpeza…). Cada item tem a sua página com fornecedores, compras, recorrência e documentos.</p></div>
  <?php if ($pode): ?><div class="acoes">
    <form method="post" action="<?= url('/financeiro/compras/omie') ?>"><?= csrf_campo() ?><button class="botao contorno" type="submit" data-buscar-omie>↻ Buscar notas do Omie</button></form>
    <a class="botao" href="<?= url('/financeiro/compras') ?>?aba=lancar">+ Lançar compra</a></div><?php endif; ?>
</div>
<?php if ($pendentes && $aba !== 'omie'): ?><p class="aviso atencao"><?= count($pendentes) ?> item(ns) de notas do Omie esperando ligação com um item do One. <a href="<?= url('/financeiro/compras') ?>?aba=omie">Ligar agora</a></p><?php endif; ?>

<nav class="abas" aria-label="Compras">
  <?php foreach ($abas as $k => $nome): ?><a class="<?= $k === $aba ? 'ativa' : '' ?>" href="<?= url('/financeiro/compras') ?>?aba=<?= $k ?>" data-aba-compras="<?= $k ?>"><?= e($nome) ?></a><?php endforeach; ?>
</nav>

<?php if ($aba === 'lancamentos'): ?>
  <form class="card it-filtros" method="get" action="<?= url('/financeiro/compras') ?>">
    <label>De<input type="date" name="de" value="<?= e($f['de']) ?>"></label>
    <label>Até<input type="date" name="ate" value="<?= e($f['ate']) ?>"></label>
    <label class="it-largo">Buscar<input type="search" name="q" value="<?= e($f['q']) ?>" placeholder="Fornecedor, NF ou item…"></label>
    <button class="botao contorno" type="submit">Filtrar</button>
  </form>
  <div class="it-duas it-topo">
    <section class="card it-total"><span>Comprado no período</span><strong data-total-periodo><?= dinheiro($totalPeriodo) ?></strong><small><?= count($lancamentos) ?> lançamento(s) · <?= data_br($f['de']) ?> a <?= data_br($f['ate']) ?></small></section>
    <section class="card">
      <h2>Por categoria</h2>
      <?php if (!$porCategoria): ?><p class="suave pequeno">Nada comprado no período.</p><?php endif; ?>
      <div class="ac-canais it-cats"><?php foreach ($porCategoria as $c): ?>
        <div title="<?= e($c['categoria'] . ': ' . dinheiro((float) $c['total'])) ?>"><span><?= e($c['categoria']) ?></span><i><b style="width: <?= round((float) $c['total'] / $maxCat * 100) ?>%"></b></i><strong><?= dinheiro((float) $c['total']) ?></strong></div>
      <?php endforeach; ?></div>
    </section>
  </div>
  <section class="card sem-espaco">
    <div class="tabela-rolagem"><table class="tabela" data-lancamentos>
      <thead><tr><th>Data</th><th>Fornecedor</th><th>NF</th><th>Itens</th><th>Origem</th><th class="direita">Valor</th></tr></thead>
      <tbody>
      <?php foreach ($lancamentos as $c): ?>
        <tr data-lancamento="<?= (int) $c['id'] ?>"><td><?= data_br($c['emissao']) ?></td>
          <td><a href="<?= url('/financeiro/compras/' . (int) $c['id']) ?>"><strong><?= e($c['fornecedor']) ?></strong></a></td>
          <td><?= e($c['numero_nf'] ?: '—') ?><?= $c['arquivo'] ? ' 📎' : '' ?></td>
          <td class="pequeno"><?= e(mb_strimwidth((string) $c['itens'], 0, 90, '…')) ?></td>
          <td><span class="selo <?= $c['origem'] === 'omie' ? 'azul' : ($c['origem'] === 'orcamento' ? 'roxo' : 'cinza') ?>"><?= e(L::ORIGENS[$c['origem']] ?? $c['origem']) ?></span><?= $c['situacao'] === 'pendente' ? ' <span class="selo laranja">item para ligar</span>' : '' ?></td>
          <td class="direita"><strong><?= dinheiro((float) $c['valor_total']) ?></strong></td></tr>
      <?php endforeach; ?>
      <?php if (!$lancamentos): ?><tr><td colspan="6" class="suave">Nenhuma compra no período.</td></tr><?php endif; ?>
      </tbody>
    </table></div>
  </section>

<?php elseif ($aba === 'itens'): ?>
  <form class="card it-filtros" method="get" action="<?= url('/financeiro/compras') ?>">
    <input type="hidden" name="aba" value="itens">
    <label>Tipo<select name="tipo"><option value="">Todos</option><?php foreach (I::TIPOS as $k => $nome): ?><option value="<?= $k ?>" <?= $f['tipo'] === $k ? 'selected' : '' ?>><?= e($nome) ?></option><?php endforeach; ?></select></label>
    <label>Categoria<select name="categoria"><option value="">Todas</option><?php foreach ($categorias as $cat): ?><option <?= $f['categoria'] === $cat ? 'selected' : '' ?>><?= e($cat) ?></option><?php endforeach; ?></select></label>
    <label class="it-largo">Buscar<input type="search" name="q" value="<?= e($f['q']) ?>" placeholder="Nome, código ou fornecedor…"></label>
    <button class="botao contorno" type="submit">Filtrar</button>
  </form>
  <section class="card sem-espaco">
    <div class="tabela-rolagem"><table class="tabela" data-itens-comprados>
      <thead><tr><th>Item</th><th>Tipo</th><th class="direita">Estoque</th><th>Compra a cada</th><th>Próxima compra</th><th class="direita">Gasto 12 meses</th></tr></thead>
      <tbody>
      <?php foreach ($itens as $m): $atrasada = $m['proxima'] && $m['proxima'] < date('Y-m-d'); ?>
        <tr data-item="<?= (int) $m['id'] ?>"><td><a href="<?= url('/producao/materias/' . (int) $m['id']) ?>"><strong><?= e($m['nome']) ?></strong></a><small class="suave" style="display:block"><?= e((string) $m['fornecedor']) ?></small></td>
          <td><span class="selo <?= $m['tipo'] === 'consumo' ? 'azul' : 'cinza' ?>"><?= e(I::tipoDe($m)) ?></span><?= $m['categoria'] ? '<small class="suave" style="display:block">' . e($m['categoria']) . '</small>' : '' ?></td>
          <td class="direita <?= $m['abaixo'] ? 'pn-vermelho' : '' ?>"><?= P::qtd($m['estoque'], $m['unidade']) ?><?= $m['abaixo'] ? '<small style="display:block">abaixo do mínimo</small>' : '' ?></td>
          <td><?= $m['intervalo'] ? '~' . $m['intervalo'] . ' dias' : '—' ?></td>
          <td class="<?= $atrasada ? 'pn-vermelho' : '' ?>"><?= $m['proxima'] ? data_br($m['proxima']) . ($atrasada ? ' · já passou' : '') : '—' ?></td>
          <td class="direita"><?= $m['gasto_12m'] ? dinheiro($m['gasto_12m']) : '—' ?></td></tr>
      <?php endforeach; ?>
      <?php if (!$itens): ?><tr><td colspan="6" class="suave">Nenhum item encontrado.</td></tr><?php endif; ?>
      </tbody>
    </table></div>
  </section>
  <?php if ($pode): ?>
    <details class="card it-cadastro"><summary><strong>+ Novo item</strong> <small class="suave">ex.: papel A4, detergente, caixa de envio</small></summary>
      <form method="post" action="<?= url('/financeiro/compras/itens') ?>" class="pr-form" data-novo-item><?= csrf_campo() ?>
        <?= Visao::renderizar('producao/_materia_campos', ['m' => null, 'unidades' => $unidades, 'categorias' => $categorias]) ?>
        <div class="acoes-form"><button class="botao" type="submit">Cadastrar item</button></div></form>
    </details>
  <?php endif; ?>

<?php elseif ($aba === 'lancar' && $pode): ?>
  <form class="card it-lancar" method="post" action="<?= url('/financeiro/compras') ?>" enctype="multipart/form-data" data-lancar-compra><?= csrf_campo() ?>
    <div class="it-form-forn">
      <label>Fornecedor<input type="text" name="fornecedor" maxlength="160" required list="fornecedores-compras" value="<?= antigo('fornecedor') ?>"></label>
      <datalist id="fornecedores-compras"><?php foreach ($fornecedores as $fo): ?><option value="<?= e($fo) ?>"><?php endforeach; ?></datalist>
      <label>CNPJ<input type="text" name="cnpj" maxlength="18" value="<?= antigo('cnpj') ?>"></label>
      <label>Nº da NF<input type="text" name="numero_nf" maxlength="30" value="<?= antigo('numero_nf') ?>"></label>
      <label>Data<input type="date" name="emissao" max="<?= date('Y-m-d') ?>" value="<?= antigo('emissao', date('Y-m-d')) ?>" required></label>
      <label>Arquivo da NF <small>PDF, imagem ou XML</small><input type="file" name="arquivo_nf" accept="application/pdf,image/*,.xml,text/xml"></label>
      <label class="it-largo">Observação<input type="text" name="observacoes" maxlength="500" value="<?= antigo('observacoes') ?>"></label>
    </div>
    <h2>Itens da nota</h2>
    <div class="tabela-rolagem"><table class="tabela it-linhas" data-linhas>
      <thead><tr><th>Item</th><th class="direita">Quantidade</th><th class="direita">Valor unitário (R$)</th><th class="direita">Total</th><th></th></tr></thead>
      <tbody>
        <?php for ($i = 0; $i < 3; $i++): ?>
          <tr data-linha><td><select name="item[]" aria-label="Item"><option value="">— escolha o item</option>
            <?php foreach ($itens as $m): ?><option value="<?= (int) $m['id'] ?>" data-preco="<?= e((string) $m['custo_unitario']) ?>" data-unidade="<?= e($m['unidade']) ?>"><?= e($m['nome']) ?> (<?= e($m['unidade']) ?>)</option><?php endforeach; ?></select></td>
            <td class="direita"><input type="text" name="quantidade[]" inputmode="decimal" aria-label="Quantidade"></td>
            <td class="direita"><input type="text" name="valor[]" inputmode="decimal" aria-label="Valor unitário"></td>
            <td class="direita" data-total-linha>—</td><td><button type="button" class="botao contorno pequeno" data-tirar-linha aria-label="Tirar linha">×</button></td></tr>
        <?php endfor; ?>
      </tbody>
    </table></div>
    <div class="it-lancar-rodape">
      <button type="button" class="botao contorno pequeno" data-mais-linha>+ Outra linha</button>
      <span>Total da nota: <strong data-total-nota>R$ 0,00</strong></span>
      <button class="botao lima" type="submit">Lançar compra</button>
    </div>
    <p class="pequeno suave">Ao lançar: cada item entra no estoque, o fornecedor vai para a lista do item com o preço desta compra e a NF fica nos documentos de cada item. Item que não está na lista? Cadastre em "Itens comprados › Novo item".</p>
  </form>

<?php elseif ($aba === 'omie'): ?>
  <section class="card sem-espaco">
    <h2 class="pr-titulo-tabela">Itens de notas do Omie para ligar</h2>
    <div class="tabela-rolagem"><table class="tabela" data-pendentes>
      <thead><tr><th>Nota</th><th>Item na nota</th><th class="direita">Quantidade</th><th>Qual item do One é este?</th></tr></thead>
      <tbody>
      <?php $catalogo = \App\Core\Banco::todos('SELECT id, nome, unidade FROM materias_primas WHERE ativo = 1 ORDER BY nome'); foreach ($pendentes as $p): ?>
        <tr data-pendente="<?= (int) $p['id'] ?>"><td><?= data_br($p['emissao']) ?> · <?= e($p['fornecedor']) ?><small class="suave" style="display:block">NF <?= e($p['numero_nf'] ?: '—') ?></small></td>
          <td><strong><?= e($p['descricao']) ?></strong><small class="suave" style="display:block">código Omie <?= e((string) $p['omie_item']) ?></small></td>
          <td class="direita"><?= rtrim(rtrim(number_format((float) $p['quantidade'], 3, ',', '.'), '0'), ',') ?></td>
          <td><?php if ($pode): ?><form method="post" action="<?= url('/financeiro/compras/ligar/' . (int) $p['id']) ?>" class="it-ligar"><?= csrf_campo() ?>
            <select name="item" required aria-label="Item do One"><option value="">— escolha</option><?php foreach ($catalogo as $c): ?><option value="<?= (int) $c['id'] ?>"><?= e($c['nome']) ?> (<?= e($c['unidade']) ?>)</option><?php endforeach; ?></select>
            <button class="botao pequeno" type="submit">Ligar e lançar</button></form><?php else: ?>—<?php endif; ?></td></tr>
      <?php endforeach; ?>
      <?php if (!$pendentes): ?><tr><td colspan="4" class="suave">Nada para ligar: as notas do Omie entraram todas no estoque. ✅</td></tr><?php endif; ?>
      </tbody>
    </table></div>
  </section>
  <p class="pequeno suave">Ligar é só da primeira vez: as próximas notas do Omie com esse item entram no estoque sozinhas e avisam quem compra, a Produção e a Diretoria.</p>
<?php endif; ?>
