<?php
/**
 * Fórmulas (V15.0) — DADOS ESSENCIAIS (idempotente): permissões da área de Fórmulas.
 *  - formulas.ver: Responsável Técnica, Qualidade, Produção, Gestor e Diretoria;
 *  - formulas.editar: Responsável Técnica;
 *  - formulas.aprovar: Responsável Técnica e Diretoria.
 * (Admin tem tudo. Ajustes depois em Administração › Acessos.)
 */
use App\Core\Banco;

$porPerfil = [
    'tecnica' => ['formulas.ver', 'formulas.editar', 'formulas.aprovar'],
    'diretoria' => ['formulas.ver', 'formulas.aprovar'],
    'qualidade' => ['formulas.ver'],
    'producao' => ['formulas.ver'],
    'gestor' => ['formulas.ver'],
];
if (!Banco::valor("SELECT 1 FROM migracoes WHERE arquivo = 'perm:formulas'")) {
    foreach ($porPerfil as $perfil => $perms) {
        foreach ($perms as $perm) {
            Banco::executar('INSERT IGNORE INTO perfil_permissoes (perfil_id, permissao) SELECT id, ? FROM perfis WHERE slug = ?', [$perm, $perfil]);
        }
    }
    Banco::inserir('migracoes', ['arquivo' => 'perm:formulas']);
}
return null;
