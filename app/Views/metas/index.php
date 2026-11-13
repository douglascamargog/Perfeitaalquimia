<?php
$podeEditar = \App\Core\Auth::pode('metas.gerenciar'); // só "Ver" (Acessos): sem campos nem botões
/**
 * Metas do mês por vendedor e por função (realizado = pedidos faturados).
 * Variáveis: $mes, $vendedores, $funcoes (MetaService::grade), $equipe (meta da equipe ou null), $historico
 */
$meses = ['janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
$nomeMes = ucfirst($meses[(int) substr($mes, 5, 2) - 1]) . ' ' . substr($mes, 0, 4);
$anterior = date('Y-m', strtotime("$mes-01 -1 month"));
$proximo = date('Y-m', strtotime("$mes-01 +1 month"));
$ultimos = array_map(fn($i) => date('Y-m', strtotime(date('Y-m-01') . " -$i months")), range(6, 1));
$grade = function (string $tipo, array $linhas, string $titulo) use ($historico, $ultimos, $meses, $podeEditar) { ?>
  <section class="card pn-metas">
    <h2><?= $titulo ?></h2>
    <div class="tabela-rolagem">
      <table class="tabela">
        <thead><tr><th><?= $tipo === 'vendedor' ? 'Vendedor' : 'Função' ?></th><th>Meta do mês (R$)</th><th class="direita">Faturado</th><th>Andamento</th>
          <th class="direita">Projeção</th><th class="direita">Precisa/dia</th><th>Últimos 6 meses</th></tr></thead>
        <tbody>
        <?php foreach ($linhas as $l): $h = $historico[$tipo][$l['id']] ?? []; ?>
          <tr>
            <td><strong><?= e($l['nome']) ?></strong><small class="suave"><?= (int) $l['pedidos'] ?> pedido(s)</small></td>
            <td><?php if ($podeEditar): ?><input class="pn-input-meta" type="text" inputmode="decimal" name="metas[<?= $tipo ?>][<?= (int) $l['id'] ?>]"
                 value="<?= $l['meta'] > 0 ? number_format($l['meta'], 2, ',', '.') : '' ?>" placeholder="0,00" aria-label="Meta de <?= e($l['nome']) ?>"><?php else: ?><?= $l['meta'] > 0 ? dinheiro($l['meta']) : '—' ?><?php endif; ?></td>
            <td class="direita"><?= dinheiro($l['vendido']) ?></td>
            <td><?php if ($l['meta'] > 0): ?><div class="pn-meta"><div class="b-barra"><i style="width: <?= min(100, round($l['percentual'])) ?>%"></i></div>
              <small class="<?= $l['atingida'] ? 'ok' : '' ?>"><?= number_format($l['percentual'], 0, ',', '.') ?>%<?= $l['atingida'] ? ' · batida ✓' : '' ?></small></div><?php else: ?><span class="suave pequeno">sem meta</span><?php endif; ?></td>
            <td class="direita <?= $l['meta'] > 0 && $l['projecao_mes'] !== null ? ($l['projecao_mes'] >= $l['meta'] ? 'pn-verde' : 'pn-vermelho') : '' ?>"><?= $l['projecao_mes'] !== null ? dinheiro($l['projecao_mes']) : '—' ?></td>
            <td class="direita"><?= $l['meta'] > 0 && $l['necessario_dia'] !== null && !$l['atingida'] ? dinheiro($l['necessario_dia']) : '—' ?></td>
            <td><div class="pn-historico"><?php foreach ($ultimos as $m): $x = $h[$m] ?? null; ?>
              <span class="<?= $x ? ($x['pct'] >= 100 ? 'bateu' : 'nao') : 'sem' ?>" title="<?= ucfirst($meses[(int) substr($m, 5, 2) - 1]) ?>: <?= $x ? number_format($x['pct'], 0, ',', '.') . '% da meta' : 'sem meta' ?>"><?= mb_substr($meses[(int) substr($m, 5, 2) - 1], 0, 3) ?></span>
            <?php endforeach; ?></div></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>
<?php };
?>
<div class="cabecalho-secao">
  <div><div class="sobretitulo">GESTÃO · METAS</div><h1>Metas de <?= e($nomeMes) ?></h1>
    <p>Realizado = pedidos faturados no mês. Projeção pelo ritmo por dia de trabalho (mesma regra do Meu mês).</p></div>
  <div class="acoes">
    <a class="botao-icone" href="?mes=<?= $anterior ?>" aria-label="Mês anterior">‹</a>
    <a class="botao-icone" href="?mes=<?= $proximo ?>" aria-label="Próximo mês">›</a>
    <?php if ($podeEditar): ?><form method="post" action="<?= url('/metas/copiar') ?>"><?= csrf_campo() ?><input type="hidden" name="mes" value="<?= e($mes) ?>"><button class="botao contorno" type="submit">Copiar metas do mês anterior</button></form><?php endif; ?>
  </div>
</div>

<section class="card pn-equipe">
  <div><strong>Meta da equipe:</strong>
    <?= $equipe ? dinheiro((float) $equipe['meta']) . ' · ' . number_format($equipe['percentual'], 0, ',', '.') . '% (vendido lançado pelo gestor)' : 'ainda não cadastrada neste mês' ?></div>
  <?php if ($podeEditar): ?><a class="botao contorno pequeno" href="<?= url('/meu-mes') ?>?mes=<?= e($mes) ?>">Editar no Meu mês</a><?php endif; ?>
</section>

<form method="post" action="<?= url('/metas') ?>">
  <?= csrf_campo() ?><input type="hidden" name="mes" value="<?= e($mes) ?>">
  <?php $grade('vendedor', $vendedores, 'Por vendedor'); ?>
  <?php $grade('funcao', $funcoes, 'Por função'); ?>
  <?php if ($podeEditar): ?><div class="pn-salvar"><button class="botao" type="submit">Salvar metas</button></div><?php endif; ?>
</form>
