<?php
namespace App\Controllers;

use App\Core\Auditoria;
use App\Core\Auth;
use App\Core\Banco;
use App\Core\Sessao;
use App\Services\EquipeService;

/**
 * Meu perfil: cada pessoa atualiza o próprio nome, foto, como prefere ser chamada, aniversário e contatos.
 * E-mail de acesso, perfil, funções, o que aparece no Início, admissão e observações só o admin muda (Administração › Usuários).
 */
class MeuPerfilController extends Controller
{
    public function index(): void
    {
        $id = (int) Auth::id();
        $this->tela('usuarios/meu_perfil', ['titulo' => 'Meu perfil', 'u' => Auth::usuario()]);
    }

    public function salvar(): void
    {
        $id = (int) Auth::id();
        $antes = Banco::um('SELECT nome, apelido, data_nascimento, telefone, whatsapp FROM usuarios WHERE id = ?', [$id]);
        $dados = EquipeService::dadosFicha($_POST, false);
        $nome = trim(preg_replace('/\s+/', ' ', (string) ($_POST['nome'] ?? '')));
        if ($nome === '' || mb_strlen($nome) > 120) {
            Sessao::aviso('erro', 'Informe o seu nome (até 120 letras).');
            redirecionar('/meu-perfil');
        }
        $dados['nome'] = $nome;
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
