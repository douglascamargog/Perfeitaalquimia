<?php
/**
 * Ordem de produção para imprimir no modelo da empresa (PLA08.1 · Check list – Elaboração produto), A4 deitado.
 * O que o One sabe sai preenchido (ficha técnica, pesos, lotes das matérias-primas, embalagens, aprovação);
 * o que é anotado na fábrica sai em branco (horários, responsáveis, pesagem das cápsulas, tempo de fabricação).
 * Variáveis: $o, $linhas (prévia da ficha; na concluída, com o que saiu de verdade), $lotes (lotes de MP), $mps (fabricante/CAS)
 */
use App\Core\Config;
use App\Services\ProducaoService as P;

$empresa = config('empresa_nome', 'Perfeita Alquimia');
$qtd = (int) $o['quantidade'];
$upf = (int) ($o['unidades_por_frasco'] ?: P::unidadesPorFrasco((int) $o['produto_id']) ?: 0);
$forma = $o['forma'] ?: P::formaDoProduto((int) $o['produto_id']);
$porCapsula = in_array($forma, ['capsula', 'sache'], true) && $upf > 0;
$emG = fn(string $u) => ['kg' => 1000, 'g' => 1, 'mg' => 0.001][mb_strtolower($u)] ?? null; // fator para gramas
$num = fn(float $v, int $casas) => number_format($v, $casas, ',', '.');
$mpLinhas = array_values(array_filter($linhas, fn($l) => $l['tipo'] !== 'embalagem' && $emG((string) $l['unidade']) !== null));
$embLinhas = array_values(array_filter($linhas, fn($l) => $l['tipo'] === 'embalagem' || $emG((string) $l['unidade']) === null));
$totalUnit = 0.0;
$totalLote = 0.0;
$perdas = [];
$x = fn(bool $c) => $c ? 'X' : '&nbsp;';
$lotesLimite = 9; // linhas da tabela de matérias-primas (o modelo tem linhas em branco para completar à mão)
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>OP <?= (int) $o['id'] ?> · <?= e($o['produto']) ?></title>
  <link rel="stylesheet" href="<?= asset('css/op-impressa.css') ?>">
