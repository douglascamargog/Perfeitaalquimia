<?php
/**
 * Planejador de visitas: lista de roteiros. Variáveis: $roteiros, $gerencia, $pessoa, $pessoas
 */
use App\Core\Auth;

$situacoes = ['planejado' => ['Em andamento', 'verde'], 'concluido' => ['Concluído', ''], 'cancelado' => ['Cancelado', 'vermelho']];
?>
<div class="cabecalho-secao">
  <div><div class="sobretitulo">VENDAS · VISITAS</div><h1>Planejador de visitas</h1>
    <p>Monte o roteiro da viagem: escolha as cidades do caminho e o One mostra as lojas que ficam perto, de qualquer carteira,
      começando pelas mais importantes. Pedido fechado em loja de outra carteira vai para a dona dela (e aparece no seu Meu mês).</p></div>
  <?php if (Auth::pode('roteiros.usar')): ?><div class="acoes"><a class="botao" href="<?= url('/roteiros/novo') ?>" data-novo-roteiro>＋ Novo roteiro</a></div><?php endif; ?>
</div>

<?php if ($gerencia && $pessoas): ?>
  <form class="filtros card" method="get" action="<?= url('/roteiros') ?>" style="max-width:360px">
    <select name="pessoa" aria-label="De quem" onchange="this.form.submit()"><option value="">Roteiros de todos</option>
      <?php foreach ($pessoas as $p): ?><option value="<?= (int) $p['id'] ?>" <?= (int) $pessoa === (int) $p['id'] && isset($_GET['pessoa']) ? 'selected' : '' ?>><?= e($p['nome']) ?></option><?php endforeach; ?></select>
  </form>
<?php endif; ?>

<?php if (!$roteiros): ?>
  <section class="card"><p class="suave" data-sem-roteiros>Nenhum roteiro ainda. Clique em <strong>Novo roteiro</strong> para planejar a próxima viagem.</p></section>
<?php else: ?>
  <div class="rt-lista">
    <?php foreach ($roteiros as $r): [$sit, $cor] = $situacoes[$r['situacao']] ?? [$r['situacao'], '']; ?>
      <a class="card rt-cartao" href="<?= url('/roteiros/' . (int) $r['id']) ?>" data-roteiro="<?= (int) $r['id'] ?>">
        <header><strong><?= e($r['nome']) ?></strong><span class="selo <?= $cor ?>"><?= $sit ?></span></header>
        <p class="suave pequeno"><?= data_br($r['data_inicio']) ?><?= $r['data_fim'] !== $r['data_inicio'] ? ' a ' . data_br($r['data_fim']) : '' ?><?= $gerencia ? ' · ' . e($r['usuario_nome']) : '' ?></p>
        <p class="rt-cidades"><?= e(implode(' → ', array_column($r['cidades'], 'nome'))) ?></p>
        <div class="rt-numeros"><span><b><?= (int) $r['paradas'] ?></b> loja(s)</span><span><b><?= (int) $r['feitas'] ?></b> visitada(s)</span><span><b><?= dinheiro($r['vendido']) ?></b> em pedidos</span></div>
      </a>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
