<?php
namespace App\Services;

use App\Core\Banco;

/**
 * Gestão › Análise de clientes: TODOS os clientes da empresa (de todas as carteiras) com filtros,
 * faturamento do período, ticket médio por pedido e MÉDIA MENSAL (o que comprou no período ÷ número de meses).
 *
 * Venda = pedido FATURADO (pedidos.faturado_em), igual à ficha do cliente e ao Painel.
 * Exemplo da média mensal: comprou R$ 4.074,63 nos últimos 12 meses → R$ 339,55 por mês (mesmo que tenha feito só 2 pedidos).
 */
class AnaliseClientesService
{
    /** Períodos: chave => [rótulo, data inicial, data final (exclusiva), meses para a média] */
    public static function periodos(): array
    {
        $mesAtual = (int) date('n');
        return [
            '12m' => ['Últimos 12 meses', date('Y-m-d', strtotime('-12 months')), date('Y-m-d', strtotime('+1 day')), 12],
            '6m'  => ['Últimos 6 meses', date('Y-m-d', strtotime('-6 months')), date('Y-m-d', strtotime('+1 day')), 6],
            '3m'  => ['Últimos 3 meses', date('Y-m-d', strtotime('-3 months')), date('Y-m-d', strtotime('+1 day')), 3],
            'ano' => ['Este ano (' . date('Y') . ')', date('Y-01-01'), date('Y-m-d', strtotime('+1 day')), $mesAtual],
            'ano_passado' => ['Ano passado (' . (date('Y') - 1) . ')', (date('Y') - 1) . '-01-01', date('Y') . '-01-01', 12],
        ];
    }

    /** Agrupar por: chave => [rótulo, expressão SQL] */
    public const GRUPOS = [
        'uf'       => ['UF', "COALESCE(NULLIF(c.uf, ''), '—')"],
        'cidade'   => ['Cidade', "CONCAT(COALESCE(NULLIF(c.cidade, ''), '—'), ' / ', COALESCE(c.uf, ''))"],
        'regiao'   => ['Região', "COALESCE(r.nome, 'Sem região')"],
        'segmento' => ['Segmento', "COALESCE(s.nome, 'Sem segmento')"],
        'vendedor' => ['Vendedor(a)', "COALESCE(u.nome, 'Sem vendedor(a)')"],
        'carteira' => ['Carteira', "COALESCE(k.nome, 'Sem carteira')"],
        'etapa'    => ['Etapa do funil', "COALESCE(e.nome, '—')"],
    ];

    /** Ordenar a lista de lojas */
    public const ORDENS = [
        'media'    => ['Maior média mensal', 'total DESC, nome'],
        'ticket'   => ['Maior ticket por pedido', 'ticket DESC, nome'],
        'pedidos'  => ['Mais pedidos', 'pedidos DESC, total DESC'],
        'ultima'   => ['Última compra mais antiga', 'c.ultima_compra_em IS NULL, c.ultima_compra_em ASC'],
        'nome'     => ['Nome', 'nome'],
    ];

    /** Filtros limpos a partir do GET */
    public static function filtros(array $g): array
    {
        $int = fn($k) => (int) ($g[$k] ?? 0) ?: null;
        $num = fn($k) => isset($g[$k]) && trim((string) $g[$k]) !== '' ? max(0, (float) str_replace(['.', ','], ['', '.'], (string) $g[$k])) : null;
        return [
            'q'        => mb_substr(trim((string) ($g['q'] ?? '')), 0, 80),
            'uf'       => preg_match('/^[A-Z]{2}$/', (string) ($g['uf'] ?? '')) ? $g['uf'] : '',
            'cidade'   => mb_substr(trim((string) ($g['cidade'] ?? '')), 0, 80),
            'regiao'   => $int('regiao'),
            'segmento' => $int('segmento'),
            'vendedor' => $int('vendedor'),
            'carteira' => $int('carteira'),
            'etapa'    => $int('etapa'),
            'tipo'     => in_array($g['tipo'] ?? '', ['cliente', 'lead'], true) ? $g['tipo'] : '',
            'compra'   => in_array($g['compra'] ?? '', ['comprou', 'nao_comprou', 'sem30', 'sem60', 'sem90', 'nunca'], true) ? $g['compra'] : '',
            'media_min' => $num('media_min'),
            'media_max' => $num('media_max'),
            'periodo'  => array_key_exists($g['periodo'] ?? '', self::periodos()) ? $g['periodo'] : '12m',
            'grupo'    => array_key_exists($g['grupo'] ?? '', self::GRUPOS) ? $g['grupo'] : 'cidade',
            'ordem'    => array_key_exists($g['ordem'] ?? '', self::ORDENS) ? $g['ordem'] : 'media',
        ];
    }

