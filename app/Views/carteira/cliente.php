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

<?php if (!Auth::pode('carteira.ver_propria') && !Auth::pode('carteira.ver_todas')): ?>
  <div class="aviso" data-so-consulta>Você está consultando esta loja (pela Cobrança ou pela Aprovação). Para mudar o cadastro, fale com a vendedora responsável.</div>
<?php endif; ?>
<div class="cl-kpis">
  <div class="card cl-kpi"><span>Comprou em 12 meses</span><strong><?= dinheiro((float) $resumo['faturado']) ?></strong><small><?= (int) $resumo['pedidos'] ?> pedido(s) faturado(s)</small></div>
  <div class="card cl-kpi" data-media-mensal><span>Média por mês</span><strong><?= (float) $resumo['faturado'] > 0 ? dinheiro((float) $resumo['faturado'] / 12) : '—' ?></strong><small>o que comprou em 12 meses ÷ 12</small></div>
  <div class="card cl-kpi"><span>Ticket médio</span><strong><?= $ticket ? dinheiro($ticket) : '—' ?></strong><small>por pedido nos últimos 12 meses</small></div>
  <div class="card cl-kpi <?= $diasSemCompra !== null && $diasSemCompra > 60 ? 'cl-alerta' : '' ?>"><span>Última compra</span><strong><?= $c['ultima_compra_em'] ? data_br($c['ultima_compra_em']) : '—' ?></strong>
    <small><?= $diasSemCompra !== null ? "há $diasSemCompra dia(s)" : 'ainda não comprou' ?><?= $primeiraCompra ? ' · cliente desde ' . data_br($primeiraCompra) : '' ?></small></div>
  <div class="card cl-kpi <?= $previsao && $previsao < strtotime('today') ? 'cl-alerta' : '' ?>"><span>Próxima compra prevista</span><strong><?= $previsao ? date('d/m/Y', $previsao) : '—' ?></strong>
    <small><?= $c['intervalo_medio_dias'] ? 'compra a cada ~' . (int) $c['intervalo_medio_dias'] . ' dias' . ($previsao < strtotime('today') ? ' · recompra atrasada' : '') : 'sem histórico suficiente' ?></small></div>
</div>

