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
            'funcoesGestor' => $usuario ? array_column(array_filter(Usuario::funcoesDe((int) $usuario['id']), fn($f) => $f['gestor']), 'id') : [],
            'acessos'       => \App\Services\AcessoService::definicao(),
            'permPerfis'    => array_map(fn($p) => array_column(Banco::todos('SELECT permissao FROM perfil_permissoes WHERE perfil_id = ?', [$p['id']]), 'permissao'),
                                   array_column(Usuario::perfis(), null, 'id')),
            'excecoes'      => $usuario ? \App\Services\AcessoService::excecoes((int) $usuario['id']) : [],
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
        $dados['versao_vista'] = \App\Services\Versoes::atual(); // quem chega agora não precisa das novidades antigas
        $id = Banco::transacao(function () use ($dados) {
            $id = Banco::inserir('usuarios', $dados);
            Usuario::definirFuncoes($id, (array) ($_POST['funcoes'] ?? []), (array) ($_POST['gestor'] ?? []));
            return $id;
        });
        \App\Services\Foco::salvarEscolha($id, ($_POST['foco_modo'] ?? 'funcoes') !== 'escolher', (array) ($_POST['foco'] ?? []));
        Auditoria::registrar('usuario', $id, 'criar', null, $dados);
        \App\Services\AcessoService::salvarExcecoes($id, (int) $dados['perfil_id'], (array) ($_POST['excecao'] ?? []), (array) ($_POST['excecao_especial'] ?? []));
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

        $nomesFuncoes = fn() => array_map(fn($f) => $f['nome'] . ($f['gestor'] ? ' (gestão)' : ''), Usuario::funcoesDe($id));
        $funcoesAntes = $nomesFuncoes();
        Banco::transacao(function () use ($id, $dados) {
            Banco::atualizar('usuarios', $id, $dados);
            Usuario::definirFuncoes($id, (array) ($_POST['funcoes'] ?? []), (array) ($_POST['gestor'] ?? []));
        });
        $funcoesDepois = $nomesFuncoes();
        $focoAntes = $antes['areas_foco'];
        \App\Services\Foco::salvarEscolha($id, ($_POST['foco_modo'] ?? 'funcoes') !== 'escolher', (array) ($_POST['foco'] ?? []));
        $focoDepois = \App\Core\Banco::valor('SELECT areas_foco FROM usuarios WHERE id = ?', [$id]);
        if ($focoAntes !== $focoDepois) {
            Auditoria::registrar('usuario', $id, 'foco', ['areas_foco' => $focoAntes ?? 'segue as funções'], ['areas_foco' => $focoDepois ?? 'segue as funções']);
        }
        \App\Services\AcessoService::salvarExcecoes($id, (int) $dados['perfil_id'], (array) ($_POST['excecao'] ?? []), (array) ($_POST['excecao_especial'] ?? []));
        if ($erroFoto = $this->tratarFoto($id)) {
            Sessao::aviso('erro', "Dados salvos, mas a foto não: $erroFoto");
            redirecionar("/usuarios/$id/editar");
        }

        Auditoria::registrar('usuario', $id, 'editar',
            array_intersect_key($antes, $dados) + ['funcoes' => implode(', ', $funcoesAntes)],
            $dados + ['funcoes' => implode(', ', $funcoesDepois)]);
        Sessao::aviso('sucesso', 'Usuário atualizado.');
        // Pessoa desativada (saiu da empresa): as carteiras dela ficam SEM vendedor(a) em Gestão › Carteiras
        if ($antes['ativo'] && !$dados['ativo'] && ($n = \App\Services\CarteirasService::liberarDoUsuario($id, Auth::id()))) {
            Sessao::aviso('aviso', "$n cliente(s) ficaram em carteira sem vendedor(a). Passe a carteira para outra pessoa em Gestão › Carteiras.");
        }
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
            // Usuário (ID ou ramal) da pessoa no Ligue Talk: liga as gravações à pessoa certa
            'ramal'           => mb_substr(trim($this->campo('ramal')), 0, 20) ?: null,
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
