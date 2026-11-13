<?php
namespace App\Controllers;

use App\Core\Auditoria;
use App\Core\Auth;
use App\Core\Banco;
use App\Core\Sessao;
use App\Models\Funcao;
use App\Models\Usuario;

/**
 * Cadastro de usuários: perfil de acesso + funções comerciais.
 */
class UsuarioController extends Controller
{
    public function index(): void
    {
        $this->tela('usuarios/index', ['titulo' => 'Usuários', 'usuarios' => Usuario::listar()]);
    }

    public function novo(): void
    {
        $this->formulario(null);
    }

    public function editar(string $id): void
    {
        $usuario = Usuario::buscar((int) $id);
        if (!$usuario) {
            redirecionar('/usuarios');
        }
        $this->formulario($usuario);
    }

    private function formulario(?array $usuario): void
    {
        $this->tela('usuarios/form', [
            'titulo'        => $usuario ? 'Editar usuário' : 'Novo usuário',
            'usuario'       => $usuario,
            'perfis'        => Usuario::perfis(),
            'funcoes'       => Funcao::listar(true),
            'funcoesMarcadas' => $usuario ? array_column(Usuario::funcoesDe((int) $usuario['id']), 'id') : [],
        ]);
    }

    public function criar(): void
    {
        $dados = $this->dadosDoFormulario();
        $erros = $this->validar($dados, null);
        $erros = array_merge($erros, AuthController::validarSenha((string) ($_POST['senha'] ?? ''), (string) ($_POST['senha_confirmacao'] ?? '')));
        if ($erros) {
            $this->voltarComErros('/usuarios/novo', $erros);
        }

        $dados['senha_hash'] = password_hash((string) $_POST['senha'], PASSWORD_DEFAULT);
        $dados['trocar_senha'] = 1; // a pessoa cria a própria senha no primeiro acesso
        $id = Banco::transacao(function () use ($dados) {
            $id = Banco::inserir('usuarios', $dados);
            Usuario::definirFuncoes($id, (array) ($_POST['funcoes'] ?? []));
            return $id;
        });
        Auditoria::registrar('usuario', $id, 'criar', null, $dados);
        if ($erroFoto = $this->tratarFoto($id)) {
            Sessao::aviso('erro', "Usuário criado, mas a foto não foi salva: $erroFoto");
            redirecionar("/usuarios/$id/editar");
        }
        Sessao::aviso('sucesso', 'Usuário criado. No primeiro acesso ele vai trocar a senha.');
        redirecionar('/usuarios');
    }

    public function atualizar(string $id): void
    {
        $id = (int) $id;
        $antes = Usuario::buscar($id);
        if (!$antes) {
            redirecionar('/usuarios');
        }
        $dados = $this->dadosDoFormulario();
        $erros = $this->validar($dados, $id);
        if ($id === Auth::id() && !$dados['ativo']) {
            $erros[] = 'Você não pode desativar o seu próprio usuário.';
        }

        // Senha nova é opcional na edição (redefinição pelo admin)
        $senha = (string) ($_POST['senha'] ?? '');
        if ($senha !== '') {
            $erros = array_merge($erros, AuthController::validarSenha($senha, (string) ($_POST['senha_confirmacao'] ?? '')));
            $dados['senha_hash'] = password_hash($senha, PASSWORD_DEFAULT);
            $dados['trocar_senha'] = 1;
        }
        if ($erros) {
            $this->voltarComErros("/usuarios/$id/editar", $erros);
        }

        $funcoesAntes = array_column(Usuario::funcoesDe($id), 'nome');
        Banco::transacao(function () use ($id, $dados) {
            Banco::atualizar('usuarios', $id, $dados);
            Usuario::definirFuncoes($id, (array) ($_POST['funcoes'] ?? []));
        });
        $funcoesDepois = array_column(Usuario::funcoesDe($id), 'nome');
        if ($erroFoto = $this->tratarFoto($id)) {
            Sessao::aviso('erro', "Dados salvos, mas a foto não: $erroFoto");
            redirecionar("/usuarios/$id/editar");
        }

        Auditoria::registrar('usuario', $id, 'editar',
            array_intersect_key($antes, $dados) + ['funcoes' => implode(', ', $funcoesAntes)],
            $dados + ['funcoes' => implode(', ', $funcoesDepois)]);
        Sessao::aviso('sucesso', 'Usuário atualizado.');
        redirecionar('/usuarios');
    }

    private function dadosDoFormulario(): array
    {
        $codigo = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $this->campo('codigo_vendedor')));
        return [
            'nome'            => $this->campo('nome'),
            'email'           => mb_strtolower($this->campo('email')),
            'perfil_id'       => (int) $this->campo('perfil_id'),
            'codigo_vendedor' => $codigo !== '' ? $codigo : null,
            'ativo'           => isset($_POST['ativo']) ? 1 : 0,
        ] + \App\Services\EquipeService::dadosFicha($_POST); // ficha do funcionário: apelido, nascimento, admissão, contatos
    }

    /** Foto enviada no formulário (ou "remover foto"). Devolve a mensagem de erro, se houver */
    private function tratarFoto(int $id): ?string
    {
        try {
            if (!empty($_POST['remover_foto'])) {
                \App\Services\EquipeService::removerFoto($id);
            }
            \App\Services\EquipeService::salvarFoto($id, $_FILES['foto'] ?? null);
            return null;
        } catch (\RuntimeException $e) {
            return $e->getMessage();
        }
    }

    private function validar(array $dados, ?int $id): array
    {
        $erros = [];
        if ($dados['nome'] === '') {
            $erros[] = 'Informe o nome.';
        }
        if (!filter_var($dados['email'], FILTER_VALIDATE_EMAIL)) {
            $erros[] = 'E-mail inválido.';
        } elseif (Banco::valor('SELECT 1 FROM usuarios WHERE email = ? AND id <> ?', [$dados['email'], $id ?? 0])) {
            $erros[] = 'Já existe um usuário com este e-mail.';
        }
        if (!Banco::valor('SELECT 1 FROM perfis WHERE id = ?', [$dados['perfil_id']])) {
            $erros[] = 'Escolha o perfil de acesso.';
        }
        if ($dados['codigo_vendedor'] && Banco::valor('SELECT 1 FROM usuarios WHERE codigo_vendedor = ? AND id <> ?', [$dados['codigo_vendedor'], $id ?? 0])) {
            $erros[] = 'Este código de vendedor já está em uso.';
        }
        return $erros;
    }
}
