<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Sessao;
use App\Services\AniversarioService;
use App\Services\PessoaService;
use RuntimeException;

/** Equipe: diretório e perfil "tipo rede social" de cada pessoa; mural de parabéns no aniversário. */
class PessoaController extends Controller
{
    public function index(): void
    {
        $busca = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 60);
        $this->tela('pessoas/index', ['titulo' => 'Equipe', 'busca' => $busca, 'pessoas' => PessoaService::diretorio($busca), 'estilos' => ['pessoas.css']]);
    }

    public function ver(string $id): void
    {
        $p = PessoaService::perfil((int) $id);
        if (!$p) {
            http_response_code(404);
            $this->tela('erros/erro', ['titulo' => 'Pessoa', 'codigo' => 404, 'mensagem' => 'Pessoa não encontrada.']);
            return;
        }
        $hoje = AniversarioService::eHoje($p);
        $this->tela('pessoas/ver', ['titulo' => $p['nome'], 'p' => $p, 'eu' => (int) $p['id'] === (int) Auth::id(),
            'aniversarioHoje' => $hoje, 'mensagens' => $hoje ? AniversarioService::mensagens((int) $p['id']) : [], 'estilos' => ['pessoas.css']]);
    }

    /** Meu perfil › Meu perfil público */
    public function salvarPerfil(): void
    {
        try {
            PessoaService::salvar((int) Auth::id(), $_POST, $_FILES);
            Sessao::aviso('sucesso', 'Perfil público salvo. Os colegas já podem ver.');
        } catch (RuntimeException $e) {
            Sessao::aviso('erro', $e->getMessage());
        }
        redirecionar('/meu-perfil#perfil-publico');
    }

    public function parabens(string $id): void
    {
        try {
            AniversarioService::escrever((int) $id, (int) Auth::id(), (string) ($_POST['texto'] ?? ''));
            Sessao::aviso('sucesso', 'Parabéns enviados! 🎉');
        } catch (RuntimeException $e) {
            Sessao::aviso('erro', $e->getMessage());
        }
        redirecionar($this->campo('voltar') === 'perfil' ? '/pessoas/' . (int) $id : '/#mural-aniversario');
    }

    public function apagarParabens(string $id): void
    {
        $voltar = $this->campo('voltar');
        if (AniversarioService::apagar((int) $id, (int) Auth::id())) {
            Sessao::aviso('sucesso', 'Mensagem apagada.');
        } else {
            http_response_code(403);
            Sessao::aviso('erro', 'Só quem escreveu pode apagar a mensagem.');
        }
        redirecionar(preg_match('#^/pessoas/\d+$#', $voltar) ? $voltar : '/#mural-aniversario');
    }
}
