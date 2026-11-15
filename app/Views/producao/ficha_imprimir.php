<?php
/**
 * Ficha técnica do produto para imprimir (A4 em pé). Página própria, sem menu.
 * Variáveis: $p (produto + dados técnicos), $linhas (ficha técnica), $json (conteúdo do produto), $mps (tipo/fabricante/CAS), $tabela (nutricional), $tecnica
 */
use App\Core\Config;
use App\Core\Visao;
use App\Services\ProducaoService as P;

$empresa = config('empresa_nome', 'Perfeita Alquimia');
$emG = fn(string $u) => ['kg' => 1000, 'g' => 1, 'mg' => 0.001][mb_strtolower($u)] ?? null;
$massa = array_values(array_filter($linhas, fn($l) => ($mps[$l['materia_prima_id']]['tipo'] ?? '') !== 'embalagem' && $emG((string) $l['unidade']) !== null));
$embalagem = array_values(array_filter($linhas, fn($l) => !in_array($l, $massa, true)));
$totalG = array_sum(array_map(fn($l) => (float) $l['quantidade'] * $emG((string) $l['unidade']), $massa));
$num = fn(float $v, int $c) => number_format($v, $c, ',', '.');
$texto = fn($v) => is_array($v) ? implode(' ', array_map(fn($x) => is_array($x) ? implode(' ', $x) : (string) $x, $v)) : (string) $v;
$campo = fn(string $rotulo, $valor) => '<div class="ft-campo"><span>' . e($rotulo) . '</span><b>' . (trim((string) $valor) !== '' ? e((string) $valor) : '—') . '</b></div>';
$forma = P::FORMAS[P::formaDoProduto((int) $p['id'])] ?? '';
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Ficha técnica · <?= e($p['nome']) ?></title>
  <link rel="stylesheet" href="<?= asset('css/nutricional.css') ?>">
  <link rel="stylesheet" href="<?= asset('css/ficha-impressa.css') ?>">
