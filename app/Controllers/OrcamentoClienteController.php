<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Banco;
use App\Core\Sessao;
use App\Services\OrcamentoService;
use App\Services\PedidoService;

/**
 * Link do orçamento para o cliente: /o/{token} (público, sem senha).
 * O cliente vê os produtos e preços da tabela dele, muda as quantidades, acrescenta produtos e aprova ou recusa.
 * Segurança: o token é aleatório (64 caracteres), vence, pode ser cancelado e não leva dado do cliente na URL.
 * Muitos links errados na mesma sessão → bloqueia por um tempo (contra quem tenta adivinhar).
 */
class OrcamentoClienteController extends Controller
{
    private const MAX_ERROS = 10;

    private function orcamento(string $token): array
    {
        $erros = (int) ($_SESSION['orc_link_erros'] ?? 0);
        $o = $erros < self::MAX_ERROS ? OrcamentoService::porToken($token) : null;
        if (!$o) {
            $_SESSION['orc_link_erros'] = $erros + 1;
            http_response_code($erros >= self::MAX_ERROS ? 429 : 404);
            $this->telaCliente('catalogo/orcamento_fim', ['titulo' => 'Orçamento não encontrado', 'fim' => 'nao_encontrado', 'o' => null]);
            exit;
        }
        return $o;
    }

    private function telaCliente(string $pagina, array $dados): void
    {
        $this->tela($pagina, $dados + ['clienteCatalogo' => null, 'semMenu' => true, 'qtdCarrinho' => 0], 'catalogo');
    }

    public function ver(string $token): void
    {
        $o = $this->orcamento($token);
        $situacao = OrcamentoService::situacao($o);
        if (!in_array($situacao, ['aberto', 'visto'], true)) {
            $this->telaCliente('catalogo/orcamento_fim', ['titulo' => 'Orçamento #' . $o['id'], 'fim' => $situacao, 'o' => $o]);
            return;
        }
        if (!Auth::logado()) {
            OrcamentoService::marcarVisto($o); // a equipe abrindo para conferir não conta como "o cliente viu"
        }
        $cliente = Banco::um('SELECT * FROM clientes WHERE id = ?', [$o['cliente_id']]);
        $oferecidos = OrcamentoService::itensVendidos((int) $o['id']);
        $quantidades = (array) Sessao::antigo('qtd', []) ?: array_column($oferecidos, 'quantidade', 'chave');
        $chaves = array_column($oferecidos, 'chave');
        $todos = PedidoService::produtosDoCliente($cliente);
        $disponiveis = array_map('strval', array_column($todos, 'id'));
        $this->telaCliente('catalogo/orcamento', [
            'titulo' => 'Orçamento #' . $o['id'], 'o' => $o, 'token' => $token, 'quantidades' => $quantidades,
            'bonificados' => OrcamentoService::itensBonificados((int) $o['id']),
            // O que foi oferecido (com o preço de hoje da tabela do cliente) e os outros produtos, para acrescentar
            'oferecidos' => array_values(array_filter($todos, fn($p) => in_array((string) $p['id'], $chaves, true))),
            'semPrecoAgora' => array_values(array_filter($oferecidos, fn($i) => !in_array((string) $i['chave'], $disponiveis, true))), // saiu do catálogo
            'outros' => array_values(array_filter($todos, fn($p) => !in_array((string) $p['id'], $chaves, true))),
            'minimo' => PedidoService::pedidoMinimo($cliente['uf']), 'formas' => PedidoService::formasDePagamento($cliente, PHP_FLOAT_MAX),
            'estilos' => ['pedidos.css', 'orcamento-cliente.css'], 'scripts' => ['pedido.js'],
        ]);
    }

    public function aprovar(string $token): void
    {
        $o = $this->orcamento($token);
        [$forma, $condicao] = array_map('intval', explode(':', $this->campo('pagamento') . ':0'));
        [$pedidoId, $erros] = OrcamentoService::aprovar($o, (array) ($_POST['qtd'] ?? []), $forma, $condicao, $this->campo('mensagem'));
        if ($erros) {
            $this->voltarComErros('/o/' . $o['token'], $erros);
        }
        redirecionar('/o/' . $o['token']);
    }

    public function recusar(string $token): void
    {
        $o = $this->orcamento($token);
        $erro = OrcamentoService::recusar($o, $this->campo('mensagem'));
        if ($erro) {
            $this->voltarComErros('/o/' . $o['token'], [$erro]);
        }
        redirecionar('/o/' . $o['token']);
    }
}
