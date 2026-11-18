<?php
namespace App\Integrations\Mapas;

use RuntimeException;

/**
 * Geoapify (mapas sem pré-pagamento: plano grátis de 3.000 créditos por dia, uso comercial com a menção "Powered by Geoapify").
 *  - localizar(): endereço → ponto no mapa (Geocoding API)
 *  - cidade(): centro de uma cidade
 *  - rota(): trajeto pelas estradas entre pontos, com km e tempo (Routing API)
 *  - ordenar(): melhor ordem para visitar várias lojas (Route Planner API)
 * A chave fica em config/segredos.php (⚙️ Administração › Mapas). Só vai para a Geoapify o endereço comercial da loja.
 * Nos testes, o endereço da API aponta para um simulado (tests/recursos/geoapify_simulado.php).
 *
 * Atenção às ordens: na Routing API os pontos vão como "lat,lon"; no Route Planner, como [lon, lat].
 */
class GeoapifyClient
{
    public function __construct(private string $chave, private string $url = 'https://api.geoapify.com/v1')
    {
        $this->url = rtrim($this->url, '/');
    }

    public static function daConfig(): self
    {
        return new self((string) config('integracoes.geoapify.chave', ''), (string) (config('integracoes.geoapify.url') ?: 'https://api.geoapify.com/v1'));
    }

    public function temChave(): bool
    {
        return $this->chave !== '';
    }

    /**
     * Endereço → ['lat', 'lng', 'tipo' (building|amenity|street|...), 'cidade', 'confianca'] (vários resultados, o melhor primeiro).
     */
    public function localizar(string $texto, string $uf = ''): array
    {
        $dados = $this->get('/geocode/search', ['text' => $texto, 'filter' => 'countrycode:br', 'lang' => 'pt', 'limit' => 5, 'format' => 'json']);
        return array_map(fn($r) => [
            'lat' => (float) ($r['lat'] ?? 0), 'lng' => (float) ($r['lon'] ?? 0), 'tipo' => (string) ($r['result_type'] ?? 'unknown'),
            'cidades' => array_values(array_filter([(string) ($r['city'] ?? ''), (string) ($r['municipality'] ?? ''), (string) ($r['county'] ?? '')])),
            'uf' => strtoupper((string) ($r['state_code'] ?? '')),
            'confianca' => (float) ($r['rank']['confidence'] ?? 0),
        ], array_values(array_filter((array) ($dados['results'] ?? []), fn($r) => isset($r['lat'], $r['lon']))));
    }

    /** Centro de uma cidade: ['lat', 'lng'] ou null */
    public function cidade(string $nome, string $uf): ?array
    {
        $dados = $this->get('/geocode/search', ['city' => $nome, 'state' => $uf, 'country' => 'Brazil', 'type' => 'city', 'lang' => 'pt', 'limit' => 1, 'format' => 'json']);
        $r = $dados['results'][0] ?? null;
        return $r && isset($r['lat'], $r['lon']) ? ['lat' => (float) $r['lat'], 'lng' => (float) $r['lon']] : null;
    }

    /**
     * Trajeto de carro passando pelos pontos, na ordem: ['km', 'minutos', 'trajeto' => [[lat, lng], ...]]
     * $pontos: [['lat' => , 'lng' => ], ...] (2 ou mais)
     */
    public function rota(array $pontos): array
    {
        $waypoints = implode('|', array_map(fn($p) => self::n($p['lat']) . ',' . self::n($p['lng']), $pontos));
        $dados = $this->get('/routing', ['waypoints' => $waypoints, 'mode' => 'drive']);
        $f = $dados['features'][0] ?? null;
        if (!$f) {
            throw new RuntimeException('A Geoapify não achou uma rota de carro entre essas cidades.');
        }
        $trajeto = [];
        $geo = $f['geometry'] ?? [];
        $linhas = ($geo['type'] ?? '') === 'LineString' ? [$geo['coordinates'] ?? []] : (array) ($geo['coordinates'] ?? []);
        foreach ($linhas as $linha) {
            foreach ((array) $linha as $c) {
                if (isset($c[0], $c[1])) {
                    $trajeto[] = [(float) $c[1], (float) $c[0]]; // GeoJSON vem [lon, lat]
                }
            }
        }
        return ['km' => round((float) ($f['properties']['distance'] ?? 0) / 1000, 1), 'minutos' => (int) round((float) ($f['properties']['time'] ?? 0) / 60), 'trajeto' => $trajeto];
    }

