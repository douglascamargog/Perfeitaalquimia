<?php
namespace App\Controllers;

use App\Core\Banco;
use App\Models\Listas;
use App\Services\MetaService;
use App\Services\VendasService;

/**
 * Gestão › Painel de vendas: faturamento (pedidos faturados), pedidos, ticket, recorrência,
 * rankings por vendedor/função/UF/origem, top clientes e produtos, sem compra há 30/60/90 dias.
 */
class PainelController extends Controller
{
    /** Filtros da URL → [filtros, escolha do período] */
    public static function filtros(): array
    {
        $periodo = array_key_exists($_GET['periodo'] ?? '', VendasService::PERIODOS) ? $_GET['periodo'] : 'mes';
        [$de, $ate] = VendasService::periodo($periodo, (string) ($_GET['de'] ?? ''), (string) ($_GET['ate'] ?? ''));
        $f = ['de' => $de, 'ate' => $ate];
        foreach (['vendedor', 'funcao', 'regiao'] as $campo) {
            $f[$campo] = ctype_digit((string) ($_GET[$campo] ?? '')) ? $_GET[$campo] : '';
        }
        $f['uf'] = in_array($_GET['uf'] ?? '', Listas::ufs(), true) ? $_GET['uf'] : '';
        $f['origem'] = in_array($_GET['origem'] ?? '', ['catalogo', 'sistema', 'app'], true) ? $_GET['origem'] : '';
        return [$f, $periodo];
    }

    public function index(): void
    {
        [$f, $periodo] = self::filtros();
        [$deAnt, $ateAnt] = VendasService::anterior($f['de'], $f['ate']);
        $atual = VendasService::resumo($f);
        $anterior = VendasService::resumo(['de' => $deAnt, 'ate' => $ateAnt] + $f);
        $vendedores = VendasService::ranking($f, 'vendedor');
        $funcoes = VendasService::ranking($f, 'funcao');
        // Meta do período (só quando o período é um mês fechado ou o mês atual)
        $mes = substr($f['de'], 0, 7) === substr($f['ate'], 0, 7) && substr($f['de'], 8) === '01' ? substr($f['de'], 0, 7) : null;
        $metas = $mes ? ['vendedor' => array_column(Banco::todos("SELECT alvo_id, valor FROM metas WHERE mes = ? AND tipo = 'vendedor'", [$mes]), 'valor', 'alvo_id'),
            'funcao' => array_column(Banco::todos("SELECT alvo_id, valor FROM metas WHERE mes = ? AND tipo = 'funcao'", [$mes]), 'valor', 'alvo_id'),
            'equipe' => $mes ? (float) Banco::valor('SELECT meta FROM metas_equipe WHERE mes = ?', [$mes]) : 0] : null;
        $this->tela('painel/index', [
            'titulo' => 'Painel de vendas', 'f' => $f, 'periodo' => $periodo, 'periodoAnterior' => [$deAnt, $ateAnt],
            'atual' => $atual, 'anterior' => $anterior, 'aFaturar' => VendasService::aFaturar($f), 'meses' => VendasService::porMes($f),
            'vendedores' => $vendedores, 'funcoes' => $funcoes, 'ufs' => VendasService::ranking($f, 'uf', 27), 'origens' => VendasService::ranking($f, 'origem'),
            'clientes' => VendasService::ranking($f, 'cliente', 10), 'produtos' => VendasService::ranking($f, 'produto', 10),
            'semCompra' => VendasService::semCompra($f), 'metas' => $metas, 'mes' => $mes,
            'opcoes' => ['vendedores' => MetaService::alvos('vendedor'), 'funcoes' => MetaService::alvos('funcao'), 'regioes' => Listas::regioes(), 'ufs' => Listas::ufsComClientes()],
            'estilos' => ['carteira.css', 'inicio.css', 'painel.css'],
            'scripts' => ['https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js', 'painel.js'],
        ]);
    }

    /** CSV dos vendedores e funções no período (abre no Excel) */
    public function exportar(): void
    {
        [$f] = self::filtros();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="painel-vendas-' . $f['de'] . '-a-' . $f['ate'] . '.csv"');
        $s = fopen('php://output', 'w');
        fwrite($s, "\xEF\xBB\xBF");
        fputcsv($s, ['Período', data_br($f['de']) . ' a ' . data_br($f['ate'])], ';');
        foreach (['vendedor' => 'Vendedor', 'funcao' => 'Função', 'uf' => 'UF', 'origem' => 'Origem', 'cliente' => 'Cliente', 'produto' => 'Produto'] as $por => $rotulo) {
            fputcsv($s, [], ';');
            fputcsv($s, [$rotulo, 'Faturado', $por === 'produto' ? 'Quantidade' : 'Pedidos', 'Ticket médio'], ';');
            foreach (VendasService::ranking($f, $por, $por === 'cliente' ? 500 : 100) as $l) {
                fputcsv($s, [$l['nome'], number_format($l['valor'], 2, ',', '.'), $l['pedidos'] ?? $l['quantidade'] ?? '', number_format((float) $l['ticket'], 2, ',', '.')], ';');
            }
        }
        fclose($s);
    }
}
