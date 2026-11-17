<?php
namespace App\Services;

use App\Core\Banco;
use App\Core\Config;
use App\Integrations\Integracoes;
use App\Integrations\Tray\TrayTexto;

/**
 * E-commerce (Tray: loja própria + marketplaces) — V14.0.
 *
 * sincronizar(): copia os pedidos da loja para ecommerce_pedidos/ecommerce_pedido_itens.
 *   1. Lista os pedidos ALTERADOS desde a última vez (barato: 50 por requisição) e grava o cabeçalho.
 *   2. Lê o pedido completo (itens, cidade/UF, nota, marketplace) só dos novos/alterados, no máximo N por rodada
 *      (Configurações › E-commerce), para não passar do limite da Tray (10 mil requisições/dia).
 *   Na primeira vez busca os últimos 12 meses aos poucos (cada rodada do cron completa mais um pedaço).
 * painel()/pedidos()/pedido(): tudo que as telas precisam, lido do banco local (rápido).
 * LGPD: nada de nome, CPF, e-mail, telefone ou endereço do comprador; só cidade, UF e um código anônimo.
 */
class EcommerceService
{
    /** Colunas do cabeçalho (vêm na listagem) */
    private const CABECALHO = ['tray_id', 'data_hora', 'modificado_em', 'status', 'status_tipo', 'status_cor', 'canal', 'canal_nome', 'marketplace_pedido',
        'marketplace_envio', 'marketplace_status', 'parceiro', 'total', 'subtotal', 'desconto', 'frete', 'cupom', 'pagamento', 'pagamento_tipo', 'pago_em',
        'envio', 'prazo_entrega', 'enviado_em', 'entregue', 'tem_nf'];
    /** Colunas que só o pedido completo traz */
    private const DETALHE = ['transportadora', 'custo', 'itens_qtd', 'nf_numero', 'nf_em', 'cidade', 'uf', 'cliente_hash', 'detalhado'];

    // =====================================================================
    // Sincronização
    // =====================================================================

    /** Grava um estado interno da sincronização (sem auditoria: muda a cada rodada) */
    private static function estado(string $chave, string $valor): void
    {
        Banco::executar('UPDATE configuracoes SET valor = ? WHERE chave = ?', [$valor, $chave]);
        Config::recarregar();
    }

    /**
     * Copia os pedidos da loja. Devolve ['novos', 'atualizados', 'detalhados', 'faltam', 'detalhes' (pedidos completos lidos agora)].
     * Pode rodar quantas vezes quiser: nada duplica (tray_id é único).
     */
    public static function sincronizar(?int $limiteDetalhes = null): array
    {
        $r = ['novos' => 0, 'atualizados' => 0, 'detalhados' => 0, 'faltam' => 0, 'detalhes' => [], 'ocupado' => false];
        if ((int) Banco::valor("SELECT GET_LOCK('one_ecommerce_sinc', 0)") !== 1) {
            $r['ocupado'] = true; // outra sincronização (cron ou botão) já está rodando
            return $r;
        }
        try {
            $loja = Integracoes::ecommerce();
            $origem = $loja->nome() === 'fake' ? 'fake' : 'tray';
            // Trocou da demonstração para a loja real: os pedidos simulados saem (não se misturam com os reais)
            if ($origem === 'tray' && Banco::valor("SELECT 1 FROM ecommerce_pedidos WHERE origem = 'fake' LIMIT 1")) {
                Banco::executar("DELETE FROM ecommerce_pedidos WHERE origem = 'fake'");
                self::estado('ecommerce.sinc_ate', '');
            }
            $ultimo = (string) Config::get('ecommerce.sinc_ate', '');
            $meses = max(1, min(24, (int) Config::get('ecommerce.meses_historico', 12)));
            $desde = $ultimo !== '' && strtotime($ultimo) ? date('Y-m-d', strtotime("$ultimo -1 day")) : date('Y-m-01', strtotime("-$meses months"));
            $ate = date('Y-m-d', strtotime('+1 day')); // folga para diferença de fuso entre a loja e o servidor

            // 1. Cabeçalhos dos pedidos alterados
            $pagina = 1;
            do {
                $lote = $loja->pedidosAlterados($desde, $ate, $pagina);
                [$novos, $atualizados] = self::gravar($lote['pedidos'], $origem);
                $r['novos'] += $novos;
                $r['atualizados'] += $atualizados;
                foreach ($lote['pedidos'] as $p) {
                    if (!empty($p['itens'])) {
                        $r['detalhes'][] = $p; // a demonstração já vem completa
                    }
                }
                $pagina++;
            } while (($pagina - 1) * max(1, (int) $lote['por_pagina']) < (int) $lote['total'] && $pagina <= 400);
            self::estado('ecommerce.sinc_ate', date('Y-m-d'));

            // 2. Pedido completo dos novos/alterados (mais recentes primeiro)
            $limite = $limiteDetalhes ?? max(10, (int) Config::get('ecommerce.detalhes_por_rodada', 300));
            $faltam = Banco::todos('SELECT tray_id FROM ecommerce_pedidos WHERE origem = ? AND detalhado = 0 ORDER BY data_hora DESC LIMIT ' . (int) $limite, [$origem]);
            foreach ($faltam as $f) {
                $p = $loja->pedidoCompleto((string) $f['tray_id']);
                if ($p) {
                    self::gravar([$p], $origem);
                    $r['detalhes'][] = $p;
                    $r['detalhados']++;
                } else {
                    Banco::executar('UPDATE ecommerce_pedidos SET detalhado = 1 WHERE origem = ? AND tray_id = ?', [$origem, $f['tray_id']]);
                }
            }
            $r['faltam'] = (int) Banco::valor('SELECT COUNT(*) FROM ecommerce_pedidos WHERE origem = ? AND detalhado = 0', [$origem]);
            self::estado('ecommerce.ultima_sincronizacao', date('Y-m-d H:i:s'));
            self::estado('ecommerce.ultimo_erro', '');
        } catch (\Throwable $erro) {
            self::estado('ecommerce.ultimo_erro', date('d/m H:i') . ' — ' . mb_substr($erro->getMessage(), 0, 300));
            throw $erro;
        } finally {
            Banco::valor("SELECT RELEASE_LOCK('one_ecommerce_sinc')");
        }
        return $r;
    }

