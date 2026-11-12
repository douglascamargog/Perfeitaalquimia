<?php
/**
 * DEMONSTRAÇÃO do catálogo e dos pedidos (só na instalação nova; "_demo" no nome = nunca roda em atualização).
 *  - Preços das 3 tabelas e estoque vindos do ERP simulado.
 *  - Acesso ao catálogo para 5 clientes (CNPJ + senha Demo@2026).
 *  - ~12 meses de pedidos faturados, coerentes com a "última compra" e o "intervalo médio" da Carteira.
 *  - Alguns pedidos recentes aguardando aprovação e aprovados (para a tela do Financeiro).
 */
use App\Core\Banco;
use App\Services\ErpSync;
use App\Services\PedidoService;

mt_srand(2026);
$precos = ErpSync::precos();
ErpSync::estoque();

// ---------- Acessos ao catálogo ----------
$vendedor = (int) Banco::valor("SELECT id FROM usuarios WHERE email = 'televendas1@demo.local'");
$clientesAcesso = Banco::todos("SELECT id, cnpj FROM clientes WHERE tipo = 'cliente' AND cnpj IS NOT NULL AND responsavel_id = ? ORDER BY id LIMIT 4", [$vendedor]);
$jaEscolhidos = array_map('intval', array_column($clientesAcesso, 'id')) ?: [0];
$emCobranca = Banco::um("SELECT c.id, c.cnpj FROM clientes c JOIN cobrancas cb ON cb.cliente_id = c.id AND cb.situacao = 'aberta'
    WHERE c.cnpj IS NOT NULL AND c.id NOT IN (" . implode(',', $jaEscolhidos) . ') ORDER BY c.id LIMIT 1');
if ($emCobranca) {
    $clientesAcesso[] = $emCobranca;
}
$senha = password_hash('Demo@2026', PASSWORD_DEFAULT);
foreach ($clientesAcesso as $c) {
    Banco::executar('INSERT IGNORE INTO cliente_acessos (cliente_id, senha_hash) VALUES (?, ?)', [$c['id'], $senha]);
}

// ---------- Histórico de pedidos (faturados) ----------
$produtos = Banco::todos('SELECT p.id, p.nome, pp.preco, pp.tabela_preco_id FROM produtos p JOIN produto_precos pp ON pp.produto_id = p.id ORDER BY p.id');
$porTabela = [];
foreach ($produtos as $p) {
    $porTabela[$p['tabela_preco_id']][] = $p;
}
$formas = Banco::todos('SELECT f.id AS forma, c.id AS condicao, c.desconto_pct FROM formas_pagamento f JOIN condicoes_pagamento c ON c.forma_pagamento_id = f.id WHERE c.valor_minimo = 0');
$lojista = (int) Banco::valor("SELECT id FROM tabelas_preco WHERE slug = 'lojista'");
$clientes = Banco::todos("SELECT id, uf, responsavel_id, tabela_preco_id, ultima_compra_em, intervalo_medio_dias FROM clientes
    WHERE tipo = 'cliente' AND ultima_compra_em IS NOT NULL AND excluido_em IS NULL");
$inicio = date('Y-m-d', strtotime('-12 months'));
$total = 0;
$nf = 3000;
foreach ($clientes as $c) {
    $tabela = (int) ($c['tabela_preco_id'] ?: $lojista);
    $lista = $porTabela[$tabela] ?? $porTabela[$lojista] ?? [];
    if (!$lista) {
        continue;
    }
    $minimo = PedidoService::pedidoMinimo($c['uf']);
    $intervalo = max(15, (int) $c['intervalo_medio_dias']);
    $datas = [];
    for ($d = strtotime($c['ultima_compra_em']); $d >= strtotime($inicio); $d -= (int) round($intervalo * (0.8 + mt_rand(0, 40) / 100)) * 86400) {
        $datas[] = date('Y-m-d', $d);
    }
    foreach (array_reverse($datas) as $k => $data) {
        $itens = [];
        $subtotal = 0;
        foreach ((array) array_rand($lista, mt_rand(3, 7)) as $i) {
            $q = mt_rand(2, 12);
            $itens[] = [$lista[$i], $q];
            $subtotal += $q * (float) $lista[$i]['preco'];
        }
        while ($subtotal < $minimo) { // garante o pedido mínimo da região
            $itens[0][1] += 2;
            $subtotal += 2 * (float) $itens[0][0]['preco'];
        }
        $f = $formas[mt_rand(0, count($formas) - 1)];
        $desconto = round($subtotal * (float) $f['desconto_pct'] / 100, 2);
        $criado = date('Y-m-d H:i:s', strtotime("$data -" . mt_rand(1, 3) . ' days ' . mt_rand(8, 18) . ':' . mt_rand(10, 59)));
        $id = Banco::inserir('pedidos', [
            'uuid' => PedidoService::novoUuid(), 'cliente_id' => $c['id'], 'vendedor_id' => $c['responsavel_id'],
            'origem' => ['sistema', 'catalogo', 'sistema', 'app'][mt_rand(0, 3)], 'tabela_preco_id' => $tabela,
            'forma_pagamento_id' => $f['forma'], 'condicao_id' => $f['condicao'], 'subtotal' => round($subtotal, 2), 'desconto' => $desconto,
            'total' => round($subtotal - $desconto, 2), 'situacao' => 'faturado', 'omie_codigo' => 'HIST-' . ++$nf, 'omie_numero' => (string) $nf,
            'numero_nf' => (string) ($nf + 40000), 'faturado_em' => "$data 15:00:00", 'aprovado_em' => $criado, 'criado_em' => $criado,
        ]);
        foreach ($itens as [$p, $q]) {
            Banco::inserir('pedido_itens', ['pedido_id' => $id, 'produto_id' => $p['id'], 'nome' => $p['nome'], 'quantidade' => $q,
                'preco_unitario' => $p['preco'], 'total' => round($q * (float) $p['preco'], 2)]);
        }
        Banco::inserir('pedido_historico', ['pedido_id' => $id, 'situacao' => 'faturado', 'texto' => 'Faturado · NF ' . ($nf + 40000) . '.', 'criado_em' => "$data 15:00:00"]);
        if ($k >= count($datas) - 2) { // os 2 últimos aparecem na linha do tempo do cliente
            Banco::inserir('cliente_eventos', ['cliente_id' => $c['id'], 'tipo' => 'pedido', 'canal' => 'ERP', 'ocorrido_em' => "$data 15:00:00",
                'descricao' => "Pedido #$id faturado · NF " . ($nf + 40000) . ' · ' . dinheiro(round($subtotal - $desconto, 2))]);
        }
        $total++;
    }
}

// ---------- Pedidos recentes (passam pelas regras de verdade e vão ao ERP simulado) ----------
$recentes = 0;
foreach (array_slice($clientesAcesso, 0, 4) as $i => $c) {
    $cliente = Banco::um('SELECT * FROM clientes WHERE id = ?', [$c['id']]);
    $tabela = PedidoService::tabelaDoCliente($cliente);
    $minimo = PedidoService::pedidoMinimo($cliente['uf']);
    $itens = [];
    $soma = 0;
    foreach (array_slice($porTabela[$tabela['id']] ?? [], 10 + $i * 7, 6) as $p) {
        $q = (int) max(2, ceil(($minimo / 5) / (float) $p['preco']));
        $itens[$p['id']] = $q;
        $soma += $q * (float) $p['preco'];
    }
    $pix = Banco::um("SELECT f.id AS forma, c.id AS condicao FROM formas_pagamento f JOIN condicoes_pagamento c ON c.forma_pagamento_id = f.id WHERE f.slug = 'pix' LIMIT 1");
    [$id] = PedidoService::criar($cliente, ['itens' => $itens, 'forma_pagamento_id' => $pix['forma'], 'condicao_id' => $pix['condicao'],
        'origem' => $i % 2 ? 'catalogo' : 'sistema', 'usuario_id' => $i % 2 ? null : $vendedor, 'observacoes' => $i === 0 ? 'Entregar pela manhã.' : '']);
    if ($id && $i === 3) {
        PedidoService::aprovar(Banco::um('SELECT * FROM pedidos WHERE id = ?', [$id]), (int) Banco::valor("SELECT id FROM usuarios WHERE email = 'financeiro@demo.local'"));
    }
    $recentes += $id ? 1 : 0;
}

$cnpjs = implode(', ', array_map(fn($c) => cnpj_formatado($c['cnpj']), $clientesAcesso));
return "Catálogo (demonstração): $precos preços, $total pedidos faturados em 12 meses, $recentes pedidos recentes. Acesso ao catálogo (senha Demo@2026): $cnpjs.";
