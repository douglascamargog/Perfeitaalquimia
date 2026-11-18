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

    // ------------------------------------------------------------------ Tray (loja virtual)

    /** Endereço de retorno da instalação do aplicativo (cadastrado no painel de parceiro da Tray) */
    public static function urlRetornoTray(): string
    {
        $https = ($_SERVER['HTTPS'] ?? '') === 'on' || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
        $host = preg_replace('/[^A-Za-z0-9.\-:]/', '', (string) ($_SERVER['HTTP_HOST'] ?? 'localhost'));
        return ($https ? 'https' : 'http') . '://' . $host . url('/tray/retorno');
    }

    /** Valida o endereço da API da loja: https://.../web_api (ou http só em teste local) */
    private static function apiTrayValida(string $api): ?string
    {
        $api = rtrim(trim($api), '/');
        if ($api !== '' && !preg_match('#^https?://#i', $api)) {
            $api = 'https://' . $api;
        }
        return preg_match('#^https?://[A-Za-z0-9.\-]+(:\d+)?(/[A-Za-z0-9_\-./]*)?$#', $api) ? $api : null;
    }

    /** Guardar as chaves da Tray (config/segredos.php) e escolher de onde vêm os números do e-commerce */
    public function tray(): void
    {
        $gravar = fn(string $campo, string $valor) => \App\Services\ChaveIA::gravar(['integracoes', 'tray', $campo], $valor);
        $erro = null;
        if (!empty($_POST['remover'])) {
            foreach (['api', 'consumer_key', 'consumer_secret', 'code'] as $c) {
                $erro ??= $gravar($c, '');
            }
            array_map('unlink', glob(RAIZ . '/storage/cache/tray_token_*.json') ?: []);
            \App\Core\Config::definir('ecommerce.provedor', 'fake');
            $msg = 'Dados da Tray removidos. O e-commerce volta aos números de demonstração.';
        } else {
            $api = trim((string) ($_POST['api'] ?? ''));
            if ($api !== '') {
                $valida = self::apiTrayValida($api);
                $erro = $valida ? $gravar('api', $valida) : 'O endereço da API não parece certo. É o endereço da loja seguido de /web_api (ex.: https://www.sualoja.com.br/web_api).';
            }
            foreach (['consumer_key', 'consumer_secret', 'code'] as $c) {
                $v = trim((string) ($_POST[$c] ?? ''));
                if (!$erro && $v !== '') {
                    $erro = preg_match('/^[A-Za-z0-9_\-.]{8,200}$/', $v) ? $gravar($c, $v) : 'O campo "' . str_replace('_', ' ', $c) . '" tem caracteres estranhos: copie de novo, sem espaços.';
                }
            }
            if (!$erro && in_array($_POST['provedor'] ?? '', ['fake', 'tray'], true)) {
                \App\Core\Config::definir('ecommerce.provedor', $_POST['provedor']);
            }
            $msg = 'Tray salva. Agora clique em "Testar conexão".';
        }
        if (!$erro) {
            \App\Core\Auditoria::registrar('configuracao', null, 'tray', null, ['provedor' => $_POST['provedor'] ?? null, 'chaves_trocadas' => array_values(array_filter(['consumer_key', 'consumer_secret', 'code', 'api'], fn($c) => trim((string) ($_POST[$c] ?? '')) !== ''))]);
        }
        \App\Core\Sessao::aviso($erro ? 'erro' : 'sucesso', $erro ?? $msg);
        redirecionar('/administracao#tray');
    }

    /** Testar a conexão com a loja (gera o Access Token na primeira vez) */
    public function testarTray(): void
    {
        $cliente = new \App\Integrations\Tray\TrayClient((string) config('integracoes.tray.api', ''), (string) config('integracoes.tray.consumer_key', ''),
            (string) config('integracoes.tray.consumer_secret', ''), (string) config('integracoes.tray.code', ''));
        @set_time_limit(60);
        [$ok, $mensagem] = $cliente->testarConexao();
        \App\Core\Sessao::aviso($ok ? 'sucesso' : 'erro', 'Tray: ' . $mensagem);
        redirecionar('/administracao#tray');
    }

    /**
     * Retorno da instalação: a Tray manda o navegador de quem instalou o aplicativo para cá, com o "code" e o
     * "api_address" da loja. Só quem administra o One pode gravar; sem login, guarda na sessão e pede para entrar.
     */
    public function retornoTray(): void
    {
        $code = (string) ($_GET['code'] ?? '');
        $api = self::apiTrayValida((string) ($_GET['api_address'] ?? ''));
        if ($code !== '' && preg_match('/^[A-Za-z0-9_\-.]{8,200}$/', $code) && $api) {
            \App\Core\Sessao::set('tray_instalacao', ['code' => $code, 'api' => $api]);
            \App\Core\Sessao::remover('tray_loja');
        } elseif ($code === '') {
            // 1ª etapa: logo depois de instalar, a Tray manda só o endereço da loja → falta "Autorizar"
            $loja = self::apiTrayValida((string) ($_GET['url'] ?? $_GET['store'] ?? $_GET['store_host'] ?? $_GET['store_url'] ?? ''));
            if ($loja) {
                \App\Core\Sessao::set('tray_loja', preg_replace('#/web_api$#', '', $loja));
            }
        }
        $pendente = \App\Core\Sessao::get('tray_instalacao');
        if (!\App\Core\Auth::logado()) {
            \App\Core\Sessao::set('voltar_para', '/tray/retorno');
            \App\Core\Sessao::aviso('aviso', 'Entre com o login de administrador do One para concluir a ligação com a loja Tray.');
            redirecionar('/entrar');
        }
        if (!\App\Core\Auth::pode('config.gerenciar')) {
            \App\Core\Sessao::aviso('erro', 'Só quem administra o One pode ligar a loja Tray. Peça ao administrador para instalar o aplicativo.');
            redirecionar('/');
        }
        $loja = \App\Core\Sessao::get('tray_loja');
        if (!$pendente && $loja) {
            $chave = (string) config('integracoes.tray.consumer_key', '');
            if ($chave === '') {
                \App\Core\Sessao::aviso('erro', 'Antes de autorizar, cole o consumer key e o consumer secret da Tray aqui e clique em Guardar. Depois instale o aplicativo de novo.');
                redirecionar('/administracao#tray');
            }
            // 2ª etapa: botão "Autorizar" → a loja confirma e volta para cá com o code e o api_address
            $autorizar = $loja . '/auth.php?' . http_build_query(['response_type' => 'code', 'consumer_key' => $chave, 'callback' => self::urlRetornoTray()]);
            $this->tela('administracao/tray_autorizar', ['titulo' => 'Autorizar a loja Tray', 'loja' => $loja, 'autorizar' => $autorizar]);
            return;
        }
        if (!$pendente) {
            \App\Core\Sessao::aviso('erro', 'A Tray não mandou o código de instalação. Instale o aplicativo de novo no painel da loja.');
            redirecionar('/administracao#tray');
        }
        \App\Core\Sessao::remover('tray_instalacao');
        $erro = \App\Services\ChaveIA::gravar(['integracoes', 'tray', 'api'], $pendente['api'])
            ?? \App\Services\ChaveIA::gravar(['integracoes', 'tray', 'code'], $pendente['code']);
        if (!$erro) {
            \App\Core\Auditoria::registrar('configuracao', null, 'tray_instalada', null, ['api' => $pendente['api']]);
        }
        \App\Core\Sessao::aviso($erro ? 'erro' : 'sucesso', $erro ?? 'Loja Tray conectada (' . parse_url($pendente['api'], PHP_URL_HOST) . '). Confira o consumer key e o consumer secret e clique em "Testar conexão".');
        redirecionar('/administracao#tray');
    }

    /** "Conectar à loja": leva para a tela de autorização da própria loja Tray (etapa 1 do OAuth da Tray) */
    public function conectarTray(): void
    {
        $loja = self::apiTrayValida((string) ($_POST['loja'] ?? ''));
        $chave = (string) config('integracoes.tray.consumer_key', '');
        if (!$loja || $chave === '' || (string) config('integracoes.tray.consumer_secret', '') === '') {
            \App\Core\Sessao::aviso('erro', !$loja ? 'Digite o endereço da loja (ex.: https://sualoja.commercesuite.com.br).' : 'Guarde antes o consumer key e o consumer secret.');
            redirecionar('/administracao#tray');
        }
        $loja = preg_replace('#/(web_api|admin.*|painel.*)$#', '', $loja);
        header('Location: ' . $loja . '/auth.php?' . http_build_query(['response_type' => 'code', 'consumer_key' => $chave, 'callback' => self::urlRetornoTray()]));
        exit;
    }

    // ------------------------------------------------------------------ Google Maps (localização das lojas)

    /** Guardar ou remover a chave do Google Maps */
    public function googleMaps(): void
    {
        $chave = trim((string) ($_POST['chave'] ?? ''));
        $erro = null;
        if (!empty($_POST['remover'])) {
            $erro = \App\Services\ChaveIA::gravar(['integracoes', 'google', 'maps_chave'], '');
            $msg = 'Chave do Google Maps removida. Os pontos que já foram localizados continuam no mapa.';
        } elseif (!preg_match('/^[A-Za-z0-9_\-]{20,100}$/', $chave)) {
            $erro = 'A chave do Google Maps não parece certa: ela começa com "AIza" e não tem espaços.';
        } else {
            $erro = \App\Services\ChaveIA::gravar(['integracoes', 'google', 'maps_chave'], $chave);
            $msg = 'Chave guardada. Clique em "Testar" e depois em "Localizar agora".';
        }
        if (!$erro) {
            \App\Core\Auditoria::registrar('configuracao', null, 'google_maps', null, ['chave_trocada' => empty($_POST['remover']), 'removida' => !empty($_POST['remover'])]);
        }
        \App\Core\Sessao::aviso($erro ? 'erro' : 'sucesso', $erro ?? $msg);
        redirecionar('/administracao#google-maps');
    }

    /** Geoapify (mapas sem pré-pagamento): guardar ou remover a chave */
    public function geoapify(): void
    {
        $chave = trim((string) ($_POST['chave'] ?? ''));
        if (!empty($_POST['remover'])) {
            $erro = \App\Services\ChaveIA::gravar(['integracoes', 'geoapify', 'chave'], '');
            $msg = 'Chave da Geoapify removida. Os pontos que já foram localizados continuam no mapa.';
        } elseif (!preg_match('/^[A-Za-z0-9]{20,64}$/', $chave)) {
            $erro = 'A chave da Geoapify não parece certa: são só letras e números, sem espaços (geoapify.com › My Projects › API Keys).';
        } else {
            $erro = \App\Services\ChaveIA::gravar(['integracoes', 'geoapify', 'chave'], $chave);
            $msg = 'Chave da Geoapify guardada. Clique em "Testar" e depois em "Localizar agora".';
        }
        if (!$erro) {
            \App\Core\Auditoria::registrar('configuracao', null, 'geoapify', null, ['chave_trocada' => empty($_POST['remover']), 'removida' => !empty($_POST['remover'])]);
        }
        \App\Core\Sessao::aviso($erro ? 'erro' : 'sucesso', $erro ?? $msg);
        redirecionar('/administracao#google-maps');
    }

    /** Qual serviço de mapas o One usa (localizar lojas e rotas): Geoapify ou Google */
    public function mapasProvedor(): void
    {
        $novo = ($_POST['provedor'] ?? '') === 'google' ? 'google' : 'geoapify';
        \App\Core\Config::definir('mapas.provedor', $novo);
        \App\Core\Auditoria::registrar('configuracao', null, 'mapas_provedor', null, ['provedor' => $novo]);
        \App\Core\Sessao::aviso('sucesso', 'Mapas: agora o One usa ' . ($novo === 'google' ? 'o Google Maps' : 'a Geoapify') . '.');
        redirecionar('/administracao#google-maps');
    }

    public function testarGoogleMaps(): void
    {
        [$ok, $mensagem] = \App\Services\LocalizacaoService::testar();
        \App\Core\Sessao::aviso($ok ? 'sucesso' : 'erro', \App\Services\LocalizacaoService::nomeProvedor() . ': ' . $mensagem);
        redirecionar('/administracao#google-maps');
    }

    /** Localiza uma leva de lojas agora (sem esperar o cron) */
    public function localizarLojas(): void
    {
        @set_time_limit(300);
        $r = \App\Services\LocalizacaoService::rodada(max(10, (int) \App\Core\Config::get('carteira.geo_por_rodada', 200)));
        \App\Core\Sessao::aviso($r['erro'] && !$r['achadas'] ? 'erro' : 'sucesso', "Mapa: {$r['achadas']} loja(s) localizada(s) no endereço"
            . ($r['nao_achadas'] ? ", {$r['nao_achadas']} sem endereço preciso (continuam aproximadas na cidade)" : '') . '.' . ($r['erro'] ? ' ' . $r['erro'] : ''));
        redirecionar('/administracao#google-maps');
    }

    // ------------------------------------------------------------------ Especialistas do One (V15.0)

    public function especialistas(): void
    {
        $this->tela('administracao/especialistas', ['titulo' => 'Especialistas do One',
            'lista' => \App\Core\Banco::todos('SELECT * FROM one_especialistas ORDER BY ordem, nome'),
            'funcoes' => \App\Core\Banco::todos('SELECT slug, nome FROM funcoes WHERE ativa = 1 ORDER BY nome'),
            'uso' => \App\Services\OneEspecialistas::usoDoMes(), 'comIa' => \App\Services\OnePergunta::comIa()]);
    }

    public function salvarEspecialista(string $id): void
    {
        $e = \App\Core\Banco::um('SELECT * FROM one_especialistas WHERE id = ?', [(int) $id]);
        if (!$e) {
            redirecionar('/administracao/especialistas');
        }
        $funcoes = array_values(array_intersect((array) ($_POST['funcoes'] ?? []), array_column(\App\Core\Banco::todos('SELECT slug FROM funcoes'), 'slug')));
        $dados = [
            'nome' => mb_substr(trim((string) ($_POST['nome'] ?? '')) ?: $e['nome'], 0, 60),
            'descricao' => mb_substr(trim((string) ($_POST['descricao'] ?? '')), 0, 200),
            'instrucoes' => mb_substr(trim((string) ($_POST['instrucoes'] ?? '')), 0, 8000),
            'sugestoes' => mb_substr(implode('|', array_filter(array_map('trim', preg_split('/\R/', (string) ($_POST['sugestoes'] ?? ''))))), 0, 600),
            'funcoes' => implode(',', $funcoes),
            'ativo' => $e['slug'] === 'geral' ? 1 : (int) !empty($_POST['ativo']),
            'editado_em' => date('Y-m-d H:i:s'),
        ];
        \App\Core\Banco::atualizar('one_especialistas', (int) $id, $dados);
        \App\Core\Auditoria::registrar('configuracao', (int) $id, 'one_especialista', ['instrucoes' => $e['instrucoes'], 'ativo' => $e['ativo']], ['slug' => $e['slug']] + array_diff_key($dados, ['editado_em' => 1]));
        \App\Core\Sessao::aviso('sucesso', 'Especialista "' . $dados['nome'] . '" salvo.');
        redirecionar('/administracao/especialistas#esp-' . $e['slug']);
    }
}
