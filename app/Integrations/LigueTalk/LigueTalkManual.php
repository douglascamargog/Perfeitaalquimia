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

    public function listarChamadas(string $desde, ?string $ate = null): array
    {
        return [];
    }

    public function automatico(): bool
    {
        return false;
    }

    /** Sem API: quem liga é o telefone da pessoa (link tel:) e o resultado é registrado com 1 clique */
    public function ligar(string $ramal, string $numero): array
    {
        return ['id_externo' => null, 'situacao' => 'manual', 'duracao' => 0, 'gravacao' => null, 'mensagem' => 'Ligue pelo telefone e registre o resultado abaixo.'];
    }

    public function gravacao(string $referencia): ?string
    {
        return null;
    }

    public function enviarLista(string $nome, array $leads): array
    {
        return ['ok' => false, 'mensagem' => 'O Ligue Talk está no modo manual (Configurações › Ligue Talk).'];
    }
}
