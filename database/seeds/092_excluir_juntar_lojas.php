<?php
/**
 * V15.7 — DADOS ESSENCIAIS (idempotente): permissão para excluir, restaurar e juntar lojas repetidas (Gestor; o Admin tem tudo).
 * Ajuste depois em Administração › Acessos › ações especiais.
 */
use App\Core\Banco;

if (!Banco::valor("SELECT 1 FROM migracoes WHERE arquivo = 'perm:carteira_excluir'")) {
    Banco::executar("INSERT IGNORE INTO perfil_permissoes (perfil_id, permissao) SELECT id, 'carteira.excluir' FROM perfis WHERE slug = 'gestor'");
    Banco::inserir('migracoes', ['arquivo' => 'perm:carteira_excluir']);
}
return null;
