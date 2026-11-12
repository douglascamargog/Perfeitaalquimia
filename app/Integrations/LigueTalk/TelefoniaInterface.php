<?php
namespace App\Integrations\LigueTalk;

/**
 * CONTRATO com a telefonia (Ligue Talk).
 * Hoje: LigueTalkManual (plano B: o vendedor registra a ligação com 1 clique).
 * Quando o Ligue Talk confirmar API/webhook: criar LigueTalkClient com os mesmos métodos.
 */
interface TelefoniaInterface
{
    /** Registra uma ligação na linha do tempo do cliente. Devolve o id do evento criado. */
    public function registrarLigacao(int $clienteId, int $usuarioId, string $resultado, ?string $observacao = null): int;

    /** Chamadas feitas desde a data (vazio no modo manual) */
    public function listarChamadas(string $desde): array;

    /** true se as ligações chegam sozinhas (API); false se o vendedor registra à mão */
    public function automatico(): bool;
}