    /** Grava pedidos (cabeçalho ou completos). Novos em lote (rápido); existentes um a um. Devolve [novos, atualizados]. */
    private static function gravar(array $pedidos, string $origem): array
    {
        if (!$pedidos) {
            return [0, 0];
        }
        $ids = array_column($pedidos, 'tray_id');
        $existentes = [];
        foreach (array_chunk($ids, 500) as $parte) {
            $marcas = implode(',', array_fill(0, count($parte), '?'));
            foreach (Banco::todos("SELECT id, tray_id, modificado_em, detalhado FROM ecommerce_pedidos WHERE origem = ? AND tray_id IN ($marcas)", array_merge([$origem], $parte)) as $e) {
                $existentes[$e['tray_id']] = $e;
            }
        }
        $novos = array_values(array_filter($pedidos, fn($p) => !isset($existentes[$p['tray_id']])));
        $atualizados = 0;
        Banco::transacao(function () use ($pedidos, $novos, $existentes, $origem, &$atualizados) {
            // Novos: INSERT em lote
            $colunas = array_merge(['origem'], self::CABECALHO, self::DETALHE);
            foreach (array_chunk($novos, 200) as $parte) {
                $valores = [];
                foreach ($parte as $p) {
                    $valores[] = $origem;
                    foreach (array_merge(self::CABECALHO, self::DETALHE) as $c) {
                        $valores[] = $p[$c] ?? ($c === 'detalhado' ? 0 : (in_array($c, ['itens_qtd', 'entregue', 'tem_nf'], true) ? 0 : null));
                    }
                }
                $linha = '(' . implode(',', array_fill(0, count($colunas), '?')) . ')';
                Banco::executar('INSERT IGNORE INTO ecommerce_pedidos (' . implode(',', $colunas) . ') VALUES ' . implode(',', array_fill(0, count($parte), $linha)), $valores);
            }
            // Existentes: atualiza (cabeçalho mudou → precisa ler o completo de novo)
            foreach ($pedidos as $p) {
                $e = $existentes[$p['tray_id']] ?? null;
                if (!$e) {
                    continue;
                }
                $completo = !empty($p['detalhado']);
                if (!$completo && $e['modificado_em'] === $p['modificado_em'] && $p['modificado_em'] !== null) {
                    continue; // nada mudou
                }
                $dados = array_intersect_key($p, array_flip($completo ? array_merge(self::CABECALHO, self::DETALHE) : self::CABECALHO));
                unset($dados['tray_id']);
                if (!$completo) {
                    unset($dados['data_hora']); // a listagem não traz a hora: mantém a do pedido completo
                    // Releitura forçada (modificado_em apagado numa atualização do One): só o cabeçalho muda
                    if ($e['modificado_em'] !== null || !$e['detalhado']) {
                        $dados['detalhado'] = 0;
                    }
                }
                Banco::atualizar('ecommerce_pedidos', (int) $e['id'], $dados);
                $atualizados++;
            }
            // Itens dos pedidos completos (trocados inteiros)
            $comItens = array_values(array_filter($pedidos, fn($p) => !empty($p['detalhado']) && isset($p['itens'])));
            if ($comItens) {
                $mapa = [];
                foreach (array_chunk(array_column($comItens, 'tray_id'), 500) as $parte) {
                    $marcas = implode(',', array_fill(0, count($parte), '?'));
                    foreach (Banco::todos("SELECT id, tray_id FROM ecommerce_pedidos WHERE origem = ? AND tray_id IN ($marcas)", array_merge([$origem], $parte)) as $m) {
                        $mapa[$m['tray_id']] = (int) $m['id'];
                    }
                }
                $existiam = array_values(array_filter(array_map(fn($p) => isset($existentes[$p['tray_id']]) ? $mapa[$p['tray_id']] ?? null : null, $comItens)));
                foreach (array_chunk($existiam, 500) as $parte) {
                    Banco::executar('DELETE FROM ecommerce_pedido_itens WHERE pedido_id IN (' . implode(',', array_map('intval', $parte)) . ')');
                }
                $cols = ['pedido_id', 'produto_ref', 'ean', 'nome', 'variacao', 'categoria', 'quantidade', 'preco', 'custo', 'imagem'];
                $linhas = [];
                foreach ($comItens as $p) {
                    foreach ($p['itens'] as $i) {
                        if (isset($mapa[$p['tray_id']])) {
                            $linhas[] = [$mapa[$p['tray_id']], $i['produto_ref'], $i['ean'], $i['nome'], $i['variacao'], $i['categoria'], $i['quantidade'], $i['preco'], $i['custo'], $i['imagem']];
                        }
                    }
                }
                foreach (array_chunk($linhas, 300) as $parte) {
                    $linha = '(' . implode(',', array_fill(0, count($cols), '?')) . ')';
                    Banco::executar('INSERT INTO ecommerce_pedido_itens (' . implode(',', $cols) . ') VALUES ' . implode(',', array_fill(0, count($parte), $linha)), array_merge(...$parte));
                }
            }
        });
        return [count($novos), $atualizados];
    }