</head>
<body class="op">
  <?php if (config('modo_demo')): ?><p class="op-demo">Ambiente de demonstração — dados simulados</p><?php endif; ?>
  <div class="op-folha">
    <!-- Cabeçalho do documento -->
    <table class="op-cab">
      <tr>
        <td class="op-logo" rowspan="4"><img src="<?= asset('img/logo.png') ?>" alt="<?= e($empresa) ?>"></td>
        <td class="op-titulo" rowspan="4"><h1>ORDEM DE PRODUÇÃO</h1><p>CHECK LIST · ELABORAÇÃO PRODUTO</p><small>OP nº <?= (int) $o['id'] ?> · <?= P::SITUACOES[$o['situacao']][0] ?></small></td>
        <td class="op-doc">Cód.: <b><?= e((string) Config::get('op.codigo_documento', 'PLA08.1')) ?></b></td>
      </tr>
      <tr><td class="op-doc">Elaboração: <?= e((string) Config::get('op.elaboracao', '')) ?> &nbsp; Revisão: <?= e((string) Config::get('op.revisao', '')) ?></td></tr>
      <tr><td class="op-doc">Aceite Coord.: </td></tr>
      <tr><td class="op-doc">Arquivo: FT<?= e(preg_replace('/\D/', '', (string) ($o['codigo'] ?? '')) ?: (string) $o['produto_id']) ?>_PA</td></tr>
    </table>

    <!-- Dados do lote -->
    <table class="op-dados">
      <tr>
        <td class="op-prod" colspan="3"><b><?= e(mb_strtoupper((string) $o['produto'])) ?></b><?= !empty($o['codigo']) ? ' <small>(' . e($o['codigo']) . ')</small>' : '' ?></td>
        <td colspan="3">Entrada na produção: <b><?= $o['entrada_producao'] ? data_br($o['entrada_producao']) : '____/____/______' ?></b></td>
        <td colspan="4" class="op-marcas">Forma: <?php foreach (P::FORMAS as $k => $nome): ?><span>(<?= $x($forma === $k) ?>) <?= mb_strtolower($nome) ?></span><?php endforeach; ?></td>
      </tr>
      <tr>
        <td colspan="3"><b>Lote: <?= e((string) ($o['lote'] ?: '__________')) ?></b></td>
        <td colspan="2">Fab.: <b><?= $o['concluida_em'] ? data_br($o['concluida_em']) : '____/____/______' ?></b></td>
        <td colspan="2">Val.: <b><?= $o['validade'] ? data_br($o['validade']) : '____/____/______' ?></b></td>
        <td colspan="3" class="op-alerta">Tempo de fabricação: ____________</td>
      </tr>
      <tr>
        <td colspan="3">Volume inicial: <b><?= $num($qtd, 0) ?></b> <?= $forma === 'liquido' ? 'frascos' : 'frascos/potes' ?><?= $porCapsula ? ' · <b>' . $upf . '</b> ' . ($forma === 'sache' ? 'sachês' : 'cápsulas') . ' cada (' . $num($qtd * $upf, 0) . ' no lote)' : '' ?></td>
        <td colspan="2">Peso do pote: ________ g</td>
        <td colspan="2" class="op-marcas">Amostra: <span>(<?= $x((bool) $o['amostra']) ?>) Sim</span><span>(<?= $x(!$o['amostra']) ?>) Não</span></td>
        <td colspan="3" class="op-marcas">Fornecedor: <span>(<?= $x($o['fornecedor_mp'] === 'cliente') ?>) Cliente</span><span>(<?= $x($o['fornecedor_mp'] !== 'cliente') ?>) P.A.</span>
          &nbsp; Destino: <span>(<?= $x(empty($o['cliente_id'])) ?>) PA</span><span>(<?= $x(!empty($o['cliente_id'])) ?>) Terceirizado<?= !empty($o['cliente_nome']) ? ': ' . e($o['cliente_nome']) : '' ?></span></td>
      </tr>
    </table>

    <!-- Matérias-primas + check list das etapas -->
    <table class="op-mp">
      <thead>
        <tr>
          <th rowspan="2" class="op-desc">Descrição do item</th>
          <th rowspan="2">Peso unitário (g)<?= $porCapsula ? '<br><small>por ' . ($forma === 'sache' ? 'sachê' : 'cápsula') . '</small>' : '' ?></th>
          <th rowspan="2" class="op-destaque">Peso em gramas<br><small>lote todo</small></th>
          <th rowspan="2">Fabricante</th><th rowspan="2">CAS / INS</th><th rowspan="2">Lote fabricante</th><th rowspan="2">Fabricação</th><th rowspan="2">Validade</th>
          <th>Pesagem / Mistura</th><th>Sala cápsulas<br>( ) 01 &nbsp;( ) 02</th><th>Sala envase / Rótulo</th><th>Embalagem secundária</th>
          <th rowspan="2" class="op-obs-cab">Observações</th>
        </tr>
        <tr><th>Hora / Responsável</th><th>Hora / Responsável</th><th>Hora / Responsável</th><th>Hora / Responsável</th></tr>
      </thead>
      <tbody>
      <?php foreach ($mpLinhas as $i => $l):
        $f = $emG((string) $l['unidade']);
        $porFrasco = (float) $l['por_unidade'] * $f; // g por frasco/pote
        $unit = $porCapsula ? $porFrasco / $upf : $porFrasco;
        $lote = (float) $l['precisa'] * $f; // g no lote (com a perda prevista)
        $totalUnit += $unit;
        $totalLote += $lote;
        $perdas[] = (float) $l['perda_pct'];
        $lt = $lotes[(int) $l['materia_prima_id']] ?? [];
        $primeiro = $lt[0] ?? null;
        $mp = $mps[(int) $l['materia_prima_id']] ?? []; ?>
        <tr>
          <td class="op-desc"><?= e(mb_strtoupper((string) $l['nome'])) ?></td>
          <td class="num"><?= $num($unit, 3) ?></td>
          <td class="num op-destaque"><?= $num($lote, 1) ?></td>
          <td><?= e((string) ($primeiro['fabricante'] ?? $mp['fabricante'] ?? '')) ?></td>
          <td><?= e((string) ($mp['cas_ins'] ?? '')) ?></td>
          <td><?= e(implode(' / ', array_map(fn($u) => (string) $u['lote'], $lt))) ?></td>
          <td><?= $primeiro && $primeiro['fabricacao'] ? data_br($primeiro['fabricacao']) : '' ?></td>
          <td><?= $primeiro && $primeiro['validade'] ? data_br($primeiro['validade']) : '' ?></td>
          <td></td><td></td><td></td><td></td>
          <?php if ($i === 0): ?><td class="op-obs" rowspan="<?= max($lotesLimite, count($mpLinhas)) + 3 ?>"><b>ESTE CHECK LIST EQUIVALE A UM LOTE DE <?= $num($qtd, 0) ?> FRASCOS<?= $porCapsula ? ' COM ' . $upf . ' ' . ($forma === 'sache' ? 'SACHÊS' : 'CÁPSULAS') . ' CADA' : '' ?></b>
            <?= $o['observacoes'] ? '<p>' . nl2br(e($o['observacoes'])) . '</p>' : '' ?></td><?php endif; ?>
        </tr>
      <?php endforeach; ?>
      <?php for ($i = count($mpLinhas); $i < $lotesLimite; $i++): ?>
        <tr class="op-vazia"><td class="op-desc">&nbsp;</td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td>
          <?php if ($i === 0): ?><td class="op-obs" rowspan="<?= $lotesLimite + 3 ?>"><b>ESTE CHECK LIST EQUIVALE A UM LOTE DE <?= $num($qtd, 0) ?> FRASCOS</b></td><?php endif; ?></tr>
      <?php endfor; ?>
        <tr class="op-total"><td class="op-desc">TOTAL PÓ</td><td class="num"><?= $num($totalUnit, 3) ?></td><td class="num op-destaque"><?= $num($totalLote, 1) ?></td><td colspan="9"></td></tr>
        <tr><td class="op-desc">PERDA PÓ PROCESSAMENTO</td><td class="num"><?= $perdas ? $num(array_sum($perdas) / count($perdas), 2) . '%' : '—' ?></td><td colspan="10"></td></tr>
        <tr><td class="op-desc">PESO <?= $forma === 'sache' ? 'SACHÊ' : 'CÁPSULA' ?> TESTE</td><td class="num"><?= $porCapsula ? $num($totalUnit, 3) : '' ?></td><td colspan="10"></td></tr>
      </tbody>
    </table>

    <!-- Embalagens e teste de pesagem -->
    <div class="op-baixo">
      <table class="op-emb">
        <thead><tr><th>Embalagem</th><th>Por frasco</th><th>No lote</th><th>Lote</th></tr></thead>
        <tbody>
        <?php foreach ($embLinhas as $l): $lt = $lotes[(int) $l['materia_prima_id']] ?? []; ?>
          <tr><td><?= e(mb_strtoupper((string) $l['nome'])) ?></td><td class="num"><?= e(P::qtd((float) $l['por_unidade'], (string) $l['unidade'])) ?></td>
            <td class="num"><?= e(P::qtd((float) $l['precisa'], (string) $l['unidade'])) ?></td><td><?= e(implode(' / ', array_map(fn($u) => (string) $u['lote'], $lt))) ?></td></tr>
        <?php endforeach; ?>
        <?php for ($i = count($embLinhas); $i < 5; $i++): ?><tr class="op-vazia"><td>&nbsp;</td><td></td><td></td><td></td></tr><?php endfor; ?>
        </tbody>
      </table>
      <?php if ($porCapsula || $forma === 'pote'): ?>
      <table class="op-pesagem">
        <thead><tr><th colspan="20">TESTE PESAGEM <?= $forma === 'sache' ? 'SACHÊS' : 'CÁPSULAS' ?> (g)</th></tr></thead>
        <tbody>
        <?php for ($linha = 1; $linha <= 5; $linha++): ?>
          <tr><?php for ($col = 0; $col < 10; $col++): $nTeste = $col * 5 + $linha; ?><td class="op-n"><?= $nTeste ?>ª</td><td class="op-campo"></td><?php endfor; ?></tr>
        <?php endfor; ?>
          <tr class="op-resumo"><td colspan="5">Peso médio: __________ g</td><td colspan="5">Peso do pote + sílica: __________ g</td>
            <td colspan="5">P + S + 50 caps: __________ g</td><td colspan="5">P + S + <?= $upf ?: 60 ?> caps: __________ g</td></tr>
        </tbody>
      </table>
      <?php endif; ?>
    </div>

    <!-- Assinaturas -->
    <table class="op-assin">
      <tr>
        <td>Preenchido por: <b><?= e((string) ($o['criador'] ?? '')) ?></b><br><small>emitida em <?= data_br($o['criado_em'], true) ?></small></td>
        <td>Revisado por: <b><?= $o['aprovada_em'] ? e((string) $o['aprovador']) : '' ?></b><br><small><?= $o['aprovada_em'] ? 'Responsável Técnica · ' . data_br($o['aprovada_em'], true) : '' ?></small></td>
        <td>Aprovado por: <b><?= $o['aprovada_em'] ? e((string) $o['aprovador']) : '' ?></b><br><small><?= $o['aprovada_em'] ? 'Responsável Técnica · ' . data_br($o['aprovada_em'], true) : '' ?></small></td>
      </tr>
    </table>
  </div>
  <script>window.addEventListener('load', () => window.print());</script>
</body>
</html>
