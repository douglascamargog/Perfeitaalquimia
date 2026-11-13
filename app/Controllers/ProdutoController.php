<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Banco;
use App\Core\Sessao;
use App\Services\ProducaoService;
use App\Services\ProdutoService as PS;
use RuntimeException;

/**
 * Produtos: tudo sobre cada produto, para a equipe (a vitrine do cliente fica em Catálogo).
 * Abas da página: Visão geral, Conteúdo, Composição, Ficha técnica, Embalagens, Documentos e Histórico.
 * A parte técnica (Composição, Ficha técnica, Embalagens e Documentos) só é editada por quem tem
 * "produtos.tecnico" (Responsável Técnica); o Conteúdo, por quem edita a Academia/catálogo (Marketing).
 */
class ProdutoController extends Controller
{
    private const ESTILOS = ['carteira.css', 'academia.css', 'producao.css', 'produtos.css'];

    public const ABAS = [
        'geral'      => 'Visão geral',
        'conteudo'   => 'Conteúdo',
        'composicao' => 'Composição',
        'ficha'      => 'Ficha técnica',
        'embalagens' => 'Embalagens',
        'documentos' => 'Documentos',
        'historico'  => 'Histórico',
    ];

    public function index(): void
    {
        $f = [
            'q'         => trim((string) ($_GET['q'] ?? '')),
            'categoria' => (int) ($_GET['categoria'] ?? 0),
            'situacao'  => isset(PS::SITUACOES[$_GET['situacao'] ?? '']) ? $_GET['situacao'] : '',
        ];
        $this->tela('produtos/index', [
            'titulo' => 'Produtos', 'filtros' => $f, 'produtos' => PS::lista($f), 'contagens' => PS::contagens(),
            'categorias' => Banco::todos('SELECT id, nome FROM categorias ORDER BY nome'), 'estilos' => self::ESTILOS,
        ]);
    }

    public function ver(string $slug): void
    {
        $p = $this->produto($slug);
        $aba = isset(self::ABAS[$_GET['aba'] ?? '']) ? $_GET['aba'] : 'geral';
        $this->tela('produtos/ver', [
            'titulo' => $p['nome'], 'p' => $p, 'aba' => $aba, 'abas' => self::ABAS,
            'tecnico' => PS::tecnico((int) $p['id']), 'ficha' => PS::fichaSeparada((int) $p['id']),
            'precos' => $aba === 'geral' ? PS::precos((int) $p['id']) : [],
            'documentos' => PS::documentos((int) $p['id']),
            'historico' => $aba === 'historico' ? PS::historico((int) $p['id']) : [],
            'materias' => $aba === 'ficha' || $aba === 'embalagens' ? PS::materiasParaFicha() : [],
            'podeTecnico' => Auth::pode('produtos.tecnico'),
            'podeConteudo' => Auth::pode('academia.editar') || Auth::pode('catalogo.editar'),
            'veCusto' => PS::veCusto(), 'vePrivado' => PS::vePrivado(),
            'estilos' => self::ESTILOS, 'scripts' => ['producao.js', 'produtos.js'],
        ]);
    }

    public function salvarConteudo(string $slug): void
    {
        $p = $this->produto($slug);
        PS::salvarConteudo($p, $_POST);
        Sessao::aviso('sucesso', 'Conteúdo salvo. A Academia e o catálogo já mostram a versão nova.');
        redirecionar('/produtos/' . $p['slug'] . '?aba=conteudo');
    }

    public function salvarComposicao(string $slug): void
    {
        $p = $this->produto($slug);
        PS::salvarComposicao($p, $_POST, Auth::id());
        Sessao::aviso('sucesso', 'Composição salva.');
        redirecionar('/produtos/' . $p['slug'] . '?aba=composicao');
    }

