<?php
namespace App\Integrations\Omie;

/**
 * Cliente da API REAL do Omie — ESQUELETO para a Fase 2. Não é usado no protótipo.
 *
 * Como a API do Omie funciona:
 *   POST https://app.omie.com.br/api/v1/<grupo>/<recurso>/
 *   corpo: {"call": "ListarProdutos", "app_key": "...", "app_secret": "...", "param": [{...}]}
 *   Limites: 240 requisições/min por IP + App Key + método, 4 simultâneas, 100 registros por página.
 *
 * O que já está pronto aqui: a chamada HTTP com retentativa e a paginação.
 * O que falta na Fase 2: converter cada resposta para o nosso formato (ver ErpInterface)
 * e confirmar os nomes exatos dos métodos/campos na documentação do Omie (developer.omie.com.br).
 */
class OmieClient implements ErpInterface
{
    private const POR_PAGINA = 100;
    private const TENTATIVAS = 3;

    public function __construct(
        private string $appKey,
        private string $appSecret,
        private string $url = 'https://app.omie.com.br/api/v1/',
    ) {
        if ($appKey === '' || $appSecret === '') {
            throw new \RuntimeException('App Key e App Secret do Omie não configurados.');
        }
    }

    /**
     * Faz uma chamada à API. Em caso de limite de requisições ou falha de rede, espera e tenta de novo.
     */
    private function chamar(string $recurso, string $metodo, array $parametros): array
    {
        $corpo = json_encode([
            'call' => $metodo, 'app_key' => $this->appKey, 'app_secret' => $this->appSecret, 'param' => [$parametros],
        ], JSON_UNESCAPED_UNICODE);

        for ($tentativa = 1; $tentativa <= self::TENTATIVAS; $tentativa++) {
            $ch = curl_init(rtrim($this->url, '/') . '/' . trim($recurso, '/') . '/');
            curl_setopt_array($ch, [
                CURLOPT_POST => true, CURLOPT_POSTFIELDS => $corpo, CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'], CURLOPT_TIMEOUT => 60,
            ]);
            $resposta = curl_exec($ch);
            $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($resposta !== false && $status < 400) {
                return json_decode($resposta, true) ?: [];
            }
            // 425/429/5xx ou rede: espera um pouco mais a cada tentativa (2s, 4s, 8s)
            sleep(2 ** $tentativa);
        }
        throw new \RuntimeException("Falha ao chamar o Omie ($recurso / $metodo).");
    }

    /** Percorre todas as páginas de um método "Listar..." e junta os registros */
    private function listarTudo(string $recurso, string $metodo, string $chaveLista, array $filtro = []): array
    {
        $todos = [];
        $pagina = 1;
        do {
            $resposta = $this->chamar($recurso, $metodo, $filtro + ['pagina' => $pagina, 'registros_por_pagina' => self::POR_PAGINA]);
            $todos = array_merge($todos, $resposta[$chaveLista] ?? []);
            $totalPaginas = (int) ($resposta['total_de_paginas'] ?? 1);
            $pagina++;
        } while ($pagina <= $totalPaginas);
        return $todos;
    }

    // ---- Métodos do contrato: a conversão para o nosso formato será feita na Fase 2 ----

    public function listarProdutos(?string $alteradoDesde = null): array
    {
        throw new \LogicException('OmieClient::listarProdutos — implementar na Fase 2 (geral/produtos, ListarProdutos).');
    }

    public function listarPrecos(?string $alteradoDesde = null): array
    {
        throw new \LogicException('OmieClient::listarPrecos — implementar na Fase 2 (tabelas de preço).');
    }

    public function listarEstoque(): array
    {
        throw new \LogicException('OmieClient::listarEstoque — implementar na Fase 2 (estoque/consulta).');
    }

    public function listarClientes(?string $alteradoDesde = null): array
    {
        throw new \LogicException('OmieClient::listarClientes — implementar na Fase 2 (geral/clientes, ListarClientes).');
    }

    public function listarTitulos(?string $alteradoDesde = null): array
    {
        throw new \LogicException('OmieClient::listarTitulos — implementar na Fase 2 (financas/contareceber).');
    }

    public function listarFaturamento(?string $alteradoDesde = null): array
    {
        throw new \LogicException('OmieClient::listarFaturamento — implementar na Fase 2 (produtos/nfconsultar).');
    }

    public function aprovarPedido(string $omieCodigo): array
    {
        throw new \LogicException('OmieClient::aprovarPedido — implementar na Fase 2 (produtos/pedido, TrocarEtapaPedido para a etapa de aprovado).');
    }

    public function enviarPedido(array $pedido): array
    {
        throw new \LogicException('OmieClient::enviarPedido — implementar na Fase 2 (produtos/pedido, IncluirPedido com codigo_pedido_integracao = uuid).');
    }

    public function consultarPedido(string $omieCodigo): array
    {
        throw new \LogicException('OmieClient::consultarPedido — implementar na Fase 2 (produtos/pedido, ConsultarPedido).');
    }

    public function testarConexao(): array
    {
        try {
            $this->chamar('geral/produtos', 'ListarProdutos', ['pagina' => 1, 'registros_por_pagina' => 1]);
            return ['ok' => true, 'mensagem' => 'Conexão com o Omie funcionando.'];
        } catch (\Throwable $erro) {
            return ['ok' => false, 'mensagem' => $erro->getMessage()];
        }
    }
}
