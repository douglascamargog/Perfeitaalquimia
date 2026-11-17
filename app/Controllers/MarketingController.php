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
        $this->garantirDados();
        $f = EcommerceService::filtros($_GET);
        $this->tela('marketing/ecommerce', [
            'titulo' => 'E-commerce', 'painel' => EcommerceService::painel($f),
            'estilos' => ['carteira.css', 'inicio.css', 'marketing.css', 'ecommerce.css'],
            'scripts' => ['https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js', 'ecommerce.js'],
        ]);
    }

    /** Lista completa dos pedidos da loja (com filtros e busca) */
    public function ecommercePedidos(): void
    {
        $this->garantirDados();
        $f = EcommerceService::filtros($_GET);
        $extra = ['sem_nf' => !empty($_GET['sem_nf']), 'atrasado' => !empty($_GET['atrasado'])];
        $this->tela('marketing/ecommerce_pedidos', [
            'titulo' => 'Pedidos do e-commerce', 'f' => $f, 'extra' => $extra,
            'lista' => EcommerceService::pedidos($f, max(1, (int) ($_GET['pagina'] ?? 1)), 50, $extra),
            'estilos' => ['carteira.css', 'inicio.css', 'marketing.css', 'ecommerce.css'],
        ]);
    }

    /** Um pedido da loja */
    public function ecommercePedido(string $id): void
    {
        $p = ctype_digit($id) ? EcommerceService::pedido((int) $id) : null;
        if (!$p) {
            http_response_code(404);
            $this->tela('erros/erro', ['titulo' => 'Pedido não encontrado', 'codigo' => 404, 'mensagem' => 'Pedido não encontrado.']);
            return;
        }
        $this->tela('marketing/ecommerce_pedido', ['titulo' => 'Pedido ' . $p['tray_id'], 'p' => $p,
            'estilos' => ['carteira.css', 'inicio.css', 'marketing.css', 'ecommerce.css']]);
    }

    /** Planilha dos pedidos (CSV, sem dados pessoais) */
    public function ecommerceCsv(): void
    {
        $f = EcommerceService::filtros($_GET);
        $linhas = EcommerceService::exportar($f, ['sem_nf' => !empty($_GET['sem_nf']), 'atrasado' => !empty($_GET['atrasado'])]);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="pedidos-ecommerce-' . $f['de'] . '-a-' . $f['ate'] . '.csv"');
        $saida = fopen('php://output', 'w');
        fwrite($saida, "\xEF\xBB\xBF"); // Excel abre com acentos
        fputcsv($saida, ['Pedido', 'Data', 'Hora', 'Canal', 'Pedido no marketplace', 'Situação', 'Status na loja', 'Itens', 'Produtos', 'Subtotal', 'Desconto', 'Cupom',
            'Frete', 'Total', 'Pagamento', 'Envio', 'Prazo de entrega', 'Enviado em', 'Nota fiscal', 'Cidade', 'UF'], ';');
        $num = fn($v) => number_format((float) $v, 2, ',', '');
        foreach ($linhas as $l) {
            fputcsv($saida, [$l['tray_id'], date('d/m/Y', strtotime($l['data_hora'])), $l['detalhado'] ? date('H:i', strtotime($l['data_hora'])) : '',
                \App\Integrations\Tray\TrayTexto::nomeCanal($l['canal'], $l['canal_nome']), $l['marketplace_pedido'],
                \App\Integrations\Tray\TrayTexto::SITUACOES[$l['status_tipo']][0] ?? $l['status_tipo'], $l['status'], $l['itens_qtd'], $l['resumo_itens'],
                $num($l['subtotal']), $num($l['desconto']), $l['cupom'], $num($l['frete']), $num($l['total']), $l['pagamento'], $l['envio'],
                $l['prazo_entrega'] ? date('d/m/Y', strtotime($l['prazo_entrega'])) : '', $l['enviado_em'] ? date('d/m/Y', strtotime($l['enviado_em'])) : '',
                $l['nf_numero'] ?: ($l['tem_nf'] ? 'emitida' : ''), $l['cidade'], $l['uf']], ';');
        }
        fclose($saida);
        exit;
    }

    /** Demonstração: gera os pedidos simulados na primeira visita */
    private function garantirDados(): void
    {
        try {
            EcommerceService::garantirDemonstracao();
        } catch (\Throwable $erro) {
            error_log((string) $erro);
        }
    }

    /** Admin: confere se as chaves da Tray funcionam */
    public function testarEcommerce(): void
    {
        [$ok, $mensagem] = Integracoes::ecommerce()->testarConexao();
        Sessao::aviso($ok ? 'sucesso' : 'erro', 'Tray: ' . $mensagem);
        redirecionar('/marketing/ecommerce');
    }

    public function atualizarEcommerce(): void
    {
        @set_time_limit(600); // a primeira leitura da loja real pode demorar (180 requisições/min na Tray)
        try {
            $r = EcommerceService::sincronizar();
            if ($r['ocupado']) {
                Sessao::aviso('aviso', 'A loja já está sendo atualizada agora. Em alguns minutos os números aparecem.');
            } else {
                Sessao::aviso('sucesso', "Loja atualizada: {$r['novos']} pedido(s) novo(s), {$r['atualizados']} alterado(s)"
                    . ($r['faltam'] ? " · ainda faltam {$r['faltam']} pedido(s) do histórico (o One continua sozinho a cada rodada)" : '') . '.');
            }
        } catch (\Throwable $erro) {
            error_log((string) $erro);
            Sessao::aviso('erro', 'Não foi possível falar com a loja agora: ' . $erro->getMessage());
        }
        redirecionar('/marketing/ecommerce' . (!empty($_POST['voltar']) && preg_match('#^\?[\w=&%.-]*$#', (string) $_POST['voltar']) ? $_POST['voltar'] : ''));
    }
}
