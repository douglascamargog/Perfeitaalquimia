<?php
namespace App\Controllers;

use App\Core\Banco;
use App\Core\Config;
use App\Core\Sessao;
use App\Models\Pedido;
use App\Models\Produto;
use App\Services\CatalogoAcesso;
use App\Services\PedidoService;

/**
 * Catálogo B2B (área do cliente).
 *  - Visitante: vê os produtos SEM preço e SEM estoque.
 *  - Cliente logado (CNPJ + senha): vê a tabela dele, monta o carrinho, faz o pedido, acompanha e repete.
 *  - /catalogo/v/{codigo}: link do vendedor (os pedidos feitos por esse link vão para ele).
 * Todas as rotas são públicas; as que exigem cliente logado conferem com exigirCliente().
 */
class CatalogoController extends Controller
{
    /** Dados que o layout do catálogo usa em todas as telas */
    private function tela2(string $pagina, array $dados = []): void
    {
        $cliente = CatalogoAcesso::cliente();
        $this->tela($pagina, $dados + [
            'clienteCatalogo' => $cliente,
            'tabelaCatalogo' => $cliente ? PedidoService::tabelaDoCliente($cliente)['nome'] : null,
            'vendedorCatalogo' => CatalogoAcesso::vendedor(),
            'qtdCarrinho' => array_sum(CatalogoAcesso::carrinho()),
        ], 'catalogo');
    }

    private function exigirCliente(): array
    {
        $cliente = CatalogoAcesso::cliente();
        if (!$cliente) {
            Sessao::aviso('aviso', 'Entre com o CNPJ da sua empresa para continuar.');
            redirecionar('/catalogo/entrar');
        }
        return $cliente;
    }

    // ---------------- Vitrine ----------------

