<?php
/**
 * Ilustração (SVG) de cada tipo de embalagem: usada na escolha do tipo, nos cartões do quadro
 * e como reserva do palco 3D (quando o 3D não carrega). Variáveis: $tipo (pote|frasco|caixinha), $classe (opcional).
 * As peças têm classes pc-* : no palco de reserva cada uma só aparece quando a etapa dela está concluída.
 */
$tipo = in_array($tipo ?? '', ['pote', 'frasco', 'caixinha'], true) ? $tipo : 'pote';
$classe = $classe ?? '';
$barras = '';
foreach ([0, 3, 5, 9, 11, 14, 17, 19, 22] as $i => $x) {
    $barras .= '<rect x="' . $x . '" y="0" width="' . ($i % 3 ? 1.4 : 2.2) . '" height="12"/>';
}
?>
<svg class="dp-ilustra <?= e($classe) ?>" viewBox="0 0 120 160" role="img" aria-label="<?= e(\App\Services\DesenvolvimentoService::TIPOS[$tipo]) ?>" data-tipo="<?= e($tipo) ?>">
<?php if ($tipo === 'pote'): ?>
  <rect class="pc-fantasma" x="30" y="10" width="60" height="142" rx="9"/>
  <rect class="pc-conteudo" x="35" y="40" width="50" height="106" rx="6"/>
  <rect class="pc-corpo" x="30" y="30" width="60" height="122" rx="9"/>
  <g class="pc-rotulo"><rect class="branco" x="30" y="44" width="60" height="96"/><rect x="30" y="44" width="60" height="2.5"/><rect x="30" y="98" width="60" height="42"/><rect x="53" y="44" width="14" height="14" rx="3"/></g>
  <g class="pc-arte"><rect x="38" y="66" width="44" height="9" rx="2"/><rect x="42" y="78" width="36" height="9" rx="2"/></g>
  <g class="pc-codigo" transform="translate(64 120)"><?= $barras ?></g>
  <rect class="pc-tampa" x="29" y="8" width="62" height="25" rx="5"/>
  <rect class="pc-lacre" x="29" y="30" width="62" height="4"/>
  <g class="pc-selo"><circle cx="84" cy="56" r="9"/><path d="M80 56l3 3 5-6"/></g>
<?php elseif ($tipo === 'frasco'): ?>
  <path class="pc-fantasma" d="M50 40h20v14c10 4 15 10 15 20v64q0 8-8 8H43q-8 0-8-8V74c0-10 5-16 15-20z"/>
  <path class="pc-conteudo" d="M40 86h40v50q0 6-6 6H46q-6 0-6-6z"/>
  <path class="pc-corpo" d="M50 40h20v14c10 4 15 10 15 20v64q0 8-8 8H43q-8 0-8-8V74c0-10 5-16 15-20z"/>
  <rect class="pc-rotulo" x="35" y="84" width="50" height="42"/>
  <rect class="pc-arte" x="40" y="90" width="30" height="7" rx="2"/>
  <g class="pc-codigo" transform="translate(55 110)"><?= $barras ?></g>
  <g class="pc-tampa"><rect x="46" y="22" width="28" height="20" rx="4"/><ellipse cx="60" cy="16" rx="9" ry="9"/></g>
  <rect class="pc-lacre" x="46" y="38" width="28" height="4"/>
  <g class="pc-selo"><circle cx="88" cy="70" r="11"/><path d="M83 70l4 4 6-8"/></g>
<?php else: ?>
  <path class="pc-fantasma" d="M25 35h60v110H25zM85 35l15-10v110l-15 10zM25 35l15-10h60l-15 10z"/>
  <g class="pc-conteudo"><rect x="32" y="48" width="46" height="14" rx="3"/><circle cx="40" cy="55" r="3"/><circle cx="50" cy="55" r="3"/><circle cx="60" cy="55" r="3"/><circle cx="70" cy="55" r="3"/></g>
  <g class="pc-corpo"><path d="M25 35h60v110H25z"/><path class="lado" d="M85 35l15-10v110l-15 10z"/></g>
  <rect class="pc-rotulo" x="25" y="66" width="60" height="62"/>
  <rect class="pc-arte" x="31" y="72" width="40" height="9" rx="2"/>
  <g class="pc-codigo" transform="translate(56 112)"><?= $barras ?></g>
  <path class="pc-tampa" d="M25 35l15-10h60l-15 10z"/>
  <rect class="pc-lacre" x="25" y="35" width="60" height="4"/>
  <g class="pc-selo"><circle cx="90" cy="54" r="11"/><path d="M85 54l4 4 6-8"/></g>
<?php endif; ?>
</svg>
