<?php
namespace App\Controllers;

use App\Core\Config;
use App\Core\Visao;
use App\Services\LeadService;

/**
 * Captura de leads:
 *  - POST /leads/receber  → endereço que o site da empresa e as páginas de anúncio chamam (sem login, com CORS);
 *  - /seja-revendedor     → nossa página pública de cadastro (os anúncios podem apontar direto para ela);
 *  - /leads               → tela do Marketing/Gestor com os leads recebidos e o resultado por campanha;
 *  - /leads/instalar      → código pronto para colocar o formulário no site.
 */
class LeadController extends Controller
{
    private const ESTILOS = ['carteira.css', 'inicio.css', 'painel.css', 'prospeccao.css'];

    /** Endpoint público: aceita formulário comum ou JSON */
    public function receber(): void
    {
        $this->cors();
        $dados = $_POST;
        if (!$dados && str_contains((string) ($_SERVER['CONTENT_TYPE'] ?? ''), 'json')) {
            $dados = json_decode((string) file_get_contents('php://input'), true) ?: [];
        }
        $r = LeadService::receber(is_array($dados) ? $dados : [], (string) ($_SERVER['REMOTE_ADDR'] ?? ''));
        // Formulário HTML comum com "redirecionar": volta para a página de obrigado do site
        $volta = (string) ($dados['redirecionar'] ?? '');
        if ($r['ok'] && $volta !== '' && preg_match('#^https?://#i', $volta) && LeadService::origemPermitida((string) parse_url($volta, PHP_URL_SCHEME) . '://' . parse_url($volta, PHP_URL_HOST))) {
            header('Location: ' . $volta, true, 303);
            return;
        }
        $this->json(['ok' => $r['ok'], 'mensagem' => $r['mensagem']], $r['status']);
    }

    /** Pré-checagem do navegador (CORS) antes do envio em JSON */
    public function preflight(): void
    {
        $this->cors();
        http_response_code(204);
    }

    private function cors(): void
    {
        $origem = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
        if ($origem !== '' && LeadService::origemPermitida($origem)) {
            header('Access-Control-Allow-Origin: ' . $origem);
            header('Vary: Origin');
            header('Access-Control-Allow-Methods: POST, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type');
        }
    }

    /** Página pública "Seja revendedor" (guarda as UTMs da URL em campos escondidos) */
    public function pagina(): void
    {
        $utm = [];
        foreach (['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'gclid', 'fbclid'] as $c) {
            $utm[$c] = mb_substr((string) ($_GET[$c] ?? ''), 0, 120);
        }
        Visao::mostrar('leads/publico', ['titulo' => 'Seja um revendedor', 'utm' => array_filter($utm), 'enviado' => null, 'erro' => null], null);
    }

    /** Envio da nossa página (com CSRF, pois é do mesmo site) */
    public function enviarPagina(): void
    {
        $r = LeadService::receber($_POST + ['pagina' => '/seja-revendedor'], (string) ($_SERVER['REMOTE_ADDR'] ?? ''));
        http_response_code($r['status']);
        Visao::mostrar('leads/publico', ['titulo' => 'Seja um revendedor', 'utm' => [], 'enviado' => $r['ok'] ? $r['mensagem'] : null, 'erro' => $r['ok'] ? null : $r['mensagem']], null);
    }

    /** Tela "Leads do site" */
    public function index(): void
    {
        $dias = in_array((int) ($_GET['dias'] ?? 30), [7, 30, 90, 365], true) ? (int) ($_GET['dias'] ?? 30) : 30;
        $de = date('Y-m-d', strtotime('-' . ($dias - 1) . ' days'));
        $ate = date('Y-m-d');
        $resultado = in_array($_GET['resultado'] ?? '', ['criado', 'existente', 'bloqueado', 'invalido'], true) ? $_GET['resultado'] : '';
        $this->tela('leads/index', ['titulo' => 'Leads do site', 'dias' => $dias, 'resultado' => $resultado,
            'r' => LeadService::resumo($de, $ate), 'leads' => LeadService::recebidos($de, $ate, $resultado),
            'estilos' => self::ESTILOS, 'scripts' => ['carteira.js', 'busca_contatos.js']]);
    }

    public function instalar(): void
    {
        $base = (isset($_SERVER['HTTP_HOST']) ? ((($_SERVER['HTTPS'] ?? '') === 'on' ? 'https://' : 'http://') . $_SERVER['HTTP_HOST']) : '');
        $this->tela('leads/instalar', ['titulo' => 'Instalar o formulário no site', 'endpoint' => $base . url('/leads/receber'),
            'script' => $base . asset('js/captura-leads.js'), 'pagina' => $base . url('/seja-revendedor'),
            'dominios' => (array) Config::get('leads.dominios_permitidos', []), 'estilos' => self::ESTILOS]);
    }
}
