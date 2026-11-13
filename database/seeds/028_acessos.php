<?php
/**
 * Acessos e foco — DADOS ESSENCIAIS (instalação e atualizações; idempotente).
 *  - Áreas de foco padrão de cada função (o que aparece no Início, no One, no sino e nos números do menu).
 *  - Vendedores passam a VER o Mural de posts e o Planejamento do Marketing (só leitura).
 * Nenhum acesso existente é retirado. Depois, tudo se ajusta em Administração › Acessos e › Funções.
 */
use App\Core\Banco;

$focoPadrao = [
    'televendas' => 'comercial', 'externo' => 'comercial', 'prospeccao_reativacao' => 'comercial', 'terceirizacao' => 'comercial',
    'marketing' => 'marketing', 'producao' => 'producao', 'cobranca' => 'cobranca,aprovacao',
];
foreach ($focoPadrao as $slug => $areas) {
    Banco::executar('UPDATE funcoes SET areas_foco = ? WHERE slug = ? AND areas_foco IS NULL', [$areas, $slug]);
}
// Funções criadas pela empresa (sem padrão): comercial
Banco::executar("UPDATE funcoes SET areas_foco = 'comercial' WHERE areas_foco IS NULL");

// Vendedores: ver o Marketing (mural e planejamento), sem alterar
Banco::executar("INSERT IGNORE INTO perfil_permissoes (perfil_id, permissao) SELECT id, 'marketing.ver' FROM perfis WHERE slug = 'vendedor'");
