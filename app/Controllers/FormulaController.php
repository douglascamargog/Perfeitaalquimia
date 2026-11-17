<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Banco;
use App\Core\Sessao;
use App\Services\FormulaService as F;

/**
 * Fórmulas (V15.0) — área da Responsável Técnica: lista, editor com as contas, versões, testes e aprovação
 * (a aprovada vira a ficha técnica do produto).
 */
class FormulaController extends Controller
{
    private const ESTILOS = ['carteira.css', 'produtos.css', 'formulas.css'];

    public function index(): void
    {
        $filtros = ['situacao' => (string) ($_GET['situacao'] ?? ''), 'forma' => (string) ($_GET['forma'] ?? ''), 'projeto' => !empty($_GET['projeto']),
            'busca' => mb_substr(trim((string) ($_GET['busca'] ?? '')), 0, 60)];
        $this->tela('formulas/index', ['titulo' => 'Fórmulas', 'lista' => F::lista($filtros), 'filtros' => $filtros, 'estilos' => self::ESTILOS]);
    }

    public function nova(): void
    {
        $projeto = (int) ($_GET['projeto'] ?? 0);
        $proj = $projeto ? Banco::um('SELECT id, nome, produto_id FROM dp_projetos WHERE id = ?', [$projeto]) : null;
        $this->tela('formulas/nova', ['titulo' => 'Nova fórmula', 'projeto' => $proj, 'produtos' => $this->produtos(), 'estilos' => self::ESTILOS]);
    }

    public function criar(): void
    {
        $id = F::criar($_POST, (int) Auth::id());
        Sessao::aviso('sucesso', 'Fórmula criada. Agora coloque os ingredientes.');
        redirecionar('/formulas/' . $id);
    }

    public function ver(string $id): void
    {
        $f = ctype_digit($id) ? F::buscar((int) $id) : null;
        if (!$f) {
            http_response_code(404);
            $this->tela('erros/erro', ['titulo' => 'Fórmula não encontrada', 'codigo' => 404, 'mensagem' => 'Fórmula não encontrada.']);
            return;
        }
        $this->tela('formulas/ver', [
            'titulo' => $f['codigo'] . ' · ' . $f['nome'], 'f' => $f, 'calc' => F::calcular($f, $f['itens']),
            'mps' => Banco::todos("SELECT id, codigo, nome, unidade, custo_unitario, estoque, tipo FROM materias_primas WHERE ativo = 1 ORDER BY tipo = 'materia_prima' DESC, nome"),
            'produtos' => $this->produtos(),
            'versoes' => Banco::todos('SELECT id, versao, situacao, atualizado_em FROM formulas WHERE codigo = ? ORDER BY versao DESC', [$f['codigo']]),
            'testes' => Banco::todos('SELECT t.*, u.nome AS autor FROM formula_testes t LEFT JOIN usuarios u ON u.id = t.criado_por WHERE t.formula_id = ? ORDER BY t.data DESC, t.id DESC', [(int) $id]),
            'historico' => Banco::todos('SELECT h.*, u.nome AS autor FROM formula_historico h LEFT JOIN usuarios u ON u.id = h.usuario_id WHERE h.formula_id = ? ORDER BY h.id DESC LIMIT 50', [(int) $id]),
            'pendencias' => F::pendenciasAprovacao($f),
            'podeEditar' => Auth::pode('formulas.editar') && in_array($f['situacao'], ['rascunho', 'em_teste'], true),
            'estilos' => self::ESTILOS, 'scripts' => ['formulas.js'],
        ]);
    }

    public function salvar(string $id): void
    {
        $this->executar($id, fn() => F::salvar((int) $id, $_POST, (int) Auth::id()), 'Fórmula salva.');
    }

    public function novaVersao(string $id): void
    {
        try {
            $novo = F::novaVersao((int) $id, (int) Auth::id());
            Sessao::aviso('sucesso', 'Nova versão criada. A anterior ficou guardada como estava.');
            redirecionar('/formulas/' . $novo);
        } catch (\RuntimeException $e) {
            Sessao::aviso('erro', $e->getMessage());
            redirecionar('/formulas/' . (int) $id);
        }
    }

    public function situacao(string $id): void
    {
        $this->executar($id, fn() => F::mudarSituacao((int) $id, (string) ($_POST['situacao'] ?? ''), (int) Auth::id()), 'Situação da fórmula atualizada.');
    }

    public function teste(string $id): void
    {
        $this->executar($id, fn() => F::registrarTeste((int) $id, $_POST, (int) Auth::id()), 'Teste registrado.', '#testes');
    }

    public function aprovar(string $id): void
    {
        $this->executar($id, function () use ($id) {
            $linhas = F::aprovar((int) $id, (int) Auth::id());
            Sessao::aviso('sucesso', 'Fórmula aprovada: a ficha técnica do produto foi atualizada com ' . count($linhas) . ' matéria(s)-prima(s). A Produção foi avisada.');
        }, null);
    }

    public function comparar(string $id): void
    {
        $f = F::buscar((int) $id);
        $outro = (int) ($_GET['com'] ?? ($f['formula_origem_id'] ?? 0));
        if (!$f || !$outro) {
            Sessao::aviso('erro', 'Escolha a versão para comparar.');
            redirecionar('/formulas/' . (int) $id);
        }
        try {
            $dif = F::comparar((int) $id, $outro);
        } catch (\RuntimeException $e) {
            Sessao::aviso('erro', $e->getMessage());
            redirecionar('/formulas/' . (int) $id);
        }
        $this->tela('formulas/comparar', ['titulo' => 'Comparar versões', 'f' => $f, 'dif' => $dif, 'outroId' => $outro, 'estilos' => self::ESTILOS]);
    }

    /** Roda uma ação e volta para a fórmula com o aviso (erro de regra vira aviso em português) */
    private function executar(string $id, callable $acao, ?string $ok, string $ancora = ''): void
    {
        try {
            $acao();
            if ($ok) {
                Sessao::aviso('sucesso', $ok);
            }
        } catch (\RuntimeException $e) {
            Sessao::aviso('erro', $e->getMessage());
        }
        redirecionar('/formulas/' . (int) $id . $ancora);
    }

    private function produtos(): array
    {
        return Banco::todos('SELECT id, nome, codigo, ativo FROM produtos ORDER BY ativo DESC, nome');
    }
}
