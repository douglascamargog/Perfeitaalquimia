<?php
/** Página da matéria-prima. Variáveis: $m (com previsão), $movimentos, $unidades, $produtos, $pode */
use App\Core\Visao;
use App\Services\ProducaoService as P;

$icones = asset('img/icones.svg');
$u = $m['unidade'];
$num = fn($valor) => rtrim(rtrim(number_format((float) $valor, 4, ',', ''), '0'), ',');
?>
<div class="cabecalho-secao">
  <div><div class="sobretitulo"><a href="<?= url('/producao/materias') ?>">MATÉRIAS-PRIMAS</a> · <?= e($m['codigo'] ?: 'SEM CÓDIGO') ?></div>
    <h1><?= e($m['nome']) ?></h1><p><?= e($m['fornecedor'] ?: 'Fornecedor não informado') ?><?= $m['ativo'] ? '' : ' · <strong>inativa</strong>' ?></p></div>
</div>
<?= Visao::renderizar('producao/_abas', ['atual' => '/producao/materias']) ?>

<div class="bento">
  <section class="b-card b-escuro span-4"><header class="b-topo"><span>Em estoque</span></header>
    <strong class="b-numero-grande" data-estoque><?= P::qtd($m['estoque'], $u) ?></strong>
    <p class="b-sub">Mínimo <?= P::qtd($m['estoque_minimo'], $u) ?><?= $m['abaixo'] ? ' · <strong class="mk-lima">abaixo do mínimo</strong>' : '' ?></p></section>
  <section class="b-card b-kpi span-3"><header class="b-topo"><span>Consumo médio</span></header>
    <strong class="b-valor"><?= $m['consumo_dia'] > 0 ? P::qtd(round($m['consumo_dia'], 3), $u) : '—' ?></strong><small>por dia (últimos <?= (int) \App\Core\Config::get('producao.dias_consumo', 30) ?> dias)</small></section>
  <section class="b-card b-kpi span-3 <?= $m['acabando'] ? 'pr-kpi-alerta' : '' ?>"><header class="b-topo"><span>Acaba em</span></header>
    <strong class="b-valor" data-previsao><?= $m['dias_restantes'] !== null ? '~' . (int) $m['dias_restantes'] . ' dias' : '—' ?></strong><small><?= $m['acaba_em'] ? data_br($m['acaba_em']) : 'sem consumo recente' ?></small></section>
  <section class="b-card b-kpi b-lima span-2"><header class="b-topo"><span>Último custo</span></header>
    <strong class="b-valor"><?= dinheiro((float) $m['custo_unitario']) ?></strong><small>por <?= e($u) ?></small></section>

  <?php if ($pode): ?>
    <section class="b-card span-6">
      <header class="b-topo"><h2>Entrada de compra</h2></header>
      <form method="post" action="<?= url('/producao/materias/' . (int) $m['id'] . '/entrada') ?>" data-form-entrada>
        <?= csrf_campo() ?>
        <div class="grade-campos">
          <div class="campo"><label for="e-qtd">Quantidade (<?= e($u) ?>)</label><input type="text" id="e-qtd" name="quantidade" inputmode="decimal" required></div>
          <div class="campo"><label for="e-custo">Custo por <?= e($u) ?> (R$)</label><input type="text" id="e-custo" name="custo_unitario" inputmode="decimal" value="<?= $num($m['custo_unitario']) ?>"></div>
          <div class="campo"><label for="e-nota">Nota fiscal</label><input type="text" id="e-nota" name="nota" maxlength="60"></div>
        </div>
        <div class="campo"><label for="e-obs">Observação</label><input type="text" id="e-obs" name="observacao" maxlength="200"></div>
        <button class="botao" type="submit">Registrar entrada</button>
      </form>
    </section>
    <section class="b-card span-6">
      <header class="b-topo"><h2>Ajuste de inventário</h2></header>
      <form method="post" action="<?= url('/producao/materias/' . (int) $m['id'] . '/ajuste') ?>" data-form-ajuste>
        <?= csrf_campo() ?>
        <div class="grade-campos">
          <div class="campo"><label for="a-contagem">Quantidade contada (<?= e($u) ?>)</label><input type="text" id="a-contagem" name="contagem" inputmode="decimal" required></div>
          <div class="campo"><label for="a-motivo">Motivo</label><input type="text" id="a-motivo" name="motivo" maxlength="200" placeholder="contagem, perda, vencido…"></div>
        </div>
        <p class="ajuda suave">O sistema grava a diferença entre a contagem e o estoque atual (<?= P::qtd($m['estoque'], $u) ?>).</p>
        <button class="botao contorno" type="submit">Ajustar estoque</button>
      </form>
    </section>
  <?php endif; ?>

  <section class="b-card span-8">
    <header class="b-topo"><h2>Histórico de movimentos</h2><span class="b-chip">últimos 100</span></header>
    <div class="tabela-rolagem">
      <table class="tabela pr-tabela" data-movimentos>
        <thead><tr><th>Data</th><th>Tipo</th><th class="direita">Quantidade</th><th class="direita">Saldo</th><th>Referência</th><th>Quem</th></tr></thead>
        <tbody>
        <?php foreach ($movimentos as $mv): $q = (float) $mv['quantidade']; ?>
          <tr>
            <td class="pequeno"><?= data_br($mv['criado_em'], true) ?></td>
            <td><span class="selo <?= ['entrada' => 'verde', 'consumo' => 'cinza', 'ajuste' => 'laranja'][$mv['tipo']] ?? 'cinza' ?>"><?= P::TIPOS_MP[$mv['tipo']] ?? e($mv['tipo']) ?></span></td>
            <td class="direita <?= $q < 0 ? 'pn-vermelho' : 'pn-verde' ?>"><?= ($q > 0 ? '+' : '') . P::qtd($q, $u) ?></td>
            <td class="direita"><?= P::qtd((float) $mv['saldo'], $u) ?></td>
            <td class="pequeno"><?php if ($mv['ordem_id']): ?><a href="<?= url('/producao/ordens/' . (int) $mv['ordem_id']) ?>"><?= e($mv['referencia']) ?></a><?php else: ?><?= e($mv['referencia'] ?? '') ?><?php endif; ?>
              <?= $mv['custo_unitario'] ? ' · ' . dinheiro((float) $mv['custo_unitario']) . '/' . e($u) : '' ?><?= $mv['observacao'] ? '<br><span class="suave">' . e($mv['observacao']) . '</span>' : '' ?></td>
            <td class="pequeno"><?= e($mv['usuario'] ?? '—') ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$movimentos): ?><tr><td colspan="6" class="vazio">Nenhum movimento ainda.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>
  <section class="b-card span-4">
    <header class="b-topo"><h2>Usada em</h2></header>
    <?php if (!$produtos): ?><p class="suave">Nenhuma ficha técnica usa esta matéria-prima.</p><?php endif; ?>
    <ul class="pr-alertas pr-compacta">
      <?php foreach ($produtos as $p): ?>
        <li><a href="<?= url('/producao/fichas/' . (int) $p['id']) ?>"><strong><?= e($p['nome']) ?></strong><small><?= P::qtd((float) $p['quantidade'], $u) ?> por unidade</small></a></li>
      <?php endforeach; ?>
    </ul>
  </section>

  <?php if ($pode): ?>
    <section class="b-card span-12">
      <details><summary><strong>Editar cadastro</strong></summary>
        <form method="post" action="<?= url('/producao/materias/' . (int) $m['id']) ?>" class="pr-form">
          <?= csrf_campo() ?>
          <?= Visao::renderizar('producao/_materia_campos', ['m' => $m, 'unidades' => $unidades]) ?>
          <div class="acoes-form"><button class="botao" type="submit">Salvar cadastro</button></div>
        </form>
      </details>
    </section>
  <?php endif; ?>
</div>
