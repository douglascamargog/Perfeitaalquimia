<?php
/** Terceirização › Pedidos dos clientes de terceirização. Variáveis: $pedidos, $situacao */
use App\Core\Visao;

$situacoes = ['' => 'Todos', 'aguardando_aprovacao' => 'Aguardando aprovação', 'aprovado' => 'Aprovados', 'faturado' => 'Faturados', 'recusado' => 'Recusados'];
$rotulo = ['aguardando_aprovacao' => ['Aguardando aprovação', 'laranja'], 'aprovado' => ['Aprovado', 'lima'], 'faturado' => ['Faturado', 'verde'],
    'recusado' => ['Recusado', 'vermelho'], 'cancelado' => ['Cancelado', 'cinza']];
?>
<?= Visao::renderizar('terceirizacao/_abas', ['atual' => '/terceirizacao/pedidos', 'titulo2' => 'Pedidos de terceirização',
    'texto' => 'Pedidos dos clientes de terceirização. A coluna "Com a marca dele" mostra quanto do pedido é de produtos com a marca do cliente.']) ?>
<div class="te-filtro-situacao">
  <?php foreach ($situacoes as $chave => $nome): ?>
    <a href="<?= url('/terceirizacao/pedidos') . ($chave ? '?situacao=' . $chave : '') ?>" class="botao <?= $situacao === $chave ? '' : 'contorno' ?> pequeno"><?= $nome ?></a>
  <?php endforeach; ?>
</div>
<section class="card sem-espaco">
  <div class="tabela-rolagem">
    <table class="tabela tabela-carteira">
      <thead><tr><th>Pedido</th><th>Cliente</th><th>Carteira</th><th>Vendedor</th><th>Situação</th><th class="direita">Com a marca dele</th><th class="direita">Total</th></tr></thead>
      <tbody>
      <?php foreach ($pedidos as $p): [$nome, $cor] = $rotulo[$p['situacao']] ?? [$p['situacao'], 'cinza']; ?>
        <tr>
          <td><a href="<?= url('/pedidos/' . (int) $p['id']) ?>"><strong>#<?= (int) $p['id'] ?></strong></a><small class="suave"><?= data_br($p['criado_em']) ?></small></td>
          <td><?= e($p['cliente']) ?></td>
          <td class="pequeno"><?= e($p['carteira'] ?? '—') ?></td>
          <td class="pequeno"><?= e($p['vendedor'] ?? '—') ?></td>
          <td><span class="selo <?= $cor ?>"><?= e($nome) ?></span></td>
          <td class="direita"><?= $p['total_marca'] > 0 ? dinheiro((float) $p['total_marca']) : '—' ?></td>
          <td class="direita"><strong><?= dinheiro((float) $p['total']) ?></strong></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$pedidos): ?><tr><td colspan="7" class="suave">Nenhum pedido.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</section>
