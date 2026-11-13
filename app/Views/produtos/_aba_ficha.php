<?php
/** Ficha técnica: matérias-primas por unidade (a MESMA ficha da Produção). Só a Responsável Técnica edita. */
use App\Core\Visao;

$opcoes = array_values(array_filter($materias, fn($m) => $m['tipo'] !== 'embalagem'));
?>
<p class="pd-dica">É a mesma ficha usada pela <strong>Produção</strong> nas ordens (baixa automática das matérias-primas). As embalagens ficam na aba <a href="<?= url('/produtos/' . e($p['slug']) . '?aba=embalagens') ?>">Embalagens</a>.</p>
<?= Visao::renderizar('produtos/_ficha_editor', ['p' => $p, 'linhas' => $ficha['materia_prima'], 'opcoes' => $opcoes, 'tipo' => 'materia_prima', 'podeTecnico' => $podeTecnico, 'veCusto' => $veCusto]) ?>
<?php if ($veCusto && ($ficha['materia_prima'] || $ficha['embalagem'])): ?>
  <p class="pd-dica">Custo total por unidade (matérias-primas + embalagens): <strong><?= dinheiro(array_sum(array_map(fn($l) => (float) $l['custo'], array_merge($ficha['materia_prima'], $ficha['embalagem'])))) ?></strong></p>
<?php endif; ?>
<?php if (!$podeTecnico): ?><p class="pd-dica">Só a Responsável Técnica altera a ficha técnica.</p><?php endif; ?>
