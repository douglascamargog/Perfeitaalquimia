<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Sessao;
use App\Services\Desenvolvedor;

/** Desenvolvedor do sistema: seletor "ver o One como" e quem é desenvolvedor (Administração) */
class DesenvolvedorController extends Controller
{
    /** Seletor do topo: "" = modo desenvolvedor (tudo); id = ver o One como aquela função */
    public function modo(): void
    {
        try {
            $id = (int) $this->campo('funcao');
            Desenvolvedor::verComo($id ?: null);
            Sessao::aviso('sucesso', $id ? 'Agora você vê o One como essa função. Para voltar, use o seletor no topo.' : 'Modo desenvolvedor: você vê tudo.');
        } catch (\RuntimeException $e) {
            Sessao::aviso('erro', $e->getMessage());
        }
        $volta = (string) ($_POST['voltar'] ?? '/');
        redirecionar(str_starts_with($volta, '/') && !str_starts_with($volta, '//') ? $volta : '/');
    }

    /** Administração › Desenvolvedor do sistema: marcar ou desmarcar */
    public function marcar(): void
    {
        try {
            Desenvolvedor::marcar((int) $this->campo('usuario', (string) Auth::id()), $this->campo('acao') !== 'desmarcar');
            Sessao::aviso('sucesso', 'Desenvolvedor do sistema atualizado.');
        } catch (\RuntimeException $e) {
            Sessao::aviso('erro', $e->getMessage());
        }
        redirecionar('/administracao#desenvolvedor');
    }
}
