<?php
namespace App\Services;

use App\Core\Auditoria;
use App\Core\Auth;
use App\Core\Banco;
use App\Core\Config;
use App\Models\ClienteEvento;

/**
 * Orçamento por link (para o cliente aprovar pelo WhatsApp).
 *
 *  1. A equipe monta o orçamento na mesma tela do "Novo pedido" (preço = tabela do cliente).
 *  2. O One gera um LINK SECRETO (/o/{token}): abre sem senha, vale X dias (configurável) e pode ser cancelado.
 *     O link não tem nenhum dado do cliente — só o token (LGPD).
 *  3. O cliente abre (vira "visto" e o vendedor é avisado), muda quantidades, acrescenta produtos e:
 *     - aprova → vira PEDIDO "aguardando aprovação" com as mesmas regras do catálogo (mínimo, boleto bloqueado...);
 *     - ou recusa (com motivo).
 *  4. Tudo vai para a linha do tempo do cliente e para o histórico do orçamento.
 */
class OrcamentoService
{
    public const SITUACOES = [
        'aberto'    => ['Enviado', 'azul'],
        'visto'     => ['Cliente abriu', 'laranja'],
        'aprovado'  => ['Aprovado (virou pedido)', 'verde'],
        'recusado'  => ['Recusado', 'vermelho'],
        'expirado'  => ['Venceu', 'cinza'],
        'cancelado' => ['Cancelado', 'cinza'],
    ];

    private const CAMPOS = "o.*, COALESCE(NULLIF(c.nome_fantasia, ''), c.razao_social) AS cliente, c.razao_social, c.cidade, c.uf, c.whatsapp, c.telefone,
        c.responsavel_id, c.funcao_id, u.nome AS vendedor, u.foto AS vendedor_foto, t.nome AS tabela";
    private const JUNCOES = 'FROM orcamentos o JOIN clientes c ON c.id = o.cliente_id LEFT JOIN usuarios u ON u.id = o.vendedor_id
        LEFT JOIN tabelas_preco t ON t.id = o.tabela_preco_id';

    /** Situação de verdade: aberto/visto com a validade vencida = "venceu" */
    public static function situacao(array $o): string
    {
        return in_array($o['situacao'], ['aberto', 'visto'], true) && $o['validade'] < date('Y-m-d') ? 'expirado' : $o['situacao'];
    }

    /** O cliente ainda pode responder (aprovar/recusar)? */
    public static function emAberto(array $o): bool
    {
        return in_array(self::situacao($o), ['aberto', 'visto'], true);
    }

    // ------------------------------------------------------------------
    // Consultas
    // ------------------------------------------------------------------

    public static function buscar(int $id): ?array
    {
        return Banco::um('SELECT ' . self::CAMPOS . ' ' . self::JUNCOES . ' WHERE o.id = ?', [$id]);
    }

    public static function porToken(string $token): ?array
    {
        if (!preg_match('/^[0-9a-f]{64}$/', $token)) {
            return null;
        }
        return Banco::um('SELECT ' . self::CAMPOS . ' ' . self::JUNCOES . ' WHERE o.token = ?', [$token]);
    }

