<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Banco;
use App\Core\Sessao;
use App\Services\AcompanhamentoService as A;
use App\Services\CarteirasService;
use App\Services\Setores;
use App\Services\TelefoniaService;
use RuntimeException;

/**
 * Gestão › Acompanhamento: ligações (Ligue Talk), gravações, contatos, reativações e pedidos de cada vendedora.
 * Também: enviar clientes para o discador do Ligue Talk e ouvir gravações (sempre pelo sistema).
 */
class AcompanhamentoController extends Controller
{
    private const ESTILOS = ['carteira.css', 'painel.css', 'acompanhamento.css'];

    public function index(): void
    {
        [$de, $ate, $periodo] = A::periodo((string) ($_GET['periodo'] ?? 'mes'), (string) ($_GET['de'] ?? ''), (string) ($_GET['ate'] ?? ''));
        // Duas visões: Vendas (vendedoras) e Cobrança (quem cuida dos títulos em atraso)
        $visao = (($_GET['visao'] ?? '') === 'cobranca' || !A::podeVerVendas()) && A::podeVerCobranca() ? 'cobranca' : 'vendas';
        [$deA, $ateA] = A::anterior($de, $ate);
        if ($visao === 'cobranca') {
            $pessoas = A::pessoasCobranca();
            $this->tela('acompanhamento/cobranca', [
                'titulo' => 'Acompanhamento · Cobrança', 'linhas' => A::resumoCobranca($pessoas, $de, $ate),
                'antes' => array_column(A::resumoCobranca($pessoas, $deA, $ateA), null, 'id'),
                'de' => $de, 'ate' => $ate, 'periodo' => $periodo, 'visoes' => $this->visoes(), 'estilos' => self::ESTILOS,
            ]);
            return;
        }
        $setor = (int) ($_GET['setor'] ?? 0) ?: (Setores::funcaoIdAtual() ?: null);
        $pessoas = A::pessoas($setor);
        $linhas = A::resumo($pessoas, $de, $ate);
        $this->tela('acompanhamento/index', [
            'antes' => array_column(A::resumo($pessoas, $deA, $ateA), null, 'id'), 'visoes' => $this->visoes(),
            'titulo' => 'Acompanhamento', 'linhas' => $linhas, 'de' => $de, 'ate' => $ate, 'periodo' => $periodo, 'setor' => $setor,
            'setores' => Auth::pode('acompanhamento.ver') ? array_values(Setores::todos()) : array_values(array_filter(Setores::todos(), fn($s) => in_array((int) $s['id'], Setores::gerenciados(), true))),
            'modo' => TelefoniaService::modo(), 'estilos' => self::ESTILOS,
        ]);
    }

    public function pessoa(string $id): void
    {
        $pessoas = A::pessoas();
        $p = current(array_filter($pessoas, fn($x) => (int) $x['id'] === (int) $id));
        if (!$p) {
            Sessao::aviso('erro', 'Pessoa fora da sua equipe.');
            redirecionar('/acompanhamento');
        }
        [$de, $ate, $periodo] = A::periodo((string) ($_GET['periodo'] ?? 'mes'), (string) ($_GET['de'] ?? ''), (string) ($_GET['ate'] ?? ''));
        [$deA, $ateA] = A::anterior($de, $ate);
        $aba = in_array($_GET['aba'] ?? '', ['resumo', 'ligacoes', 'carteira', 'agenda', 'contatos', 'pedidos'], true) ? $_GET['aba'] : 'resumo';
        $this->tela('acompanhamento/pessoa', [
            'titulo' => 'Acompanhamento · ' . $p['nome'], 'p' => $p, 'resumo' => A::resumo([$p], $de, $ate)[0] ?? [], 'det' => A::detalhe((int) $p['id'], $de, $ate),
            'antes' => A::resumo([$p], $deA, $ateA)[0] ?? [], 'aba' => $aba, 'extra' => A::carteiraEAgenda((int) $p['id'], $de, $ate),
            'meta' => A::meta((int) $p['id'], substr($ate, 0, 7)),
            'de' => $de, 'ate' => $ate, 'periodo' => $periodo, 'estilos' => self::ESTILOS,
            'scripts' => ['https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js', 'acompanhamento.js'],
        ]);
    }