    /** Base da consulta: cada cliente com o que comprou no período (subconsulta agregada uma vez só) */
    private static function base(array $f): array
    {
        [, $de, $ate, $meses] = self::periodos()[$f['periodo']];
        $w = ['c.excluido_em IS NULL'];
        $p = [$de, $ate];
        if ($f['q'] !== '') {
            $w[] = '(c.razao_social LIKE ? OR c.nome_fantasia LIKE ? OR c.cnpj LIKE ?)';
            $like = '%' . $f['q'] . '%';
            array_push($p, $like, $like, '%' . preg_replace('/\D/', '', $f['q']) . '%');
        }
        foreach (['uf' => 'c.uf', 'cidade' => 'c.cidade', 'regiao' => 'c.regiao_id', 'segmento' => 'c.segmento_id',
                  'vendedor' => 'c.responsavel_id', 'carteira' => 'c.carteira_id', 'etapa' => 'c.etapa_id', 'tipo' => 'c.tipo'] as $k => $col) {
            if ($f[$k] !== null && $f[$k] !== '') {
                $w[] = "$col = ?";
                $p[] = $f[$k];
            }
        }
        $semCompra = fn(int $dias) => "(c.ultima_compra_em IS NOT NULL AND c.ultima_compra_em < CURDATE() - INTERVAL $dias DAY)";
        $w[] = match ($f['compra']) {
            'comprou'     => 'COALESCE(v.total, 0) > 0',
            'nao_comprou' => 'COALESCE(v.total, 0) = 0',
            'sem30'       => $semCompra(30),
            'sem60'       => $semCompra(60),
            'sem90'       => $semCompra(90),
            'nunca'       => 'c.ultima_compra_em IS NULL AND COALESCE(v.total, 0) = 0',
            default       => '1 = 1',
        };
        if ($f['media_min'] !== null) {
            $w[] = 'COALESCE(v.total, 0) / ? >= ?';
            array_push($p, $meses, $f['media_min']);
        }
        if ($f['media_max'] !== null) {
            $w[] = 'COALESCE(v.total, 0) / ? <= ?';
            array_push($p, $meses, $f['media_max']);
        }
        $de = "FROM clientes c
            LEFT JOIN (SELECT cliente_id, SUM(total) AS total, COUNT(*) AS pedidos FROM pedidos
                        WHERE situacao = 'faturado' AND faturado_em >= ? AND faturado_em < ? GROUP BY cliente_id) v ON v.cliente_id = c.id
            LEFT JOIN usuarios u ON u.id = c.responsavel_id
            LEFT JOIN carteiras k ON k.id = c.carteira_id
            LEFT JOIN regioes r ON r.id = c.regiao_id
            LEFT JOIN segmentos s ON s.id = c.segmento_id
            LEFT JOIN funil_etapas e ON e.id = c.etapa_id
            WHERE " . implode(' AND ', $w);
        return [$de, $p, $meses];
    }

