<?php
namespace App\Integrations\Tray;

/**
 * API REAL da Tray (https://developers.tray.com.br).
 *
 * Autenticação (OAuth da Tray):
 *   - O aplicativo recebe da Tray "consumer key" e "consumer secret" (cadastro de parceiro/aplicativo).
 *   - A loja autoriza o aplicativo e recebe um "code" (painel da loja › Meus Aplicativos) e o "api_address"
 *     (endereço da loja + /web_api, ex.: https://www.sualoja.com.br/web_api).
 *   - POST {api_address}/auth (consumer_key, consumer_secret, code) → access_token + refresh_token + validades.
 *   - GET  {api_address}/auth?refresh_token=... → tokens novos quando o access_token vence.
 *   Os tokens ficam em storage/cache/tray_token_*.json (um por loja/chave; fora do git e fora da web).
 *
 * Consultas usadas (somente leitura):
 *   - GET /orders?access_token=&date=AAAA-MM-DD,AAAA-MM-DD 23:59:59&limit=50&page=N   (pedidos do período)
 *   - GET /products_solds?access_token=&sort=id_desc&limit=50&page=N                (itens vendidos)
 * Limites da Tray: 180 requisições/minuto e 10 mil/dia por loja → pausa entre chamadas e uso pelo cron.
 * LGPD: nada do comprador é guardado (nem nome, nem e-mail); só números.
 */
class TrayClient implements EcommerceInterface
{
    private const POR_PAGINA = 50;          // máximo da Tray
    private const PAUSA_MICROSSEGUNDOS = 350_000; // ~170 req/min, abaixo do limite de 180
    private string $arquivoToken;

    public function __construct(private string $api, private string $consumerKey, private string $consumerSecret, private string $code)
    {
        $this->api = rtrim(trim($this->api), '/');
        if ($this->api !== '' && !preg_match('#^https?://#i', $this->api)) {
            $this->api = 'https://' . $this->api;
        }
        // Um arquivo por loja + chave: trocar as chaves no config.php gera um token novo (não reaproveita o antigo)
        $this->arquivoToken = RAIZ . '/storage/cache/tray_token_' . substr(sha1($this->api . '|' . $this->consumerKey . '|' . $this->code), 0, 12) . '.json';
    }

    public function nome(): string
    {
        return 'tray';
    }

    // ------------------------------------------------------------------
    // Dados
    // ------------------------------------------------------------------

    public function resumoPorDia(string $desde, string $ate): array
    {
        $dias = [];
        $clientes = [];
        foreach ($this->pedidosDoPeriodo($desde, $ate) as $o) {
            $dia = substr((string) $o['date'], 0, 10);
            $dias[$dia] ??= ['data' => $dia, 'pedidos' => 0, 'faturamento' => 0.0, 'visitas' => null, 'novos_clientes' => 0];
            $dias[$dia]['pedidos']++;
            $dias[$dia]['faturamento'] += (float) $o['total'];
            // "Clientes que compraram" no dia (pessoas diferentes, contadas pelo código, sem guardar quem é)
            if (!isset($clientes[$dia][$o['customer_id'] ?? ''])) {
                $clientes[$dia][$o['customer_id'] ?? ''] = true;
                $dias[$dia]['novos_clientes']++;
            }
        }
        // Dias sem pedido também entram (com zero), para o gráfico não pular dias
        for ($d = $desde; $d <= min($ate, date('Y-m-d')); $d = date('Y-m-d', strtotime("$d +1 day"))) {
            $dias[$d] ??= ['data' => $d, 'pedidos' => 0, 'faturamento' => 0.0, 'visitas' => null, 'novos_clientes' => 0];
            $dias[$d]['faturamento'] = round($dias[$d]['faturamento'], 2);
        }
        ksort($dias);
        return array_values($dias);
    }

