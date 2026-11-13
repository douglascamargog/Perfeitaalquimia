<?php
/**
 * Página do cliente: tudo da empresa numa tela. A gaveta da Carteira continua como acesso rápido.
 * Variáveis: $c, $resumo (12 meses), $primeiraCompra, $todosPedidos, $maisComprados, $marca (produtos com a marca dele),
 *            $tarefas (abertas), $linhaDoTempo, $ficha (HTML da ficha, mesma da gaveta), $cobranca
 */
use App\Core\Auth;
use App\Models\ClienteEvento;
use App\Services\Normalizar;

$icones = asset('img/icones.svg');
$id = (int) $c['id'];
$ticket = $resumo['pedidos'] ? (float) $resumo['faturado'] / (int) $resumo['pedidos'] : 0;
$previsao = $c['intervalo_medio_dias'] && $c['ultima_compra_em'] ? strtotime($c['ultima_compra_em'] . ' +' . (int) $c['intervalo_medio_dias'] . ' days') : null;
$diasSemCompra = $c['ultima_compra_em'] ? (int) floor((time() - strtotime($c['ultima_compra_em'])) / 86400) : null;
?>
<div class="cl-topo">
  <div>
    <div class="sobretitulo"><a href="<?= url('/carteira/lista') ?>">CARTEIRA</a> · <?= e(mb_strtoupper($c['segmento'] ?? 'SEM SEGMENTO')) ?> · <?= $c['tipo'] === 'cliente' ? 'CLIENTE' : 'LEAD' ?></div>
    <h1><?= e($c['nome_fantasia'] ?: $c['razao_social']) ?></h1>
    <p><?= e($c['razao_social']) ?><?= $c['cnpj'] ? ' · CNPJ ' . e(cnpj_formatado($c['cnpj'])) : '' ?> · <?= e($c['cidade']) ?>/<?= e($c['uf']) ?></p>
    <div class="ficha-selos">
      <span class="selo-etapa" style="--cor: <?= e($c['etapa_cor']) ?>"><?= e($c['etapa']) ?></span>
      <span class="selo prioridade-<?= e(Normalizar::semAcento($c['prioridade'])) ?>"><?= e($c['prioridade']) ?></span>
      <?php if ($cobranca): ?><span class="selo vermelho">Em cobrança</span><?php elseif ($c['credito_situacao'] === 'atraso'): ?><span class="selo vermelho">Título em atraso</span><?php endif; ?>
      <?php if ($marca): ?><span class="selo verde">Terceirização · <?= count($marca) ?> produto(s)</span><?php endif; ?>
    </div>
  </div>
  <div class="acoes">
    <?php if (Auth::pode('pedidos.criar')): ?><a class="botao" href="<?= url('/pedidos/novo') ?>?cliente=<?= $id ?>"><svg class="icone"><use href="<?= $icones ?>#carrinho"/></svg>Novo pedido</a><?php endif; ?>
    <a class="botao contorno" href="<?= url("/carteira/clientes/$id/editar") ?>"><svg class="icone"><use href="<?= $icones ?>#editar"/></svg>Editar cadastro</a>
  </div>
</div>

<div class="cl-kpis">
  <div class="card cl-kpi"><span>Comprou em 12 meses</span><strong><?= dinheiro((float) $resumo['faturado']) ?></strong><small><?= (int) $resumo['pedidos'] ?> pedido(s) faturado(s)</small></div>
  <div class="card cl-kpi"><span>Ticket médio</span><strong><?= $ticket ? dinheiro($ticket) : '—' ?></strong><small>por pedido nos últimos 12 meses</small></div>
  <div class="card cl-kpi <?= $diasSemCompra !== null && $diasSemCompra > 60 ? 'cl-alerta' : '' ?>"><span>Última compra</span><strong><?= $c['ultima_compra_em'] ? data_br($c['ultima_compra_em']) : '—' ?></strong>
    <small><?= $diasSemCompra !== null ? "há $diasSemCompra dia(s)" : 'ainda não comprou' ?><?= $primeiraCompra ? ' · cliente desde ' . data_br($primeiraCompra) : '' ?></small></div>
  <div class="card cl-kpi <?= $previsao && $previsao < strtotime('today') ? 'cl-alerta' : '' ?>"><span>Próxima compra prevista</span><strong><?= $previsao ? date('d/m/Y', $previsao) : '—' ?></strong>
    <small><?= $c['intervalo_medio_dias'] ? 'compra a cada ~' . (int) $c['intervalo_medio_dias'] . ' dias' . ($previsao < strtotime('today') ? ' · recompra atrasada' : '') : 'sem histórico suficiente' ?></small></div>
</div>

