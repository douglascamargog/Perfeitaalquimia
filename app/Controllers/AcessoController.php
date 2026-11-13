<?php
namespace App\Controllers;

use App\Core\Sessao;
use App\Services\AcessoService;

/**
 * Administração › Acessos: quem pode VER e quem pode EDITAR cada aba, por perfil, e as ações especiais.
 * As exceções de uma pessoa ficam no cadastro dela (Administração › Usuários).
 */
class AcessoController extends Controller
{
    public function index(): void
    {
        $this->tela('acessos/index', ['titulo' => 'Acessos', 'def' => AcessoService::definicao(), 'perfis' => AcessoService::perfis(),
            'permissoes' => AcessoService::permissoesPorPerfil(), 'estilos' => ['carteira.css', 'painel.css']]);
    }

    public function salvar(): void
    {
        $n = AcessoService::salvarGrade((array) ($_POST['nivel'] ?? []), (array) ($_POST['especial'] ?? []));
        Sessao::aviso('sucesso', $n ? 'Acessos salvos. Vale a partir da próxima tela que cada pessoa abrir.' : 'Nenhum acesso mudou.');
        redirecionar('/acessos');
    }
}
