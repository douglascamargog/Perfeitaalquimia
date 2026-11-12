<?php
namespace App\Models;

use App\Core\Auth;
use App\Core\Banco;
use App\Core\Config;

/**
 * Clientes e leads: busca com filtros, paginação no servidor, contagens e dados do mapa.
 *
 * SEGURANÇA: toda consulta passa por montarWhere(), que aplica o ESCOPO do usuário:
 *   - quem tem 'carteira.ver_todas' vê todos;
 *   - os demais veem só os clientes em que são o responsável.
 */
class Cliente
{
    /** Filtros aceitos (vêm da URL: ?uf=RS&segmento=2...) */
    public const FILTROS = ['q', 'uf', 'cidade', 'regiao', 'segmento', 'etapa', 'prioridade', 'responsavel', 'funcao', 'compra', 'tipo', 'origem'];

    /** Ordenações permitidas: nome amigável => SQL */
    public const ORDENS = [
        'nome'       => 'c.razao_social ASC',
        'cidade'     => 'c.uf ASC, c.cidade ASC, c.razao_social ASC',
        'prioridade' => 'FIELD(c.prioridade, %s), c.razao_social ASC',
        'contato'    => 'c.ultimo_contato_em IS NULL, c.ultimo_contato_em DESC',
        'compra'     => 'c.ultima_compra_em IS NULL, c.ultima_compra_em DESC',
        'recentes'   => 'c.atualizado_em DESC',
    ];

    /** Lê e limpa os filtros da URL */
    public static function filtrosDe(array $entrada): array
    {
        $f = [];
        foreach (self::FILTROS as $nome) {
            $valor = $entrada[$nome] ?? '';
            $f[$nome] = is_string($valor) ? trim(mb_substr($valor, 0, 120)) : '';
        }
        return $f;
    }

    /** Monta o WHERE (com escopo de acesso) e os parâmetros */
    public static function montarWhere(array $f): array
    {
        $w = ['c.excluido_em IS NULL'];
        $p = [];

        if (!Auth::pode('carteira.ver_todas')) {
            $w[] = 'c.responsavel_id = ?';
            $p[] = Auth::id() ?? 0;
        }
        if ($f['q'] ?? '') {
            $termo = '%' . $f['q'] . '%';
            $digitos = preg_replace('/\D/', '', $f['q']);
            $w[] = '(c.razao_social LIKE ? OR c.nome_fantasia LIKE ? OR c.cidade LIKE ? OR c.endereco LIKE ?' . ($digitos !== '' ? ' OR c.cnpj LIKE ? OR c.telefone LIKE ? OR c.whatsapp LIKE ?' : '') . ')';
            array_push($p, $termo, $termo, $termo, $termo);
            if ($digitos !== '') {
                array_push($p, "%$digitos%", "%$digitos%", "%$digitos%");
            }
        }
        $simples = ['uf' => 'c.uf', 'cidade' => 'c.cidade', 'regiao' => 'c.regiao_id', 'segmento' => 'c.segmento_id', 'etapa' => 'c.etapa_id',
                    'prioridade' => 'c.prioridade', 'responsavel' => 'c.responsavel_id', 'funcao' => 'c.funcao_id', 'tipo' => 'c.tipo', 'origem' => 'c.origem_id'];
        foreach ($simples as $nome => $coluna) {
            if (($f[$nome] ?? '') !== '') {
                $w[] = "$coluna = ?";
                $p[] = $f[$nome];
            }
        }
        // Situação de compra: nunca | ativo | sem30 | sem60 | sem90 ...
        $compra = $f['compra'] ?? '';
        if ($compra === 'nunca') {
            $w[] = 'c.ultima_compra_em IS NULL';
        } elseif ($compra === 'ativo') {
            $w[] = 'c.ultima_compra_em >= CURDATE() - INTERVAL ? DAY';
            $p[] = (int) Config::get('carteira.dias_inatividade', 90);
        } elseif (preg_match('/^sem(\d+)$/', $compra, $m)) {
            $w[] = 'c.ultima_compra_em < CURDATE() - INTERVAL ? DAY';
            $p[] = (int) $m[1];
        }
        return [implode(' AND ', $w), $p];
    }