    /** Detalhe de quem cuida da Cobrança */
    public function cobranca(string $id): void
    {
        $p = current(array_filter(A::pessoasCobranca(), fn($x) => (int) $x['id'] === (int) $id));
        if (!$p) {
            Sessao::aviso('erro', 'Pessoa não encontrada na Cobrança.');
            redirecionar('/acompanhamento?visao=cobranca');
        }
        [$de, $ate, $periodo] = A::periodo((string) ($_GET['periodo'] ?? 'mes'), (string) ($_GET['de'] ?? ''), (string) ($_GET['ate'] ?? ''));
        $this->tela('acompanhamento/cobranca_pessoa', [
            'titulo' => 'Cobrança · ' . $p['nome'], 'p' => $p, 'r' => A::resumoCobranca([$p], $de, $ate)[0] ?? [], 'det' => A::detalheCobranca((int) $p['id'], $de, $ate),
            'de' => $de, 'ate' => $ate, 'periodo' => $periodo, 'estilos' => self::ESTILOS,
            'scripts' => ['https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js', 'acompanhamento.js'],
        ]);
    }

    /** Abas Vendas / Cobrança que a pessoa pode ver */
    private function visoes(): array
    {
        return array_filter(['vendas' => A::podeVerVendas() ? 'Vendas' : null, 'cobranca' => A::podeVerCobranca() ? 'Cobrança' : null]);
    }

    /** Toca a gravação (nunca há link público; a própria pessoa ou quem a acompanha) */
    public function gravacao(string $id): void
    {
        $g = TelefoniaService::gravacao((int) $id);
        if (!$g) {
            http_response_code(404);
            echo 'Gravação não disponível.';
            return;
        }
        header('Content-Type: ' . $g['mime']);
        header('Content-Length: ' . strlen($g['conteudo']));
        header('Cache-Control: private, no-store');
        header('X-Content-Type-Options: nosniff');
        echo $g['conteudo'];
    }

    /** Envia os clientes marcados (cliente[]) ou a carteira inteira (carteira_id) para o discador do Ligue Talk */
    public function discador(): void
    {
        $volta = (string) ($_POST['voltar'] ?? '/carteiras');
        $volta = str_starts_with($volta, '/') && !str_starts_with($volta, '//') ? $volta : '/carteiras';
        try {
            $ids = (array) ($_POST['cliente'] ?? []);
            $nome = trim((string) ($_POST['lista'] ?? '')) ?: 'One · ' . date('d/m/Y H:i');
            if (!$ids && ($k = (int) ($_POST['carteira_id'] ?? 0))) {
                $carteira = CarteirasService::daPessoa($k);
                $ids = array_column(Banco::todos('SELECT id FROM clientes WHERE carteira_id = ? AND excluido_em IS NULL', [$k]), 'id');
                $nome = trim((string) ($_POST['lista'] ?? '')) ?: $carteira['nome'];
            }
            $r = TelefoniaService::enviarParaDiscador($ids, $nome);
            Sessao::aviso($r['ok'] ? 'sucesso' : 'erro', $r['mensagem']);
        } catch (RuntimeException $e) {
            Sessao::aviso('erro', $e->getMessage());
        }
        redirecionar($volta);
    }

    /** Testa a conexão com a API do Ligue Talk (modo "api") */
    public function testar(): void
    {
        $tel = \App\Integrations\Integracoes::telefonia();
        $r = $tel instanceof \App\Integrations\LigueTalk\LigueTalkClient ? $tel->testar()
            : ['ok' => false, 'mensagem' => 'Para testar, coloque Configurações › Ligue Talk › Modo = "api" e preencha company_id e token no config.php.'];
        Sessao::aviso($r['ok'] ? 'sucesso' : 'erro', $r['mensagem']);
        redirecionar('/acompanhamento');
    }

    public function sincronizar(): void
    {
        $n = TelefoniaService::sincronizar();
        Sessao::aviso('sucesso', TelefoniaService::modo() === 'api' ? "$n ligação(ões) nova(s) do Ligue Talk." : 'A sincronização só funciona no modo "api" (Configurações › Ligue Talk).');
        redirecionar('/acompanhamento');
    }
}
