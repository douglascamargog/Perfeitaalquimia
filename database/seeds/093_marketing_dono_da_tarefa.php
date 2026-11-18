<?php
/**
 * V16.0 — DADOS ESSENCIAIS (roda uma vez): fim do "quem pegar primeiro" no Marketing.
 * As tarefas abertas que ainda estão sem dono passam para quem cuida da função delas
 * (a única pessoa da função, ou a de cadastro mais antigo até o gestor escolher em Início do Marketing › Quem cuida de quê).
 */
use App\Core\Banco;
use App\Services\MarketingTarefaService as T;

$n = 0;
foreach (Banco::todos("SELECT DISTINCT funcao_slug FROM mkt_tarefas WHERE responsavel_id IS NULL AND situacao IN ('a_fazer','fazendo','aprovacao') AND funcao_slug IS NOT NULL") as $f) {
    $dono = T::responsavelDaFuncao((string) $f['funcao_slug']);
    if ($dono) {
        $n += Banco::executar("UPDATE mkt_tarefas SET responsavel_id = ? WHERE funcao_slug = ? AND responsavel_id IS NULL AND situacao IN ('a_fazer','fazendo','aprovacao')", [$dono, $f['funcao_slug']]);
    }
}
return $n ? "$n tarefa(s) do Marketing passaram a ter dono" : null;
