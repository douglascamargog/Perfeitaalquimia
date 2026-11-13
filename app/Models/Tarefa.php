<?php
namespace App\Models;

use App\Core\Banco;

/**
 * Agenda: próximos contatos e tarefas (tabela tarefas).
 */
class Tarefa
{
    /**
     * Tarefas abertas de um usuário (ou de todos, se $usuarioId = null), separadas em atrasadas, hoje e futuras.
     */
    /** $funcaoId: só tarefas de clientes daquele setor de venda (as tarefas sem cliente continuam) */
    public static function agenda(?int $usuarioId, ?int $funcaoId = null): array
    {
        $sql = 'SELECT t.*, c.razao_social, c.cidade, c.uf, c.telefone, c.whatsapp, u.nome AS usuario_nome
                  FROM tarefas t
                  JOIN usuarios u ON u.id = t.usuario_id
                  LEFT JOIN clientes c ON c.id = t.cliente_id AND c.excluido_em IS NULL
                 WHERE t.concluida_em IS NULL' . ($usuarioId ? ' AND t.usuario_id = ?' : '') . ($funcaoId ? ' AND (t.cliente_id IS NULL OR c.funcao_id = ?)' : '') . '
                 ORDER BY t.vence_em, t.id LIMIT 500';
        $grupos = ['atrasadas' => [], 'hoje' => [], 'futuras' => []];
        $hoje = date('Y-m-d');
        foreach (Banco::todos($sql, array_values(array_filter([$usuarioId, $funcaoId]))) as $t) {
            $grupo = $t['vence_em'] < $hoje ? 'atrasadas' : ($t['vence_em'] === $hoje ? 'hoje' : 'futuras');
            $grupos[$grupo][] = $t;
        }
        return $grupos;
    }

    /** Próximo retorno em aberto do cliente (o "Próximo contato" da ficha) */
    public static function proximoRetorno(int $clienteId): ?array
    {
        return Banco::um(
            "SELECT * FROM tarefas WHERE cliente_id = ? AND concluida_em IS NULL AND tipo = 'retorno' ORDER BY vence_em LIMIT 1",
            [$clienteId]
        );
    }

    /**
     * Define o próximo contato do cliente: atualiza o retorno em aberto ou cria um novo.
     * Data vazia = remove (conclui) o retorno em aberto.
     */
    public static function definirRetorno(int $clienteId, int $usuarioId, ?string $data, string $titulo = 'Retornar contato'): void
    {
        $atual = self::proximoRetorno($clienteId);
        if (!$data) {
            if ($atual) {
                Banco::executar('UPDATE tarefas SET concluida_em = NOW() WHERE id = ?', [$atual['id']]);
            }
            return;
        }
        if ($atual) {
            Banco::atualizar('tarefas', (int) $atual['id'], ['vence_em' => $data, 'usuario_id' => $usuarioId]);
        } else {
            Banco::inserir('tarefas', ['cliente_id' => $clienteId, 'usuario_id' => $usuarioId, 'titulo' => $titulo, 'tipo' => 'retorno', 'vence_em' => $data]);
        }
    }

    /** Quantos retornos pendentes (vencidos ou de hoje) o usuário tem */
    public static function pendentes(?int $usuarioId): int
    {
        return (int) Banco::valor(
            'SELECT COUNT(*) FROM tarefas WHERE concluida_em IS NULL AND vence_em <= CURDATE()' . ($usuarioId ? ' AND usuario_id = ?' : ''),
            $usuarioId ? [$usuarioId] : []
        );
    }
}
