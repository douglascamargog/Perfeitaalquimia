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

    /** "Testar a IA agora" (Pergunte ao One): uma pergunta curtinha com a chave do config.php */
    public function testarIa(): void
    {
        $ia = new \App\Integrations\OneIA\OneIAClaude((string) config('integracoes.anthropic.chave', ''),
            (string) config('integracoes.anthropic.modelo', 'claude-opus-5-5'), config('integracoes.anthropic.endereco') ?: null);
        [$ok, $mensagem] = $ia->testar();
        \App\Core\Sessao::aviso($ok ? 'sucesso' : 'erro', $mensagem);
        redirecionar('/administracao');
    }

    /** Cadastrar ou remover a chave da IA (Anthropic) pela tela */
    public function chaveIa(): void
    {
        $remover = !empty($_POST['remover']);
        $erro = \App\Services\ChaveIA::salvar($remover ? '' : (string) ($_POST['chave'] ?? ''), (int) \App\Core\Auth::id());
        \App\Core\Sessao::aviso($erro ? 'erro' : 'sucesso', $erro ?? ($remover ? 'Chave removida. O One volta a responder sem IA.' : 'Chave guardada com segurança. Agora clique em "Testar a IA agora".'));
        redirecionar('/administracao#chave-ia');
    }
}
