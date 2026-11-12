<?php
namespace App\Integrations;

use App\Integrations\LigueTalk\LigueTalkManual;
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

    /** true quando o ERP é o simulado (usado para mostrar avisos na tela) */
    public static function erpSimulado(): bool
    {
        return self::erp() instanceof OmieFake;
    }
}
