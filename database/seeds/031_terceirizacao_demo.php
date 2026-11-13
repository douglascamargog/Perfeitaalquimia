<?php
/**
 * Terceirização (DEMONSTRAÇÃO — só na instalação nova):
 *  - 3 clientes da carteira de Terceirização com produtos na marca deles (nossos produtos com ficha técnica);
 *  - 1 deles com uma fórmula própria (produto exclusivo, com ficha e preço);
 *  - 1 cliente da carteira de TELEVENDAS que também faz um produto com a marca dele (pedido misto);
 *  - rótulos: entradas e consumos, um abaixo do mínimo; uma ordem planejada para cliente.
 */
use App\Core\Banco;
use App\Services\TerceirizacaoService as T;
use App\Services\ProducaoService as P;

$terc = Banco::todos("SELECT c.id FROM clientes c JOIN funcoes f ON f.id = c.funcao_id WHERE f.frente = 'terceirizacao' AND c.excluido_em IS NULL AND c.tipo = 'cliente' ORDER BY c.id LIMIT 3");
$tele = Banco::um("SELECT c.id FROM clientes c JOIN funcoes f ON f.id = c.funcao_id WHERE f.frente = 'televendas' AND c.excluido_em IS NULL AND c.tipo = 'cliente' ORDER BY c.id LIMIT 1");
$comFicha = array_column(Banco::todos('SELECT DISTINCT produto_id AS id FROM ficha_tecnica ORDER BY produto_id LIMIT 6'), 'id');
if (count($terc) < 3 || count($comFicha) < 4) {
    return 'Terceirização (demonstração): sem clientes ou fichas suficientes, nada criado.';
}
$quando = date('Y-m-d H:i:s', strtotime('-20 days'));
$nome = fn($id) => (string) Banco::valor('SELECT nome FROM produtos WHERE id = ?', [$id]);
$criados = [];
$marcas = ['Vida Plena', 'Natu Mais', 'Bem Viver'];
foreach ($terc as $i => $c) {
    foreach (array_slice($comFicha, $i, 2) as $j => $produtoId) {
        $tp = T::salvarProduto(null, ['cliente_id' => $c['id'], 'tipo' => 'nosso', 'produto_id' => $produtoId,
            'nome_rotulo' => $nome($produtoId) . ' ' . $marcas[$i], 'codigo_cliente' => strtoupper(substr($marcas[$i], 0, 2)) . '-' . (100 + $j),
            'rotulos_minimo' => 500], null);
        T::movimentarRotulos($tp, 'entrada', $i === 1 && $j === 0 ? 300 : 3000, 'Rótulos enviados pelo cliente', null, null, $quando);
        $criados[] = $tp;
    }
}
// Fórmula própria do 1º cliente: produto exclusivo com ficha técnica e preço na tabela Terceirização
$exclusivo = T::salvarProduto(null, ['cliente_id' => $terc[0]['id'], 'tipo' => 'proprio', 'nome_rotulo' => 'Blend Detox Vida Plena',
    'embalagem' => '60 cápsulas', 'codigo_cliente' => 'VP-900', 'rotulos_minimo' => 300], null);
$prodExclusivo = (int) Banco::valor('SELECT produto_id FROM terceirizacao_produtos WHERE id = ?', [$exclusivo]);
$ficha = Banco::todos('SELECT materia_prima_id, quantidade FROM ficha_tecnica WHERE produto_id = ?', [$comFicha[0]]);
foreach ($ficha as $l) {
    Banco::inserir('ficha_tecnica', ['produto_id' => $prodExclusivo, 'materia_prima_id' => $l['materia_prima_id'], 'quantidade' => $l['quantidade']]);
}
Banco::executar('INSERT IGNORE INTO produto_precos (produto_id, tabela_preco_id, preco) VALUES (?, ?, 38.90)', [$prodExclusivo, T::tabelaId()]);
T::movimentarRotulos($exclusivo, 'entrada', 1500, 'Primeira remessa de rótulos', null, null, $quando);

// Cliente de Televendas que também faz um produto com a marca dele (compra junto com os produtos normais)
if ($tele) {
    $tp = T::salvarProduto(null, ['cliente_id' => $tele['id'], 'tipo' => 'nosso', 'produto_id' => $comFicha[3],
        'nome_rotulo' => $nome($comFicha[3]) . ' (marca da loja)', 'preco' => '', 'rotulos_minimo' => 200], null);
    T::movimentarRotulos($tp, 'entrada', 800, 'Rótulos da loja', null, null, $quando);
}

// Uma produção já feita (consumiu rótulos) e uma planejada
// (escolhe um produto de cliente que tenha matéria-prima suficiente na demonstração)
foreach (Banco::todos('SELECT cliente_id, produto_id FROM terceirizacao_produtos ORDER BY id') as $t) {
    if (P::previa((int) $t['produto_id'], 60)['ok']) {
        $op = P::criarOrdem(['produto_id' => $t['produto_id'], 'quantidade' => 60, 'cliente_id' => $t['cliente_id'], 'observacoes' => 'Lote com a marca do cliente'], null, 'manual', $quando);
        P::concluirOrdem($op, null, null, date('Y-m-d H:i:s', strtotime('-15 days')));
        break;
    }
}
P::criarOrdem(['produto_id' => $comFicha[2], 'quantidade' => 500, 'cliente_id' => $terc[1]['id'], 'previsto_para' => date('Y-m-d', strtotime('+5 days'))], null, 'manual');

// Pedidos faturados com produtos da marca do cliente (junto com produtos normais, no caso do cliente de Televendas)
$forma = Banco::um("SELECT f.id AS forma, c.id AS condicao FROM formas_pagamento f JOIN condicoes_pagamento c ON c.forma_pagamento_id = f.id WHERE c.valor_minimo = 0 AND f.slug <> 'boleto' LIMIT 1");
$pedidosDemo = 0;
foreach (array_filter([$terc[0]['id'] ?? null, $terc[2]['id'] ?? null, $tele['id'] ?? null]) as $k => $cid) {
    $cliente = \App\Models\Cliente::buscar((int) $cid);
    $itens = [];
    foreach (T::linhasDoPedido((int) $cid) as $chave => $l) {
        $itens[$chave] = 300 + 100 * $k;
    }
    if ($cid === ($tele['id'] ?? null)) { // pedido misto: + produtos normais
        foreach (Banco::todos('SELECT produto_id FROM produto_precos WHERE tabela_preco_id = ? LIMIT 3', [\App\Services\PedidoService::tabelaDoCliente($cliente)['id']]) as $pp) {
            $itens[$pp['produto_id']] = 12;
        }
    }
    if (!$itens || !$forma) {
        continue;
    }
    [$id] = \App\Services\PedidoService::criar($cliente, ['itens' => $itens, 'forma_pagamento_id' => $forma['forma'], 'condicao_id' => $forma['condicao'],
        'origem' => 'sistema', 'vendedor_id' => $cliente['responsavel_id'], 'usuario_id' => $cliente['responsavel_id'], 'observacoes' => 'Demonstração: produtos com a marca do cliente']);
    if ($id) {
        $quandoFat = date('Y-m-d H:i:s', strtotime('-' . (3 + $k * 4) . ' days'));
        Banco::executar("UPDATE pedidos SET situacao = 'faturado', aprovado_em = ?, faturado_em = ? WHERE id = ?", [$quandoFat, $quandoFat, $id]);
        $pedidosDemo++;
    }
}

return 'Terceirização (demonstração): ' . (count($criados) + 2) . ' produtos de clientes, 1 fórmula própria, rótulos e ' . $pedidosDemo . ' pedido(s) com a marca do cliente.';
