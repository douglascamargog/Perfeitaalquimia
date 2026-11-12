<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Banco;
use App\Core\Sessao;
use App\Integrations\Integracoes;
use App\Models\Listas;
use App\Models\Post;
use App\Services\EcommerceService;
use App\Services\MarketingService;

/**
 * Marketing: mural do mês (calendário de posts), prévia do feed, planejamento de campanhas
 * e painel do e-commerce (Tray).
 */
class MarketingController extends Controller
{
    /** Mês da URL (?mes=AAAA-MM) ou o atual */
    private function mes(): string
    {
        $mes = (string) ($_GET['mes'] ?? '');
        return preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $mes) ? $mes : date('Y-m');
    }

    private function filtros(): array
    {
        return [
            'formato'  => array_key_exists($_GET['formato'] ?? '', Post::FORMATOS) ? $_GET['formato'] : '',
            'etapa'    => ctype_digit((string) ($_GET['etapa'] ?? '')) ? $_GET['etapa'] : '',
            'campanha' => ctype_digit((string) ($_GET['campanha'] ?? '')) ? $_GET['campanha'] : '',
        ];
    }

    /** Mural do mês: calendário seg → dom com os posts */
    public function mural(): void
    {
        $mes = $this->mes();
        $semanas = MarketingService::semanas($mes);
        $desde = $semanas[0][0];
        $ate = end($semanas)[6];
        $filtros = $this->filtros();
        $posts = Post::doPeriodo($desde, $ate, $filtros);
        $porDia = [];
        foreach ($posts as $p) {
            $porDia[$p['data_publicacao']][] = $p;
        }
        $doMes = array_filter($posts, fn($p) => str_starts_with($p['data_publicacao'], $mes));
        $this->tela('marketing/mural', [
            'titulo' => 'Mural de posts', 'mes' => $mes, 'semanas' => $semanas, 'porDia' => $porDia, 'filtros' => $filtros,
            'resumo' => [
                'total'      => count($doMes),
                'publicados' => count(array_filter($doMes, fn($p) => $p['etapa_tipo'] === 'publicado')),
                'aprovacao'  => count(array_filter($doMes, fn($p) => $p['etapa_tipo'] === 'aprovacao')),
                'ajuste'     => count(array_filter($doMes, fn($p) => $p['etapa_tipo'] === 'ajuste')),
                'formatos'   => array_count_values(array_column($doMes, 'formato')),
            ],
            'campanhas' => Post::campanhas($desde, $ate), 'datas' => MarketingService::datasComemorativas(), 'meta' => MarketingService::metaSemanal(),
            'etapas' => Listas::etapasPosts(), 'listaCampanhas' => Post::listaCampanhas(),
            'estilos' => ['carteira.css', 'marketing.css'], 'scripts' => ['carteira.js', 'mural.js'],
        ]);
    }

    /** Prévia do feed do Instagram (grade 3 colunas, mais novo primeiro) */
    public function feed(): void
    {
        $mes = $this->mes();
        $posts = array_reverse(array_values(array_filter(Post::doPeriodo("$mes-01", date('Y-m-t', strtotime("$mes-01"))), fn($p) => $p['formato'] !== 'stories')));
        $this->tela('marketing/feed', ['titulo' => 'Prévia do feed', 'mes' => $mes, 'posts' => $posts,
            'estilos' => ['carteira.css', 'marketing.css'], 'scripts' => ['carteira.js', 'mural.js']]);
    }

    /** Planejamento: campanhas dos próximos meses em linha do tempo */
    public function planejamento(): void
    {
        $mes = $this->mes();
        $desde = "$mes-01";
        $ate = date('Y-m-t', strtotime("$desde +2 months"));
        $campanhas = Post::campanhas($desde, $ate);
        foreach ($campanhas as &$c) {
            $c['vendas'] = $c['produto_slug'] ? EcommerceService::vendasDoProduto($c['produto_slug'], $c['inicio'], $c['fim']) : null;
        }
        unset($c);
        $editar = ctype_digit((string) ($_GET['editar'] ?? '')) ? Banco::um('SELECT * FROM mkt_campanhas WHERE id = ?', [(int) $_GET['editar']]) : null;
        $this->tela('marketing/planejamento', [
            'titulo' => 'Planejamento', 'mes' => $mes, 'desde' => $desde, 'ate' => $ate, 'campanhas' => $campanhas,
            'datas' => MarketingService::datasComemorativas(), 'editar' => $editar,
            'produtos' => Banco::todos('SELECT id, nome FROM produtos WHERE ativo = 1 ORDER BY nome'),
            'estilos' => ['carteira.css', 'inicio.css', 'marketing.css'],
        ]);
    }

    public function salvarCampanha(string $id = ''): void
    {
        [$novoId, $erros] = MarketingService::salvarCampanha($id !== '' ? (int) $id : null, $_POST, Auth::id());
        if ($erros) {
            $this->voltarComErros('/marketing/planejamento' . ($id !== '' ? "?editar=$id" : ''), $erros);
        }
        Sessao::aviso('sucesso', 'Campanha salva.');
        redirecionar('/marketing/planejamento?mes=' . substr((string) $_POST['inicio'], 0, 7));
    }

    // ---------------- E-commerce (Tray) ----------------

    public function ecommerce(): void
    {
        $mes = $this->mes();
        if (!Banco::valor('SELECT 1 FROM ecommerce_resumo_diario LIMIT 1')) {
            EcommerceService::sincronizar(); // primeira vez: busca o histórico
        }
        try {
            $pedidos = Integracoes::ecommerce()->pedidosRecentes(8);
        } catch (\Throwable $erro) {
            error_log((string) $erro);
            $pedidos = [];
        }
        $this->tela('marketing/ecommerce', [
            'titulo' => 'E-commerce', 'painel' => EcommerceService::painel($mes), 'pedidos' => $pedidos,
            'estilos' => ['carteira.css', 'inicio.css', 'marketing.css'],
            'scripts' => ['https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js', 'ecommerce.js'],
        ]);
    }

    public function atualizarEcommerce(): void
    {
        try {
            $r = EcommerceService::sincronizar();
            Sessao::aviso('sucesso', "Números atualizados ({$r['dias']} dia(s)).");
        } catch (\Throwable $erro) {
            error_log((string) $erro);
            Sessao::aviso('erro', 'Não foi possível falar com a loja agora. Tente de novo em alguns minutos.');
        }
        redirecionar('/marketing/ecommerce');
    }
}
