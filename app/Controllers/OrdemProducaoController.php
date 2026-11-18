<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Banco;
use App\Core\Sessao;
use App\Services\ProducaoService;
use RuntimeException;

/**
 * Produção › Ordens de produção: planejar (com prévia do consumo), concluir (baixa automática
 * das matérias-primas e soma do produto acabado), cancelar e imprimir para o chão de fábrica.
 */
class OrdemProducaoController extends Controller
{
    private const ESTILOS = ['carteira.css', 'painel.css', 'producao.css'];

    public function index(): void
    {
        $f = ['situacao' => (string) ($_GET['situacao'] ?? ''), 'produto' => (int) ($_GET['produto'] ?? 0), 'q' => trim((string) ($_GET['q'] ?? ''))];
        $this->tela('producao/ordens', [
            'titulo' => 'Ordens de produção', 'f' => $f, 'r' => ProducaoService::ordens($f, (int) ($_GET['pagina'] ?? 1)),
            'produtos' => self::produtosComFicha(), 'pode' => Auth::pode('producao.gerenciar'), 'estilos' => self::ESTILOS,
        ]);
    }

    public function nova(): void
    {
        $this->tela('producao/ordem_form', [
            'titulo' => 'Nova ordem de produção', 'produtos' => self::produtosComFicha(),
            // Terceirização: clientes que fazem cada produto com a marca deles (a ordem usa os rótulos do cliente)
            'terc' => \App\Services\TerceirizacaoService::produtos(),
            'produtoId' => (int) ($_GET['produto'] ?? Sessao::antigo('produto_id', 0)), 'estilos' => self::ESTILOS, 'scripts' => ['producao.js'],
        ]);
    }

    /** JSON: prévia do consumo para o formulário (produto + quantidade) */
    public function previa(): void
    {
        $qtd = max(0, min(1000000, (int) ($_GET['quantidade'] ?? 0)));
        $p = ProducaoService::previa((int) ($_GET['produto'] ?? 0), $qtd);
        foreach ($p['linhas'] as &$l) {
            $l['precisa_txt'] = ProducaoService::qtd($l['precisa'], $l['unidade']);
            $l['estoque_txt'] = ProducaoService::qtd($l['estoque'], $l['unidade']);
            $l['falta_txt'] = $l['falta'] > 0 ? ProducaoService::qtd($l['falta'], $l['unidade']) : '';
            $l['perda_txt'] = $l['perda_pct'] > 0 ? '+' . number_format($l['perda_pct'], 1, ',', '.') . '% de perda' . ($l['perda_origem'] === 'materia' ? ' (média da matéria-prima)' : '') : '';
        }
        unset($l);
        $p['embalagens_url'] = $p['slug'] !== '' ? url('/produtos/' . $p['slug']) . '?aba=embalagens' : '';
        $p['ficha_url'] = $p['slug'] !== '' ? url('/produtos/' . $p['slug']) . '?aba=ficha' : '';
        $p['custo_txt'] = dinheiro($p['custo']);
        $p['maximo_txt'] = number_format((int) $p['maximo'], 0, ',', '.');
        $p['custo_unidade_txt'] = $qtd > 0 ? dinheiro($p['custo'] / $qtd) : '';
        $this->json($p);
    }

    public function criar(): void
    {
        try {
            $id = ProducaoService::criarOrdem($_POST, Auth::id());
            Sessao::aviso('sucesso', "Ordem de produção nº $id planejada.");
            redirecionar("/producao/ordens/$id");
        } catch (RuntimeException $e) {
            $this->voltarComErros('/producao/ordens/nova', [$e->getMessage()]);
        }
    }

    public function ver(string $id): void
    {
        $o = ProducaoService::ordem((int) $id);
        if (!$o) {
            Sessao::aviso('erro', 'Ordem não encontrada.');
            redirecionar('/producao/ordens');
        }
        $this->tela('producao/ordem', [
            'titulo' => 'Ordem de produção nº ' . $o['id'], 'o' => $o,
            'previa' => $o['situacao'] === 'planejada' ? ProducaoService::previa((int) $o['produto_id'], (int) $o['quantidade']) : null,
            'consumo' => $o['situacao'] === 'concluida' ? ProducaoService::consumoDaOrdem((int) $id) : [],
            'perdas' => $o['situacao'] === 'concluida' ? ProducaoService::perdasDaOrdem((int) $id) : [],
            'pode' => Auth::pode('producao.gerenciar'), 'podeAprovar' => Auth::pode('produtos.tecnico'), 'estilos' => self::ESTILOS, 'scripts' => ['producao.js'],
            'lotes' => $o['situacao'] !== 'cancelada' ? ProducaoService::lotesDaOrdem((int) $id, $o['situacao'] === 'planejada'
                ? ProducaoService::previa((int) $o['produto_id'], (int) $o['quantidade'])['linhas'] : ProducaoService::consumoDaOrdem((int) $id)) : [],
        ]);
    }

