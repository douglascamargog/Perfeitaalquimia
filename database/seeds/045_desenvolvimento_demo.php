<?php
/**
 * DEMONSTRAÇÃO do Desenvolvimento de Produto (só na instalação nova): usuário "Qualidade (demo)" (Qualidade + Marketing)
 * e 3 projetos em etapas diferentes — um na Formulação, um na Aprovação da prova da gráfica e um de Terceirização.
 */
use App\Core\Banco;
use App\Services\DesenvolvimentoService as D;
use App\Services\ProdutoService;

if (Banco::valor('SELECT 1 FROM dp_projetos LIMIT 1')) {
    return null;
}
$perfil = (int) Banco::valor("SELECT id FROM perfis WHERE slug = 'marketing'");
$qual = (int) Banco::valor("SELECT id FROM usuarios WHERE email = 'qualidade@demo.local'");
if (!$qual && $perfil) {
    $qual = Banco::inserir('usuarios', ['nome' => 'Qualidade (demo)', 'email' => 'qualidade@demo.local', 'senha_hash' => password_hash('Demo@2026', PASSWORD_DEFAULT), 'perfil_id' => $perfil]);
    foreach (['qualidade', 'marketing'] as $slug) {
        Banco::executar('INSERT IGNORE INTO usuario_funcoes (usuario_id, funcao_id) SELECT ?, id FROM funcoes WHERE slug = ?', [$qual, $slug]);
    }
}
$gestor = (int) Banco::valor("SELECT id FROM usuarios WHERE email = 'gestor@demo.local'") ?: null;
$etapas = D::etapas();
$irAte = function (int $projeto, string $checagem) use ($etapas, $gestor): void {
    foreach ($etapas as $e) {
        if ($e['checagem'] === $checagem) {
            Banco::executar("UPDATE dp_projeto_etapas SET situacao = 'andamento' WHERE projeto_id = ? AND etapa_id = ?", [$projeto, $e['id']]);
            Banco::executar('UPDATE dp_projetos SET etapa_id = ? WHERE id = ?', [$e['id'], $projeto]);
            return;
        }
        Banco::executar("UPDATE dp_projeto_etapas SET situacao = 'concluida', concluida_em = NOW() - INTERVAL 3 DAY, concluida_por = ? WHERE projeto_id = ? AND etapa_id = ?", [$gestor, $projeto, $e['id']]);
        Banco::inserir('dp_historico', ['projeto_id' => $projeto, 'usuario_id' => $gestor, 'tipo' => 'etapa', 'texto' => 'Etapa concluída: ' . $e['nome'], 'criado_em' => date('Y-m-d H:i:s', strtotime('-3 days'))]);
    }
};
$cat = (int) Banco::valor("SELECT id FROM categorias ORDER BY id LIMIT 1") ?: null;

// 1. Na Formulação (com a Técnica)
$p1 = D::criar(['nome' => 'Colágeno + Vitamina C 300 g', 'briefing' => "Pó sabor limão para lojas de produtos naturais.\nPúblico: mulheres 35+. Diferencial: vitamina C na mesma dose.",
    'categoria_id' => $cat, 'embalagem' => 'Pote 300 g', 'prazo' => date('Y-m-d', strtotime('+45 days'))], $gestor);
$irAte($p1, 'ficha');

// 2. Na Aprovação da prova da gráfica: ficha, composição, EAN/NCM, arte e prova prontos; Marketing já aprovou
$p2 = D::criar(['nome' => 'Magnésio Dimalato 120 cápsulas', 'briefing' => 'Versão maior do magnésio para quem compra todo mês.', 'categoria_id' => $cat,
    'embalagem' => 'Pote 120 cápsulas', 'prazo' => date('Y-m-d', strtotime('+10 days'))], $gestor);
