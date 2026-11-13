<?php
namespace App\Models;

use App\Core\Banco;

/**
 * Linha do tempo do cliente (tabela cliente_eventos).
 */
class ClienteEvento
{
    /** Nomes amigáveis de cada tipo de evento */
    public const TIPOS = [
        'contato' => 'Contato', 'ligacao' => 'Ligação', 'whatsapp' => 'WhatsApp', 'visita' => 'Visita', 'email' => 'E-mail',
        'nota' => 'Anotação', 'etapa' => 'Mudança de etapa', 'responsavel' => 'Responsável', 'importacao' => 'Cadastro',
        'pedido' => 'Pedido', 'tarefa' => 'Tarefa', 'cobranca' => 'Cobrança', 'promessa' => 'Promessa de pagamento',
        'busca_contato' => 'Contatos atualizados',
    ];

    public static function registrar(int $clienteId, string $tipo, ?string $descricao, ?int $usuarioId, ?string $canal = null, ?array $dados = null, ?string $ocorridoEm = null): int
    {
        // Avisa o responsável quando OUTRA pessoa mexeu no cliente dele (não roda em cron/instalação)
        if (PHP_SAPI !== 'cli') {
            \App\Services\NotificacaoService::clienteAtualizado($clienteId, $tipo, $descricao, $usuarioId, $ocorridoEm);
        }
        return Banco::inserir('cliente_eventos', array_filter([
            'cliente_id'  => $clienteId,
            'usuario_id'  => $usuarioId,
            'tipo'        => $tipo,
            'canal'       => $canal,
            'descricao'   => $descricao,
            'dados_json'  => $dados ? json_encode($dados, JSON_UNESCAPED_UNICODE) : null,
            'ocorrido_em' => $ocorridoEm,
        ], fn($v) => $v !== null));
    }

    public static function doCliente(int $clienteId, int $limite = 30): array
    {
        return Banco::todos(
            'SELECT e.*, u.nome AS usuario_nome FROM cliente_eventos e LEFT JOIN usuarios u ON u.id = e.usuario_id
              WHERE e.cliente_id = ? ORDER BY e.ocorrido_em DESC, e.id DESC LIMIT ' . (int) $limite,
            [$clienteId]
        );
    }
}
