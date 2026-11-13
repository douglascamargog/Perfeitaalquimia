<?php
/**
 * Produtos completo — DADOS ESSENCIAIS (instalação e atualizações; idempotente).
 *  - perfil "Responsável Técnica" (preenche a parte técnica dos produtos e vê a Produção);
 *  - função "Responsável Técnica e Química" (foco: Produção);
 *  - permissão nova "produtos.tecnico" (Técnica) e "produtos.ver" para a equipe (documentos são de todos);
 *  - classifica como EMBALAGEM os itens em "un" com nome de pote, tampa, rótulo, lacre, caixa...
 * Nenhum acesso existente é retirado. Tudo se ajusta depois em Administração › Acessos e › Funções.
 */
use App\Core\Banco;

Banco::executar("INSERT IGNORE INTO perfis (slug, nome, descricao)
                 VALUES ('tecnica', 'Responsável Técnica', 'Parte técnica dos produtos: composição, ficha técnica, embalagens e documentos')");

foreach (['produtos.tecnico', 'produtos.ver', 'producao.ver', 'academia.ver'] as $perm) {
    Banco::executar("INSERT IGNORE INTO perfil_permissoes (perfil_id, permissao) SELECT id, ? FROM perfis WHERE slug = 'tecnica'", [$perm]);
}
// A equipe toda passa a ver a página de Produtos (só leitura)
foreach (['gestor', 'vendedor', 'financeiro', 'producao'] as $perfil) {
    Banco::executar("INSERT IGNORE INTO perfil_permissoes (perfil_id, permissao) SELECT id, 'produtos.ver' FROM perfis WHERE slug = ?", [$perfil]);
}

if (!Banco::valor("SELECT 1 FROM funcoes WHERE slug = 'tecnica'")) {
    Banco::executar("INSERT INTO funcoes (slug, nome, descricao, areas_foco, ordem)
                     SELECT 'tecnica', 'Responsável Técnica e Química', 'Fórmulas, fichas técnicas, embalagens e documentos dos produtos', 'producao', COALESCE(MAX(ordem), 0) + 1 FROM funcoes");
}

// Configuração: quantos dias antes do vencimento de um documento o sistema avisa
if (!Banco::valor("SELECT 1 FROM configuracoes WHERE chave = 'produtos.dias_aviso_documento'")) {
    Banco::inserir('configuracoes', ['chave' => 'produtos.dias_aviso_documento', 'valor' => '30', 'tipo' => 'numero', 'grupo' => 'Produção',
        'rotulo' => 'Aviso de documento vencendo (dias)', 'descricao' => 'Produtos › Documentos: quantos dias antes da validade o sistema avisa (laudos, registros, certificados).', 'ordem' => 90]);
}

// Embalagens: só itens em "un" que ainda são "matéria-prima" (o que alguém já mudou à mão não é tocado)
$n = Banco::executar("UPDATE materias_primas SET tipo = 'embalagem'
     WHERE tipo = 'materia_prima' AND unidade = 'un'
       AND nome REGEXP '^(pote|tampa|r[oó]tulo|lacre|caixa|frasco|sach[eê]|saco|embalagem|blister|bisnaga|etiqueta)'");
return $n ? "Produtos: $n item(ns) classificado(s) como embalagem." : null;