$prod2 = (int) Banco::valor('SELECT produto_id FROM dp_projetos WHERE id = ?', [$p2]);
$base = Banco::um("SELECT p.id, p.ficha_json FROM produtos p WHERE p.ativo = 1 AND EXISTS (SELECT 1 FROM ficha_tecnica f WHERE f.produto_id = p.id) ORDER BY p.id LIMIT 1");
if ($base) {
    Banco::executar('INSERT INTO ficha_tecnica (produto_id, materia_prima_id, quantidade) SELECT ?, materia_prima_id, quantidade FROM ficha_tecnica WHERE produto_id = ?', [$prod2, $base['id']]);
    $f = ['ingredients' => 'Dimalato de magnésio, cápsula vegetal.', 'serving' => '2 cápsulas', 'nutrition' => [['Magnésio', '260 mg', '100%']], 'benefits' => ['Magnésio em dose dupla (exemplo)']];
    Banco::executar('UPDATE produtos SET ficha_json = ? WHERE id = ?', [json_encode($f, JSON_UNESCAPED_UNICODE), $prod2]);
}
Banco::inserir('produto_tecnico', ['produto_id' => $prod2, 'ean' => '7890000200014', 'ncm' => '2106.90.30', 'validade_meses' => 24]);
if (!is_dir(ProdutoService::pasta())) {
    mkdir(ProdutoService::pasta(), 0750, true);
}
foreach (['rotulo' => 'Arte do rótulo v2 (exemplo)', 'prova' => 'Prova da gráfica (exemplo)'] as $tipo => $titulo) {
    $arquivo = bin2hex(random_bytes(16)) . '.pdf';
    $pdf = "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj 2 0 obj<</Type/Pages/Kids[]/Count 0>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n";
    file_put_contents(ProdutoService::pasta() . '/' . $arquivo, $pdf);
    Banco::inserir('produto_documentos', ['produto_id' => $prod2, 'tipo' => $tipo, 'titulo' => $titulo, 'arquivo' => $arquivo, 'nome_original' => "$tipo.pdf",
        'mime' => 'application/pdf', 'tamanho' => strlen($pdf), 'enviado_por' => $qual ?: null]);
}
$itens = array_column(Banco::todos('SELECT materia_prima_id FROM ficha_tecnica WHERE produto_id = ?', [$prod2]), 'materia_prima_id');
Banco::executar('UPDATE dp_projetos SET compras_ok = ?, grafica = ?, grafica_pedido_em = CURDATE() - INTERVAL 4 DAY WHERE id = ?', [json_encode(array_map('intval', $itens)), 'Gráfica Pampa (exemplo)', $p2]);
$irAte($p2, 'aprovacao');
$marketing = (int) Banco::valor("SELECT id FROM usuarios WHERE email = 'marketing@demo.local'");
$etapaAprov = (int) Banco::valor('SELECT etapa_id FROM dp_projetos WHERE id = ?', [$p2]);
if ($marketing) {
    Banco::inserir('dp_aprovacoes', ['projeto_id' => $p2, 'etapa_id' => $etapaAprov, 'funcao_slug' => 'marketing', 'usuario_id' => $marketing, 'decisao' => 'aprovado', 'rodada' => 1]);
    Banco::inserir('dp_historico', ['projeto_id' => $p2, 'usuario_id' => $marketing, 'tipo' => 'aprovacao', 'texto' => 'Marketing aprovou a prova da gráfica.']);
}

// 3. Terceirização, na Criação do rótulo
$cliente = (int) Banco::valor("SELECT c.id FROM clientes c JOIN funcoes f ON f.id = c.funcao_id WHERE f.slug = 'terceirizacao' AND c.excluido_em IS NULL ORDER BY c.id LIMIT 1");
if ($cliente) {
    $p3 = D::criar(['nome' => 'Chá Detox Marca do Cliente 60 g', 'briefing' => 'Chá com a marca do cliente, mesma base do nosso chá verde.', 'cliente_id' => $cliente,
        'embalagem' => 'Sachê 60 g', 'prazo' => date('Y-m-d', strtotime('-2 days'))], $gestor);
    $irAte($p3, 'arte');
}
return 'Desenvolvimento (demonstração): usuário Qualidade e 3 projetos em etapas diferentes.';
