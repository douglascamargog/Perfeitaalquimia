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
        }
        unset($l);
        $p['custo_txt'] = dinheiro($p['custo']);
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
            'pode' => Auth::pode('producao.gerenciar'), 'estilos' => self::ESTILOS, 'scripts' => ['producao.js'],
        ]);
    }

    /** Versão A4 para o chão de fábrica (abre direto a janela de impressão) */
    public function imprimir(string $id): void
    {
        $o = ProducaoService::ordem((int) $id);
        if (!$o) {
            redirecionar('/producao/ordens');
        }
        \App\Core\Visao::mostrar('producao/ordem_imprimir', [
            'titulo' => 'OP ' . $o['id'], 'o' => $o,
            'linhas' => $o['situacao'] === 'concluida' ? ProducaoService::consumoDaOrdem((int) $id) : ProducaoService::previa((int) $o['produto_id'], (int) $o['quantidade'])['linhas'],
        ], null); // página própria, sem menu (A4)
    }

    public function concluir(string $id): void
    {
        try {
            $real = $this->campo('quantidade_real');
            ProducaoService::concluirOrdem((int) $id, Auth::id(), $real !== '' ? (int) $real : null);
            Sessao::aviso('sucesso', 'Ordem concluída: matérias-primas baixadas e produto acabado somado ao estoque.');
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

    private static function produtosComFicha(): array
    {
        return Banco::todos('SELECT p.id, p.nome, p.embalagem FROM produtos p WHERE p.ativo = 1 AND EXISTS (SELECT 1 FROM ficha_tecnica f WHERE f.produto_id = p.id) ORDER BY p.nome');
    }
}