    /** Escolher o lote de cada matéria-prima (o padrão é o que vence primeiro) */
    public function lotes(string $id): void
    {
        try {
            ProducaoService::escolherLotes((int) $id, (array) ($_POST['lote'] ?? []), Auth::id());
            Sessao::aviso('sucesso', 'Lotes das matérias-primas salvos.');
        } catch (RuntimeException $e) {
            Sessao::aviso('erro', $e->getMessage());
        }
        redirecionar('/producao/ordens/' . (int) $id);
    }

    /** Responsável Técnica revisa e aprova a ordem */
    public function aprovar(string $id): void
    {
        try {
            ProducaoService::aprovarOrdem((int) $id, (int) Auth::id());
            Sessao::aviso('sucesso', 'Ordem revisada e aprovada.');
        } catch (RuntimeException $e) {
            Sessao::aviso('erro', $e->getMessage());
        }
        redirecionar('/producao/ordens/' . (int) $id);
    }

    /** Versão A4 para o chão de fábrica (abre direto a janela de impressão) */
    public function imprimir(string $id): void
    {
        $o = ProducaoService::ordem((int) $id);
        if (!$o) {
            redirecionar('/producao/ordens');
        }
        // A ficha (peso por unidade, perda) vem da prévia; na ordem concluída, as quantidades são as que realmente saíram
        $linhas = ProducaoService::previa((int) $o['produto_id'], (int) $o['quantidade'])['linhas'];
        if ($o['situacao'] === 'concluida') {
            $real = array_column(ProducaoService::consumoDaOrdem((int) $id), 'quantidade', 'materia_prima_id');
            foreach ($linhas as &$l) {
                $l['precisa'] = (float) ($real[$l['materia_prima_id']] ?? $l['precisa']);
            }
            unset($l);
        }
        $ids = array_map('intval', array_column($linhas, 'materia_prima_id')) ?: [0];
        \App\Core\Visao::mostrar('producao/ordem_imprimir', [
            'titulo' => 'OP ' . $o['id'], 'o' => $o, 'linhas' => $linhas,
            'lotes' => ProducaoService::lotesDaOrdem((int) $id, $linhas),
            'mps' => array_column(\App\Core\Banco::todos('SELECT id, fabricante, cas_ins FROM materias_primas WHERE id IN (' . implode(',', $ids) . ')'), null, 'id'),
        ], null); // página própria, sem menu (A4 deitado, modelo PLA08.1)
    }

    public function concluir(string $id): void
    {
        try {
            $real = $this->campo('quantidade_real');
            $usado = array_filter((array) ($_POST['usado'] ?? []), fn($v) => is_string($v) && trim($v) !== '');
            ProducaoService::concluirOrdem((int) $id, Auth::id(), $real !== '' ? (int) $real : null, null, $usado);
            Sessao::aviso('sucesso', 'Ordem concluída: matérias-primas baixadas e produto acabado somado ao estoque.'
                . ($usado ? ' O desperdício medido já vale para as próximas ordens.' : ''));
        } catch (RuntimeException $e) {
            Sessao::aviso('erro', $e->getMessage());
        }
        redirecionar("/producao/ordens/$id");
    }

    public function cancelar(string $id): void
    {
        try {
            ProducaoService::cancelarOrdem((int) $id, $this->campo('motivo'), Auth::id());
            Sessao::aviso('sucesso', 'Ordem cancelada. Nenhum estoque foi mexido.');
        } catch (RuntimeException $e) {
            Sessao::aviso('erro', $e->getMessage());
        }
        redirecionar("/producao/ordens/$id");
    }

    /** Editar uma ordem planejada (V15.7) */
    public function editar(string $id): void
    {
        try {
            ProducaoService::editarOrdem((int) $id, $_POST, Auth::id());
            Sessao::aviso('sucesso', 'Ordem atualizada.');
        } catch (RuntimeException $e) {
            Sessao::aviso('erro', $e->getMessage());
        }
        redirecionar("/producao/ordens/$id");
    }

    private static function produtosComFicha(): array
    {
        return Banco::todos('SELECT p.id, p.nome, p.embalagem FROM produtos p WHERE p.ativo = 1 AND EXISTS (SELECT 1 FROM ficha_tecnica f WHERE f.produto_id = p.id) ORDER BY p.nome');
    }
}