    /** Ficha técnica: a mesma da Produção. Recebe mp[] e qtd[]; $_POST['voltar'] = ficha | embalagens */
    public function salvarFicha(string $slug): void
    {
        $p = $this->produto($slug);
        $aba = ($_POST['voltar'] ?? '') === 'embalagens' ? 'embalagens' : 'ficha';
        // A aba mostra só uma parte (matérias-primas OU embalagens): a outra parte é mantida como está
        $manter = PS::fichaSeparada((int) $p['id'])[$aba === 'embalagens' ? 'materia_prima' : 'embalagem'];
        $linhas = [];
        foreach ($manter as $l) {
            $linhas[(int) $l['materia_prima_id']] = (float) $l['quantidade'];
        }
        $qtds = (array) ($_POST['qtd'] ?? []);
        foreach ((array) ($_POST['mp'] ?? []) as $i => $mp) {
            if (ctype_digit((string) $mp) && isset($qtds[$i])) {
                $linhas[(int) $mp] = ($linhas[(int) $mp] ?? 0) + ProducaoService::numero((string) $qtds[$i]);
            }
        }
        try {
            ProducaoService::salvarFicha((int) $p['id'], $linhas);
            Sessao::aviso('sucesso', $aba === 'embalagens' ? 'Embalagens da ficha salvas.' : 'Ficha técnica salva. A Produção já usa a ficha nova.');
        } catch (RuntimeException $e) {
            Sessao::aviso('erro', $e->getMessage());
        }
        redirecionar('/produtos/' . $p['slug'] . '?aba=' . $aba);
    }

    /** Especificações (aba Embalagens) */
    public function salvarTecnico(string $slug): void
    {
        $p = $this->produto($slug);
        try {
            PS::salvarTecnico((int) $p['id'], $_POST, Auth::id());
            Sessao::aviso('sucesso', 'Especificações salvas.');
        } catch (RuntimeException $e) {
            $this->voltarComErros('/produtos/' . $p['slug'] . '?aba=embalagens', [$e->getMessage()]);
        }
        redirecionar('/produtos/' . $p['slug'] . '?aba=embalagens');
    }

    public function enviarDocumento(string $slug): void
    {
        $p = $this->produto($slug);
        try {
            PS::enviarDocumento((int) $p['id'], $_FILES['arquivo'] ?? [], $_POST, Auth::id());
            Sessao::aviso('sucesso', 'Documento guardado.');
        } catch (RuntimeException $e) {
            $this->voltarComErros('/produtos/' . $p['slug'] . '?aba=documentos', [$e->getMessage()]);
        }
        redirecionar('/produtos/' . $p['slug'] . '?aba=documentos');
    }

    /** Download pelo sistema (o arquivo nunca fica na pasta pública) */
    public function baixar(string $id): void
    {
        $doc = PS::documento((int) $id);
        if (!$doc) {
            http_response_code(404);
            Sessao::aviso('erro', 'Documento não encontrado ou sem acesso.');
            redirecionar('/produtos');
        }
        $extensao = pathinfo($doc['arquivo'], PATHINFO_EXTENSION);
        $nome = preg_replace('/[^\w\- ]+/u', '', $doc['titulo']) ?: 'documento';
        header('Content-Type: ' . $doc['mime']);
        header('Content-Length: ' . filesize($doc['caminho']));
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-store');
        // PDF e imagem abrem no navegador; ?baixar=1 força o download
        $modo = isset($_GET['baixar']) ? 'attachment' : 'inline';
        header("Content-Disposition: $modo; filename=\"" . str_replace('"', '', $nome) . ".$extensao\"; filename*=UTF-8''" . rawurlencode($nome . '.' . $extensao));
        readfile($doc['caminho']);
    }

    public function arquivarDocumento(string $id): void
    {
        $doc = PS::arquivarDocumento((int) $id);
        Sessao::aviso($doc ? 'sucesso' : 'erro', $doc ? 'Documento removido da lista (fica guardado no histórico).' : 'Documento não encontrado.');
        redirecionar($doc ? '/produtos/' . $doc['slug'] . '?aba=documentos' : '/produtos');
    }

    private function produto(string $slug): array
    {
        $p = PS::porSlug($slug);
        if (!$p) {
            Sessao::aviso('erro', 'Produto não encontrado.');
            redirecionar('/produtos');
        }
        return $p;
    }
}
