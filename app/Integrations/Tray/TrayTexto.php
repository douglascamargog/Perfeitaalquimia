<?php
namespace App\Integrations\Tray;

/**
 * Tradução dos dados da Tray para o formato do One (V14.0).
 *  - limpar(): nomes de produto vêm com HTML e "(Disponibilidade: Imediata)" → nome limpo + variação.
 *  - canal(): de onde veio a venda (loja própria, Mercado Livre, Shopee, Amazon...).
 *  - statusTipo(): o status da loja (que pode ser personalizado) vira aguardando | pago | enviado | entregue | cancelado.
 *  - pedido(): um pedido da Tray (/orders ou /orders/{id}/complete) no formato da tabela ecommerce_pedidos.
 * LGPD: nada de nome, CPF, e-mail, telefone ou endereço do comprador sai daqui (só cidade, UF e um código anônimo).
 */
class TrayTexto
{
    /** Canais conhecidos: chave => [nome, cor do selo, palavras que identificam] */
    public const CANAIS = [
        'loja'          => ['Loja própria', 'verde', ['LOJA VIRTUAL', 'LOJA']],
        'mercado_livre' => ['Mercado Livre', 'amarelo', ['MERCADO LIVRE', 'MERCADOLIVRE', 'MERCADO']],
        'shopee'        => ['Shopee', 'laranja', ['SHOPEE']],
        'amazon'        => ['Amazon', 'azul', ['AMAZON']],
        'magalu'        => ['Magalu', 'azul', ['MAGALU', 'MAGAZINE']],
        'americanas'    => ['Americanas', 'vermelho', ['AMERICANAS', 'B2W', 'SUBMARINO']],
        'shein'         => ['Shein', 'cinza', ['SHEIN']],
        'tiktok'        => ['TikTok Shop', 'cinza', ['TIKTOK']],
        'manual'        => ['Digitado no painel', 'cinza', ['PARTICULAR', 'MANUAL']],
        'outro'         => ['Outros', 'cinza', []],
    ];

    /** Situações (já traduzidas): chave => [nome, cor] */
    public const SITUACOES = [
        'aguardando' => ['Aguardando pagamento', 'amarelo'],
        'pago'       => ['Pago / a enviar', 'verde'],
        'enviado'    => ['Enviado', 'azul'],
        'entregue'   => ['Entregue', 'verde'],
        'cancelado'  => ['Cancelado', 'vermelho'],
    ];

    /** Situações que contam como venda */
    public const VENDIDO = ['pago', 'enviado', 'entregue'];

