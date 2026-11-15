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
    private const ESTILOS = ['carteira.css', 'inicio.css', 'painel.css', 'producao.css', 'graficos.css'];

    public function painel(): void
    {
        $this->tela('producao/painel', [
            'titulo' => 'Produção', 'p' => ProducaoService::painel(), 'pode' => Auth::pode('producao.gerenciar'),
            'estilos' => self::ESTILOS,
            'scripts' => ['producao.js'], // gráfico desenhado no servidor (funciona sem internet)
        ]);
    }

    /** Produção › Rótulos: estoque de rótulos dos nossos produtos e dos clientes de terceirização */
    public function rotulos(): void
    {
        $nossos = ProducaoService::rotulos();
        $clientes = \App\Services\TerceirizacaoService::produtos();
        $so = ($_GET['situacao'] ?? '') === 'alerta';
        $alertaCliente = fn($t) => (int) $t['rotulos_estoque'] <= (int) $t['rotulos_minimo'] || (int) $t['rotulos_estoque'] < (int) $t['em_ordens'];
        $alertas = count(array_filter($nossos, fn($m) => $m['alerta'])) + count(array_filter($clientes, $alertaCliente));
        if ($so) {
            $nossos = array_values(array_filter($nossos, fn($m) => $m['alerta']));
            $clientes = array_values(array_filter($clientes, $alertaCliente));
        }
        $this->tela('producao/rotulos', [
            'titulo' => 'Rótulos', 'nossos' => $nossos, 'clientes' => $clientes, 'so' => $so, 'alertas' => $alertas,
            'alertaCliente' => $alertaCliente, 'movimentos' => ProducaoService::movimentosRotulos(40),
            'podeNossos' => Auth::pode('producao.gerenciar'), 'podeClientes' => Auth::pode('producao.gerenciar') || Auth::pode('terceirizacao.editar'),
            'estilos' => self::ESTILOS,
        ]);
    }

    /** Chegada ou contagem de rótulos ($origem: nosso = matéria-prima marcada como rótulo; cliente = produto de terceirização) */
    public function movimentarRotulo(string $origem, string $id): void
    {
        $tipo = $this->campo('tipo') === 'ajuste' ? 'ajuste' : 'entrada';
        $qtd = (int) $this->campo('quantidade', '-1');
        $obs = $this->campo('observacao');
        try {
            if ($origem === 'nosso') {
                if (!Auth::pode('producao.gerenciar')) {
                    throw new RuntimeException('Só a Produção registra os rótulos dos nossos produtos.');
                }
                if (!Banco::valor('SELECT 1 FROM materias_primas WHERE id = ? AND rotulo = 1', [(int) $id])) {
                    throw new RuntimeException('Rótulo não encontrado.');
                }
                if ($tipo === 'entrada') {
                    ProducaoService::entrada((int) $id, $qtd, 0, '', $obs !== '' ? $obs : 'Chegada de rótulos', Auth::id());
                } else {
                    ProducaoService::ajustar((int) $id, $qtd, $obs !== '' ? $obs : 'Contagem de rótulos', Auth::id());
                }
                $saldo = (int) Banco::valor('SELECT estoque FROM materias_primas WHERE id = ?', [(int) $id]);
            } elseif ($origem === 'cliente') {
                $saldo = \App\Services\TerceirizacaoService::movimentarRotulos((int) $id, $tipo, $qtd, $obs, Auth::id());
            } else {
                throw new RuntimeException('Rótulo não encontrado.');
            }
            Sessao::aviso('sucesso', ($tipo === 'entrada' ? 'Chegada de rótulos registrada.' : 'Contagem ajustada.') . " Saldo: $saldo rótulo(s).");
        } catch (RuntimeException $e) {
            Sessao::aviso('erro', $e->getMessage());
        }
        redirecionar('/producao/rotulos');
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
