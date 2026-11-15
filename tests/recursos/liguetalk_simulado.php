<?php
// Ligue Talk SIMULADO (só para testes): php -S 127.0.0.1:8097 tests/recursos/liguetalk_simulado.php
// Token certo: "token-certo-123". users_report_v1 devolve dois usuários (um ligado a alguém do One no teste).
header('Content-Type: application/json');
if (($_SERVER['HTTP_X_LTK_VND'] ?? '') !== 'token-certo-123' || ($_GET['company_id'] ?? '') !== '777') {
    http_response_code(403);
    echo '{"erro":"nao autorizado"}';
    return;
}
if (str_contains($_SERVER['REQUEST_URI'], 'users_report_v1')) {
    echo json_encode(['data' => [['usr_id' => '501', 'usr_nome' => 'Vendedora Um'], ['usr_id' => '502', 'usr_nome' => 'Vendedora Dois']]]);
    return;
}
echo json_encode(['data' => []]);