    /**
     * Melhor ordem para visitar as lojas saindo de $inicio: ['ordem' => [índices de $paradas na ordem], 'km', 'minutos'].
     * Lojas que o planejador não conseguiu encaixar vão para o fim, na ordem em que vieram.
     */
    public function ordenar(array $inicio, array $paradas, int $minutosPorVisita = 30): array
    {
        $corpo = ['mode' => 'drive',
            'agents' => [['start_location' => [(float) $inicio['lng'], (float) $inicio['lat']]]],
            'jobs' => array_map(fn($p, $i) => ['id' => "loja-$i", 'location' => [(float) $p['lng'], (float) $p['lat']], 'duration' => $minutosPorVisita * 60], $paradas, array_keys($paradas))];
        $dados = $this->post('/routeplanner', $corpo);
        $f = $dados['features'][0] ?? null;
        $ordem = [];
        foreach ((array) ($f['properties']['actions'] ?? []) as $a) {
            if (($a['type'] ?? '') === 'job' && isset($a['job_index']) && isset($paradas[(int) $a['job_index']]) && !in_array((int) $a['job_index'], $ordem, true)) {
                $ordem[] = (int) $a['job_index'];
            }
        }
        foreach (array_keys($paradas) as $i) { // as que ficaram de fora
            if (!in_array($i, $ordem, true)) {
                $ordem[] = $i;
            }
        }
        return ['ordem' => $ordem, 'km' => round((float) ($f['properties']['distance'] ?? 0) / 1000, 1),
            'minutos' => (int) round((float) ($f['properties']['time'] ?? 0) / 60)];
    }

    /** Teste da chave: um endereço conhecido de Porto Alegre */
    public function testar(): array
    {
        if (!$this->temChave()) {
            return [false, 'Falta a chave da Geoapify.'];
        }
        try {
            $r = $this->localizar('Avenida Borges de Medeiros, 1501, Praia de Belas, Porto Alegre - RS, 90119-900, Brasil');
            return $r ? [true, 'Chave funcionando: endereço de teste localizado (' . round($r[0]['lat'], 5) . ', ' . round($r[0]['lng'], 5) . ').']
                : [false, 'A chave funcionou, mas o endereço de teste não foi achado.'];
        } catch (RuntimeException $e) {
            return [false, $e->getMessage()];
        }
    }

    // ------------------------------------------------------------------

    private static function n(float|string $v): string
    {
        return number_format((float) $v, 6, '.', '');
    }

    private function get(string $caminho, array $parametros): array
    {
        return $this->chamar($this->url . $caminho . '?' . http_build_query($parametros + ['apiKey' => $this->chave]), null);
    }

    private function post(string $caminho, array $corpo): array
    {
        return $this->chamar($this->url . $caminho . '?' . http_build_query(['apiKey' => $this->chave]), $corpo);
    }

    /** Chamada HTTP (erros viram mensagem em português; a chave nunca aparece na mensagem) */
    private function chamar(string $endereco, ?array $corpo): array
    {
        if (!$this->temChave()) {
            throw new RuntimeException('Falta a chave da Geoapify (⚙️ Administração › Mapas).');
        }
        $curl = curl_init($endereco);
        $opcoes = [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 25, CURLOPT_CONNECTTIMEOUT => 8, CURLOPT_HTTPHEADER => ['Accept: application/json']];
        if ($corpo !== null) {
            $opcoes[CURLOPT_POST] = true;
            $opcoes[CURLOPT_POSTFIELDS] = json_encode($corpo);
            $opcoes[CURLOPT_HTTPHEADER][] = 'Content-Type: application/json';
        }
        curl_setopt_array($curl, $opcoes);
        $resposta = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $erroCurl = curl_error($curl);
        curl_close($curl);
        if ($resposta === false) {
            throw new RuntimeException('Sem conexão com a Geoapify: ' . $erroCurl);
        }
        $dados = json_decode((string) $resposta, true);
        if ($status === 401 || $status === 403) {
            throw new RuntimeException('A Geoapify recusou a chave: confira se ela foi copiada inteira (geoapify.com › My Projects › API Keys).');
        }
        if ($status === 429) {
            throw new RuntimeException('Acabaram os créditos grátis de hoje na Geoapify. O One continua amanhã sozinho.');
        }
        if ($status >= 400 || !is_array($dados)) {
            throw new RuntimeException('A Geoapify respondeu com erro (' . ($status ?: 'sem resposta') . ')'
                . (is_array($dados) && !empty($dados['message']) ? ': ' . mb_substr((string) $dados['message'], 0, 160) : '') . '.');
        }
        return $dados;
    }
}
