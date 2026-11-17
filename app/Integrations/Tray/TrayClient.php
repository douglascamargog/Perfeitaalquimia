<?php
namespace App\Integrations\Tray;

/**
 * API REAL da Tray (https://developers.tray.com.br).
 *
 * Autenticação (OAuth da Tray):
 *   - O aplicativo recebe da Tray "consumer key" e "consumer secret" (cadastro de parceiro/aplicativo).
 *   - A loja autoriza o aplicativo e recebe um "code" (painel da loja › Meus Aplicativos) e o "api_address"
 *     (endereço da loja + /web_api, ex.: https://www.sualoja.com.br/web_api).
 *   - POST {api_address}/auth (JSON: consumer_key, consumer_secret, code) → access_token (HTTP 201) + refresh_token + validades.
 *   - GET  {api_address}/auth?refresh_token=... → tokens novos quando o access_token vence.
 *   Os tokens ficam em storage/cache/tray_token_*.json (um por loja/chave; fora do git e fora da web).
 *
 * Consultas usadas (somente leitura):
 *   - GET /orders?modified=AAAA-MM-DD,AAAA-MM-DD&limit=50&page=N   (pedidos alterados no período, só o cabeçalho)
 *   - GET /orders/{id}/complete                                     (pedido completo: itens, cidade/UF, nota, marketplace)
 *   - GET /orders/statuses                                          (status da loja, para classificar os personalizados)
 * Limites da Tray: 180 requisições/minuto e 10 mil/dia por loja → pausa entre chamadas e uso pelo cron.
 * LGPD: nada do comprador é guardado (nem nome, nem e-mail); só cidade/UF e um código anônimo (TrayTexto).
 */
class TrayClient implements EcommerceInterface
{
    private const POR_PAGINA = 50;          // máximo da Tray
    private const PAUSA_MICROSSEGUNDOS = 350_000; // ~170 req/min, abaixo do limite de 180
    private string $arquivoToken;
    /** O que aconteceu em cada chamada (para o "Testar conexão" mostrar onde a Tray recusou; nunca guarda chaves) */
    private array $passos = [];

    public function __construct(private string $api, private string $consumerKey, private string $consumerSecret, private string $code)
    {
        $this->api = rtrim(trim($this->api), '/');
        if ($this->api !== '' && !preg_match('#^https?://#i', $this->api)) {
            $this->api = 'https://' . $this->api;
        }
        // Um arquivo por loja + chave: trocar as chaves (Administração › Tray) gera um token novo (não reaproveita o antigo)
        $this->arquivoToken = RAIZ . '/storage/cache/tray_token_' . substr(sha1($this->api . '|' . $this->consumerKey . '|' . $this->code), 0, 12) . '.json';
    }

    public function nome(): string
    {
        return 'tray';
    }

    // ------------------------------------------------------------------
    // Pedidos (V14.0): o One guarda uma cópia local (EcommerceService::sincronizar)
    // ------------------------------------------------------------------

    public function pedidosAlterados(string $desde, string $ate, int $pagina = 1): array
    {
        $r = $this->get('/orders', ['modified' => "$desde,$ate", 'limit' => self::POR_PAGINA, 'page' => $pagina, 'sort' => 'id_asc']);
        $pedidos = [];
        foreach ($r['Orders'] ?? [] as $linha) {
            if (!empty($linha['Order']['id'])) {
                $pedidos[] = TrayTexto::pedido($linha['Order'], false, $this->statusDaLoja());
            }
        }
        return ['pedidos' => $pedidos, 'total' => (int) ($r['paging']['total'] ?? count($pedidos)), 'por_pagina' => self::POR_PAGINA];
    }

    public function pedidoCompleto(string $id): ?array
    {
        if (!preg_match('/^\d+$/', $id)) {
            return null;
        }
        $o = $this->get("/orders/$id/complete", [])['Order'] ?? null;
        return $o ? TrayTexto::pedido($o, true, $this->statusDaLoja()) : null;
    }

    private ?array $status = null;

    public function statusDaLoja(): array
    {
        if ($this->status !== null) {
            return $this->status;
        }
        $this->status = [];
        try {
            for ($pagina = 1; $pagina <= 5; $pagina++) {
                $r = $this->get('/orders/statuses', ['limit' => self::POR_PAGINA, 'page' => $pagina]);
                foreach ($r['OrderStatuses'] ?? [] as $linha) {
                    $st = $linha['OrderStatus'] ?? [];
                    if (!empty($st['status'])) {
                        $this->status[(string) $st['status']] = (string) ($st['type'] ?? '');
                    }
                }
                if ($pagina * self::POR_PAGINA >= (int) ($r['paging']['total'] ?? 0)) {
                    break;
                }
            }
        } catch (\RuntimeException) {
            // Sem a lista de status, a classificação usa só o nome do status (TrayTexto::statusTipo)
        }
        return $this->status;
    }

    /** Loja real: o Depósito usa a cópia local (EcommercePedidoService); este método não é usado */
    public function pedidosParaSeparar(string $desde): array
    {
        return [];
    }

