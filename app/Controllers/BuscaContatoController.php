<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Integrations\BuscaContatos\BuscaContatosException;
use App\Models\Cliente;
use App\Services\BuscaContatoService;

/**
 * "Buscar contatos com IA" — chamado pelo busca_contatos.js (respostas em JSON).
 * Pode usar quem vê o cliente na Carteira ou quem trabalha a Cobrança.
 */
class BuscaContatoController extends Controller
{
    private function clienteOuErro(string $id): array
    {
        $cliente = Cliente::buscar((int) $id);
        if (!$cliente || (!Cliente::podeVer($cliente) && !Auth::pode('cobranca.ver'))) {
            $this->json(['ok' => false, 'mensagem' => 'Cliente não encontrado.'], 404);
            exit;
        }
        return $cliente;
    }

    /** Última busca do cliente (qualquer data), para mostrar sem gastar uma busca nova */
    public function ultima(string $id): void
    {
        $cliente = $this->clienteOuErro($id);
        $this->json(['ok' => true, 'busca' => BuscaContatoService::ultima((int) $cliente['id'], true)]);
    }

    public function buscar(string $id): void
    {
        $cliente = $this->clienteOuErro($id);
        @set_time_limit(180); // a busca real na web pode levar 1 a 2 minutos
        try {
            $this->json(['ok' => true, 'busca' => BuscaContatoService::buscar($cliente, Auth::id())]);
        } catch (BuscaContatosException $erro) {
            $this->json(['ok' => false, 'mensagem' => $erro->getMessage()], 422);
        }
    }

    public function aplicar(string $id): void
    {
        $cliente = $this->clienteOuErro($id);
        $itens = array_filter((array) ($_POST['itens'] ?? []), 'is_numeric');
        if (!$itens) {
            $this->json(['ok' => false, 'mensagem' => 'Marque pelo menos um contato.'], 422);
            return;
        }
        try {
            $mensagem = BuscaContatoService::aplicar($cliente, (int) ($_POST['busca_id'] ?? 0), $itens, Auth::id());
            $this->json(['ok' => true, 'mensagem' => $mensagem]);
        } catch (BuscaContatosException $erro) {
            $this->json(['ok' => false, 'mensagem' => $erro->getMessage()], 422);
        }
    }
}
