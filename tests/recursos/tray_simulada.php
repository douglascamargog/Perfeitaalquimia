<?php
// Tray SIMULADA (só para testes): php -S 127.0.0.1:8096 tests/recursos/tray_simulada.php
// Chaves certas: consumer_key "chave-certa-123", consumer_secret "segredo-certo-456", code "code-certo-789".
// POST /web_api/auth → tokens; GET /web_api/orders?access_token=token-acesso-1 → 1 pedido.
header('Content-Type: application/json');
$caminho = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($caminho === '/web_api/auth' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (($_POST['consumer_key'] ?? '') !== 'chave-certa-123' || ($_POST['consumer_secret'] ?? '') !== 'segredo-certo-456' || ($_POST['code'] ?? '') !== 'code-certo-789') {
        http_response_code(401);
        echo '{"message":"Unauthorized","code":401}';
        return;
    }
    echo json_encode(['code' => 200, 'message' => 'Ok', 'access_token' => 'token-acesso-1', 'refresh_token' => 'token-renova-1',
        'date_expiration_access_token' => date('Y-m-d H:i:s', time() + 3 * 3600), 'date_expiration_refresh_token' => date('Y-m-d H:i:s', time() + 30 * 86400),
        'date_activated' => date('Y-m-d H:i:s'), 'api_host' => 'http://127.0.0.1:8096/web_api', 'store_id' => '391250']);
    return;
}
if ($caminho === '/web_api/orders') {
    if (($_GET['access_token'] ?? '') !== 'token-acesso-1') {
        http_response_code(401);
        echo '{"message":"Unauthorized"}';
        return;
    }
    echo json_encode(['paging' => ['total' => 1, 'page' => 1, 'offset' => 0, 'limit' => 1, 'maxLimit' => 50],
        'Orders' => [['Order' => ['id' => '1001', 'status' => 'A ENVIAR', 'date' => date('Y-m-d'), 'total' => '199.90', 'customer_id' => '9']]]]);
    return;
}
http_response_code(404);
echo '{"message":"not found"}';
