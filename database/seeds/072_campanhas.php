<?php
/**
 * Campanhas comerciais (V12.0) — ESSENCIAL (instalação e atualizações; idempotente).
 *  - permissões "campanhas.gerenciar" (Gestor) e "campanhas.ver" (vendedores, Financeiro, Marketing, gestor de setor);
 *  - visual padrão do catálogo (quando nenhuma campanha muda as cores): editado em Comercial › Campanhas › Visual do catálogo.
 */
use App\Core\Banco;

Banco::executar("INSERT IGNORE INTO perfil_permissoes (perfil_id, permissao) SELECT id, 'campanhas.gerenciar' FROM perfis WHERE slug = 'gestor'");
Banco::executar("INSERT IGNORE INTO perfil_permissoes (perfil_id, permissao) SELECT id, 'campanhas.ver' FROM perfis WHERE slug IN ('vendedor', 'financeiro', 'marketing', 'gestor_setor')");

$configs = [
    ['catalogo.visual_titulo', 'Nossos produtos', 'texto', 'Catálogo', 'Título grande do catálogo (sem campanha no ar)', 'Aparece no alto do catálogo quando nenhuma campanha muda o visual.', 40],
    ['catalogo.visual_primaria', '', 'texto', 'Catálogo', 'Cor principal do catálogo', 'Vazio = cor primária da empresa. Formato #025150.', 41],
    ['catalogo.visual_destaque', '', 'texto', 'Catálogo', 'Cor de destaque do catálogo', 'Vazio = cor secundária da empresa. Formato #5cd4b6.', 42],
    ['catalogo.visual_tema', 'claro', 'texto', 'Catálogo', 'Fundo do catálogo (claro ou escuro)', 'claro ou escuro.', 43],
    ['catalogo.visual_banner', '', 'texto', 'Catálogo', 'Imagem do banner do catálogo', 'Caminho da imagem (trocada em Comercial › Campanhas › Visual do catálogo).', 44],
];
foreach ($configs as [$chave, $valor, $tipo, $grupo, $rotulo, $descricao, $ordem]) {
    if (!Banco::valor('SELECT 1 FROM configuracoes WHERE chave = ?', [$chave])) {
        Banco::inserir('configuracoes', compact('chave', 'valor', 'tipo', 'grupo', 'rotulo', 'descricao', 'ordem'));
    }
}
return null;
