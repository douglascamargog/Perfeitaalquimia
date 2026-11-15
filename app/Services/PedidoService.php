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
     * $quantidades: [produto_id => quantidade], e para os produtos com a marca do cliente (Terceirização)
     * a chave "t{id}" (ex.: "t12" => 300) — linha própria, com o preço do produto terceirizado.
     */
    public static function calcular(array $cliente, array $quantidades): array
    {
        $tabela = self::tabelaDoCliente($cliente);
        $quantidades = array_filter(array_map('intval', $quantidades), fn($q) => $q > 0);
        $itens = [];
        $semPreco = [];
        // Produtos terceirizados do cliente (linhas "t{id}"): só os dele, com preço definido
        $terc = array_filter($quantidades, fn($k) => is_string($k) && str_starts_with($k, 't'), ARRAY_FILTER_USE_KEY);
        $quantidades = array_diff_key($quantidades, $terc);
        if ($terc) {
            $linhasTerc = TerceirizacaoService::linhasDoPedido((int) $cliente['id']);
            foreach ($terc as $chave => $q) {
                if (!isset($linhasTerc[$chave])) {
                    continue; // não é deste cliente (ou está sem preço): ignora
                }
                $l = $linhasTerc[$chave];
                $q = min(99999, $q);
                $itens[] = ['id' => (int) $l['produto_id'], 'chave' => $chave, 'terceirizacao_produto_id' => (int) $l['id'], 'codigo' => $l['codigo_cliente'] ?: $l['produto_codigo'],
                    'nome' => $l['nome_rotulo'], 'slug' => null, 'embalagem' => $l['embalagem'], 'imagem' => null,
                    'quantidade' => $q, 'preco' => (float) $l['preco_final'], 'total' => round($q * (float) $l['preco_final'], 2)];
            }
        }
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
        // Campanhas comerciais: preço promocional nos produtos (as linhas com a marca do cliente ficam de fora)
        $campanhas = CampanhaService::paraCliente($cliente);
        $promo = CampanhaService::promocoes($campanhas);
        foreach ($itens as &$i) {
            if (empty($i['chave']) && isset($promo[$i['id']])) {
                $i['preco_cheio'] = (float) $i['preco'];
                $i['promo_pct'] = $promo[$i['id']]['pct'];
                $i['campanha_id'] = $promo[$i['id']]['campanha_id'];
                $i['preco'] = CampanhaService::precoPromo($i['preco'], $i['promo_pct']);
                $i['total'] = round($i['quantidade'] * $i['preco'], 2);
            }
        }
        unset($i);
        $subtotal = round(array_sum(array_column($itens, 'total')), 2);
        $minimo = self::pedidoMinimo($cliente['uf']);
        // Faixas (desconto, brinde, frete) pelo valor do pedido já com os preços promocionais
        $beneficios = CampanhaService::beneficios($campanhas, $subtotal);
        $descontoCampanha = round($subtotal * $beneficios['desconto_pct'] / 100, 2);
        $usadas = array_unique(array_filter(array_column($itens, 'campanha_id')));
        foreach ($campanhas as $c) { // campanha com produto em promoção neste pedido também conta como aplicada
            if (in_array((int) $c['id'], $usadas, true) && !in_array($c['titulo'] ?: $c['nome'], $beneficios['nomes'], true)) {
                $beneficios['nomes'][] = $c['titulo'] ?: $c['nome'];
            }
        }
        return ['tabela' => $tabela, 'itens' => $itens, 'sem_preco' => $semPreco, 'subtotal' => $subtotal,
            'quantidade' => array_sum(array_column($itens, 'quantidade')), 'minimo' => $minimo, 'falta' => max(0, round($minimo - $subtotal, 2)),
            'campanhas' => $campanhas, 'campanha' => $beneficios, 'desconto_campanha' => $descontoCampanha,
            'economia_promo' => round(array_sum(array_map(fn($i) => isset($i['preco_cheio']) ? ($i['preco_cheio'] - $i['preco']) * $i['quantidade'] : 0, $itens)), 2)];
    }

    /**
     * Produtos que este cliente pode comprar, com o preço da tabela dele (para montar pedido ou orçamento).
     * Os produtos com a marca do cliente (Terceirização) vêm primeiro, como linhas próprias ("t{id}").
     */
    public static function produtosDoCliente(array $cliente): array
    {
        $tabela = self::tabelaDoCliente($cliente);
        $produtos = Banco::todos('SELECT p.id, p.codigo, p.nome, p.embalagem, c.nome AS categoria, m.arquivo AS imagem, pp.preco, e.quantidade AS estoque
            FROM produtos p JOIN produto_precos pp ON pp.produto_id = p.id AND pp.tabela_preco_id = ?
            LEFT JOIN categorias c ON c.id = p.categoria_id LEFT JOIN midias m ON m.id = p.imagem_id LEFT JOIN produto_estoque e ON e.produto_id = p.id
            WHERE p.ativo = 1 AND p.visivel_catalogo = 1 ORDER BY p.nome', [$tabela['id']]);
        // Preço promocional das campanhas do cliente (o mesmo que calcular() aplica ao gravar)
        $promo = CampanhaService::promocoes(CampanhaService::paraCliente($cliente));
        foreach ($produtos as &$p) {
            $p['promo_pct'] = $promo[$p['id']]['pct'] ?? 0;
            if ($p['promo_pct'] > 0) {
                $p['preco_cheio'] = (float) $p['preco'];
                $p['preco'] = CampanhaService::precoPromo((float) $p['preco'], $p['promo_pct']);
            }
        }
        unset($p);
        $marca = [];
        foreach (TerceirizacaoService::linhasDoPedido((int) $cliente['id']) as $chave => $t) {
            $marca[] = ['id' => $chave, 'codigo' => $t['codigo_cliente'] ?: $t['produto_codigo'], 'nome' => $t['nome_rotulo'], 'embalagem' => $t['embalagem'],
                'categoria' => 'marca do cliente', 'imagem' => null, 'preco' => $t['preco_final'], 'estoque' => null, 'terceirizado' => true,
                'rotulos' => (int) $t['rotulos_estoque']];
        }
        return array_merge($marca, $produtos);
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

        $vendedor = (int) ($d['vendedor_id'] ?? 0) ?: (int) ($d['usuario_id'] ?? 0) ?: (int) $cliente['responsavel_id'] ?: null;
        $origem = in_array($d['origem'] ?? '', ['catalogo', 'sistema', 'app', 'orcamento'], true) ? $d['origem'] : 'sistema';

        // Desconto da vendedora (só em pedido montado pela equipe): por produto e no pedido inteiro
        $subtotalTabela = $carrinho['subtotal'];
        $descontoPedidoPct = 0.0;
        if (in_array($origem, ['sistema', 'app'], true)) {
            $pct = fn($v) => max(0, min(100, round(ProducaoService::numero((string) $v), 2)));
            foreach ($carrinho['itens'] as &$i) {
                $chave = (string) ($i['chave'] ?? $i['id']);
                $i['preco_tabela'] = $i['preco'];
                $i['desconto_pct'] = $pct($d['descontos'][$chave] ?? 0);
                if ($i['desconto_pct'] > 0) {
                    $i['preco'] = round($i['preco'] * (1 - $i['desconto_pct'] / 100), 2);
                    $i['total'] = round($i['quantidade'] * $i['preco'], 2);
                }
            }
            unset($i);
            $carrinho['subtotal'] = round(array_sum(array_column($carrinho['itens'], 'total')), 2);
            $descontoPedidoPct = $pct($d['desconto_pedido_pct'] ?? 0);
        }
        // Campanha: a faixa foi calculada com o total já promocional (antes dos descontos da vendedora)
        $camp = $carrinho['campanha'];
        $deuDescontoVendedor = $subtotalTabela - $carrinho['subtotal'] > 0.004 || $descontoPedidoPct > 0;
        $campPct = $camp['desconto_pct'];
        if ($campPct > 0 && $deuDescontoVendedor) {
            foreach ($carrinho['campanhas'] as $c) { // a campanha da faixa não soma com o desconto da vendedora
                if (($c['titulo'] ?: $c['nome']) === $camp['desconto_de'] && !(int) $c['soma_desconto_vendedor']) {
                    $campPct = 0.0;
                }
            }
        }
        $descontoCampanha = round($carrinho['subtotal'] * $campPct / 100, 2);
        $descontoPedido = round(($carrinho['subtotal'] - $descontoCampanha) * $descontoPedidoPct / 100, 2);
        $descontoVendedor = round($subtotalTabela - $carrinho['subtotal'] + $descontoPedido, 2);
        $pctVendedor = $subtotalTabela > 0 ? $descontoVendedor / $subtotalTabela * 100 : 0;
        $limite = (float) \App\Core\Config::get('pedidos.desconto_max_pct', 10);
        $acimaLimite = $descontoVendedor > 0 && $pctVendedor > $limite + 0.0001;
        $base = $carrinho['subtotal'] - $descontoCampanha - $descontoPedido;
        $desconto = round($descontoCampanha + $descontoPedido + ($condicao ? $base * (float) $condicao['desconto_pct'] / 100 : 0.0), 2);
        $brindes = implode(' · ', array_column($camp['brindes'], 'texto'));

        $id = Banco::transacao(function () use ($cliente, $carrinho, $forma, $condicao, $desconto, $vendedor, $origem, $uuid, $d, $descontoPedidoPct, $descontoVendedor, $acimaLimite, $pctVendedor, $limite, $camp, $campPct, $descontoCampanha, $brindes) {
            $id = Banco::inserir('pedidos', [
                'uuid' => $uuid, 'cliente_id' => $cliente['id'], 'vendedor_id' => $vendedor, 'origem' => $origem,
                'tabela_preco_id' => $carrinho['tabela']['id'], 'forma_pagamento_id' => $forma['id'], 'condicao_id' => $condicao['id'] ?? null,
                'subtotal' => $carrinho['subtotal'], 'desconto' => $desconto, 'total' => round($carrinho['subtotal'] - $desconto, 2),
                'desconto_pedido_pct' => $descontoPedidoPct, 'desconto_vendedor' => $descontoVendedor, 'desconto_acima_limite' => $acimaLimite ? 1 : 0,
                'campanha_desconto_pct' => $campPct, 'campanha_desconto' => $descontoCampanha, 'campanha_brindes' => mb_substr($brindes, 0, 500) ?: null,
                'frete_gratis' => $camp['frete'] ? 1 : 0, 'campanhas' => mb_substr(implode(', ', $camp['nomes']), 0, 255) ?: null,
                'observacoes' => mb_substr(trim((string) ($d['observacoes'] ?? '')), 0, 1000) ?: null,
                'criado_por_usuario' => $d['usuario_id'] ?? null, 'criado_por_cliente' => in_array($origem, ['catalogo', 'orcamento'], true) ? 1 : 0,
            ]);
            foreach ($carrinho['itens'] as $i) {
                Banco::inserir('pedido_itens', ['pedido_id' => $id, 'produto_id' => $i['id'], 'codigo' => $i['codigo'] ?? null, 'nome' => $i['nome'],
                    'quantidade' => $i['quantidade'], 'preco_unitario' => $i['preco'], 'total' => $i['total'],
                    'preco_tabela' => $i['preco_tabela'] ?? null, 'desconto_pct' => $i['desconto_pct'] ?? 0,
                    'promo_pct' => $i['promo_pct'] ?? 0, 'campanha_id' => $i['campanha_id'] ?? null,
                    'terceirizacao_produto_id' => $i['terceirizacao_produto_id'] ?? null]);
            }
            $quem = ['catalogo' => 'pelo cliente no catálogo', 'sistema' => 'pela equipe', 'app' => 'pelo app do vendedor externo', 'orcamento' => 'pelo cliente, aprovando o orçamento'][$origem];
            self::historico($id, 'aguardando_aprovacao', "Pedido criado $quem.", $d['usuario_id'] ?? null);
            if ($descontoVendedor > 0) {
                self::historico($id, 'aguardando_aprovacao', 'Desconto da vendedora: ' . dinheiro($descontoVendedor) . ' (' . number_format($pctVendedor, 1, ',', '.') . '%)'
                    . ($acimaLimite ? ' · acima do limite de ' . number_format($limite, 1, ',', '.') . '%: precisa de aprovação.' : '.'), $d['usuario_id'] ?? null);
            }
            $ganhou = CampanhaService::conquistas(['desconto_pct' => $campPct] + $camp);
            if ($ganhou) {
                self::historico($id, 'aguardando_aprovacao', 'Campanha ' . implode(', ', $camp['nomes']) . ': ' . implode(' · ', $ganhou) . '.', $d['usuario_id'] ?? null);
            }
            ClienteEvento::registrar((int) $cliente['id'], 'pedido', 'Pedido #' . $id . ' · ' . dinheiro(round($carrinho['subtotal'] - $desconto, 2)) . ' · ' . $forma['nome'] . ' (aguardando aprovação)',
                $d['usuario_id'] ?? null, $origem === 'orcamento' ? 'Orçamento' : ucfirst($origem), ['pedido_id' => $id]);
            Auditoria::registrar('pedido', $id, 'criar', null, ['cliente_id' => $cliente['id'], 'total' => round($carrinho['subtotal'] - $desconto, 2), 'origem' => $origem]);
            return $id;
        });
        self::enviarAoErp($id);
        NotificacaoService::pedidoCriado((int) $id, $d['usuario_id'] ?? null); // sino do Financeiro (e do vendedor, se veio do catálogo)
        if ($acimaLimite) { // gestores avisados: desconto acima do limite
            NotificacaoService::avisar(NotificacaoService::destinatarios('pedidos.ver_todos', 'comercial', 'aprovacao'), 'pedido_desconto',
                "Pedido #$id com desconto acima do limite (" . number_format($pctVendedor, 1, ',', '.') . '%)', '/pedidos/' . $id,
                ['pedido_id' => (int) $id, 'cliente_id' => (int) $cliente['id'], 'texto' => 'Desconto da vendedora: ' . dinheiro($descontoVendedor)], $d['usuario_id'] ?? null);
        }
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
                'forma_pagamento' => $p['forma'], 'condicao' => $p['condicao'], 'observacoes' => self::observacoesErp($p)]);
        } catch (\Throwable $erro) {
            error_log('Envio do pedido ' . $id . ' ao ERP: ' . $erro->getMessage());
            return false;
        }
        Banco::atualizar('pedidos', $id, ['omie_codigo' => $r['omie_codigo'], 'omie_numero' => $r['numero']]);
        self::historico($id, $p['situacao'], 'Enviado ao ERP como orçamento nº ' . $r['numero'] . '.', null);
        return true;
    }

    /** Observação que vai ao ERP: a do pedido + o que a campanha deu (brinde, frete grátis, desconto) para o faturamento não esquecer */
    private static function observacoesErp(array $p): string
    {
        $extra = [];
        if (!empty($p['campanhas'])) {
            if ((float) $p['campanha_desconto'] > 0) {
                $extra[] = 'desconto da campanha ' . CampanhaService::pct((float) $p['campanha_desconto_pct']) . ' (' . dinheiro((float) $p['campanha_desconto']) . ')';
            }
            if ($p['campanha_brindes']) {
                $extra[] = 'BRINDE: ' . $p['campanha_brindes'];
            }
            if ((int) $p['frete_gratis']) {
                $extra[] = 'FRETE GRÁTIS';
            }
        }
        $campanha = $extra ? 'Campanha ' . $p['campanhas'] . ': ' . implode(' · ', $extra) : '';
        return trim((string) $p['observacoes'] . ($campanha ? "\n" . $campanha : ''));
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
        NotificacaoService::diretoria('problemas', 'Pedido #' . $pedido['id'] . ' recusado pelo Financeiro', '/pedidos/' . $pedido['id'],
            ['texto' => mb_substr((string) ($pedido['cliente'] ?? '') . ' · ' . $motivo, 0, 300), 'pedido_id' => (int) $pedido['id'], 'cliente_id' => (int) $pedido['cliente_id']], $usuarioId);
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
            DepositoService::entrarNaFila((int) $pedido['id'], $quando); // nota emitida → vai para a separação no depósito
            if (!empty($pedido['vendedor_id']) && MetaService::vendasDoOmie()) { // venda do Meu mês vem do faturamento: bateu a meta?
                $mes = substr($quando, 0, 7);
                $depois = MetaService::totalMes((int) $pedido['vendedor_id'], $mes);
                MetaService::avisarSeBateuMeta((int) $pedido['vendedor_id'], $mes, $depois - (float) $pedido['total'], $depois);
            }
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
