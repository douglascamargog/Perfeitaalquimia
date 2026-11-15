<?php
/**
 * Metas (V11.0) — DADOS ESSENCIAIS (idempotente): quais setores a meta da equipe cobre e de onde vêm as vendas do Meu mês.
 * Padrão: Televendas + Prospecção e Reativação (os Externos e a Terceirização têm as metas da própria frente).
 */
use App\Core\Banco;

if (!Banco::valor("SELECT 1 FROM configuracoes WHERE chave = 'metas.equipe_funcoes'")) {
    Banco::inserir('configuracoes', [
        'chave' => 'metas.equipe_funcoes', 'valor' => 'televendas,prospeccao_reativacao', 'tipo' => 'lista', 'grupo' => 'Metas',
        'rotulo' => 'Setores da meta da equipe',
        'descricao' => 'Códigos das funções separados por vírgula (Administração › Funções). Só essas pessoas veem a meta da equipe no Meu mês.',
        'ordem' => 10,
    ]);
}
if (!Banco::valor("SELECT 1 FROM configuracoes WHERE chave = 'metas.vendas_origem'")) {
    Banco::inserir('configuracoes', [
        'chave' => 'metas.vendas_origem', 'valor' => 'omie', 'tipo' => 'texto', 'grupo' => 'Metas',
        'rotulo' => 'De onde vêm as vendas do Meu mês',
        'descricao' => 'omie = pedidos faturados em que a pessoa é a vendedora (entram sozinhos). manual = cada pessoa registra as vendas no Meu mês.',
        'ordem' => 20,
    ]);
}
return null;
