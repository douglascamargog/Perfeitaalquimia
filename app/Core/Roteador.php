<?php
namespace App\Core;

/**
 * Roteador: olha o endereço pedido (ex.: /usuarios/5/editar) e chama o controller certo.
 *
 * As rotas ficam em app/rotas.php, uma por linha:
 *     ['GET', '/usuarios/{id}/editar', 'UsuarioController@editar', 'usuarios.gerenciar'],
 *
 * O 4º item é a permissão exigida:
 *   'publico' → qualquer pessoa  ·  'logado' → qualquer usuário logado  ·  outra → precisa da permissão
 *   'a|b' → basta ter UMA das permissões (ex.: 'carteira.ver_propria|carteira.ver_todas')
 *
 * Todo POST é conferido contra CSRF antes de chegar ao controller.
 */
class Roteador
{
    public function __construct(private array $rotas)
    {
    }

    public function despachar(string $metodo, string $caminho): void
    {
        $caminho = '/' . trim($caminho, '/');

        foreach ($this->rotas as [$metodoRota, $padrao, $destino, $permissao]) {
            if ($metodoRota !== $metodo) {
                continue;
            }
            // Transforma '/usuarios/{id}' numa expressão que captura o {id}
            $regex = '#^' . preg_replace('#\{(\w+)\}#', '(?P<$1>[^/]+)', $padrao) . '$#';
            if (!preg_match($regex, $caminho, $partes)) {
                continue;
            }
            $parametros = array_filter($partes, 'is_string', ARRAY_FILTER_USE_KEY);

            // 'api-publica' = endereço que outros sites chamam (formulário de leads): sem sessão e sem token CSRF,
            // protegido pelas próprias regras (anti-robô e limite por hora) no LeadService
            $apiPublica = $permissao === 'api-publica';
            if ($metodo === 'POST' && !$apiPublica && !Csrf::valido() && self::querJson()) {
                http_response_code(419);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => false, 'login' => true, 'mensagem' => 'A sessão mudou. Abra o app de novo com internet.']);
                return;
            }
            if ($metodo === 'POST' && !$apiPublica && !Csrf::valido()) {
                $this->erro(419, 'A página expirou. Volte, recarregue e tente de novo.');
                return;
            }
            if (!$this->autorizado($permissao)) {
                return;
            }
            // Diretoria (só acompanha e aprova): POST só nas ações liberadas (aprovar, comentar, o próprio perfil...)
            if ($metodo === 'POST' && Auth::logado() && Auth::somenteAcompanha() && !self::liberadoParaDiretoria($padrao, $parametros, $permissao)) {
                $this->erro(403, 'A Diretoria acompanha e aprova. Para mudar isto, peça a quem cuida da área.');
                return;
            }

