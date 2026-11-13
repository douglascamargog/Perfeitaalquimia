<?php
/** Lista de matérias-primas. Variáveis: $f (filtros), $itens, $pode */
use App\Core\Visao;
use App\Services\ProducaoService as P;

$icones = asset('img/icones.svg');
?>
<div class="cabecalho-secao">
  <div><div class="sobretitulo">PRODUÇÃO · MATÉRIAS-PRIMAS</div><h1>Matérias-primas</h1>
    <p>Estoque, mínimo e previsão de quando acaba (pelo consumo médio). Entrada de compra e inventário em cada uma.</p></div>
  <div class="acoes nao-imprimir">
    <?php if ($pode): ?><a class="botao" href="<?= url('/producao/materias/nova') ?>"><svg class="icone"><use href="<?= $icones ?>#mais"/></svg>Nova matéria-prima</a><?php endif; ?>
    <button class="botao contorno" type="button" onclick="window.print()"><svg class="icone"><use href="<?= $icones ?>#imprimir"/></svg>Imprimir</button>
  </div>
</div>
<?= Visao::renderizar('producao/_abas', ['atual' => '/producao/materias']) ?>

<form class="filtros card nao-imprimir" method="get" action="<?= url('/producao/materias') ?>" data-filtros>
  <div class="filtros-busca"><svg class="icone"><use href="<?= $icones ?>#lupa"/></svg>
    <input type="search" name="q" value="<?= e($f['q']) ?>" placeholder="Nome, código ou fornecedor…" aria-label="Buscar"></div>
  <div class="filtros-grade">
    <select name="situacao" aria-label="Situação">
      <option value="">Todas</option>
      <option value="abaixo" <?= $f['situacao'] === 'abaixo' ? 'selected' : '' ?>>Abaixo do mínimo</option>
      <option value="alerta" <?= $f['situacao'] === 'alerta' ? 'selected' : '' ?>>Abaixo do mínimo ou acabando</option>
    </select>
    <label class="opcao"><input type="checkbox" name="inativas" value="1" <?= $f['inativas'] ? 'checked' : '' ?>> Mostrar inativas</label>
  </div>
</form>

<section class="card sem-espaco">
  <div class="tabela-rolagem">
    <table class="tabela tabela-carteira pr-tabela" data-tabela-materias>
      <thead><tr><th>Matéria-prima</th><th class="direita">Estoque</th><th class="direita">Mínimo</th><th class="direita">Consumo/dia</th><th>Previsão</th><th>Fornecedor</th><?php if ($pode): ?><th class="nao-imprimir">Entrada de compra</th><?php endif; ?></tr></thead>
      <tbody>
      <?php foreach ($itens as $m): ?>
        <tr class="<?= $m['ativo'] ? '' : 'inativo' ?>" data-mp="<?= (int) $m['id'] ?>">
          <td><a href="<?= url('/producao/materias/' . (int) $m['id']) ?>"><strong><?= e($m['nome']) ?></strong></a>
            <small class="suave"><?= e($m['codigo'] ?: '') ?><?= $m['codigo'] ? ' · ' : '' ?><?= (int) $m['produtos'] ?> produto(s)</small></td>
          <td class="direita"><strong class="<?= $m['abaixo'] ? 'pn-vermelho' : '' ?>" data-estoque><?= P::qtd($m['estoque'], $m['unidade']) ?></strong></td>
          <td class="direita"><?= P::qtd($m['estoque_minimo'], $m['unidade']) ?></td>
          <td class="direita pequeno"><?= $m['consumo_dia'] > 0 ? P::qtd(round($m['consumo_dia'], 3), $m['unidade']) : '—' ?></td>
          <td>
            <?php if ($m['abaixo']): ?><span class="selo vermelho">abaixo do mínimo</span><?php endif; ?>
            <?php if ($m['dias_restantes'] !== null): ?>
              <span class="selo <?= $m['acabando'] ? 'laranja' : 'verde' ?>" data-previsao>~<?= (int) $m['dias_restantes'] ?> dias · <?= data_br($m['acaba_em']) ?></span>
            <?php elseif (!$m['abaixo']): ?><span class="suave pequeno">sem consumo recente</span><?php endif; ?>
          </td>
          <td class="pequeno"><?= e($m['fornecedor'] ?? '—') ?></td>
          <?php if ($pode): ?>
            <td class="nao-imprimir">
              <form method="post" action="<?= url('/producao/materias/' . (int) $m['id'] . '/entrada') ?>" class="pr-entrada-rapida">
                <?= csrf_campo() ?><input type="hidden" name="voltar" value="lista">
                <input type="text" name="quantidade" inputmode="decimal" placeholder="qtd (<?= e($m['unidade']) ?>)" aria-label="Quantidade comprada de <?= e($m['nome']) ?>" required>
                <input type="text" name="custo_unitario" inputmode="decimal" placeholder="R$/<?= e($m['unidade']) ?>" aria-label="Custo por unidade" value="<?= $m['custo_unitario'] > 0 ? rtrim(rtrim(number_format((float) $m['custo_unitario'], 4, ',', ''), '0'), ',') : '' ?>">
                <button class="botao contorno pequeno" type="submit">+ Entrada</button>
              </form>
            </td>
          <?php endif; ?>
        </tr>
      <?php endforeach; ?>
      <?php if (!$itens): ?><tr><td colspan="7" class="vazio">Nenhuma matéria-prima com esses filtros.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
  <div class="paginacao-barra"><span><?= count($itens) ?> matéria(s)-prima(s)</span></div>
</section>
