<?php
namespace App\Controllers;

use App\Services\TelefoniaService;

/**
 * Discador do topo (ao lado do sino): Televendas e Cobrança ligam para qualquer número pelo Ligue Talk.
 */
class TelefoniaController extends Controller
{
    public function ligarNumero(): void
    {
        if (!TelefoniaService::veDiscador()) {
            $this->json(['ok' => false, 'mensagem' => 'O discador é das funções configuradas (Televendas e Cobrança).'], 403);
            return;
        }
        try {
            $this->json(TelefoniaService::ligarNumero($this->campo('numero')));
        } catch (\RuntimeException $e) {
            $this->json(['ok' => false, 'mensagem' => $e->getMessage()], 422);
        }
    }
}