            [$classe, $acao] = explode('@', $destino);
            $classe = 'App\\Controllers\\' . $classe;
            (new $classe())->$acao(...array_values($parametros));
            return;
        }

        $this->erro(404, 'Página não encontrada.');
    }

    private function autorizado(string $permissao): bool
    {
        if ($permissao === 'publico' || $permissao === 'api-publica') {
            return true;
        }
        if (!Auth::logado()) {
            // Chamada de API (o app pede JSON): responde 401 em vez de mandar para a tela de login
            if (self::querJson()) {
                http_response_code(401);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => false, 'login' => true, 'mensagem' => 'Sessão encerrada. Entre de novo.']);
                exit;
            }
            // Depois de entrar, volta para a página que a pessoa tentou abrir (ex.: o app /app)
            if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
                $caminho = '/' . trim(substr((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), strlen(url_base())), '/');
                Sessao::set('voltar_para', $caminho);
            }
            Sessao::aviso('aviso', 'Entre com seu usuário para continuar.');
            redirecionar('/entrar');
        }
        // Senha provisória: só pode trocar a senha ou sair até criar uma senha própria
        $caminho = '/' . trim(substr(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), strlen(url_base())), '/');
        if (Auth::usuario()['trocar_senha'] && !in_array($caminho, ['/minha-senha', '/sair'], true)) {
            Sessao::aviso('aviso', 'Crie uma senha nova para continuar.');
            redirecionar('/minha-senha');
        }
        $temAlguma = false;
        foreach (explode('|', $permissao) as $p) {
            $temAlguma = $temAlguma || $p === 'logado' || Auth::pode($p);
        }
        if (!$temAlguma) {
            if (self::querJson() || ($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
                $this->erro(403, 'Você pode ver esta tela, mas não alterar. Peça ao administrador (Administração › Acessos).');
                return false;
            }
            // Aba sem acesso: página amigável dizendo qual aba é e a quem pedir
            $caminho = '/' . trim(substr((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), strlen(url_base())), '/');
            $aba = Menu::itemDe($caminho);
            http_response_code(403);
            Visao::mostrar('erros/sem_acesso', ['titulo' => 'Sem acesso', 'aba' => $aba['titulo'] ?? 'esta tela'], 'base');
            return false;
        }
        return true;
    }

    /** POST que a Diretoria pode fazer: aprovar, decidir, comentar, coisas pessoais. Rotas exatas (padrões de app/rotas.php). */
    private const POST_DIRETORIA = [
        '/sair', '/minha-senha', '/meu-perfil', '/meu-perfil/publico', '/meu-perfil/aparencia', '/meu-perfil/status', '/meu-perfil/personalizar',
        '/pessoas/{id}/recados', '/pessoas/recados/{id}/apagar', '/pessoas/{id}/elogios', '/pessoas/elogios/{id}/apagar', '/notificacoes/marcar-todas', '/novidades/vista',
        '/aniversario/{id}/mensagens', '/aniversario/mensagens/{id}/apagar', '/comunicados/{id}/ler', '/academia/progresso',
        '/aprovacao/{id}/aprovar', '/aprovacao/{id}/recusar',
        '/marketing/posts/{id}/aprovar', '/marketing/posts/{id}/ajuste', '/marketing/posts/{id}/comentar', '/marketing/ecommerce/atualizar',
        '/marketing/tarefas', '/marketing/tarefas/{id}/mudar', '/marketing/tarefas/{id}/comentar', // o serviço só deixa aprovar/devolver
    ];
    /** Permissões das telas de VENDA: liberadas para a Diretoria que também vende ("diretoria.vende"), só nos clientes dela */
    private const PERMISSOES_VENDA = ['carteira.ver_propria', 'pedidos.criar', 'meu_mes.usar', 'app.usar', 'mensagens.usar', 'recompra.ver',
        'catalogo.acessos', 'cobranca.enviar', 'contatos.buscar_ia', 'prospeccao.usar'];

    private static function liberadoParaDiretoria(string $padrao, array $parametros, string $permissao): bool
    {
        if (in_array($padrao, self::POST_DIRETORIA, true)) {
            return true;
        }
        // Ações de páginas com várias ações: só as de aprovar/comentar
        if ($padrao === '/compras/{id}/{acao}' && ($parametros['acao'] ?? '') === 'decidir') {
            return true;
        }
        if ($padrao === '/desenvolvimento/{id}/{acao}' && in_array($parametros['acao'] ?? '', ['aprovar', 'comentar'], true)) {
            return true;
        }
        // Diretoria que também vende: telas de venda, mas só nos clientes em que é a responsável
        if (Auth::pode('diretoria.vende') && array_intersect(explode('|', $permissao), self::PERMISSOES_VENDA)) {
            $cliente = self::clienteDaRequisicao($padrao, $parametros);
            return $cliente === null || (int) \App\Core\Banco::valor('SELECT responsavel_id FROM clientes WHERE id = ?', [$cliente]) === Auth::id();
        }
        return false;
    }

    /** Cliente envolvido no POST (pela rota ou pelo formulário), ou null quando não há um cliente específico */
    private static function clienteDaRequisicao(string $padrao, array $p): ?int
    {
        $id = (int) ($p['id'] ?? 0);
        $sql = match (true) {
            str_starts_with($padrao, '/carteira/clientes/{id}'), str_starts_with($padrao, '/clientes/{id}') => null,
            str_starts_with($padrao, '/pedidos/{id}') => 'SELECT cliente_id FROM pedidos WHERE id = ?',
            str_starts_with($padrao, '/orcamentos/{id}') => 'SELECT cliente_id FROM orcamentos WHERE id = ?',
            str_starts_with($padrao, '/agenda/{id}') => 'SELECT cliente_id FROM tarefas WHERE id = ?',
            str_starts_with($padrao, '/reativacao/{id}'), str_starts_with($padrao, '/recompra/{id}') => null,
            default => false,
        };
        if ($sql === null && $id) {
            return $id; // a própria rota é do cliente
        }
        if (is_string($sql) && $id) {
            return (int) \App\Core\Banco::valor($sql, [$id]) ?: -1;
        }
        $doForm = (int) ($_POST['cliente_id'] ?? 0);
        return $doForm ?: null;
    }

    /** A requisição pede JSON (chamadas do app e dos scripts)? */
    public static function querJson(): bool
    {
        return str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json') && !str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'text/html');
    }

    public function erro(int $codigo, string $mensagem): void
    {
        http_response_code($codigo);
        Visao::mostrar('erros/erro', ['titulo' => 'Erro ' . $codigo, 'codigo' => $codigo, 'mensagem' => $mensagem], Auth::logado() ? 'base' : 'simples');
    }
}