    /** Lista paginada para a tela Lista */
    public static function listar(array $f, int $pagina, int $porPagina, string $ordem = 'nome'): array
    {
        [$where, $p] = self::montarWhere($f);
        $total = (int) Banco::valor("SELECT COUNT(*) FROM clientes c WHERE $where", $p);
        $paginas = max(1, (int) ceil($total / $porPagina));
        $pagina = min(max(1, $pagina), $paginas);

        $ordemSql = self::ORDENS[$ordem] ?? self::ORDENS['nome'];
        if ($ordem === 'prioridade') {
            $ordemSql = sprintf($ordemSql, implode(',', array_map(fn($x) => Banco::pdo()->quote($x), Listas::prioridades())));
        }
        $itens = Banco::todos(
            "SELECT c.id, c.razao_social, c.nome_fantasia, c.cidade, c.uf, c.telefone, c.whatsapp, c.prioridade, c.tipo,
                    c.ultimo_contato_em, c.ultima_compra_em, c.contatado,
                    s.nome AS segmento, e.nome AS etapa, e.cor AS etapa_cor, e.tipo AS etapa_tipo, u.nome AS responsavel
               FROM clientes c
               LEFT JOIN segmentos s ON s.id = c.segmento_id
               LEFT JOIN funil_etapas e ON e.id = c.etapa_id
               LEFT JOIN usuarios u ON u.id = c.responsavel_id
              WHERE $where ORDER BY $ordemSql LIMIT $porPagina OFFSET " . (($pagina - 1) * $porPagina),
            $p
        );
        return ['itens' => $itens, 'total' => $total, 'pagina' => $pagina, 'paginas' => $paginas, 'por_pagina' => $porPagina];
    }

    /** Ficha completa de um cliente (com nomes das listas) — sem checar escopo; use podeVer() */
    public static function buscar(int $id): ?array
    {
        return Banco::um(
            'SELECT c.*, s.nome AS segmento, e.nome AS etapa, e.cor AS etapa_cor, e.tipo AS etapa_tipo, o.nome AS origem,
                    u.nome AS responsavel, f.nome AS funcao, f.slug AS funcao_slug, t.nome AS tabela_preco, r.nome AS regiao
               FROM clientes c
               LEFT JOIN segmentos s ON s.id = c.segmento_id
               LEFT JOIN funil_etapas e ON e.id = c.etapa_id
               LEFT JOIN origens o ON o.id = c.origem_id
               LEFT JOIN usuarios u ON u.id = c.responsavel_id
               LEFT JOIN funcoes f ON f.id = c.funcao_id
               LEFT JOIN tabelas_preco t ON t.id = c.tabela_preco_id
               LEFT JOIN regioes r ON r.id = c.regiao_id
              WHERE c.id = ? AND c.excluido_em IS NULL',
            [$id]
        );
    }

    /** O usuário logado pode ver/editar este cliente? */
    public static function podeVer(array $cliente): bool
    {
        return Auth::pode('carteira.ver_todas') || (int) $cliente['responsavel_id'] === Auth::id();
    }

    /** Números da visão geral (respeitando filtros e escopo) */
    public static function resumo(array $f): array
    {
        [$where, $p] = self::montarWhere($f);
        $r = Banco::um(
            "SELECT COUNT(*) AS total,
                    SUM(c.contatado = 1 OR c.tipo = 'cliente') AS contatados,
                    SUM(c.tipo = 'cliente') AS clientes,
                    SUM(e.nome IN ('Em negociação', 'Proposta enviada', 'Aguardando retorno')) AS negociacao,
                    SUM(c.contatado = 0 AND c.tipo = 'lead') AS nao_abordados,
                    SUM(c.prioridade = ?) AS alta,
                    COUNT(DISTINCT CONCAT(c.uf, c.cidade)) AS cidades
               FROM clientes c LEFT JOIN funil_etapas e ON e.id = c.etapa_id WHERE $where",
            [Listas::prioridades()[0] ?? 'Alta', ...$p]
        );
        return array_map('intval', $r);
    }

