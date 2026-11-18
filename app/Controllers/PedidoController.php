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
use App\Services\ModeloPedidoService;
use App\Services\OrcamentoService;
use App\Services\PedidoService;

/**
 * Pedidos da equipe: lista, novo pedido para um cliente, página do pedido (imprimir, cancelar)
 * e, só na demonstração, "Simular faturamento" (o ERP real fatura sozinho).
 */
class PedidoController extends Controller
{
    public function index(): void
    {
        $filtros = Pedido::filtrosDaUrl() + ['funcao' => (string) (\App\Services\Setores::funcaoIdAtual() ?? '')];
        $this->tela('pedidos/index', [
            'titulo' => 'Pedidos', 'filtros' => $filtros, 'resultado' => Pedido::listar($filtros, (int) ($_GET['pagina'] ?? 1)),
            'vendedores' => Auth::pode('pedidos.ver_todos') ? Listas::vendedores(\App\Services\Setores::funcaoIdAtual()) : [],
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
        $produtos = PedidoService::produtosDoCliente($cliente);
        // Editar um orçamento em aberto (ou transformá-lo em pedido): a tela vem com os itens dele
        $orcamento = ctype_digit((string) ($_GET['orcamento'] ?? '')) ? OrcamentoService::buscar((int) $_GET['orcamento']) : null;
        if ($orcamento && (!OrcamentoService::podeVer($orcamento) || (int) $orcamento['cliente_id'] !== (int) $cliente['id'] || !OrcamentoService::emAberto($orcamento))) {
            $orcamento = null;
        }
        $ultimo = Banco::valor("SELECT id FROM pedidos WHERE cliente_id = ? AND situacao IN ('faturado','aprovado') ORDER BY criado_em DESC LIMIT 1", [$cliente['id']]);
        $quantidades = (array) Sessao::antigo('qtd', []);
        $bonificados = (array) Sessao::antigo('bonif', []);
        if (!$quantidades && $orcamento) {
            foreach (OrcamentoService::itens((int) $orcamento['id']) as $i) {
                $quantidades[$i['chave']] = $i['quantidade'];
            }
        }
        // Corrigir um pedido que ainda espera aprovação (V15.7): a tela vem com os itens dele; ao enviar, o novo substitui o antigo
        $corrigir = ctype_digit((string) ($_GET['corrigir'] ?? '')) ? $this->pedidoParaCorrigir((int) $_GET['corrigir'], (int) $cliente['id']) : null;
        if (!$quantidades && $corrigir) {
            foreach (Pedido::itens((int) $corrigir['id']) as $i) {
                $chave = $i['terceirizacao_produto_id'] ? 't' . $i['terceirizacao_produto_id'] : $i['produto_id'];
                if ((int) $i['bonificado']) {
                    $bonificados[$chave] = (int) $i['quantidade']; // item bonificado volta na coluna "Bonif."
                } else {
                    $quantidades[$chave] = $i['quantidade'];
                }
            }
        }
        if (!$quantidades && ($_GET['repetir'] ?? '') === '1' && $ultimo) {
            foreach (Pedido::itens((int) $ultimo) as $i) {
                $chave = $i['terceirizacao_produto_id'] ? 't' . $i['terceirizacao_produto_id'] : $i['produto_id'];
                if ((int) $i['bonificado']) {
                    $bonificados[$chave] = (int) $i['quantidade']; // item bonificado volta na coluna "Bonif."
                } else {
                    $quantidades[$chave] = $i['quantidade'];
                }
            }
        }
        $this->tela('pedidos/novo', [
            'titulo' => 'Novo pedido', 'c' => $cliente, 'tabela' => $tabela, 'produtos' => $produtos, 'quantidades' => $quantidades, 'bonificados' => $bonificados,
            'motivosBonificacao' => PedidoService::motivosBonificacao(), 'bonificacaoMax' => (float) \App\Core\Config::get('pedidos.bonificacao_max_pct', 15),
            'minimo' => PedidoService::pedidoMinimo($cliente['uf']), 'formas' => PedidoService::formasDePagamento($cliente, PHP_FLOAT_MAX),
            'boletoBloqueado' => PedidoService::boletoBloqueado($cliente), 'ultimo' => $ultimo, 'uuid' => PedidoService::novoUuid(),
            'orcamento' => $orcamento, 'corrigir' => $corrigir, 'modelos' => ModeloPedidoService::disponiveis((int) Auth::id()),
            'podeCampanha' => ModeloPedidoService::podeCampanha(), 'setores' => \App\Services\Setores::todos(),
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
            'descontos' => (array) ($_POST['desconto'] ?? []), 'desconto_pedido_pct' => $this->campo('desconto_pedido'),
            'entrega' => in_array($this->campo('entrega'), ['cadastro', 'entrega'], true) ? $this->campo('entrega') : null,
            'bonificados' => (array) ($_POST['bonif'] ?? []), 'bonificacao_motivo' => $this->campo('bonificacao_motivo'),
        ]);
        if ($erros) {
            $this->voltarComErros('/pedidos/novo?cliente=' . (int) $cliente['id'], $erros);
        }
        if (ctype_digit($this->campo('orcamento_id'))) {
            OrcamentoService::vincularPedido((int) $this->campo('orcamento_id'), (int) $id, (int) Auth::id()); // o orçamento virou este pedido
        }
        $antigo = ctype_digit($this->campo('corrigir_id')) ? $this->pedidoParaCorrigir((int) $this->campo('corrigir_id'), (int) $cliente['id']) : null;
        if ($antigo && (int) $antigo['id'] !== (int) $id) { // o novo substitui o que estava esperando aprovação
            PedidoService::cancelar($antigo, Auth::id(), "pela equipe: substituído pelo pedido #$id (correção)");
            PedidoService::historico((int) $id, 'aguardando_aprovacao', 'Correção do pedido #' . $antigo['id'] . ' (que foi cancelado).', Auth::id());
            Sessao::aviso('sucesso', "Pedido corrigido: o #$id substitui o #{$antigo['id']}, que foi cancelado. Agora é com o Financeiro.");
            redirecionar("/pedidos/$id");
        }
        Sessao::aviso('sucesso', "Pedido #$id criado e enviado ao ERP como orçamento. Agora é com o Financeiro.");
        // One comemora: cliente que estava parado (sem compra há X dias) vale festa maior
        $total = dinheiro((float) \App\Core\Banco::valor('SELECT total FROM pedidos WHERE id = ?', [$id]));
        $nome = $cliente['nome_fantasia'] ?: $cliente['razao_social'];
        $dias = (int) \App\Core\Config::get('carteira.dias_inatividade', 90);
        if ($cliente['ultima_compra_em'] && strtotime($cliente['ultima_compra_em']) < strtotime("-$dias days")) {
            \App\Services\OneTela::festejar('Cliente reconquistado! 🏆', "$nome voltou a comprar depois de muito tempo: pedido #$id de $total. Mandou bem!");
        } else {
            \App\Services\OneTela::festejar('Pedido enviado! 🎉', "Pedido #$id de $nome: $total. Agora é com o Financeiro, eu te aviso quando for aprovado.");
        }
        redirecionar("/pedidos/$id");
    }

    /** Pedido que ainda pode ser corrigido: do mesmo cliente, aguardando aprovação e visível para a pessoa */
    private function pedidoParaCorrigir(int $id, int $clienteId): ?array
    {
        $p = Pedido::buscar($id);
        return $p && Pedido::podeVer($p) && (int) $p['cliente_id'] === $clienteId && $p['situacao'] === 'aguardando_aprovacao' && Auth::pode('pedidos.criar') ? $p : null;
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

    /** Pedidos › Bonificações (V16.4): quanto foi bonificado por vendedor, cliente, mês, motivo ou produto */
    public function bonificacoes(): void
    {
        $B = \App\Services\BonificacaoService::class;
        $periodo = array_key_exists($_GET['periodo'] ?? '', $B::PERIODOS) ? $_GET['periodo'] : '12m';
        $grupo = array_key_exists($_GET['grupo'] ?? '', $B::GRUPOS) ? $_GET['grupo'] : 'vendedor';
        $this->tela('pedidos/bonificacoes', ['titulo' => 'Bonificações', 'periodo' => $periodo, 'grupo' => $grupo,
            'resumo' => $B::resumo($periodo), 'grupos' => $B::agrupado($periodo, $grupo), 'pedidos' => $B::pedidos($periodo),
            'limite' => (float) \App\Core\Config::get('pedidos.bonificacao_max_pct', 15), 'estilos' => ['carteira.css', 'pedidos.css', 'analise.css']]);
    }
}
