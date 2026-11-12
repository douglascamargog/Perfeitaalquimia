<?php
namespace App\Models;

use App\Core\Auth;
use App\Core\Banco;

/**
 * Consultas da Cobrança (tabelas cobrancas e cobranca_titulos).
 * Quem tem cobranca.ver enxerga todos os casos (a equipe de cobrança divide a fila).
 */
class Cobranca
{
    /** Filtros aceitos na URL */
    public const FILTROS = ['q', 'etapa', 'responsavel', 'faixa', 'uf', 'situacao', 'minhas'];

    /** Faixas de atraso: rótulo => [mínimo, máximo] de dias */
    public const FAIXAS = ['ate30' => ['Até 30 dias', 0, 30], '31a60' => ['31 a 60 dias', 31, 60], '61a90' => ['61 a 90 dias', 61, 90], 'mais90' => ['Mais de 90 dias', 91, 100000]];

    private const CAMPOS = "cb.*, DATEDIFF(CURDATE(), cb.vencimento_mais_antigo) AS dias_atraso,
        c.razao_social, c.nome_fantasia, c.cnpj, c.cidade, c.uf, c.telefone, c.whatsapp, c.email,
        e.nome AS etapa, e.cor AS etapa_cor, e.tipo AS etapa_tipo, u.nome AS responsavel";

    private const JUNCOES = 'FROM cobrancas cb
        JOIN clientes c ON c.id = cb.cliente_id
        JOIN cobranca_etapas e ON e.id = cb.etapa_id
        LEFT JOIN usuarios u ON u.id = cb.responsavel_id';

    /** Lê os filtros da URL (só os conhecidos) */
    public static function filtrosDaUrl(): array
    {
        $f = [];
        foreach (self::FILTROS as $nome) {
            $f[$nome] = trim((string) ($_GET[$nome] ?? ''));
        }
        return $f;
    }

    /** WHERE + parâmetros a partir dos filtros */
    private static function montarWhere(array $f): array
    {
        $w = ['c.excluido_em IS NULL'];
        $p = [];
        $w[] = ($f['situacao'] ?? '') === 'encerrada' ? "cb.situacao = 'encerrada'" : "cb.situacao = 'aberta'";
        if (($f['q'] ?? '') !== '') {
            $digitos = preg_replace('/\D/', '', $f['q']);
            $w[] = '(c.razao_social LIKE ? OR c.nome_fantasia LIKE ?' . ($digitos !== '' ? ' OR c.cnpj LIKE ?' : '') . ')';
            array_push($p, "%{$f['q']}%", "%{$f['q']}%");
            if ($digitos !== '') {
                $p[] = "$digitos%";
            }
        }
        if (($f['etapa'] ?? '') !== '') {
            $w[] = 'cb.etapa_id = ?';
            $p[] = (int) $f['etapa'];
        }
        if (($f['responsavel'] ?? '') !== '') {
            $w[] = 'cb.responsavel_id = ?';
            $p[] = (int) $f['responsavel'];
        }
        if (($f['minhas'] ?? '') === '1') {
            $w[] = 'cb.responsavel_id = ?';
            $p[] = Auth::id();
        }
        if (($f['uf'] ?? '') !== '') {
            $w[] = 'c.uf = ?';
            $p[] = $f['uf'];
        }
        if (isset(self::FAIXAS[$f['faixa'] ?? ''])) {
            [, $min, $max] = self::FAIXAS[$f['faixa']];
            $w[] = 'DATEDIFF(CURDATE(), cb.vencimento_mais_antigo) BETWEEN ? AND ?';
            array_push($p, $min, $max);
        }
        return [implode(' AND ', $w), $p];
    }

