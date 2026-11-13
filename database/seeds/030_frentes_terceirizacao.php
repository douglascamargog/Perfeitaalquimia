<?php
/**
 * Frentes de venda e Terceirização — DADOS ESSENCIAIS (instalação e atualizações; idempotente).
 *  - frente de cada função padrão (Televendas, Externo, Terceirização) — o admin muda em Administração › Funções;
 *  - foco "Terceirização" na função Terceirização e Marca Própria;
 *  - permissões novas: Equipe de vendas (ver) e Terceirização (ver / editar). Nenhum acesso existente é retirado.
 */
use App\Core\Banco;

foreach (['televendas' => 'televendas', 'externo' => 'externo', 'terceirizacao' => 'terceirizacao'] as $slug => $frente) {
    Banco::executar('UPDATE funcoes SET frente = ? WHERE slug = ? AND frente IS NULL', [$frente, $slug]);
}
// Foco da função Terceirização: comercial + terceirização (se ainda não tiver)
Banco::executar("UPDATE funcoes SET areas_foco = CONCAT(COALESCE(NULLIF(areas_foco, ''), 'comercial'), ',terceirizacao')
                  WHERE slug = 'terceirizacao' AND FIND_IN_SET('terceirizacao', COALESCE(areas_foco, '')) = 0");
// Produção também acompanha os rótulos
Banco::executar("UPDATE funcoes SET areas_foco = CONCAT(COALESCE(NULLIF(areas_foco, ''), 'producao'), ',terceirizacao')
                  WHERE slug = 'producao' AND FIND_IN_SET('terceirizacao', COALESCE(areas_foco, '')) = 0");

$permissoes = [
    'equipe.ver'            => ['gestor', 'vendedor', 'financeiro'],
    'terceirizacao.ver'     => ['gestor', 'vendedor', 'financeiro', 'producao'],
    'terceirizacao.editar'  => ['gestor', 'vendedor', 'producao'],
];
foreach ($permissoes as $perm => $perfis) {
    foreach ($perfis as $perfil) {
        Banco::executar('INSERT IGNORE INTO perfil_permissoes (perfil_id, permissao) SELECT id, ? FROM perfis WHERE slug = ?', [$perm, $perfil]);
    }
}
return null;
