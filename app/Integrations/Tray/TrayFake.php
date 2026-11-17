<?php
namespace App\Integrations\Tray;

use App\Core\Banco;

/**
 * Loja virtual SIMULADA (demonstração). Pedidos determinísticos (sempre os mesmos para a mesma data),
 * com sazonalidade: fim de semana mais fraco, pico na Black Friday e em dezembro, janeiro mais calmo.
 * Canais: loja própria, Mercado Livre, Shopee, Amazon, Magalu. Os produtos são os do cadastro (mesmos nomes do site).
 */
class TrayFake implements EcommerceInterface
{
    /** Peso de cada mês (1 = normal) */
    private const SAZONAL = [1 => .8, 2 => .85, 3 => 1, 4 => 1, 5 => 1.15, 6 => 1.05, 7 => 1.1, 8 => 1.05, 9 => 1, 10 => 1.1, 11 => 1.45, 12 => 1.35];

    public function nome(): string
    {
        return 'fake';
    }

    /** Pedidos simulados por dia (sempre os mesmos para a mesma data), já completos */
    public function pedidosAlterados(string $desde, string $ate, int $pagina = 1): array
    {
        $todos = [];
        $ate = min($ate, date('Y-m-d')); // não inventa o futuro
        for ($d = $desde; $d <= $ate; $d = date('Y-m-d', strtotime("$d +1 day"))) {
            array_push($todos, ...$this->pedidosDoDia($d));
        }
        $porPagina = 500;
        return ['pedidos' => array_slice($todos, ($pagina - 1) * $porPagina, $porPagina), 'total' => count($todos), 'por_pagina' => $porPagina];
    }

    public function pedidoCompleto(string $id): ?array
    {
        if (!preg_match('/^D(\d{8})-(\d+)$/', $id, $m)) {
            return null;
        }
        $dia = substr($m[1], 0, 4) . '-' . substr($m[1], 4, 2) . '-' . substr($m[1], 6, 2);
        foreach ($this->pedidosDoDia($dia) as $p) {
            if ($p['tray_id'] === $id) {
                return $p;
            }
        }
        return null;
    }

    public function statusDaLoja(): array
    {
        return ['AGUARDANDO PAGAMENTO' => 'open', 'A ENVIAR' => 'open', 'ENVIADO' => 'closed', 'FINALIZADO' => 'closed', 'CANCELADO' => 'closed'];
    }

    /** Quantos pedidos no dia (fim de semana mais fraco, pico na Black Friday, sazonalidade e crescimento) */
    private function quantosNoDia(string $d): int
    {
        $semente = crc32("tray-$d");
        $semana = (int) date('N', strtotime($d)) >= 6 ? .7 : 1;
        $pico = substr($d, 5) === $this->blackFriday((int) substr($d, 0, 4)) ? 4.2 : 1;
        $n = (int) round((14 + $semente % 9) * self::SAZONAL[(int) substr($d, 5, 2)] * $semana * $pico * $this->crescimento($d));
        if ($d === date('Y-m-d')) {
            $n = (int) round($n * min(1, (int) date('G') / 22)); // hoje: só o que já entrou
        }
        return $n;
    }

    private ?array $produtos = null;

