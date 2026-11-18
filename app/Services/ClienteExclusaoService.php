<?php
namespace App\Services;

use App\Core\Auditoria;
use App\Core\Banco;
use App\Models\ClienteEvento;
use RuntimeException;

/**
 * Excluir, restaurar e juntar lojas duplicadas (V15.7). Quem pode: permissão "carteira.excluir" (Gestor; o Admin tem tudo).
 *
 * - Excluir: a loja SAI da Carteira, das listas e dos filtros, mas fica guardada (clientes.excluido_em), com o histórico.
 *   Dá para restaurar em Carteira › Lojas excluídas. Loja com pedido em andamento não pode ser excluída.
 * - Juntar duplicadas: tudo da loja repetida (pedidos, linha do tempo, tarefas, cobrança, anexos, roteiros...) passa para a loja
 *   que fica; campos vazios da que fica são completados com os da repetida; a repetida é excluída.
 */
class ClienteExclusaoService
{
    /** Campos que a loja que fica herda da repetida, se estiverem vazios */
    private const COMPLETAR = ['nome_fantasia', 'cnpj', 'endereco', 'bairro', 'cep', 'telefone', 'whatsapp', 'email', 'instagram', 'facebook', 'site',
        'segmento_id', 'origem_id', 'tabela_preco_id', 'mix_produtos'];

    public static function excluir(int $id, string $motivo, int $usuarioId): void
    {
        $c = self::loja($id);
        $motivo = mb_substr(trim($motivo), 0, 300);
        if ($motivo === '') {
            throw new RuntimeException('Conte o motivo (ex.: loja fechou, cadastro repetido, não é cliente).');
        }
        $andamento = (int) Banco::valor("SELECT COUNT(*) FROM pedidos WHERE cliente_id = ? AND situacao IN ('aguardando_aprovacao', 'aprovado')", [$id]);
        if ($andamento) {
            throw new RuntimeException("Esta loja tem $andamento pedido(s) em andamento. Cancele ou espere faturar antes de excluir.");
        }
        Banco::transacao(function () use ($id, $motivo, $usuarioId) {
            Banco::executar('UPDATE clientes SET excluido_em = NOW() WHERE id = ?', [$id]);
            Banco::executar('UPDATE tarefas SET concluida_em = NOW() WHERE cliente_id = ? AND concluida_em IS NULL', [$id]); // some da Agenda
            ClienteEvento::registrar($id, 'cadastro', 'Loja excluída da Carteira. Motivo: ' . $motivo, $usuarioId);
        });
        Auditoria::registrar('cliente', $id, 'excluir', ['razao_social' => $c['razao_social']], ['motivo' => $motivo]);
    }

    public static function restaurar(int $id, int $usuarioId): void
    {
        $c = Banco::um('SELECT id, razao_social FROM clientes WHERE id = ? AND excluido_em IS NOT NULL', [$id]);
        if (!$c) {
            throw new RuntimeException('Loja não encontrada entre as excluídas.');
        }
        Banco::executar('UPDATE clientes SET excluido_em = NULL WHERE id = ?', [$id]);
        ClienteEvento::registrar($id, 'cadastro', 'Loja restaurada na Carteira.', $usuarioId);
        Auditoria::registrar('cliente', $id, 'restaurar', null, ['razao_social' => $c['razao_social']]);
    }

