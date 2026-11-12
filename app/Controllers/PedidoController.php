<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Banco;
use App\Core\Sessao;
use App\Integrations\Integracoes;
use App\Integrations\Omie\OmieFake;
use App\Models\Cliente;
use App\Models\Listas;
use App\Models\Pedido;
use App\Services\PedidoService;

/**
 * Pedidos da equipe: lista, novo pedido para um cliente, página do pedido (imprimir, cancelar)
 * e, só na demonstração, "Simular faturamento" (o ERP real fatura sozinho).
 */
class PedidoController extends Controller
{
    public function index(): void
    {
        $filtros = Pedido::filtrosDaUrl();
        $this->tela('pedidos/index', [
            'titulo' => 'Pedidos', 'filtros' => $filtros, 'resultado' => Pedido::listar($filtros, (int) ($_GET['pagina'] ?? 1)),
            'vendedores' => Auth::pode('pedidos.ver_todos') ? Listas::vendedores() : [],
            'meuCodigo' => Banco::valor('SELECT codigo_vendedor FROM usuarios WHERE id = ?', [Auth::id()]),
            'estilos' => ['carteira.css', 'pedidos.css'],
        ]);
    }

    /** Escolher o cliente e montar o pedido */
    public function novo(): void
    {
        $cliente = ctype_digit((string) ($_GET['cliente'] ?? '')) ? Cliente::buscar((int) $_GET['cliente']) : null;
        if ($cliente && !Cliente::podeVer($cliente)) {
            $cliente = null;
        }
        if (!$cliente) {
            $busca = trim((string) ($_GET['q'] ?? ''));
            $clientes = $busca !== '' ? Cliente::listar(Cliente::filtrosDe(['q' => $busca]), 1, 20)['itens'] : [];
            $this->tela('pedidos/escolher_cliente', ['titulo' => 'Novo pedido', 'busca' => $busca, 'clientes' => $clientes, 'estilos' => ['carteira.css', 'pedidos.css']]);
            return;
        }
        $tabela = PedidoService::tabelaDoCliente($cliente);
        $produtos = Banco::todos('SELECT p.id, p.nome, p.embalagem, c.nome AS categoria, m.arquivo AS imagem, pp.preco, e.quantidade AS estoque
            FROM produtos p JOIN produto_precos pp ON pp.produto_id = p.id AND pp.tabela_preco_id = ?
            LEFT JOIN categorias c ON c.id = p.categoria_id LEFT JOIN midias m ON m.id = p.imagem_id LEFT JOIN produto_estoque e ON e.produto_id = p.id
            WHERE p.ativo = 1 AND p.visivel_catalogo = 1 ORDER BY p.nome', [$tabela['id']]);
        $ultimo = Banco::valor("SELECT id FROM pedidos WHERE cliente_id = ? AND situacao IN ('faturado','aprovado') ORDER BY criado_em DESC LIMIT 1", [$cliente['id']]);
        $quantidades = (array) Sessao::antigo('qtd', []);
        if (!$quantidades && ($_GET['repetir'] ?? '') === '1' && $ultimo) {
            foreach (Pedido::itens((int) $ultimo) as $i) {
                $quantidades[$i['produto_id']] = $i['quantidade'];
            }
        }
        $this->tela('pedidos/novo', [
            'titulo' => 'Novo pedido', 'c' => $cliente, 'tabela' => $tabela, 'produtos' => $produtos, 'quantidades' => $quantidades,
            'minimo' => PedidoService::pedidoMinimo($cliente['uf']), 'formas' => PedidoService::formasDePagamento($cliente, PHP_FLOAT_MAX),
            'boletoBloqueado' => PedidoService::boletoBloqueado($cliente), 'ultimo' => $ultimo, 'uuid' => PedidoService::novoUuid(),
            'estilos' => ['carteira.css', 'pedidos.css'], 'scripts' => ['pedido.js'],
        ]);
    }

    public function criar(): void
    {
        $cliente = Cliente::buscar((int) $this->campo('cliente_id'));
        if (!$cliente || !Cliente::podeVer($cliente)) {
            redirecionar('/pedidos/novo');
        }
        [$forma, $condicao] = array_map('intval', explode(':', $this->campo('pagamento') . ':0'));
        [$id, $erros] = PedidoService::criar($cliente, [
            'itens' => (array) ($_POST['qtd'] ?? []), 'forma_pagamento_id' => $forma, 'condicao_id' => $condicao,
            'observacoes' => $this->campo('observacoes'), 'origem' => 'sistema', 'usuario_id' => Auth::id(), 'uuid' => $this->campo('uuid'),
        ]);
        if ($erros) {
            $this->voltarComErros('/pedidos/novo?cliente=' . (int) $cliente['id'], $erros);
        }
        Sessao::aviso('sucesso', "Pedido #$id criado e enviado ao ERP como orçamento. Agora é com o Financeiro.");
        redirecionar("/pedidos/$id");
    }

    private function pedidoOuErro(string $id): array
    {
        $p = Pedido::buscar((int) $id);
        if (!$p || !Pedido::podeVer($p)) {
            http_response_code(404);
            (new \App\Core\Roteador([]))->erro(404, 'Pedido não encontrado.');
            exit;
        }
        return $p;
    }

    public function ver(string $id): void
    {
        $p = $this->pedidoOuErro($id);
        $this->tela('pedidos/ver', [
            'titulo' => 'Pedido #' . $p['id'], 'p' => $p, 'itens' => Pedido::itens((int) $p['id']), 'historico' => Pedido::historico((int) $p['id']),
            'credito' => Auth::pode('pedidos.aprovar') ? Pedido::credito((int) $p['cliente_id']) : null,
            'erpSimulado' => Integracoes::erp() instanceof OmieFake, 'estilos' => ['carteira.css', 'pedidos.css'],
        ]);
    }

    public function cancelar(string $id): void
    {
        $p = $this->pedidoOuErro($id);
        $erro = PedidoService::cancelar($p, Auth::id(), 'pela equipe');
        Sessao::aviso($erro ? 'erro' : 'sucesso', $erro ?? 'Pedido cancelado.');
        redirecionar("/pedidos/$id");
    }

    /** SÓ NA DEMONSTRAÇÃO: emite a NF no ERP simulado e traz o faturamento para o One (o cron faria isso sozinho) */
    public function simularFaturamento(string $id): void
    {
        $p = $this->pedidoOuErro($id);
        $erp = Integracoes::erp();
        if (!$erp instanceof OmieFake || $p['situacao'] !== 'aprovado' || !$p['omie_codigo']) {
            Sessao::aviso('erro', 'Só dá para simular o faturamento de pedido aprovado, no ERP de demonstração.');
            redirecionar("/pedidos/$id");
        }
        $erp->simularFaturamento($p['omie_codigo']);
        \App\Services\ErpSync::pedidos();
        Sessao::aviso('sucesso', 'Faturamento simulado: a nota voltou do ERP e a ficha do cliente foi atualizada.');
        redirecionar("/pedidos/$id");
    }
}
