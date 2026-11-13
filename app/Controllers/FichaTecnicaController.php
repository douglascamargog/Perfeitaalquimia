<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Banco;
use App\Core\Sessao;
use App\Services\ProducaoService;
use RuntimeException;

/**
 * Produção › Fichas técnicas: o que vai em 1 unidade de cada produto e o custo de matéria-prima.
 * Os produtos são os mesmos do catálogo e da Academia.
 */
class FichaTecnicaController extends Controller
{
    private const ESTILOS = ['carteira.css', 'painel.css', 'producao.css'];

    public function index(): void
    {
        $busca = trim((string) ($_GET['q'] ?? ''));
        $filtro = in_array($_GET['ficha'] ?? '', ['com', 'sem'], true) ? $_GET['ficha'] : '';
        $this->tela('producao/fichas', ['titulo' => 'Fichas técnicas', 'busca' => $busca, 'filtro' => $filtro,
            'itens' => ProducaoService::produtosComFicha($busca, $filtro), 'estilos' => self::ESTILOS]);
    }

    public function ver(string $id): void
    {
        $produto = Banco::um('SELECT id, nome, embalagem FROM produtos WHERE id = ?', [(int) $id]);
        if (!$produto) {
            Sessao::aviso('erro', 'Produto não encontrado.');
            redirecionar('/producao/fichas');
        }
        $this->tela('producao/ficha', [
            'titulo' => 'Ficha técnica · ' . $produto['nome'], 'produto' => $produto, 'linhas' => ProducaoService::ficha((int) $id),
            'materias' => Banco::todos('SELECT id, nome, unidade, custo_unitario FROM materias_primas WHERE ativo = 1 ORDER BY nome'),
            'pode' => Auth::pode('producao.gerenciar'), 'estilos' => self::ESTILOS, 'scripts' => ['producao.js'],
        ]);
    }

    /** Recebe mp[] e qtd[] (linhas do editor) */
    public function salvar(string $id): void
    {
        $linhas = [];
        $mps = (array) ($_POST['mp'] ?? []);
        $qtds = (array) ($_POST['qtd'] ?? []);
        foreach ($mps as $i => $mp) {
            if (ctype_digit((string) $mp) && isset($qtds[$i])) {
                $linhas[(int) $mp] = ($linhas[(int) $mp] ?? 0) + ProducaoService::numero((string) $qtds[$i]);
            }
        }
        try {
            $n = ProducaoService::salvarFicha((int) $id, $linhas);
            Sessao::aviso('sucesso', $n ? "Ficha técnica salva com $n matéria(s)-prima(s)." : 'Ficha técnica esvaziada.');
        } catch (RuntimeException $e) {
            Sessao::aviso('erro', $e->getMessage());
        }
        redirecionar("/producao/fichas/$id");
    }
}
