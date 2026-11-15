<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Banco;
use App\Core\Sessao;
use App\Services\CompraLancamentoService as L;
use App\Services\ItemCompraService as I;
use App\Services\ProducaoService;
use RuntimeException;

/**
 * Financeiro › Compras: lançamentos (NFs), itens comprados, lançar compra e notas do Omie para ligar.
 * Quem lança: Financeiro (compras.lancar) e a Qualidade (função). Quem só vê: compras.ver (Gestor, Diretoria).
 */
class FinanceiroCompraController extends Controller
{
    private const ESTILOS = ['carteira.css', 'painel.css', 'producao.css', 'graficos.css', 'compras-itens.css'];

    public function index(): void
    {
        $aba = in_array($_GET['aba'] ?? '', ['itens', 'lancar', 'omie'], true) ? $_GET['aba'] : 'lancamentos';
        $de = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_GET['de'] ?? '')) ? $_GET['de'] : date('Y-m-01');
        $ate = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_GET['ate'] ?? '')) ? $_GET['ate'] : date('Y-m-d');
        $f = ['de' => $de, 'ate' => $ate, 'q' => mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 80),
            'tipo' => array_key_exists($_GET['tipo'] ?? '', I::TIPOS) ? $_GET['tipo'] : '', 'categoria' => mb_substr((string) ($_GET['categoria'] ?? ''), 0, 60)];
        $this->tela('financeiro_compras/index', [
            'titulo' => 'Compras', 'aba' => $aba, 'f' => $f, 'pode' => Auth::pode('compras.itens') && !Auth::somenteAcompanha(),
            'lancamentos' => $aba === 'lancamentos' ? L::listar($f) : [],
            'porCategoria' => $aba === 'lancamentos' ? L::porCategoria($de, $ate) : [],
            'itens' => in_array($aba, ['itens', 'lancar'], true) ? I::catalogo($aba === 'itens' ? $f : []) : [],
            'pendentes' => L::pendentes(), 'categorias' => I::categorias(), 'unidades' => ProducaoService::unidades(),
            'fornecedores' => array_column(Banco::todos('SELECT DISTINCT fornecedor FROM compras_lancadas ORDER BY fornecedor LIMIT 300'), 'fornecedor'),
            'estilos' => self::ESTILOS, 'scripts' => ['compras-itens.js'],
        ]);
    }

    /** Lançar compra (NF) com várias linhas */
    public function lancar(): void
    {
        $linhas = [];
        foreach ((array) ($_POST['item'] ?? []) as $i => $item) {
            $linhas[] = ['materia_prima_id' => (int) $item, 'quantidade' => ProducaoService::numero((string) ($_POST['quantidade'][$i] ?? '0')),
                'valor_unitario' => ProducaoService::numero((string) ($_POST['valor'][$i] ?? '0'))];
        }
        try {
            $arquivo = $_FILES['arquivo_nf'] ?? null;
            $nf = $arquivo && ($arquivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE ? I::guardar($arquivo) : null;
            $id = L::lancar(['fornecedor' => $this->campo('fornecedor'), 'cnpj' => $this->campo('cnpj'), 'numero_nf' => $this->campo('numero_nf'),
                'emissao' => $this->campo('emissao'), 'observacoes' => $this->campo('observacoes'), 'origem' => 'manual'], $linhas, $nf, (int) Auth::id());
            Sessao::aviso('sucesso', 'Compra lançada: entrou no estoque e a NF foi para os documentos dos itens.');
            redirecionar("/financeiro/compras/$id");
        } catch (RuntimeException $e) {
            $this->voltarComErros('/financeiro/compras?aba=lancar', [$e->getMessage()]);
        }
    }

    public function ver(string $id): void
    {
        $c = ctype_digit($id) ? L::buscar((int) $id) : null;
        if (!$c) {
            Sessao::aviso('erro', 'Compra não encontrada.');
            redirecionar('/financeiro/compras');
        }
        $this->tela('financeiro_compras/ver', [
            'titulo' => 'Compra · ' . $c['fornecedor'], 'c' => $c, 'itens' => L::itens((int) $c['id']),
            'catalogo' => Banco::todos('SELECT id, nome, unidade FROM materias_primas WHERE ativo = 1 ORDER BY nome'),
            'pode' => Auth::pode('compras.itens') && !Auth::somenteAcompanha(), 'estilos' => self::ESTILOS,
        ]);
    }

    public function nf(string $id): void
    {
        $c = ctype_digit($id) ? L::buscar((int) $id) : null;
        $this->enviarArquivo($c['arquivo'] ?? null, $c['arquivo_mime'] ?? '', $c['arquivo_nome'] ?? 'nf');
    }

    public function buscarOmie(): void
    {
        [$novas, $pendentes] = L::sincronizarOmie((int) Auth::id());
        Sessao::aviso('sucesso', $novas ? "$novas nota(s) do Omie lançada(s)" . ($pendentes ? " · $pendentes item(ns) para ligar" : ' · tudo entrou no estoque') . '.' : 'Nenhuma nota nova no Omie.');
        redirecionar($pendentes ? '/financeiro/compras?aba=omie' : '/financeiro/compras');
    }

    public function ligar(string $linha): void
    {
        try {
            L::ligarItemOmie((int) $linha, (int) $this->campo('item'), (int) Auth::id());
            Sessao::aviso('sucesso', 'Item ligado: entrou no estoque. As próximas notas com ele entram sozinhas.');
        } catch (RuntimeException $e) {
            Sessao::aviso('erro', $e->getMessage());
        }
        redirecionar($this->campo('voltar') !== '' && str_starts_with($this->campo('voltar'), '/financeiro/compras') ? $this->campo('voltar') : '/financeiro/compras?aba=omie');
    }

    /** Novo item (de qualquer tipo, inclusive uso e consumo) */
    public function novoItem(): void
    {
        try {
            $id = ProducaoService::salvarMateria(null, $_POST + ['ativo' => 1], (int) Auth::id());
            Sessao::aviso('sucesso', 'Item cadastrado.');
            redirecionar("/producao/materias/$id");
        } catch (RuntimeException $e) {
            $this->voltarComErros('/financeiro/compras?aba=itens', [$e->getMessage()]);
        }
    }

    // ------------------------------------------------------------------
    // Página do item: fornecedores e documentos
    // ------------------------------------------------------------------

    public function salvarFornecedor(string $item, string $fornecedor = ''): void
    {
        try {
            I::salvarFornecedor((int) $item, ctype_digit($fornecedor) ? (int) $fornecedor : null, $_POST, (int) Auth::id());
            Sessao::aviso('sucesso', 'Fornecedor salvo.');
        } catch (RuntimeException $e) {
            Sessao::aviso('erro', $e->getMessage());
        }
        redirecionar("/producao/materias/$item?aba=fornecedores");
    }

    public function editarFornecedor(string $item, string $fornecedor): void
    {
        $this->salvarFornecedor($item, $fornecedor);
    }

    public function principal(string $item, string $fornecedor): void
    {
        try {
            I::principal((int) $item, (int) $fornecedor);
            Sessao::aviso('sucesso', 'Fornecedor principal trocado.');
        } catch (RuntimeException $e) {
            Sessao::aviso('erro', $e->getMessage());
        }
        redirecionar("/producao/materias/$item?aba=fornecedores");
    }

    public function removerFornecedor(string $item, string $fornecedor): void
    {
        I::removerFornecedor((int) $item, (int) $fornecedor);
        Sessao::aviso('sucesso', 'Fornecedor retirado da lista.');
        redirecionar("/producao/materias/$item?aba=fornecedores");
    }

    public function enviarDocumento(string $item): void
    {
        try {
            I::enviarDocumento((int) $item, $_FILES['arquivo'] ?? [], $_POST, (int) Auth::id());
            Sessao::aviso('sucesso', 'Documento guardado.');
        } catch (RuntimeException $e) {
            Sessao::aviso('erro', $e->getMessage());
        }
        redirecionar("/producao/materias/$item?aba=documentos");
    }

    public function arquivarDocumento(string $item, string $doc): void
    {
        I::arquivarDocumento((int) $item, (int) $doc);
        Sessao::aviso('sucesso', 'Documento arquivado.');
        redirecionar("/producao/materias/$item?aba=documentos");
    }

    public function documento(string $doc): void
    {
        $d = ctype_digit($doc) ? I::documento((int) $doc) : null;
        $this->enviarArquivo($d['arquivo'] ?? null, $d['mime'] ?? '', $d['nome_original'] ?? 'documento');
    }

    private function enviarArquivo(?string $arquivo, string $mime, string $nome): void
    {
        $caminho = $arquivo ? I::pasta() . '/' . basename($arquivo) : '';
        if (!$arquivo || !is_file($caminho)) {
            http_response_code(404);
            (new \App\Core\Roteador([]))->erro(404, 'Arquivo não encontrado.');
            return;
        }
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($caminho));
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-store');
        header('Content-Disposition: inline; filename="' . str_replace('"', '', preg_replace('/[^\w\-. ]+/u', '', $nome) ?: 'arquivo') . '"');
        readfile($caminho);
    }
}