    /** Demonstração: gera os pedidos na primeira visita (a loja real é lida pelo cron ou "Atualizar agora") */
    public static function garantirDemonstracao(): void
    {
        if (Integracoes::ecommerce()->nome() === 'fake' && !Banco::valor("SELECT 1 FROM ecommerce_pedidos WHERE origem = 'fake' LIMIT 1")) {
            self::sincronizar();
        } elseif (Integracoes::ecommerce()->nome() === 'fake' && substr((string) Config::get('ecommerce.ultima_sincronizacao', ''), 0, 13) !== date('Y-m-d H')) {
            self::sincronizar(); // demonstração: os pedidos de hoje vão "chegando" ao longo do dia
        }
    }

    // =====================================================================
    // Filtros e números do painel
    // =====================================================================

    /** Períodos prontos: chave => [nome, de, até] */
    public static function periodos(): array
    {
        $hoje = date('Y-m-d');
        return [
            'hoje'      => ['Hoje', $hoje, $hoje],
            '7d'        => ['Últimos 7 dias', date('Y-m-d', strtotime('-6 days')), $hoje],
            'mes'       => ['Este mês', date('Y-m-01'), $hoje],
            'mes_ant'   => ['Mês anterior', date('Y-m-01', strtotime('first day of last month')), date('Y-m-t', strtotime('first day of last month'))],
            '30d'       => ['Últimos 30 dias', date('Y-m-d', strtotime('-29 days')), $hoje],
            '90d'       => ['Últimos 90 dias', date('Y-m-d', strtotime('-89 days')), $hoje],
            '12m'       => ['Últimos 12 meses', date('Y-m-d', strtotime('-12 months +1 day')), $hoje],
        ];
    }