    /** "Roupa X (Ref. 51)<br /><strong>Tamanho</strong> 02 (Disponibilidade: Imediata)<br />" → ['Roupa X', 'Tamanho 02'] */
    public static function limpar(string $texto): array
    {
        $texto = html_entity_decode($texto, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $partes = preg_split('#<br\s*/?>#i', $texto) ?: [$texto];
        $limpas = [];
        foreach ($partes as $p) {
            $p = strip_tags($p);
            $p = preg_replace('/\(\s*Disponibilidade:[^)]*\)/iu', '', $p);
            $p = trim(preg_replace('/\s+/u', ' ', $p));
            if ($p !== '') {
                $limpas[] = $p;
            }
        }
        $nome = array_shift($limpas) ?? '';
        $nome = trim(preg_replace('/\s*\(Ref\.[^)]*\)\s*$/iu', '', $nome)) ?: $nome; // a referência já vai em outro campo
        return [mb_substr($nome ?: 'Produto', 0, 200), $limpas ? mb_substr(implode(' · ', $limpas), 0, 120) : null];
    }

    /** Nome limpo (sem a variação) — atalho */
    public static function nome(string $texto): string
    {
        [$nome, $variacao] = self::limpar($texto);
        return $variacao ? "$nome ($variacao)" : $nome;
    }

    /** [canal, nome original] a partir do marketplace / local de venda / parceiro */
    public static function canal(string $marketplace, string $pontoVenda, string $parceiro = ''): array
    {
        foreach ([$marketplace, $pontoVenda, $parceiro] as $texto) {
            $t = mb_strtoupper(trim($texto));
            if ($t === '') {
                continue;
            }
            foreach (self::CANAIS as $chave => [, , $palavras]) {
                foreach ($palavras as $palavra) {
                    if (str_contains($t, $palavra)) {
                        return [$chave, trim($texto)];
                    }
                }
            }
            if ($texto === $marketplace) {
                return ['outro', trim($texto)]; // marketplace que o One ainda não conhece: aparece pelo nome
            }
        }
        return [$pontoVenda === '' && $marketplace === '' ? 'loja' : 'outro', trim($marketplace ?: $pontoVenda) ?: null];
    }

    public static function nomeCanal(?string $canal, ?string $original = null): string
    {
        if ($canal === 'outro' && $original) {
            return mb_convert_case(mb_strtolower($original), MB_CASE_TITLE);
        }
        return self::CANAIS[$canal][0] ?? ($canal ? ucfirst(str_replace('_', ' ', $canal)) : 'Loja própria');
    }

    /** Status da loja → aguardando | pago | enviado | entregue | cancelado */
    public static function statusTipo(string $status, string $tipoTray = ''): string
    {
        $s = mb_strtoupper(trim($status));
        return match (true) {
            str_contains($s, 'CANCEL') || str_contains($s, 'ESTORN') || str_contains($s, 'DEVOLV') => 'cancelado',
            str_starts_with($s, 'AGUARDANDO') || str_contains($s, 'PENDENTE') || str_contains($s, 'EM ABERTO') || str_contains($s, 'EM ANÁLISE') => 'aguardando',
            str_contains($s, 'ENTREGUE') || str_contains($s, 'FINALIZADO') || str_contains($s, 'CONCLU') => 'entregue',
            str_contains($s, 'ENVIADO') || str_contains($s, 'A CAMINHO') || str_contains($s, 'EM TRANSPORTE') || str_contains($s, 'DESPACHADO') => 'enviado',
            $s === '' && $tipoTray === 'open' => 'aguardando',
            default => 'pago', // A ENVIAR, PAGO, EM SEPARAÇÃO, FATURADO, PRONTO PARA ENVIO...
        };
    }

    /** Forma de pagamento → pix | cartao | boleto | outro */
    public static function pagamentoTipo(string $tipoTray, string $forma = ''): ?string
    {
        $t = mb_strtolower($tipoTray . ' ' . $forma);
        if (trim($t) === '') {
            return null;
        }
        return match (true) {
            str_contains($t, 'pix') => 'pix',
            str_contains($t, 'boleto') || str_contains($t, 'bank_billet') => 'boleto',
            str_contains($t, 'cart') || str_contains($t, 'credit') || str_contains($t, 'card') || str_contains($t, 'débito') || str_contains($t, 'debito') => 'cartao',
            default => 'outro',
        };
    }

    /** Data/valor seguros */
    private static function data(?string $v): ?string
    {
        $v = trim((string) $v);
        return $v === '' || str_starts_with($v, '0000') || !strtotime($v) ? null : $v;
    }

    private static function num(mixed $v): float
    {
        return round((float) str_replace(',', '.', (string) $v), 2);
    }

    /**
     * Pedido da Tray ("Order" da listagem ou do /complete) → linha de ecommerce_pedidos (+ 'itens' quando completo).
     * $statusDaLoja: [status => tipo da Tray] (open/closed...) para ajudar a classificar status personalizados.
     */
    public static function pedido(array $o, bool $completo, array $statusDaLoja = []): array
    {
        $mkt = $o['MarketplaceOrder'][0]['MarketplaceOrder'] ?? ($o['MarketplaceOrder'][0] ?? []);
        if (!$mkt && !empty($o['MlOrder'])) {
            $mkt = ['marketplace_name' => 'Mercado Livre'] + ($o['MlOrder'][0]['MlOrder'] ?? []);
        }
        $status = (string) ($o['OrderStatus']['status'] ?? $o['status'] ?? '');
        [$canal, $canalNome] = self::canal((string) ($mkt['marketplace_name'] ?? ''), (string) ($o['point_sale'] ?? ''), (string) ($o['partner_id'] ?? ''));
        $data = self::data($o['date'] ?? null) ?? date('Y-m-d');
        $hora = preg_match('/^\d{2}:\d{2}(:\d{2})?$/', (string) ($o['hour'] ?? '')) ? $o['hour'] : '00:00:00';
        $cupom = trim((string) ($o['coupon']['code'] ?? explode('/', (string) ($o['discount_coupon'] ?? ''))[0]));
        $linha = [
            'tray_id' => (string) ($o['id'] ?? ''),
            'data_hora' => "$data $hora",
            'modificado_em' => self::data($o['modified'] ?? null),
            'status' => mb_substr($status, 0, 80),
            'status_tipo' => self::statusTipo($status, (string) ($o['OrderStatus']['type'] ?? ($statusDaLoja[$status] ?? ''))),
            'status_cor' => preg_match('/^#[0-9a-f]{3,8}$/i', (string) ($o['OrderStatus']['background'] ?? '')) ? $o['OrderStatus']['background'] : null,
            'canal' => $canal, 'canal_nome' => $canalNome ? mb_substr($canalNome, 0, 80) : null,
            'marketplace_pedido' => mb_substr((string) ($mkt['marketplace_order_id'] ?? ''), 0, 60) ?: (str_starts_with((string) ($o['external_code'] ?? ''), 'TRAY') ? null : (mb_substr((string) ($o['external_code'] ?? ''), 0, 60) ?: null)),
            'marketplace_envio' => mb_substr((string) ($mkt['marketplace_shipping_type'] ?? ''), 0, 40) ?: null,
            'marketplace_status' => mb_substr((string) ($mkt['marketplace_internal_status'] ?? ''), 0, 60) ?: null,
            'parceiro' => mb_substr((string) ($o['partner_id'] ?? ''), 0, 60) ?: null,
            'total' => self::num($o['total'] ?? 0),
            'subtotal' => self::num($o['partial_total'] ?? 0),
            'desconto' => self::num($o['discount'] ?? 0),
            'frete' => self::num($o['shipment_value'] ?? 0),
            'cupom' => $cupom !== '' ? mb_substr($cupom, 0, 60) : null,
            'pagamento' => mb_substr((string) ($o['payment_method'] ?? $o['payment_form'] ?? ''), 0, 80) ?: null,
            'pagamento_tipo' => self::pagamentoTipo((string) ($o['payment_method_type'] ?? ''), (string) ($o['payment_method'] ?? $o['payment_form'] ?? '')),
            'pago_em' => self::data($o['payment_date'] ?? null),
            'envio' => mb_substr((string) ($o['shipment'] ?? ''), 0, 80) ?: null,
            'prazo_entrega' => self::data($o['estimated_delivery_date'] ?? null),
            'enviado_em' => self::data($o['sending_date'] ?? null) ?? self::data($o['shipment_date'] ?? null),
            'entregue' => !empty($o['delivered']) && $o['delivered'] !== '0' ? 1 : 0,
            'tem_nf' => !empty($o['has_invoice']) && $o['has_invoice'] !== '0' || !empty($o['OrderInvoice'][0]['OrderInvoice']['number']) ? 1 : 0,
        ];
        if (!$completo) {
            return $linha;
        }
        $nota = $o['OrderInvoice'][0]['OrderInvoice'] ?? null;
        $cliente = $o['Customer'] ?? [];
        $endereco = $cliente['CustomerAddresses'][0]['CustomerAddress'] ?? [];
        $itens = [];
        foreach ($o['ProductsSold'] ?? [] as $linhaItem) {
            $i = $linhaItem['ProductsSold'] ?? $linhaItem;
            if (!isset($i['quantity'])) {
                continue;
            }
            [$nome, $variacao] = self::limpar((string) ($i['original_name'] ?? '') !== '' ? $i['original_name'] : (string) ($i['name'] ?? 'Produto'));
            if (!$variacao && !empty($i['name'])) {
                $variacao = self::limpar((string) $i['name'])[1];
            }
            $img = $i['ProductSoldImage'][0]['thumbs']['90']['https'] ?? ($i['ProductSoldImage'][0]['https'] ?? null);
            $itens[] = [
                'produto_ref' => mb_substr((string) (($i['reference'] ?? '') !== '' ? $i['reference'] : ($i['product_id'] ?? '')), 0, 80),
                'ean' => mb_substr((string) ($i['ean'] ?? ''), 0, 20) ?: null,
                'nome' => $nome, 'variacao' => $variacao ?: (trim(strip_tags((string) ($i['text_variant'] ?? ''))) ?: null),
                'categoria' => mb_substr((string) ($i['Category'][0]['name'] ?? ''), 0, 80) ?: null,
                'quantidade' => max(1, (int) $i['quantity']),
                'preco' => self::num($i['price'] ?? 0),
                'custo' => isset($i['cost_price']) && (float) $i['cost_price'] > 0 ? self::num($i['cost_price']) : null,
                'imagem' => is_string($img) && str_starts_with($img, 'https://') ? mb_substr($img, 0, 300) : null,
            ];
        }
        $custo = array_sum(array_map(fn($i) => ($i['custo'] ?? 0) * $i['quantidade'], $itens));
        return $linha + [
            'transportadora' => mb_substr((string) ($o['shipment_integrator'] ?? ''), 0, 80) ?: null,
            'custo' => $custo > 0 ? round($custo, 2) : (isset($o['cost']) && (float) $o['cost'] > 0 ? self::num($o['cost']) : null),
            'itens_qtd' => array_sum(array_column($itens, 'quantidade')),
            'nf_numero' => $nota ? (mb_substr((string) ($nota['number'] ?? ''), 0, 30) ?: null) : null,
            'nf_em' => $nota ? self::data($nota['issue_date'] ?? null) : null,
            'cidade' => mb_substr(trim((string) ($endereco['city'] ?? $cliente['city'] ?? '')), 0, 80) ?: null,
            'uf' => mb_strtoupper(mb_substr(trim((string) ($endereco['state'] ?? $cliente['state'] ?? '')), 0, 2)) ?: null,
            // Código anônimo: só serve para saber se a pessoa já comprou antes (não dá para voltar ao cliente)
            'cliente_hash' => !empty($o['customer_id']) ? substr(hash('sha256', 'one-eco|' . $o['customer_id']), 0, 16) : null,
            'detalhado' => 1,
            'itens' => $itens,
        ];
    }
}
