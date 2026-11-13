<?php
namespace App\Services;

use App\Core\Auditoria;
use App\Core\Banco;
use App\Integrations\Integracoes;
use App\Models\ClienteEvento;

/**
 * Regras do pedido — as MESMAS para o catálogo (cliente), o sistema (vendedor) e o app (externo).
 *
 *  1. Preço = tabela do cliente (Lojista / Distribuidor / Terceirização). O cliente nunca escolhe a tabela.
 *  2. Pedido mínimo da UF (Configurações › Regiões): o da UF vale mais que o da região.
 *  3. Boleto bloqueado para cliente com título em atraso ou em cobrança. Condição respeita o valor mínimo dela.
 *  4. Venda por unidade. Produto sem preço na tabela do cliente não entra.
 *  5. O mesmo pedido (uuid) nunca entra duas vezes.
 *  6. Criado → vai ao ERP como orçamento → Financeiro aprova → ERP fatura → volta para a ficha do cliente.
 */
class PedidoService
{
    public const SITUACOES = [
        'aguardando_aprovacao' => ['Aguardando aprovação', 'laranja'],
        'aprovado'             => ['Aprovado', 'lima'],
        'faturado'             => ['Faturado', 'verde'],
        'recusado'             => ['Recusado', 'vermelho'],
        'cancelado'            => ['Cancelado', 'cinza'],
    ];

    // ------------------------------------------------------------------
    // Preço, mínimo e pagamento
    // ------------------------------------------------------------------

    /** Tabela de preço do cliente (padrão: a primeira ativa, Lojista) */
    public static function tabelaDoCliente(array $cliente): array
    {
        $tabela = $cliente['tabela_preco_id'] ? Banco::um('SELECT * FROM tabelas_preco WHERE id = ? AND ativa = 1', [$cliente['tabela_preco_id']]) : null;
        return $tabela ?: Banco::um('SELECT * FROM tabelas_preco WHERE ativa = 1 ORDER BY id LIMIT 1');
    }

    /** Pedido mínimo da UF do cliente */
    public static function pedidoMinimo(?string $uf): float
    {
        $r = Banco::um('SELECT COALESCE(ru.pedido_minimo, r.pedido_minimo) AS minimo, r.nome FROM regiao_ufs ru JOIN regioes r ON r.id = ru.regiao_id WHERE ru.uf = ?', [(string) $uf]);
        return $r ? (float) $r['minimo'] : 0.0;
    }

    /** Boleto bloqueado? (título em atraso ou caso aberto na Cobrança) */
    public static function boletoBloqueado(array $cliente): bool
    {
        if (!\App\Core\Config::get('cobranca.bloquear_boleto', true)) {
            return false;
        }
        return $cliente['credito_situacao'] === 'atraso'
            || (bool) Banco::valor("SELECT 1 FROM cobrancas WHERE cliente_id = ? AND situacao = 'aberta' LIMIT 1", [$cliente['id']]);
    }

    /** Formas de pagamento com as condições; cada uma diz se está liberada (e por quê não) para este cliente e valor */
    public static function formasDePagamento(array $cliente, float $subtotal): array
    {
        $bloqueioBoleto = self::boletoBloqueado($cliente);
        $formas = Banco::todos('SELECT * FROM formas_pagamento WHERE ativa = 1 ORDER BY ordem, id');
        foreach ($formas as &$f) {
            $f['bloqueio'] = $f['bloqueia_com_atraso'] && $bloqueioBoleto ? 'Indisponível: há título em aberto. Use Pix ou cartão.' : null;
            $f['condicoes'] = Banco::todos('SELECT * FROM condicoes_pagamento WHERE forma_pagamento_id = ? AND ativa = 1 ORDER BY valor_minimo, id', [$f['id']]);
            foreach ($f['condicoes'] as &$c) {
                $c['bloqueio'] = $subtotal < (float) $c['valor_minimo'] ? 'A partir de ' . dinheiro((float) $c['valor_minimo']) : null;
            }
        }
        return $formas;
    }