    /** Filtros da tela (GET), validados */
    public static function filtros(array $get): array
    {
        $periodos = self::periodos();
        $chave = array_key_exists($get['periodo'] ?? '', $periodos) ? $get['periodo'] : ((!empty($get['de']) || !empty($get['ate'])) ? 'livre' : 'mes');
        if ($chave === 'livre') {
            $de = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($get['de'] ?? '')) ? $get['de'] : date('Y-m-01');
            $ate = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($get['ate'] ?? '')) ? $get['ate'] : date('Y-m-d');
            if ($ate < $de) {
                [$de, $ate] = [$ate, $de];
            }
        } else {
            [, $de, $ate] = $periodos[$chave];
        }
        return [
            'periodo' => $chave, 'de' => $de, 'ate' => $ate,
            'canal' => array_key_exists($get['canal'] ?? '', TrayTexto::CANAIS) ? $get['canal'] : '',
            'uf' => preg_match('/^[A-Z]{2}$/', strtoupper((string) ($get['uf'] ?? ''))) ? strtoupper($get['uf']) : '',
            'situacao' => array_key_exists($get['situacao'] ?? '', TrayTexto::SITUACOES) ? $get['situacao'] : '',
            'busca' => mb_substr(trim((string) ($get['busca'] ?? '')), 0, 60),
        ];
    }

    /** WHERE + parâmetros dos filtros (período opcional, para comparar com o anterior) */
    private static function onde(array $f, ?string $de = null, ?string $ate = null, bool $comSituacao = true): array
    {
        $w = ['p.origem = ?', 'p.data_hora >= ?', 'p.data_hora < ?'];
        $par = [self::origem(), ($de ?? $f['de']) . ' 00:00:00', date('Y-m-d', strtotime(($ate ?? $f['ate']) . ' +1 day')) . ' 00:00:00'];
        if ($f['canal'] !== '') {
            $w[] = 'p.canal = ?';
            $par[] = $f['canal'];
        }
        if ($f['uf'] !== '') {
            $w[] = 'p.uf = ?';
            $par[] = $f['uf'];
        }
        if ($comSituacao && $f['situacao'] !== '') {
            $w[] = 'p.status_tipo = ?';
            $par[] = $f['situacao'];
        }
        if (($f['busca'] ?? '') !== '') {
            $w[] = '(p.tray_id = ? OR p.marketplace_pedido LIKE ? OR p.cupom = ?)';
            array_push($par, $f['busca'], '%' . $f['busca'] . '%', $f['busca']);
        }
        return [implode(' AND ', $w), $par];
    }

    private static function origem(): string
    {
        return Config::get('ecommerce.provedor', 'fake') === 'tray' ? 'tray' : 'fake';
    }

    private const VENDIDO_SQL = "p.status_tipo IN ('pago', 'enviado', 'entregue')";

    /** Totais de um período (vendido = pago, enviado ou entregue) */
    private static function totais(array $f, string $de, string $ate): array
    {
        [$w, $par] = self::onde($f, $de, $ate, false);
        $v = self::VENDIDO_SQL;
        $r = Banco::um("SELECT
                COALESCE(SUM(CASE WHEN $v THEN p.total END), 0) AS faturamento,
                COUNT(CASE WHEN $v THEN 1 END) AS pedidos,
                COALESCE(SUM(CASE WHEN $v THEN p.itens_qtd END), 0) AS itens,
                COALESCE(SUM(CASE WHEN $v THEN p.frete END), 0) AS frete,
                COALESCE(SUM(CASE WHEN $v THEN p.desconto END), 0) AS desconto,
                COUNT(CASE WHEN $v AND p.cupom IS NOT NULL THEN 1 END) AS com_cupom,
                COALESCE(SUM(CASE WHEN $v AND p.custo IS NOT NULL THEN p.total - p.frete - p.custo END), 0) AS margem,
                COALESCE(SUM(CASE WHEN $v AND p.custo IS NOT NULL THEN p.total - p.frete END), 0) AS base_margem,
                COUNT(CASE WHEN p.status_tipo = 'cancelado' THEN 1 END) AS cancelados,
                COALESCE(SUM(CASE WHEN p.status_tipo = 'cancelado' THEN p.total END), 0) AS cancelado_valor,
                COUNT(CASE WHEN p.status_tipo = 'aguardando' THEN 1 END) AS aguardando,
                COALESCE(SUM(CASE WHEN p.status_tipo = 'aguardando' THEN p.total END), 0) AS aguardando_valor,
                COUNT(*) AS todos,
                COUNT(DISTINCT CASE WHEN $v THEN p.cliente_hash END) AS clientes
              FROM ecommerce_pedidos p WHERE $w", $par);
        $r['ticket'] = $r['pedidos'] > 0 ? $r['faturamento'] / $r['pedidos'] : 0;
        $r['itens_por_pedido'] = $r['pedidos'] > 0 ? $r['itens'] / $r['pedidos'] : 0;
        $r['cancelados_pct'] = ($r['pedidos'] + $r['cancelados']) > 0 ? $r['cancelados'] / ($r['pedidos'] + $r['cancelados']) * 100 : 0;
        $r['margem_pct'] = $r['base_margem'] > 0 ? $r['margem'] / $r['base_margem'] * 100 : null;
        // Recompra: clientes que já tinham comprado antes do período
        $r['recompra'] = (int) Banco::valor("SELECT COUNT(DISTINCT p.cliente_hash) FROM ecommerce_pedidos p
            WHERE $w AND $v AND p.cliente_hash IS NOT NULL AND EXISTS (SELECT 1 FROM ecommerce_pedidos a WHERE a.origem = p.origem
              AND a.cliente_hash = p.cliente_hash AND a.data_hora < ? AND a.status_tipo IN ('pago', 'enviado', 'entregue'))", array_merge($par, [$de . ' 00:00:00']));
        $r['novos'] = max(0, (int) $r['clientes'] - $r['recompra']);
        return $r;
    }

    private static function variacao(float $atual, float $anterior): ?float
    {
        return $anterior > 0 ? ($atual - $anterior) / $anterior * 100 : null;
    }

    /** Período anterior com o mesmo número de dias */
    private static function anterior(array $f): array
    {
        $dias = (int) round((strtotime($f['ate']) - strtotime($f['de'])) / 86400) + 1;
        if ($f['periodo'] === 'mes') { // mês corrente: compara com o mês anterior até o mesmo dia
            $de = date('Y-m-01', strtotime('first day of last month'));
            return [$de, date('Y-m-d', min(strtotime(date('Y-m-t', strtotime($de))), strtotime("$de +" . ($dias - 1) . ' days')))];
        }
        return [date('Y-m-d', strtotime($f['de'] . " -$dias days")), date('Y-m-d', strtotime($f['de'] . ' -1 day'))];
    }

    /** Tudo do painel, para os filtros dados */
    public static function painel(array $f): array
    {
        [$deAnt, $ateAnt] = self::anterior($f);
        $atual = self::totais($f, $f['de'], $f['ate']);
        $ant = self::totais($f, $deAnt, $ateAnt);
        [$w, $par] = self::onde($f);
        $v = self::VENDIDO_SQL;
        $var = [];
        foreach (['faturamento', 'pedidos', 'ticket', 'itens_por_pedido', 'clientes', 'frete', 'desconto', 'recompra'] as $k) {
            $var[$k] = self::variacao((float) $atual[$k], (float) $ant[$k]);
        }
        $var['cancelados_pct'] = $atual['cancelados_pct'] - $ant['cancelados_pct']; // pontos percentuais

        // Canais (período atual × anterior)
        [$wSemCanal, $parSemCanal] = self::onde(['canal' => ''] + $f);
        [$wAntSemCanal, $parAntSemCanal] = self::onde(['canal' => ''] + $f, $deAnt, $ateAnt);
        $canaisAnt = array_column(Banco::todos("SELECT p.canal, SUM(p.total) AS faturamento FROM ecommerce_pedidos p WHERE $wAntSemCanal AND $v GROUP BY p.canal", $parAntSemCanal), 'faturamento', 'canal');
        $canais = Banco::todos("SELECT p.canal, MAX(p.canal_nome) AS canal_nome, COUNT(CASE WHEN $v THEN 1 END) AS pedidos, COALESCE(SUM(CASE WHEN $v THEN p.total END), 0) AS faturamento,
                COUNT(CASE WHEN p.status_tipo = 'cancelado' THEN 1 END) AS cancelados, COALESCE(SUM(CASE WHEN $v THEN p.frete END), 0) AS frete
              FROM ecommerce_pedidos p WHERE $wSemCanal GROUP BY p.canal ORDER BY faturamento DESC", $parSemCanal);
        $totalCanais = array_sum(array_column($canais, 'faturamento')) ?: 1;
        foreach ($canais as &$c) {
            $c['nome'] = TrayTexto::nomeCanal($c['canal'], $c['canal_nome']);
            $c['cor'] = TrayTexto::CANAIS[$c['canal']][1] ?? 'cinza';
            $c['ticket'] = $c['pedidos'] ? $c['faturamento'] / $c['pedidos'] : 0;
            $c['participacao'] = $c['faturamento'] / $totalCanais * 100;
            $c['variacao'] = self::variacao((float) $c['faturamento'], (float) ($canaisAnt[$c['canal']] ?? 0));
        }
        unset($c);

        // Por dia e canal (gráfico empilhado)
        $diario = Banco::todos("SELECT DATE(p.data_hora) AS dia, p.canal, SUM(p.total) AS faturamento, COUNT(*) AS pedidos
            FROM ecommerce_pedidos p WHERE $w AND $v GROUP BY dia, p.canal ORDER BY dia", $par);

        // 12 meses até o fim do período
        $fIni = date('Y-m-01', strtotime($f['ate'] . ' -11 months'));
        [$w12, $par12] = self::onde($f, $fIni, $f['ate']);
        $meses = Banco::todos("SELECT DATE_FORMAT(p.data_hora, '%Y-%m') AS mes, SUM(p.total) AS faturamento, COUNT(*) AS pedidos
            FROM ecommerce_pedidos p WHERE $w12 AND $v GROUP BY mes ORDER BY mes", $par12);

        // Mapa de calor: dia da semana × hora (só pedidos com hora: os completos)
        $calor = Banco::todos("SELECT WEEKDAY(p.data_hora) AS dia_semana, HOUR(p.data_hora) AS hora, COUNT(*) AS pedidos
            FROM ecommerce_pedidos p WHERE $w AND p.detalhado = 1 AND p.status_tipo <> 'cancelado' GROUP BY dia_semana, hora", $par);

        // Funil de situação (sem filtro de situação)
        [$wSit, $parSit] = self::onde(['situacao' => ''] + $f);
        $funil = Banco::um("SELECT COUNT(*) AS todos, COUNT(CASE WHEN p.status_tipo = 'aguardando' THEN 1 END) AS aguardando,
                COUNT(CASE WHEN p.status_tipo IN ('pago', 'enviado', 'entregue') THEN 1 END) AS pagos,
                COUNT(CASE WHEN p.status_tipo IN ('pago', 'enviado', 'entregue') AND p.tem_nf = 1 THEN 1 END) AS com_nf,
                COUNT(CASE WHEN p.status_tipo IN ('enviado', 'entregue') THEN 1 END) AS enviados,
                COUNT(CASE WHEN p.status_tipo = 'entregue' THEN 1 END) AS entregues,
                COUNT(CASE WHEN p.status_tipo = 'cancelado' THEN 1 END) AS cancelados
              FROM ecommerce_pedidos p WHERE $wSit", $parSit);

        // Produtos e categorias
        $produtos = self::produtos($w, $par, 15);
        [$wAnt, $parAnt] = self::onde($f, $deAnt, $ateAnt);
        $antes = self::produtos($wAnt, $parAnt, 200);
        $agoraRefs = array_flip(array_column(self::produtos($w, $par, 1000), 'produto_ref'));
        $parados = array_slice(array_values(array_filter($antes, fn($p) => !isset($agoraRefs[$p['produto_ref']]))), 0, 8);
        $categorias = Banco::todos("SELECT COALESCE(i.categoria, 'Sem categoria') AS categoria, SUM(i.quantidade) AS quantidade, SUM(i.quantidade * i.preco) AS faturamento
            FROM ecommerce_pedido_itens i JOIN ecommerce_pedidos p ON p.id = i.pedido_id WHERE $w AND $v GROUP BY categoria ORDER BY faturamento DESC LIMIT 10", $par);

        // Pagamento, envio e prazo
        $pagamentos = Banco::todos("SELECT COALESCE(p.pagamento_tipo, 'outro') AS tipo, COUNT(CASE WHEN $v THEN 1 END) AS pedidos, COALESCE(SUM(CASE WHEN $v THEN p.total END), 0) AS faturamento,
                COUNT(CASE WHEN p.status_tipo = 'cancelado' THEN 1 END) AS cancelados, COUNT(CASE WHEN p.status_tipo = 'aguardando' THEN 1 END) AS aguardando
              FROM ecommerce_pedidos p WHERE $wSit GROUP BY tipo ORDER BY faturamento DESC", $parSit);
        $envios = Banco::todos("SELECT COALESCE(NULLIF(p.envio, ''), 'Não informado') AS envio, MAX(p.canal) AS canal, COUNT(*) AS pedidos, AVG(p.frete) AS frete_medio,
                COUNT(CASE WHEN p.enviado_em IS NOT NULL AND p.prazo_entrega IS NOT NULL THEN 1 END) AS com_prazo,
                COUNT(CASE WHEN p.enviado_em IS NOT NULL AND p.prazo_entrega IS NOT NULL AND p.enviado_em <= p.prazo_entrega THEN 1 END) AS no_prazo
              FROM ecommerce_pedidos p WHERE $w AND $v GROUP BY envio ORDER BY pedidos DESC LIMIT 10", $par);
        $prazo = Banco::um("SELECT COUNT(CASE WHEN p.enviado_em IS NOT NULL AND p.prazo_entrega IS NOT NULL THEN 1 END) AS com_prazo,
                COUNT(CASE WHEN p.enviado_em IS NOT NULL AND p.prazo_entrega IS NOT NULL AND p.enviado_em <= p.prazo_entrega THEN 1 END) AS no_prazo,
                AVG(CASE WHEN p.enviado_em IS NOT NULL THEN DATEDIFF(p.enviado_em, DATE(p.data_hora)) END) AS dias_para_enviar
              FROM ecommerce_pedidos p WHERE $w AND $v", $par);

        // Geografia e cupons
        $ufs = Banco::todos("SELECT p.uf, COUNT(*) AS pedidos, SUM(p.total) AS faturamento FROM ecommerce_pedidos p WHERE $w AND $v AND p.uf IS NOT NULL GROUP BY p.uf ORDER BY faturamento DESC", $par);
        $cidades = Banco::todos("SELECT p.cidade, p.uf, COUNT(*) AS pedidos, SUM(p.total) AS faturamento FROM ecommerce_pedidos p WHERE $w AND $v AND p.cidade IS NOT NULL
            GROUP BY p.cidade, p.uf ORDER BY faturamento DESC LIMIT 10", $par);
        $cupons = Banco::todos("SELECT p.cupom, COUNT(*) AS pedidos, SUM(p.desconto) AS desconto, SUM(p.total) AS faturamento FROM ecommerce_pedidos p
            WHERE $w AND $v AND p.cupom IS NOT NULL GROUP BY p.cupom ORDER BY pedidos DESC LIMIT 10", $par);

        $ultima = (string) Config::get('ecommerce.ultima_sincronizacao', '');
        $faltam = (int) Banco::valor('SELECT COUNT(*) FROM ecommerce_pedidos WHERE origem = ? AND detalhado = 0', [self::origem()]);
        return [
            'filtros' => $f, 'anterior' => ['de' => $deAnt, 'ate' => $ateAnt],
            'atual' => $atual, 'ant' => $ant, 'variacao' => $var,
            'projecao' => $f['periodo'] === 'mes' && (int) date('j') > 0 ? $atual['faturamento'] / (int) date('j') * (int) date('t') : null,
            'canais' => $canais, 'diario' => $diario, 'meses' => $meses, 'calor' => $calor, 'funil' => $funil,
            'produtos' => $produtos, 'parados' => $parados, 'categorias' => $categorias,
            'pagamentos' => $pagamentos, 'envios' => $envios, 'prazo' => $prazo,
            'ufs' => $ufs, 'cidades' => $cidades, 'cupons' => $cupons,
            'recentes' => self::pedidos($f, 1, 8)['linhas'],
            'alertas' => self::alertas($f, $atual, $ant),
            'simulado' => self::origem() === 'fake',
            'ultima_sincronizacao' => $ultima,
            'importando' => $faltam,
            'total_local' => (int) Banco::valor('SELECT COUNT(*) FROM ecommerce_pedidos WHERE origem = ?', [self::origem()]),
        ];
    }

    /** Ranking de produtos (vendidos) */
    private static function produtos(string $w, array $par, int $limite): array
    {
        $v = self::VENDIDO_SQL;
        $lista = Banco::todos("SELECT i.produto_ref, MAX(i.nome) AS nome, MAX(i.categoria) AS categoria, MAX(i.imagem) AS imagem, SUM(i.quantidade) AS quantidade,
                SUM(i.quantidade * i.preco) AS faturamento, COUNT(DISTINCT p.id) AS pedidos,
                SUBSTRING_INDEX(GROUP_CONCAT(p.canal ORDER BY p.canal), ',', 1) AS canal_qualquer
              FROM ecommerce_pedido_itens i JOIN ecommerce_pedidos p ON p.id = i.pedido_id
              WHERE $w AND $v GROUP BY i.produto_ref ORDER BY faturamento DESC LIMIT " . (int) $limite, $par);
        if ($limite > 30 || !$lista) {
            return $lista;
        }
        // Canal onde o produto mais vende
        $refs = array_column($lista, 'produto_ref');
        $marcas = implode(',', array_fill(0, count($refs), '?'));
        $porCanal = [];
        foreach (Banco::todos("SELECT i.produto_ref, p.canal, SUM(i.quantidade * i.preco) AS fat FROM ecommerce_pedido_itens i JOIN ecommerce_pedidos p ON p.id = i.pedido_id
            WHERE $w AND $v AND i.produto_ref IN ($marcas) GROUP BY i.produto_ref, p.canal", array_merge($par, $refs)) as $l) {
            if (!isset($porCanal[$l['produto_ref']]) || $l['fat'] > $porCanal[$l['produto_ref']][1]) {
                $porCanal[$l['produto_ref']] = [$l['canal'], $l['fat']];
            }
        }
        // Foto do cadastro do One quando a loja não manda
        $fotos = [];
        foreach (Banco::todos("SELECT p.slug, p.codigo, m.arquivo FROM produtos p JOIN midias m ON m.id = p.imagem_id WHERE p.slug IN ($marcas) OR p.codigo IN ($marcas)", array_merge($refs, $refs)) as $pr) {
            $fotos[$pr['slug']] = $pr['arquivo'];
            if ($pr['codigo']) {
                $fotos[$pr['codigo']] = $pr['arquivo'];
            }
        }
        foreach ($lista as &$p) {
            $p['canal'] = $porCanal[$p['produto_ref']][0] ?? $p['canal_qualquer'];
            $p['foto_one'] = $fotos[$p['produto_ref']] ?? null;
        }
        return $lista;
    }

    /** Alertas do topo */
    private static function alertas(array $f, array $atual, array $ant): array
    {
        $origem = self::origem();
        $alertas = [];
        $horas = max(1, (int) Config::get('ecommerce.alerta_horas_nf', 24));
        $semNota = (int) Banco::valor("SELECT COUNT(*) FROM ecommerce_pedidos WHERE origem = ? AND status_tipo = 'pago' AND tem_nf = 0
            AND data_hora < NOW() - INTERVAL $horas HOUR AND data_hora > NOW() - INTERVAL 30 DAY", [$origem]);
        if ($semNota) {
            $alertas[] = ['aviso', "$semNota pedido(s) pago(s) há mais de {$horas}h ainda sem nota fiscal.", '/marketing/ecommerce/pedidos?situacao=pago&periodo=30d&sem_nf=1'];
        }
        $atrasados = (int) Banco::valor("SELECT COUNT(*) FROM ecommerce_pedidos WHERE origem = ? AND status_tipo = 'pago' AND prazo_entrega IS NOT NULL
            AND prazo_entrega < CURDATE() AND data_hora > NOW() - INTERVAL 60 DAY", [$origem]);
        if ($atrasados) {
            $alertas[] = ['erro', "$atrasados pedido(s) pago(s) passaram do prazo de entrega e ainda não foram enviados.", '/marketing/ecommerce/pedidos?situacao=pago&periodo=90d&atrasado=1'];
        }
        if ($atual['cancelados'] >= 3 && $atual['cancelados_pct'] >= 5 && $atual['cancelados_pct'] > $ant['cancelados_pct'] * 1.5) {
            $alertas[] = ['aviso', 'Cancelamentos acima do normal: ' . number_format($atual['cancelados_pct'], 1, ',', '.') . '% dos pedidos (antes: '
                . number_format($ant['cancelados_pct'], 1, ',', '.') . '%).', '/marketing/ecommerce/pedidos?situacao=cancelado&periodo=' . $f['periodo']];
        }
        $erro = (string) Config::get('ecommerce.ultimo_erro', '');
        if ($erro !== '') {
            $alertas[] = ['erro', 'A última atualização com a loja falhou: ' . $erro, null];
        }
        return $alertas;
    }

    /** Lista de pedidos (paginada no servidor) */
    public static function pedidos(array $f, int $pagina = 1, int $porPagina = 50, array $extra = []): array
    {
        [$w, $par] = self::onde($f);
        if (!empty($extra['sem_nf'])) {
            $w .= ' AND p.tem_nf = 0';
        }
        if (!empty($extra['atrasado'])) {
            $w .= " AND p.status_tipo = 'pago' AND p.prazo_entrega < CURDATE()";
        }
        $total = (int) Banco::valor("SELECT COUNT(*) FROM ecommerce_pedidos p WHERE $w", $par);
        $pagina = max(1, min($pagina, (int) ceil(max(1, $total) / $porPagina)));
        $linhas = Banco::todos("SELECT p.*, (SELECT GROUP_CONCAT(CONCAT(i.quantidade, '× ', i.nome) SEPARATOR ' · ') FROM ecommerce_pedido_itens i WHERE i.pedido_id = p.id) AS resumo_itens
            FROM ecommerce_pedidos p WHERE $w ORDER BY p.data_hora DESC, p.id DESC LIMIT $porPagina OFFSET " . (($pagina - 1) * $porPagina), $par);
        return ['linhas' => $linhas, 'total' => $total, 'pagina' => $pagina, 'paginas' => (int) ceil(max(1, $total) / $porPagina)];
    }

    /** Um pedido + itens + o caminho dele no Depósito do One */
    public static function pedido(int $id): ?array
    {
        $p = Banco::um('SELECT * FROM ecommerce_pedidos WHERE id = ? AND origem = ?', [$id, self::origem()]);
        if (!$p) {
            return null;
        }
        $p['itens'] = Banco::todos('SELECT * FROM ecommerce_pedido_itens WHERE pedido_id = ? ORDER BY id', [$id]);
        $p['deposito'] = Banco::um('SELECT id, situacao, expedicao, numero_nf, expedicao_limite FROM pedidos WHERE tray_codigo = ?', [$p['tray_id']]);
        return $p;
    }

    /** Linhas para o CSV (sem dados pessoais) */
    public static function exportar(array $f, array $extra = []): array
    {
        $linhas = [];
        $pagina = 1;
        do {
            $r = self::pedidos($f, $pagina, 500, $extra);
            array_push($linhas, ...$r['linhas']);
            $pagina++;
        } while ($pagina <= $r['paginas'] && $pagina <= 100);
        return $linhas;
    }

    // =====================================================================
    // Para outras telas
    // =====================================================================

    /** Card do Início: faturamento do mês × mês anterior até o mesmo dia, e hoje */
    public static function resumoInicio(): ?array
    {
        if (!Banco::valor('SELECT 1 FROM ecommerce_pedidos WHERE origem = ? LIMIT 1', [self::origem()])) {
            return null;
        }
        $f = self::filtros(['periodo' => 'mes']);
        [$deAnt, $ateAnt] = self::anterior($f);
        $atual = self::totais($f, $f['de'], $f['ate']);
        $hoje = self::totais($f, date('Y-m-d'), date('Y-m-d'));
        $ant = self::totais($f, $deAnt, $ateAnt);
        return ['atual' => $atual, 'hoje' => $hoje, 'variacao' => ['faturamento' => self::variacao((float) $atual['faturamento'], (float) $ant['faturamento'])]];
    }

    /** Vendas de um produto no período (campanhas do planejamento): pelo slug ou pelo código do produto */
    public static function vendasDoProduto(string $slug, string $desde, string $ate): ?array
    {
        $codigo = (string) Banco::valor('SELECT codigo FROM produtos WHERE slug = ?', [$slug]);
        $r = Banco::um("SELECT SUM(i.quantidade) AS quantidade, SUM(i.quantidade * i.preco) AS faturamento FROM ecommerce_pedido_itens i
            JOIN ecommerce_pedidos p ON p.id = i.pedido_id WHERE p.origem = ? AND (i.produto_ref = ? OR (? <> '' AND i.produto_ref = ?))
            AND p.status_tipo IN ('pago', 'enviado', 'entregue') AND p.data_hora >= ? AND p.data_hora < ?",
            [self::origem(), $slug, $codigo, $codigo, "$desde 00:00:00", date('Y-m-d', strtotime("$ate +1 day")) . ' 00:00:00']);
        return $r && $r['quantidade'] !== null ? $r : null;
    }

    /** Produto mais vendido do mês na posição N (seed de demonstração das campanhas) */
    public static function produtoNoTopo(string $mes, int $posicao): ?int
    {
        $ref = Banco::valor("SELECT i.produto_ref FROM ecommerce_pedido_itens i JOIN ecommerce_pedidos p ON p.id = i.pedido_id
            WHERE p.origem = ? AND DATE_FORMAT(p.data_hora, '%Y-%m') = ? AND p.status_tipo IN ('pago', 'enviado', 'entregue')
            GROUP BY i.produto_ref ORDER BY SUM(i.quantidade * i.preco) DESC LIMIT 1 OFFSET " . (int) $posicao, [self::origem(), $mes]);
        return $ref ? ((int) Banco::valor('SELECT id FROM produtos WHERE slug = ? OR codigo = ? LIMIT 1', [$ref, $ref]) ?: null) : null;
    }
}