    /** Pedidos completos de um dia, no formato de TrayTexto::pedido */
    private function pedidosDoDia(string $d): array
    {
        $this->produtos ??= Banco::todos('SELECT slug, codigo, nome FROM produtos WHERE ativo = 1 ORDER BY id LIMIT 60');
        if (!$this->produtos) {
            return [];
        }
        $canais = [['loja', 'LOJA VIRTUAL', 46], ['mercado_livre', 'Mercado Livre', 28], ['shopee', 'Shopee', 14], ['amazon', 'Amazon', 7], ['magalu', 'Magalu', 3], ['manual', 'PARTICULAR', 2]];
        $cidades = [['Porto Alegre', 'RS', 14], ['São Paulo', 'SP', 16], ['Canoas', 'RS', 5], ['Caxias do Sul', 'RS', 5], ['Curitiba', 'PR', 7], ['Florianópolis', 'SC', 5],
            ['Belo Horizonte', 'MG', 6], ['Rio de Janeiro', 'RJ', 8], ['Campinas', 'SP', 5], ['Salvador', 'BA', 4], ['Recife', 'PE', 3], ['Goiânia', 'GO', 3],
            ['Brasília', 'DF', 4], ['Novo Hamburgo', 'RS', 3], ['Fortaleza', 'CE', 3], ['Manaus', 'AM', 1], ['Belém', 'PA', 2], ['Joinville', 'SC', 3], ['Vitória', 'ES', 2], ['Campo Grande', 'MS', 1]];
        $envios = [['loja', 'PAC', 'Correios', 18.9], ['loja', 'Sedex', 'Correios', 29.9], ['loja', 'Jadlog', 'Jadlog', 21.5], ['mercado_livre', 'Mercado Envios', 'Mercado Envios', 0], ['shopee', 'Shopee Xpress', 'Shopee Xpress', 0], ['amazon', 'Amazon DBA', 'Amazon', 0], ['magalu', 'Magalu Entregas', 'Magalu', 0], ['manual', 'Retirada', '', 0]];
        $sorteia = function (array $lista, int $s) {
            $total = array_sum(array_column($lista, count($lista[0]) - 1));
            $alvo = $s % $total;
            foreach ($lista as $item) {
                $alvo -= $item[count($item) - 1];
                if ($alvo < 0) {
                    return $item;
                }
            }
            return $lista[0];
        };
        $idade = (int) ((strtotime('today') - strtotime($d)) / 86400);
        $lista = [];
        $n = $this->quantosNoDia($d);
        for ($i = 1; $i <= $n; $i++) {
            $s = crc32("pedido-$d-$i");
            [$canal, $canalNome] = $sorteia($canais, $s);
            [$cidade, $uf] = $sorteia($cidades, $s >> 3);
            $minuto = 7 * 60 + (int) (($s >> 5) % (16 * 60)); // das 7h às 23h (mais à noite)
            if ($d === date('Y-m-d') && $minuto > (int) date('G') * 60 + (int) date('i')) {
                $minuto = max(0, (int) date('G') * 60 - $i);
            }
            $hora = sprintf('%02d:%02d:%02d', intdiv($minuto, 60), $minuto % 60, $s % 60);
            // Itens
            $itens = [];
            $qtdItens = 1 + ($s >> 7) % 3;
            for ($k = 0; $k < $qtdItens; $k++) {
                $pr = $this->produtos[(($s >> (9 + $k)) + $k * 7) % count($this->produtos)];
                $preco = round(39.9 + crc32($pr['slug']) % 90, 2);
                $itens[] = ['produto_ref' => $pr['slug'], 'ean' => null, 'nome' => $pr['nome'], 'variacao' => null, 'categoria' => ['Suplementos', 'Chás e ervas', 'Óleos', 'Cápsulas'][crc32($pr['slug']) % 4],
                    'quantidade' => 1 + ($s >> (11 + $k)) % 2, 'preco' => $preco, 'custo' => round($preco * .42, 2), 'imagem' => null];
            }
            $subtotal = round(array_sum(array_map(fn($it) => $it['preco'] * $it['quantidade'], $itens)), 2);
            $cupom = $canal === 'loja' && $s % 7 === 0 ? ['BEMVINDO10', 'ALQUIMIA15', 'FRETEGRATIS'][$s % 3] : null;
            $desconto = $cupom ? round($subtotal * ($cupom === 'ALQUIMIA15' ? .15 : ($cupom === 'BEMVINDO10' ? .10 : 0)), 2) : 0;
            $envio = array_values(array_filter($envios, fn($e) => $e[0] === $canal));
            $envio = $envio[$s % count($envio)];
            $frete = $cupom === 'FRETEGRATIS' || $subtotal >= 199 ? 0 : $envio[3];
            $pagamento = $canal === 'loja' ? [['Pix', 'pix'], ['Cartão de crédito', 'cartao'], ['Cartão de crédito', 'cartao'], ['Boleto', 'boleto']][($s >> 13) % 4]
                : ['Pagamento no ' . $canalNome, 'outro'];
            // Situação conforme a idade do pedido
            $sorte = ($s >> 15) % 100;
            $tipo = match (true) {
                $sorte < 4 => 'cancelado',
                $pagamento[1] === 'boleto' && $sorte < 14 && $idade < 4 => 'aguardando',
                $idade === 0 => $sorte < 20 ? 'aguardando' : 'pago',
                $idade <= 2 => $sorte < 70 ? 'pago' : 'enviado',
                $idade <= 8 => $sorte < 55 ? 'enviado' : 'entregue',
                default => 'entregue',
            };
            $status = ['aguardando' => 'AGUARDANDO PAGAMENTO', 'pago' => 'A ENVIAR', 'enviado' => 'ENVIADO', 'entregue' => 'FINALIZADO', 'cancelado' => 'CANCELADO'][$tipo];
            $prazo = date('Y-m-d', strtotime("$d +" . (3 + $s % 6) . ' days'));
            $enviadoEm = in_array($tipo, ['enviado', 'entregue'], true) ? date('Y-m-d', strtotime("$d +" . (($s >> 17) % 10 < 8 ? 1 : 4) . ' days')) : null;
            $id = 'D' . str_replace('-', '', $d) . '-' . $i;
            $lista[] = [
                'tray_id' => $id, 'data_hora' => "$d $hora", 'modificado_em' => "$d $hora",
                'status' => $status, 'status_tipo' => $tipo, 'status_cor' => null,
                'canal' => $canal, 'canal_nome' => $canalNome,
                'marketplace_pedido' => in_array($canal, ['loja', 'manual'], true) ? null : strtoupper(substr($canal, 0, 2)) . '-' . (2000000000 + $s % 999999999),
                'marketplace_envio' => $canal === 'mercado_livre' ? ['me2', 'fulfillment'][$s % 2] : null,
                'marketplace_status' => in_array($canal, ['loja', 'manual'], true) ? null : ['aguardando' => 'payment_required', 'pago' => 'ready_to_ship', 'enviado' => 'shipped', 'entregue' => 'delivered', 'cancelado' => 'cancelled'][$tipo],
                'parceiro' => null,
                'total' => round($subtotal - $desconto + $frete, 2), 'subtotal' => $subtotal, 'desconto' => $desconto, 'frete' => $frete,
                'custo' => round(array_sum(array_map(fn($it) => $it['custo'] * $it['quantidade'], $itens)), 2), 'cupom' => $cupom,
                'itens_qtd' => array_sum(array_column($itens, 'quantidade')),
                'pagamento' => $pagamento[0], 'pagamento_tipo' => $pagamento[1], 'pago_em' => $tipo === 'aguardando' ? null : $d,
                'envio' => $envio[1], 'transportadora' => $envio[2] ?: null, 'prazo_entrega' => $prazo, 'enviado_em' => $enviadoEm,
                'entregue' => $tipo === 'entregue' ? 1 : 0, 'tem_nf' => in_array($tipo, ['enviado', 'entregue'], true) || ($tipo === 'pago' && $sorte % 2 === 0) ? 1 : 0,
                'nf_numero' => null, 'nf_em' => null,
                'cidade' => $cidade, 'uf' => $uf,
                'cliente_hash' => substr(hash('sha256', 'demo|' . (($s >> 4) % 100 < 72 ? "$d-$i" : 'fiel-' . ($s % 400))), 0, 16),
                'detalhado' => 1, 'itens' => $itens,
            ];
        }
        return $lista;
    }

