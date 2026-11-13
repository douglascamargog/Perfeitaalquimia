<?php
namespace App\Controllers;

use App\Core\Auditoria;
use App\Core\Auth;
use App\Core\Banco;
use App\Core\Sessao;
use App\Services\EquipeService;

/**
 * Meu perfil: cada pessoa atualiza a própria foto, como prefere ser chamada, aniversário e contatos.
 * Perfil de acesso, funções, admissão e observações só o admin muda (Administração › Usuários).
 */
class MeuPerfilController extends Controller
{
    public function index(): void
    {
        $this->tela('usuarios/meu_perfil', ['titulo' => 'Meu perfil', 'u' => Auth::usuario()]);
    }

    public function salvar(): void
    {
        $id = (int) Auth::id();
        $antes = Banco::um('SELECT apelido, data_nascimento, telefone, whatsapp FROM usuarios WHERE id = ?', [$id]);
        $dados = EquipeService::dadosFicha($_POST, false);
        Banco::atualizar('usuarios', $id, $dados);
        Auditoria::registrar('usuario', $id, 'meu_perfil', $antes, $dados);
        try {
            if (!empty($_POST['remover_foto'])) {
                EquipeService::removerFoto($id);
            }
            EquipeService::salvarFoto($id, $_FILES['foto'] ?? null);
            Sessao::aviso('sucesso', 'Perfil atualizado.');
        } catch (\RuntimeException $e) {
            Sessao::aviso('erro', 'Dados salvos, mas a foto não: ' . $e->getMessage());
        }
        redirecionar('/meu-perfil');
    }
}
