<?php
namespace App\Services;

use App\Core\Banco;

/**
 * Metas do mês (individual e da equipe) e suas projeções.
 *
 * Mesma regra da Central de Televendas:
 *   - Dias de trabalho = dias da semana escolhidos, menos folgas/feriados.
 *   - Hoje conta como dia completo quando é dia de trabalho.
 *   - Projeção do mês = vendido até hoje ÷ dias de trabalho decorridos × dias de trabalho do mês.
 *   - Projeção do dia (ritmo) = vendido até hoje ÷ dias de trabalho decorridos.
 *   - Necessário por dia = o que falta ÷ dias de trabalho restantes (incluindo hoje).
 */
class MetaService
{
    public const DIAS_SEMANA = [1 => 'Seg', 2 => 'Ter', 3 => 'Qua', 4 => 'Qui', 5 => 'Sex', 6 => 'Sáb', 0 => 'Dom'];

    /** Mês válido (AAAA-MM) ou o mês atual */
    public static function mesValido(?string $mes): string
    {
        return preg_match('/^(20\d{2})-(0[1-9]|1[0-2])$/', (string) $mes) ? $mes : date('Y-m');
    }

    /** Todas as datas do mês: ['2026-11-01', ...] */
    public static function diasDoMes(string $mes): array
    {
        $total = (int) date('t', strtotime("$mes-01"));
        return array_map(fn($d) => sprintf('%s-%02d', $mes, $d), range(1, $total));
    }

    /**
     * Calcula andamento e projeções.
     * $vendido = total vendido até hoje; $temVendas = existe algum registro (sem registro não há projeção).
     */
    public static function calcular(string $mes, float $meta, float $vendido, array $diasSemana, array $folgas, bool $temVendas, ?string $hoje = null): array
    {
        $hoje ??= date('Y-m-d');
        $trabalho = array_values(array_filter(self::diasDoMes($mes), fn($d) => in_array((int) date('w', strtotime($d)), $diasSemana, true) && !in_array($d, $folgas, true)));
        $decorridos = count(array_filter($trabalho, fn($d) => $d <= $hoje));
        $restantes = count(array_filter($trabalho, fn($d) => $d >= $hoje));
        $passado = $mes < substr($hoje, 0, 7);
        $futuro = $mes > substr($hoje, 0, 7);
        $falta = max(0, $meta - $vendido);

        $ritmo = (!$futuro && $decorridos && $temVendas) ? $vendido / $decorridos : null;
        return [
            'meta'          => $meta,
            'vendido'       => $vendido,
            'percentual'    => $meta > 0 ? $vendido / $meta * 100 : 0,
            'falta'         => $falta,
            'acima'         => max(0, $vendido - $meta),
            'atingida'      => $meta > 0 && $vendido >= $meta,
            'dias_trabalho' => count($trabalho),
            'decorridos'    => $decorridos,
            'restantes'     => $restantes,
            'ritmo_dia'     => $ritmo,                                                     // projeção do dia
            'projecao_mes'  => $passado ? $vendido : ($ritmo !== null ? $ritmo * count($trabalho) : null),
            'necessario_dia' => (!$passado && $restantes) ? $falta / $restantes : null,
            'passado'       => $passado,
            'futuro'        => $futuro,
            'trabalho'      => $trabalho,
        ];
    }

    // ------------------------------------------------------------------
    // Individual (Meu mês)
    // ------------------------------------------------------------------

    /** Configuração do mês da pessoa (meta, dias de trabalho, folgas). Herda os dias do mês anterior se ainda não existir. */
    public static function meuMes(int $usuarioId, string $mes): array
    {
        $linha = Banco::um('SELECT * FROM meu_mes WHERE usuario_id = ? AND mes = ?', [$usuarioId, $mes]);
        if (!$linha) {
            $anterior = Banco::um('SELECT dias_semana FROM meu_mes WHERE usuario_id = ? AND mes < ? ORDER BY mes DESC LIMIT 1', [$usuarioId, $mes]);
            $linha = ['id' => null, 'meta' => 0, 'dias_semana' => $anterior['dias_semana'] ?? '1,2,3,4,5', 'folgas' => '[]', 'anotacoes' => ''];
        }
        $linha['dias_semana'] = array_map('intval', array_filter(explode(',', (string) $linha['dias_semana']), 'strlen'));
        $linha['folgas'] = json_decode((string) $linha['folgas'], true) ?: [];
        return $linha;
    }