    /** Números do filtro */
    public static function resumo(array $f): array
    {
        [$de, $p, $meses] = self::base($f);
        $r = Banco::um("SELECT COUNT(*) AS clientes, SUM(COALESCE(v.total, 0) > 0) AS compraram,
                COALESCE(SUM(v.total), 0) AS faturamento, COALESCE(SUM(v.pedidos), 0) AS pedidos $de", $p);
        $compraram = (int) $r['compraram'];
        $fat = (float) $r['faturamento'];
        return [
            'clientes' => (int) $r['clientes'], 'compraram' => $compraram, 'faturamento' => $fat, 'pedidos' => (int) $r['pedidos'], 'meses' => $meses,
            'ticket' => $r['pedidos'] ? $fat / (int) $r['pedidos'] : 0.0,
            'media_cliente' => $compraram ? $fat / $meses / $compraram : 0.0,      // média mensal de cada cliente que comprou
            'media_empresa' => $fat / $meses,                                        // quanto esse grupo compra por mês, somado
            'pedidos_cliente' => $compraram ? (int) $r['pedidos'] / $compraram : 0.0,
        ];
    }

    /** Uma linha por grupo (UF, cidade, vendedor...) */
    public static function agrupado(array $f, int $limite = 200): array
    {
        [$de, $p, $meses] = self::base($f);
        $expr = self::GRUPOS[$f['grupo']][1];
        $linhas = Banco::todos("SELECT $expr AS grupo, MIN(c.uf) AS uf, MIN(c.cidade) AS cidade, MIN(c.regiao_id) AS regiao_id, MIN(c.segmento_id) AS segmento_id,
                MIN(c.responsavel_id) AS responsavel_id, MIN(c.carteira_id) AS carteira_id, MIN(c.etapa_id) AS etapa_id,
                COUNT(*) AS clientes, SUM(COALESCE(v.total, 0) > 0) AS compraram, COALESCE(SUM(v.total), 0) AS faturamento, COALESCE(SUM(v.pedidos), 0) AS pedidos
            $de GROUP BY grupo ORDER BY faturamento DESC, clientes DESC LIMIT " . (int) $limite, $p);
        foreach ($linhas as &$l) {
            $l['ticket'] = $l['pedidos'] ? (float) $l['faturamento'] / (int) $l['pedidos'] : 0.0;
            $l['media_cliente'] = $l['compraram'] ? (float) $l['faturamento'] / $meses / (int) $l['compraram'] : 0.0;
        }
        return $linhas;
    }

    /** Lista de lojas (paginada) */
    public static function lista(array $f, int $pagina = 1, int $porPagina = 50): array
    {
        [$de, $p, $meses] = self::base($f);
        $total = (int) Banco::valor("SELECT COUNT(*) $de", $p);
        $paginas = max(1, (int) ceil($total / $porPagina));
        $pagina = min(max(1, $pagina), $paginas);
        $itens = Banco::todos(self::camposLista($meses) . " $de ORDER BY " . self::ORDENS[$f['ordem']][1] . " LIMIT $porPagina OFFSET " . (($pagina - 1) * $porPagina), $p);
        return ['itens' => $itens, 'total' => $total, 'pagina' => $pagina, 'paginas' => $paginas];
    }

    /** Todas as linhas do filtro (exportar CSV), uma de cada vez */
    public static function paraExportar(array $f): \PDOStatement
    {
        [$de, $p, $meses] = self::base($f);
        return Banco::consulta(self::camposLista($meses) . " $de ORDER BY " . self::ORDENS[$f['ordem']][1], $p);
    }

    private static function camposLista(int $meses): string
    {
        return "SELECT c.id, COALESCE(NULLIF(c.nome_fantasia, ''), c.razao_social) AS nome, c.razao_social, c.cnpj, c.cidade, c.uf, c.tipo,
                u.nome AS vendedor, k.nome AS carteira, s.nome AS segmento, e.nome AS etapa, c.ultima_compra_em,
                COALESCE(v.total, 0) AS total, COALESCE(v.pedidos, 0) AS pedidos,
                CASE WHEN COALESCE(v.pedidos, 0) > 0 THEN v.total / v.pedidos ELSE 0 END AS ticket,
                COALESCE(v.total, 0) / $meses AS media";
    }

    /** Opções dos filtros */
    public static function opcoes(string $uf): array
    {
        return [
            'ufs' => \App\Models\Listas::ufsComClientes(),
            'cidades' => \App\Models\Listas::cidadesComClientes($uf),
            'regioes' => \App\Models\Listas::regioes(),
            'segmentos' => \App\Models\Listas::segmentos(),
            'vendedores' => \App\Models\Listas::vendedores(),
            'carteiras' => Banco::todos('SELECT id, nome FROM carteiras WHERE ativa = 1 ORDER BY nome'),
            'etapas' => \App\Models\Listas::etapas(),
        ];
    }

    /** Filtro que abre só um grupo (clicar na linha do agrupamento) */
    public static function filtroDoGrupo(string $grupo, array $linha): array
    {
        return match ($grupo) {
            'uf'       => ['uf' => $linha['uf'] ?? ''],
            'cidade'   => ['uf' => $linha['uf'] ?? '', 'cidade' => $linha['cidade'] ?? ''],
            'regiao'   => ['regiao' => $linha['regiao_id'] ?? ''],
            'segmento' => ['segmento' => $linha['segmento_id'] ?? ''],
            'vendedor' => ['vendedor' => $linha['responsavel_id'] ?? ''],
            'carteira' => ['carteira' => $linha['carteira_id'] ?? ''],
            'etapa'    => ['etapa' => $linha['etapa_id'] ?? ''],
            default    => [],
        };
    }
}
