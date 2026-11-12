<?php
namespace App\Integrations\LigueTalk;

use App\Core\Banco;

/**
 * Plano B da telefonia: o vendedor clica em "registrar ligação" na ficha do cliente.
 * A ligação vira um evento na linha do tempo (tabela cliente_eventos, criada no módulo Carteira).
 */
class LigueTalkManual implements TelefoniaInterface
{
    public function registrarLigacao(int $clienteId, int $usuarioId, string $resultado, ?string $observacao = null): int
    {
        return Banco::inserir('cliente_eventos', [
            'cliente_id' => $clienteId,
            'usuario_id' => $usuarioId,
            'tipo'       => 'ligacao',
            'descricao'  => trim($resultado . ($observacao ? ' — ' . $observacao : '')),
        ]);
    }

    public function listarChamadas(string $desde): array
    {
        return [];
    }

    public function automatico(): bool
    {
        return false;
    }
}
