<?php
namespace App\Controllers;

use App\Core\Auditoria;
use App\Core\Auth;
use App\Core\Banco;
use App\Core\Sessao;

/**
 * Entrar, sair e trocar a própria senha.
 */
class AuthController extends Controller
{
    public function formulario(): void
    {
        if (Auth::logado()) {
            redirecionar('/');
        }
        $this->tela('auth/entrar', ['titulo' => 'Entrar'], 'simples');
    }

    public function entrar(): void
    {
        $erro = Auth::entrar($this->campo('email'), (string) ($_POST['senha'] ?? ''));
        if ($erro) {
            $this->voltarComErros('/entrar', [$erro]);
        }
        // Senha provisória? Obriga a trocar antes de continuar
        if (Auth::usuario()['trocar_senha']) {
            Sessao::aviso('aviso', 'Crie uma senha nova para continuar.');
            redirecionar('/minha-senha');
        }
        // Volta para onde a pessoa estava indo (só caminhos internos)
        $voltar = (string) Sessao::get('voltar_para', '/');
        Sessao::remover('voltar_para');
        redirecionar(preg_match('#^/[a-z0-9/_-]*$#i', $voltar) && $voltar !== '/entrar' ? $voltar : '/');
    }

    public function sair(): void
    {
        Auth::sair();
        redirecionar('/entrar');
    }

    public function formSenha(): void
    {
        $this->tela('auth/senha', ['titulo' => 'Minha senha']);
    }

    public function trocarSenha(): void
    {
        $usuario = Banco::um('SELECT id, senha_hash FROM usuarios WHERE id = ?', [Auth::id()]);
        $nova = (string) ($_POST['senha'] ?? '');

        $erros = [];
        if (!password_verify((string) ($_POST['senha_atual'] ?? ''), $usuario['senha_hash'])) {
            $erros[] = 'A senha atual não confere.';
        }
        $erros = array_merge($erros, self::validarSenha($nova, (string) ($_POST['senha_confirmacao'] ?? '')));
        if ($erros) {
            $this->voltarComErros('/minha-senha', $erros);
        }

        Banco::atualizar('usuarios', Auth::id(), ['senha_hash' => password_hash($nova, PASSWORD_DEFAULT), 'trocar_senha' => 0]);
        Auditoria::registrar('usuario', Auth::id(), 'trocar_senha');
        Sessao::aviso('sucesso', 'Senha alterada.');
        redirecionar('/');
    }

    /** Regra de senha forte, usada também no cadastro de usuários */
    public static function validarSenha(string $senha, string $confirmacao): array
    {
        $erros = [];
        if (mb_strlen($senha) < 8 || !preg_match('/[A-Za-z]/', $senha) || !preg_match('/\d/', $senha)) {
            $erros[] = 'A senha precisa de pelo menos 8 caracteres, com letras e números.';
        }
        if ($senha !== $confirmacao) {
            $erros[] = 'A confirmação não é igual à senha.';
        }
        return $erros;
    }
}
