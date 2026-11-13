<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Banco;
use App\Core\Sessao;
use App\Services\ProducaoService;
use RuntimeException;

/**
 * Produção › Painel (alertas, ordens planejadas, produção do mês) e Estoque acabado
 * (estoque da fábrica, mínimo, ajuste de inventário e ordem de reabastecimento).
 * Quem só tem producao.ver enxerga tudo, mas sem botões de alterar.
 */
class ProducaoController extends Controller
{
    private const ESTILOS = ['carteira.css', 'inicio.css', 'painel.css', 'producao.css'];

    public function painel(): void
    {
        $this->tela('producao/painel', [
            'titulo' => 'Produção', 'p' => ProducaoService::painel(), 'pode' => Auth::pode('producao.gerenciar'),
            'estilos' => self::ESTILOS,
            'scripts' => ['https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js', 'producao.js'],
        ]);
    }

    public function acabados(): void
    {
        $busca = trim((string) ($_GET['q'] ?? ''));
        $so = ($_GET['situacao'] ?? '') === 'abaixo';
        $this->tela('producao/acabados', [
            'titulo' => 'Estoque acabado', 'busca' => $busca, 'so' => $so, 'itens' => ProducaoService::acabados($busca, $so),
            'pode' => Auth::pode('producao.gerenciar'), 'estilos' => self::ESTILOS,
        ]);
    }

    public function acabado(string $id): void
    {
        $item = current(array_filter(ProducaoService::acabados(), fn($a) => (int) $a['id'] === (int) $id));
        if (!$item) {
            $nome = Banco::valor('SELECT nome FROM produtos WHERE id = ? AND ativo = 1', [(int) $id]);
            if (!$nome) {
                Sessao::aviso('erro', 'Produto não encontrado.');
                redirecionar('/producao/acabados');
            }
            $item = ['id' => (int) $id, 'nome' => $nome, 'embalagem' => '', 'quantidade' => 0, 'minimo' => 0, 'venda_dia' => 0, 'dias_cobertura' => null,
                'planejado' => 0, 'sugestao' => 0, 'abaixo' => false, 'tem_ficha' => 0];
        }
        $this->tela('producao/acabado', [
            'titulo' => $item['nome'], 'a' => $item, 'movimentos' => ProducaoService::movimentosAcabado((int) $id),
            'pode' => Auth::pode('producao.gerenciar'), 'estilos' => self::ESTILOS,
        ]);
    }

    public function minimo(string $id): void
    {
        ProducaoService::definirMinimo((int) $id, (int) $this->campo('minimo', '0'));
        Sessao::aviso('sucesso', 'Estoque mínimo salvo.');
        redirecionar($this->campo('voltar') === 'lista' ? '/producao/acabados' : "/producao/acabados/$id");
    }

    public function ajuste(string $id): void
    {
        try {
            $dif = ProducaoService::ajustarAcabado((int) $id, (int) $this->campo('contagem', '-1'), $this->campo('motivo'), Auth::id());
            Sessao::aviso('sucesso', $dif ? 'Inventário ajustado (' . ($dif > 0 ? '+' : '') . $dif . ' un).' : 'A contagem bate com o estoque. Nada mudou.');
        } catch (RuntimeException $e) {
            Sessao::aviso('erro', $e->getMessage());
        }
        redirecionar("/producao/acabados/$id");
    }

    /** Cria a ordem sugerida para o produto abaixo do mínimo */
    public function reabastecer(string $id): void
    {
        try {
            $op = ProducaoService::gerarReabastecimento((int) $id, Auth::id());
            Sessao::aviso('sucesso', "Ordem de reabastecimento nº $op criada. Confira e conclua quando fabricar.");
            redirecionar("/producao/ordens/$op");
        } catch (RuntimeException $e) {
            Sessao::aviso('erro', $e->getMessage());
            redirecionar('/producao');
        }
    }
}
