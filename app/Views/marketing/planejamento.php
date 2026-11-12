<?php
/**
 * Planejamento: campanhas em linha do tempo (3 meses) + datas comemorativas + cadastro.
 * Variáveis: $mes, $desde, $ate, $campanhas, $datas, $editar (campanha em edição ou null), $produtos
 */
use App\Core\Auth;
use App\Core\Visao;

$inicio = strtotime($desde);
$totalDias = (int) round((strtotime($ate) - $inicio) / 86400) + 1;
$pos = fn(string $data) => max(0, min(100, (strtotime($data) - $inicio) / 86400 / $totalDias * 100));
$meses = ['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'];
$situacoes = ['planejada' => ['Planejada', 'cinza'], 'ativa' => ['Ativa', 'lima'], 'encerrada' => ['Encerrada', 'cinza']];
$c = $editar ?? [];
$form = fn(string $campo, $padrao = '') => antigo($campo) !== '' ? antigo($campo) : e((string) ($c[$campo] ?? $padrao)); // antigo() já vem escapado
?>
<?= Visao::renderizar('marketing/_cabecalho', ['aba' => 'planejamento', 'mes' => $mes, 'titulo' => 'Planejamento',
    'sobretitulo' => 'MARKETING · CAMPANHAS', 'subtitulo' => 'Campanhas dos próximos 3 meses, datas comemorativas e resultado no e-commerce.']) ?>

<!-- Linha do tempo -->
<section class="card mk-gantt">
  <div class="mk-gantt-meses">
    <?php for ($i = 0; $i < 3; $i++): $m = strtotime("$desde +$i months"); ?>
      <span style="left: <?= $pos(date('Y-m-d', $m)) ?>%"><?= $meses[(int) date('n', $m) - 1] ?> <?= date('Y', $m) ?></span>
    <?php endfor; ?>
  </div>
  <div class="mk-gantt-trilho">
    <?php // Datas comemorativas dos 3 meses
    for ($d = $inicio; $d <= strtotime($ate); $d = strtotime('+1 day', $d)):
        if (isset($datas[date('m-d', $d)])): ?>
        <span class="mk-gantt-data" style="left: <?= $pos(date('Y-m-d', $d)) ?>%" title="<?= date('d/m', $d) ?> · <?= e($datas[date('m-d', $d)]) ?>"><i></i><small><?= date('d/m', $d) ?> <?= e($datas[date('m-d', $d)]) ?></small></span>
    <?php endif; endfor; ?>
    <?php if (date('Y-m-d') >= $desde && date('Y-m-d') <= $ate): ?><span class="mk-gantt-hoje" style="left: <?= $pos(date('Y-m-d')) ?>%">hoje</span><?php endif; ?>
  </div>
  <div class="mk-gantt-barras">
    <?php foreach ($campanhas as $cp): $esq = $pos(max($cp['inicio'], $desde)); $dir = $pos(date('Y-m-d', strtotime(min($cp['fim'], $ate) . ' +1 day'))); ?>
      <a class="mk-gantt-barra" href="?mes=<?= e($mes) ?>&editar=<?= (int) $cp['id'] ?>#form-campanha" style="--cor: <?= e($cp['cor']) ?>; left: <?= $esq ?>%; width: <?= max(2, $dir - $esq) ?>%">
        <strong><?= e($cp['nome']) ?></strong><small><?= data_br($cp['inicio']) ?> – <?= data_br($cp['fim']) ?></small>
      </a>
    <?php endforeach; ?>
    <?php if (!$campanhas): ?><p class="b-vazio">Nenhuma campanha nestes 3 meses. Cadastre abaixo.</p><?php endif; ?>
  </div>
</section>

