<?php
// Geoapify SIMULADO, só para testes: php -S 127.0.0.1:8096 tests/recursos/geoapify_simulado.php
// Chave certa: "GeoTesteChave1234567890abcd". Endereço com "Inexistente" → não acha; com "Errada" → acha em outra cidade;
// com "SoCidade" → acha só a cidade (não serve para o ponto exato). Rota e melhor ordem: contas simples, mas no formato da Geoapify.
header('Content-Type: application/json');
$caminho = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (($_GET['apiKey'] ?? '') !== 'GeoTesteChave1234567890abcd') {
    http_response_code(401);
    echo json_encode(['statusCode' => 401, 'error' => 'Unauthorized', 'message' => 'Invalid apiKey']);
    return;
}
$km = function (float $la1, float $lo1, float $la2, float $lo2): float {
    $r = 6371;
    $a = sin(deg2rad($la2 - $la1) / 2) ** 2 + cos(deg2rad($la1)) * cos(deg2rad($la2)) * sin(deg2rad($lo2 - $lo1) / 2) ** 2;
    return 2 * $r * asin(sqrt($a));
};

if (str_ends_with($caminho, '/geocode/search')) {
    if (($_GET['type'] ?? '') === 'city') { // centro da cidade
        $h = crc32(($_GET['city'] ?? '') . ($_GET['state'] ?? ''));
        echo json_encode(['results' => [['lat' => -29.5 + ($h % 1000) / 1000, 'lon' => -52.0 + (($h >> 10) % 1000) / 1000, 'city' => $_GET['city'] ?? '', 'result_type' => 'city', 'rank' => ['confidence' => 1]]]]);
        return;
    }
    $texto = (string) ($_GET['text'] ?? '');
    if (str_contains($texto, 'Inexistente')) {
        echo json_encode(['results' => []]);
        return;
    }
    preg_match('/,\s*([^,]+?)\s*-\s*([A-Z]{2})\b/u', $texto, $m);
    $cidade = str_contains($texto, 'Errada') ? 'Outra Cidade' : trim($m[1] ?? 'Porto Alegre');
    $h = crc32($texto);
    echo json_encode(['results' => [[
        'lat' => -30.0 + ($h % 1000) / 10000, 'lon' => -51.2 + (($h >> 10) % 1000) / 10000, 'city' => $cidade, 'state_code' => $m[2] ?? 'RS',
        'result_type' => str_contains($texto, 'SoCidade') ? 'city' : ($h % 4 === 0 ? 'street' : 'building'), 'rank' => ['confidence' => 0.9],
    ]], 'query' => ['text' => $texto]]);
    return;
}

if (str_ends_with($caminho, '/routing')) {
    $pts = array_map(fn($w) => array_map('floatval', explode(',', $w)), explode('|', (string) ($_GET['waypoints'] ?? ''))); // lat,lon
    $linhas = [];
    $dist = 0;
    for ($i = 1; $i < count($pts); $i++) {
        [$a, $b] = [$pts[$i - 1], $pts[$i]];
        $linha = [];
        for ($k = 0; $k <= 10; $k++) { // 11 pontos por trecho, com uma curvinha (como uma estrada)
            $t = $k / 10;
            $linha[] = [$a[1] + ($b[1] - $a[1]) * $t + sin($t * M_PI) * 0.02, $a[0] + ($b[0] - $a[0]) * $t]; // [lon, lat]
        }
        $linhas[] = $linha;
        $dist += $km($a[0], $a[1], $b[0], $b[1]) * 1.25 * 1000;
    }
    echo json_encode(['type' => 'FeatureCollection', 'features' => [['type' => 'Feature',
        'properties' => ['mode' => 'drive', 'distance' => round($dist), 'distance_units' => 'meters', 'time' => round($dist / 1000 / 70 * 3600)],
        'geometry' => ['type' => 'MultiLineString', 'coordinates' => $linhas]]]]);
    return;
}

if (str_ends_with($caminho, '/routeplanner') && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $corpo = json_decode((string) file_get_contents('php://input'), true);
    $atual = $corpo['agents'][0]['start_location']; // [lon, lat]
    $faltam = array_keys($corpo['jobs']);
    $acoes = [['index' => 0, 'type' => 'start', 'start_time' => 0, 'duration' => 0]];
    $dist = 0;
    $tempo = 0;
    while ($faltam) { // vizinho mais próximo
        usort($faltam, fn($x, $y) => $km($atual[1], $atual[0], $corpo['jobs'][$x]['location'][1], $corpo['jobs'][$x]['location'][0])
            <=> $km($atual[1], $atual[0], $corpo['jobs'][$y]['location'][1], $corpo['jobs'][$y]['location'][0]));
        $j = array_shift($faltam);
        $d = $km($atual[1], $atual[0], $corpo['jobs'][$j]['location'][1], $corpo['jobs'][$j]['location'][0]) * 1.25;
        $dist += $d;
        $tempo += $d / 50 * 3600 + (int) ($corpo['jobs'][$j]['duration'] ?? 0);
        $acoes[] = ['index' => count($acoes), 'type' => 'job', 'start_time' => (int) $tempo, 'duration' => (int) ($corpo['jobs'][$j]['duration'] ?? 0), 'job_index' => $j, 'job_id' => $corpo['jobs'][$j]['id'] ?? null];
        $atual = $corpo['jobs'][$j]['location'];
    }
    $acoes[] = ['index' => count($acoes), 'type' => 'end', 'start_time' => (int) $tempo];
    echo json_encode(['type' => 'FeatureCollection', 'properties' => ['mode' => 'drive', 'params' => $corpo], 'features' => [['type' => 'Feature',
        'properties' => ['agent_index' => 0, 'distance' => round($dist * 1000), 'time' => round($tempo), 'actions' => $acoes], 'geometry' => null]]]);
    return;
}
http_response_code(404);
echo json_encode(['statusCode' => 404, 'message' => 'Not found']);
