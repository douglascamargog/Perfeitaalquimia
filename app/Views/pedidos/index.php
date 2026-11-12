<?php
/** Lista de pedidos. Variáveis: $filtros, $resultado, $vendedores, $meuCodigo */
use App\Core\Auth;
use App\Services\PedidoService;

$r = $resultado;
$icones = asset('img/icones.svg');
$sel = fn(string $campo, $valor) => (string) $filtros[$campo] === (string) $valor ? 'selected' : '';
$linkPagina = fn(int $p) => url('/pedidos') . '?' . e(http_build_query(array_filter($filtros) + ['pagina' => $p]));
$origens = ['catalogo' => 'Catálogo', 'sistema' => 'Sistema', 'app' => 'App externo'];
?>
<div class="cabecalho-secao">
  <div>
    <div class="sobretitulo">COMERCIAL · PEDIDOS</div>
    <h1>Pedidos</h1>
    <p>Pedidos do catálogo, do sistema e do app. Todo pedido vai ao ERP como orçamento e passa pelo Financeiro.</p>
  </div>
  <div class="acoes">
    <?php if (Auth::pode('pedidos.criar')): ?><a class="botao" href="<?= url('/pedidos/novo') ?>"><svg class="icone"><use href="<?= $icones ?>#mais"/></svg>Novo pedido</a><?php endif; ?>
    <a class="botao contorno" href="<?= url('/catalogo') ?>" target="_blank" rel="noopener"><svg class="icone"><use href="<?= $icones ?>#externo"/></svg>Abrir catálogo</a>
  </div>
</div>
<?php if ($meuCodigo): $link = (isset($_SERVER['HTTP_HOST']) ? 'https://' . $_SERVER['HTTP_HOST'] : '') . url('/catalogo/v/' . rawurlencode($meuCodigo)); ?>
  <div class="ped-link-vendedor">
    <span>Seu link do catálogo (pedidos por ele ficam com você):</span>
    <code><?= e($link) ?></code>
    <button class="botao contorno pequeno" type="button" onclick="navigator.clipboard.writeText('<?= e($link) ?>').then(() => this.textContent = 'Copiado ✓')">Copiar link</button>
  </div>
<?php endif; ?>

<form class="filtros card" method="get" action="<?= url('/pedidos') ?>" data-filtros>
  <div class="filtros-busca"><svg class="icone"><use href="<?= $icones ?>#lupa"/></svg>
    <input type="search" name="q" value="<?= e($filtros['q']) ?>" placeholder="Cliente, CNPJ ou nº do pedido…" aria-label="Buscar"></div>
  <div class="filtros-grade">
    <select name="situacao" aria-label="Situação"><option value="">Todas as situações</option><?php foreach (PedidoService::SITUACOES as $k => [$n]): ?><option value="<?= $k ?>" <?= $sel('situacao', $k) ?>><?= $n ?></option><?php endforeach; ?></select>
    <select name="origem" aria-label="Origem"><option value="">Todas as origens</option><?php foreach ($origens as $k => $n): ?><option value="<?= $k ?>" <?= $sel('origem', $k) ?>><?= $n ?></option><?php endforeach; ?></select>
    <?php if ($vendedores): ?><select name="vendedor" aria-label="Vendedor"><option value="">Todos os vendedores</option><?php foreach ($vendedores as $v): ?><option value="<?= (int) $v['id'] ?>" <?= $sel('vendedor', $v['id']) ?>><?= e($v['nome']) ?></option><?php endforeach; ?></select><?php endif; ?>
    <input type="date" name="de" value="<?= e($filtros['de']) ?>" aria-label="De">
    <input type="date" name="ate" value="<?= e($filtros['ate']) ?>" aria-label="Até">
  </div>
</form>

<section class="card sem-espaco">
  <div class="tabela-rolagem">
    <table class="tabela tabela-carteira">
      <thead><tr><th>Pedido</th><th>Cliente</th><th>Origem</th><th>Vendedor</th><th>Pagamento</th><th class="direita">Total</th><th>Situação</th></tr></thead>
      <tbody>
      <?php foreach ($r['itens'] as $p): [$rotulo, $cor] = PedidoService::SITUACOES[$p['situacao']] ?? [$p['situacao'], 'cinza']; ?>
        <tr>
          <td><a href="<?= url('/pedidos/' . (int) $p['id']) ?>"><strong>#<?= (int) $p['id'] ?></strong></a><small class="suave"><?= data_br($p['criado_em'], true) ?></small></td>
          <td><?= e($p['nome_fantasia'] ?: $p['razao_social']) ?><small class="suave"><?= e($p['cidade']) ?>/<?= e($p['uf']) ?></small></td>
          <td class="pequeno"><?= $origens[$p['origem']] ?? e($p['origem']) ?></td>
          <td class="pequeno"><?= e($p['vendedor'] ?? '—') ?></td>
          <td class="pequeno"><?= e($p['forma']) ?></td>
          <td class="direita"><strong><?= dinheiro((float) $p['total']) ?></strong></td>
          <td><span class="selo <?= $cor ?>"><?= $rotulo ?></span></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$r['itens']): ?><tr><td colspan="7" class="vazio">Nenhum pedido com esses filtros.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
  <div class="paginacao-barra">
    <span><?= number_format($r['total'], 0, ',', '.') ?> pedido(s) · <?= dinheiro($r['valor']) ?></span>
    <?php if ($r['paginas'] > 1): ?>
      <nav class="paginacao" aria-label="Páginas">
        <?php if ($r['pagina'] > 1): ?><a class="botao contorno pequeno" href="<?= $linkPagina($r['pagina'] - 1) ?>">‹ Anterior</a><?php endif; ?>
        <span class="pequeno">Página <?= $r['pagina'] ?> de <?= $r['paginas'] ?></span>
        <?php if ($r['pagina'] < $r['paginas']): ?><a class="botao contorno pequeno" href="<?= $linkPagina($r['pagina'] + 1) ?>">Próxima ›</a><?php endif; ?>
      </nav>
    <?php endif; ?>
  </div>
</section>
