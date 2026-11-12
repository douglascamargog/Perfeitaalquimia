<?php
namespace App\Core;

/**
 * Monta as telas: junta o template da página (app/Views/<pasta>/<arquivo>.php)
 * com o layout (menu, topo, faixa de demonstração).
 *
 *     Visao::mostrar('usuarios/index', ['usuarios' => $lista, 'titulo' => 'Usuários']);
 */
class Visao
{
    public static function mostrar(string $pagina, array $dados = [], ?string $layout = 'base'): void
    {
        $conteudo = self::renderizar($pagina, $dados);
        if ($layout === null) {
            echo $conteudo;
            return;
        }
        echo self::renderizar('layout/' . $layout, $dados + ['conteudo' => $conteudo]);
    }

    /** Executa um template e devolve o HTML gerado como texto */
    public static function renderizar(string $template, array $dados = []): string
    {
        $arquivo = RAIZ . '/app/Views/' . $template . '.php';
        if (!is_file($arquivo)) {
            throw new \RuntimeException("Template não encontrado: $template");
        }
        extract($dados, EXTR_SKIP);
        ob_start();
        require $arquivo;
        return (string) ob_get_clean();
    }
}
