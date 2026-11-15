<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Sessao;
use App\Services\AniversarioService;
use App\Services\PerfilSocialService as Social;
use App\Services\PessoaService;
use RuntimeException;

/** Equipe: diretório e perfil "tipo rede social" de cada pessoa; mural de parabéns no aniversário. */
class PessoaController extends Controller
{
    public function index(): void
    {
        $busca = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 60);
        $this->tela('pessoas/index', ['titulo' => 'Equipe', 'busca' => $busca, 'pessoas' => PessoaService::diretorio($busca),
            'elogios' => Social::totaisElogios(), 'estilos' => ['carteira.css', 'pessoas.css']]);
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
            'aniversarioHoje' => $hoje, 'mensagens' => $hoje ? AniversarioService::mensagens((int) $p['id']) : [],
            'recados' => Social::recados((int) $p['id']), 'elogios' => Social::elogios((int) $p['id']), 'resumoElogios' => Social::resumoElogios((int) $p['id']),
            'conquistas' => Social::conquistas($p), 'tiposElogio' => Social::tiposElogio(), 'blocos' => Social::blocos($p['blocos'] ?? null),
            'estilos' => ['pessoas.css'], 'scripts' => ['pessoas.js']]);
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

    // ------------------------------------------------------------------
    // Perfil pessoal (V11.0): status, personalizar, recados e elogios
    // ------------------------------------------------------------------

    public function status(): void
    {
        $apagar = !empty($_POST['apagar']);
        Social::salvarStatus((int) Auth::id(), $apagar ? '' : (string) ($_POST['status_emoji'] ?? ''), $apagar ? '' : (string) ($_POST['status_texto'] ?? ''));
        Sessao::aviso('sucesso', !empty($_POST['apagar']) ? 'Status apagado.' : 'Status atualizado. Os colegas já veem.');
        redirecionar($this->voltarPerfil());
    }

    public function personalizar(): void
    {
        Social::salvarPersonalizacao((int) Auth::id(), $_POST);
        Sessao::aviso('sucesso', 'Perfil personalizado.');
        redirecionar('/meu-perfil#personalizar');
    }

    public function recado(string $id): void
    {
        try {
            Social::deixarRecado((int) $id, (int) Auth::id(), (string) ($_POST['texto'] ?? ''));
            Sessao::aviso('sucesso', 'Recado deixado! 💬');
        } catch (RuntimeException $e) {
            Sessao::aviso('erro', $e->getMessage());
        }
        redirecionar('/pessoas/' . (int) $id . '#recados');
    }

    public function apagarRecado(string $id): void
    {
        $perfil = (int) \App\Core\Banco::valor('SELECT perfil_id FROM perfil_recados WHERE id = ?', [(int) $id]);
        if (Social::apagarRecado((int) $id, (int) Auth::id())) {
            Sessao::aviso('sucesso', 'Recado removido.');
        } else {
            http_response_code(403);
            Sessao::aviso('erro', 'Só quem escreveu, o dono do perfil ou o administrador pode remover o recado.');
        }
        redirecionar('/pessoas/' . $perfil . '#recados');
    }

    public function elogiar(string $id): void
    {
        try {
            Social::elogiar((int) $id, (int) Auth::id(), (string) ($_POST['tipo'] ?? ''), (string) ($_POST['texto'] ?? ''));
            Sessao::aviso('sucesso', 'Elogio enviado! Ele(a) vai adorar. 💛');
        } catch (RuntimeException $e) {
            Sessao::aviso('erro', $e->getMessage());
        }
        redirecionar('/pessoas/' . (int) $id . '#elogios');
    }

    public function apagarElogio(string $id): void
    {
        $para = (int) \App\Core\Banco::valor('SELECT para_id FROM perfil_elogios WHERE id = ?', [(int) $id]);
        if (Social::apagarElogio((int) $id, (int) Auth::id())) {
            Sessao::aviso('sucesso', 'Elogio removido.');
        } else {
            http_response_code(403);
            Sessao::aviso('erro', 'Só quem deu o elogio ou o administrador pode remover.');
        }
        redirecionar('/pessoas/' . $para . '#elogios');
    }

    private function voltarPerfil(): string
    {
        $v = $this->campo('voltar');
        return preg_match('#^/(pessoas(/\d+)?|meu-perfil)$#', $v) ? $v : '/pessoas/' . (int) Auth::id();
    }
}
