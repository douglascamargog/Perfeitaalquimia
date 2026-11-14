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
        $setor = \App\Services\Setores::funcaoIdAtual();
        $equipe = !$verTodas && \App\Services\Setores::gerenciaAtual() ? \App\Services\Setores::equipeGerenciada() : [];
        if ($verTodas) {
            $pessoa = (int) ($_GET['pessoa'] ?? 0) ?: null;
            $quem = $pessoa;
        } elseif ($equipe) {
            // Gestor(a) do setor: a própria agenda ou a de alguém da equipe do setor ("Todas" = a equipe toda, só clientes do setor)
            $ids = array_map('intval', array_column($equipe, 'id'));
            $pessoa = in_array((int) ($_GET['pessoa'] ?? 0), $ids, true) ? (int) $_GET['pessoa'] : null;
            $quem = $pessoa ?? $ids;
            $setor ??= \App\Services\Setores::gerenciados()[0];
        } else {
            $pessoa = $quem = Auth::id();
        }
        $this->tela('agenda/index', [
            'titulo'     => 'Agenda',
            'grupos'     => Tarefa::agenda($quem, $setor),
            'verTodas'   => $verTodas || $equipe,
            'pessoa'     => $pessoa,
            'vendedores' => $verTodas ? \App\Models\Listas::vendedores() : $equipe,
            'estilos'    => ['carteira.css'],
            'scripts'    => ['carteira.js', 'busca_contatos.js'],
        ]);
    }

    public function concluir(string $id): void
    {
        $tarefa = Banco::um('SELECT * FROM tarefas WHERE id = ? AND concluida_em IS NULL', [(int) $id]);
        $doSetor = $tarefa && $tarefa['cliente_id'] && in_array((int) Banco::valor('SELECT funcao_id FROM clientes WHERE id = ?', [$tarefa['cliente_id']]), \App\Services\Setores::gerenciados(), true);
        if (!$tarefa || (!Auth::pode('carteira.ver_todas') && (int) $tarefa['usuario_id'] !== Auth::id() && !$doSetor)) {
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
