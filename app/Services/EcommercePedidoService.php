<?php
namespace App\Services;

use App\Core\Auditoria;
use App\Core\Banco;
use App\Core\Config;
use App\Integrations\Integracoes;

/**
 * Pedidos do e-commerce (Tray: Mercado Livre, Shopee, Amazon, loja própria) para o Depósito (V11.4).
 *  - Pedido PAGO na Tray → é importado para o One (fica fora da fila).
 *  - Quando a NOTA FISCAL é emitida → entra na fila de separação com o horário limite do canal.
 *  - Cancelado na Tray antes de começar a separar → sai da fila.
 * Os compradores NÃO viram clientes da carteira: cada canal tem um "cliente técnico" (tipo 'consumidor')
 * que fica fora da carteira, das metas, da recompra e do painel de vendas da equipe. Nome e cidade do comprador
 * ficam só no pedido, para o Depósito conferir a etiqueta (LGPD: nada de CPF, e-mail, telefone ou endereço completo).
 */
class EcommercePedidoService
{
    /** Canais: chave => [nome, cor do selo] (os mesmos do painel do E-commerce) */
    public static function canais(): array
    {
        return array_map(fn($c) => [$c[0], $c[1]], \App\Integrations\Tray\TrayTexto::CANAIS);
    }

    /** Tipos de pedido na separação (filtro do Depósito) */
    public const TIPOS = ['lojista' => 'Lojistas', 'ecommerce' => 'E-commerce', 'terceirizacao' => 'Terceirização'];

    public static function nomeCanal(?string $canal): string
    {
        return $canal ? \App\Integrations\Tray\TrayTexto::nomeCanal($canal) : 'E-commerce';
    }

