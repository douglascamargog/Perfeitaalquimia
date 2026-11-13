<?php
/** Documentos do produto: laudos, FISPQ, registros, certificados, artes. Toda a equipe vê; "interno" só técnica, produção e admin. */
use App\Services\ProdutoService as PS;

$tamanho = fn(int $b) => $b >= 1048576 ? number_format($b / 1048576, 1, ',', '.') . ' MB' : max(1, (int) round($b / 1024)) . ' KB';
$rotulo = ['vencido' => 'Vencido', 'vencendo' => 'Vence em breve'];
?>
<?php if (!$documentos): ?>
  <div class="card vazio"><p>Nenhum documento ainda.</p></div>
<?php else: ?>
<div class="pd-docs">
  <?php foreach ($documentos as $d): ?>
    <article class="card pd-doc <?= e($d['situacao']) ?>">
      <span class="pd-doc-icone"><?= $d['mime'] === 'application/pdf' ? 'PDF' : 'IMG' ?></span>
      <div>
        <small><?= e(PS::TIPOS_DOCUMENTO[$d['tipo']] ?? 'Documento') ?><?= $d['interno'] ? ' · <b class="pd-interno">interno</b>' : '' ?></small>
        <strong><?= e($d['titulo']) ?></strong>
        <p class="pequeno suave"><?= $tamanho((int) $d['tamanho']) ?> · enviado por <?= e($d['enviado_por_nome'] ?? '—') ?> em <?= data_br($d['criado_em']) ?>
          <?php if ($d['validade']): ?> · validade <strong class="<?= $d['situacao'] !== 'ok' ? 'pd-vence' : '' ?>"><?= data_br($d['validade']) ?></strong><?= isset($rotulo[$d['situacao']]) ? ' (' . $rotulo[$d['situacao']] . ')' : '' ?><?php endif; ?></p>
      </div>
      <div class="pd-doc-acoes">
        <a class="botao contorno pequeno" href="<?= url('/produtos/documentos/' . (int) $d['id']) ?>" target="_blank" rel="noopener">Abrir</a>
        <a class="botao contorno pequeno" href="<?= url('/produtos/documentos/' . (int) $d['id']) ?>?baixar=1">Baixar</a>
        <?php if ($podeTecnico): ?>
          <form method="post" action="<?= url('/produtos/documentos/' . (int) $d['id'] . '/arquivar') ?>" data-confirmar="Remover &quot;<?= e($d['titulo']) ?>&quot; da lista?"><?= csrf_campo() ?>
            <button class="botao contorno pequeno perigo" type="submit">Remover</button></form>
        <?php endif; ?>
      </div>
    </article>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if ($podeTecnico): ?>
<form class="card pd-form" method="post" action="<?= url('/produtos/' . e($p['slug']) . '/documentos') ?>" enctype="multipart/form-data">
  <?= csrf_campo() ?>
  <h3>Enviar documento</h3>
  <div class="pd-tres">
    <label>Tipo<select name="tipo"><?php foreach (PS::TIPOS_DOCUMENTO as $k => $nome): ?><option value="<?= e($k) ?>"><?= e($nome) ?></option><?php endforeach; ?></select></label>
    <label>Título<input type="text" name="titulo" maxlength="160" placeholder="Ex.: Laudo microbiológico lote 2411"></label>
    <label>Validade <small>se tiver</small><input type="date" name="validade"></label>
  </div>
  <label>Arquivo (PDF, JPG ou PNG, até 15 MB)<input type="file" name="arquivo" accept="application/pdf,image/jpeg,image/png" required></label>
  <label class="caixa-marcar"><input type="checkbox" name="interno" value="1"> Interno — só a Responsável Técnica, a Produção e a administração veem</label>
  <div class="acoes-form"><button class="botao" type="submit">Enviar documento</button></div>
  <p class="ajuda suave">O arquivo fica guardado no sistema (não tem link público). Avisamos <?= PS::diasAviso() ?> dias antes de vencer.</p>
</form>
<?php else: ?>
  <p class="pd-dica">Só a Responsável Técnica envia ou troca documentos.</p>
<?php endif; ?>
