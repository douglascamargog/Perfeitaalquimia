<?php
/**
 * Fila de recompra: clientes que passaram do próprio tempo de recompra.
 * Variáveis: $clientes (VendasService::recompra + mensagem), $vendedor, $vendedores
 */
use App\Core\Auth;
use App\Core\Visao;
use App\Services\Normalizar;

$icones = asset('img/icones.svg');
$amanha = date('Y-m-d', strtotime('+1 weekday'));
?>
<div class="cabecalho-secao">
  <div><div class="sobretitulo">COMERCIAL · ALERTA DE RECOMPRA</div><h1>Recompra</h1>
    <p>Clientes que já passaram do tempo normal entre as compras deles. Os mais valiosos e mais atrasados aparecem primeiro.</p></div>
  <?php if ($vendedores): ?>
    <form method="get" class="acoes" data-filtros>
      <select name="vendedor" aria-label="Vendedor" onchange="this.form.submit()"><option value="">Todos os vendedores</option>
        <?php foreach ($vendedores as $v): ?><option value="<?= (int) $v['id'] ?>" <?= (int) $vendedor === (int) $v['id'] ? 'selected' : '' ?>><?= e($v['nome']) ?></option><?php endforeach; ?></select>
    </form>
  <?php endif; ?>
</div>

<?php if (!$clientes): ?>
  <div class="card ped-vazio"><img src="<?= asset('img/one/one-feliz-padrao.svg') ?>" alt="" width="80" height="88"><p>Nenhum cliente atrasado na recompra. Carteira em dia!</p></div>
<?php endif; ?>
<div class="rc-lista">
  <?php foreach ($clientes as $c): $whats = $c['whatsapp'] ?: (strlen((string) $c['telefone']) === 11 ? $c['telefone'] : ''); $pct = min(100, round($c['dias_desde'] / max(1, $c['intervalo_medio_dias']) * 50)); ?>
    <article class="card rc-item" data-recompra="<?= (int) $c['id'] ?>">
      <div class="rc-principal">
        <button class="nome-cliente" type="button" data-abrir-cliente="<?= (int) $c['id'] ?>"><?= e($c['nome_fantasia'] ?: $c['razao_social']) ?></button><?= \App\Services\NotificacaoService::marca((int) $c['id']) ?>
        <small class="suave"><?= e($c['cidade']) ?>/<?= e($c['uf']) ?><?= Auth::pode('carteira.ver_todas') || \App\Services\Setores::gerenciados() ? ' · ' . e($c['responsavel']) : '' ?></small>
        <?php if ($c['produtos']): ?><small>Costuma comprar: <?= e(implode(', ', $c['produtos'])) ?></small><?php endif; ?>
      </div>
      <div class="rc-numeros">
        <div><span>Última compra</span><strong><?= data_br($c['ultima_compra_em']) ?></strong><small>há <?= (int) $c['dias_desde'] ?> dias</small></div>
        <div><span>Compra a cada</span><strong>~<?= (int) $c['intervalo_medio_dias'] ?> dias</strong></div>
        <div class="rc-atraso"><span>Atrasado</span><strong><?= (int) $c['dias_atraso'] ?> dia(s)</strong></div>
        <div><span>Ticket médio</span><strong><?= dinheiro($c['ticket']) ?></strong></div>
      </div>
      <div class="ficha-acoes">
        <?php if (Auth::pode('pedidos.criar')): ?><a class="botao lima pequeno" href="<?= url('/pedidos/novo') ?>?cliente=<?= (int) $c['id'] ?>&repetir=1">Novo pedido (repetir)</a><?php endif; ?>
        <?php if ($whats): ?><a class="botao contorno pequeno" href="https://wa.me/55<?= e($whats) ?>?text=<?= rawurlencode($c['mensagem']) ?>" target="_blank" rel="noopener noreferrer"><svg class="icone"><use href="<?= $icones ?>#conversa"/></svg>WhatsApp</a><?php endif; ?>
        <?php if ($c['telefone']): ?><a class="botao contorno pequeno" href="tel:+55<?= e($c['telefone']) ?>"><svg class="icone"><use href="<?= $icones ?>#telefone"/></svg><?= e(Normalizar::telefoneFormatado($c['telefone'])) ?></a><?php endif; ?>
        <form method="post" action="<?= url('/recompra/' . (int) $c['id'] . '/agendar') ?>" class="rc-agendar">
          <?= csrf_campo() ?><input type="date" name="data" value="<?= $amanha ?>" min="<?= date('Y-m-d') ?>" aria-label="Data do retorno">
          <button class="botao contorno pequeno" type="submit">Agendar retorno</button>
        </form>
        <?php if ($c['tarefas_abertas']): ?><span class="selo cinza">retorno já agendado</span><?php endif; ?>
      </div>
    </article>
  <?php endforeach; ?>
</div>
<?= Visao::renderizar('carteira/_drawer') ?>