    public function produtosMaisVendidos(string $mes, int $limite = 10): array
    {
        $pedidos = $this->pedidosDoPeriodo("$mes-01", date('Y-m-t', strtotime("$mes-01")));
        if (!$pedidos) {
            return [];
        }
        $validos = array_flip(array_map(fn($o) => (string) $o['id'], $pedidos));
        $menorPedido = min(array_map(fn($o) => (int) $o['id'], $pedidos));

        // Itens vendidos do mais novo para o mais antigo, até passar do primeiro pedido do mês
        $soma = [];
        for ($pagina = 1; $pagina <= 400; $pagina++) {
            $r = $this->get('/products_solds', ['sort' => 'id_desc', 'limit' => self::POR_PAGINA, 'page' => $pagina]);
            $itens = $r['ProductsSolds'] ?? [];
            foreach ($itens as $linha) {
                $i = $linha['ProductsSold'] ?? [];
                if (!isset($validos[(string) ($i['order_id'] ?? '')])) {
                    continue; // pedido de outro mês ou cancelado
                }
                $ref = (string) $i['product_id'];
                $soma[$ref] ??= ['produto_ref' => $ref, 'nome' => (string) ($i['original_name'] ?? $i['name'] ?? $ref), 'quantidade' => 0, 'faturamento' => 0.0];
                $soma[$ref]['quantidade'] += (int) $i['quantity'];
                $soma[$ref]['faturamento'] += (float) $i['price'] * (int) $i['quantity'];
            }
            $ultimo = end($itens);
            if (!$itens || (int) ($ultimo['ProductsSold']['order_id'] ?? 0) < $menorPedido || count($itens) < self::POR_PAGINA) {
                break;
            }
        }
        usort($soma, fn($a, $b) => $b['faturamento'] <=> $a['faturamento']);
        return array_map(fn($p) => ['faturamento' => round($p['faturamento'], 2)] + $p, array_slice($soma, 0, $limite));
    }

    public function pedidosRecentes(int $limite = 10): array
    {
        $r = $this->get('/orders', ['sort' => 'id_desc', 'limit' => min($limite, self::POR_PAGINA), 'page' => 1]);
        return array_map(fn($linha) => [
            'numero'   => (string) $linha['Order']['id'],
            'data'     => (string) $linha['Order']['date'],
            'valor'    => (float) $linha['Order']['total'],
            'situacao' => mb_convert_case(mb_strtolower((string) $linha['Order']['status']), MB_CASE_TITLE),
            'cidade'   => '', 'uf' => '', // a listagem não traz endereço; não buscamos dados do comprador
        ], $r['Orders'] ?? []);
    }

    public function testarConexao(): array
    {
        if ($this->api === '' || $this->consumerKey === '' || $this->consumerSecret === '' || $this->code === '') {
            return [false, 'Faltam dados da Tray no config.php (api, consumer_key, consumer_secret, code).'];
        }
        try {
            $r = $this->get('/orders', ['limit' => 1, 'page' => 1]);
            return [true, 'Conectado à loja. Pedidos encontrados: ' . (int) ($r['paging']['total'] ?? 0) . '.'];
        } catch (\RuntimeException $erro) {
            return [false, $erro->getMessage()];
        }
    }

    // ------------------------------------------------------------------
    // Pedidos (paginados), sem os cancelados
    // ------------------------------------------------------------------

    private function pedidosDoPeriodo(string $desde, string $ate): array
    {
        $pedidos = [];
        for ($pagina = 1; $pagina <= 400; $pagina++) {
            $r = $this->get('/orders', ['date' => "$desde,$ate 23:59:59", 'limit' => self::POR_PAGINA, 'page' => $pagina, 'sort' => 'id_asc']);
            foreach ($r['Orders'] ?? [] as $linha) {
                $o = $linha['Order'] ?? [];
                if (str_contains(mb_strtoupper((string) ($o['status'] ?? '')), 'CANCEL')) {
                    continue;
                }
                $pedidos[] = $o;
            }
            $total = (int) ($r['paging']['total'] ?? 0);
            if ($pagina * self::POR_PAGINA >= $total) {
                break;
            }
        }
        return $pedidos;
    }

    // ------------------------------------------------------------------
    // Token e HTTP
    // ------------------------------------------------------------------