    /** Lista paginada (mais atrasados primeiro) */
    public static function listar(array $f, int $pagina, int $porPagina = 50): array
    {
        [$where, $p] = self::montarWhere($f);
        $total = (int) Banco::valor('SELECT COUNT(*) ' . self::JUNCOES . " WHERE $where", $p);
        $paginas = max(1, (int) ceil($total / $porPagina));
        $pagina = min(max(1, $pagina), $paginas);
        $itens = Banco::todos('SELECT ' . self::CAMPOS . ' ' . self::JUNCOES . " WHERE $where
            ORDER BY cb.vencimento_mais_antigo IS NULL, cb.vencimento_mais_antigo, cb.id LIMIT $porPagina OFFSET " . (($pagina - 1) * $porPagina), $p);
        return ['itens' => $itens, 'total' => $total, 'pagina' => $pagina, 'paginas' => $paginas, 'por_pagina' => $porPagina];
    }

    /** Todos os casos dos filtros (para exportar / imprimir), limitado para não pesar */
    public static function todos(array $f, int $limite = 5000): array
    {
        [$where, $p] = self::montarWhere($f);
        return Banco::todos('SELECT ' . self::CAMPOS . ' ' . self::JUNCOES . " WHERE $where ORDER BY cb.vencimento_mais_antigo, cb.id LIMIT $limite", $p);
    }

    /**
     * Colunas do funil: cada etapa ativa com até $porColuna cards.
     * Colunas de encerramento mostram só os casos fechados nos últimos 30 dias.
     */
    public static function funil(array $f, int $porColuna = 40): array
    {
        [$whereAbertos, $pAbertos] = self::montarWhere(['situacao' => ''] + $f);
        [$whereFechados, $pFechados] = self::montarWhere(['situacao' => 'encerrada'] + $f);
        $whereFechados .= ' AND cb.encerrada_em >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)';
        $colunas = [];
        foreach (Listas::etapasCobranca() as $etapa) {
            $encerra = in_array($etapa['tipo'], \App\Services\CobrancaService::TIPOS_ENCERRAMENTO, true);
            [$where, $p] = $encerra ? [$whereFechados, $pFechados] : [$whereAbertos, $pAbertos];
            $where .= ' AND cb.etapa_id = ?';
            $p[] = (int) $etapa['id'];
            $colunas[] = [
                'etapa'   => $etapa,
                'encerra' => $encerra,
                'total'   => (int) Banco::valor('SELECT COUNT(*) ' . self::JUNCOES . " WHERE $where", $p),
                'valor'   => (float) Banco::valor('SELECT COALESCE(SUM(cb.valor_total), 0) ' . self::JUNCOES . " WHERE $where", $p),
                'cards'   => Banco::todos('SELECT ' . self::CAMPOS . ' ' . self::JUNCOES . " WHERE $where ORDER BY cb.vencimento_mais_antigo, cb.id LIMIT $porColuna", $p),
            ];
        }
        return $colunas;
    }

    /** Números do topo: total em aberto, casos, faixas de atraso, promessas de hoje */
    public static function resumo(): array
    {
        $r = Banco::um("SELECT COUNT(*) AS casos, COALESCE(SUM(valor_total), 0) AS total,
                SUM(DATEDIFF(CURDATE(), vencimento_mais_antigo) > 30) AS mais30,
                SUM(DATEDIFF(CURDATE(), vencimento_mais_antigo) > 60) AS mais60,
                SUM(DATEDIFF(CURDATE(), vencimento_mais_antigo) > 90) AS mais90,
                SUM(promessa_em = CURDATE()) AS promessas_hoje,
                SUM(responsavel_id = ?) AS meus
              FROM cobrancas WHERE situacao = 'aberta'", [Auth::id()]);
        $r['recuperado_mes'] = (float) Banco::valor("SELECT COALESCE(SUM(t.valor), 0) FROM cobranca_titulos t
            WHERE t.pago_em >= DATE_FORMAT(CURDATE(), '%Y-%m-01')");
        $r['sem_contato'] = (int) Banco::valor("SELECT COUNT(*) FROM cobrancas cb JOIN cobranca_etapas e ON e.id = cb.etapa_id
            WHERE cb.situacao = 'aberta' AND e.tipo = 'sem_contato'");
        return array_map(fn($v) => $v ?? 0, $r);
    }

    /** Um caso com os dados do cliente */
    public static function buscar(int $id): ?array
    {
        return Banco::um('SELECT ' . self::CAMPOS . ', c.instagram, c.facebook, c.site, c.endereco, c.bairro, c.ultimo_contato_em AS cliente_ultimo_contato,
            c.responsavel_id AS vendedor_id, (SELECT nome FROM usuarios WHERE id = c.responsavel_id) AS vendedor ' . self::JUNCOES . ' WHERE cb.id = ?', [$id]);
    }

    /** Títulos do caso (em aberto primeiro) */
    public static function titulos(int $cobrancaId): array
    {
        return Banco::todos('SELECT *, DATEDIFF(CURDATE(), vencimento) AS dias FROM cobranca_titulos WHERE cobranca_id = ? ORDER BY pago_em IS NOT NULL, vencimento', [$cobrancaId]);
    }

    /** Resumo do caso aberto de um cliente (para o selo na ficha da Carteira) */
    public static function doCliente(int $clienteId): ?array
    {
        return Banco::um("SELECT cb.id, cb.valor_total, DATEDIFF(CURDATE(), cb.vencimento_mais_antigo) AS dias_atraso, e.nome AS etapa
            FROM cobrancas cb JOIN cobranca_etapas e ON e.id = cb.etapa_id WHERE cb.cliente_id = ? AND cb.situacao = 'aberta' ORDER BY cb.id DESC LIMIT 1", [$clienteId]);
    }

    /** Pessoas que podem receber casos de cobrança (perfil Financeiro/Gestor/Admin ou função Cobrança) */
    public static function responsaveis(): array
    {
        return Banco::todos("SELECT DISTINCT u.id, u.nome FROM usuarios u
            JOIN perfis p ON p.id = u.perfil_id
            LEFT JOIN usuario_funcoes uf ON uf.usuario_id = u.id
            LEFT JOIN funcoes f ON f.id = uf.funcao_id
            WHERE u.ativo = 1 AND (p.slug IN ('financeiro','gestor','admin') OR f.slug = 'cobranca') ORDER BY u.nome");
    }
}
