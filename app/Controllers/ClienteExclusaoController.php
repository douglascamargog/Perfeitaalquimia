<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Sessao;
use App\Services\ClienteExclusaoService as X;

/** Excluir, restaurar e juntar lojas repetidas (permissão carteira.excluir: Gestor e Admin) */
class ClienteExclusaoController extends Controller
{
    public function excluir(string $id): void
    {
        try {
            X::excluir((int) $id, (string) ($_POST['motivo'] ?? ''), (int) Auth::id());
        } catch (\RuntimeException $e) {
            Sessao::aviso('erro', $e->getMessage());
            redirecionar("/carteira/clientes/$id/editar");
        }
        Sessao::aviso('sucesso', 'Loja excluída da Carteira. Se precisar, restaure em Carteira › Lojas excluídas.');
        redirecionar('/carteira/lista');
    }

    public function juntar(string $id): void
    {
        $origem = (int) ($_POST['origem_numero'] ?? 0) ?: (int) ($_POST['origem_id'] ?? 0);
        if (!$origem) {
            Sessao::aviso('erro', 'Escolha a loja repetida (na lista de parecidas ou pelo número dela).');
            redirecionar("/carteira/clientes/$id/editar");
        }
        try {
            $n = X::juntar((int) $id, $origem, (int) Auth::id());
        } catch (\RuntimeException $e) {
            Sessao::aviso('erro', $e->getMessage());
            redirecionar("/carteira/clientes/$id/editar");
        }
        Sessao::aviso('sucesso', "Lojas juntadas: $n registro(s) do histórico vieram para esta loja, e a repetida saiu da Carteira.");
        redirecionar("/carteira/clientes/$id");
    }

    public function excluidas(): void
    {
        $this->tela('carteira/excluidas', ['titulo' => 'Lojas excluídas', 'lojas' => X::excluidas(), 'estilos' => ['carteira.css']]);
    }

    public function restaurar(string $id): void
    {
        try {
            X::restaurar((int) $id, (int) Auth::id());
            Sessao::aviso('sucesso', 'Loja restaurada: ela volta para a Carteira de quem era responsável.');
        } catch (\RuntimeException $e) {
            Sessao::aviso('erro', $e->getMessage());
        }
        redirecionar('/carteira/excluidas');
    }
}
