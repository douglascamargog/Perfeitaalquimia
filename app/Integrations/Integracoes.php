<?php
namespace App\Integrations;

use App\Integrations\BuscaContatos\BuscaContatosClaude;
use App\Integrations\BuscaContatos\BuscaContatosFake;
use App\Integrations\BuscaContatos\BuscaContatosInterface;
use App\Integrations\LigueTalk\LigueTalkManual;
use App\Integrations\Tray\EcommerceInterface;
use App\Integrations\Tray\TrayClient;
use App\Integrations\Tray\TrayFake;
use App\Integrations\LigueTalk\TelefoniaInterface;
use App\Integrations\Omie\ErpInterface;
use App\Integrations\Omie\OmieClient;
use App\Integrations\Omie\OmieFake;

/**
 * "Fábrica" das integrações: devolve a versão certa conforme o config.php.
 *
 *     $erp = Integracoes::erp();        // OmieFake no protótipo, OmieClient na Fase 2
 *     $erp->enviarPedido($pedido);
 *
 * O resto do sistema NUNCA cria OmieFake/OmieClient diretamente: sempre pede aqui.
 */
class Integracoes
{
    private static ?ErpInterface $erp = null;
    private static ?TelefoniaInterface $telefonia = null;

    public static function erp(): ErpInterface
    {
        return self::$erp ??= match (config('integracoes.erp', 'fake')) {
            'omie'  => new OmieClient(
                (string) config('integracoes.omie.app_key', ''),
                (string) config('integracoes.omie.app_secret', ''),
                (string) config('integracoes.omie.url', 'https://app.omie.com.br/api/v1/'),
            ),
            default => new OmieFake(),
        };
    }

    public static function telefonia(): TelefoniaInterface
    {
        // Quando existir a API do Ligue Talk: 'api' => new LigueTalkClient(...)
        return self::$telefonia ??= new LigueTalkManual();
    }

    /**
     * Busca de contatos na internet: 'fake' (demonstração) ou 'claude' (IA real).
     * Escolhida em Configurações › busca_ia.provedor; a chave fica no config.php.
     */
    public static function buscaContatos(): BuscaContatosInterface
    {
        return match (\App\Core\Config::get('busca_ia.provedor', 'fake')) {
            'claude' => new BuscaContatosClaude(
                (string) config('integracoes.anthropic.chave', ''),
                (string) config('integracoes.anthropic.modelo', 'claude-opus-5-5'),
            ),
            default  => new BuscaContatosFake(),
        };
    }

    /** Loja virtual (Tray): 'fake' (demonstração) ou 'tray' (API real). Escolha em Configurações › E-commerce */
    public static function ecommerce(): EcommerceInterface
    {
        return match (\App\Core\Config::get('ecommerce.provedor', 'fake')) {
            'tray'  => new TrayClient(
                (string) config('integracoes.tray.api', ''),
                (string) config('integracoes.tray.consumer_key', ''),
                (string) config('integracoes.tray.consumer_secret', ''),
                (string) config('integracoes.tray.code', ''),
            ),
            default => new TrayFake(),
        };
    }

    /** Base pública de CNPJs da prospecção (hoje no próprio banco; a base da Receita entra na mesma tabela) */
    public static function baseCnpj(): \App\Integrations\BaseCnpj\BaseCnpjInterface
    {
        return new \App\Integrations\BaseCnpj\BaseCnpjLocal();
    }

    /** true quando o ERP é o simulado (usado para mostrar avisos na tela) */
    public static function erpSimulado(): bool
    {
        return self::erp() instanceof OmieFake;
    }
}
