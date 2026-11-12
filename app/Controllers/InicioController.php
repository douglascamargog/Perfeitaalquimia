<?php
namespace App\Controllers;

use App\Core\Auth;

/**
 * Tela inicial. Nesta etapa mostra o mapa dos módulos do protótipo;
 * nas próximas etapas recebe tarefas do dia, alertas de recompra e metas.
 */
class InicioController extends Controller
{
    public function index(): void
    {
        $modulos = array_filter(require RAIZ . '/app/menu.php', fn($m) => $m['url'] !== '/' && self::visivel($m));
        $this->tela('inicio/index', ['titulo' => 'Início', 'usuario' => Auth::usuario(), 'modulos' => $modulos]);
    }

    /** O item do menu aparece para este usuário? */
    public static function visivel(array $item): bool
    {
        foreach ((array) $item['permissao'] as $permissao) {
            if ($permissao === 'logado' || Auth::pode($permissao)) {
                return true;
            }
        }
        return false;
    }
}