    /** Contagem agrupada por uma coluna (segmento, etapa, cidade...) */
    public static function contarPor(array $f, string $grupo, int $limite = 50): array
    {
        $grupos = [
            'segmento' => ['COALESCE(s.nome, "Sem segmento")', 'LEFT JOIN segmentos s ON s.id = c.segmento_id'],
            'etapa'    => ['e.id', 'LEFT JOIN funil_etapas e ON e.id = c.etapa_id'],
            'cidade'   => ['CONCAT(c.cidade, " / ", c.uf)', ''],
            'uf'       => ['c.uf', ''],
        ];
        [$expr, $join] = $grupos[$grupo];
        [$where, $p] = self::montarWhere($f);
        return Banco::todos("SELECT $expr AS chave, COUNT(*) AS total FROM clientes c $join WHERE $where GROUP BY chave ORDER BY total DESC LIMIT $limite", $p);
    }

    /** Contatos e pedidos por dia nos últimos N dias (gráfico "Atividade comercial") */
    public static function atividade(array $f, int $dias = 7): array
    {
        [$where, $p] = self::montarWhere($f);
        return Banco::todos(
            "SELECT DATE(ev.ocorrido_em) AS dia,
                    SUM(ev.tipo IN ('contato','ligacao','whatsapp','visita','email')) AS contatos,
                    SUM(ev.tipo = 'pedido') AS pedidos
               FROM cliente_eventos ev JOIN clientes c ON c.id = ev.cliente_id
              WHERE $where AND ev.ocorrido_em >= CURDATE() - INTERVAL ? DAY
              GROUP BY dia",
            [...$p, $dias - 1]
        );
    }

    /**
     * Dados do mapa.
     *   nivel 'uf'     → um círculo por estado, com a contagem
     *   nivel 'cidade' → um círculo por cidade, com a contagem
     *   nivel 'ponto'  → um ponto por loja (limitado; o mapa só pede isso com zoom alto ou filtro de cidade)
     */
    public static function mapa(array $f, string $nivel, ?array $caixa = null, int $limitePontos = 3000): array
    {
        [$where, $p] = self::montarWhere($f);
        if ($nivel === 'uf') {
            return Banco::todos("SELECT c.uf, COUNT(*) AS total, SUM(c.tipo = 'cliente') AS clientes FROM clientes c WHERE $where AND c.uf IS NOT NULL GROUP BY c.uf", $p);
        }
        $filtroCaixa = '';
        if ($caixa) { // só o que está visível na tela: [oeste, sul, leste, norte]
            $filtroCaixa = ' AND ci.lng BETWEEN ? AND ? AND ci.lat BETWEEN ? AND ?';
            array_push($p, $caixa[0] - 0.3, $caixa[2] + 0.3, $caixa[1] - 0.3, $caixa[3] + 0.3);
        }
        if ($nivel === 'cidade') {
            return Banco::todos(
                "SELECT c.cidade, c.uf, ci.lat, ci.lng, COUNT(*) AS total, SUM(c.tipo = 'cliente') AS clientes
                   FROM clientes c JOIN cidades ci ON ci.id = c.cidade_id
                  WHERE $where AND ci.lat IS NOT NULL $filtroCaixa GROUP BY c.cidade, c.uf, ci.lat, ci.lng",
                $p
            );
        }
        return Banco::todos(
            "SELECT c.id, c.razao_social AS nome, c.cidade, c.uf, c.endereco, c.prioridade, c.tipo,
                    COALESCE(c.lat, ci.lat) AS lat, COALESCE(c.lng, ci.lng) AS lng, c.lat IS NOT NULL AS exato,
                    s.nome AS segmento, e.nome AS etapa
               FROM clientes c JOIN cidades ci ON ci.id = c.cidade_id
               LEFT JOIN segmentos s ON s.id = c.segmento_id
               LEFT JOIN funil_etapas e ON e.id = c.etapa_id
              WHERE $where AND ci.lat IS NOT NULL $filtroCaixa LIMIT $limitePontos",
            $p
        );
    }
}
