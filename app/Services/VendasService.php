<?php
namespace App\Services;

use App\Core\Auth;
use App\Core\Banco;
use App\Core\Config;

/**
 * Números de vendas para o Painel, as Metas e a Recompra.
 *
 * "Vendido" = pedidos FATURADOS (com nota), pela data do faturamento — assim o painel bate com o ERP.
 * A função de um pedido é a função do cliente na Carteira (fila/carteira dona do cliente).
 *
 * Filtros aceitos ($f): de, ate (AAAA-MM-DD), vendedor (usuário), funcao, regiao, uf, origem.
 */
class VendasService
{
    public const PERIODOS = ['mes' => 'Mês atual', 'mes_anterior' => 'Mês anterior', '3m' => 'Últimos 3 meses', '6m' => 'Últimos 6 meses',
        '12m' => 'Últimos 12 meses', 'livre' => 'Escolher datas'];

    /** Converte a escolha de período em datas [de, ate] */
    public static function periodo(string $chave, string $de = '', string $ate = ''): array
    {
        $hoje = date('Y-m-d');
        return match ($chave) {
            'mes_anterior' => [date('Y-m-01', strtotime('first day of last month')), date('Y-m-t', strtotime('first day of last month'))],
            '3m'  => [date('Y-m-01', strtotime('first day of -2 months')), $hoje],
            '6m'  => [date('Y-m-01', strtotime('first day of -5 months')), $hoje],
            '12m' => [date('Y-m-01', strtotime('first day of -11 months')), $hoje],
            'livre' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $de) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $ate) && $de <= $ate ? [$de, $ate] : [date('Y-m-01'), $hoje],
            default => [date('Y-m-01'), $hoje],
        };
    }

    /** Período anterior do mesmo tamanho (para comparar) */
    public static function anterior(string $de, string $ate): array
    {
        $dias = (int) round((strtotime($ate) - strtotime($de)) / 86400) + 1;
        // Período que começa no dia 1: compara com o mesmo trecho do mês anterior
        if (substr($de, 8) === '01') {
            $meses = (int) ((int) substr($ate, 0, 4) * 12 + (int) substr($ate, 5, 2) - (int) substr($de, 0, 4) * 12 - (int) substr($de, 5, 2)) + 1;
            $deAnt = date('Y-m-01', strtotime("$de -$meses months"));
            $fimMes = date('Y-m-t', strtotime(date('Y-m-01', strtotime("$ate -$meses months"))));
            $ateAnt = min($fimMes, date('Y-m-d', strtotime("$ate -$meses months")));
            return [$deAnt, $ateAnt];
        }
        return [date('Y-m-d', strtotime("$de -$dias days")), date('Y-m-d', strtotime("$de -1 day"))];
    }

    /** WHERE dos pedidos faturados com os filtros */
    private static function where(array $f, bool $comDatas = true): array
    {
        $w = ["p.situacao = 'faturado'"];
        $par = [];
        if ($comDatas) {
            $w[] = 'p.faturado_em >= ? AND p.faturado_em < ? + INTERVAL 1 DAY';
            array_push($par, $f['de'], $f['ate']);
        }
        foreach (['vendedor' => 'p.vendedor_id', 'funcao' => 'c.funcao_id', 'regiao' => 'c.regiao_id', 'uf' => 'c.uf', 'origem' => 'p.origem'] as $campo => $coluna) {
            if (($f[$campo] ?? '') !== '') {
                $w[] = "$coluna = ?";
                $par[] = $f[$campo];
            }
        }
        return [implode(' AND ', $w), $par];
    }

    private const BASE = 'FROM pedidos p JOIN clientes c ON c.id = p.cliente_id';

    /** Cards: faturamento, pedidos, ticket, clientes e recorrência */
    public static function resumo(array $f): array
    {
        [$w, $par] = self::where($f);
        $r = Banco::um('SELECT COALESCE(SUM(p.total), 0) AS faturamento, COUNT(*) AS pedidos, COUNT(DISTINCT p.cliente_id) AS clientes ' . self::BASE . " WHERE $w", $par);
        $r['recorrentes'] = (int) Banco::valor('SELECT COUNT(*) FROM (SELECT p.cliente_id ' . self::BASE . " WHERE $w GROUP BY p.cliente_id HAVING COUNT(*) >= 2) x", $par);
        $r['faturamento'] = (float) $r['faturamento'];
        $r['pedidos'] = (int) $r['pedidos'];
        $r['clientes'] = (int) $r['clientes'];
        $r['ticket'] = $r['pedidos'] ? $r['faturamento'] / $r['pedidos'] : 0.0;
        $r['recorrencia'] = $r['clientes'] ? $r['recorrentes'] / $r['clientes'] * 100 : 0.0;
        return $r;
    }

    /** Pedidos aprovados ainda não faturados (com os mesmos filtros, sem datas) */
    public static function aFaturar(array $f): array
    {
        [$w, $par] = self::where($f, false);
        $w = str_replace("p.situacao = 'faturado'", "p.situacao = 'aprovado'", $w);
        return Banco::um('SELECT COALESCE(SUM(p.total), 0) AS valor, COUNT(*) AS pedidos ' . self::BASE . " WHERE $w", $par);
    }

    /** Faturamento mês a mês (12 meses até o fim do período) */
    public static function porMes(array $f): array
    {
        $g = ['de' => date('Y-m-01', strtotime(substr($f['ate'], 0, 7) . '-01 -11 months')), 'ate' => $f['ate']] + $f;
        [$w, $par] = self::where($g);
        $linhas = array_column(Banco::todos("SELECT DATE_FORMAT(p.faturado_em, '%Y-%m') AS mes, SUM(p.total) AS valor " . self::BASE . " WHERE $w GROUP BY mes", $par), 'valor', 'mes');
        $metas = array_column(Banco::todos('SELECT mes, meta FROM metas_equipe WHERE mes >= ?', [substr($g['de'], 0, 7)]), 'meta', 'mes');
        $saida = [];
        for ($i = 11; $i >= 0; $i--) {
            $mes = date('Y-m', strtotime(substr($f['ate'], 0, 7) . "-01 -$i months"));
            $saida[] = ['mes' => $mes, 'valor' => (float) ($linhas[$mes] ?? 0), 'meta' => isset($metas[$mes]) ? (float) $metas[$mes] : null];
        }
        return $saida;
    }

    /** Ranking por uma dimensão: vendedor, funcao, uf, origem, cliente, produto */
    public static function ranking(array $f, string $por, int $limite = 50): array
    {
        [$w, $par] = self::where($f);
        $sql = match ($por) {
            'vendedor' => 'SELECT p.vendedor_id AS id, COALESCE(u.nome, "Sem vendedor") AS nome, SUM(p.total) AS valor, COUNT(*) AS pedidos, COUNT(DISTINCT p.cliente_id) AS clientes '
                . self::BASE . " LEFT JOIN usuarios u ON u.id = p.vendedor_id WHERE $w GROUP BY p.vendedor_id, u.nome",
            'funcao'   => 'SELECT c.funcao_id AS id, COALESCE(fn.nome, "Sem função") AS nome, SUM(p.total) AS valor, COUNT(*) AS pedidos, COUNT(DISTINCT p.cliente_id) AS clientes '
                . self::BASE . " LEFT JOIN funcoes fn ON fn.id = c.funcao_id WHERE $w GROUP BY c.funcao_id, fn.nome",
            'uf'       => 'SELECT c.uf AS id, c.uf AS nome, SUM(p.total) AS valor, COUNT(*) AS pedidos, COUNT(DISTINCT p.cliente_id) AS clientes ' . self::BASE . " WHERE $w GROUP BY c.uf",
            'origem'   => 'SELECT p.origem AS id, p.origem AS nome, SUM(p.total) AS valor, COUNT(*) AS pedidos, COUNT(DISTINCT p.cliente_id) AS clientes ' . self::BASE . " WHERE $w GROUP BY p.origem",
            'cliente'  => 'SELECT c.id, COALESCE(NULLIF(c.nome_fantasia, ""), c.razao_social) AS nome, c.cidade, c.uf, SUM(p.total) AS valor, COUNT(*) AS pedidos ' . self::BASE . " WHERE $w GROUP BY c.id",
            'produto'  => 'SELECT i.produto_id AS id, i.nome, SUM(i.total) AS valor, SUM(i.quantidade) AS quantidade ' . self::BASE . " JOIN pedido_itens i ON i.pedido_id = p.id WHERE $w GROUP BY i.produto_id, i.nome",
        };
        $linhas = Banco::todos("$sql ORDER BY valor DESC LIMIT " . (int) $limite, $par);
        foreach ($linhas as &$l) {
            $l['valor'] = (float) $l['valor'];
            $l['ticket'] = !empty($l['pedidos']) ? $l['valor'] / $l['pedidos'] : 0;
        }
        return $linhas;
    }

    /** Clientes sem compra há 30/60/90 dias (faixas de Configurações) */
    public static function semCompra(array $f): array
    {
        $faixas = array_map('intval', (array) Config::get('carteira.faixas_sem_compra', [30, 60, 90]));
        $w = ["c.excluido_em IS NULL", "c.tipo = 'cliente'", 'c.ultima_compra_em IS NOT NULL'];
        $par = [];
        foreach (['vendedor' => 'c.responsavel_id', 'funcao' => 'c.funcao_id', 'regiao' => 'c.regiao_id', 'uf' => 'c.uf'] as $campo => $coluna) {
            if (($f[$campo] ?? '') !== '') {
                $w[] = "$coluna = ?";
                $par[] = $f[$campo];
            }
        }
        $saida = [];
        foreach ($faixas as $dias) {
            $saida[$dias] = (int) Banco::valor('SELECT COUNT(*) FROM clientes c WHERE ' . implode(' AND ', $w) . ' AND c.ultima_compra_em < CURDATE() - INTERVAL ? DAY', [...$par, $dias]);
        }
        return $saida;
    }

    /** Faturado de um vendedor ou de uma função num mês (Metas e Meu mês) */
    public static function faturadoNoMes(string $mes, string $tipo, int $alvoId): array
    {
        $f = ['de' => "$mes-01", 'ate' => date('Y-m-t', strtotime("$mes-01")), $tipo === 'funcao' ? 'funcao' : 'vendedor' => $alvoId];
        $r = self::resumo($f);
        return ['valor' => $r['faturamento'], 'pedidos' => $r['pedidos']];
    }

    // ------------------------------------------------------------------
    // Recompra
    // ------------------------------------------------------------------

    /**
     * Clientes que passaram do próprio tempo de recompra (última compra + intervalo médio + tolerância < hoje),
     * ainda dentro da carteira ativa (antes de irem para a reativação).
     * Vendedor: só a carteira dele. Gestor: todos (pode filtrar por vendedor).
     */
    public static function recompra(?int $vendedor = null): array
    {
        $tolerancia = max(0, (int) Config::get('recompra.tolerancia_dias', 3));
        $w = ["c.excluido_em IS NULL", "c.tipo = 'cliente'", 'c.intervalo_medio_dias > 0',
            'c.ultima_compra_em + INTERVAL (c.intervalo_medio_dias + ?) DAY < CURDATE()',
            'c.ultima_compra_em >= CURDATE() - INTERVAL ? DAY'];
        $par = [$tolerancia, (int) Config::get('carteira.dias_inatividade', 90)];
        if (!Auth::pode('carteira.ver_todas')) {
            $w[] = 'c.responsavel_id = ?';
            $par[] = Auth::id();
        } elseif ($vendedor) {
            $w[] = 'c.responsavel_id = ?';
            $par[] = $vendedor;
        }
        $clientes = Banco::todos('SELECT c.id, c.razao_social, c.nome_fantasia, c.cidade, c.uf, c.telefone, c.whatsapp, c.ultima_compra_em, c.intervalo_medio_dias,
                DATEDIFF(CURDATE(), c.ultima_compra_em + INTERVAL c.intervalo_medio_dias DAY) AS dias_atraso, DATEDIFF(CURDATE(), c.ultima_compra_em) AS dias_desde,
                u.nome AS responsavel,
                (SELECT AVG(total) FROM pedidos p WHERE p.cliente_id = c.id AND p.situacao = \'faturado\') AS ticket,
                (SELECT MAX(id) FROM pedidos p WHERE p.cliente_id = c.id AND p.situacao IN (\'faturado\', \'aprovado\')) AS ultimo_pedido,
                (SELECT COUNT(*) FROM tarefas t WHERE t.cliente_id = c.id AND t.concluida_em IS NULL) AS tarefas_abertas
            FROM clientes c LEFT JOIN usuarios u ON u.id = c.responsavel_id WHERE ' . implode(' AND ', $w), $par);
        foreach ($clientes as &$c) {
            $c['ticket'] = (float) $c['ticket'];
            // Prioridade: valor do cliente × atraso proporcional ao intervalo
            $c['prioridade'] = $c['ticket'] * (1 + (int) $c['dias_atraso'] / max(1, (int) $c['intervalo_medio_dias']));
            $c['produtos'] = array_column(Banco::todos('SELECT i.nome, SUM(i.quantidade) AS q FROM pedido_itens i JOIN pedidos p ON p.id = i.pedido_id
                WHERE p.cliente_id = ? AND p.situacao = \'faturado\' GROUP BY i.nome ORDER BY q DESC LIMIT 3', [$c['id']]), 'nome');
        }
        usort($clientes, fn($a, $b) => $b['prioridade'] <=> $a['prioridade']);
        return $clientes;
    }

    /** Quantos clientes da carteira do usuário estão na recompra (Início/One) */
    public static function qtdRecompra(): int
    {
        return count(self::recompra());
    }
}
