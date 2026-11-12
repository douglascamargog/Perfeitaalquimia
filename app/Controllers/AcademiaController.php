<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Sessao;
use App\Services\AcademiaService as A;

/**
 * Academia comercial (conteúdo da Central de Televendas, no visual do sistema):
 * início, trilha de treinamento, produtos, guia de atendimento e CRM na prática.
 * O progresso de estudo fica salvo no servidor, por pessoa.
 */
class AcademiaController extends Controller
{
    private const POR_PAGINA = 24;

    /** Dados comuns às telas da Academia */
    private function tela_(string $pagina, string $aba, array $dados): void
    {
        $progresso = A::progresso(Auth::id());
        $itens = A::itensDeEstudo();
        $feitos = count(array_intersect($itens, array_keys($progresso)));
        $this->tela($pagina, $dados + [
            'aba'       => $aba,
            'progresso' => $progresso,
            'percentual' => $itens ? round($feitos / count($itens) * 100) : 0,
            'feitos'    => $feitos,
            'totalItens' => count($itens),
            'estilos'   => ['carteira.css', 'academia.css'],
            'scripts'   => ['academia.js'],
        ]);
    }

    public function inicio(): void
    {
        $progresso = A::progresso(Auth::id());
        $proximo = null;
        foreach (A::modulos() as $m) {
            if (!isset($progresso['modulo:' . $m['id']])) {
                $proximo = $m;
                break;
            }
        }
        $this->tela_('academia/inicio', 'inicio', [
            'titulo'     => 'Academia',
            'proximo'    => $proximo,
            'modulos'    => A::modulos(),
            'totalProdutos' => count(A::produtos()),
            'aulasCrm'   => A::aulasCrm(),
            'categorias' => A::categorias(),
            'temas'      => A::temas(),
        ]);
    }

    public function trilha(): void
    {
        $this->tela_('academia/trilha', 'trilha', ['titulo' => 'Academia · Trilha', 'modulos' => A::modulos()]);
    }

    public function modulo(string $id): void
    {
        $m = A::modulo($id);
        if (!$m) {
            Sessao::aviso('erro', 'Módulo não encontrado.');
            redirecionar('/academia/trilha');
        }
        $modulos = A::modulos();
        $dados = ['titulo' => 'Academia · ' . $m['title'], 'm' => $m, 'anterior' => $modulos[$m['indice'] - 1] ?? null, 'proximo' => $modulos[$m['indice'] + 1] ?? null];
        // Módulos com página própria
        if ($id === 'empresa') {
            $dados['pagina'] = A::conteudo('pagina_empresa');
            $dados['produtos'] = A::produtos();
        } elseif ($id === 'midias-marketing') {
            $dados['pagina'] = A::conteudo('pagina_marketing');
            $dados['imagens'] = A::conteudo('marketing');
            $dados['linkCatalogo'] = A::link('catalogo');
            $dados['linkGrupo'] = A::link('grupo_marketing');
        } elseif (!empty($m['productSlug'])) {
            $dados['produto'] = A::produto($m['productSlug']);
        }
        $this->tela_('academia/modulo', 'trilha', $dados);
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
            'titulo'     => 'Academia · Produtos',
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
        $this->tela_('academia/produto', 'produtos', ['titulo' => 'Academia · ' . $p['nome'], 'p' => $p, 'abas' => $abas, 'abaProduto' => $aba, 'relacionados' => $relacionados]);
    }

    public function atendimento(): void
    {
        $this->tela_('academia/atendimento', 'atendimento', ['titulo' => 'Academia · Guia de atendimento', 'pagina' => A::conteudo('pagina_atendimento')]);
    }

    public function crm(string $id = ''): void
    {
        $aulas = A::aulasCrm();
        $indice = 0;
        foreach ($aulas as $i => $a) {
            if ($a['id'] === $id) {
                $indice = $i;
            }
        }
        $this->tela_('academia/crm', 'crm', ['titulo' => 'Academia · CRM na prática', 'aulas' => $aulas, 'indice' => $indice, 'midias' => A::conteudo('media')]);
    }

    /** Marca/desmarca um item como estudado (formulário ou JavaScript) */
    public function marcar(): void
    {
        $item = $this->campo('item');
        A::marcar(Auth::id(), $item, $this->campo('concluido') === '1');
        if (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')) {
            $this->json(['ok' => true]);
            return;
        }
        $voltar = $this->campo('voltar');
        redirecionar(str_starts_with($voltar, '/academia') ? $voltar : '/academia/trilha');
    }
}
