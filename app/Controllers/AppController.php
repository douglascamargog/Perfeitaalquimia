<?php
namespace App\Controllers;

use App\Core\Visao;
use App\Services\AppSync;

/**
 * App do vendedor externo (PWA): tela única, manifest, service worker e API JSON.
 * A tela funciona sem internet (service worker + dados no IndexedDB do celular).
 */
class AppController extends Controller
{
    public function index(): void
    {
        header('Cache-Control: no-cache');
        echo Visao::renderizar('app/index', ['titulo' => 'One Vendas']);
    }

    public function manifest(): void
    {
        header('Content-Type: application/manifest+json; charset=utf-8');
        echo json_encode([
            'name' => 'One Vendas — Perfeita Alquimia', 'short_name' => 'One Vendas', 'lang' => 'pt-BR',
            'start_url' => url('/app'), 'scope' => url('/app'), 'display' => 'standalone', 'orientation' => 'portrait',
            'background_color' => '#14251b', 'theme_color' => '#14251b',
            'icons' => [
                ['src' => asset('app/icone-192.png'), 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any maskable'],
                ['src' => asset('app/icone-512.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any maskable'],
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /** Service worker (servido daqui para valer em todo o /app) */
    public function sw(): void
    {
        header('Content-Type: application/javascript; charset=utf-8');
        header('Service-Worker-Allowed: ' . url('/app'));
        header('Cache-Control: no-cache');
        $versao = filemtime(RAIZ . '/public/assets/app/sw.js') . '-' . filemtime(RAIZ . '/public/assets/app/app.js') . '-' . filemtime(RAIZ . '/public/assets/app/app.css');
        echo "const VERSAO = '" . $versao . "';\nconst BASE = " . json_encode(url_base()) . ";\n";
        readfile(RAIZ . '/public/assets/app/sw.js');
    }

    public function dados(): void
    {
        $this->json(['ok' => true] + AppSync::pacote());
    }

    public function sincronizar(): void
    {
        $corpo = json_decode((string) file_get_contents('php://input'), true);
        $this->json(['ok' => true, 'resultados' => AppSync::sincronizar((array) ($corpo['itens'] ?? []))]);
    }

    public function situacao(): void
    {
        $this->json(['ok' => true, 'pedidos' => AppSync::situacao(explode(',', (string) ($_GET['uuids'] ?? '')))]);
    }
}
