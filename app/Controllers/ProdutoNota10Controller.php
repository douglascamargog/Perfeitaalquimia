<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Sessao;
use App\Services\ProdutoNota10Service as S;

/** Produtos › Ligar produtos do Nota10 (duplicados criados pela importação → produto do One) */
class ProdutoNota10Controller extends Controller
{
    public function index(): void
    {
        $this->tela('produtos/nota10', ['titulo' => 'Ligar produtos do Nota10', 'pendentes' => S::pendentes(), 'doOne' => S::doOne(),
            'ligados' => S::ligados(), 'estilos' => ['carteira.css', 'pedidos.css']]);
    }

    /** Liga um (origem + destino) */
    public function juntar(): void
    {
        try {
            // o campo traz "123 · código · nome" (lista de busca): o número do começo é o produto do One
            $destino = preg_match('/^\s*(\d+)\s*·/u', $this->campo('destino'), $m) ? (int) $m[1] : 0;
            S::juntar((int) $this->campo('origem'), $destino, Auth::id());
            Sessao::aviso('sucesso', 'Produto ligado: o histórico do Nota10 agora está no produto do One.');
        } catch (\RuntimeException $e) {
            Sessao::aviso('erro', $e->getMessage());
        }
        redirecionar('/produtos/nota10');
    }

    /** Liga todos os sugeridos pelo código */
    public function juntarPorCodigo(): void
    {
        $n = S::juntarPorCodigo(Auth::id());
        Sessao::aviso($n ? 'sucesso' : 'aviso', $n ? "$n produto(s) ligado(s) pelo código." : 'Nenhum produto do Nota10 tem o mesmo código de um produto do One. Preencha os códigos no Catálogo.');
        redirecionar('/produtos/nota10');
    }

    public function desfazer(string $id): void
    {
        try {
            S::desfazer((int) $id, Auth::id());
            Sessao::aviso('sucesso', 'Ligação desfeita: o histórico voltou para o produto do Nota10.');
        } catch (\RuntimeException $e) {
            Sessao::aviso('erro', $e->getMessage());
        }
        redirecionar('/produtos/nota10#ligados');
    }
}
