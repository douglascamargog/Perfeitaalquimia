<?php
/** Estoque do produto acabado. Variáveis: $busca, $so (só abaixo do mínimo), $itens, $pode */
use App\Core\Visao;

$icones = asset('img/icones.svg');
$n = fn($v) => number_format((float) $v, 0, ',', '.');
?>
<div class="cabecalho-secao">
  <div><div class="sobretitulo">PRODUÇÃO · PRODUTO ACABADO</div><h1>Estoque acabado</h1>
    <p>Produto pronto na fábrica: soma nas ordens concluídas<?= \App\Core\Config::get('producao.baixar_no_faturamento', true) ? ' e baixa nos pedidos faturados' : '' ?>. Abaixo do mínimo = reabastecer.</p></div>
  <div class="acoes nao-imprimir"><button class="botao contorno" type="button" onclick="window.print()"><svg class="icone"><use href="<?= $icones ?>#imprimir"/></svg>Imprimir</button></div>
</div>
<?= Visao::renderizar('producao/_abas', ['atual' => '/producao/acabados']) ?>
<form class="filtros card nao-imprimir" method="get" action="<?= url('/producao/acabados') ?>" data-filtros>
  <div class="filtros-busca"><svg class="icone"><use href="<?= $icones ?>#lupa"/></svg>
    <input type="search" name="q" value="<?= e($busca) ?>" placeholder="Nome do produto…" aria-label="Buscar"></div>
  <div class="filtros-grade"><select name="situacao" aria-label="Situação"><option value="">Todos</option><option value="abaixo" <?= $so ? 'selected' : '' ?>>Abaixo do mínimo</option></select></div>
</form>
<section class="card sem-espaco">
  <div class="tabela-rolagem">
    <table class="tabela tabela-carteira pr-tabela" data-tabela-acabados>
      <thead><tr><th>Produto</th><th class="direita">Estoque</th><th class="direita">Mínimo</th><th class="direita">Venda/dia</th><th class="direita">Cobertura</th><th class="direita">Planejado</th><th>Reabastecer</th></tr></thead>
      <tbody>
      <?php foreach ($itens as $a): ?>
        <tr data-acabado="<?= (int) $a['id'] ?>">
          <td><a href="<?= url('/producao/acabados/' . (int) $a['id']) ?>"><strong><?= !empty($a['codigo']) ? '<span class="cod-produto">' . e($a['codigo']) . '</span> ' : '' ?><?= e($a['nome']) ?></strong></a><?= $a['tem_ficha'] ? '' : '<small class="suave">sem ficha técnica</small>' ?></td>
          <td class="direita"><strong class="<?= $a['abaixo'] || $a['quantidade'] < 0 ? 'pn-vermelho' : '' ?>" data-quantidade><?= $n($a['quantidade']) ?></strong></td>
          <td class="direita">
            <?php if ($pode): ?>
              <form method="post" action="<?= url('/producao/acabados/' . (int) $a['id'] . '/minimo') ?>" class="pr-minimo nao-imprimir">
                <?= csrf_campo() ?><input type="hidden" name="voltar" value="lista">
                <input type="number" name="minimo" min="0" value="<?= (int) $a['minimo'] ?>" aria-label="Estoque mínimo de <?= e($a['nome']) ?>" class="pr-num" onchange="this.form.submit()">
              </form>
            <?php endif; ?><span class="<?= $pode ? 'so-impressao' : '' ?>"><?= $n($a['minimo']) ?></span>
          </td>
          <td class="direita pequeno"><?= number_format($a['venda_dia'], 1, ',', '.') ?></td>
          <td class="direita pequeno"><?= $a['dias_cobertura'] !== null ? '~' . $a['dias_cobertura'] . ' dias' : '—' ?></td>
          <td class="direita pequeno"><?= $a['planejado'] ? $n($a['planejado']) : '—' ?></td>
          <td>
            <?php if ($a['abaixo'] && $a['sugestao'] > 0 && $pode && $a['tem_ficha']): ?>
              <form method="post" action="<?= url('/producao/reabastecer/' . (int) $a['id']) ?>" class="nao-imprimir"><?= csrf_campo() ?>
                <button class="botao lima pequeno" type="submit">Gerar ordem de <?= $n($a['sugestao']) ?> un</button></form>
            <?php elseif ($a['abaixo'] && $a['sugestao'] > 0): ?><span class="selo laranja">sugerido <?= $n($a['sugestao']) ?> un</span>
            <?php elseif ($a['abaixo']): ?><span class="selo cinza">já planejado</span>
            <?php else: ?><span class="selo verde">ok</span><?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$itens): ?><tr><td colspan="7" class="vazio">Nenhum produto com controle na fábrica ainda. Cadastre uma ficha técnica para começar.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
  <div class="paginacao-barra"><span><?= count($itens) ?> produto(s) · <?= count(array_filter($itens, fn($a) => $a['abaixo'])) ?> abaixo do mínimo</span></div>
</section>
