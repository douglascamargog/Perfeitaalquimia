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

            if ($metodo === 'POST' && !Csrf::valido() && self::querJson()) {
                http_response_code(419);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => false, 'login' => true, 'mensagem' => 'A sessão mudou. Abra o app de novo com internet.']);
                return;
            }
            if ($metodo === 'POST' && !Csrf::valido()) {
                $this->erro(419, 'A página expirou. Volte, recarregue e tente de novo.');
                return;
            }
            if (!$this->autorizado($permissao)) {
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
        if ($permissao === 'publico') {
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
            $this->erro(403, 'Seu perfil não tem acesso a esta área.');
            return false;
        }
        return true;
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
