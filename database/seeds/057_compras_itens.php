<?php
/**
 * Compras da empresa e itens comprados — DADOS ESSENCIAIS (instalação e atualizações; idempotente).
 *  - o fornecedor que estava escrito no cadastro de cada item vira o fornecedor PRINCIPAL (uma vez só);
 *  - categorias dos itens (Configurações › Compras) e permissão "compras.lancar" (Financeiro);
 *  - Configurações › Diretoria: aviso de compra que entrou no estoque.
 */
use App\Core\Banco;

if (!Banco::valor("SELECT 1 FROM migracoes WHERE arquivo = 'compras:fornecedor-principal'")) {
    Banco::executar("INSERT INTO mp_fornecedores (materia_prima_id, fornecedor, ultimo_preco, principal)
        SELECT m.id, m.fornecedor, NULLIF(m.custo_unitario, 0), 1 FROM materias_primas m WHERE m.fornecedor IS NOT NULL AND TRIM(m.fornecedor) <> ''
          AND NOT EXISTS (SELECT 1 FROM mp_fornecedores f WHERE f.materia_prima_id = m.id)");
    Banco::executar("INSERT IGNORE INTO migracoes (arquivo) VALUES ('compras:fornecedor-principal')");
}

Banco::executar("INSERT IGNORE INTO perfil_permissoes (perfil_id, permissao) SELECT id, 'compras.lancar' FROM perfis WHERE slug = 'financeiro'");

$configs = [
    ['compras.categorias', 'Escritório,Limpeza,Manutenção,Embalagem de envio,Copa e cozinha,Outros', 'lista', 'Compras', 'Categorias dos itens de uso e consumo',
        'Separadas por vírgula. Usadas em Financeiro › Compras (matéria-prima, embalagem e rótulo já têm o próprio tipo).', 20],
    ['diretoria.aviso_compras', '1', 'booleano', 'Diretoria', 'Avisar: compra que entrou no estoque (nota do Omie)', 'Vai para a Diretoria (e o Admin) que acompanham a área "Diretoria".', 70],
];
foreach ($configs as [$chave, $valor, $tipo, $grupo, $rotulo, $descricao, $ordem]) {
    if (!Banco::valor('SELECT 1 FROM configuracoes WHERE chave = ?', [$chave])) {
        Banco::inserir('configuracoes', compact('chave', 'valor', 'tipo', 'grupo', 'rotulo', 'descricao', 'ordem'));
    }
}
return null;
