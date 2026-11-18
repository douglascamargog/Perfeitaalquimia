<?php
namespace App\Services;

use App\Core\Auth;
use App\Core\Banco;

/**
 * Comercial › Análise de clientes: os clientes com filtros, faturamento do período, ticket médio, MÉDIA MENSAL,
 * recorrência (em quantos meses cada cliente comprou), clientes ativos e gráficos.
 *
 * Quem vê: gestão (carteira.ver_todas) vê todos os clientes; a vendedora vê só a própria carteira
 * (e a gestora do setor, a do setor dela) — a mesma regra da Carteira.
 * Venda = pedido FATURADO. O período é sempre em meses do calendário (ex.: últimos 12 meses = do 1º dia de 11 meses atrás até hoje).
 * Média mensal = o que comprou no período ÷ número de meses do período.
 */
class AnaliseClientesService
{
    /** Períodos: chave => [rótulo, data inicial, data final (exclusiva), meses para a média] */
    public static function periodos(): array
    {
        $mesAtual = (int) date('n');
        $inicio = fn(int $n) => date('Y-m-01', strtotime(date('Y-m-01') . " -$n months"));
        $amanha = date('Y-m-d', strtotime('+1 day'));
        return [
            '12m' => ['Últimos 12 meses', $inicio(11), $amanha, 12],
            '6m'  => ['Últimos 6 meses', $inicio(5), $amanha, 6],
            '3m'  => ['Últimos 3 meses', $inicio(2), $amanha, 3],
            'ano' => ['Este ano (' . date('Y') . ')', date('Y-01-01'), $amanha, $mesAtual],
            'ano_passado' => ['Ano passado (' . (date('Y') - 1) . ')', (date('Y') - 1) . '-01-01', date('Y') . '-01-01', 12],
        ];
    }

    /** Meses do período ('Y-m'), do mais antigo ao mais recente */
    public static function mesesDoPeriodo(string $periodo): array
    {
        [, $de, $ate] = self::periodos()[$periodo];
        $lista = [];
        for ($m = substr($de, 0, 7); $m . '-01' < $ate && count($lista) < 24; $m = date('Y-m', strtotime("$m-01 +1 month"))) {
            $lista[] = $m;
        }
        return $lista;
    }

    /** Meses completos (sem o mês que ainda está correndo) — base do "comprou todos os meses" */
    public static function mesesCompletos(string $periodo): array
    {
        return array_values(array_filter(self::mesesDoPeriodo($periodo), fn($m) => $m < date('Y-m')));
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
        'meses'    => ['Mais meses com compra', 'meses_compra DESC, total DESC'],
        'ticket'   => ['Maior ticket por pedido', 'ticket DESC, nome'],
        'pedidos'  => ['Mais pedidos', 'pedidos DESC, total DESC'],
        'ultima'   => ['Última compra mais antiga', 'c.ultima_compra_em IS NULL, c.ultima_compra_em ASC'],
        'nome'     => ['Nome', 'nome'],
    ];

    /** Frequência de compra no período */
    public const FREQUENCIAS = [
        'todos'       => 'Comprou em todos os meses',
        'recorrentes' => 'Recorrentes (2 meses ou mais)',
        'uma'         => 'Comprou em 1 mês só',
        'nenhum'      => 'Não comprou no período',
    ];

