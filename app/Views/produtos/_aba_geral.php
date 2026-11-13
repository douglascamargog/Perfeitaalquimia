<?php
/** Visão geral: o essencial do produto num lugar só */

$f = $p['ficha'];
$dados = array_filter([
    'Código' => $p['codigo'], 'Código no ERP' => $p['omie_codigo'], 'Apresentação' => $p['categoria'], 'Embalagem' => $p['embalagem'],
    'Porção' => $f['serving'] ?? '', 'Rendimento' => $f['servings'] ?? '',
    'Validade' => !empty($tecnico['validade_meses']) ? $tecnico['validade_meses'] . ' meses' : '',
    'Registro / notificação' => $tecnico['registro_anvisa'] ?? '', 'NCM' => $tecnico['ncm'] ?? '',
]);
?>
<div class="pd-grade">
  <div>
    <section class="card">
      <h2>O que é</h2>
      <?php foreach (array_slice($f['descriptionParagraphs'] ?? [], 0, 2) as $par): ?><p><?= e($par) ?></p><?php endforeach; ?>
      <?php if (empty($f['descriptionParagraphs'])): ?><p class="suave">Sem descrição ainda<?= $podeConteudo ? ' — preencha na aba Conteúdo.' : '.' ?></p><?php endif; ?>
      <?php if (!empty($f['features'])): ?><div class="chips"><?php foreach ($f['features'] as $s): ?><span class="chip"><?= e($s) ?></span><?php endforeach; ?></div><?php endif; ?>
    </section>
    <section class="card">
      <h2>Dados do produto</h2>
      <dl class="pd-dados"><?php foreach ($dados as $k => $v): ?><div><dt><?= e($k) ?></dt><dd><?= e($v) ?></dd></div><?php endforeach; ?></dl>
    </section>
  </div>
  <aside>
    <section class="card">
      <h3>Preços (do ERP)</h3>
      <dl class="pd-dados"><?php foreach ($precos as $t): ?><div><dt><?= e($t['nome']) ?></dt><dd><?= $t['preco'] !== null && (float) $t['preco'] > 0 ? dinheiro((float) $t['preco']) : '<span class="suave">sem preço</span>' ?></dd></div><?php endforeach; ?></dl>
    </section>
    <section class="card">
      <h3>Estoque</h3>
      <dl class="pd-dados">
        <div><dt>No ERP (para venda)</dt><dd><?= $p['estoque_erp'] !== null ? (int) $p['estoque_erp'] . ' un' : '—' ?></dd></div>
        <div><dt>Na fábrica</dt><dd><?= $p['estoque_fabrica'] !== null ? (int) $p['estoque_fabrica'] . ' un' . ((int) $p['minimo_fabrica'] ? ' (mín. ' . (int) $p['minimo_fabrica'] . ')' : '') : '—' ?></dd></div>
      </dl>
    </section>
    <section class="card pd-atalhos">
      <h3>Atalhos</h3>
      <?php if (\App\Core\Auth::pode('academia.ver')): ?><a class="botao contorno pequeno" href="<?= url('/academia/produtos/' . e($p['slug'])) ?>">Ver na Academia</a><?php endif; ?>
      <?php if ($p['visivel_catalogo'] && !$p['cliente_exclusivo_id']): ?><a class="botao contorno pequeno" href="<?= url('/catalogo/produto/' . e($p['slug'])) ?>" target="_blank" rel="noopener">Ver no catálogo ↗</a><?php endif; ?>
      <a class="botao contorno pequeno" href="<?= url('/produtos/catalogo?q=' . rawurlencode((string) ($p['codigo'] ?: $p['nome']))) ?>">Ajustar no Catálogo</a>
      <?php if (\App\Core\Auth::pode('producao.gerenciar')): ?><a class="botao contorno pequeno" href="<?= url('/producao/ordens/nova?produto=' . (int) $p['id']) ?>">Nova ordem de produção</a><?php endif; ?>
    </section>
  </aside>
</div>
