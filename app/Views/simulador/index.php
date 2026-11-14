<?php /** Administração › Dados de teste. Variáveis: $contagem, $pessoas */
$nomes = ['clientes' => 'Clientes de teste', 'ligacoes' => 'Ligações', 'contatos' => 'Contatos e eventos', 'tarefas' => 'Retornos (agenda)', 'pedidos' => 'Pedidos',
    'cobrancas' => 'Casos de cobrança', 'ordens' => 'Ordens de produção', 'posts' => 'Posts do Marketing'];
$total = array_sum($contagem);
?>
<div class="cabecalho-secao">
  <div><div class="sobretitulo">ADMINISTRAÇÃO · DADOS DE TESTE</div><h1>Dados de teste</h1>
    <p>Gere movimento simulado dos últimos 60 dias para as pessoas já cadastradas e teste todos os setores. Quando forem usar de verdade, apague tudo o que foi simulado com um clique.</p></div>
</div>

<div class="aviso alerta">Os dados simulados aparecem junto com os reais (Acompanhamento, Carteira, Agenda, Pedidos, Cobrança...) até você apagar. Eles não mexem em estoque nem no ERP.</div>

<div class="ac-duas" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:14px">
  <section class="card">
    <h2>Quem vai receber movimento</h2>
    <p><strong>Vendas</strong> (pessoas nas funções de setor de venda): <?= $pessoas['vendas'] ? e(implode(', ', array_column($pessoas['vendas'], 'nome'))) : '<span class="suave">ninguém — coloque as vendedoras nas funções Televendas, Externo...</span>' ?></p>
    <p><strong>Cobrança</strong>: <?= $pessoas['cobranca'] ? e(implode(', ', array_column($pessoas['cobranca'], 'nome'))) : '<span class="suave">ninguém</span>' ?></p>
    <p class="pequeno suave">Também: ordens de produção planejadas e posts no Marketing. Quem tem menos de 10 clientes recebe 30 clientes de teste.</p>
    <form method="post" action="<?= url('/sistema/dados-de-teste/gerar') ?>" data-confirmar="Gerar movimento simulado dos últimos 60 dias?"><?= csrf_campo() ?>
      <button class="botao" type="submit">Gerar movimento simulado</button></form>
  </section>
  <section class="card">
    <h2>Simulado no sistema agora</h2>
    <dl class="pd-dados" style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px">
      <?php foreach ($contagem as $k => $n): ?><div><dt class="pequeno suave"><?= e($nomes[$k]) ?></dt><dd style="margin:0;font-weight:700" data-simulado="<?= e($k) ?>"><?= number_format($n, 0, ',', '.') ?></dd></div><?php endforeach; ?>
    </dl>
    <?php if ($total): ?>
      <form method="post" action="<?= url('/sistema/dados-de-teste/apagar') ?>" class="pd-form" style="margin-top:14px"><?= csrf_campo() ?>
        <label>Para apagar tudo o que foi simulado, digite <strong>APAGAR</strong><input type="text" name="confirmar" autocomplete="off"></label>
        <button class="botao contorno" type="submit" style="color:var(--perigo);border-color:var(--perigo)">Apagar dados simulados</button>
        <p class="pequeno suave">Um backup do banco é feito antes. Só o que foi gerado aqui é apagado.</p>
      </form>
    <?php endif; ?>
  </section>
</div>
