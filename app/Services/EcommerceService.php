<?php
namespace App\Services;

use App\Core\Banco;
use App\Core\Config;
use App\Integrations\Integracoes;

/**
 * Números do e-commerce (Tray).
 * - sincronizar(): copia da loja para as tabelas locais (resumo diário e mais vendidos do mês).
 *   Usa "INSERT ... ON DUPLICATE KEY UPDATE": rodar de novo atualiza o dia, nunca duplica.
 * - painel(): tudo que a tela precisa, já calculado (mês atual × mês anterior, 12 meses, top produtos).
 */
class EcommerceService
{
    /** Copia os dados da loja. Primeira vez: últimos 13 meses; depois: últimos 7 dias + mês atual/anterior */
    public static function sincronizar(): array
    {
        $loja = Integracoes::ecommerce();
        $vazio = !Banco::valor('SELECT 1 FROM ecommerce_resumo_diario LIMIT 1');
        $desde = $vazio ? date('Y-m-01', strtotime('-12 months')) : date('Y-m-d', strtotime('-7 days'));

        $dias = 0;
        foreach ($loja->resumoPorDia($desde, date('Y-m-d')) as $d) {
            Banco::executar(
                'INSERT INTO ecommerce_resumo_diario (data, pedidos, faturamento, visitas, novos_clientes, origem) VALUES (?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE pedidos = VALUES(pedidos), faturamento = VALUES(faturamento), visitas = VALUES(visitas),
                     novos_clientes = VALUES(novos_clientes), origem = VALUES(origem)',
                [$d['data'], $d['pedidos'], $d['faturamento'], $d['visitas'], $d['novos_clientes'], $loja->nome()]
            );
            $dias++;
        }

        $meses = $vazio ? array_map(fn($i) => date('Y-m', strtotime(date('Y-m-01') . " -$i months")), range(0, 12)) : [date('Y-m'), date('Y-m', strtotime(date('Y-m-01') . ' -1 month'))];
        foreach ($meses as $mes) {
            Banco::executar('DELETE FROM ecommerce_produtos_mes WHERE mes = ?', [$mes]); // o ranking do mês é substituído inteiro
            foreach ($loja->produtosMaisVendidos($mes, 10) as $p) {
                Banco::inserir('ecommerce_produtos_mes', ['mes' => $mes, 'produto_ref' => mb_substr($p['produto_ref'], 0, 80),
                    'nome' => mb_substr($p['nome'], 0, 200), 'quantidade' => $p['quantidade'], 'faturamento' => $p['faturamento']]);
            }
        }
        Config::definir('ecommerce.ultima_sincronizacao', date('Y-m-d H:i:s'));
        return ['dias' => $dias, 'meses' => count($meses)];
    }

    /** Totais de um período */
    private static function totais(string $desde, string $ate): array
    {
        $r = Banco::um('SELECT COALESCE(SUM(pedidos), 0) AS pedidos, COALESCE(SUM(faturamento), 0) AS faturamento,
                SUM(visitas) AS visitas, COALESCE(SUM(novos_clientes), 0) AS novos_clientes
              FROM ecommerce_resumo_diario WHERE data BETWEEN ? AND ?', [$desde, $ate]);
        $r['ticket_medio'] = $r['pedidos'] > 0 ? $r['faturamento'] / $r['pedidos'] : 0;
        $r['conversao'] = $r['visitas'] ? $r['pedidos'] / $r['visitas'] * 100 : null;
        return $r;
    }

    /** Variação em % (null quando não dá para comparar) */
    private static function variacao(float $atual, float $anterior): ?float
    {
        return $anterior > 0 ? ($atual - $anterior) / $anterior * 100 : null;
    }

    /** Dados do painel do e-commerce para o mês (AAAA-MM) */
    public static function painel(string $mes): array
    {
        $inicio = "$mes-01";
        $fim = date('Y-m-t', strtotime($inicio));
        $ateHoje = min($fim, date('Y-m-d'));
        // Mês anterior até o MESMO dia (comparação justa no meio do mês)
        $diasCorridos = (int) date('j', strtotime($ateHoje));
        $inicioAnt = date('Y-m-01', strtotime("$inicio -1 month"));
        $fimAnt = date('Y-m-d', min(strtotime(date('Y-m-t', strtotime($inicioAnt))), strtotime("$inicioAnt +" . ($diasCorridos - 1) . ' days')));

        $atual = self::totais($inicio, $ateHoje);
        $anterior = self::totais($inicioAnt, $fimAnt);
        $hoje = self::totais(date('Y-m-d'), date('Y-m-d'));

        $diario = Banco::todos('SELECT data, pedidos, faturamento FROM ecommerce_resumo_diario WHERE data BETWEEN ? AND ? ORDER BY data', [$inicio, $fim]);
        $meses = Banco::todos("SELECT DATE_FORMAT(data, '%Y-%m') AS mes, SUM(faturamento) AS faturamento, SUM(pedidos) AS pedidos
            FROM ecommerce_resumo_diario WHERE data >= ? GROUP BY mes ORDER BY mes", [date('Y-m-01', strtotime("$inicio -11 months"))]);

        return [
            'mes' => $mes, 'atual' => $atual, 'anterior' => $anterior, 'hoje' => $hoje,
            'variacao' => [
                'faturamento' => self::variacao((float) $atual['faturamento'], (float) $anterior['faturamento']),
                'pedidos'     => self::variacao((float) $atual['pedidos'], (float) $anterior['pedidos']),
                'ticket'      => self::variacao((float) $atual['ticket_medio'], (float) $anterior['ticket_medio']),
                'novos'       => self::variacao((float) $atual['novos_clientes'], (float) $anterior['novos_clientes']),
            ],
            // Projeção simples do mês: média por dia × dias do mês
            'projecao' => $mes === date('Y-m') && $diasCorridos > 0 ? $atual['faturamento'] / $diasCorridos * (int) date('t', strtotime($inicio)) : null,
            'diario'   => $diario,
            'meses'    => $meses,
            'produtos' => Banco::todos('SELECT * FROM ecommerce_produtos_mes WHERE mes = ? ORDER BY faturamento DESC LIMIT 10', [$mes]),
            'simulado' => Config::get('ecommerce.provedor', 'fake') !== 'tray',
            'ultima_sincronizacao' => Config::get('ecommerce.ultima_sincronizacao'),
        ];
    }

    /** Vendas de um produto no período (para as campanhas do planejamento) — usa o ranking mensal */
    public static function vendasDoProduto(string $slug, string $desde, string $ate): ?array
    {
        $r = Banco::um('SELECT SUM(quantidade) AS quantidade, SUM(faturamento) AS faturamento FROM ecommerce_produtos_mes
            WHERE produto_ref = ? AND mes BETWEEN ? AND ?', [$slug, substr($desde, 0, 7), substr($ate, 0, 7)]);
        return $r && $r['quantidade'] !== null ? $r : null;
    }
}
