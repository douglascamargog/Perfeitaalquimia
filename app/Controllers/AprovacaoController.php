<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Sessao;
use App\Models\Pedido;
use App\Services\PedidoService;

/**
 * Aprovação do Financeiro: fila de pedidos com o quadro de crédito de cada cliente.
 * Aprovar avisa o ERP; recusar exige motivo (aparece para o vendedor e para o cliente).
 */
class AprovacaoController extends Controller
{
    public function index(): void
    {
        // Fila em páginas de 30 (os mais antigos primeiro): a tela não pesa mesmo com muitos pedidos esperando
        $todos = Pedido::aguardandoAprovacao();
        $porPagina = 30;
        $paginas = max(1, (int) ceil(count($todos) / $porPagina));
        $pagina = min($paginas, max(1, (int) ($_GET['pagina'] ?? 1)));
        $pedidos = array_slice($todos, ($pagina - 1) * $porPagina, $porPagina);
        $credito = $itens = [];
        foreach ($pedidos as $p) {
            $credito[$p['id']] = Pedido::credito((int) $p['cliente_id']);
            $itens[$p['id']] = Pedido::itens((int) $p['id']);
        }
        $this->tela('aprovacao/index', ['titulo' => 'Aprovação de pedidos', 'pedidos' => $pedidos, 'credito' => $credito, 'itens' => $itens,
            'total' => count($todos), 'pagina' => $pagina, 'paginas' => $paginas,
            'estilos' => ['carteira.css', 'pedidos.css']]);
    }

    private function pedido(string $id): array
    {
        $p = Pedido::buscar((int) $id);
        if (!$p) {
            Sessao::aviso('erro', 'Pedido não encontrado.');
            redirecionar('/aprovacao');
        }
        return $p;
    }

    public function aprovar(string $id): void
    {
        $p = $this->pedido($id);
        $erro = PedidoService::aprovar($p, Auth::id());
        Sessao::aviso($erro ? 'erro' : 'sucesso', $erro ?? "Pedido #{$p['id']} aprovado e liberado no ERP.");
        redirecionar($this->campo('voltar') === 'pedido' ? "/pedidos/{$p['id']}" : '/aprovacao');
    }

    public function recusar(string $id): void
    {
        $p = $this->pedido($id);
        $motivo = mb_substr($this->campo('motivo'), 0, 500);
        if ($motivo === '') {
            Sessao::aviso('erro', 'Escreva o motivo da recusa (o vendedor e o cliente vão ver).');
        } else {
            $erro = PedidoService::recusar($p, $motivo, Auth::id());
            Sessao::aviso($erro ? 'erro' : 'sucesso', $erro ?? "Pedido #{$p['id']} recusado.");
        }
        redirecionar($this->campo('voltar') === 'pedido' ? "/pedidos/{$p['id']}" : '/aprovacao');
    }
}