    public static function itens(int $id): array
    {
        return Banco::todos('SELECT i.*, p.slug, p.embalagem, m.arquivo AS imagem FROM orcamento_itens i LEFT JOIN produtos p ON p.id = i.produto_id
            LEFT JOIN midias m ON m.id = p.imagem_id WHERE i.orcamento_id = ? ORDER BY i.nome', [$id]);
    }

    /** Só os itens vendidos (sem os bonificados) */
    public static function itensVendidos(int $id): array
    {
        return array_values(array_filter(self::itens($id), fn($i) => !(int) $i['bonificado']));
    }

    /** Só os itens bonificados (o cliente vê no link, mas não muda) */
    public static function itensBonificados(int $id): array
    {
        return array_values(array_filter(self::itens($id), fn($i) => (int) $i['bonificado']));
    }

    public static function historico(int $id): array
    {
        return Banco::todos('SELECT h.*, u.nome AS usuario FROM orcamento_historico h LEFT JOIN usuarios u ON u.id = h.usuario_id
            WHERE h.orcamento_id = ? ORDER BY h.criado_em DESC, h.id DESC', [$id]);
    }

    /** Escopo da equipe: quem vê todos os pedidos vê todos; os outros, os seus, os dos seus clientes e os do setor que gerenciam */
    private static function escopo(): array
    {
        if (Auth::pode('pedidos.ver_todos')) {
            return ['1 = 1', []];
        }
        $setor = Setores::sqlGerenciados();
        return ['(o.vendedor_id = ? OR o.criado_por = ? OR c.responsavel_id = ?' . ($setor ? " OR $setor" : '') . ')', [Auth::id(), Auth::id(), Auth::id()]];
    }

    public static function podeVer(array $o): bool
    {
        return Auth::pode('pedidos.ver_todos') || (int) $o['vendedor_id'] === Auth::id() || (int) $o['criado_por'] === Auth::id()
            || (int) $o['responsavel_id'] === Auth::id() || in_array((int) $o['funcao_id'], Setores::gerenciados(), true);
    }

    /**
     * Lista da equipe. $aba: abertos | aprovados | encerrados. $funcao: setor do menu (filtra pela carteira).
     */
    public static function listar(string $aba, ?int $funcao, string $busca = ''): array
    {
        [$w, $par] = self::escopo();
        $w = [$w];
        $hoje = date('Y-m-d');
        if ($aba === 'aprovados') {
            $w[] = "o.situacao = 'aprovado'";
        } elseif ($aba === 'encerrados') {
            $w[] = "(o.situacao IN ('recusado','cancelado') OR (o.situacao IN ('aberto','visto') AND o.validade < ?))";
            $par[] = $hoje;
        } else {
            $w[] = "o.situacao IN ('aberto','visto') AND o.validade >= ?";
            $par[] = $hoje;
        }
        if ($funcao) {
            $w[] = 'c.funcao_id = ?';
            $par[] = $funcao;
        }
        if ($busca !== '') {
            $w[] = '(c.razao_social LIKE ? OR c.nome_fantasia LIKE ? OR o.id = ?)';
            array_push($par, "%$busca%", "%$busca%", (int) preg_replace('/\D/', '', $busca));
        }
        return Banco::todos('SELECT ' . self::CAMPOS . ', (SELECT COUNT(*) FROM orcamento_itens i WHERE i.orcamento_id = o.id) AS qtd_itens ' . self::JUNCOES
            . ' WHERE ' . implode(' AND ', $w) . ' ORDER BY COALESCE(o.respondido_em, o.visto_em, o.criado_em) DESC, o.id DESC LIMIT 200', $par);
    }

    /** Contadores das abas */
    public static function contar(?int $funcao): array
    {
        [$w, $par] = self::escopo();
        if ($funcao) {
            $w .= ' AND c.funcao_id = ?';
            $par[] = $funcao;
        }
        $hoje = date('Y-m-d');
        $r = Banco::um("SELECT SUM(o.situacao IN ('aberto','visto') AND o.validade >= ?) AS abertos, SUM(o.situacao = 'aprovado') AS aprovados,
            SUM(o.situacao IN ('recusado','cancelado') OR (o.situacao IN ('aberto','visto') AND o.validade < ?)) AS encerrados " . self::JUNCOES . " WHERE $w",
            array_merge([$hoje, $hoje], $par));
        return array_map('intval', $r ?: []);
    }

    // ------------------------------------------------------------------
    // Link e mensagem
    // ------------------------------------------------------------------

    public static function link(array $o): string
    {
        $host = isset($_SERVER['HTTP_HOST']) ? ((($_SERVER['HTTPS'] ?? '') === 'on' ? 'https://' : 'http://') . $_SERVER['HTTP_HOST']) : '';
        return $host . url('/o/' . $o['token']);
    }

    public static function mensagem(array $o): string
    {
        $texto = (string) Config::get('orcamento.mensagem', 'Olá, {nome}! Segue o seu orçamento: {link}');
        return strtr($texto, [
            '{nome}' => (string) $o['cliente'], '{link}' => self::link($o), '{validade}' => data_br($o['validade']),
            '{total}' => dinheiro((float) $o['subtotal']), '{vendedor}' => $o['vendedor'] ? explode(' ', trim((string) $o['vendedor']))[0] : '',
        ]);
    }

    // ------------------------------------------------------------------
    // Criar, atualizar, cancelar (equipe)
    // ------------------------------------------------------------------

    /**
     * Cria (ou, com $orcamento, atualiza) o orçamento a partir das quantidades da tela do pedido.
     * Não exige pedido mínimo nem pagamento: isso é conferido quando o cliente aprova.
     * Devolve [id, erros].
     */
    public static function salvar(array $cliente, array $quantidades, array $d, int $usuarioId, ?array $orcamento = null): array
    {
        if ($orcamento && !self::emAberto($orcamento)) {
            return [null, ['Este orçamento já foi respondido ou encerrado: faça um novo.']];
        }
        $carrinho = PedidoService::calcular($cliente, $quantidades);
        if (!$carrinho['itens']) {
            return [null, ['Escolha pelo menos um produto para o orçamento.']];
        }
        // Bonificação oferecida (V16.6): calculada com o preço de tabela do cliente; motivo obrigatório
        $bonificados = [];
        if (array_filter(array_map('intval', (array) ($d['bonificados'] ?? [])))) {
            foreach (PedidoService::calcular($cliente, (array) $d['bonificados'])['itens'] as $b) {
                $preco = (float) ($b['preco_cheio'] ?? $b['preco']);
                $bonificados[] = $b + ['preco_bon' => $preco, 'valor_bon' => round($b['quantidade'] * $preco, 2)];
            }
            if ($bonificados && !in_array(trim((string) ($d['bonificacao_motivo'] ?? '')), PedidoService::motivosBonificacao(), true)) {
                return [null, ['Escolha o motivo da bonificação.']];
            }
        }
        $dias = max(1, (int) Config::get('orcamento.validade_dias', 15));
        $dados = [
            'tabela_preco_id' => $carrinho['tabela']['id'], 'subtotal' => $carrinho['subtotal'],
            'forma_pagamento_id' => (int) ($d['forma_pagamento_id'] ?? 0) ?: null, 'condicao_id' => (int) ($d['condicao_id'] ?? 0) ?: null,
            'observacoes' => mb_substr(trim((string) ($d['observacoes'] ?? '')), 0, 1000) ?: null,
            'validade' => date('Y-m-d', strtotime("+$dias days")),
            'bonificado_valor' => round(array_sum(array_column($bonificados, 'valor_bon')), 2),
            'bonificacao_motivo' => $bonificados ? mb_substr(trim((string) $d['bonificacao_motivo']), 0, 60) : null,
        ];
        $id = Banco::transacao(function () use ($cliente, $carrinho, $dados, $usuarioId, $orcamento, $bonificados) {
            if ($orcamento) {
                $id = (int) $orcamento['id'];
                Banco::atualizar('orcamentos', $id, $dados + ['atualizado_em' => date('Y-m-d H:i:s')]);
                Banco::executar('DELETE FROM orcamento_itens WHERE orcamento_id = ?', [$id]); // itens do próprio orçamento em aberto (refeitos abaixo)
                $texto = 'Orçamento alterado: ' . count($carrinho['itens']) . ' produto(s) · ' . dinheiro($carrinho['subtotal']) . '. O link continua o mesmo.';
            } else {
                $id = Banco::inserir('orcamentos', $dados + [
                    'uuid' => PedidoService::novoUuid(), 'token' => bin2hex(random_bytes(32)), 'cliente_id' => $cliente['id'],
                    'vendedor_id' => $usuarioId, 'situacao' => 'aberto', 'criado_por' => $usuarioId,
                ]);
                $texto = 'Orçamento criado: ' . count($carrinho['itens']) . ' produto(s) · ' . dinheiro($carrinho['subtotal']) . '.';
            }
            foreach ($carrinho['itens'] as $i) {
                Banco::inserir('orcamento_itens', ['orcamento_id' => $id, 'chave' => (string) ($i['chave'] ?? $i['id']), 'produto_id' => $i['id'],
                    'terceirizacao_produto_id' => $i['terceirizacao_produto_id'] ?? null, 'codigo' => $i['codigo'] ?? null, 'nome' => $i['nome'],
                    'quantidade' => $i['quantidade'], 'preco_unitario' => $i['preco'], 'total' => $i['total']]);
            }
            foreach ($bonificados as $b) { // vai na nota com o preço de tabela, a loja não paga
                Banco::inserir('orcamento_itens', ['orcamento_id' => $id, 'chave' => (string) ($b['chave'] ?? $b['id']), 'produto_id' => $b['id'],
                    'terceirizacao_produto_id' => $b['terceirizacao_produto_id'] ?? null, 'codigo' => $b['codigo'] ?? null, 'nome' => $b['nome'],
                    'quantidade' => $b['quantidade'], 'preco_unitario' => $b['preco_bon'], 'total' => 0, 'bonificado' => 1, 'valor_bonificado' => $b['valor_bon']]);
            }
            if ($bonificados) {
                $texto .= ' Bonificação (' . $dados['bonificacao_motivo'] . '): ' . dinheiro($dados['bonificado_valor']) . '.';
            }
            self::historico_($id, $texto, $usuarioId);
            ClienteEvento::registrar((int) $cliente['id'], 'orcamento', "Orçamento #$id · " . dinheiro($carrinho['subtotal']) . ($orcamento ? ' (alterado)' : ' (criado, para enviar ao cliente)'),
                $usuarioId, 'Orçamento', ['orcamento_id' => $id]);
            Auditoria::registrar('orcamento', $id, $orcamento ? 'editar' : 'criar', null, ['cliente_id' => $cliente['id'], 'subtotal' => $carrinho['subtotal']]);
            return $id;
        });
        return [$id, []];
    }

    public static function cancelar(array $o, int $usuarioId): ?string
    {
        if (!self::emAberto($o)) {
            return 'Só dá para cancelar orçamento que o cliente ainda não respondeu.';
        }
        Banco::atualizar('orcamentos', (int) $o['id'], ['situacao' => 'cancelado', 'atualizado_em' => date('Y-m-d H:i:s')]);
        self::historico_((int) $o['id'], 'Orçamento cancelado: o link parou de funcionar.', $usuarioId);
        ClienteEvento::registrar((int) $o['cliente_id'], 'orcamento', 'Orçamento #' . $o['id'] . ' cancelado', $usuarioId, 'Orçamento', ['orcamento_id' => (int) $o['id']]);
        Auditoria::registrar('orcamento', (int) $o['id'], 'cancelar', ['situacao' => $o['situacao']], ['situacao' => 'cancelado']);
        return null;
    }

    /** A equipe registrou que mandou o link (botão do WhatsApp / copiar): fica no histórico e na linha do tempo */
    public static function registrarEnvio(array $o, int $usuarioId, string $como): void
    {
        $como = $como === 'imagem' ? 'baixou a imagem do orçamento' : ($como === 'copiar' ? 'copiou o link do orçamento' : 'enviou o link pelo WhatsApp');
        self::historico_((int) $o['id'], ucfirst($como) . '.', $usuarioId);
        ClienteEvento::registrar((int) $o['cliente_id'], 'orcamento', 'Orçamento #' . $o['id'] . ': ' . $como, $usuarioId, 'Orçamento', ['orcamento_id' => (int) $o['id']]);
    }

    /** O vendedor transformou o orçamento em pedido ele mesmo (cliente confirmou por telefone, por exemplo) */
    public static function vincularPedido(int $orcamentoId, int $pedidoId, int $usuarioId): void
    {
        $o = self::buscar($orcamentoId);
        if (!$o || !self::emAberto($o)) {
            return;
        }
        Banco::atualizar('orcamentos', $orcamentoId, ['situacao' => 'aprovado', 'pedido_id' => $pedidoId, 'respondido_em' => date('Y-m-d H:i:s')]);
        self::historico_($orcamentoId, "Virou o pedido #$pedidoId (feito pela equipe).", $usuarioId);
    }

    // ------------------------------------------------------------------
    // O cliente (link público)
    // ------------------------------------------------------------------

    /** Primeira vez que o cliente abre o link: "visto" + aviso ao vendedor */
    public static function marcarVisto(array $o): void
    {
        if ($o['situacao'] !== 'aberto' || !self::emAberto($o)) {
            return;
        }
        $agora = date('Y-m-d H:i:s');
        if (Banco::executar("UPDATE orcamentos SET situacao = 'visto', visto_em = ? WHERE id = ? AND situacao = 'aberto'", [$agora, $o['id']]) < 1) {
            return; // outra aba já marcou
        }
        self::historico_((int) $o['id'], 'O cliente abriu o orçamento.', null, true);
        ClienteEvento::registrar((int) $o['cliente_id'], 'orcamento', 'Cliente abriu o orçamento #' . $o['id'], null, 'Orçamento', ['orcamento_id' => (int) $o['id']]);
        self::avisarVendedor($o, 'orcamento_visto', $o['cliente'] . ' abriu o orçamento #' . $o['id'], dinheiro((float) $o['subtotal']));
    }

    /**
     * O cliente aprovou (com as quantidades que ele deixou). Vira pedido com as regras de sempre.
     * $quantidades: [chave => qtd] (chave = id do produto ou "t{id}"). Devolve [pedido_id, erros].
     */
    public static function aprovar(array $o, array $quantidades, int $formaId, int $condicaoId, string $mensagem): array
    {
        if (!self::emAberto($o)) {
            return [$o['pedido_id'] ? (int) $o['pedido_id'] : null, $o['pedido_id'] ? [] : ['Este orçamento não está mais aberto.']];
        }
        $cliente = Banco::um('SELECT * FROM clientes WHERE id = ?', [$o['cliente_id']]);
        $mensagem = mb_substr(trim($mensagem), 0, 1000);
        $obs = trim(implode("\n", array_filter([$o['observacoes'], $mensagem !== '' ? 'Mensagem do cliente: ' . $mensagem : ''])));
        // A bonificação vem do que o vendedor ofereceu (o cliente não muda); o limite é conferido com as quantidades finais
        $bonificados = array_column(self::itensBonificados((int) $o['id']), 'quantidade', 'chave');
        [$pedidoId, $erros] = PedidoService::criar($cliente, [
            'itens' => $quantidades, 'forma_pagamento_id' => $formaId, 'condicao_id' => $condicaoId, 'observacoes' => $obs,
            'origem' => 'orcamento', 'uuid' => $o['uuid'], 'vendedor_id' => $o['vendedor_id'], 'usuario_id' => null,
            'bonificados' => $bonificados, 'bonificacao_motivo' => (string) ($o['bonificacao_motivo'] ?? ''),
        ]);
        if ($erros) {
            return [null, $erros];
        }
        // O que o cliente mudou em relação ao que foi oferecido
        $oferecido = [];
        foreach (self::itensVendidos((int) $o['id']) as $i) {
            $oferecido[$i['chave']] = $i;
        }
        $confirmado = array_filter(array_map('intval', $quantidades), fn($q) => $q > 0);
        $mudou = false;
        foreach ($oferecido as $chave => $i) {
            $q = $confirmado[$chave] ?? 0;
            if ($q !== (int) $i['quantidade']) {
                $mudou = true;
                Banco::executar('UPDATE orcamento_itens SET quantidade_cliente = ? WHERE id = ?', [$q, $i['id']]);
            }
        }
        $novos = array_diff_key($confirmado, $oferecido);
        $mudou = $mudou || $novos;
        Banco::atualizar('orcamentos', (int) $o['id'], ['situacao' => 'aprovado', 'pedido_id' => $pedidoId, 'respondido_em' => date('Y-m-d H:i:s'),
            'cliente_alterou' => $mudou ? 1 : 0, 'resposta_cliente' => $mensagem ?: null]);
        $total = (float) Banco::valor('SELECT total FROM pedidos WHERE id = ?', [$pedidoId]);
        $texto = "O cliente aprovou" . ($mudou ? ' com alterações' . ($novos ? ' (acrescentou ' . count($novos) . ' produto(s))' : '') : '') . ": virou o pedido #$pedidoId · " . dinheiro($total) . '.';
        self::historico_((int) $o['id'], $texto . ($mensagem !== '' ? ' Mensagem: ' . $mensagem : ''), null, true);
        ClienteEvento::registrar((int) $o['cliente_id'], 'orcamento', 'Orçamento #' . $o['id'] . ' aprovado pelo cliente' . ($mudou ? ' (com alterações)' : '') . " → pedido #$pedidoId",
            null, 'Orçamento', ['orcamento_id' => (int) $o['id'], 'pedido_id' => $pedidoId]);
        self::avisarVendedor($o, 'orcamento_aprovado', $o['cliente'] . ' aprovou o orçamento #' . $o['id'], "Pedido #$pedidoId · " . dinheiro($total) . ($mudou ? ' · com alterações' : ''), "/pedidos/$pedidoId");
        return [$pedidoId, []];
    }

    public static function recusar(array $o, string $motivo): ?string
    {
        if (!self::emAberto($o)) {
            return 'Este orçamento não está mais aberto.';
        }
        $motivo = mb_substr(trim($motivo), 0, 1000);
        if ($motivo === '') {
            return 'Conte pra gente o motivo: assim o vendedor pode ajustar o orçamento.';
        }
        Banco::atualizar('orcamentos', (int) $o['id'], ['situacao' => 'recusado', 'respondido_em' => date('Y-m-d H:i:s'), 'resposta_cliente' => $motivo]);
        self::historico_((int) $o['id'], 'O cliente recusou. Motivo: ' . $motivo, null, true);
        ClienteEvento::registrar((int) $o['cliente_id'], 'orcamento', 'Orçamento #' . $o['id'] . ' recusado pelo cliente. Motivo: ' . $motivo, null, 'Orçamento', ['orcamento_id' => (int) $o['id']]);
        self::avisarVendedor($o, 'orcamento_recusado', $o['cliente'] . ' recusou o orçamento #' . $o['id'], $motivo);
        return null;
    }

    // ------------------------------------------------------------------

    private static function avisarVendedor(array $o, string $tipo, string $titulo, string $texto, ?string $link = null): void
    {
        $quem = array_filter([(int) ($o['vendedor_id'] ?: $o['criado_por'])]); // só o vendedor do orçamento
        NotificacaoService::avisar($quem, $tipo, $titulo, $link ?? '/orcamentos/' . (int) $o['id'], ['cliente_id' => (int) $o['cliente_id'], 'texto' => $texto]);
    }

    private static function historico_(int $id, string $texto, ?int $usuarioId, bool $cliente = false): void
    {
        Banco::inserir('orcamento_historico', ['orcamento_id' => $id, 'usuario_id' => $usuarioId, 'pelo_cliente' => $cliente ? 1 : 0, 'texto' => mb_substr($texto, 0, 1000)]);
    }
}
