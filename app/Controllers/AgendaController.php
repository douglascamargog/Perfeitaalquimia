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
        // Visão: Semana (padrão) ou Lista. Semana começa na segunda (?semana=AAAA-MM-DD)
        $ver = ($_GET['ver'] ?? '') === 'lista' ? 'lista' : 'semana';
        $ref = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_GET['semana'] ?? '')) ? $_GET['semana'] : date('Y-m-d');
        $segunda = date('Y-m-d', strtotime('monday this week', strtotime($ref)));
        $ids = is_array($quem) ? $quem : ($quem ? [(int) $quem] : null);
        $feitasHoje = (int) Banco::valor('SELECT COUNT(*) FROM tarefas WHERE DATE(concluida_em) = CURDATE()'
            . ($ids !== null ? ' AND usuario_id IN (' . implode(',', array_map('intval', $ids ?: [0])) . ')' : ''));
        $this->tela('agenda/index', [
            'titulo'     => 'Agenda',
            'ver'        => $ver, 'segunda' => $segunda, 'feitasHoje' => $feitasHoje,
            'grupos'     => Tarefa::agenda($quem, $setor),
            'verTodas'   => $verTodas || $equipe,
            'pessoa'     => $pessoa,
            'vendedores' => $verTodas ? \App\Models\Listas::vendedores($setor) : $equipe,
            'estilos'    => ['carteira.css', 'agenda.css'],
            'scripts'    => ['carteira.js', 'busca_contatos.js'],
        ]);
    }

    /** Muda o dia da tarefa (hoje, amanhã ou a data escolhida). Mesmas regras do Concluir. */
    public function reagendar(string $id): void
    {
        $tarefa = Banco::um('SELECT * FROM tarefas WHERE id = ? AND concluida_em IS NULL', [(int) $id]);
        $doSetor = $tarefa && $tarefa['cliente_id'] && in_array((int) Banco::valor('SELECT funcao_id FROM clientes WHERE id = ?', [$tarefa['cliente_id']]), \App\Services\Setores::gerenciados(), true);
        if (!$tarefa || (!Auth::pode('carteira.ver_todas') && (int) $tarefa['usuario_id'] !== Auth::id() && !$doSetor)) {
            Sessao::aviso('erro', 'Tarefa não encontrada.');
            redirecionar('/agenda');
        }
        $data = match ($_POST['para'] ?? '') {
            'hoje' => date('Y-m-d'),
            'amanha' => date('Y-m-d', strtotime('+1 day')),
            default => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_POST['data'] ?? '')) ? $_POST['data'] : null,
        };
        if (!$data || $data < date('Y-m-d')) {
            Sessao::aviso('erro', 'Escolha hoje ou uma data futura.');
        } else {
            Banco::executar('UPDATE tarefas SET vence_em = ? WHERE id = ?', [$data, $tarefa['id']]);
            if ($tarefa['cliente_id']) {
                ClienteEvento::registrar((int) $tarefa['cliente_id'], 'tarefa', 'Reagendada para ' . data_br($data) . ': ' . $tarefa['titulo'], Auth::id());
            }
            \App\Core\Auditoria::registrar('tarefa', (int) $tarefa['id'], 'reagendar', ['vence_em' => $tarefa['vence_em']], ['vence_em' => $data]);
            Sessao::aviso('sucesso', 'Tarefa reagendada para ' . data_br($data) . '.');
        }
        $voltar = (string) ($_POST['voltar'] ?? '');
        redirecionar(str_starts_with($voltar, '/agenda') ? $voltar : '/agenda');
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
        // One comemora quando a pessoa zera a agenda de hoje (nada atrasado nem para hoje)
        if ((int) $tarefa['usuario_id'] === (int) Auth::id() && !Banco::valor('SELECT 1 FROM tarefas WHERE usuario_id = ? AND concluida_em IS NULL AND vence_em <= CURDATE() LIMIT 1', [Auth::id()])) {
            \App\Services\OneTela::festejar('Agenda do dia zerada! ✅', 'Nenhum retorno atrasado nem para hoje. Que tal adiantar a Recompra?');
        }
        $voltar = (string) ($_POST['voltar'] ?? '');
        redirecionar(preg_match('#^/carteira/clientes/\d+$#', $voltar) || str_starts_with($voltar, '/agenda') ? $voltar : '/agenda');
    }
}
