<?php
namespace App\Integrations\Tray;

/**
 * API REAL da Tray (estrutura pronta — ligar quando a loja liberar o aplicativo).
 *
 * Como a Tray funciona (confirmar na documentação oficial antes de ligar):
 *   - O aplicativo tem "consumer key" e "consumer secret"; a loja autoriza e gera um "code".
 *   - Com eles pedimos um access token (dura algumas horas) e um refresh token (renova o acesso).
 *   - As consultas usam o endereço da API da loja (ex.: https://www.sualoja.com.br/web_api) + ?access_token=...
 *     Pedidos: /orders (paginado) · Produtos: /products · Produtos vendidos: dentro de cada pedido.
 *   - Há limite de requisições por minuto: usar paginação e o cron (cron/ecommerce.php), nunca a tela.
 *
 * Configuração (config.php › integracoes.tray): api, consumer_key, consumer_secret, code.
 * Os tokens ficam em storage/cache/tray_token.json (fora do git e da web).
 */
class TrayClient implements EcommerceInterface
{
    public function __construct(private string $api, private string $consumerKey, private string $consumerSecret, private string $code)
    {
    }

    public function nome(): string
    {
        return 'tray';
    }

    public function resumoPorDia(string $desde, string $ate): array
    {
        throw new \LogicException('TrayClient::resumoPorDia — implementar ao ligar a loja (somar /orders por data, só pedidos pagos).');
    }

    public function produtosMaisVendidos(string $mes, int $limite = 10): array
    {
        throw new \LogicException('TrayClient::produtosMaisVendidos — implementar ao ligar a loja (somar os produtos dos pedidos do mês).');
    }

    public function pedidosRecentes(int $limite = 10): array
    {
        throw new \LogicException('TrayClient::pedidosRecentes — implementar ao ligar a loja (/orders ordenado por data, sem dados do comprador).');
    }

    public function testarConexao(): array
    {
        if ($this->api === '' || $this->consumerKey === '' || $this->consumerSecret === '' || $this->code === '') {
            return [false, 'Faltam as chaves da Tray no config.php (api, consumer_key, consumer_secret, code).'];
        }
        return [false, 'Integração real com a Tray ainda não ativada nesta versão.'];
    }
}