    /**
     * Monta o carrinho: preços da tabela do cliente, totais, mínimo e o que falta.
     * $quantidades: [produto_id => quantidade]
     */
    public static function calcular(array $cliente, array $quantidades): array
    {
        $tabela = self::tabelaDoCliente($cliente);
        $quantidades = array_filter(array_map('intval', $quantidades), fn($q) => $q > 0);
        $itens = [];
        $semPreco = [];
        if ($quantidades) {
            $ids = implode(',', array_map('intval', array_keys($quantidades)));
            $linhas = Banco::todos("SELECT p.id, p.codigo, p.nome, p.slug, p.embalagem, m.arquivo AS imagem, pp.preco
                FROM produtos p LEFT JOIN midias m ON m.id = p.imagem_id
                LEFT JOIN produto_precos pp ON pp.produto_id = p.id AND pp.tabela_preco_id = ?
                WHERE p.id IN ($ids) AND p.ativo = 1 AND p.visivel_catalogo = 1", [$tabela['id']]);
            foreach ($linhas as $l) {
                if ($l['preco'] === null) {
                    $semPreco[] = $l['nome'];
                    continue;
                }
                $q = min(9999, $quantidades[$l['id']]);
                $itens[] = $l + ['quantidade' => $q, 'preco' => (float) $l['preco'], 'total' => round($q * (float) $l['preco'], 2)];
            }
        }
        usort($itens, fn($a, $b) => strcmp($a['nome'], $b['nome']));
        $subtotal = round(array_sum(array_column($itens, 'total')), 2);
        $minimo = self::pedidoMinimo($cliente['uf']);
        return ['tabela' => $tabela, 'itens' => $itens, 'sem_preco' => $semPreco, 'subtotal' => $subtotal,
            'quantidade' => array_sum(array_column($itens, 'quantidade')), 'minimo' => $minimo, 'falta' => max(0, round($minimo - $subtotal, 2))];
    }

    // ------------------------------------------------------------------
    // Criar
    // ------------------------------------------------------------------

    /**
     * Cria o pedido (validando tudo). Devolve [id, erros]. Se o uuid já existe, devolve o pedido existente.
     * $d: itens [produto_id => qtd], forma_pagamento_id, condicao_id, observacoes, origem, vendedor_id, usuario_id, uuid
     */
    public static function criar(array $cliente, array $d): array
    {
        $uuid = self::uuidValido((string) ($d['uuid'] ?? '')) ? strtolower($d['uuid']) : self::novoUuid();
        if ($existente = Banco::valor('SELECT id FROM pedidos WHERE uuid = ?', [$uuid])) {
            return [(int) $existente, []]; // já recebido (ex.: app reenviou ao voltar a internet)
        }
        $carrinho = self::calcular($cliente, (array) ($d['itens'] ?? []));
        $erros = [];
        if (!$carrinho['itens']) {
            $erros[] = 'O pedido está vazio.';
        }
        if ($carrinho['falta'] > 0) {
            $erros[] = 'Faltam ' . dinheiro($carrinho['falta']) . ' para o pedido mínimo da sua região (' . dinheiro($carrinho['minimo']) . ').';
        }
        $forma = null;
        $condicao = null;
        foreach (self::formasDePagamento($cliente, $carrinho['subtotal']) as $f) {
            if ((int) $f['id'] === (int) ($d['forma_pagamento_id'] ?? 0)) {
                $forma = $f;
                foreach ($f['condicoes'] as $c) {
                    if ((int) $c['id'] === (int) ($d['condicao_id'] ?? 0)) {
                        $condicao = $c;
                    }
                }
            }
        }
        if (!$forma) {
            $erros[] = 'Escolha a forma de pagamento.';
        } elseif ($forma['bloqueio']) {
            $erros[] = $forma['nome'] . ': ' . $forma['bloqueio'];
        } elseif ($forma['condicoes'] && !$condicao) {
            $erros[] = 'Escolha a condição de pagamento.';
        } elseif ($condicao && $condicao['bloqueio']) {
            $erros[] = $condicao['nome'] . ': ' . $condicao['bloqueio'] . '.';
        }
        if ($erros) {
            return [null, $erros];
        }

        $desconto = $condicao ? round($carrinho['subtotal'] * (float) $condicao['desconto_pct'] / 100, 2) : 0.0;
        $vendedor = (int) ($d['vendedor_id'] ?? 0) ?: (int) ($d['usuario_id'] ?? 0) ?: (int) $cliente['responsavel_id'] ?: null;
        $origem = in_array($d['origem'] ?? '', ['catalogo', 'sistema', 'app'], true) ? $d['origem'] : 'sistema';

        $id = Banco::transacao(function () use ($cliente, $carrinho, $forma, $condicao, $desconto, $vendedor, $origem, $uuid, $d) {
            $id = Banco::inserir('pedidos', [
                'uuid' => $uuid, 'cliente_id' => $cliente['id'], 'vendedor_id' => $vendedor, 'origem' => $origem,
                'tabela_preco_id' => $carrinho['tabela']['id'], 'forma_pagamento_id' => $forma['id'], 'condicao_id' => $condicao['id'] ?? null,
                'subtotal' => $carrinho['subtotal'], 'desconto' => $desconto, 'total' => round($carrinho['subtotal'] - $desconto, 2),
                'observacoes' => mb_substr(trim((string) ($d['observacoes'] ?? '')), 0, 1000) ?: null,
                'criado_por_usuario' => $d['usuario_id'] ?? null, 'criado_por_cliente' => $origem === 'catalogo' ? 1 : 0,
            ]);
            foreach ($carrinho['itens'] as $i) {
                Banco::inserir('pedido_itens', ['pedido_id' => $id, 'produto_id' => $i['id'], 'codigo' => $i['codigo'] ?? null, 'nome' => $i['nome'],
                    'quantidade' => $i['quantidade'], 'preco_unitario' => $i['preco'], 'total' => $i['total']]);
            }
            $quem = ['catalogo' => 'pelo cliente no catálogo', 'sistema' => 'pela equipe', 'app' => 'pelo app do vendedor externo'][$origem];
            self::historico($id, 'aguardando_aprovacao', "Pedido criado $quem.", $d['usuario_id'] ?? null);
            ClienteEvento::registrar((int) $cliente['id'], 'pedido', 'Pedido #' . $id . ' · ' . dinheiro(round($carrinho['subtotal'] - $desconto, 2)) . ' · ' . $forma['nome'] . ' (aguardando aprovação)',
                $d['usuario_id'] ?? null, ucfirst($origem), ['pedido_id' => $id]);
            Auditoria::registrar('pedido', $id, 'criar', null, ['cliente_id' => $cliente['id'], 'total' => round($carrinho['subtotal'] - $desconto, 2), 'origem' => $origem]);
            return $id;
        });
        self::enviarAoErp($id);
        NotificacaoService::pedidoCriado((int) $id, $d['usuario_id'] ?? null); // sino do Financeiro (e do vendedor, se veio do catálogo)
        return [$id, []];
    }

    /** Envia ao ERP como orçamento. Se falhar, fica pendente e o cron tenta de novo (o uuid evita duplicar no ERP) */
    public static function enviarAoErp(int $id): bool
    {
        $p = Banco::um('SELECT p.*, c.cnpj, f.slug AS forma, cp.nome AS condicao FROM pedidos p JOIN clientes c ON c.id = p.cliente_id
            JOIN formas_pagamento f ON f.id = p.forma_pagamento_id LEFT JOIN condicoes_pagamento cp ON cp.id = p.condicao_id WHERE p.id = ?', [$id]);
        if (!$p || $p['omie_codigo'] || in_array($p['situacao'], ['cancelado', 'recusado'], true)) {
            return (bool) ($p['omie_codigo'] ?? false);
        }
        $itens = Banco::todos('SELECT i.quantidade, i.preco_unitario, COALESCE(pr.omie_codigo, pr.slug) AS omie_codigo_produto FROM pedido_itens i
            LEFT JOIN produtos pr ON pr.id = i.produto_id WHERE i.pedido_id = ?', [$id]);
        try {
            $r = Integracoes::erp()->enviarPedido(['uuid' => $p['uuid'], 'cnpj' => (string) $p['cnpj'], 'itens' => $itens,
                'forma_pagamento' => $p['forma'], 'condicao' => $p['condicao'], 'observacoes' => (string) $p['observacoes']]);
        } catch (\Throwable $erro) {
            error_log('Envio do pedido ' . $id . ' ao ERP: ' . $erro->getMessage());
            return false;
        }
        Banco::atualizar('pedidos', $id, ['omie_codigo' => $r['omie_codigo'], 'omie_numero' => $r['numero']]);
        self::historico($id, $p['situacao'], 'Enviado ao ERP como orçamento nº ' . $r['numero'] . '.', null);
        return true;
    }

    // ------------------------------------------------------------------
    // Fluxo
    // ------------------------------------------------------------------

    public static function aprovar(array $pedido, int $usuarioId): ?string
    {
        if ($pedido['situacao'] !== 'aguardando_aprovacao') {
            return 'Este pedido não está aguardando aprovação.';
        }
        if (!$pedido['omie_codigo'] && !self::enviarAoErp((int) $pedido['id'])) {
            return 'Não foi possível enviar o pedido ao ERP agora. Tente de novo em alguns minutos.';
        }
        $pedido = Banco::um('SELECT * FROM pedidos WHERE id = ?', [$pedido['id']]);
        try {
            Integracoes::erp()->aprovarPedido((string) $pedido['omie_codigo']);
        } catch (\Throwable $erro) {
            error_log('Aprovar pedido ' . $pedido['id'] . ' no ERP: ' . $erro->getMessage());
            return 'O ERP não aceitou a aprovação agora. Tente de novo em alguns minutos.';
        }
        Banco::atualizar('pedidos', (int) $pedido['id'], ['situacao' => 'aprovado', 'aprovado_por' => $usuarioId, 'aprovado_em' => date('Y-m-d H:i:s'), 'motivo_recusa' => null]);
        self::historico((int) $pedido['id'], 'aprovado', 'Aprovado pelo Financeiro.', $usuarioId);
        ClienteEvento::registrar((int) $pedido['cliente_id'], 'pedido', 'Pedido #' . $pedido['id'] . ' aprovado pelo Financeiro', $usuarioId, null, ['pedido_id' => (int) $pedido['id']]);
        Auditoria::registrar('pedido', (int) $pedido['id'], 'aprovar', ['situacao' => $pedido['situacao']], ['situacao' => 'aprovado']);
        NotificacaoService::pedidoMudou((int) $pedido['id'], 'aprovado', $usuarioId);
        return null;
    }

    public static function recusar(array $pedido, string $motivo, int $usuarioId): ?string
    {
        if ($pedido['situacao'] !== 'aguardando_aprovacao') {
            return 'Este pedido não está aguardando aprovação.';
        }
        Banco::atualizar('pedidos', (int) $pedido['id'], ['situacao' => 'recusado', 'motivo_recusa' => mb_substr($motivo, 0, 500)]);
        self::historico((int) $pedido['id'], 'recusado', 'Recusado: ' . $motivo, $usuarioId);
        ClienteEvento::registrar((int) $pedido['cliente_id'], 'pedido', 'Pedido #' . $pedido['id'] . ' recusado: ' . $motivo, $usuarioId, null, ['pedido_id' => (int) $pedido['id']]);
        Auditoria::registrar('pedido', (int) $pedido['id'], 'recusar', ['situacao' => $pedido['situacao']], ['situacao' => 'recusado', 'motivo' => $motivo]);
        NotificacaoService::pedidoMudou((int) $pedido['id'], 'recusado', $usuarioId, 'Motivo: ' . $motivo);
        return null;
    }

    public static function cancelar(array $pedido, ?int $usuarioId, string $quem): ?string
    {
        if ($pedido['situacao'] !== 'aguardando_aprovacao') {
            return 'Só dá para cancelar pedido que ainda não foi aprovado. Fale com o Financeiro.';
        }
        Banco::atualizar('pedidos', (int) $pedido['id'], ['situacao' => 'cancelado']);
        self::historico((int) $pedido['id'], 'cancelado', "Cancelado $quem.", $usuarioId);
        ClienteEvento::registrar((int) $pedido['cliente_id'], 'pedido', 'Pedido #' . $pedido['id'] . " cancelado $quem", $usuarioId, null, ['pedido_id' => (int) $pedido['id']]);
        Auditoria::registrar('pedido', (int) $pedido['id'], 'cancelar', ['situacao' => $pedido['situacao']], ['situacao' => 'cancelado']);
        NotificacaoService::pedidoMudou((int) $pedido['id'], 'cancelado', $usuarioId); // tira o "para aprovar" do sino do Financeiro
        return null;
    }

    /**
     * O ERP faturou: pedido vira "faturado" e a ficha do cliente é atualizada
     * (última compra, intervalo médio de compra, vira cliente ativo).
     */
    public static function marcarFaturado(array $pedido, string $numeroNf, ?string $quando = null): void
    {
        $quando ??= date('Y-m-d H:i:s');
        Banco::atualizar('pedidos', (int) $pedido['id'], ['situacao' => 'faturado', 'numero_nf' => $numeroNf, 'faturado_em' => $quando]);
        self::historico((int) $pedido['id'], 'faturado', "Faturado · NF $numeroNf.", null);
        ClienteEvento::registrar((int) $pedido['cliente_id'], 'pedido', 'Pedido #' . $pedido['id'] . ' faturado · NF ' . $numeroNf . ' · ' . dinheiro((float) $pedido['total']), null, 'ERP', ['pedido_id' => (int) $pedido['id']], $quando);
        self::atualizarCompras((int) $pedido['cliente_id']);
        ProducaoService::baixarVenda((int) $pedido['id'], $quando); // estoque acabado da fábrica (se configurado)
        if (strtotime($quando) >= strtotime('-1 day')) { // faturamento de agora (não o histórico da importação)
            NotificacaoService::pedidoMudou((int) $pedido['id'], 'faturado', null, 'NF ' . $numeroNf);
        }
    }

    /** Recalcula última compra e intervalo médio entre compras (base do alerta de recompra) */
    public static function atualizarCompras(int $clienteId): void
    {
        $datas = array_column(Banco::todos("SELECT DISTINCT DATE(faturado_em) AS d FROM pedidos WHERE cliente_id = ? AND situacao = 'faturado' ORDER BY d", [$clienteId]), 'd');
        if (!$datas) {
            return;
        }
        $intervalos = [];
        for ($i = 1; $i < count($datas); $i++) {
            $intervalos[] = (strtotime($datas[$i]) - strtotime($datas[$i - 1])) / 86400;
        }
        $dados = ['ultima_compra_em' => end($datas), 'tipo' => 'cliente'];
        if ($intervalos) {
            $dados['intervalo_medio_dias'] = (int) round(array_sum($intervalos) / count($intervalos));
        }
        $ganho = Banco::valor("SELECT id FROM funil_etapas WHERE tipo = 'ganho' AND ativa = 1 ORDER BY ordem LIMIT 1");
        if ($ganho) {
            $dados['etapa_id'] = (int) $ganho;
        }
        Banco::atualizar('clientes', $clienteId, $dados);
    }

    public static function historico(int $pedidoId, string $situacao, string $texto, ?int $usuarioId): void
    {
        Banco::inserir('pedido_historico', ['pedido_id' => $pedidoId, 'situacao' => $situacao, 'texto' => mb_substr($texto, 0, 500), 'usuario_id' => $usuarioId]);
    }

    // ------------------------------------------------------------------
    // UUID
    // ------------------------------------------------------------------

    public static function novoUuid(): string
    {
        $b = random_bytes(16);
        $b[6] = chr(ord($b[6]) & 0x0f | 0x40);
        $b[8] = chr(ord($b[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
    }

    public static function uuidValido(string $uuid): bool
    {
        return (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $uuid);
    }
}