    public function vitrine(): void
    {
        $cliente = CatalogoAcesso::cliente();
        $tabela = $cliente ? PedidoService::tabelaDoCliente($cliente) : null;
        $busca = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 80);
        $categoria = ctype_digit((string) ($_GET['categoria'] ?? '')) ? (int) $_GET['categoria'] : null;
        $this->tela2('catalogo/vitrine', [
            'titulo' => 'Produtos', 'busca' => $busca, 'categoria' => $categoria, 'categorias' => Produto::categorias(),
            'produtos' => Produto::vitrine($busca, $categoria, $tabela ? (int) $tabela['id'] : null, (bool) Config::get('catalogo.mostrar_sem_preco', false)),
            'carrinho' => CatalogoAcesso::carrinho(), 'logado' => (bool) $cliente,
            'minimo' => $cliente ? PedidoService::pedidoMinimo($cliente['uf']) : null,
        ]);
    }

    public function produto(string $slug): void
    {
        $cliente = CatalogoAcesso::cliente();
        $tabela = $cliente ? PedidoService::tabelaDoCliente($cliente) : null;
        $produto = Produto::porSlug($slug, $tabela ? (int) $tabela['id'] : null);
        if (!$produto) {
            http_response_code(404);
            $this->tela2('catalogo/nao_encontrado', ['titulo' => 'Produto não encontrado']);
            return;
        }
        $this->tela2('catalogo/produto', ['titulo' => $produto['nome'], 'p' => $produto, 'logado' => (bool) $cliente,
            'noCarrinho' => CatalogoAcesso::carrinho()[$produto['id']] ?? 0]);
    }

    /** Link do vendedor: /catalogo/v/{codigo} */
    public function vendedor(string $codigo): void
    {
        $vendedor = Banco::um('SELECT id FROM usuarios WHERE codigo_vendedor = ? AND ativo = 1', [mb_substr($codigo, 0, 20)]);
        if ($vendedor) {
            $_SESSION['catalogo']['vendedor_id'] = (int) $vendedor['id'];
        }
        redirecionar('/catalogo');
    }

    // ---------------- Carrinho ----------------

    /** POST (JSON): define a quantidade de um produto no carrinho */
    public function quantidade(): void
    {
        $cliente = CatalogoAcesso::cliente();
        if (!$cliente) {
            $this->json(['ok' => false, 'mensagem' => 'Entre com o CNPJ para comprar.'], 401);
            return;
        }
        $produtoId = (int) $this->campo('produto_id');
        $quantidade = max(0, min(9999, (int) $this->campo('quantidade')));
        $tabela = PedidoService::tabelaDoCliente($cliente);
        if ($quantidade > 0 && !Banco::valor('SELECT 1 FROM produtos p JOIN produto_precos pp ON pp.produto_id = p.id AND pp.tabela_preco_id = ?
                WHERE p.id = ? AND p.ativo = 1 AND p.visivel_catalogo = 1', [$tabela['id'], $produtoId])) {
            $this->json(['ok' => false, 'mensagem' => 'Este produto está sem preço na sua tabela. Fale com o seu vendedor.'], 422);
            return;
        }
        CatalogoAcesso::definirQuantidade($produtoId, $quantidade);
        $c = PedidoService::calcular($cliente, CatalogoAcesso::carrinho());
        $this->json(['ok' => true, 'quantidade' => $c['quantidade'], 'subtotal' => dinheiro($c['subtotal']), 'falta' => $c['falta'],
            'falta_texto' => $c['falta'] > 0 ? 'Faltam ' . dinheiro($c['falta']) . ' para o pedido mínimo da sua região' : 'Pedido mínimo atingido ✓',
            'percentual' => $c['minimo'] > 0 ? min(100, round($c['subtotal'] / $c['minimo'] * 100)) : 100]);
    }

    public function carrinho(): void
    {
        $cliente = $this->exigirCliente();
        $c = PedidoService::calcular($cliente, CatalogoAcesso::carrinho());
        $this->tela2('catalogo/carrinho', ['titulo' => 'Carrinho', 'c' => $c,
            'formas' => PedidoService::formasDePagamento($cliente, $c['subtotal']), 'uuid' => PedidoService::novoUuid()]);
    }

    public function finalizar(): void
    {
        $cliente = $this->exigirCliente();
        [$forma, $condicao] = array_map('intval', explode(':', $this->campo('pagamento') . ':0')); // "forma:condição" escolhido no rádio
        [$id, $erros] = PedidoService::criar($cliente, [
            'itens' => CatalogoAcesso::carrinho(), 'forma_pagamento_id' => $forma,
            'condicao_id' => $condicao, 'observacoes' => $this->campo('observacoes'), 'origem' => 'catalogo',
            'vendedor_id' => CatalogoAcesso::vendedor()['id'] ?? null, 'uuid' => $this->campo('uuid'),
        ]);
        if ($erros) {
            $this->voltarComErros('/catalogo/carrinho', $erros);
        }
        CatalogoAcesso::limparCarrinho();
        Sessao::aviso('sucesso', "Pedido #$id enviado! O Financeiro confere e você acompanha aqui. Obrigado!");
        redirecionar('/catalogo/pedidos');
    }

    // ---------------- Meus pedidos ----------------

    public function pedidos(): void
    {
        $cliente = $this->exigirCliente();
        $pedidos = Pedido::doCliente((int) $cliente['id'], 30);
        $itens = [];
        foreach ($pedidos as $p) {
            $itens[$p['id']] = Pedido::itens((int) $p['id']);
        }
        $this->tela2('catalogo/pedidos', ['titulo' => 'Meus pedidos', 'pedidos' => $pedidos, 'itens' => $itens]);
    }

    /** Repetir um pedido: coloca os mesmos itens no carrinho, com o preço de hoje */
    public function repetir(string $id): void
    {
        $cliente = $this->exigirCliente();
        $pedido = Banco::um('SELECT id FROM pedidos WHERE id = ? AND cliente_id = ?', [(int) $id, $cliente['id']]);
        if (!$pedido) {
            redirecionar('/catalogo/pedidos');
        }
        foreach (Pedido::itens((int) $pedido['id']) as $i) {
            if ($i['produto_id']) {
                CatalogoAcesso::definirQuantidade((int) $i['produto_id'], (int) $i['quantidade']);
            }
        }
        $semPreco = count(PedidoService::calcular($cliente, CatalogoAcesso::carrinho())['sem_preco']);
        Sessao::aviso('sucesso', 'Itens do pedido #' . (int) $id . ' colocados no carrinho com os preços de hoje.' . ($semPreco ? " $semPreco produto(s) sem preço ficaram de fora." : ''));
        redirecionar('/catalogo/carrinho');
    }

    public function cancelar(string $id): void
    {
        $cliente = $this->exigirCliente();
        $pedido = Banco::um('SELECT * FROM pedidos WHERE id = ? AND cliente_id = ?', [(int) $id, $cliente['id']]);
        if ($pedido) {
            $erro = PedidoService::cancelar($pedido, null, 'pelo cliente no catálogo');
            Sessao::aviso($erro ? 'erro' : 'sucesso', $erro ?? 'Pedido #' . (int) $id . ' cancelado.');
        }
        redirecionar('/catalogo/pedidos');
    }

    // ---------------- Acesso ----------------

    public function formEntrar(): void
    {
        if (CatalogoAcesso::cliente()) {
            redirecionar('/catalogo');
        }
        $this->tela2('catalogo/entrar', ['titulo' => 'Entrar', 'modo' => $_GET['modo'] ?? 'senha', 'codigoDemo' => Sessao::get('codigo_demo')]);
        Sessao::remover('codigo_demo');
    }

    public function entrar(): void
    {
        $erro = CatalogoAcesso::entrar($this->campo('cnpj'), (string) ($_POST['senha'] ?? ''));
        if ($erro) {
            $this->voltarComErros('/catalogo/entrar', [$erro]);
        }
        redirecionar(CatalogoAcesso::carrinho() ? '/catalogo/carrinho' : '/catalogo');
    }

    public function pedirCodigo(): void
    {
        [$mensagem, $codigoDemo] = CatalogoAcesso::enviarCodigo($this->campo('cnpj'));
        Sessao::aviso('sucesso', $mensagem);
        if ($codigoDemo) {
            Sessao::set('codigo_demo', $codigoDemo);
        }
        Sessao::guardarFormulario(['cnpj' => $this->campo('cnpj')]);
        redirecionar('/catalogo/entrar?modo=codigo');
    }

    public function definirSenha(): void
    {
        $erro = CatalogoAcesso::definirSenha($this->campo('cnpj'), $this->campo('codigo'), (string) ($_POST['senha'] ?? ''));
        if ($erro) {
            $this->voltarComErros('/catalogo/entrar?modo=codigo', [$erro]);
        }
        Sessao::aviso('sucesso', 'Senha criada! Da próxima vez, entre com o CNPJ e esta senha.');
        redirecionar('/catalogo');
    }

    public function sair(): void
    {
        CatalogoAcesso::sair();
        redirecionar('/catalogo');
    }
}
