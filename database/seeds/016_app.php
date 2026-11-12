<?php
/**
 * App do vendedor externo (PWA) — DADOS ESSENCIAIS (instalação e atualizações; idempotente).
 */
use App\Core\Banco;

foreach (['vendedor', 'gestor'] as $perfil) {
    Banco::executar('INSERT IGNORE INTO perfil_permissoes (perfil_id, permissao) SELECT id, ? FROM perfis WHERE slug = ?', ['app.usar', $perfil]);
}
$configuracoes = [
    ['app.dias_cache', '7', 'numero', 'App do externo', 'Avisar para atualizar os dados após (dias)', 'Preços e carteira guardados no celular mais velhos que isso mostram um aviso', 55],
];
foreach ($configuracoes as [$chave, $valor, $tipo, $grupo, $rotulo, $descricao, $ordem]) {
    if (!Banco::valor('SELECT 1 FROM configuracoes WHERE chave = ?', [$chave])) {
        Banco::inserir('configuracoes', compact('chave', 'valor', 'tipo', 'grupo', 'rotulo', 'descricao', 'ordem'));
    }
}
