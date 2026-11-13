<?php
/** Histórico: quem mudou o quê no produto (a partir do log de auditoria) */
use App\Services\ProdutoService as PS;

$campos = ['descriptionParagraphs' => 'descrição', 'explain' => 'descrição', 'benefits' => 'benefícios', 'use' => 'modo de uso', 'care' => 'cuidados', 'faq' => 'perguntas',
    'sales' => 'argumento de venda', 'chamada' => 'chamada', 'ingredients' => 'ingredientes', 'capsule' => 'cápsula', 'serving' => 'porção', 'servings' => 'porções',
    'storage' => 'conservação', 'tableNote' => 'nota da tabela', 'features' => 'selos', 'nutrition' => 'tabela nutricional', 'alergenicos' => 'alergênicos',
    'avisos' => 'avisos', 'ean' => 'EAN', 'ncm' => 'NCM', 'registro_anvisa' => 'registro', 'validade_meses' => 'validade', 'peso_liquido' => 'peso líquido',
    'peso_bruto' => 'peso bruto', 'dimensoes' => 'dimensões', 'unidades_caixa' => 'unidades por caixa', 'armazenamento' => 'armazenamento', 'observacoes' => 'observações',
    'visivel_catalogo' => 'visível no catálogo', 'ordem' => 'ordem', 'codigo' => 'código', 'itens' => 'itens'];
$detalhe = function (array $h) use ($campos): string {
    $depois = json_decode((string) $h['depois_json'], true) ?: [];
    $antes = json_decode((string) $h['antes_json'], true) ?: [];
    if ($h['entidade'] === 'produto_documento') {
        return (string) ($depois['documento'] ?? $antes['documento'] ?? '');
    }
    $nomes = array_unique(array_filter(array_map(fn($k) => $campos[$k] ?? null, array_keys($depois + $antes))));
    return $nomes ? 'Mudou: ' . implode(', ', $nomes) : '';
};
?>
<section class="card">
  <?php if (!$historico): ?><p class="suave">Nenhuma alteração registrada ainda.</p><?php else: ?>
  <ol class="pd-historico">
    <?php foreach ($historico as $h): ?>
      <li><time><?= data_br($h['criado_em'], true) ?></time>
        <div><strong><?= e(PS::descreverMudanca($h)) ?></strong> <span class="suave">· <?= e($h['usuario'] ?? 'Sistema') ?></span>
          <?php if ($txt = $detalhe($h)): ?><p class="pequeno"><?= e($txt) ?></p><?php endif; ?></div></li>
    <?php endforeach; ?>
  </ol>
  <?php endif; ?>
</section>
