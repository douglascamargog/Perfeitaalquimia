<?php
namespace App\Controllers;

use App\Core\Menu;

/** Administração: página aberta pela engrenagem do topo, com um cartão para cada tela de administração que a pessoa pode abrir. */
class AdministracaoController extends Controller
{
    public function index(): void
    {
        $itens = Menu::administracao();
        if (!$itens) {
            http_response_code(403);
            exit('Sem acesso à Administração.');
        }
        $this->tela('administracao/index', ['titulo' => 'Administração', 'itens' => $itens]);
    }
}
