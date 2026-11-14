<?php
/**
 * Ficha rápida e discador — DADOS ESSENCIAIS (idempotente).
 *  - Configurações › Ligue Talk: funções que veem o discador (ícone de telefone ao lado do sino).
 */
use App\Core\Banco;

if (!Banco::valor("SELECT 1 FROM configuracoes WHERE chave = 'telefonia.discador_funcoes'")) {
    Banco::inserir('configuracoes', ['chave' => 'telefonia.discador_funcoes', 'valor' => 'televendas,cobranca', 'tipo' => 'lista', 'grupo' => 'Ligue Talk',
        'rotulo' => 'Funções que veem o discador', 'descricao' => 'Slugs das funções (Administração › Funções), separados por vírgula. Essas pessoas veem o telefone ao lado do sino para ligar para qualquer número.',
        'ordem' => 90]);
}
if (!Banco::valor("SELECT 1 FROM configuracoes WHERE chave = 'inicio.letreiro_pedidos'")) {
    $grupo = Banco::valor("SELECT grupo FROM configuracoes WHERE chave LIKE 'inicio.%' LIMIT 1") ?: 'Início';
    Banco::inserir('configuracoes', ['chave' => 'inicio.letreiro_pedidos', 'valor' => '1', 'tipo' => 'booleano', 'grupo' => $grupo,
        'rotulo' => 'Letreiro de pedido novo no topo', 'descricao' => '"Novo pedido feito por Fulana" passa por 30 segundos no topo de todos quando entra um pedido.', 'ordem' => 95]);
}
return null;
