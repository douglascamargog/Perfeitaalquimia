<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Banco;
use App\Core\Sessao;
use App\Services\TerceirizacaoService as T;
use RuntimeException;

/**
 * Terceirização (marca própria do cliente): Clientes, Pedidos, Produtos por cliente e Rótulos.
 * Ver: terceirizacao.ver · cadastrar produtos e registrar rótulos: terceirizacao.editar.
 */
class TerceirizacaoController extends Controller
{
    private function base(string $pagina, array $dados): void
    {
        $this->tela('terceirizacao/' . $pagina, $dados + ['titulo' => 'Terceirização', 'pode' => Auth::pode('terceirizacao.editar'),
            'estilos' => ['carteira.css', 'producao.css', 'terceirizacao.css']]);
    }

    public function clientes(): void
    {
        $busca = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 80);
        $de = date('Y-m-01');
        $this->base('clientes', [
            'busca' => $busca, 'clientes' => T::clientes($busca), 'alertas' => count(T::alertas()),
            'faturadoMes' => T::faturado($de, date('Y-m-d')), 'faturado12' => T::faturado(date('Y-m-d', strtotime('-12 months')), date('Y-m-d')),
            'scripts' => ['carteira.js'], // ficha do cliente na lateral
        ]);
    }

    public function pedidos(): void
    {
        $situacao = in_array($_GET['situacao'] ?? '', ['aguardando_aprovacao', 'aprovado', 'faturado', 'recusado', 'cancelado'], true) ? $_GET['situacao'] : '';
        $w = 'c.excluido_em IS NULL AND ' . T::EH_CLIENTE . ($situacao ? ' AND p.situacao = ?' : '');
        $pedidos = Banco::todos(
            "SELECT p.id, p.criado_em, p.total, p.situacao, p.faturado_em, COALESCE(NULLIF(c.nome_fantasia, ''), c.razao_social) AS cliente, c.id AS cliente_id,
                    f.nome AS carteira, u.nome AS vendedor,
                    (SELECT COALESCE(SUM(i.total), 0) FROM pedido_itens i WHERE i.pedido_id = p.id AND i.terceirizacao_produto_id IS NOT NULL) AS total_marca
               FROM pedidos p JOIN clientes c ON c.id = p.cliente_id LEFT JOIN funcoes f ON f.id = c.funcao_id LEFT JOIN usuarios u ON u.id = p.vendedor_id
              WHERE $w ORDER BY p.criado_em DESC LIMIT 200",
            $situacao ? [$situacao] : []
        );
        $this->base('pedidos', ['pedidos' => $pedidos, 'situacao' => $situacao]);
    }

    public function produtos(): void
    {
        $clienteId = ctype_digit((string) ($_GET['cliente'] ?? '')) ? (int) $_GET['cliente'] : null;
        $editar = ctype_digit((string) ($_GET['editar'] ?? '')) ? T::produto((int) $_GET['editar']) : null;
        $this->base('produtos', [
            'clienteId' => $clienteId, 'itens' => T::produtos($clienteId, false), 'editar' => $editar,
            'clientes' => T::clientes(), 'todosClientes' => Banco::todos("SELECT id, COALESCE(NULLIF(nome_fantasia, ''), razao_social) AS nome, cidade, uf
                FROM clientes WHERE excluido_em IS NULL AND tipo = 'cliente' ORDER BY nome"),
            'nossos' => Banco::todos('SELECT id, codigo, nome, embalagem FROM produtos WHERE ativo = 1 AND cliente_exclusivo_id IS NULL ORDER BY nome'),
        ]);
    }

    public function salvarProduto(?string $id = null): void
    {
        try {
            $novo = T::salvarProduto($id ? (int) $id : null, $_POST, Auth::id());
            $tipo = $this->campo('tipo');
            Sessao::aviso('sucesso', $id ? 'Produto do cliente atualizado.' : ($tipo === 'proprio'
                ? 'Fórmula própria cadastrada. Agora cadastre a ficha técnica dela em Produção › Fichas técnicas e o preço em Produtos.'
                : 'Produto cadastrado na marca do cliente.'));
            redirecionar('/terceirizacao/produtos?cliente=' . (int) Banco::valor('SELECT cliente_id FROM terceirizacao_produtos WHERE id = ?', [$novo]));
        } catch (RuntimeException $e) {
            $this->voltarComErros('/terceirizacao/produtos' . ($this->campo('cliente_id') ? '?cliente=' . (int) $this->campo('cliente_id') : ''), [$e->getMessage()]);
        }
    }

    public function rotulos(): void
    {
        $so = ($_GET['situacao'] ?? '') === 'alerta';
        $itens = $so ? T::alertas() : T::produtos();
        $this->base('rotulos', ['itens' => $itens, 'so' => $so, 'movimentos' => T::movimentos(null, 40), 'alertas' => count(T::alertas())]);
    }

    public function movimentar(string $id): void
    {
        $tipo = $this->campo('tipo') === 'ajuste' ? 'ajuste' : 'entrada';
        try {
            $saldo = T::movimentarRotulos((int) $id, $tipo, (int) $this->campo('quantidade', '-1'), $this->campo('observacao'), Auth::id());
            Sessao::aviso('sucesso', ($tipo === 'entrada' ? 'Chegada de rótulos registrada.' : 'Contagem ajustada.') . " Saldo: $saldo rótulo(s).");
        } catch (RuntimeException $e) {
            Sessao::aviso('erro', $e->getMessage());
        }
        redirecionar('/terceirizacao/rotulos');
    }
}
