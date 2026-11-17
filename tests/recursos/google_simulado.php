<?php
// Google Maps (Geocoding) SIMULADO, só para testes: php -S 127.0.0.1:8095 tests/recursos/google_simulado.php
// Chave certa: "AIzaChaveDeTesteCerta1234567890". Endereço com "Inexistente" → não acha. Com "Errada" → acha em outra cidade.
header('Content-Type: application/json');
if (($_GET['key'] ?? '') !== 'AIzaChaveDeTesteCerta1234567890') {
    echo json_encode(['status' => 'REQUEST_DENIED', 'error_message' => 'The provided API key is invalid.', 'results' => []]);
    return;
}
$endereco = (string) ($_GET['address'] ?? '');
if (str_contains($endereco, 'Inexistente')) {
    echo json_encode(['status' => 'ZERO_RESULTS', 'results' => []]);
    return;
}
// "Rua X, Bairro, Cidade - UF, CEP, Brasil" → a cidade é o pedaço antes de " - UF"
preg_match('/,\s*([^,]+?)\s*-\s*([A-Z]{2})\b/u', $endereco, $m);
$cidade = str_contains($endereco, 'Errada') ? 'Outra Cidade' : trim($m[1] ?? 'Porto Alegre');
$h = crc32($endereco);
echo json_encode(['status' => 'OK', 'results' => [[
    'address_components' => [['long_name' => $cidade, 'types' => ['administrative_area_level_2', 'political']], ['long_name' => $m[2] ?? 'RS', 'types' => ['administrative_area_level_1']]],
    'geometry' => ['location' => ['lat' => -30.0 + ($h % 1000) / 10000, 'lng' => -51.2 + (($h >> 10) % 1000) / 10000], 'location_type' => $h % 5 === 0 ? 'RANGE_INTERPOLATED' : 'ROOFTOP'],
]]]);