<div class="cl-grade">
  <div class="cl-principal">
    <div class="cl-duas">
      <section class="card cl-bloco">
        <header class="cl-bloco-topo"><h2>Próximas atividades</h2><?php if ($tarefas): ?><span class="cl-contador"><?= count($tarefas) ?></span><?php endif; ?></header>
        <?php if (!$tarefas): ?>
          <p class="cl-vazio">Nada agendado. Use a aba <strong>Agendar</strong> na linha do tempo.</p>
        <?php else: ?>
        <ul class="cl-ativs" data-proximas>
          <?php foreach ($tarefas as $i => $t): $atrasada = $t['vence_em'] < date('Y-m-d'); $hoje = $t['vence_em'] === date('Y-m-d'); $ts = strtotime($t['vence_em']); ?>
            <li class="cl-ativ <?= $atrasada ? 'atrasada' : ($hoje ? 'hoje' : '') ?>" data-tarefa="<?= (int) $t['id'] ?>" <?= $i >= 4 ? 'data-a-mais hidden' : '' ?>>
              <span class="cl-ativ-data"><b><?= date('d', $ts) ?></b><small><?= mes_curto((int) date('n', $ts)) ?></small></span>
              <div class="cl-ativ-txt"><strong><?= e($t['titulo']) ?></strong>
                <small><?= $atrasada ? '<em>atrasada</em> · ' : ($hoje ? '<em>hoje</em> · ' : '') ?><?= $t['hora'] ? substr($t['hora'], 0, 5) . ' · ' : '' ?><?= pessoa($t['usuario'] ?? null, $t['usuario_foto'] ?? null, true, 16) ?></small></div>
              <?php if (!Auth::somenteAcompanha() && ((int) $t['usuario_id'] === Auth::id() || Auth::pode('carteira.ver_todas'))): ?>
                <form method="post" action="<?= url('/agenda/' . (int) $t['id'] . '/concluir') ?>"><?= csrf_campo() ?><input type="hidden" name="voltar" value="/carteira/clientes/<?= $id ?>"><button class="cl-ok" type="submit" title="Concluir" aria-label="Concluir: <?= e($t['titulo']) ?>">✓</button></form>
              <?php endif; ?></li>
          <?php endforeach; ?>
        </ul>
        <?php if (count($tarefas) > 4): ?><button class="cl-mais" type="button" data-ver-mais="[data-proximas]">Ver mais <?= count($tarefas) - 4 ?></button><?php endif; ?>
        <?php endif; ?>
      </section>

      <section class="card cl-bloco">
        <header class="cl-bloco-topo"><h2>O que mais compra</h2><span class="suave pequeno">últimos 12 meses</span></header>
        <?php if (!$maisComprados): ?><p class="cl-vazio">Sem compras faturadas nos últimos 12 meses.</p><?php else: $maior = max(array_map(fn($m) => (float) $m['valor'], $maisComprados)) ?: 1; ?>
        <ol class="cl-top" data-mais-comprados>
          <?php foreach ($maisComprados as $i => $m): ?>
            <li <?= $i >= 5 ? 'data-a-mais hidden' : '' ?>>
              <span class="cl-top-foto"><?php if ($m['imagem']): ?><img src="<?= url('/' . e($m['imagem'])) ?>" alt="" loading="lazy" width="44" height="44"><?php else: ?><b><?= e(mb_strtoupper(mb_substr((string) $m['nome'], 0, 2))) ?></b><?php endif; ?></span>
              <div class="cl-top-txt">
                <strong title="<?= e($m['nome']) ?>"><?= $m['slug'] ? '<a href="' . url('/produtos/' . e($m['slug'])) . '">' . e($m['nome']) . '</a>' : e($m['nome']) ?></strong>
                <small><?= $m['codigo'] ? '<span class="cod-produto">' . e($m['codigo']) . '</span> ' : '' ?><?= number_format((int) $m['quantidade'], 0, ',', '.') ?> un · <?= (int) $m['vezes'] ?>× · última <?= $m['ultima'] ? data_br($m['ultima']) : '—' ?></small>
                <span class="cl-top-barra"><i style="width: <?= round((float) $m['valor'] / $maior * 100) ?>%"></i></span>
              </div>
              <span class="cl-top-valor"><?= dinheiro((float) $m['valor']) ?></span>
            </li>
          <?php endforeach; ?>
        </ol>
        <?php if (count($maisComprados) > 5): ?><button class="cl-mais" type="button" data-ver-mais="[data-mais-comprados]">Ver todos (<?= count($maisComprados) ?>)</button><?php endif; ?>
        <?php endif; ?>
      </section>
    </div>

    <section class="card cl-bloco" data-pagina-cliente="<?= $id ?>">
      <header class="cl-bloco-topo"><h2>Linha do tempo</h2><span class="suave pequeno"><?= count($linhaDoTempo) ?> registro(s)</span></header>
      <?php if (!Auth::somenteAcompanha() || Auth::pode('diretoria.vende') && (int) $c['responsavel_id'] === Auth::id()): ?>
        <?= \App\Core\Visao::renderizar('carteira/_compositor', ['id' => $id, 'canais' => array_keys(\App\Services\CarteiraService::CANAIS)]) ?>
      <?php endif; ?>
      <?php if (!$linhaDoTempo): ?><p class="cl-vazio">Nenhum registro ainda.</p><?php else: ?>
        <div class="cl-filtros" role="group" aria-label="Filtrar a linha do tempo" data-filtro-tempo>
          <?php foreach (['' => 'Tudo', 'contato' => 'Contatos', 'nota' => 'Anotações', 'pedido' => 'Pedidos e orçamentos', 'etapa' => 'Etapas e cadastro', 'cobranca' => 'Cobrança'] as $grupo => $rot): ?>
            <button type="button" class="<?= $grupo === '' ? 'ativo' : '' ?>" data-grupo="<?= $grupo ?>"><?= $rot ?></button>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
      <?php $anexos = \App\Services\ClienteAnexoService::porEvento($id); ?>
      <ol class="linha-tempo cl-linha-tempo">
        <?php foreach ($linhaDoTempo as $ev): ?><?= \App\Core\Visao::renderizar('carteira/_evento', ['ev' => $ev, 'anexos' => $anexos[(int) $ev['id']] ?? [], 'clienteId' => $id]) ?><?php endforeach; ?>
      </ol>
    </section>

    <section class="card sem-espaco cl-bloco">
      <header class="cl-secao-topo"><h2>Pedidos</h2>
        <?php if (Auth::pode('pedidos.criar') && $todosPedidos): ?><a class="botao contorno pequeno" href="<?= url('/pedidos/novo') ?>?cliente=<?= $id ?>&repetir=1">↻ Repetir último</a><?php endif; ?></header>
      <div class="tabela-rolagem cl-pedidos">
        <table class="tabela" data-pedidos-cliente>
          <thead><tr><th>Pedido</th><th>Data</th><th>Origem</th><th>Vendedor</th><th>Pagamento</th><th>Situação</th><th class="direita">Total</th></tr></thead>
          <tbody>
          <?php foreach ($todosPedidos as $pd): [$rot, $cor] = \App\Services\PedidoService::SITUACOES[$pd['situacao']] ?? [$pd['situacao'], 'cinza']; ?>
            <tr><td><a href="<?= url('/pedidos/' . (int) $pd['id']) ?>"><strong>#<?= (int) $pd['id'] ?></strong></a></td><td class="pequeno"><?= data_br($pd['criado_em']) ?></td>
              <td class="pequeno"><?= e(ucfirst($pd['origem'])) ?></td><td class="pequeno"><?= pessoa($pd['vendedor'] ?? null, $pd['vendedor_foto'] ?? null, true) ?></td><td class="pequeno"><?= e($pd['forma']) ?></td>
              <td><span class="selo <?= $cor ?>"><?= $rot ?></span></td><td class="direita"><strong><?= e(dinheiro((float) $pd['total'])) ?></strong></td></tr>
          <?php endforeach; ?>
          <?php if (!$todosPedidos): ?><tr><td colspan="7" class="suave">Nenhum pedido ainda.</td></tr><?php endif; ?>
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

  </div>

  <!-- A mesma ficha da gaveta: contatos, mensagem pronta, dados, gestão comercial, registrar contato... -->
  <aside class="card cl-ficha sem-espaco" data-pagina-cliente="<?= $id ?>"><?= $ficha ?></aside>
</div>
<div class="aviso-flutuante" data-aviso-flutuante role="status" aria-live="polite"></div>
