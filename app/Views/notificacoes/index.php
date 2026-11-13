<?php
/** Histórico das notificações da pessoa. Variáveis: $itens */
$naoLidas = count(array_filter($itens, fn($n) => !$n['lida_em']));
$tipos = ['pedido_aprovar' => 'Aprovação', 'pedido_aprovado' => 'Pedido', 'pedido_recusado' => 'Pedido', 'pedido_faturado' => 'Pedido', 'pedido_catalogo' => 'Catálogo',
    'lead_novo' => 'Lead', 'cliente_recebido' => 'Carteira', 'cliente_atualizado' => 'Cliente', 'post_aprovar' => 'Marketing', 'post_ajuste' => 'Marketing'];
?>
<div class="cabecalho-secao">
  <div><div class="sobretitulo">AVISOS</div><h1>Notificações</h1>
    <p>Tudo o que aconteceu e precisa da sua atenção. Clique para abrir; o que você abriu fica marcado como lido.</p></div>
  <?php if ($naoLidas): ?><div class="acoes"><form method="post" action="<?= url('/notificacoes/marcar-todas') ?>"><?= csrf_campo() ?><button class="botao contorno" type="submit">Marcar todas como lidas</button></form></div><?php endif; ?>
</div>
<section class="card sem-espaco">
  <div class="sino-lista notif-pagina">
    <?php foreach ($itens as $n): ?>
      <a href="<?= url('/notificacoes/' . (int) $n['id']) ?>" class="<?= $n['lida_em'] ? '' : 'nao-lida' ?>" data-notificacao="<?= (int) $n['id'] ?>">
        <strong><span class="selo cinza"><?= e($tipos[$n['tipo']] ?? 'Aviso') ?></span> <?= e($n['titulo']) ?></strong>
        <?php if ($n['texto']): ?><span><?= e($n['texto']) ?></span><?php endif; ?>
        <small><?= data_br($n['criado_em'], true) ?><?= $n['lida_em'] ? ' · lida' : '' ?></small>
      </a>
    <?php endforeach; ?>
    <?php if (!$itens): ?><p class="suave">Nenhuma notificação por enquanto. Quando chegar um pedido para aprovar, um lead novo ou alguém mexer num cliente seu, aparece aqui e no sino do topo.</p><?php endif; ?>
  </div>
</section>
