<?php
/** Lista de ordens de produção. Variáveis: $f, $r (itens, total, pagina, paginas), $produtos, $pode */
use App\Core\Visao;
use App\Services\ProducaoService as P;

$icones = asset('img/icones.svg');
$link = fn(int $p) => url('/producao/ordens') . '?' . e(http_build_query(array_filter($f) + ['pagina' => $p]));
?>
<div class="cabecalho-secao">
  <div><div class="sobretitulo">PRODUÇÃO · ORDENS</div><h1>Ordens de produção</h1>
    <p>Ao concluir, o sistema baixa as matérias-primas da ficha técnica e soma o produto acabado.</p></div>
  <?php if ($pode): ?><div class="acoes"><a class="botao" href="<?= url('/producao/ordens/nova') ?>"><svg class="icone"><use href="<?= $icones ?>#mais"/></svg>Nova ordem</a></div><?php endif; ?>
</div>
<?= Visao::renderizar('producao/_abas', ['atual' => '/producao/ordens']) ?>
<form class="filtros card" method="get" action="<?= url('/producao/ordens') ?>" data-filtros>
  <div class="filtros-busca"><svg class="icone"><use href="<?= $icones ?>#lupa"/></svg>
    <input type="search" name="q" value="<?= e($f['q']) ?>" placeholder="Nº da ordem, lote ou produto…" aria-label="Buscar"></div>
  <div class="filtros-grade">
    <select name="situacao" aria-label="Situação"><option value="">Todas as situações</option>
      <?php foreach (P::SITUACOES as $k => [$nome]): ?><option value="<?= $k ?>" <?= $f['situacao'] === $k ? 'selected' : '' ?>><?= $nome ?></option><?php endforeach; ?></select>
    <select name="produto" aria-label="Produto"><option value="">Todos os produtos</option>
      <?php foreach ($produtos as $p): ?><option value="<?= (int) $p['id'] ?>" <?= $f['produto'] === (int) $p['id'] ? 'selected' : '' ?>><?= e($p['nome']) ?></option><?php endforeach; ?></select>
  </div>
</form>
<section class="card sem-espaco">
  <div class="tabela-rolagem">
    <table class="tabela tabela-carteira pr-tabela">
      <thead><tr><th>Ordem</th><th>Produto</th><th class="direita">Quantidade</th><th>Lote</th><th>Data</th><th class="direita">Custo MP</th><th>Situação</th></tr></thead>
      <tbody>
      <?php foreach ($r['itens'] as $o): [$rotulo, $cor] = P::SITUACOES[$o['situacao']]; ?>
        <tr data-ordem="<?= (int) $o['id'] ?>">
          <td><a href="<?= url('/producao/ordens/' . (int) $o['id']) ?>"><strong>OP <?= (int) $o['id'] ?></strong></a><?= $o['origem'] === 'reabastecimento' ? '<small class="suave">reabastecimento</small>' : '' ?></td>
          <td><?= e($o['produto']) ?></td>
          <td class="direita"><?= number_format((int) $o['quantidade'], 0, ',', '.') ?> un</td>
          <td class="pequeno"><?= e($o['lote'] ?? '—') ?></td>
          <td class="pequeno"><?= $o['situacao'] === 'concluida' ? data_br($o['concluida_em']) : ($o['previsto_para'] ? 'para ' . data_br($o['previsto_para']) : data_br($o['criado_em'])) ?></td>
          <td class="direita pequeno"><?= $o['custo_total'] !== null ? dinheiro((float) $o['custo_total']) : '—' ?></td>
          <td><span class="selo <?= $cor ?>"><?= $rotulo ?></span></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$r['itens']): ?><tr><td colspan="7" class="vazio">Nenhuma ordem com esses filtros.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
  <div class="paginacao-barra">
    <span><?= number_format($r['total'], 0, ',', '.') ?> ordem(ns)</span>
    <?php if ($r['paginas'] > 1): ?>
      <nav class="paginacao" aria-label="Páginas">
        <?php if ($r['pagina'] > 1): ?><a class="botao contorno pequeno" href="<?= $link($r['pagina'] - 1) ?>">‹ Anterior</a><?php endif; ?>
        <span class="pequeno">Página <?= $r['pagina'] ?> de <?= $r['paginas'] ?></span>
        <?php if ($r['pagina'] < $r['paginas']): ?><a class="botao contorno pequeno" href="<?= $link($r['pagina'] + 1) ?>">Próxima ›</a><?php endif; ?>
      </nav>
    <?php endif; ?>
  </div>
</section>
