<?php
namespace App\Services;

use App\Core\Auditoria;
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

    // ------------------------------------------------------------------
    // Metas por vendedor e por função (Gestão › Metas)
    // ------------------------------------------------------------------

    /** Dias de trabalho da equipe no mês (os da meta da equipe; padrão seg–sex) */
    private static function diasEquipe(string $mes): array
    {
        $dias = Banco::valor('SELECT dias_semana FROM metas_equipe WHERE mes = ?', [$mes]);
        $lista = array_map('intval', array_filter(explode(',', (string) $dias), 'strlen'));
        return $lista ?: [1, 2, 3, 4, 5];
    }

    /** Quem aparece na grade: vendedores (pessoas com função comercial) ou funções ativas */
    public static function alvos(string $tipo): array
    {
        if ($tipo === 'funcao') {
            return Banco::todos("SELECT id, nome FROM funcoes WHERE ativa = 1 AND slug NOT IN ('marketing', 'producao', 'cobranca') ORDER BY ordem, nome");
        }
        // Perfil Vendedor, ou quem já tem pedido/meta como vendedor (ex.: um gestor que também vende)
        return Banco::todos("SELECT u.id, u.nome FROM usuarios u JOIN perfis p ON p.id = u.perfil_id WHERE u.ativo = 1 AND (p.slug = 'vendedor'
            OR EXISTS (SELECT 1 FROM pedidos pe WHERE pe.vendedor_id = u.id) OR EXISTS (SELECT 1 FROM metas m WHERE m.tipo = 'vendedor' AND m.alvo_id = u.id))
            ORDER BY u.nome");
    }

    /** Grade do mês: meta, faturado (pedidos faturados), %, projeção e necessário por dia */
    public static function grade(string $mes, string $tipo): array
    {
        $metas = array_column(Banco::todos('SELECT alvo_id, valor FROM metas WHERE mes = ? AND tipo = ?', [$mes, $tipo]), 'valor', 'alvo_id');
        $dias = self::diasEquipe($mes);
        $linhas = [];
        foreach (self::alvos($tipo) as $a) {
            $fat = VendasService::faturadoNoMes($mes, $tipo, (int) $a['id']);
            $meta = (float) ($metas[$a['id']] ?? 0);
            $linhas[] = ['id' => (int) $a['id'], 'nome' => $a['nome'], 'pedidos' => $fat['pedidos']]
                + self::calcular($mes, $meta, $fat['valor'], $dias, [], $fat['pedidos'] > 0);
        }
        return $linhas;
    }

    /** Grava (ou apaga, se vazio/zero) a meta de um alvo. Devolve true se mudou */
    public static function definirMeta(string $mes, string $tipo, int $alvoId, ?float $valor, ?int $usuarioId): bool
    {
        $antes = Banco::um('SELECT id, valor FROM metas WHERE mes = ? AND tipo = ? AND alvo_id = ?', [$mes, $tipo, $alvoId]);
        if (!$valor) {
            if ($antes) {
                Banco::executar('UPDATE metas SET valor = 0 WHERE id = ?', [$antes['id']]);
                Auditoria::registrar('meta', (int) $antes['id'], 'editar', ['valor' => $antes['valor']], ['valor' => 0]);
                return true;
            }
            return false;
        }
        if ($antes && (float) $antes['valor'] === $valor) {
            return false;
        }
        if ($antes) {
            Banco::atualizar('metas', (int) $antes['id'], ['valor' => $valor]);
            Auditoria::registrar('meta', (int) $antes['id'], 'editar', ['valor' => $antes['valor']], ['valor' => $valor, 'mes' => $mes, 'tipo' => $tipo, 'alvo_id' => $alvoId]);
        } else {
            $id = Banco::inserir('metas', ['mes' => $mes, 'tipo' => $tipo, 'alvo_id' => $alvoId, 'valor' => $valor, 'criado_por' => $usuarioId]);
            Auditoria::registrar('meta', $id, 'criar', null, ['valor' => $valor, 'mes' => $mes, 'tipo' => $tipo, 'alvo_id' => $alvoId]);
        }
        return true;
    }

    /** Copia as metas do mês anterior para os alvos que ainda não têm meta neste mês. Devolve quantas copiou */
    public static function copiarMesAnterior(string $mes, ?int $usuarioId): int
    {
        $anterior = date('Y-m', strtotime("$mes-01 -1 month"));
        $n = 0;
        foreach (Banco::todos('SELECT tipo, alvo_id, valor FROM metas WHERE mes = ? AND valor > 0', [$anterior]) as $m) {
            if (!Banco::valor('SELECT 1 FROM metas WHERE mes = ? AND tipo = ? AND alvo_id = ? AND valor > 0', [$mes, $m['tipo'], $m['alvo_id']])) {
                $n += self::definirMeta($mes, $m['tipo'], (int) $m['alvo_id'], (float) $m['valor'], $usuarioId) ? 1 : 0;
            }
        }
        return $n;
    }

    /** Histórico (6 meses): para cada alvo, se bateu a meta em cada mês */
    public static function historico(string $tipo, int $meses = 6): array
    {
        $saida = [];
        for ($i = $meses; $i >= 1; $i--) {
            $mes = date('Y-m', strtotime(date('Y-m-01') . " -$i months"));
            foreach (Banco::todos('SELECT alvo_id, valor FROM metas WHERE mes = ? AND tipo = ? AND valor > 0', [$mes, $tipo]) as $m) {
                $vendido = VendasService::faturadoNoMes($mes, $tipo, (int) $m['alvo_id'])['valor'];
                $saida[$m['alvo_id']][$mes] = ['meta' => (float) $m['valor'], 'vendido' => $vendido, 'pct' => $vendido / (float) $m['valor'] * 100];
            }
        }
        return $saida;
    }
}
