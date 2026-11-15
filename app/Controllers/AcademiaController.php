<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Sessao;
use App\Services\AcademiaService as A;

/**
 * Biblioteca de produtos (as fichas para estudar: composição, uso, falas e treinamento técnico).
 * Desde a V13.0 o treinamento fica em /treinamento (TreinamentoController); os endereços antigos
 * da Academia (/academia, trilha, atendimento, aulas) levam para lá.
 */
class AcademiaController extends Controller
{
    private const POR_PAGINA = 24;

    /** Dados comuns às telas da biblioteca */
    private function tela_(string $pagina, string $aba, array $dados): void
    {
        $this->tela($pagina, $dados + ['aba' => $aba, 'estilos' => ['carteira.css', 'academia.css'], 'scripts' => ['academia.js']]);
    }

    /** Endereços antigos da Academia: vão para o Treinamento */
    public function paraTreinamento(string $a = '', string $b = ''): void
    {
        redirecionar('/treinamento');
    }

    public function produtos(): void
    {
        $q = trim((string) ($_GET['q'] ?? ''));
        $categoria = (string) ($_GET['categoria'] ?? '');
        $temaId = (string) ($_GET['tema'] ?? '');
        [$lista, $tema] = A::buscar($q, $categoria, $temaId);
        $total = count($lista);
        $paginas = max(1, (int) ceil($total / self::POR_PAGINA));
        $pagina = min(max(1, (int) ($_GET['pagina'] ?? 1)), $paginas);
        $this->tela_('academia/produtos', 'produtos', [
            'titulo'     => 'Biblioteca de produtos',
            'lista'      => array_slice($lista, ($pagina - 1) * self::POR_PAGINA, self::POR_PAGINA),
            'total'      => $total, 'pagina' => $pagina, 'paginas' => $paginas,
            'q'          => $q, 'categoria' => $categoria, 'tema' => $tema,
            'categorias' => A::categorias(), 'temas' => A::temas(),
        ]);
    }

    public function produto(string $slug): void
    {
        $p = A::produto($slug);
        if (!$p) {
            Sessao::aviso('erro', 'Produto não encontrado.');
            redirecionar('/academia/produtos');
        }
        // Abas: a de composição só aparece se houver dados; a de treinamento técnico só para produtos que têm
        $abas = ['conheca' => 'Conheça o produto'];
        if (!empty($p['nutrition']) || !empty($p['ingredients']) || !empty($p['capsule'])) {
            $abas['composicao'] = 'Composição';
        }
        $abas += ['uso' => 'Uso e cuidados', 'atendimento' => 'No atendimento'];
        if (!empty($p['technicalTraining'])) {
            $abas['treinamento'] = 'Treinamento técnico';
        }
        $aba = array_key_exists($_GET['aba'] ?? '', $abas) ? $_GET['aba'] : 'conheca';
        $relacionados = array_values(array_filter(array_map(fn($s) => A::produto($s), $p['related'] ?? [])));
        $this->tela_('academia/produto', 'produtos', ['titulo' => $p['nome'], 'p' => $p, 'abas' => $abas, 'abaProduto' => $aba, 'relacionados' => $relacionados]);
    }
}
