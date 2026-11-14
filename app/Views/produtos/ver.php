<?php
/**
 * Página do produto, com abas de pasta.
 * Variáveis: $p (produto + ficha), $aba, $abas, $tecnico, $ficha (materia_prima/embalagem), $precos, $documentos,
 *            $historico, $materias, $podeTecnico, $podeConteudo, $veCusto, $vePrivado
 */
use App\Core\Visao;

$vencendo = count(array_filter($documentos, fn($d) => $d['situacao'] !== 'ok'));
$contador = ['ficha' => count($ficha['materia_prima']), 'embalagens' => count($ficha['embalagem']), 'documentos' => count($documentos)];
?>
<a class="voltar-link" href="<?= url('/produtos') ?>">← Todos os produtos</a>

<section class="pd-capa">
  <span class="pd-foto"><?php if ($p['imagem']): ?><img src="<?= url('/' . e($p['imagem'])) ?>" alt="Embalagem de <?= e($p['nome']) ?>"><?php else: ?><b>PA</b><?php endif; ?></span>
  <div>
    <div class="sobretitulo">PRODUTO · <?= e(mb_strtoupper((string) $p['categoria'])) ?></div>
    <h1><?= e($p['nome']) ?></h1>
    <p class="pd-sub">
      <?php if ($p['codigo']): ?><span class="cod-produto"><?= e($p['codigo']) ?></span><?php endif; ?>
      <?= $p['embalagem'] ? e($p['embalagem']) : '' ?>
      <?php if (!empty($tecnico['ean'])): ?> · EAN <?= e($tecnico['ean']) ?><?php endif; ?>
    </p>
    <?php if ($p['chamada']): ?><p class="pd-chamada"><?= e($p['chamada']) ?></p><?php endif; ?>
    <div class="pd-selos">
      <?php if (!$p['ativo']): $dp = \App\Core\Banco::um('SELECT id, situacao FROM dp_projetos WHERE produto_id = ? ORDER BY id DESC LIMIT 1', [$p['id']]); ?>
        <a class="pd-selo falta" href="<?= url('/desenvolvimento/' . (int) ($dp['id'] ?? 0)) ?>">Em desenvolvimento — ver o projeto</a><?php endif; ?>
      <span class="pd-selo <?= $p['visivel_catalogo'] ? 'ok' : 'cinza' ?>"><?= $p['visivel_catalogo'] ? 'Aparece no catálogo' : 'Oculto no catálogo' ?></span>
      <span class="pd-selo <?= $contador['ficha'] ? 'ok' : 'falta' ?>"><?= $contador['ficha'] ? 'Ficha técnica preenchida' : 'Sem ficha técnica' ?></span>
      <?php if ($vencendo): ?><span class="pd-selo falta"><?= $vencendo ?> documento(s) vencido(s) ou vencendo</span><?php endif; ?>
    </div>
  </div>
</section>

<nav class="abas" aria-label="Informações do produto">
  <?php foreach ($abas as $chave => $nome): ?>
    <a href="<?= url('/produtos/' . e($p['slug'])) . ($chave === 'geral' ? '' : '?aba=' . $chave) ?>" class="<?= $chave === $aba ? 'ativa' : '' ?>"><?= e($nome) ?><?php if (!empty($contador[$chave])): ?> <b class="pd-num"><?= $contador[$chave] ?></b><?php endif; ?></a>
  <?php endforeach; ?>
</nav>

<div class="pd-aba">
<?= Visao::renderizar('produtos/_aba_' . $aba, get_defined_vars()) ?>
</div>
