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
        $id = (int) Auth::id();
        $this->tela('usuarios/meu_perfil', ['titulo' => 'Meu perfil', 'u' => Auth::usuario(),
            'foco' => \App\Services\Foco::areasDe($id), 'focoFuncoes' => \App\Services\Foco::dasFuncoes($id)]);
    }

    public function salvar(): void
    {
        $id = (int) Auth::id();
        $antes = Banco::um('SELECT apelido, data_nascimento, telefone, whatsapp, areas_foco FROM usuarios WHERE id = ?', [$id]);
        $dados = EquipeService::dadosFicha($_POST, false);
        // "O que aparece para mim": igual ao das funções = segue as funções (NULL)
        if (isset($_POST['foco_enviado'])) {
            $escolha = \App\Services\Foco::lista(implode(',', (array) ($_POST['foco'] ?? [])));
            $dasFuncoes = \App\Services\Foco::dasFuncoes($id);
            sort($escolha);
            sort($dasFuncoes);
            $dados['areas_foco'] = $escolha === $dasFuncoes && $dasFuncoes ? null : implode(',', $escolha);
        }
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
