<?php /** Novo pedido — passo 1: escolher o cliente. Variáveis: $busca, $clientes */ ?>
<div class="cabecalho-secao"><div><div class="sobretitulo">NOVO PEDIDO</div><h1>Para qual cliente?</h1><p>Busque pelo nome, cidade ou CNPJ (só os clientes que você atende).</p></div></div>
<form class="card ped-busca" method="get" action="<?= url('/pedidos/novo') ?>">
  <input type="search" name="q" value="<?= e($busca) ?>" placeholder="Nome, cidade ou CNPJ…" aria-label="Buscar cliente" autofocus>
  <button class="botao" type="submit">Buscar</button>
</form>
<?php if ($busca !== '' && !$clientes): ?><p class="suave">Nenhum cliente encontrado.</p><?php endif; ?>
<div class="ped-clientes">
  <?php foreach ($clientes as $c): ?>
    <a class="card" href="<?= url('/pedidos/novo') ?>?cliente=<?= (int) $c['id'] ?>"><strong><?= e($c['nome_fantasia'] ?: $c['razao_social']) ?></strong><small><?= e($c['cidade']) ?>/<?= e($c['uf']) ?> · <?= e($c['etapa']) ?></small></a>
  <?php endforeach; ?>
</div>
