<?php
// API da Anthropic SIMULADA (só para testes): php -S 127.0.0.1:8099 tests/recursos/anthropic_simulada.php
// 1ª chamada da conversa: pede a consulta "meus_numeros"; depois que recebe o resultado, responde com um texto.
// Guarda cada pedido recebido em /tmp/anthropic_simulada.log (para o teste conferir o formato).
$corpo = file_get_contents('php://input');
file_put_contents('/tmp/anthropic_simulada.log', json_encode(['caminho' => $_SERVER['REQUEST_URI'], 'beta' => $_SERVER['HTTP_ANTHROPIC_BETA'] ?? '', 'corpo' => json_decode($corpo, true)]) . "\n", FILE_APPEND);
$d = json_decode($corpo, true) ?: [];
$ultima = end($d['messages']);
$temResultado = is_array($ultima['content'] ?? null) && ($ultima['content'][0]['type'] ?? '') === 'tool_result';
header('Content-Type: application/json');
$uso = ['input_tokens' => 1200, 'output_tokens' => 80];
if (!$temResultado) {
    echo json_encode(['id' => 'msg_1', 'type' => 'message', 'role' => 'assistant', 'model' => $d['model'], 'stop_reason' => 'tool_use', 'stop_sequence' => null, 'usage' => $uso,
        'content' => [['type' => 'tool_use', 'id' => 'toolu_1', 'name' => 'meus_numeros', 'input' => new stdClass()]]]);
} else {
    $dados = json_decode($ultima['content'][0]['content'], true);
    echo json_encode(['id' => 'msg_2', 'type' => 'message', 'role' => 'assistant', 'model' => $d['model'], 'stop_reason' => 'end_turn', 'stop_sequence' => null, 'usage' => $uso,
        'content' => [['type' => 'text', 'text' => 'Você já vendeu **' . ($dados['vendido_no_mes'] ?? '?') . '** neste mês. Veja em [Meu mês](/meu-mes).']]]);
}