<div class="cl-grade">
  <div class="cl-principal">
    <?php if ($tarefas): ?>
    <section class="card">
      <h2>Retornos e tarefas abertas</h2>
      <ul class="cl-tarefas">
        <?php foreach ($tarefas as $t): $atrasada = $t['vence_em'] < date('Y-m-d'); ?>
          <li class="<?= $atrasada ? 'atrasada' : '' ?>"><strong><?= e($t['titulo']) ?></strong>
            <small><?= data_br($t['vence_em']) ?><?= $t['hora'] ? ' ' . substr($t['hora'], 0, 5) : '' ?> · <?= e($t['usuario'] ?? '') ?><?= $atrasada ? ' · atrasada' : '' ?></small></li>
        <?php endforeach; ?>
      </ul>
    </section>
    <?php endif; ?>

    <section class="card sem-espaco">
      <header class="cl-secao-topo"><h2>Pedidos</h2>
        <?php if (Auth::pode('pedidos.criar') && $todosPedidos): ?><a class="botao contorno pequeno" href="<?= url('/pedidos/novo') ?>?cliente=<?= $id ?>&repetir=1">↻ Repetir último</a><?php endif; ?></header>
      <div class="tabela-rolagem">
        <table class="tabela" data-pedidos-cliente>
          <thead><tr><th>Pedido</th><th>Data</th><th>Origem</th><th>Vendedor</th><th>Pagamento</th><th>Situação</th><th class="direita">Total</th></tr></thead>
          <tbody>
          <?php foreach ($todosPedidos as $pd): [$rot, $cor] = \App\Services\PedidoService::SITUACOES[$pd['situacao']] ?? [$pd['situacao'], 'cinza']; ?>
            <tr><td><a href="<?= url('/pedidos/' . (int) $pd['id']) ?>"><strong>#<?= (int) $pd['id'] ?></strong></a></td><td class="pequeno"><?= data_br($pd['criado_em']) ?></td>
              <td class="pequeno"><?= e(ucfirst($pd['origem'])) ?></td><td class="pequeno"><?= e($pd['vendedor'] ?? '—') ?></td><td class="pequeno"><?= e($pd['forma']) ?></td>
              <td><span class="selo <?= $cor ?>"><?= $rot ?></span></td><td class="direita"><strong><?= e(dinheiro((float) $pd['total'])) ?></strong></td></tr>
          <?php endforeach; ?>
          <?php if (!$todosPedidos): ?><tr><td colspan="7" class="suave">Nenhum pedido ainda.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </section>

    <section class="card sem-espaco">
      <header class="cl-secao-topo"><h2>O que mais compra</h2><span class="suave pequeno">últimos 12 meses</span></header>
      <div class="tabela-rolagem">
        <table class="tabela">
          <thead><tr><th>Produto</th><th class="direita">Unidades</th><th class="direita">Valor</th><th>Última vez</th></tr></thead>
          <tbody>
          <?php foreach ($maisComprados as $m): ?>
            <tr><td><?= $m['codigo'] ? '<span class="cod-produto">' . e($m['codigo']) . '</span> ' : '' ?><?= e($m['nome']) ?></td>
              <td class="direita"><?= number_format((int) $m['quantidade'], 0, ',', '.') ?></td><td class="direita"><?= dinheiro((float) $m['valor']) ?></td>
              <td class="pequeno"><?= $m['ultima'] ? data_br($m['ultima']) : '—' ?></td></tr>
          <?php endforeach; ?>
          <?php if (!$maisComprados): ?><tr><td colspan="4" class="suave">Sem compras faturadas nos últimos 12 meses.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </section>

    <?php if ($marca): ?>
    <section class="card sem-espaco">
      <header class="cl-secao-topo"><h2>Produtos com a marca do cliente</h2>
        <a class="botao contorno pequeno" href="<?= url('/terceirizacao/produtos?cliente=' . $id) ?>">Ver na Terceirização</a></header>
      <div class="tabela-rolagem">
        <table class="tabela">
          <thead><tr><th>Produto (rótulo do cliente)</th><th>Tipo</th><th class="direita">Preço</th><th class="direita">Rótulos</th></tr></thead>
          <tbody>
          <?php foreach ($marca as $t): $alerta = (int) $t['rotulos_estoque'] <= (int) $t['rotulos_minimo']; ?>
            <tr><td><?= $t['codigo_cliente'] ? '<span class="cod-produto">' . e($t['codigo_cliente']) . '</span> ' : '' ?><?= e($t['nome_rotulo']) ?></td>
              <td><span class="selo <?= $t['cliente_exclusivo_id'] ? 'laranja' : 'verde' ?>"><?= $t['cliente_exclusivo_id'] ? 'fórmula própria' : 'nosso produto' ?></span></td>
              <td class="direita"><?= $t['preco_final'] !== null ? dinheiro((float) $t['preco_final']) : '—' ?></td>
              <td class="direita"><strong class="<?= $alerta ? 'pn-vermelho' : '' ?>"><?= number_format((int) $t['rotulos_estoque'], 0, ',', '.') ?></strong></td></tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>
    <?php endif; ?>

    <section class="card">
      <h2>Linha do tempo</h2>
      <?php if (!$linhaDoTempo): ?><p class="suave pequeno">Nenhum registro ainda.</p><?php endif; ?>
      <ol class="linha-tempo cl-linha-tempo">
        <?php foreach ($linhaDoTempo as $ev): ?>
          <li class="tipo-<?= e($ev['tipo']) ?>">
            <strong><?= e(ClienteEvento::TIPOS[$ev['tipo']] ?? $ev['tipo']) ?><?= $ev['canal'] && $ev['tipo'] !== 'ligacao' ? ' · ' . e($ev['canal']) : '' ?></strong>
            <?php if ($ev['descricao']): ?><span><?= e($ev['descricao']) ?></span><?php endif; ?>
            <small><?= data_br($ev['ocorrido_em'], true) ?> · <?= e($ev['usuario_nome'] ?? 'Sistema') ?></small>
          </li>
        <?php endforeach; ?>
      </ol>
    </section>
  </div>

  <!-- A mesma ficha da gaveta: contatos, mensagem pronta, dados, gestão comercial, registrar contato... -->
  <aside class="card cl-ficha sem-espaco" data-pagina-cliente="<?= $id ?>"><?= $ficha ?></aside>
</div>
<div class="aviso-flutuante" data-aviso-flutuante role="status" aria-live="polite"></div>