    /** Lojas excluídas (as mais recentes primeiro) */
    public static function excluidas(int $limite = 200): array
    {
        return Banco::todos("SELECT c.id, c.razao_social, c.nome_fantasia, c.cidade, c.uf, c.excluido_em, u.nome AS responsavel,
                (SELECT e.descricao FROM cliente_eventos e WHERE e.cliente_id = c.id AND e.descricao LIKE 'Loja %' ORDER BY e.id DESC LIMIT 1) AS motivo
            FROM clientes c LEFT JOIN usuarios u ON u.id = c.responsavel_id
            WHERE c.excluido_em IS NOT NULL ORDER BY c.excluido_em DESC LIMIT " . (int) $limite);
    }

    /**
     * Junta a loja repetida ($origem) na loja que fica ($destino). Devolve quantos registros mudaram de loja.
     */
    public static function juntar(int $destino, int $origem, int $usuarioId): int
    {
        if ($destino === $origem) {
            throw new RuntimeException('Escolha outra loja: não dá para juntar a loja com ela mesma.');
        }
        $fica = self::loja($destino);
        $sai = self::loja($origem);
        $movidos = Banco::transacao(function () use ($fica, $sai, $destino, $origem, $usuarioId) {
            $n = 0;
            // Toda tabela que guarda a loja (cliente_id) passa a apontar para a que fica
            $tabelas = array_column(Banco::todos("SELECT TABLE_NAME FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND COLUMN_NAME = 'cliente_id' AND TABLE_NAME <> 'clientes'"), 'TABLE_NAME');
            foreach ($tabelas as $t) {
                $n += Banco::executar("UPDATE IGNORE `$t` SET cliente_id = ? WHERE cliente_id = ?", [$destino, $origem]);
                Banco::executar("DELETE FROM `$t` WHERE cliente_id = ?", [$origem]); // repetidos que a loja que fica já tinha (ex.: mesma parada de roteiro)
            }
            Banco::executar('UPDATE produtos SET cliente_exclusivo_id = ? WHERE cliente_exclusivo_id = ?', [$destino, $origem]);
            // Completa o que estiver vazio na loja que fica
            $completar = [];
            foreach (self::COMPLETAR as $campo) {
                if (($fica[$campo] ?? null) === null || $fica[$campo] === '') {
                    if (($sai[$campo] ?? null) !== null && $sai[$campo] !== '') {
                        $completar[$campo] = $sai[$campo];
                    }
                }
            }
            $completar['observacoes'] = trim(($fica['observacoes'] ?? '') . (($sai['observacoes'] ?? '') !== '' ? "\n[Da loja juntada #$origem] " . $sai['observacoes'] : '')) ?: null;
            if ($sai['cnpj'] && ($completar['cnpj'] ?? null) === $sai['cnpj']) {
                Banco::executar('UPDATE clientes SET cnpj = NULL WHERE id = ?', [$origem]); // o CNPJ passa para a loja que fica
            }
            Banco::atualizar('clientes', $destino, $completar);
            Banco::executar('UPDATE clientes SET excluido_em = NOW() WHERE id = ?', [$origem]);
            ClienteEvento::registrar($destino, 'cadastro', 'Loja repetida juntada a esta: ' . ($sai['nome_fantasia'] ?: $sai['razao_social']) . " (#$origem) — o histórico dela veio para cá.", $usuarioId);
            ClienteEvento::registrar($origem, 'cadastro', 'Loja excluída da Carteira. Motivo: cadastro repetido, juntado à loja #' . $destino . '.', $usuarioId);
            return $n;
        });
        PedidoService::atualizarCompras($destino);
        Auditoria::registrar('cliente', $destino, 'juntar', ['origem' => $origem, 'origem_nome' => $sai['razao_social']], ['registros_movidos' => $movidos]);
        return $movidos;
    }

    /** Possíveis repetidas: mesmo CNPJ, ou mesmo nome (sem acento) na mesma cidade */
    public static function parecidas(array $c, int $limite = 8): array
    {
        $nome = mb_strtolower(trim((string) ($c['nome_fantasia'] ?: $c['razao_social'])));
        return Banco::todos("SELECT id, razao_social, nome_fantasia, cidade, uf, cnpj FROM clientes
            WHERE excluido_em IS NULL AND id <> ? AND ((cnpj IS NOT NULL AND cnpj <> '' AND cnpj = ?)
               OR (cidade = ? AND (LOWER(nome_fantasia) = ? OR LOWER(razao_social) = ? OR LOWER(razao_social) LIKE ?)))
            ORDER BY razao_social LIMIT " . (int) $limite,
            [$c['id'], (string) $c['cnpj'], (string) $c['cidade'], $nome, $nome, mb_substr($nome, 0, 12) . '%']);
    }

    private static function loja(int $id): array
    {
        $c = Banco::um('SELECT * FROM clientes WHERE id = ? AND excluido_em IS NULL', [$id]);
        if (!$c) {
            throw new RuntimeException('Loja não encontrada (talvez já tenha sido excluída).');
        }
        return $c;
    }
}