    public function testarConexao(): array
    {
        if ($this->api === '' || $this->consumerKey === '' || $this->consumerSecret === '' || $this->code === '') {
            return [false, 'Faltam dados da Tray: confira em ⚙️ Administração › Tray o endereço da API, o consumer key, o consumer secret e o code de instalação.'];
        }
        try {
            $r = $this->get('/orders', ['limit' => 1, 'page' => 1]);
            return [true, 'Conectado à loja. Pedidos encontrados: ' . (int) ($r['paging']['total'] ?? 0) . '.'];
        } catch (\RuntimeException $erro) {
            return [false, $erro->getMessage() . ($this->passos ? ' Passos: ' . implode(' → ', $this->passos) . '.' : '')];
        }
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
        $novo = null;
        if (!empty($t['refresh_token']) && strtotime((string) $t['date_expiration_refresh_token']) > $agora) {
            try {
                $novo = $this->requisicao('GET', '/auth', ['refresh_token' => $t['refresh_token']]);
            } catch (\RuntimeException) {
                // Renovação recusada: esquece a chave guardada e tenta gerar outra com o code
                @unlink($this->arquivoToken);
            }
        }
        if ($novo === null) {
            if ($this->consumerKey === '' || $this->consumerSecret === '' || $this->code === '') {
                throw new \RuntimeException('Faltam as chaves da Tray (⚙️ Administração › Tray).');
            }
            $chaves = ['consumer_key' => $this->consumerKey, 'consumer_secret' => $this->consumerSecret, 'code' => $this->code];
            try {
                $novo = $this->requisicao('POST', '/auth', [], $chaves);
            } catch (\RuntimeException $erroJson) {
                // Algumas lojas só aceitam o formato de formulário: tenta de novo assim antes de desistir
                try {
                    $novo = $this->requisicao('POST', '/auth', [], $chaves, false);
                } catch (\RuntimeException) {
                    throw $erroJson;
                }
            }
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

    private function requisicao(string $metodo, string $caminho, array $query, array $corpo = [], bool $json = true): array
    {
        if ($this->api === '') {
            throw new \RuntimeException('Falta o endereço da API da loja (⚙️ Administração › Tray).');
        }
        usleep(self::PAUSA_MICROSSEGUNDOS);
        $curl = curl_init($this->api . $caminho . ($query ? '?' . http_build_query($query) : ''));
        curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 40, CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HTTPHEADER => ['Accept: application/json'], CURLOPT_USERAGENT => 'One/1.0']);
        if ($metodo === 'POST') {
            // A Tray recebe o corpo em JSON (doc oficial: POST {api_address}/auth)
            curl_setopt_array($curl, $json
                ? [CURLOPT_POST => true, CURLOPT_POSTFIELDS => json_encode($corpo), CURLOPT_HTTPHEADER => ['Accept: application/json', 'Content-Type: application/json']]
                : [CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query($corpo)]);
        }
        $resposta = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $erroCurl = curl_error($curl);
        curl_close($curl);

        if ($resposta === false) {
            $this->passos[] = $caminho . ' ✗ (sem conexão)';
            throw new \RuntimeException('Sem conexão com a loja Tray: ' . $erroCurl);
        }
        $dados = json_decode((string) $resposta, true);
        $nomePasso = match (true) {
            $caminho === '/auth' && $metodo === 'POST' => 'gerar a chave com o code' . ($json ? '' : ' (formulário)'),
            $caminho === '/auth' => 'renovar a chave',
            default => 'ler ' . ltrim($caminho, '/'),
        };
        $this->passos[] = $nomePasso . ($status >= 200 && $status < 300 ? ' ✓' : ' ✗ (HTTP ' . $status . (is_array($dados) && isset($dados['error_code']) ? ', código ' . $dados['error_code'] : '') . ')');
        // Motivo dado pela Tray (mensagem + causas), para a tela explicar o erro; nunca inclui as chaves
        $motivo = is_array($dados) ? trim(implode(' · ', array_filter(array_merge([(string) ($dados['message'] ?? '')],
            array_map('strval', array_filter((array) ($dados['causes'] ?? []), 'is_scalar')), isset($dados['error_code']) ? ['código ' . $dados['error_code']] : [])))) : '';
        $motivo = $motivo !== '' ? ' (Tray: ' . mb_substr(strip_tags($motivo), 0, 200) . ')' : '';
        if ($caminho === '/auth' && ($status === 400 || $status === 401)) {
            throw new \RuntimeException('A Tray recusou as chaves. Confira o consumer key, o consumer secret e o code em ⚙️ Administração › Tray (o code pode ter expirado: instale o aplicativo de novo na loja).' . $motivo);
        }
        if ($status === 401) {
            throw new TrayNaoAutorizado('A loja Tray recusou a chave de acesso ao ler os dados' . $motivo . '.');
        }
        if ($status === 429) {
            throw new \RuntimeException('Limite de requisições da Tray atingido. Tente de novo em alguns minutos.');
        }
        if ($status >= 400 || !is_array($dados)) {
            // Nunca registrar o access_token: o log guarda só o caminho e o código
            error_log("Tray $metodo $caminho → HTTP $status");
            throw new \RuntimeException('A Tray respondeu com erro (HTTP ' . $status . ')' . $motivo . '.');
        }
        return $dados;
    }
}