    /** Filtros limpos a partir do GET */
    public static function filtros(array $g): array
    {
        $int = fn($k) => (int) ($g[$k] ?? 0) ?: null;
        $num = fn($k) => isset($g[$k]) && trim((string) $g[$k]) !== '' ? max(0, (float) str_replace(['.', ','], ['', '.'], (string) $g[$k])) : null;
        $periodo = array_key_exists($g['periodo'] ?? '', self::periodos()) ? $g['periodo'] : '12m';
        $meses = array_values(array_intersect(array_map('strval', (array) ($g['meses'] ?? [])), self::mesesDoPeriodo($periodo)));
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
            'frequencia' => array_key_exists($g['frequencia'] ?? '', self::FREQUENCIAS) ? $g['frequencia'] : '',
            'meses'    => $meses,                                   // comprou em TODOS estes meses
            'media_min' => $num('media_min'),
            'media_max' => $num('media_max'),
            'periodo'  => $periodo,
            'grupo'    => array_key_exists($g['grupo'] ?? '', self::GRUPOS) ? $g['grupo'] : 'cidade',
            'ordem'    => array_key_exists($g['ordem'] ?? '', self::ORDENS) ? $g['ordem'] : 'media',
        ];
    }

    /** A pessoa vê todos os clientes? (senão, só a própria carteira e a do setor que gerencia) */
    public static function veTodos(): bool
    {
        return Auth::pode('carteira.ver_todas');
    }

    /** Base da consulta: cada cliente com o que comprou no período (subconsulta agregada uma vez só) */
    private static function base(array $f): array
    {
        [, $de, $ate, $meses] = self::periodos()[$f['periodo']];
        $w = ['c.excluido_em IS NULL', "c.tipo <> 'consumidor'"];
        $p = [$de, $ate];
        if (!self::veTodos()) {
            $setor = Setores::sqlGerenciados();
            $w[] = $setor ? "(c.responsavel_id = ? OR $setor)" : 'c.responsavel_id = ?';
            $p[] = (int) Auth::id();
        }
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
        // Frequência: em quantos meses do período comprou ("todos" = todos os meses COMPLETOS, sem o mês que está correndo)
        $completos = self::mesesCompletos($f['periodo']);
        if ($f['frequencia'] === 'todos') {
            if (!$completos) {
                $w[] = 'COALESCE(v.meses, 0) >= 1';
            } else {
                $w[] = '(SELECT COUNT(DISTINCT DATE_FORMAT(pt.criado_em, \'%Y-%m\')) FROM pedidos pt WHERE pt.cliente_id = c.id AND pt.situacao = \'faturado\''
                    . ' AND pt.criado_em >= ? AND pt.criado_em < ?) = ' . count($completos);
                array_push($p, $completos[0] . '-01', date('Y-m-01'));
            }
        } elseif ($f['frequencia'] === 'recorrentes') {
            $w[] = 'COALESCE(v.meses, 0) >= 2';
        } elseif ($f['frequencia'] === 'uma') {
            $w[] = 'COALESCE(v.meses, 0) = 1';
        } elseif ($f['frequencia'] === 'nenhum') {
            $w[] = 'COALESCE(v.meses, 0) = 0';
        }
        // Comprou em TODOS os meses escolhidos
        foreach ($f['meses'] as $m) {
            $w[] = "EXISTS (SELECT 1 FROM pedidos pm WHERE pm.cliente_id = c.id AND pm.situacao = 'faturado' AND pm.criado_em >= ? AND pm.criado_em < ? + INTERVAL 1 MONTH)";
            array_push($p, "$m-01", "$m-01");
        }
        if ($f['media_min'] !== null) {
            $w[] = 'COALESCE(v.total, 0) / ? >= ?';
            array_push($p, $meses, $f['media_min']);
        }
        if ($f['media_max'] !== null) {
            $w[] = 'COALESCE(v.total, 0) / ? <= ?';
            array_push($p, $meses, $f['media_max']);
        }
        $de = "FROM clientes c
            LEFT JOIN (SELECT cliente_id, SUM(total) AS total, COUNT(*) AS pedidos, COUNT(DISTINCT DATE_FORMAT(criado_em, '%Y-%m')) AS meses FROM pedidos
                        WHERE situacao = 'faturado' AND criado_em >= ? AND criado_em < ? GROUP BY cliente_id) v ON v.cliente_id = c.id
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
        $dias = (int) \App\Core\Config::get('carteira.dias_inatividade', 90);
        $r = Banco::um("SELECT COUNT(*) AS clientes, SUM(COALESCE(v.total, 0) > 0) AS compraram,
                COALESCE(SUM(v.total), 0) AS faturamento, COALESCE(SUM(v.pedidos), 0) AS pedidos,
                SUM(COALESCE(v.meses, 0) >= 2) AS recorrentes,
                SUM(c.ultima_compra_em >= CURDATE() - INTERVAL $dias DAY) AS ativos,
                SUM(c.ultima_compra_em IS NULL) AS nunca $de", $p);
        $compraram = (int) $r['compraram'];
        $fat = (float) $r['faturamento'];
        $todos = $f['frequencia'] === 'todos' ? (int) $r['clientes'] : self::contarTodosOsMeses($f);
        return [
            'clientes' => (int) $r['clientes'], 'compraram' => $compraram, 'faturamento' => $fat, 'pedidos' => (int) $r['pedidos'], 'meses' => $meses,
            'ticket' => $r['pedidos'] ? $fat / (int) $r['pedidos'] : 0.0,
            'media_cliente' => $compraram ? $fat / $meses / $compraram : 0.0,      // média mensal de cada cliente que comprou
            'media_empresa' => $fat / $meses,                                        // quanto esse grupo compra por mês, somado
            'pedidos_cliente' => $compraram ? (int) $r['pedidos'] / $compraram : 0.0,
            'recorrentes' => (int) $r['recorrentes'], 'recorrencia_pct' => $compraram ? (int) $r['recorrentes'] / $compraram * 100 : 0.0,
            'ativos' => (int) $r['ativos'], 'dias_ativo' => $dias, 'nunca' => (int) $r['nunca'],
            'todos_meses' => $todos, 'meses_completos' => count(self::mesesCompletos($f['periodo'])),
        ];
    }

    private static function contarTodosOsMeses(array $f): int
    {
        [$de, $p] = self::base(['frequencia' => 'todos'] + $f);
        return (int) Banco::valor("SELECT COUNT(*) $de", $p);
    }

    /** Mês a mês: faturamento, clientes que compraram, pedidos e clientes novos (primeira compra da vida no mês) */
    public static function mensal(array $f): array
    {
        [$de, $p] = self::base($f);
        [, $ini, $fim] = self::periodos()[$f['periodo']];
        $linhas = array_column(Banco::todos("SELECT DATE_FORMAT(pe.criado_em, '%Y-%m') AS mes, SUM(pe.total) AS valor, COUNT(DISTINCT pe.cliente_id) AS clientes, COUNT(*) AS pedidos
            FROM pedidos pe WHERE pe.situacao = 'faturado' AND pe.criado_em >= ? AND pe.criado_em < ? AND pe.cliente_id IN (SELECT c.id $de)
            GROUP BY mes", array_merge([$ini, $fim], $p)), null, 'mes');
        $novos = array_column(Banco::todos("SELECT DATE_FORMAT(x.primeira, '%Y-%m') AS mes, COUNT(*) AS n FROM (
                SELECT pe.cliente_id, MIN(pe.criado_em) AS primeira FROM pedidos pe WHERE pe.situacao = 'faturado' AND pe.cliente_id IN (SELECT c.id $de) GROUP BY pe.cliente_id
            ) x WHERE x.primeira >= ? AND x.primeira < ? GROUP BY mes", array_merge($p, [$ini, $fim])), 'n', 'mes');
        return array_map(fn($m) => ['mes' => $m, 'valor' => (float) ($linhas[$m]['valor'] ?? 0), 'clientes' => (int) ($linhas[$m]['clientes'] ?? 0),
            'pedidos' => (int) ($linhas[$m]['pedidos'] ?? 0), 'novos' => (int) ($novos[$m] ?? 0)], self::mesesDoPeriodo($f['periodo']));
    }

    /** Quantos clientes compraram em 0, 1, 2... meses do período */
    public static function frequencia(array $f): array
    {
        [$de, $p] = self::base($f);
        $porN = array_column(Banco::todos("SELECT COALESCE(v.meses, 0) AS n, COUNT(*) AS clientes $de GROUP BY n", $p), 'clientes', 'n');
        $total = count(self::mesesDoPeriodo($f['periodo']));
        return array_map(fn($n) => ['meses' => $n, 'clientes' => (int) ($porN[$n] ?? 0)], range(0, $total));
    }

    /** Situação da carteira pela última compra (para a rosca) */
    public static function situacao(array $f): array
    {
        [$de, $p] = self::base($f);
        $r = Banco::um("SELECT SUM(c.ultima_compra_em >= CURDATE() - INTERVAL 30 DAY) AS a30,
                SUM(c.ultima_compra_em < CURDATE() - INTERVAL 30 DAY AND c.ultima_compra_em >= CURDATE() - INTERVAL 60 DAY) AS a60,
                SUM(c.ultima_compra_em < CURDATE() - INTERVAL 60 DAY AND c.ultima_compra_em >= CURDATE() - INTERVAL 90 DAY) AS a90,
                SUM(c.ultima_compra_em < CURDATE() - INTERVAL 90 DAY) AS mais90, SUM(c.ultima_compra_em IS NULL) AS nunca $de", $p);
        return [['Comprou nos últimos 30 dias', (int) $r['a30']], ['31 a 60 dias', (int) $r['a60']], ['61 a 90 dias', (int) $r['a90']],
            ['Mais de 90 dias sem comprar', (int) $r['mais90']], ['Nunca comprou', (int) $r['nunca']]];
    }

    /** Uma linha por grupo (UF, cidade, vendedor...) */
    public static function agrupado(array $f, int $limite = 200): array
    {
        [$de, $p, $meses] = self::base($f);
        $expr = self::GRUPOS[$f['grupo']][1];
        $linhas = Banco::todos("SELECT $expr AS grupo, MIN(c.uf) AS uf, MIN(c.cidade) AS cidade, MIN(c.regiao_id) AS regiao_id, MIN(c.segmento_id) AS segmento_id,
                MIN(c.responsavel_id) AS responsavel_id, MIN(c.carteira_id) AS carteira_id, MIN(c.etapa_id) AS etapa_id,
                COUNT(*) AS clientes, SUM(COALESCE(v.total, 0) > 0) AS compraram, COALESCE(SUM(v.total), 0) AS faturamento, COALESCE(SUM(v.pedidos), 0) AS pedidos,
                SUM(COALESCE(v.meses, 0) >= 2) AS recorrentes
            $de GROUP BY grupo ORDER BY faturamento DESC, clientes DESC LIMIT " . (int) $limite, $p);
        foreach ($linhas as &$l) {
            $l['ticket'] = $l['pedidos'] ? (float) $l['faturamento'] / (int) $l['pedidos'] : 0.0;
            $l['media_cliente'] = $l['compraram'] ? (float) $l['faturamento'] / $meses / (int) $l['compraram'] : 0.0;
            $l['recorrencia_pct'] = $l['compraram'] ? (int) $l['recorrentes'] / (int) $l['compraram'] * 100 : 0.0;
        }
        return $linhas;
    }

    /** Lista de lojas (paginada), com os meses em que cada uma comprou */
    public static function lista(array $f, int $pagina = 1, int $porPagina = 50): array
    {
        [$de, $p, $meses] = self::base($f);
        $total = (int) Banco::valor("SELECT COUNT(*) $de", $p);
        $paginas = max(1, (int) ceil($total / $porPagina));
        $pagina = min(max(1, $pagina), $paginas);
        $itens = Banco::todos(self::camposLista($meses) . " $de ORDER BY " . self::ORDENS[$f['ordem']][1] . " LIMIT $porPagina OFFSET " . (($pagina - 1) * $porPagina), $p);
        // Mapa dos meses: em quais meses do período cada loja da página comprou (e quanto)
        if ($itens) {
            [, $ini, $fim] = self::periodos()[$f['periodo']];
            $ids = implode(',', array_map(fn($i) => (int) $i['id'], $itens));
            $porLoja = [];
            foreach (Banco::todos("SELECT cliente_id, DATE_FORMAT(criado_em, '%Y-%m') AS mes, SUM(total) AS valor FROM pedidos
                WHERE situacao = 'faturado' AND criado_em >= ? AND criado_em < ? AND cliente_id IN ($ids) GROUP BY cliente_id, mes", [$ini, $fim]) as $l) {
                $porLoja[(int) $l['cliente_id']][$l['mes']] = (float) $l['valor'];
            }
            foreach ($itens as &$i) {
                $i['por_mes'] = $porLoja[(int) $i['id']] ?? [];
            }
            unset($i);
        }
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
                COALESCE(v.total, 0) AS total, COALESCE(v.pedidos, 0) AS pedidos, COALESCE(v.meses, 0) AS meses_compra,
                CASE WHEN COALESCE(v.pedidos, 0) > 0 THEN v.total / v.pedidos ELSE 0 END AS ticket,
                COALESCE(v.total, 0) / $meses AS media";
    }

    /** Opções dos filtros (a vendedora só vê a própria carteira: sem os filtros de vendedor e carteira) */
    public static function opcoes(string $uf): array
    {
        return [
            'ufs' => \App\Models\Listas::ufsComClientes(),
            'cidades' => \App\Models\Listas::cidadesComClientes($uf),
            'regioes' => \App\Models\Listas::regioes(),
            'segmentos' => \App\Models\Listas::segmentos(),
            'vendedores' => self::veTodos() ? \App\Models\Listas::vendedores() : [],
            'carteiras' => self::veTodos() ? Banco::todos('SELECT id, nome FROM carteiras WHERE ativa = 1 ORDER BY nome') : [],
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