</head>
<body class="ft">
  <?php if (config('modo_demo')): ?><p class="ft-demo">Ambiente de demonstração — dados simulados</p><?php endif; ?>
  <div class="ft-folha">
    <header class="ft-cab">
      <img src="<?= asset('img/logo.png') ?>" alt="<?= e($empresa) ?>">
      <div class="ft-titulo"><h1>FICHA TÉCNICA DO PRODUTO</h1><p><?= e(mb_strtoupper((string) $p['nome'])) ?></p></div>
      <div class="ft-doc"><div>Arquivo: <b>FT<?= e(preg_replace('/\D/', '', (string) ($p['codigo'] ?? '')) ?: (string) $p['id']) ?>_PA</b></div>
        <div>Código: <b><?= e((string) ($p['codigo'] ?? '—')) ?></b></div><div>Emitida em <?= date('d/m/Y') ?></div></div>
    </header>

    <section class="ft-sec">
      <h2>1. Identificação</h2>
      <div class="ft-identificacao">
        <?php if (!empty($p['imagem'])): ?><img class="ft-foto" src="<?= url('/' . e($p['imagem'])) ?>" alt=""><?php endif; ?>
        <div class="ft-grade">
          <?= $campo('Produto', $p['nome']) ?><?= $campo('Categoria', $p['categoria'] ?? '') ?><?= $campo('Forma', $forma) ?><?= $campo('Apresentação', $p['embalagem'] ?? '') ?>
          <?= $campo('EAN', $p['ean'] ?? '') ?><?= $campo('NCM', $p['ncm'] ?? '') ?><?= $campo('Registro / notificação Anvisa', $p['registro_anvisa'] ?? '') ?>
          <?= $campo('Validade', $p['validade_meses'] ? $p['validade_meses'] . ' meses' : '') ?><?= $campo('Peso líquido', $p['peso_liquido'] ?? '') ?><?= $campo('Peso bruto', $p['peso_bruto'] ?? '') ?>
          <?= $campo('Dimensões', $p['dimensoes'] ?? '') ?><?= $campo('Unidades por caixa', $p['unidades_caixa'] ?? '') ?>
        </div>
      </div>
    </section>

    <section class="ft-sec">
      <h2>2. Composição (por unidade fabricada)</h2>
      <table class="ft-tabela">
        <thead><tr><th>Matéria-prima</th><th class="num">Quantidade</th><th class="num">%</th><th>Fabricante</th><th>CAS / INS</th></tr></thead>
        <tbody>
        <?php foreach ($massa as $l): $g = (float) $l['quantidade'] * $emG((string) $l['unidade']); $mp = $mps[$l['materia_prima_id']] ?? []; ?>
          <tr><td><?= e($l['nome']) ?></td><td class="num"><?= e(P::qtd((float) $l['quantidade'], (string) $l['unidade'])) ?></td>
            <td class="num"><?= $totalG > 0 ? $num($g / $totalG * 100, 2) . '%' : '—' ?></td><td><?= e((string) ($mp['fabricante'] ?? '')) ?></td><td><?= e((string) ($mp['cas_ins'] ?? '')) ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$massa): ?><tr><td colspan="5" class="suave">Ficha técnica ainda sem matérias-primas.</td></tr><?php endif; ?>
        </tbody>
        <?php if ($massa): ?><tfoot><tr><td>Total por unidade</td><td class="num"><?= $num($totalG, 3) ?> g</td><td class="num">100%</td><td colspan="2"></td></tr></tfoot><?php endif; ?>
      </table>
      <?php if (!empty($json['ingredients'])): ?><p class="ft-texto"><b>Ingredientes:</b> <?= e($texto($json['ingredients'])) ?></p><?php endif; ?>
    </section>

    <div class="ft-duas">
      <section class="ft-sec">
        <h2>3. Informação nutricional</h2>
        <?php if ($tabela['linhas']): ?><?= Visao::renderizar('producao/_tabela_nutricional', ['t' => $tabela]) ?><?php else: ?><p class="suave">Tabela nutricional ainda não preenchida.</p><?php endif; ?>
      </section>
      <section class="ft-sec">
        <h2>4. Uso, armazenamento e cuidados</h2>
        <?php if (!empty($json['use'])): ?><p class="ft-texto"><b>Modo de uso:</b> <?= e($texto($json['use'])) ?></p><?php endif; ?>
        <p class="ft-texto"><b>Armazenamento:</b> <?= e((string) ($p['armazenamento'] ?: ($texto($json['storage'] ?? '') ?: '—'))) ?></p>
        <p class="ft-texto"><b>Alergênicos:</b> <?= e((string) ($p['alergenicos'] ?: '—')) ?></p>
        <p class="ft-texto"><b>Advertências:</b> <?= e((string) ($p['avisos'] ?: ($texto($json['care'] ?? '') ?: '—'))) ?></p>
        <?php if ($embalagem): ?>
          <h3>Embalagem</h3>
          <ul class="ft-lista"><?php foreach ($embalagem as $l): ?><li><?= e($l['nome']) ?> · <?= e(P::qtd((float) $l['quantidade'], (string) $l['unidade'])) ?> por unidade</li><?php endforeach; ?></ul>
        <?php endif; ?>
      </section>
    </div>

    <footer class="ft-assin">
      <div>Elaborado por:<br><b><?= e((string) ($p['atualizado_por_nome'] ?? '')) ?></b><small><?= $p['tecnico_em'] ? 'em ' . data_br($p['tecnico_em']) : '' ?></small></div>
      <div>Responsável Técnica:<br><b><?= e((string) ($tecnica['nome'] ?? '')) ?></b><small>assinatura</small></div>
      <div>Aprovado em:<br><b>____/____/______</b></div>
    </footer>
  </div>
  <script>window.addEventListener('load', () => window.print());</script>
</body>
</html>
