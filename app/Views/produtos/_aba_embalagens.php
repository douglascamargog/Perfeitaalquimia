<?php
/** Embalagens: itens de embalagem da ficha + especificações (EAN, NCM, pesos, validade, caixa...). Só a Responsável Técnica edita. */
use App\Core\Visao;

$opcoes = array_values(array_filter($materias, fn($m) => $m['tipo'] === 'embalagem'));
$t = $tecnico;
$v = fn(string $k) => antigo($k, (string) ($t[$k] ?? '')); // antigo() já escapa
$specs = array_filter([
    'EAN (código de barras)' => $t['ean'] ?? '', 'NCM' => $t['ncm'] ?? '', 'Registro / notificação' => $t['registro_anvisa'] ?? '',
    'Validade' => !empty($t['validade_meses']) ? $t['validade_meses'] . ' meses' : '', 'Peso líquido' => $t['peso_liquido'] ?? '',
    'Peso bruto' => $t['peso_bruto'] ?? '', 'Dimensões' => $t['dimensoes'] ?? '', 'Unidades por caixa' => $t['unidades_caixa'] ?? '',
    'Armazenamento' => $t['armazenamento'] ?? '',
]);
?>
<h2 class="pd-titulo">Itens de embalagem por unidade</h2>
<?= Visao::renderizar('produtos/_ficha_editor', ['p' => $p, 'linhas' => $ficha['embalagem'], 'opcoes' => $opcoes, 'tipo' => 'embalagem', 'podeTecnico' => $podeTecnico, 'veCusto' => $veCusto]) ?>

<h2 class="pd-titulo">Especificações</h2>
<?php if ($podeTecnico): ?>
<form class="card pd-form" method="post" action="<?= url('/produtos/' . e($p['slug']) . '/embalagens') ?>">
  <?= csrf_campo() ?>
  <div class="pd-tres">
    <label>EAN (código de barras)<input type="text" name="ean" inputmode="numeric" maxlength="20" value="<?= $v('ean') ?>"></label>
    <label>NCM<input type="text" name="ncm" inputmode="numeric" maxlength="12" value="<?= $v('ncm') ?>" placeholder="0000.00.00"></label>
    <label>Registro / notificação (Anvisa)<input type="text" name="registro_anvisa" maxlength="80" value="<?= $v('registro_anvisa') ?>"></label>
    <label>Validade (meses)<input type="number" name="validade_meses" min="1" max="120" value="<?= $v('validade_meses') ?>"></label>
    <label>Peso líquido<input type="text" name="peso_liquido" maxlength="40" value="<?= $v('peso_liquido') ?>" placeholder="Ex.: 60 g"></label>
    <label>Peso bruto<input type="text" name="peso_bruto" maxlength="40" value="<?= $v('peso_bruto') ?>"></label>
    <label>Dimensões da embalagem<input type="text" name="dimensoes" maxlength="60" value="<?= $v('dimensoes') ?>" placeholder="Ex.: 5 x 5 x 10 cm"></label>
    <label>Unidades por caixa de embarque<input type="number" name="unidades_caixa" min="1" value="<?= $v('unidades_caixa') ?>"></label>
  </div>
  <label>Armazenamento<input type="text" name="armazenamento" maxlength="500" value="<?= $v('armazenamento') ?>"></label>
  <label>Observações técnicas<textarea name="observacoes" rows="3"><?= $v('observacoes') ?></textarea></label>
  <div class="acoes-form"><button class="botao" type="submit">Salvar especificações</button></div>
  <?php if (!empty($t['atualizado_por_nome'])): ?><p class="ajuda suave">Última alteração: <?= e($t['atualizado_por_nome']) ?>, <?= data_br($t['atualizado_em'], true) ?>.</p><?php endif; ?>
</form>
<?php else: ?>
<section class="card">
  <?php if ($specs): ?><dl class="pd-dados pd-dados-largo"><?php foreach ($specs as $k => $val): ?><div><dt><?= e($k) ?></dt><dd><?= e($val) ?></dd></div><?php endforeach; ?></dl>
  <?php else: ?><p class="suave">Especificações ainda não preenchidas.</p><?php endif; ?>
  <?php if (!empty($t['observacoes'])): ?><p><?= nl2br(e($t['observacoes'])) ?></p><?php endif; ?>
</section>
<p class="pd-dica">Só a Responsável Técnica altera as embalagens e as especificações.</p>
<?php endif; ?>
