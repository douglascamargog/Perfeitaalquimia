<?php
namespace App\Controllers;

use App\Core\Auditoria;
use App\Core\Banco;
use App\Core\Sessao;
use App\Models\Produto;
use App\Services\ErpSync;

/**
 * Conteúdo › Produtos: o MESMO cadastro da Academia e do catálogo.
 * Preços e estoque vêm do ERP (somente leitura aqui). Aqui se decide o que aparece no catálogo, a chamada e a ordem.
 */
class ProdutoController extends Controller
{
    public function index(): void
    {
        $busca = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 80);
        $this->tela('produtos/index', ['titulo' => 'Produtos', 'busca' => $busca] + Produto::administracao($busca) + ['estilos' => ['carteira.css', 'pedidos.css']]);
    }

    public function salvar(string $id): void
    {
        $antes = Banco::um('SELECT id, chamada, ordem, visivel_catalogo FROM produtos WHERE id = ?', [(int) $id]);
        if ($antes) {
            $dados = ['visivel_catalogo' => isset($_POST['visivel_catalogo']) ? 1 : 0, 'ordem' => (int) $this->campo('ordem', (string) $antes['ordem']),
                'chamada' => mb_substr($this->campo('chamada'), 0, 255) ?: null];
            Banco::atualizar('produtos', (int) $id, $dados);
            Auditoria::registrar('produto', (int) $id, 'editar', $antes, $dados);
            Sessao::aviso('sucesso', 'Produto atualizado.');
        }
        redirecionar('/produtos' . ($this->campo('q') !== '' ? '?q=' . rawurlencode($this->campo('q')) : '') . '#produto-' . (int) $id);
    }

    public function sincronizar(): void
    {
        $precos = ErpSync::precos();
        $estoque = ErpSync::estoque();
        Sessao::aviso('sucesso', "Atualizado do ERP: $precos preço(s) e $estoque estoque(s).");
        redirecionar('/produtos');
    }
}
