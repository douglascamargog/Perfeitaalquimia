<?php
/**
 * Equipe de vendas. Variáveis: $mes, $frente, $equipe, $podeEquipe, $frentes, $ranking, $grafico
 * Todos da equipe veem tudo (inclusive o ranking com nomes); só quem gerencia metas lança o vendido da equipe.
 */
use App\Core\Visao;
use App\Services\EquipeService;
use App\Services\EquipeVendasService as E;

$meses = [1 => 'janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
$nomeMes = fn(string $m) => ucfirst($meses[(int) substr($m, 5, 2)]) . ' de ' . substr($m, 0, 4);
$pct = fn(float $v) => number_format($v, 1, ',', '.') . '%';
$ou = fn($v) => $v === null ? '—' : dinheiro($v);
$anterior = date('Y-m', strtotime("$mes-01 -1 month"));
$seguinte = date('Y-m', strtotime("$mes-01 +1 month"));
$link = fn(array $p) => url('/equipe') . '?' . e(http_build_query($p + ['mes' => $mes]));
?>
<div class="cabecalho-secao">
  <div><div class="sobretitulo">COMERCIAL · EQUIPE DE VENDAS</div><h1>Equipe de vendas</h1>
    <p>A meta da equipe e como está cada frente de venda: Externos, Televendas e Terceirização. Só pedidos faturados contam.</p></div>
  <div class="acoes seletor-mes">
    <a class="botao-icone" href="<?= $link(['mes' => $anterior, 'frente' => $frente]) ?>" aria-label="Mês anterior">←</a>
    <form method="get" action="<?= url('/equipe') ?>"><input type="month" name="mes" value="<?= e($mes) ?>" aria-label="Mês" onchange="this.form.submit()"></form>
    <a class="botao-icone" href="<?= $link(['mes' => $seguinte, 'frente' => $frente]) ?>" aria-label="Próximo mês">→</a>
  </div>
</div>

<?= Visao::renderizar('meu_mes/_equipe', compact('mes', 'equipe', 'podeEquipe', 'nomeMes', 'pct', 'ou')) ?>

<div class="eq-frentes">
  <?php foreach ($frentes as $chave => $f): ?>
    <section class="card eq-frente" data-frente="<?= e($chave) ?>">
      <header><span class="sobretitulo"><?= e(mb_strtoupper($f['nome'])) ?></span>
        <?php if ($f['percentual'] !== null): ?><b class="eq-pct"><?= $pct($f['percentual']) ?></b><?php endif; ?></header>
      <strong class="eq-valor"><?= dinheiro($f['faturado']) ?></strong>
      <?php if ($f['meta'] > 0): ?>
        <div class="barra-meta clara"><span style="width: <?= min(100, $f['percentual']) ?>%"></span></div>
        <small class="suave">meta da frente <?= dinheiro($f['meta']) ?></small>
      <?php else: ?><small class="suave">sem meta das funções desta frente em <?= e($nomeMes($mes)) ?> (Gestão › Metas)</small><?php endif; ?>
      <dl>
        <div><dt>Pedidos</dt><dd><?= (int) $f['pedidos'] ?></dd></div>
        <div><dt>Ticket médio</dt><dd><?= dinheiro($f['ticket']) ?></dd></div>
        <div><dt>Compraram</dt><dd><?= (int) $f['compraram'] ?></dd></div>
        <div><dt>Carteira</dt><dd><?= (int) $f['clientes'] ?> <small><?= (int) $f['parados'] ?> sem compra há <?= (int) $f['dias_parado'] ?>+ dias</small></dd></div>
      </dl>
    </section>
  <?php endforeach; ?>
</div>

<div class="eq-grade">
  <section class="card sem-espaco">
    <nav class="abas eq-abas" aria-label="Frente">
      <a href="<?= $link([]) ?>" class="<?= $frente === '' ? 'ativa' : '' ?>">Todas</a>
      <?php foreach (E::FRENTES as $chave => $nome): ?><a href="<?= $link(['frente' => $chave]) ?>" class="<?= $frente === $chave ? 'ativa' : '' ?>"><?= e($nome) ?></a><?php endforeach; ?>
    </nav>
    <div class="tabela-rolagem">
      <table class="tabela tabela-carteira" data-ranking>
        <thead><tr><th>#</th><th>Vendedor</th><th>Frente</th><th class="direita">Faturado</th><th class="direita">Pedidos</th><th>Meta individual</th></tr></thead>
        <tbody>
        <?php foreach ($ranking as $i => $r): ?>
          <tr>
            <td><b class="eq-posicao <?= $i < 3 && $r['faturado'] > 0 ? 'top' : '' ?>"><?= $i + 1 ?></b></td>
            <td><div class="eq-pessoa"><?= EquipeService::avatar($r, 30) ?><strong><?= e($r['apelido'] ?: $r['nome']) ?></strong></div></td>
            <td class="pequeno"><?= e(E::FRENTES[$r['frente']] ?? '—') ?></td>
            <td class="direita"><strong><?= dinheiro($r['faturado']) ?></strong></td>
            <td class="direita"><?= (int) $r['pedidos'] ?></td>
            <td><?php if ($r['percentual'] !== null): ?><div class="eq-meta-ind"><div class="barra-meta clara"><span style="width: <?= min(100, $r['percentual']) ?>%"></span></div><small><?= $pct($r['percentual']) ?> de <?= dinheiro((float) $r['meta']) ?></small></div><?php else: ?><small class="suave">sem meta</small><?php endif; ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$ranking): ?><tr><td colspan="6" class="suave">Ninguém nesta frente ainda.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>
  <section class="card">
    <h2>Faturado por frente</h2>
    <p class="suave pequeno">Últimos 6 meses</p>
    <div class="eq-grafico"><canvas id="grafico-frentes" aria-label="Faturado por frente nos últimos 6 meses"></canvas></div>
    <script type="application/json" id="dados-equipe"><?= json_encode($grafico, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?></script>
  </section>
</div>
