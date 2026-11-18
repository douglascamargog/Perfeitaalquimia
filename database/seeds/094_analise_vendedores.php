<?php
/**
 * V16.1 — DADOS ESSENCIAIS (idempotente): a Análise de clientes passa a abrir para o perfil Vendedor
 * (cada vendedor(a) vê só a própria carteira; Gestão continua vendo todas). Recarrega o passo a passo da tela.
 * Ajuste depois em Administração › Acessos.
 */
use App\Core\Banco;

if (!Banco::valor("SELECT 1 FROM migracoes WHERE arquivo = 'perm:analise_vendedor'")) {
    Banco::executar("INSERT IGNORE INTO perfil_permissoes (perfil_id, permissao) SELECT id, 'clientes.analise' FROM perfis WHERE slug = 'vendedor'");
    Banco::inserir('migracoes', ['arquivo' => 'perm:analise_vendedor']);
}
require __DIR__ . '/dados/carregar_treinamento.php';
return null;
