<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Banco;
use App\Models\Cliente;

/**
 * Busca rápida do topo: clientes (nome, fantasia, CNPJ, telefone — só da carteira que a pessoa pode ver)
 * e produtos (código ou nome). Responde JSON para o campo do topo.
 */
class BuscaController extends Controller
{
    public function rapida(): void
    {
        $q = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 60);
        if (mb_strlen($q) < 2) {
            $this->json(['clientes' => [], 'produtos' => []]);
            return;
        }
        $clientes = [];
        if (Auth::pode('carteira.ver_propria') || Auth::pode('carteira.ver_todas')) {
            // Mesma regra de escopo da Carteira (vendedor só vê os dele)
            $r = Cliente::listar(Cliente::filtrosDe(['q' => $q]), 1, 8);
            foreach ($r['itens'] as $c) {
                $clientes[] = ['id' => (int) $c['id'], 'nome' => $c['nome_fantasia'] ?: $c['razao_social'], 'local' => trim(($c['cidade'] ?? '') . '/' . ($c['uf'] ?? ''), '/'),
                    'tipo' => $c['tipo'], 'etapa' => $c['etapa'], 'url' => url('/carteira/lista') . '?abrir=' . (int) $c['id']];
            }
        }
        $produtos = array_map(fn($p) => ['codigo' => $p['codigo'], 'nome' => $p['nome'], 'embalagem' => $p['embalagem'],
            'url' => Auth::pode('academia.ver') ? url('/academia/produtos/' . rawurlencode($p['slug'])) : url('/catalogo/produto/' . rawurlencode($p['slug']))],
            Banco::todos('SELECT codigo, nome, slug, embalagem FROM produtos WHERE ativo = 1 AND (codigo LIKE ? OR nome LIKE ?) ORDER BY codigo LIKE ? DESC, nome LIMIT 5',
                ["$q%", "%$q%", "$q%"]));
        $this->json(['clientes' => $clientes, 'produtos' => $produtos]);
    }
}
