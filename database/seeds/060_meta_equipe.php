<?php
/**
 * Meta da equipe (V11.0) — DADO ESSENCIAL (idempotente): quais setores a meta da equipe cobre.
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
return null;
