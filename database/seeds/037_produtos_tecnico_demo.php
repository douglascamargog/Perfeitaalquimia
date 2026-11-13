<?php
/**
 * DEMONSTRAÇÃO de Produtos completo (só na instalação nova; "_demo" no nome = nunca roda em atualização):
 *  - usuário "Técnica (demo)" com o perfil Responsável Técnica;
 *  - especificações de alguns produtos que já têm ficha técnica;
 *  - documentos de exemplo (PDFs gerados aqui, com textos fictícios), um deles vencendo e um interno.
 */
use App\Core\Banco;
use App\Services\ProdutoService;

$perfil = (int) Banco::valor("SELECT id FROM perfis WHERE slug = 'tecnica'");
$funcao = (int) Banco::valor("SELECT id FROM funcoes WHERE slug = 'tecnica'");
$tecnica = (int) Banco::valor("SELECT id FROM usuarios WHERE email = 'tecnica@demo.local'");
if (!$tecnica && $perfil) {
    $tecnica = Banco::inserir('usuarios', ['nome' => 'Técnica (demo)', 'email' => 'tecnica@demo.local',
        'senha_hash' => password_hash('Demo@2026', PASSWORD_DEFAULT), 'perfil_id' => $perfil]);
    if ($funcao) {
        Banco::inserir('usuario_funcoes', ['usuario_id' => $tecnica, 'funcao_id' => $funcao]);
    }
}

/** PDF simples de uma página (texto), só para a demonstração */
$pdf = function (string $titulo, array $linhas): string {
    $limpa = fn(string $t) => str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], iconv('UTF-8', 'Windows-1252//TRANSLIT', $t));
    $texto = "BT /F1 16 Tf 60 780 Td (" . $limpa($titulo) . ") Tj /F1 11 Tf";
    foreach ($linhas as $l) {
        $texto .= " 0 -22 Td (" . $limpa($l) . ") Tj";
    }
    $texto .= ' ET';
    $obj = [
        '<< /Type /Catalog /Pages 2 0 R >>',
        '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
        '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>',
        '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
        '<< /Length ' . strlen($texto) . " >>\nstream\n$texto\nendstream",
    ];
    $saida = "%PDF-1.4\n";
    $pos = [];
    foreach ($obj as $i => $o) {
        $pos[] = strlen($saida);
        $saida .= ($i + 1) . " 0 obj\n$o\nendobj\n";
    }
    $xref = strlen($saida);
    $saida .= "xref\n0 " . (count($obj) + 1) . "\n0000000000 65535 f \n";
    foreach ($pos as $p) {
        $saida .= sprintf("%010d 00000 n \n", $p);
    }
    return $saida . "trailer\n<< /Size " . (count($obj) + 1) . " /Root 1 0 R >>\nstartxref\n$xref\n%%EOF\n";
};

$produtos = Banco::todos('SELECT p.id, p.nome, p.embalagem FROM produtos p WHERE p.ativo = 1 AND p.cliente_exclusivo_id IS NULL
    AND EXISTS (SELECT 1 FROM ficha_tecnica f WHERE f.produto_id = p.id) ORDER BY p.id LIMIT 6');
if (!is_dir(ProdutoService::pasta())) {
    mkdir(ProdutoService::pasta(), 0750, true);
}
mt_srand(5050);
$docs = 0;
foreach ($produtos as $i => $p) {
    if (!Banco::valor('SELECT 1 FROM produto_tecnico WHERE produto_id = ?', [$p['id']])) {
        Banco::inserir('produto_tecnico', [
            'produto_id' => $p['id'], 'ean' => '7890000' . str_pad((string) (100000 + $p['id']), 6, '0', STR_PAD_LEFT),
            'ncm' => '2106.90.30', 'registro_anvisa' => 'Dispensado de registro (exemplo)', 'validade_meses' => 24,
            'peso_liquido' => $p['embalagem'] ?: null, 'dimensoes' => '6 x 6 x 11 cm', 'unidades_caixa' => 48,
            'armazenamento' => 'Local seco e arejado, ao abrigo da luz, entre 15 °C e 30 °C.',
            'alergenicos' => 'Não contém glúten. Pode conter traços de soja (exemplo).',
            'avisos' => 'Este produto não é um medicamento. Não exceder a recomendação diária de consumo indicada na embalagem. Mantenha fora do alcance de crianças.',
            'atualizado_por' => $tecnica ?: null,
        ]);
    }
    if (Banco::valor('SELECT 1 FROM produto_documentos WHERE produto_id = ?', [$p['id']])) {
        continue;
    }
    $lista = [
        ['laudo', 'Laudo microbiológico (exemplo)', $i === 0 ? date('Y-m-d', strtotime('+12 days')) : date('Y-m-d', strtotime('+' . mt_rand(120, 400) . ' days')), 0],
        ['fispq', 'FISPQ (exemplo)', null, 0],
    ];
    if ($i === 0) {
        $lista[] = ['especificacao', 'Especificação do fornecedor (interno, exemplo)', null, 1];
    }
    foreach ($lista as [$tipo, $titulo, $validade, $interno]) {
        $arquivo = bin2hex(random_bytes(16)) . '.pdf';
        $conteudo = $pdf($titulo, ['Produto: ' . $p['nome'], 'DOCUMENTO DE DEMONSTRAÇÃO — dados fictícios.',
            'Validade: ' . ($validade ? date('d/m/Y', strtotime($validade)) : 'não se aplica')]);
        file_put_contents(ProdutoService::pasta() . '/' . $arquivo, $conteudo);
        Banco::inserir('produto_documentos', ['produto_id' => $p['id'], 'tipo' => $tipo, 'titulo' => $titulo, 'arquivo' => $arquivo,
            'nome_original' => strtolower($tipo) . '.pdf', 'mime' => 'application/pdf', 'tamanho' => strlen($conteudo), 'validade' => $validade,
            'interno' => $interno, 'enviado_por' => $tecnica ?: null, 'criado_em' => date('Y-m-d H:i:s', strtotime('-' . mt_rand(5, 60) . ' days'))]);
        $docs++;
    }
}
return 'Produtos (demonstração): ' . count($produtos) . " produtos com especificações e $docs documentos.";
