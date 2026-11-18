<?php
namespace App\Controllers;

use App\Core\Auditoria;
use App\Services\AnaliseClientesService as A;

/** Comercial › Análise de clientes (clientes.analise: Gestor, Diretoria e Vendedor; quem não tem carteira.ver_todas vê só a própria carteira) */
class AnaliseClientesController extends Controller
{
    public function index(): void
    {
        $f = A::filtros($_GET);
        $this->tela('analise/index', [
            'titulo' => 'Análise de clientes',
            'f' => $f,
            'opcoes' => A::opcoes($f['uf']),
            'resumo' => A::resumo($f),
            'grupos' => A::agrupado($f),
            'lista' => A::lista($f, (int) ($_GET['pagina'] ?? 1)),
            'mensal' => A::mensal($f), 'frequencia' => A::frequencia($f), 'situacao' => A::situacao($f),
            'mesesPeriodo' => A::mesesDoPeriodo($f['periodo']), 'veTodos' => A::veTodos(),
            'estilos' => ['carteira.css', 'inicio.css', 'painel.css', 'analise.css'],
            'scripts' => ['https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js', 'analise.js'],
        ]);
    }

    public function exportar(): void
    {
        $f = A::filtros($_GET);
        Auditoria::registrar('clientes', null, 'analise_exportar_csv', null, ['filtros' => array_filter($f)]);
        $meses = A::periodos()[$f['periodo']][3];
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="analise_clientes_' . date('Y-m-d') . '.csv"');
        $saida = fopen('php://output', 'w');
        fwrite($saida, "\xEF\xBB\xBF"); // BOM: o Excel reconhece os acentos
        fputcsv($saida, ['ID', 'Loja', 'Razão social', 'CNPJ', 'Cidade', 'UF', 'Vendedor(a)', 'Carteira', 'Segmento', 'Etapa',
            'Comprou no período', 'Pedidos', 'Meses com compra', 'Ticket médio', "Média mensal (÷ $meses)", 'Última compra'], ';');
        $n = fn($v) => number_format((float) $v, 2, ',', '');
        $stmt = A::paraExportar($f);
        while ($l = $stmt->fetch()) {
            fputcsv($saida, [$l['id'], $l['nome'], $l['razao_social'], $l['cnpj'], $l['cidade'], $l['uf'], $l['vendedor'], $l['carteira'], $l['segmento'],
                $l['etapa'], $n($l['total']), $l['pedidos'], $l['meses_compra'], $n($l['ticket']), $n($l['media']), $l['ultima_compra_em'] ? data_br($l['ultima_compra_em']) : ''], ';');
        }
        fclose($saida);
    }
}
