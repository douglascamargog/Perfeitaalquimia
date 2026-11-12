<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Config;
use App\Core\Sessao;
use App\Models\Cliente;
use App\Models\ClienteEvento;
use App\Models\Listas;
use App\Models\Tarefa;
use App\Services\VendasService;

/**
 * Comercial › Recompra: clientes que passaram do próprio tempo de recompra
 * (intervalo médio entre compras, recalculado a cada faturamento).
 */
class RecompraController extends Controller
{
    public function index(): void
    {
        $vendedor = Auth::pode('carteira.ver_todas') && ctype_digit((string) ($_GET['vendedor'] ?? '')) ? (int) $_GET['vendedor'] : null;
        $clientes = VendasService::recompra($vendedor);
        $base = (isset($_SERVER['HTTP_HOST']) ? 'https://' . $_SERVER['HTTP_HOST'] : '') . url('/catalogo');
        $codigo = \App\Core\Banco::valor('SELECT codigo_vendedor FROM usuarios WHERE id = ?', [Auth::id()]);
        foreach ($clientes as &$c) {
            $c['mensagem'] = strtr((string) Config::get('recompra.mensagem', ''), [
                '{nome}' => $c['nome_fantasia'] ?: $c['razao_social'], '{dias}' => (string) $c['dias_desde'],
                '{link_catalogo}' => $codigo ? "$base/v/$codigo" : $base,
            ]);
        }
        $this->tela('recompra/index', [
            'titulo' => 'Recompra', 'clientes' => $clientes, 'vendedor' => $vendedor,
            'vendedores' => Auth::pode('carteira.ver_todas') ? Listas::vendedores() : [],
            'estilos' => ['carteira.css', 'inicio.css', 'painel.css'], 'scripts' => ['carteira.js', 'busca_contatos.js'],
        ]);
    }

    /** Agenda um retorno para o cliente (tarefa na Agenda do responsável) */
    public function agendar(string $id): void
    {
        $cliente = Cliente::buscar((int) $id);
        if (!$cliente || !Cliente::podeVer($cliente)) {
            redirecionar('/recompra');
        }
        $data = preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->campo('data')) && $this->campo('data') >= date('Y-m-d') ? $this->campo('data') : date('Y-m-d');
        Tarefa::definirRetorno((int) $cliente['id'], (int) $cliente['responsavel_id'], $data, 'Recompra: oferecer reposição');
        ClienteEvento::registrar((int) $cliente['id'], 'tarefa', 'Retorno de recompra agendado para ' . data_br($data), Auth::id());
        Sessao::aviso('sucesso', 'Retorno agendado para ' . data_br($data) . ' (aparece na Agenda).');
        redirecionar('/recompra');
    }
}