<!-- Campanhas -->
<div class="mk-campanhas">
  <?php foreach ($campanhas as $cp): [$rotulo, $cor] = $situacoes[$cp['situacao']] ?? ['—', 'cinza']; ?>
    <article class="b-card mk-campanha" style="--cor: <?= e($cp['cor']) ?>">
      <header class="b-topo"><span class="selo <?= $cor ?>"><?= $rotulo ?></span><?php if (Auth::pode('marketing.editar')): ?><a class="b-chip" href="?mes=<?= e($mes) ?>&editar=<?= (int) $cp['id'] ?>#form-campanha">Editar</a><?php endif; ?></header>
      <h3><?= e($cp['nome']) ?></h3>
      <p class="suave pequeno"><?= data_br($cp['inicio']) ?> a <?= data_br($cp['fim']) ?><?= $cp['objetivo'] ? ' · ' . e($cp['objetivo']) : '' ?></p>
      <?php if ($cp['meta_descricao']): ?><p class="mk-meta"><strong>Meta:</strong> <?= e($cp['meta_descricao']) ?></p><?php endif; ?>
      <div class="mk-campanha-numeros">
        <div><small>Posts</small><strong><?= (int) $cp['publicados'] ?>/<?= (int) $cp['posts'] ?></strong><span>publicados</span></div>
        <?php if ($cp['orcamento']): ?><div><small>Orçamento</small><strong><?= dinheiro((float) $cp['orcamento']) ?></strong></div><?php endif; ?>
        <?php if ($cp['produto']): ?>
          <div><small><?= e($cp['produto']) ?> no site</small>
            <?php if ($cp['vendas']): ?><strong><?= number_format((int) $cp['vendas']['quantidade'], 0, ',', '.') ?> un</strong><span><?= dinheiro((float) $cp['vendas']['faturamento']) ?></span>
            <?php else: ?><strong>—</strong><span>fora do top 10 do e-commerce</span><?php endif; ?>
          </div>
        <?php endif; ?>
      </div>
      <a class="pequeno" href="<?= url('/marketing') ?>?mes=<?= substr($cp['inicio'], 0, 7) ?>&campanha=<?= (int) $cp['id'] ?>">Ver posts da campanha →</a>
    </article>
  <?php endforeach; ?>
</div>

<!-- Cadastro -->
<?php if (Auth::pode('marketing.editar')): ?>
  <section class="card" id="form-campanha">
    <h2><?= $editar ? 'Editar campanha' : 'Nova campanha' ?></h2>
    <form method="post" action="<?= url('/marketing/campanhas' . ($editar ? '/' . (int) $editar['id'] : '')) ?>">
      <?= csrf_campo() ?>
      <div class="grade-campos">
        <div class="campo"><label for="cp-nome">Nome</label><input type="text" id="cp-nome" name="nome" maxlength="120" required value="<?= $form('nome') ?>" placeholder="Ex.: Black Friday 2026"></div>
        <div class="campo"><label for="cp-obj">Objetivo</label><input type="text" id="cp-obj" name="objetivo" maxlength="255" value="<?= $form('objetivo') ?>" placeholder="Ex.: vender no site e gerar leads de lojistas"></div>
      </div>
      <div class="grade-campos">
        <div class="campo"><label for="cp-ini">Início</label><input type="date" id="cp-ini" name="inicio" required value="<?= $form('inicio', $desde) ?>"></div>
        <div class="campo"><label for="cp-fim">Fim</label><input type="date" id="cp-fim" name="fim" required value="<?= $form('fim', date('Y-m-t', strtotime($desde))) ?>"></div>
        <div class="campo"><label for="cp-cor">Cor</label><input type="color" id="cp-cor" name="cor" value="<?= $form('cor', '#9bd344') ?>"></div>
        <div class="campo"><label for="cp-sit">Situação</label>
          <select id="cp-sit" name="situacao"><?php foreach ($situacoes as $k => [$n]): ?><option value="<?= $k ?>" <?= ($c['situacao'] ?? 'planejada') === $k ? 'selected' : '' ?>><?= $n ?></option><?php endforeach; ?></select></div>
      </div>
      <div class="grade-campos">
        <div class="campo"><label for="cp-meta">Meta</label><input type="text" id="cp-meta" name="meta_descricao" maxlength="255" value="<?= $form('meta_descricao') ?>" placeholder="Ex.: 300 unidades de Magnésio no site"></div>
        <div class="campo"><label for="cp-orc">Orçamento (R$)</label><input type="text" id="cp-orc" name="orcamento" inputmode="decimal" value="<?= isset($c['orcamento']) && $c['orcamento'] !== null ? number_format((float) $c['orcamento'], 2, ',', '.') : '' ?>"></div>
        <div class="campo"><label for="cp-prod">Produto em destaque</label>
          <select id="cp-prod" name="produto_id"><option value="">Nenhum</option><?php foreach ($produtos as $pr): ?><option value="<?= (int) $pr['id'] ?>" <?= (int) ($c['produto_id'] ?? 0) === (int) $pr['id'] ? 'selected' : '' ?>><?= e($pr['nome']) ?></option><?php endforeach; ?></select></div>
      </div>
      <div class="campo"><label for="cp-obs">Observações</label><textarea id="cp-obs" name="observacoes" rows="3"><?= $form('observacoes') ?></textarea></div>
      <button class="botao" type="submit">Salvar campanha</button>
      <?php if ($editar): ?><a class="botao contorno" href="?mes=<?= e($mes) ?>">Cancelar</a><?php endif; ?>
    </form>
  </section>
<?php endif; ?>
