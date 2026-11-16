<?php
/**
 * One: horário de descanso (V13.6) — ESSENCIAL (idempotente).
 * Depois deste horário o One aparece dormindo, a saudação do Início avisa que é hora de descansar e,
 * uma vez por dia, ele dá o recado num balão.
 */
use App\Core\Banco;

if (!Banco::valor("SELECT 1 FROM configuracoes WHERE chave = 'one.hora_descanso'")) {
    Banco::inserir('configuracoes', ['chave' => 'one.hora_descanso', 'valor' => '17:45', 'tipo' => 'texto', 'grupo' => 'One (IA)',
        'rotulo' => 'Hora de descansar', 'descricao' => 'Depois deste horário (formato 17:45) o One dorme e avisa a equipe que é hora de descansar. Vazio = desligado.', 'ordem' => 40]);
}
return null;