    /**
     * Demonstração: pedidos do dia nos marketplaces. Os que foram pagos há mais de 40 minutos já têm nota
     * (então entram na fila do Depósito); os mais novos ainda esperam a nota.
     */
    public function pedidosParaSeparar(string $desde): array
    {
        $produtos = \App\Core\Banco::todos("SELECT codigo, nome FROM produtos WHERE ativo = 1 AND codigo IS NOT NULL AND codigo <> '' ORDER BY id LIMIT 20");
        if (!$produtos) {
            return [];
        }
        $canais = ['mercado_livre', 'mercado_livre', 'shopee', 'mercado_livre', 'shopee', 'amazon', 'loja', 'mercado_livre', 'shopee', 'loja'];
        $nomes = ['Ana P.', 'Bruno S.', 'Carla M.', 'Diego R.', 'Elisa T.', 'Fábio L.', 'Gabi N.', 'Heitor C.', 'Iara B.', 'João V.'];
        $cidades = [['Porto Alegre', 'RS'], ['São Paulo', 'SP'], ['Curitiba', 'PR'], ['Recife', 'PE'], ['Belo Horizonte', 'MG'], ['Canoas', 'RS'],
            ['Florianópolis', 'SC'], ['Goiânia', 'GO'], ['Salvador', 'BA'], ['Campinas', 'SP']];
        // Os pedidos aconteceram nas últimas horas (a qualquer hora do dia que se abra a demonstração);
        // a base é a hora cheia, então o mesmo pedido mantém o código durante a hora
        $base = (int) (floor(time() / 3600) * 3600);
        $hoje = date('YmdH', $base);
        $lista = [];
        foreach ([390, 355, 320, 285, 250, 215, 180, 125, 70, 25] as $i => $minutosAtras) {
            $criado = $base - $minutosAtras * 60;
            $s = crc32($hoje . '-' . $i);
            $itens = [];
            foreach ([0, 1] as $k) {
                if ($k === 1 && $s % 3 === 0) {
                    continue;
                }
                $pr = $produtos[($s >> ($k * 4)) % count($produtos)];
                $itens[] = ['ref' => $pr['codigo'], 'nome' => $pr['nome'], 'quantidade' => 1 + ($s >> (8 + $k)) % 3, 'preco' => round(39.9 + ($s >> (12 + $k)) % 60, 2)];
            }
            $temNota = $criado < time() - 40 * 60;
            $lista[] = [
                'codigo' => 'TRAY-' . date('ymdH', $base) . '-' . ($i + 1), 'numero_marketplace' => strtoupper(substr($canais[$i], 0, 2)) . '-' . (2000000000 + $s % 999999999),
                'canal' => $canais[$i], 'criado_em' => date('Y-m-d H:i:s', $criado), 'cancelado' => false,
                'nf' => $temNota ? (string) (100000 + intdiv($base, 3600) % 50000 * 10 + $i) : null, 'nf_em' => $temNota ? date('Y-m-d H:i:s', $criado + 35 * 60) : null,
                'comprador' => $nomes[$i], 'cidade' => $cidades[$i][0], 'uf' => $cidades[$i][1],
                'total' => round(array_sum(array_map(fn($it) => $it['quantidade'] * $it['preco'], $itens)), 2), 'itens' => $itens,
            ];
        }
        return $lista;
    }

    public function testarConexao(): array
    {
        return [true, 'Loja simulada (demonstração).'];
    }

    /** Black Friday = dia seguinte à 4ª quinta-feira de novembro (MM-DD) */
    private function blackFriday(int $ano): string
    {
        return date('m-d', strtotime("fourth thursday of november $ano +1 day"));
    }

    /** A loja cresce ~1,5% ao mês (para o gráfico de 12 meses ter tendência) */
    private function crescimento(string $data): float
    {
        $meses = (int) ((strtotime('today') - strtotime($data)) / (30 * 86400));
        return pow(0.985, $meses);
    }
}
