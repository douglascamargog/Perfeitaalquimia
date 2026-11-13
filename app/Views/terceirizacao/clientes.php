<?php
/** Terceirização › Clientes. Variáveis: $busca, $clientes, $alertas, $faturadoMes, $faturado12 */
use App\Core\Visao;

$icones = asset('img/icones.svg');
?>
<?= Visao::renderizar('terceirizacao/_abas', ['atual' => '/terceirizacao', 'alertas' => $alertas, 'titulo2' => 'Clientes de terceirização',
    'texto' => 'Quem faz produtos com a marca dele: os da carteira de Terceirização e os de outras carteiras (ex.: Televendas) que compram junto com os nossos produtos.']) ?>
<div class="te-kpis">
  <div class="card te-kpi"><span>Clientes</span><strong><?= count($clientes) ?></strong></div>
  <div class="card te-kpi"><span>Faturado com a marca dos clientes · mês</span><strong><?= dinheiro($faturadoMes) ?></strong></div>
  <div class="card te-kpi"><span>Últimos 12 meses</span><strong><?= dinheiro($faturado12) ?></strong></div>
  <a class="card te-kpi <?= $alertas ? 'te-alerta' : '' ?>" href="<?= url('/terceirizacao/rotulos?situacao=alerta') ?>"><span>Rótulos em alerta</span><strong><?= (int) $alertas ?></strong></a>
</div>
<form class="filtros card" method="get" action="<?= url('/terceirizacao') ?>" data-filtros>
  <div class="filtros-busca"><svg class="icone"><use href="<?= $icones ?>#lupa"/></svg>
    <input type="search" name="q" value="<?= e($busca) ?>" placeholder="Nome, CNPJ ou cidade…" aria-label="Buscar cliente"></div>
</form>
<section class="card sem-espaco">
  <div class="tabela-rolagem">
    <table class="tabela tabela-carteira">
      <thead><tr><th>Cliente</th><th>Carteira</th><th class="direita">Produtos</th><th>Rótulos</th><th class="direita">Com a marca dele (12 meses)</th><th>Última compra</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($clientes as $c): ?>
        <tr data-cliente-terc="<?= (int) $c['id'] ?>">
          <td><button type="button" class="link-cliente" data-abrir-cliente="<?= (int) $c['id'] ?>"><strong><?= e($c['nome_fantasia'] ?: $c['razao_social']) ?></strong></button>
            <small class="suave"><?= e($c['cidade']) ?>/<?= e($c['uf']) ?><?= $c['responsavel'] ? ' · ' . e($c['responsavel']) : '' ?></small></td>
          <td><span class="selo <?= $c['frente'] === 'terceirizacao' ? 'verde' : 'cinza' ?>"><?= e($c['carteira'] ?? 'Sem carteira') ?></span></td>
          <td class="direita"><a href="<?= url('/terceirizacao/produtos?cliente=' . (int) $c['id']) ?>"><?= (int) $c['produtos'] ?></a></td>
          <td><?= $c['rotulos_alerta'] ? '<span class="selo vermelho">' . (int) $c['rotulos_alerta'] . ' em alerta</span>' : ($c['produtos'] ? '<span class="selo verde">ok</span>' : '—') ?></td>
          <td class="direita"><?= $c['faturado_terc'] > 0 ? dinheiro((float) $c['faturado_terc']) : '—' ?></td>
          <td class="pequeno"><?= $c['ultima_compra_em'] ? data_br($c['ultima_compra_em']) : '—' ?></td>
          <td><a class="botao contorno pequeno" href="<?= url('/pedidos/novo?cliente=' . (int) $c['id']) ?>">Novo pedido</a></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$clientes): ?><tr><td colspan="7" class="suave">Nenhum cliente de terceirização ainda. Cadastre os produtos com a marca do cliente em "Produtos por cliente".</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</section>
<?= Visao::renderizar('carteira/_drawer') ?>