    /** access_token válido (gera ou renova quando precisa) */
    private function token(bool $forcarNovo = false): string
    {
        $t = is_file($this->arquivoToken) ? (json_decode((string) file_get_contents($this->arquivoToken), true) ?: []) : [];
        $agora = time() + 60;
        if (!$forcarNovo && !empty($t['access_token']) && strtotime((string) $t['date_expiration_access_token']) > $agora) {
            return $t['access_token'];
        }
        if (!empty($t['refresh_token']) && strtotime((string) $t['date_expiration_refresh_token']) > $agora) {
            $novo = $this->requisicao('GET', '/auth', ['refresh_token' => $t['refresh_token']]);
        } else {
            if ($this->consumerKey === '' || $this->consumerSecret === '' || $this->code === '') {
                throw new \RuntimeException('Faltam as chaves da Tray no config.php.');
            }
            $novo = $this->requisicao('POST', '/auth', [], ['consumer_key' => $this->consumerKey, 'consumer_secret' => $this->consumerSecret, 'code' => $this->code]);
        }
        if (empty($novo['access_token'])) {
            throw new \RuntimeException('A Tray não devolveu a chave de acesso. Confira consumer key, secret e code (o code pode ter expirado: gere outro no painel da loja).');
        }
        if (!is_dir(dirname($this->arquivoToken))) {
            mkdir(dirname($this->arquivoToken), 0700, true);
        }
        file_put_contents($this->arquivoToken, json_encode(array_intersect_key($novo, array_flip(
            ['access_token', 'refresh_token', 'date_expiration_access_token', 'date_expiration_refresh_token', 'store_id']))), LOCK_EX);
        @chmod($this->arquivoToken, 0600);
        return $novo['access_token'];
    }

    /** GET autenticado; se a Tray recusar o token (401), renova uma vez e tenta de novo */
    private function get(string $caminho, array $parametros): array
    {
        try {
            return $this->requisicao('GET', $caminho, $parametros + ['access_token' => $this->token()]);
        } catch (TrayNaoAutorizado) {
            return $this->requisicao('GET', $caminho, $parametros + ['access_token' => $this->token(true)]);
        }
    }

    private function requisicao(string $metodo, string $caminho, array $query, array $corpo = []): array
    {
        if ($this->api === '') {
            throw new \RuntimeException('Falta o endereço da API da loja (api_address) no config.php.');
        }
        usleep(self::PAUSA_MICROSSEGUNDOS);
        $curl = curl_init($this->api . $caminho . ($query ? '?' . http_build_query($query) : ''));
        curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 40, CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HTTPHEADER => ['Accept: application/json'], CURLOPT_USERAGENT => 'One/1.0']);
        if ($metodo === 'POST') {
            curl_setopt_array($curl, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query($corpo)]);
        }
        $resposta = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $erroCurl = curl_error($curl);
        curl_close($curl);

        if ($resposta === false) {
            throw new \RuntimeException('Sem conexão com a loja Tray: ' . $erroCurl);
        }
        $dados = json_decode((string) $resposta, true);
        if ($caminho === '/auth' && ($status === 400 || $status === 401)) {
            throw new \RuntimeException('A Tray recusou as chaves. Confira consumer key, consumer secret e code no config.php (o code pode ter expirado: gere outro em Meus Aplicativos, no painel da loja).');
        }
        if ($status === 401) {
            throw new TrayNaoAutorizado('Chave de acesso recusada.');
        }
        if ($status === 429) {
            throw new \RuntimeException('Limite de requisições da Tray atingido. Tente de novo em alguns minutos.');
        }
        if ($status >= 400 || !is_array($dados)) {
            // Nunca registrar o access_token: o log guarda só o caminho e o código
            error_log("Tray $metodo $caminho → HTTP $status");
            throw new \RuntimeException('A Tray respondeu com erro (' . $status . ')' . (is_array($dados) && !empty($dados['message']) ? ': ' . $dados['message'] : '') . '.');
        }
        return $dados;
    }
}