    /** Indicadores individuais do mês */
    public static function indicadores(int $usuarioId, string $mes): array
    {
        $cfg = self::meuMes($usuarioId, $mes);
        $hoje = date('Y-m-d');
        $vendas = Banco::todos('SELECT * FROM vendas_registradas WHERE usuario_id = ? AND data LIKE ? ORDER BY data DESC, id DESC', [$usuarioId, "$mes-%"]);
        $ateHoje = array_filter($vendas, fn($v) => $v['data'] <= $hoje);
        $total = array_sum(array_column($ateHoje, 'valor'));
        $calc = self::calcular($mes, (float) $cfg['meta'], $total, $cfg['dias_semana'], $cfg['folgas'], (bool) $ateHoje);

        $porDia = [];
        foreach ($vendas as $v) {
            $porDia[$v['data']] = ($porDia[$v['data']] ?? 0) + $v['valor'];
        }
        arsort($porDia);
        $mesAnterior = date('Y-m', strtotime("$mes-01 -1 month"));
        $totalAnterior = (float) Banco::valor('SELECT COALESCE(SUM(valor), 0) FROM vendas_registradas WHERE usuario_id = ? AND data LIKE ?', [$usuarioId, "$mesAnterior-%"]);

        return $calc + [
            'config'          => $cfg,
            'vendas'          => $vendas,
            'qtd_vendas'      => count($vendas),
            'ticket_medio'    => $vendas ? array_sum(array_column($vendas, 'valor')) / count($vendas) : 0,
            'maior_venda'     => $vendas ? max(array_column($vendas, 'valor')) : 0,
            'melhor_dia'      => $porDia ? ['data' => array_key_first($porDia), 'valor' => reset($porDia)] : null,
            'dias_com_venda'  => count($porDia),
            'por_dia'         => $porDia,
            'mes_anterior'    => $totalAnterior,
            'variacao'        => $totalAnterior > 0 ? ($total - $totalAnterior) / $totalAnterior * 100 : null,
        ];
    }

    // ------------------------------------------------------------------
    // Equipe
    // ------------------------------------------------------------------

    /** Meta da equipe do mês com projeções (null se o gestor ainda não cadastrou) */
    public static function equipe(string $mes): ?array
    {
        $linha = Banco::um('SELECT m.*, u.nome AS atualizado_por_nome FROM metas_equipe m LEFT JOIN usuarios u ON u.id = m.atualizado_por WHERE m.mes = ?', [$mes]);
        if (!$linha) {
            return null;
        }
        $dias = array_map('intval', array_filter(explode(',', $linha['dias_semana']), 'strlen'));
        $folgas = json_decode((string) $linha['folgas'], true) ?: [];
        $lancamentos = Banco::todos('SELECT l.*, u.nome AS usuario_nome FROM metas_equipe_lancamentos l LEFT JOIN usuarios u ON u.id = l.usuario_id WHERE l.mes = ? ORDER BY l.data, l.id', [$mes]);
        // Para a projeção, o vendido vale até a data do último lançamento (o gestor informa "até o momento")
        $referencia = $lancamentos ? end($lancamentos)['data'] : date('Y-m-d');
        $hoje = min(date('Y-m-d'), max($referencia, "$mes-01"));
        if ($mes < date('Y-m')) {
            $hoje = date('Y-m-d');
        }
        return self::calcular($mes, (float) $linha['meta'], (float) $linha['vendido'], $dias, $folgas, (float) $linha['vendido'] > 0, $hoje) + [
            'linha' => $linha, 'dias_semana' => $dias, 'folgas' => $folgas, 'lancamentos' => $lancamentos, 'referencia' => $referencia,
        ];
    }
}
