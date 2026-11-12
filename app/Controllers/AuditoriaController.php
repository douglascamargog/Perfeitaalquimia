<?php
namespace App\Controllers;

use App\Core\Banco;

/**
 * Consulta do log de auditoria (quem, o quê, quando), com filtro e paginação.
 */
class AuditoriaController extends Controller
{
    private const POR_PAGINA = 50;

    public function index(): void
    {
        $entidade = (string) ($_GET['entidade'] ?? '');
        $pagina = max(1, (int) ($_GET['pagina'] ?? 1));

        $where = $entidade !== '' ? 'WHERE a.entidade = ?' : '';
        $params = $entidade !== '' ? [$entidade] : [];

        $total = (int) Banco::valor("SELECT COUNT(*) FROM auditoria a $where", $params);
        $registros = Banco::todos(
            "SELECT a.*, u.nome AS usuario_nome FROM auditoria a LEFT JOIN usuarios u ON u.id = a.usuario_id
             $where ORDER BY a.id DESC LIMIT " . self::POR_PAGINA . ' OFFSET ' . (($pagina - 1) * self::POR_PAGINA),
            $params
        );

        $this->tela('auditoria/index', [
            'titulo'    => 'Auditoria',
            'registros' => $registros,
            'entidades' => array_column(Banco::todos('SELECT DISTINCT entidade FROM auditoria ORDER BY entidade'), 'entidade'),
            'entidade'  => $entidade,
            'pagina'    => $pagina,
            'paginas'   => max(1, (int) ceil($total / self::POR_PAGINA)),
            'total'     => $total,
        ]);
    }
}
