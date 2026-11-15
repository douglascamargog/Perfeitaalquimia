<?php
namespace App\Services;

use App\Core\Auditoria;
use App\Core\Banco;
use App\Core\Config;
use App\Models\ClienteEvento;
use RuntimeException;

/**
 * Depósito: separação e expedição dos pedidos.
 * A nota já veio do ERP (o Omie emite na aprovação), então o pedido continua "faturado";
 * o andamento no depósito fica em pedidos.expedicao: aguardando → separando → separado → despachado.
 * Cada item separado guarda o lote e a validade (pedido_item_lotes): é a rastreabilidade para um recolhimento.
 */
class DepositoService
{
    public const ETAPAS = [
        'aguardando' => ['A separar', 'laranja'],
        'separando'  => ['Separando', 'azul'],
        'separado'   => ['Separado', 'lima'],
        'despachado' => ['Despachado', 'verde'],
    ];

    // ------------------------------------------------------------------
    // Fila e painel do dia
    // ------------------------------------------------------------------

    /** Fila: $aba = separar (aguardando + separando) | separados | despachados */
    public static function fila(string $aba = 'separar'): array
    {
        $w = match ($aba) {
            'separados'   => "p.expedicao = 'separado'",
            'despachados' => "p.expedicao = 'despachado' AND p.despachado_em >= CURDATE() - INTERVAL 7 DAY",
            default       => "p.expedicao IN ('aguardando', 'separando')",
        };
        $ordem = $aba === 'despachados' ? 'p.despachado_em DESC' : 'p.expedicao_prioridade DESC, p.expedicao_desde, p.id';
        return Banco::todos("SELECT p.id, p.numero_nf, p.total, p.expedicao, p.expedicao_prioridade, p.expedicao_aguarda_produto, p.expedicao_desde,
                p.transportadora, p.rastreio, p.despachado_em, p.falta,
                COALESCE(NULLIF(c.nome_fantasia, ''), c.razao_social) AS cliente, c.cidade, c.uf,
                v.nome AS vendedor, v.foto AS vendedor_foto, s.nome AS separador,
                (SELECT COUNT(*) FROM pedido_itens i WHERE i.pedido_id = p.id) AS itens,
                (SELECT COALESCE(SUM(i.quantidade), 0) FROM pedido_itens i WHERE i.pedido_id = p.id) AS unidades,
                (SELECT COUNT(*) FROM pedido_itens i WHERE i.pedido_id = p.id AND i.terceirizacao_produto_id IS NOT NULL) AS terceirizados
            FROM pedidos p JOIN clientes c ON c.id = p.cliente_id
            LEFT JOIN usuarios v ON v.id = p.vendedor_id LEFT JOIN usuarios s ON s.id = p.separado_por
            WHERE $w ORDER BY $ordem LIMIT 300");
    }

    /** Números do dia (topo da fila e cartão do Início) */
    public static function resumo(): array
    {
        $dias = max(1, (int) Config::get('deposito.dias_atraso', 2));
        $r = Banco::um("SELECT
                SUM(expedicao = 'aguardando') AS a_separar,
                SUM(expedicao = 'separando') AS separando,
                SUM(expedicao = 'separado') AS separados,
                SUM(expedicao = 'despachado' AND despachado_em >= CURDATE()) AS despachados_hoje,
                SUM(expedicao IN ('aguardando', 'separando', 'separado') AND expedicao_desde < NOW() - INTERVAL $dias DAY) AS atrasados
            FROM pedidos WHERE expedicao IS NOT NULL") ?: [];
        $r = array_map('intval', $r);
        $r['receber'] = (int) Banco::valor("SELECT COUNT(*) FROM compras WHERE situacao = 'comprado'");
        return $r;
    }

    /** Pedido com itens (código, EAN, embalagem, lotes já anotados) para a tela de separação */
    public static function pedido(int $id): ?array
    {
        $p = Banco::um("SELECT p.*, COALESCE(NULLIF(c.nome_fantasia, ''), c.razao_social) AS cliente, c.cidade, c.uf, c.telefone,
                v.nome AS vendedor, v.foto AS vendedor_foto, s.nome AS separador, d.nome AS despachante
            FROM pedidos p JOIN clientes c ON c.id = p.cliente_id LEFT JOIN usuarios v ON v.id = p.vendedor_id
            LEFT JOIN usuarios s ON s.id = p.separado_por LEFT JOIN usuarios d ON d.id = p.despachado_por WHERE p.id = ?", [$id]);
        if (!$p) {
            return null;
        }
        $p['itens'] = Banco::todos('SELECT i.*, pr.embalagem, pt.ean, tp.nome_rotulo
            FROM pedido_itens i LEFT JOIN produtos pr ON pr.id = i.produto_id LEFT JOIN produto_tecnico pt ON pt.produto_id = i.produto_id
            LEFT JOIN terceirizacao_produtos tp ON tp.id = i.terceirizacao_produto_id WHERE i.pedido_id = ? ORDER BY i.id', [$id]);
        $lotes = [];
        foreach (Banco::todos('SELECT * FROM pedido_item_lotes WHERE pedido_id = ? ORDER BY id', [$id]) as $l) {
            $lotes[(int) $l['pedido_item_id']][] = $l;
        }
        foreach ($p['itens'] as &$i) {
            $i['lotes'] = $lotes[(int) $i['id']] ?? [];
            $i['sugestoes'] = $i['produto_id'] ? self::lotesSugeridos((int) $i['produto_id']) : [];
        }
        return $p;
    }

    /** Validade de um lote do produto: da ordem de produção daquele lote ou, se não houver, de uma separação anterior */
    public static function validadeDoLote(int $produtoId, string $lote): ?string
    {
        if ($produtoId <= 0 || $lote === '') {
            return null;
        }
        $v = Banco::valor("SELECT MIN(validade) FROM ordens_producao WHERE produto_id = ? AND lote = ? AND validade IS NOT NULL", [$produtoId, $lote])
            ?: Banco::valor('SELECT MIN(validade) FROM pedido_item_lotes WHERE produto_id = ? AND lote = ? AND validade IS NOT NULL', [$produtoId, $lote]);
        return $v ?: null;
    }

    /** Lotes das ordens de produção concluídas do produto, a validade mais próxima primeiro (vencidos ficam de fora) */
    public static function lotesSugeridos(int $produtoId): array
    {
        return Banco::todos("SELECT lote, MIN(validade) AS validade, MAX(concluida_em) AS produzido_em FROM ordens_producao
            WHERE produto_id = ? AND situacao = 'concluida' AND lote IS NOT NULL AND lote <> '' AND (validade IS NULL OR validade >= CURDATE())
            GROUP BY lote ORDER BY MIN(validade) IS NULL, MIN(validade), MAX(concluida_em) LIMIT 6", [$produtoId]);
    }

    // ------------------------------------------------------------------
    // Separar
    // ------------------------------------------------------------------

    /** Pedido faturado agora entra na fila do depósito (chamado por PedidoService::marcarFaturado) */
    public static function entrarNaFila(int $pedidoId, string $quando): void
    {
        Banco::executar("UPDATE pedidos SET expedicao = 'aguardando', expedicao_desde = ? WHERE id = ? AND expedicao IS NULL", [$quando, $pedidoId]);
    }

    public static function marcarUrgente(int $id, bool $urgente, ?int $usuarioId): void
    {
        Banco::executar('UPDATE pedidos SET expedicao_prioridade = ? WHERE id = ? AND expedicao IS NOT NULL', [$urgente ? 1 : 0, $id]);
        PedidoService::historico($id, 'deposito', $urgente ? 'Marcado como urgente no depósito.' : 'Urgência retirada.', $usuarioId);
    }

    public static function comecar(int $id, int $usuarioId): void
    {
        $p = self::exigir($id, ['aguardando', 'separando']);
        Banco::executar("UPDATE pedidos SET expedicao = 'separando', separado_por = ?, expedicao_aguarda_produto = 0 WHERE id = ?", [$usuarioId, $id]);
        if ($p['expedicao'] === 'aguardando') {
            PedidoService::historico($id, 'deposito', 'Depósito começou a separar.', $usuarioId);
        }
    }

    /**
     * Grava a separação: quantidade separada e lotes de cada item.
     * $itens = [pedido_item_id => ['qtd' => n, 'lotes' => [['lote' => .., 'validade' => .., 'qtd' => n], ...]]]
     * Devolve a lista do que falta (vazia = completo).
     */
    public static function salvarSeparacao(int $id, array $itens, int $usuarioId): array
    {
        $p = self::exigir($id, ['aguardando', 'separando']);
        $obrigatorio = (bool) Config::get('deposito.lote_obrigatorio', true);
        $falta = [];
        Banco::transacao(function () use ($p, $itens, $usuarioId, $obrigatorio, &$falta) {
            Banco::executar('DELETE FROM pedido_item_lotes WHERE pedido_id = ?', [$p['id']]);
            foreach (Banco::todos('SELECT id, produto_id, nome, quantidade FROM pedido_itens WHERE pedido_id = ?', [$p['id']]) as $item) {
                $d = $itens[$item['id']] ?? [];
                $qtd = max(0, min((int) $item['quantidade'], (int) ($d['qtd'] ?? 0)));
                $lotes = [];
                foreach ((array) ($d['lotes'] ?? []) as $l) {
                    $lote = mb_substr(trim((string) ($l['lote'] ?? '')), 0, 40);
                    $q = (int) ($l['qtd'] ?? 0);
                    if ($lote !== '' && $q > 0) {
                        // a validade vem do próprio lote (ordem de produção ou separação anterior); não precisa digitar
                        $val = self::validadeDoLote((int) $item['produto_id'], $lote)
                            ?? (preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($l['validade'] ?? '')) ? $l['validade'] : null);
                        $lotes[] = [$lote, $val, $q];
                    }
                }
                $noLote = array_sum(array_column($lotes, 2));
                if ($qtd > 0 && $obrigatorio && $noLote < $qtd) {
                    throw new RuntimeException('Anote o lote de "' . $item['nome'] . '" (' . $qtd . ' un separadas, ' . $noLote . ' com lote).');
                }
                if ($noLote > $qtd && $qtd > 0) {
                    throw new RuntimeException('Os lotes de "' . $item['nome'] . '" somam mais do que a quantidade separada.');
                }
                foreach ($lotes as [$lote, $val, $q]) {
                    Banco::inserir('pedido_item_lotes', ['pedido_id' => $p['id'], 'pedido_item_id' => $item['id'], 'produto_id' => $item['produto_id'],
                        'lote' => $lote, 'validade' => $val, 'quantidade' => $q, 'criado_por' => $usuarioId]);
                }
                Banco::executar('UPDATE pedido_itens SET qtd_separada = ? WHERE id = ?', [$qtd, $item['id']]);
                if ($qtd < (int) $item['quantidade']) {
                    $falta[] = ['nome' => $item['nome'], 'falta' => (int) $item['quantidade'] - $qtd];
                }
            }
            if ($p['expedicao'] === 'aguardando') {
                Banco::executar("UPDATE pedidos SET expedicao = 'separando', separado_por = ? WHERE id = ?", [$usuarioId, $p['id']]);
            }
        });
        return $falta;
    }

    // ------------------------------------------------------------------
    // Caixas e embalagens usadas (dão baixa no estoque do item)
    // ------------------------------------------------------------------

    /** Itens que podem ser usados para embalar (categorias em Configurações › Depósito) */
    public static function embalagensDisponiveis(): array
    {
        $cats = array_values(array_filter(array_map('trim', (array) Config::get('deposito.embalagem_categorias', ['Embalagem de envio']))));
        if (!$cats) {
            return [];
        }
        return Banco::todos('SELECT id, nome, unidade, estoque FROM materias_primas WHERE ativo = 1 AND categoria IN (' . implode(',', array_fill(0, count($cats), '?')) . ') ORDER BY nome', $cats);
    }

    /** O que foi usado para embalar o pedido */
    public static function embalagensDoPedido(int $id): array
    {
        return Banco::todos('SELECT e.*, m.nome, m.unidade FROM pedido_embalagens e JOIN materias_primas m ON m.id = e.materia_prima_id
            WHERE e.pedido_id = ? AND e.quantidade > 0 ORDER BY m.nome', [$id]);
    }

    /** Quantas caixas (itens com "caixa" no nome): sugestão de volumes no despacho */
    public static function caixasDoPedido(int $id): int
    {
        return (int) round((float) Banco::valor("SELECT COALESCE(SUM(e.quantidade), 0) FROM pedido_embalagens e JOIN materias_primas m ON m.id = e.materia_prima_id
            WHERE e.pedido_id = ? AND LOWER(m.nome) LIKE '%caixa%'", [$id]));
    }

    /**
     * Grava as embalagens usadas. $linhas = [['item' => materia_prima_id, 'qtd' => '2'], ...]
     * Só a DIFERENÇA para o que já estava gravado mexe no estoque (salvar de novo não baixa duas vezes).
     */
    public static function salvarEmbalagem(int $id, array $linhas, int $usuarioId): void
    {
        $p = self::exigir($id, ['aguardando', 'separando']);
        $validos = array_column(self::embalagensDisponiveis(), 'nome', 'id');
        $novo = [];
        foreach ($linhas as $l) {
            $item = (int) ($l['item'] ?? 0);
            $qtd = ProducaoService::numero((string) ($l['qtd'] ?? '0'));
            if ($item <= 0 || $qtd <= 0) {
                continue;
            }
            if (!isset($validos[$item]) && !Banco::valor('SELECT 1 FROM pedido_embalagens WHERE pedido_id = ? AND materia_prima_id = ?', [$id, $item])) {
                throw new RuntimeException('Escolha a caixa na lista.');
            }
            if ($qtd > 500) {
                throw new RuntimeException('Quantidade de embalagem muito alta. Confira o número.');
            }
            $novo[$item] = ($novo[$item] ?? 0) + $qtd;
        }
        $antes = array_map('floatval', array_column(Banco::todos('SELECT materia_prima_id, quantidade FROM pedido_embalagens WHERE pedido_id = ?', [$id]), 'quantidade', 'materia_prima_id'));
        Banco::transacao(function () use ($id, $novo, $antes, $usuarioId) {
            foreach (array_unique(array_merge(array_keys($novo), array_keys($antes))) as $item) {
                $delta = round(($novo[$item] ?? 0) - ($antes[$item] ?? 0), 3);
                if (abs($delta) < 0.0005) {
                    continue;
                }
                ProducaoService::movimentarEmbalagem((int) $item, $delta, $id, $usuarioId);
                Banco::executar('INSERT INTO pedido_embalagens (pedido_id, materia_prima_id, quantidade, usuario_id) VALUES (?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE quantidade = VALUES(quantidade), usuario_id = VALUES(usuario_id), atualizado_em = NOW()',
                    [$id, (int) $item, $novo[$item] ?? 0, $usuarioId]);
            }
        });
        if ($novo != $antes) {
            Auditoria::registrar('pedido', $id, 'embalagem', $antes ?: null, $novo ?: null);
        }
        if ($p['expedicao'] === 'aguardando' && $novo) {
            Banco::executar("UPDATE pedidos SET expedicao = 'separando', separado_por = ? WHERE id = ?", [$usuarioId, $id]);
        }
    }

    /** Concluir a separação. Com falta: $decisao = aguardar (fica na fila) | despachar_sem (obs. obrigatória) */
    public static function concluir(int $id, int $usuarioId, string $decisao = '', string $observacao = ''): string
    {
        $p = self::exigir($id, ['separando']);
        $falta = Banco::todos('SELECT nome, quantidade - COALESCE(qtd_separada, 0) AS falta FROM pedido_itens
            WHERE pedido_id = ? AND COALESCE(qtd_separada, 0) < quantidade', [$id]);
        if (Banco::valor('SELECT 1 FROM pedido_itens WHERE pedido_id = ? AND qtd_separada IS NULL', [$id])) {
            throw new RuntimeException('Salve a separação dos itens antes de concluir.');
        }
        $lista = implode(', ', array_map(fn($f) => $f['falta'] . ' × ' . $f['nome'], $falta));
        if ($falta && $decisao === 'aguardar') {
            Banco::executar('UPDATE pedidos SET expedicao_aguarda_produto = 1 WHERE id = ?', [$id]);
            PedidoService::historico($id, 'deposito', "Depósito aguardando produto: $lista.", $usuarioId);
            self::avisarFalta($p, "Pedido #$id está esperando produto no depósito", $lista, $usuarioId, false);
            Auditoria::registrar('pedido', $id, 'expedicao_aguardar', null, ['falta' => $lista]);
            return 'O pedido ficou na fila aguardando o produto. O vendedor e a Produção foram avisados.';
        }
        // vai sair: precisa dizer em que caixa foi embalado (quando fica aguardando produto, ainda não embalou)
        if (Config::get('deposito.embalagem_obrigatoria', true) && self::embalagensDisponiveis() && !self::embalagensDoPedido($id)) {
            throw new RuntimeException('Informe a caixa (ou embalagem) usada para embalar o pedido.');
        }
        if ($falta && $decisao !== 'despachar_sem') {
            throw new RuntimeException('Falta produto: ' . $lista . '. Escolha aguardar o produto ou despachar sem o que falta.');
        }
        if ($falta && mb_strlen(trim($observacao)) < 3) {
            throw new RuntimeException('Para despachar sem o que falta, escreva a observação (ex.: combinado com o vendedor).');
        }
        Banco::executar("UPDATE pedidos SET expedicao = 'separado', separado_por = ?, separado_em = NOW(), expedicao_aguarda_produto = 0, falta = ?,
            expedicao_obs = COALESCE(NULLIF(?, ''), expedicao_obs) WHERE id = ?", [$usuarioId, $falta ? 1 : 0, mb_substr(trim($observacao), 0, 500), $id]);
        $emb = implode(', ', array_map(fn($e) => rtrim(rtrim(number_format((float) $e['quantidade'], 3, ',', '.'), '0'), ',') . ' × ' . $e['nome'], self::embalagensDoPedido($id)));
        PedidoService::historico($id, 'deposito', ($falta ? "Separado sem: $lista. " . trim($observacao) : 'Separado no depósito.') . ($emb ? " Embalagem: $emb." : ''), $usuarioId);
        if ($falta) {
            NotificacaoService::diretoria('problemas', "Pedido #$id vai sem alguns itens", '/pedidos/' . $id,
                ['texto' => mb_substr("Faltou: $lista. " . trim($observacao), 0, 300), 'pedido_id' => $id], $usuarioId);
        }
        Auditoria::registrar('pedido', $id, 'expedicao_separado', ['expedicao' => 'separando'], ['expedicao' => 'separado', 'falta' => $lista]);
        if ($falta) {
            self::avisarFalta($p, "Pedido #$id vai sair sem alguns itens", $lista . ' · ' . trim($observacao), $usuarioId, true);
            return 'Separado sem os itens que faltam. O vendedor e o Financeiro foram avisados (a nota pode precisar de ajuste).';
        }
        return 'Separação concluída. Agora é só despachar.';
    }

    /** Despachar: transportadora, volumes, peso, rastreio. Vendedor avisado; aparece no pedido, no catálogo e na linha do tempo. */
    public static function despachar(int $id, array $d, int $usuarioId): void
    {
        $p = self::exigir($id, ['separado']);
        $transportadora = mb_substr(trim((string) ($d['transportadora'] ?? '')), 0, 80);
        if ($transportadora === '') {
            throw new RuntimeException('Informe a transportadora (ou "Retirada no balcão").');
        }
        $volumes = max(1, (int) ($d['volumes'] ?? 1));
        $peso = ProducaoService::numero((string) ($d['peso'] ?? '')) ?: null;
        $rastreio = mb_substr(trim((string) ($d['rastreio'] ?? '')), 0, 80) ?: null;
        $obs = mb_substr(trim((string) ($d['observacao'] ?? '')), 0, 500);
        Banco::executar("UPDATE pedidos SET expedicao = 'despachado', despachado_por = ?, despachado_em = NOW(), transportadora = ?, volumes = ?, peso_kg = ?,
            rastreio = ?, expedicao_obs = CASE WHEN ? = '' THEN expedicao_obs ELSE ? END WHERE id = ?",
            [$usuarioId, $transportadora, $volumes, $peso, $rastreio, $obs, $obs, $id]);
        $texto = "Despachado · $transportadora · $volumes volume(s)" . ($rastreio ? " · rastreio $rastreio" : '');
        PedidoService::historico($id, 'deposito', $texto, $usuarioId);
        ClienteEvento::registrar((int) $p['cliente_id'], 'pedido', "Pedido #$id despachado · $transportadora" . ($rastreio ? " · rastreio $rastreio" : ''), $usuarioId, null, ['pedido_id' => $id]);
        Auditoria::registrar('pedido', $id, 'expedicao_despachado', null, ['transportadora' => $transportadora, 'volumes' => $volumes, 'rastreio' => $rastreio]);
        if ($p['vendedor_id']) {
            NotificacaoService::avisar([(int) $p['vendedor_id']], 'pedido_despachado', "Pedido #$id despachado", '/pedidos/' . $id,
                ['pedido_id' => $id, 'cliente_id' => (int) $p['cliente_id'], 'texto' => $transportadora . ($rastreio ? ' · rastreio ' . $rastreio : '')], $usuarioId);
        }
    }

    // ------------------------------------------------------------------
    // Lotes (rastreabilidade)
    // ------------------------------------------------------------------

    /** Para quais clientes foi um lote (ou os lotes de um produto) */
    public static function rastrear(string $busca): array
    {
        $busca = trim($busca);
        if ($busca === '') {
            return [];
        }
        return Banco::todos("SELECT l.lote, l.validade, l.quantidade, l.criado_em, p.id AS pedido_id, p.numero_nf, p.despachado_em,
                COALESCE(i.codigo, pr.codigo) AS codigo, i.nome AS produto,
                COALESCE(NULLIF(c.nome_fantasia, ''), c.razao_social) AS cliente, c.cnpj, c.cidade, c.uf, c.telefone
            FROM pedido_item_lotes l JOIN pedidos p ON p.id = l.pedido_id JOIN pedido_itens i ON i.id = l.pedido_item_id
            JOIN clientes c ON c.id = p.cliente_id LEFT JOIN produtos pr ON pr.id = l.produto_id
            WHERE l.lote = ? OR pr.codigo = ? OR i.nome LIKE ? ORDER BY l.lote, l.criado_em DESC LIMIT 500", [$busca, $busca, '%' . $busca . '%']);
    }

    // ------------------------------------------------------------------

    private static function exigir(int $id, array $etapas): array
    {
        $p = Banco::um('SELECT * FROM pedidos WHERE id = ?', [$id]);
        if (!$p || $p['expedicao'] === null) {
            throw new RuntimeException('Este pedido não está na fila do depósito.');
        }
        if (!in_array($p['expedicao'], $etapas, true)) {
            throw new RuntimeException('Este pedido já está em "' . (self::ETAPAS[$p['expedicao']][0] ?? $p['expedicao']) . '".');
        }
        return $p;
    }

    /** Falta de produto: vendedor sempre; Produção (aguardar) ou Financeiro (despachar sem) */
    private static function avisarFalta(array $p, string $titulo, string $texto, int $usuarioId, bool $saiuSem): void
    {
        $quem = $p['vendedor_id'] ? [(int) $p['vendedor_id']] : [];
        $outros = $saiuSem ? NotificacaoService::destinatarios('pedidos.aprovar', 'aprovacao', 'cobranca')
            : NotificacaoService::destinatarios('producao.gerenciar', 'producao');
        NotificacaoService::avisar(array_merge($quem, $outros), 'pedido_falta', $titulo, '/pedidos/' . (int) $p['id'],
            ['pedido_id' => (int) $p['id'], 'cliente_id' => (int) $p['cliente_id'], 'texto' => $texto], $usuarioId);
    }
}
