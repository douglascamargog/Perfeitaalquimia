<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Banco;
use App\Core\Sessao;
use App\Models\ClienteEvento;
use App\Models\Tarefa;

/**
 * Agenda de retornos e tarefas: atrasados, hoje e futuros.
 * Vendedor vê as próprias tarefas; gestor vê as de todos (com filtro por pessoa).
 */
class AgendaController extends Controller
{
    public function index(): void
    {
        $verTodas = Auth::pode('carteira.ver_todas');
        $pessoa = $verTodas ? ((int) ($_GET['pessoa'] ?? 0) ?: null) : Auth::id();
        $this->tela('agenda/index', [
            'titulo'     => 'Agenda',
            'grupos'     => Tarefa::agenda($pessoa),
            'verTodas'   => $verTodas,
            'pessoa'     => $pessoa,
            'vendedores' => $verTodas ? \App\Models\Listas::vendedores() : [],
            'estilos'    => ['carteira.css'],
            'scripts'    => ['carteira.js'],
        ]);
    }

    public function concluir(string $id): void
    {
        $tarefa = Banco::um('SELECT * FROM tarefas WHERE id = ? AND concluida_em IS NULL', [(int) $id]);
        if (!$tarefa || (!Auth::pode('carteira.ver_todas') && (int) $tarefa['usuario_id'] !== Auth::id())) {
            Sessao::aviso('erro', 'Tarefa não encontrada.');
            redirecionar('/agenda');
        }
        Banco::executar('UPDATE tarefas SET concluida_em = NOW() WHERE id = ?', [$tarefa['id']]);
        if ($tarefa['cliente_id']) {
            ClienteEvento::registrar((int) $tarefa['cliente_id'], 'tarefa', 'Concluída: ' . $tarefa['titulo'], Auth::id());
        }
        Sessao::aviso('sucesso', 'Tarefa concluída.');
        redirecionar('/agenda');
    }
}
