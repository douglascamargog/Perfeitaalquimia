<?php
/** Depósito › Rastrear lote: para quais clientes foi um lote (lista para recolhimento). Variáveis: $busca, $linhas */
use App\Core\Visao;

$icones = asset('img/icones.svg');
$clientes = count(array_unique(array_column($linhas, 'cnpj')));
?>
<div class="cabecalho-secao">
  <div><div class="sobretitulo">DEPÓSITO · RASTREABILIDADE</div><h1>Rastrear lote</h1>
    <p>Digite o lote (ou o código do produto) para ver para quais clientes e pedidos ele foi. É a lista de um recolhimento.</p></div>
  <?php if ($linhas): ?><div class="acoes nao-imprimir"><button class="botao contorno" type="button" onclick="window.print()"><svg class="icone"><use href="<?= $icones ?>#imprimir"/></svg>Imprimir</button></div><?php endif; ?>
</div>
<?= Visao::renderizar('deposito/_abas', ['atual' => '/deposito/lotes']) ?>
<form class="filtros card nao-imprimir" method="get" action="<?= url('/deposito/lotes') ?>">
  <div class="filtros-busca"><svg class="icone"><use href="<?= $icones ?>#lupa"/></svg>
    <input type="search" name="q" value="<?= e($busca) ?>" placeholder="Lote, código ou nome do produto…" aria-label="Lote" autofocus></div>
</form>
<?php if ($busca !== ''): ?>
<section class="card sem-espaco">
  <h2 class="pr-titulo-tabela"><?= count($linhas) ?> saída(s) · <?= $clientes ?> cliente(s)</h2>
  <div class="tabela-rolagem">
    <table class="tabela" data-rastreio>
      <thead><tr><th>Lote</th><th>Validade</th><th>Produto</th><th class="direita">Qtd</th><th>Cliente</th><th>Cidade</th><th>Telefone</th><th>Pedido</th><th>Saída</th></tr></thead>
      <tbody>
      <?php foreach ($linhas as $l): ?>
        <tr><td><strong><?= e($l['lote']) ?></strong></td><td><?= $l['validade'] ? data_br($l['validade']) : '—' ?></td>
          <td><?= $l['codigo'] ? '<span class="cod-produto">' . e($l['codigo']) . '</span> ' : '' ?><?= e($l['produto']) ?></td>
          <td class="direita"><?= (int) $l['quantidade'] ?></td><td><?= e($l['cliente']) ?><small class="suave"><?= $l['cnpj'] ? e(cnpj_formatado($l['cnpj'])) : '' ?></small></td>
          <td class="pequeno"><?= e($l['cidade']) ?>/<?= e($l['uf']) ?></td><td class="pequeno"><?= e((string) $l['telefone']) ?></td>
          <td><a href="<?= url('/pedidos/' . (int) $l['pedido_id']) ?>">#<?= (int) $l['pedido_id'] ?></a><?= $l['numero_nf'] ? ' <small>NF ' . e($l['numero_nf']) . '</small>' : '' ?></td>
          <td class="pequeno"><?= data_br($l['despachado_em'] ?: $l['criado_em']) ?></td></tr>
      <?php endforeach; ?>
      <?php if (!$linhas): ?><tr><td colspan="9" class="suave">Nenhuma saída com esse lote ou produto.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</section>
<?php endif; ?>
