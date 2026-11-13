<?php
/**
 * Administração › Integração Omie.
 * Variáveis: $simulado (bool), $chaves (bool: chaves preenchidas no config.php), $ultima (última sincronização ou null), $numeros, $produtos, $estoque
 */
$icones = asset('img/icones.svg');
$n = fn($v) => number_format((float) $v, 0, ',', '.');
$vem = [
    ['Produtos e preços', 'As 3 tabelas (Lojista, Distribuidor, Terceirização) → catálogo, pedidos e app'],
    ['Estoque', 'Mostrado no catálogo e no pedido'],
    ['Faturamento e NF', 'Pedido aprovado e faturado → linha do tempo do cliente, última compra, recompra e painel de vendas'],
    ['Títulos em atraso', 'Cobrança e bloqueio do boleto no catálogo'],
    ['Dados fiscais do cliente', 'Razão social, CNPJ e endereço oficiais'],
];
$vai = [
    ['Pedidos', 'Do catálogo, do sistema e do app → Omie como orçamento / etapa "a aprovar" (sem duplicar: código de integração)'],
    ['Aprovação do Financeiro', 'Avança a etapa do pedido no Omie'],
    ['Clientes novos', 'Cadastro do cliente no Omie junto com o primeiro pedido (Fase 2)'],
];
$fica = ['Prospecção e funil', 'Carteira e vendedor responsável', 'Histórico e tarefas', 'Catálogo (fotos e textos)', 'Metas e painel', 'Academia', 'Marketing'];
?>
<div class="cabecalho-secao">
  <div><div class="sobretitulo">ADMINISTRAÇÃO · ERP</div><h1>Integração Omie</h1>
    <p>O Omie é a fonte da verdade de produtos, preços, estoque, faturamento e financeiro. O One cuida do comercial. Esta tela mostra o que passa de um para o outro.</p></div>
  <div class="acoes">
    <form method="post" action="<?= url('/integracao/testar') ?>"><?= csrf_campo() ?><button class="botao contorno" type="submit">Testar conexão</button></form>
    <form method="post" action="<?= url('/integracao/sincronizar') ?>"><?= csrf_campo() ?><button class="botao" type="submit" data-sincronizar><svg class="icone"><use href="<?= $icones ?>#nuvem"/></svg>Sincronizar agora</button></form>
  </div>
</div>

<div class="bento">
  <section class="b-card b-escuro span-4" data-card="modo"><header class="b-topo"><span>Modo atual</span><span class="b-icone-redondo lima"><svg class="icone"><use href="<?= $icones ?>#nuvem"/></svg></span></header>
    <strong class="b-numero-grande" data-modo><?= $simulado ? 'Simulado' : 'Omie real' ?></strong>
    <p class="b-sub"><?= $simulado ? 'Dados de demonstração (OmieFake). Trocar para o real é só preencher as chaves no servidor.' : ($chaves ? 'Conectado com as chaves do config.php.' : 'Faltam as chaves no config.php.') ?></p></section>
  <section class="b-card b-kpi span-2"><header class="b-topo"><span>Última sincronização</span></header>
    <strong class="b-valor"><?= $ultima ? data_br($ultima['em'], true) : '—' ?></strong><small><?= $ultima ? $n($ultima['precos']) . ' preços · ' . $n($ultima['faturados']) . ' faturado(s)' : 'ainda não rodou' ?></small></section>
  <section class="b-card b-kpi span-2 <?= $numeros['sem_envio'] ? 'b-alerta-suave' : '' ?>"><header class="b-topo"><span>Pedidos sem envio</span></header>
    <strong class="b-valor" data-sem-envio><?= $n($numeros['sem_envio']) ?></strong><small>reenviados na próxima sincronização</small></section>
  <section class="b-card b-kpi span-2"><header class="b-topo"><span>No Omie a faturar</span></header>
    <strong class="b-valor"><?= $n($numeros['a_faturar']) ?></strong><small><?= $n($numeros['aguardando']) ?> aguardando aprovação</small></section>
  <section class="b-card b-kpi b-lima span-2"><header class="b-topo"><span>Faturados em 30 dias</span></header>
    <strong class="b-valor"><?= $n($numeros['faturados_30d']) ?></strong><small>voltaram do ERP com NF</small></section>

  <section class="b-card span-4"><header class="b-topo"><h2>Vem do Omie</h2><span class="b-chip">a cada 30 min</span></header>
    <ul class="pr-alertas"><?php foreach ($vem as [$t, $d]): ?><li><div><strong><?= $t ?></strong><br><small class="suave"><?= $d ?></small></div></li><?php endforeach; ?></ul>
    <p class="suave pequeno"><?= $n($produtos) ?> produtos com preço · <?= $n($estoque) ?> com estoque no catálogo.</p>
    <?php if ($semPreco): ?><p class="pn-vermelho pequeno" data-sem-preco><?= $n($semPreco) ?> produto(s) ativo(s) sem preço: não aparecem no catálogo do cliente. Clique em "Sincronizar agora".</p><?php endif; ?></section>
  <section class="b-card span-4"><header class="b-topo"><h2>Vai para o Omie</h2><span class="b-chip">na hora</span></header>
    <ul class="pr-alertas"><?php foreach ($vai as [$t, $d]): ?><li><div><strong><?= $t ?></strong><br><small class="suave"><?= $d ?></small></div></li><?php endforeach; ?></ul></section>
  <section class="b-card span-4"><header class="b-topo"><h2>Fica no One</h2><span class="b-chip">fonte da verdade</span></header>
    <ul class="pr-alertas"><?php foreach ($fica as $t): ?><li><strong><?= $t ?></strong></li><?php endforeach; ?></ul></section>

  <section class="b-card span-12"><header class="b-topo"><h2>Para ligar o Omie de verdade</h2><span class="b-chip">Fase 2, depois da contratação</span></header>
    <p class="suave">Todo o sistema já conversa com o ERP por uma "tomada" única: hoje ligada ao simulador. Na Fase 2 terminamos o conector real
      (as mesmas chamadas, agora na API do Omie) e seguimos os passos abaixo. Nenhuma tela muda.</p>
    <ol class="int-passos">
      <li>No Omie: <strong>Configurações › Aplicativos › Integrações</strong> → gerar <strong>App Key</strong> e <strong>App Secret</strong>.</li>
      <li>No servidor, em <code>config/config.php</code>: preencher <code>integracoes.omie.app_key</code> e <code>app_secret</code> e trocar <code>integracoes.erp</code> de <code>fake</code> para <code>omie</code>.
        As chaves ficam só no servidor (nunca no banco, no git ou nesta tela).</li>
      <li>Clicar em <strong>Testar conexão</strong> e depois em <strong>Sincronizar agora</strong>.</li>
      <li>Conferir no cPanel o cron <code>cron/sincronizar_erp.php</code> a cada 30 minutos (respeita o limite do Omie: 240 chamadas/min, 4 ao mesmo tempo, 100 registros por página).</li>
    </ol></section>
</div>
