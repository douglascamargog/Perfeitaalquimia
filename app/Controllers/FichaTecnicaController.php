<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Banco;
use App\Core\Sessao;
use App\Services\Normalizar;
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
            'pode' => Auth::pode('produtos.tecnico'), 'podeOrdem' => Auth::pode('producao.gerenciar'), 'estilos' => array_merge(self::ESTILOS, ['nutricional.css']),
            'scripts' => ['producao.js', 'nutricional.js'], 'tabela' => \App\Services\TabelaNutricionalService::doProduto((int) $id),
        ]);
    }

    /** Tabela nutricional (modelo Anvisa) */
    public function salvarNutricional(string $id): void
    {
        try {
            \App\Services\TabelaNutricionalService::salvar((int) $id, $_POST, Auth::id());
            Sessao::aviso('sucesso', 'Tabela nutricional salva.');
        } catch (\RuntimeException $e) {
            Sessao::aviso('erro', $e->getMessage());
        }
        redirecionar('/producao/fichas/' . (int) $id . '#tabela-nutricional');
    }

    /** Baixar a tabela nutricional em PDF (vetorial, para a gráfica) */
    public function nutricionalPdf(string $id): void
    {
        $nome = (string) Banco::valor('SELECT nome FROM produtos WHERE id = ?', [(int) $id]);
        if ($nome === '') {
            redirecionar('/producao/fichas');
        }
        $arquivo = 'tabela-nutricional-' . trim(preg_replace('/[^a-z0-9]+/', '-', mb_strtolower(Normalizar::semAcento($nome))), '-') . '.pdf';
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $arquivo . '"');
        header('Cache-Control: private, no-store');
        echo \App\Services\TabelaNutricionalService::pdf(\App\Services\TabelaNutricionalService::doProduto((int) $id));
    }

    /** Ficha técnica do produto para imprimir (documento A4) */
    public function imprimir(string $id): void
    {
        $p = Banco::um("SELECT p.*, c.nome AS categoria, t.ean, t.ncm, t.registro_anvisa, t.validade_meses, t.peso_liquido, t.peso_bruto, t.dimensoes,
                t.unidades_caixa, t.armazenamento, t.alergenicos, t.avisos, ua.nome AS atualizado_por_nome, t.atualizado_em AS tecnico_em, m.arquivo AS imagem
            FROM produtos p LEFT JOIN categorias c ON c.id = p.categoria_id LEFT JOIN produto_tecnico t ON t.produto_id = p.id
            LEFT JOIN usuarios ua ON ua.id = t.atualizado_por LEFT JOIN midias m ON m.id = p.imagem_id WHERE p.id = ?", [(int) $id]);
        if (!$p) {
            redirecionar('/producao/fichas');
        }
        $linhas = ProducaoService::ficha((int) $id);
        $ids = array_map('intval', array_column($linhas, 'materia_prima_id')) ?: [0];
        \App\Core\Visao::mostrar('producao/ficha_imprimir', [
            'p' => $p, 'linhas' => $linhas, 'json' => json_decode((string) $p['ficha_json'], true) ?: [],
            'mps' => array_column(Banco::todos('SELECT id, tipo, fabricante, cas_ins FROM materias_primas WHERE id IN (' . implode(',', $ids) . ')'), null, 'id'),
            'tabela' => \App\Services\TabelaNutricionalService::doProduto((int) $id),
            'tecnica' => Banco::um("SELECT u.nome FROM usuarios u JOIN usuario_funcoes uf ON uf.usuario_id = u.id JOIN funcoes f ON f.id = uf.funcao_id
                WHERE f.slug = 'tecnica' AND u.ativo = 1 ORDER BY u.id LIMIT 1"),
        ], null);
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