    /**
     * Busca na Tray e atualiza o One. Devolve ['novos' => n, 'na_fila' => n, 'cancelados' => n].
     * Pode rodar quantas vezes quiser: o código da Tray garante que nada é importado duas vezes.
     */
    public static function importar(?int $usuarioId = null): array
    {
        $desde = Banco::valor("SELECT MAX(criado_em) FROM pedidos WHERE tipo_venda = 'ecommerce'");
        $desde = $desde ? date('Y-m-d H:i:s', strtotime($desde . ' -3 days')) : date('Y-m-d 00:00:00', strtotime('-3 days'));
        $r = ['novos' => 0, 'na_fila' => 0, 'cancelados' => 0];
        $loja = Integracoes::ecommerce();
        if ($loja->nome() === 'fake') {
            $lista = $loja->pedidosParaSeparar($desde);
        } else {
            // Loja real: a mesma leitura do painel do E-commerce (cópia local dos pedidos)
            $sinc = EcommerceService::sincronizar();
            $lidos = array_column($sinc['detalhes'], null, 'tray_id');
            $dias = max(1, (int) Config::get('ecommerce.dias_deposito', 60));
            // Interessam: pagos que ainda não estão no Depósito e os que já estão mas ganharam a nota
            $lista = [];
            foreach (Banco::todos("SELECT e.tray_id FROM ecommerce_pedidos e LEFT JOIN pedidos p ON p.tray_codigo = e.tray_id
                WHERE e.origem = 'tray' AND e.detalhado = 1 AND (
                  (p.id IS NULL AND e.status_tipo = 'pago' AND e.data_hora > NOW() - INTERVAL $dias DAY)
                  OR (p.id IS NOT NULL AND p.situacao NOT IN ('faturado', 'cancelado') AND e.tem_nf = 1 AND e.status_tipo <> 'cancelado'))
                ORDER BY e.data_hora LIMIT 200") as $c) {
                // O nome curto do comprador (para a etiqueta) não fica na cópia local: lê o pedido de novo se não veio agora
                $completo = $lidos[$c['tray_id']] ?? $loja->pedidoCompleto((string) $c['tray_id']);
                if ($completo) {
                    $lista[] = self::doPedidoDaLoja($completo);
                }
            }
        }
        foreach ($lista as $t) {
            $codigo = mb_substr(trim((string) ($t['codigo'] ?? '')), 0, 40);
            if ($codigo === '' || empty($t['itens'])) {
                continue;
            }
            $p = Banco::um('SELECT id, situacao, expedicao FROM pedidos WHERE tray_codigo = ?', [$codigo]);
            if (!empty($t['cancelado'])) {
                if ($p && self::cancelar($p)) {
                    $r['cancelados']++;
                }
                continue;
            }
            if (!$p) {
                $p = ['id' => self::criar($t, $codigo), 'situacao' => 'aprovado', 'expedicao' => null];
                $r['novos']++;
            }
            // Nota emitida: entra na fila (uma vez)
            if (!empty($t['nf']) && $p['situacao'] !== 'faturado' && $p['situacao'] !== 'cancelado') {
                $quando = !empty($t['nf_em']) && strtotime($t['nf_em']) ? date('Y-m-d H:i:s', strtotime($t['nf_em'])) : date('Y-m-d H:i:s');
                $limite = self::limite((string) ($t['canal'] ?? 'loja'), $quando);
                Banco::executar("UPDATE pedidos SET situacao = 'faturado', numero_nf = ?, faturado_em = ?, expedicao_limite = ? WHERE id = ?",
                    [mb_substr((string) $t['nf'], 0, 30), $quando, $limite, $p['id']]);
                PedidoService::historico((int) $p['id'], 'faturado', 'Nota emitida · NF ' . $t['nf'] . ($limite ? ' · sai até ' . date('d/m H:i', strtotime($limite)) : ''), null);
                ProducaoService::baixarVenda((int) $p['id'], $quando); // estoque do produto acabado (se configurado)
                DepositoService::entrarNaFila((int) $p['id'], $quando);
                $r['na_fila']++;
            }
        }
        // Loja real: confere TODOS os pedidos que ainda estão no Depósito (um cancelamento antigo também precisa sair da fila)
        if ($loja->nome() !== 'fake') {
            foreach (Banco::todos("SELECT p.id, p.situacao, p.expedicao FROM pedidos p JOIN ecommerce_pedidos e ON e.tray_id = p.tray_codigo AND e.origem = 'tray'
                WHERE p.tipo_venda = 'ecommerce' AND p.situacao <> 'cancelado' AND e.status_tipo = 'cancelado'") as $p) {
                if (self::cancelar($p)) {
                    $r['cancelados']++;
                }
            }
        }
        if ($r['novos'] || $r['na_fila'] || $r['cancelados']) {
            Auditoria::registrar('integracao', null, 'tray_pedidos', null, $r + ['usuario' => $usuarioId]);
        }
        return $r;
    }

    /**
     * Cancelado na Tray: sai da fila em qualquer etapa antes de despachar (com aviso ao Depósito).
     * Já despachado: só avisa (uma vez). Devolve true quando tirou da fila.
     */
    private static function cancelar(array $p): bool
    {
        if ($p['situacao'] === 'cancelado') {
            return false;
        }
        if ($p['expedicao'] === 'despachado') {
            if (!Banco::valor("SELECT 1 FROM pedido_historico WHERE pedido_id = ? AND texto LIKE 'Cancelado na Tray depois de despachado%'", [$p['id']])) {
                PedidoService::historico((int) $p['id'], (string) $p['situacao'], 'Cancelado na Tray depois de despachado: combinar a devolução com o comprador.', null);
                self::avisarDeposito((int) $p['id'], 'Pedido do e-commerce cancelado depois de despachado');
            }
            return false;
        }
        $etapa = (string) $p['expedicao'];
        Banco::executar("UPDATE pedidos SET situacao = 'cancelado', expedicao = NULL WHERE id = ?", [$p['id']]);
        PedidoService::historico((int) $p['id'], 'cancelado', 'Cancelado na Tray: saiu da fila do Depósito'
            . (in_array($etapa, ['separando', 'separado'], true) ? ' (já estava sendo separado: devolver os produtos à prateleira).' : '.'), null);
        if (in_array($etapa, ['aguardando', 'separando', 'separado'], true)) {
            self::avisarDeposito((int) $p['id'], 'Pedido do e-commerce cancelado na Tray: não separar');
        }
        return true;
    }

    private static function avisarDeposito(int $pedidoId, string $titulo): void
    {
        NotificacaoService::avisar(NotificacaoService::destinatarios('deposito.operar', 'deposito'), 'pedido_cancelado', $titulo,
            '/deposito/' . $pedidoId, ['pedido_id' => $pedidoId]);
    }

    /** Pedido completo da loja (TrayTexto::pedido) → formato da importação do Depósito */
    private static function doPedidoDaLoja(array $p): array
    {
        return [
            'codigo' => $p['tray_id'], 'numero_marketplace' => $p['marketplace_pedido'] ?? '', 'canal' => $p['canal'],
            'criado_em' => $p['data_hora'], 'nf' => $p['nf_numero'] ?: (!empty($p['tem_nf']) ? 'emitida' : null), 'nf_em' => $p['nf_em'] ?? null,
            'cancelado' => $p['status_tipo'] === 'cancelado', 'comprador' => $p['comprador'] ?? '', 'cidade' => $p['cidade'] ?? '', 'uf' => $p['uf'] ?? '',
            'total' => $p['total'],
            'itens' => array_map(fn($i) => ['ref' => $i['produto_ref'] ?: (string) $i['ean'], 'nome' => $i['nome'] . ($i['variacao'] ? ' — ' . $i['variacao'] : ''),
                'quantidade' => $i['quantidade'], 'preco' => $i['preco']], $p['itens'] ?? []),
        ];
    }

    /**
     * Horário limite de envio do canal (Configurações › Depósito, ex.: "13:00").
     * Nota emitida depois do horário (ou em fim de semana) → vale o horário do próximo dia útil. Sem horário → null.
     */
    public static function limite(string $canal, string $quando): ?string
    {
        $hora = trim((string) Config::get('deposito.horario_' . $canal, ''));
        if (!preg_match('/^([01]?\d|2[0-3]):([0-5]\d)$/', $hora, $m)) {
            return null;
        }
        $dia = strtotime(date('Y-m-d', strtotime($quando)));
        $limite = $dia + (int) $m[1] * 3600 + (int) $m[2] * 60;
        if (strtotime($quando) > $limite || (int) date('N', $limite) >= 6) {
            do {
                $limite = strtotime('+1 day', $limite);
            } while ((int) date('N', $limite) >= 6);
        }
        return date('Y-m-d H:i:s', $limite);
    }

    /** Situação do horário limite para a tela: [texto, classe] (classe: ok | perto | atrasado) */
    public static function prazo(?string $limite): ?array
    {
        if (!$limite) {
            return null;
        }
        $falta = strtotime($limite) - time();
        $hora = date('H:i', strtotime($limite)) . (date('Y-m-d', strtotime($limite)) !== date('Y-m-d') ? ' de ' . date('d/m', strtotime($limite)) : '');
        if ($falta < 0) {
            return ['passou do horário (' . $hora . ')', 'atrasado'];
        }
        $h = intdiv($falta, 3600);
        $min = intdiv($falta % 3600, 60);
        return ['sai até ' . $hora . ' · faltam ' . ($h ? $h . 'h' . str_pad((string) $min, 2, '0', STR_PAD_LEFT) : $min . ' min'), $falta < 3600 ? 'perto' : 'ok'];
    }

    /** Cria o pedido no One (sem fila ainda): cliente técnico do canal, itens ligados aos produtos pelo código */
    private static function criar(array $t, string $codigo): int
    {
        $canal = array_key_exists($t['canal'] ?? '', self::canais()) ? $t['canal'] : 'loja';
        $cliente = self::clienteDoCanal($canal);
        $tabela = (int) (Banco::valor('SELECT id FROM tabelas_preco ORDER BY id LIMIT 1') ?: 1);
        $forma = (int) (Banco::valor('SELECT id FROM formas_pagamento ORDER BY id LIMIT 1') ?: 1);
        $total = round((float) ($t['total'] ?? 0), 2);
        return Banco::transacao(function () use ($t, $codigo, $canal, $cliente, $tabela, $forma, $total) {
            $id = Banco::inserir('pedidos', [
                'uuid' => self::uuid(), 'cliente_id' => $cliente, 'origem' => 'ecommerce', 'tabela_preco_id' => $tabela, 'forma_pagamento_id' => $forma,
                'subtotal' => $total, 'total' => $total, 'situacao' => 'aprovado', 'tipo_venda' => 'ecommerce', 'canal' => $canal, 'tray_codigo' => $codigo,
                'marketplace_numero' => mb_substr((string) ($t['numero_marketplace'] ?? ''), 0, 60) ?: null,
                'comprador_nome' => mb_substr((string) ($t['comprador'] ?? ''), 0, 120) ?: null,
                'comprador_cidade' => mb_substr((string) ($t['cidade'] ?? ''), 0, 80) ?: null,
                'comprador_uf' => mb_strtoupper(mb_substr((string) ($t['uf'] ?? ''), 0, 2)) ?: null,
                'criado_em' => !empty($t['criado_em']) && strtotime($t['criado_em']) ? date('Y-m-d H:i:s', strtotime($t['criado_em'])) : date('Y-m-d H:i:s'),
            ]);
            foreach ($t['itens'] as $it) {
                $ref = trim((string) ($it['ref'] ?? ''));
                $produto = $ref !== '' ? Banco::um('SELECT id, nome, codigo FROM produtos WHERE codigo = ? LIMIT 1', [$ref]) : null;
                $qtd = max(1, (int) ($it['quantidade'] ?? 1));
                $preco = round((float) ($it['preco'] ?? 0), 2);
                Banco::inserir('pedido_itens', array_filter([
                    'pedido_id' => $id, 'produto_id' => $produto['id'] ?? null, 'codigo' => $produto['codigo'] ?? ($ref ?: null),
                    'nome' => mb_substr((string) ($produto['nome'] ?? \App\Integrations\Tray\TrayTexto::nome((string) ($it['nome'] ?? 'Produto'))), 0, 200), 'quantidade' => $qtd,
                    'preco_unitario' => $preco, 'total' => round($qtd * $preco, 2),
                ], fn($v) => $v !== null));
            }
            PedidoService::historico($id, 'aprovado', 'Importado da Tray (' . self::nomeCanal($canal) . ($t['numero_marketplace'] ?? '' ? ' · pedido ' . $t['numero_marketplace'] : '') . '). Entra na separação quando a nota for emitida.', null);
            return $id;
        });
    }

    /** Cliente técnico de cada canal (fora da carteira): "Mercado Livre (consumidores)" */
    public static function clienteDoCanal(string $canal): int
    {
        $nome = self::nomeCanal($canal) . ' (consumidores)';
        $id = Banco::valor("SELECT id FROM clientes WHERE tipo = 'consumidor' AND razao_social = ? LIMIT 1", [$nome]);
        return (int) ($id ?: Banco::inserir('clientes', ['razao_social' => $nome, 'nome_fantasia' => $nome, 'tipo' => 'consumidor',
            'observacoes' => 'Cliente técnico dos pedidos do e-commerce (Tray). Não faz parte da carteira.']));
    }

    private static function uuid(): string
    {
        $b = random_bytes(16);
        $b[6] = chr(ord($b[6]) & 0x0f | 0x40);
        $b[8] = chr(ord($b[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
    }
}
