<?php
// Tray SIMULADA (só para testes): php -S 127.0.0.1:8096 tests/recursos/tray_simulada.php
// Chaves certas: consumer_key "chave-certa-123", consumer_secret "segredo-certo-456", code "code-certo-789".
// POST /web_api/auth → tokens; /web_api/orders (lista, /complete, /statuses) com pedidos da loja e do Mercado Livre.
header('Content-Type: application/json');
$caminho = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($caminho === '/web_api/auth' && $_SERVER['REQUEST_METHOD'] === 'GET') {   // renovar
    http_response_code(401);
    echo '{"message":"Invalid or expired token","error_code":1000}';
    return;
}
if ($caminho === '/web_api/auth' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $_POST = json_decode((string) file_get_contents('php://input'), true) ?: $_POST;   // JSON (ou formulário)
    // code "code-sem-acesso-1": gera a chave, mas a loja recusa a chave depois (erro 1000), como aconteceu de verdade
    if (($_POST['code'] ?? '') === 'code-sem-acesso-1') {
        http_response_code(201);
        echo json_encode(['code' => 201, 'access_token' => 'token-recusado', 'refresh_token' => 'renova-recusado',
            'date_expiration_access_token' => date('Y-m-d H:i:s', time() + 3 * 3600), 'date_expiration_refresh_token' => date('Y-m-d H:i:s', time() + 30 * 86400)]);
        return;
    }
    if (($_POST['consumer_key'] ?? '') !== 'chave-certa-123' || ($_POST['consumer_secret'] ?? '') !== 'segredo-certo-456' || ($_POST['code'] ?? '') !== 'code-certo-789') {
        http_response_code(401);
        echo '{"message":"Unauthorized","code":401,"causes":["Invalid code"]}';
        return;
    }
    http_response_code(201);
    echo json_encode(['code' => 201, 'message' => 'Created tokens', 'access_token' => 'token-acesso-1', 'refresh_token' => 'token-renova-1',
        'date_expiration_access_token' => date('Y-m-d H:i:s', time() + 3 * 3600), 'date_expiration_refresh_token' => date('Y-m-d H:i:s', time() + 30 * 86400),
        'date_activated' => date('Y-m-d H:i:s'), 'api_host' => 'http://127.0.0.1:8096/web_api', 'store_id' => '391250']);
    return;
}
// ----- Pedidos (V14.0) -----
// Estado mutável (o teste muda o status de um pedido): arquivo JSON em TRAY_ESTADO, ex.: {"1281": {"status": "CANCELADO", "modified": "..."}}
// Cada chamada de /complete é contada em TRAY_ESTADO.contagem (para conferir que pedido sem mudança não é lido de novo).
if (str_starts_with($caminho, '/web_api/orders')) {
    if (($_GET['access_token'] ?? '') !== 'token-acesso-1') {
        http_response_code(401);
        echo '{"message":"Invalid or expired token","error_code":1000}';
        return;
    }
    $arquivo = getenv('TRAY_ESTADO') ?: sys_get_temp_dir() . '/tray_estado.json';
    $estado = is_file($arquivo) ? (json_decode((string) file_get_contents($arquivo), true) ?: []) : [];
    $dia = fn(int $n) => date('Y-m-d', strtotime("-$n days"));
    $agora = date('Y-m-d H:i:s');
    $cliente = fn($id, $nome, $cidade, $uf) => ['id' => $id, 'name' => $nome, 'cpf' => '92976518017', 'email' => strtolower(explode(' ', $nome)[0]) . '@exemplo.com.br',
        'phone' => '11933330002', 'city' => $cidade, 'state' => $uf, 'CustomerAddresses' => [['CustomerAddress' => ['city' => $cidade, 'state' => $uf, 'recipient' => $nome, 'address' => 'Rua Secreta 10']]]];
    $item = fn($ref, $nome, $qtd, $preco, $custo = '0.00') => ['ProductsSold' => ['product_id' => $ref, 'reference' => $ref, 'quantity' => (string) $qtd, 'price' => $preco, 'cost_price' => $custo,
        'name' => $nome, 'original_name' => '', 'Category' => [['name' => 'Roupas']], 'ProductSoldImage' => []]];
    $pedidos = [
        '1281' => ['status' => 'A ENVIAR', 'date' => $dia(40), 'hour' => '18:21:00', 'point_sale' => 'LOJA VIRTUAL', 'total' => '899.00', 'partial_total' => '899.00', 'shipment_value' => '0.00',
            'payment_method' => 'Pix - Vindi', 'payment_method_type' => 'pix', 'shipment' => 'Sedex', 'shipment_integrator' => 'Correios', 'has_invoice' => '1', 'modified' => $dia(39) . ' 10:00:00',
            'Customer' => $cliente('31', 'Sdc Cliente Teste Outro', 'Franca', 'SP'), 'OrderInvoice' => [['OrderInvoice' => ['number' => '999002', 'issue_date' => $dia(39) . ' 09:00:00']]],
            'ProductsSold' => [$item('519550', 'Roupa Cirurgica Gato Confort Verde (teste Tray) (Ref. 519550)<br /><strong>Tamanho</strong> 02 (Disponibilidade: Imediata)<br />', 1, '899.00', '300.00')]],
        '2029' => ['status' => 'A ENVIAR', 'date' => $dia(1), 'hour' => '15:41:03', 'point_sale' => 'MERCADO LIVRE', 'total' => '21.85', 'partial_total' => '10.00', 'shipment_value' => '11.85',
            'payment_method' => 'Mercado Pago', 'payment_method_type' => 'other', 'shipment' => 'Mercado Envios', 'has_invoice' => '1', 'modified' => $dia(1) . ' 15:45:00',
            'Customer' => $cliente('41', 'Jhonatan Cardoso', 'Campinas', 'SP'), 'OrderInvoice' => [['OrderInvoice' => ['number' => '999010', 'issue_date' => $dia(1) . ' 16:00:00']]],
            'MarketplaceOrder' => [['MarketplaceOrder' => ['marketplace_name' => 'Mercado Livre', 'marketplace_order_id' => '2000009988776655', 'marketplace_shipping_type' => 'me2', 'marketplace_internal_status' => 'ready_to_ship']]],
            'ProductsSold' => [$item('MOTO32', 'MOTO G32 1GB RAM HD (Ref. 000004) (Disponibilidade: Imediata)<br />', 1, '10.00')]],
        '2023' => ['status' => 'PENDENTE', 'date' => $dia(1), 'hour' => '15:01:15', 'point_sale' => 'PARTICULAR', 'total' => '30.00', 'partial_total' => '30.00', 'shipment_value' => '0.00',
            'payment_method' => 'Boleto', 'payment_method_type' => 'bank_billet', 'shipment' => 'Retirada', 'has_invoice' => '0', 'modified' => $dia(1) . ' 15:02:00',
            'Customer' => $cliente('51', 'Cliente Teste Api', 'Porto Alegre', 'RS'), 'ProductsSold' => [$item('AGUA500', 'AGUA DIAS S/GAS 500ML', 2, '15.00')]],
        '2019' => ['status' => 'Aguardando Vindi', 'date' => $dia(1), 'hour' => '12:14:54', 'point_sale' => 'LOJA VIRTUAL', 'total' => '101.50', 'partial_total' => '69.90', 'shipment_value' => '31.60',
            'payment_method' => 'Cartão - Vindi', 'payment_method_type' => 'credit_card', 'shipment' => 'Sedex', 'has_invoice' => '0', 'modified' => $dia(1) . ' 12:15:00',
            'Customer' => $cliente('61', 'Ana Paula', 'Curitiba', 'PR'), 'ProductsSold' => [$item('CAD8', 'Cadeira BBB Boucle (Ref. CABBB-8)<br /><strong>Cor</strong> Terracota', 1, '69.90')]],
    ];
    // Como o #2029 do print: "A ENVIAR", mas a Tray ainda não confirmou o pagamento (has_payment = 0) → não é venda
    $pedidos['2033'] = ['status' => 'A ENVIAR', 'date' => $dia(1), 'hour' => '16:10:00', 'point_sale' => 'LOJA VIRTUAL', 'total' => '45.00', 'partial_total' => '45.00',
        'shipment_value' => '0.00', 'payment_method' => 'Mercado Pago', 'payment_method_type' => 'other', 'shipment' => 'PAC', 'has_invoice' => '0', 'has_payment' => '0',
        'modified' => $dia(1) . ' 16:11:00', 'Customer' => $cliente('71', 'Pessoa Sem Pagamento', 'Maringá', 'PR'), 'ProductsSold' => [$item('CAMISA', 'Camisa Teste', 1, '45.00')]];
    for ($i = 0; $i < 12; $i++) { // pedidos comuns da loja (para testar o limite por rodada)
        $pedidos[(string) (3000 + $i)] = ['status' => 'FINALIZADO', 'date' => $dia(5 + $i), 'hour' => '10:00:00', 'point_sale' => 'LOJA VIRTUAL', 'total' => '100.00', 'partial_total' => '100.00',
            'shipment_value' => '0.00', 'payment_method' => 'Pix', 'payment_method_type' => 'pix', 'shipment' => 'PAC', 'has_invoice' => '1', 'modified' => $dia(4 + $i) . ' 10:00:00', 'delivered' => '1',
            'Customer' => $cliente((string) (100 + $i % 3), 'Fulano Teste', 'Canoas', 'RS'), 'ProductsSold' => [$item('CAMISA', 'Camisa Teste', 1, '100.00')]];
    }
    foreach ($estado['pedidos'] ?? [] as $id => $mudou) {
        if (isset($pedidos[$id])) {
            $pedidos[$id] = $mudou + $pedidos[$id];
        }
    }
    // /orders/{id}/complete
    if (preg_match('#^/web_api/orders/(\d+)/complete$#', $caminho, $m)) {
        $estado['contagem'][$m[1]] = ($estado['contagem'][$m[1]] ?? 0) + 1;
        file_put_contents($arquivo, json_encode($estado));
        if (!isset($pedidos[$m[1]])) {
            http_response_code(404);
            echo '{"message":"not found"}';
            return;
        }
        echo json_encode(['Order' => ['id' => $m[1], 'customer_id' => $pedidos[$m[1]]['Customer']['id'], 'OrderStatus' => ['status' => $pedidos[$m[1]]['status'], 'type' => 'open']] + $pedidos[$m[1]]]);
        return;
    }
    if ($caminho === '/web_api/orders/statuses') {
        echo json_encode(['paging' => ['total' => 5], 'OrderStatuses' => array_map(fn($s) => ['OrderStatus' => ['status' => $s, 'type' => 'open']], ['A ENVIAR', 'PENDENTE', 'Aguardando Vindi', 'CANCELADO', 'FINALIZADO'])]);
        return;
    }
    // /orders (listagem: só o cabeçalho, sem cliente e sem itens; filtro "modified=início,fim")
    [$ini, $fim] = array_pad(explode(',', (string) ($_GET['modified'] ?? '')), 2, '');
    $lista = [];
    foreach ($pedidos as $id => $o) {
        $mod = substr($o['modified'], 0, 10);
        if ($ini !== '' && ($mod < $ini || $mod > $fim)) {
            continue;
        }
        $cab = array_diff_key($o, array_flip(['Customer', 'ProductsSold', 'hour', 'OrderInvoice']));
        $lista[] = ['Order' => ['id' => (string) $id, 'customer_id' => $o['Customer']['id'], 'ProductsSold' => [['id' => '1']]] + $cab];
    }
    $limite = min(50, (int) ($_GET['limit'] ?? 30));
    $pagina = max(1, (int) ($_GET['page'] ?? 1));
    echo json_encode(['paging' => ['total' => count($lista), 'page' => $pagina, 'offset' => ($pagina - 1) * $limite, 'limit' => $limite, 'maxLimit' => 50],
        'Orders' => array_slice($lista, ($pagina - 1) * $limite, $limite)]);
    return;
}
http_response_code(404);
echo '{"message":"not found"}';
