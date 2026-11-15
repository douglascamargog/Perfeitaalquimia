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

    /** Ligue Talk: guardar código da empresa e token (config/segredos.php) e escolher o modo */
    public function ligueTalk(): void
    {
        $empresa = preg_replace('/[^A-Za-z0-9_\-]/', '', (string) ($_POST['company_id'] ?? ''));
        $token = trim((string) ($_POST['token'] ?? ''));
        $erro = null;
        if (!empty($_POST['remover'])) {
            $erro = \App\Services\ChaveIA::gravar(['integracoes', 'ligue_talk', 'token'], '') ?? \App\Services\ChaveIA::gravar(['integracoes', 'ligue_talk', 'company_id'], '');
            \App\Core\Config::definir('telefonia.modo', 'manual');
        } else {
            if ($empresa !== '') {
                $erro = \App\Services\ChaveIA::gravar(['integracoes', 'ligue_talk', 'company_id'], $empresa);
            }
            if (!$erro && $token !== '') {
                $erro = strlen($token) < 8 ? 'Esse token parece curto demais. Copie de novo o token da API que o Ligue Talk enviou.' : \App\Services\ChaveIA::gravar(['integracoes', 'ligue_talk', 'token'], $token);
            }
            if (!$erro && in_array($_POST['modo'] ?? '', ['manual', 'simulado', 'api'], true)) {
                \App\Core\Config::definir('telefonia.modo', $_POST['modo']);
            }
        }
        if (!$erro) {
            \App\Core\Auditoria::registrar('configuracao', null, 'ligue_talk', null, ['company_id' => $empresa ?: null, 'token_trocado' => $token !== '', 'modo' => $_POST['modo'] ?? null]);
        }
        \App\Core\Sessao::aviso($erro ? 'erro' : 'sucesso', $erro ?? (!empty($_POST['remover']) ? 'Dados do Ligue Talk removidos. As ligações voltam ao registro manual.' : 'Ligue Talk salvo. Agora clique em "Testar conexão".'));
        redirecionar('/administracao#ligue-talk');
    }

    /** Ligue Talk: testar a conexão (lista os usuários e mostra quais já estão ligados a pessoas do One) */
    public function testarLigueTalk(): void
    {
        $cliente = new \App\Integrations\LigueTalk\LigueTalkClient(
            (string) config('integracoes.ligue_talk.url', 'https://sistema.liguetalk.com.br/index.php/API_control'),
            (string) config('integracoes.ligue_talk.company_id', ''), (string) config('integracoes.ligue_talk.token', ''));
        [$ok, $mensagem, $usuarios] = $cliente->testarConexao();
        \App\Core\Sessao::aviso($ok ? 'sucesso' : 'erro', $mensagem);
        \App\Core\Sessao::set('ligue_usuarios', $ok ? array_slice($usuarios, 0, 100) : null);
        redirecionar('/administracao#ligue-talk');
    }
}
