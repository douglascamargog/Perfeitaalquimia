<?php
/**
 * DEMONSTRAÇÃO da captura de leads (só na instalação nova; "_demo" no nome = nunca roda em atualização).
 * ~70 formulários nos últimos 60 dias, pelo site e por anúncios (3 campanhas), passando pelas regras de verdade
 * (LeadService): alguns já existiam na carteira, alguns são robôs. Parte dos leads novos virou cliente com pedido faturado.
 * Pessoas, lojas e contatos são fictícios.
 */
use App\Core\Banco;
use App\Services\LeadService;
use App\Services\PedidoService;

mt_srand(2121);
$campanhas = [
    [30, ['utm_source' => 'instagram', 'utm_medium' => 'cpc', 'utm_campaign' => 'revendedor-rs']],
    [20, ['utm_source' => 'google', 'utm_medium' => 'cpc', 'utm_campaign' => 'fabrica-suplementos']],
    [15, ['utm_source' => 'facebook', 'utm_medium' => 'paid_social', 'utm_campaign' => 'lojista-natural']],
    [25, []], // site, sem campanha (orgânico)
    [10, ['utm_source' => 'newsletter', 'utm_medium' => 'email', 'utm_campaign' => 'novidades-mes']],
];
$nomes = ['Ana', 'Bruno', 'Carla', 'Diego', 'Elisa', 'Fábio', 'Gabriela', 'Henrique', 'Isabela', 'João', 'Karen', 'Lucas', 'Mariana', 'Nícolas', 'Olívia', 'Paulo', 'Renata', 'Sérgio', 'Tatiana', 'Vinícius'];
$sobrenomes = ['Ramos', 'Teixeira', 'Fontana', 'Kunz', 'Barcellos', 'Vargas', 'Zanella', 'Prates', 'Lemos', 'Antunes'];
$lojas = ['Empório', 'Natural', 'Vida Leve', 'Casa Verde', 'Bem Nutrir', 'Grão Dourado', 'Sabor Integral', 'Ponto Fit', 'Raiz Forte', 'Folha Viva', 'Farmácia Nova', 'Botica Real'];
$cidades = [['Porto Alegre', 'RS'], ['Caxias do Sul', 'RS'], ['Pelotas', 'RS'], ['Santa Maria', 'RS'], ['Florianópolis', 'SC'], ['Joinville', 'SC'], ['Curitiba', 'PR'],
    ['Londrina', 'PR'], ['São Paulo', 'SP'], ['Campinas', 'SP'], ['Belo Horizonte', 'MG'], ['Goiânia', 'GO']];
$mensagens = ['', '', 'Tenho uma loja de produtos naturais e quero conhecer a linha.', 'Qual o pedido mínimo?', 'Vocês atendem a minha cidade?', 'Gostaria da tabela para lojistas.'];
$sortear = function (array $lista) {
    $r = mt_rand(1, array_sum(array_column($lista, 0)));
    foreach ($lista as [$peso, $valor]) {
        if (($r -= $peso) <= 0) {
            return $valor;
        }
    }
    return $lista[0][1];
};

$criados = [];
$total = 0;
$existentesCarteira = Banco::todos("SELECT whatsapp, razao_social, cidade, uf FROM clientes WHERE whatsapp IS NOT NULL AND tipo = 'cliente' ORDER BY RAND(2121) LIMIT 6");
for ($i = 0; $i < 72; $i++) {
    $quando = date('Y-m-d H:i:s', mt_rand(strtotime('-60 days 08:00'), strtotime('-2 hours')));
    $utm = $sortear($campanhas);
    $nome = $nomes[mt_rand(0, count($nomes) - 1)] . ' ' . $sobrenomes[mt_rand(0, count($sobrenomes) - 1)];
    [$cidade, $uf] = $cidades[mt_rand(0, count($cidades) - 1)];
    $dados = $utm + [
        'nome' => $nome, 'empresa' => $lojas[mt_rand(0, count($lojas) - 1)] . ' ' . $cidade, 'cidade' => $cidade, 'uf' => $uf,
        'whatsapp' => (string) mt_rand(11, 99) . '9' . mt_rand(10000000, 99999999), 'email' => mt_rand(1, 3) === 1 ? preg_replace('/[^a-z]/', '', strtolower(iconv('UTF-8', 'ASCII//TRANSLIT', explode(' ', $nome)[0]))) . mt_rand(10, 99) . '@exemplo.com.br' : '',
        'mensagem' => $mensagens[mt_rand(0, count($mensagens) - 1)], 'pagina' => mt_rand(0, 1) ? '/seja-revendedor' : 'https://www.exemplo-site.com.br/revenda',
    ];
    if ($i % 12 === 5 && $existentesCarteira) { // cliente que já está na carteira preencheu de novo
        $c = array_pop($existentesCarteira);
        $dados = ['whatsapp' => $c['whatsapp'], 'empresa' => $c['razao_social'], 'cidade' => $c['cidade'], 'uf' => $c['uf']] + $dados;
    }
    if ($i % 17 === 3) {
        $dados['site_url'] = 'http://spam.exemplo'; // robô (honeypot)
    }
    $r = LeadService::receber($dados, '10.0.' . mt_rand(0, 255) . '.' . mt_rand(1, 254), $quando);
    if ($r['resultado'] === 'criado') {
        $criados[] = [$r['cliente_id'], $quando];
    }
    $total++;
}

// Parte dos leads novos (os mais antigos) virou cliente: primeiro pedido faturado
$lojista = (int) Banco::valor("SELECT id FROM tabelas_preco WHERE slug = 'lojista'");
$produtos = Banco::todos('SELECT p.id, p.nome, pp.preco FROM produtos p JOIN produto_precos pp ON pp.produto_id = p.id AND pp.tabela_preco_id = ? WHERE p.ativo = 1 ORDER BY p.id LIMIT 40', [$lojista]);
$forma = Banco::um('SELECT f.id AS forma, c.id AS condicao FROM formas_pagamento f JOIN condicoes_pagamento c ON c.forma_pagamento_id = f.id WHERE c.valor_minimo = 0 ORDER BY f.id LIMIT 1');
$convertidos = 0;
usort($criados, fn($a, $b) => strcmp($a[1], $b[1]));
foreach ($criados as $k => [$id, $quando]) {
    if (!$produtos || !$forma || $k % 3 !== 0 || strtotime($quando) > strtotime('-10 days')) {
        continue;
    }
    $c = Banco::um('SELECT * FROM clientes WHERE id = ?', [$id]);
    $data = date('Y-m-d', strtotime($quando . ' +' . mt_rand(3, 9) . ' days'));
    $subtotal = 0;
    $itens = [];
    foreach ((array) array_rand($produtos, 4) as $i) {
        $q = mt_rand(6, 18);
        $itens[] = [$produtos[$i], $q];
        $subtotal += $q * (float) $produtos[$i]['preco'];
    }
    $pid = Banco::inserir('pedidos', ['uuid' => PedidoService::novoUuid(), 'cliente_id' => $id, 'vendedor_id' => $c['responsavel_id'], 'origem' => 'sistema',
        'tabela_preco_id' => $lojista, 'forma_pagamento_id' => $forma['forma'], 'condicao_id' => $forma['condicao'], 'subtotal' => round($subtotal, 2), 'desconto' => 0,
        'total' => round($subtotal, 2), 'situacao' => 'faturado', 'omie_codigo' => 'LEAD-' . mt_rand(100000, 999999), 'numero_nf' => (string) mt_rand(60000, 69999),
        'faturado_em' => "$data 15:00:00", 'aprovado_em' => "$data 09:00:00", 'criado_em' => "$data 08:30:00"]);
    foreach ($itens as [$p, $q]) {
        Banco::inserir('pedido_itens', ['pedido_id' => $pid, 'produto_id' => $p['id'], 'nome' => $p['nome'], 'quantidade' => $q, 'preco_unitario' => $p['preco'], 'total' => round($q * (float) $p['preco'], 2)]);
    }
    Banco::executar('UPDATE clientes SET contatado = 1, ultimo_contato_em = ? WHERE id = ?', [$data, $id]);
    PedidoService::atualizarCompras($id); // vira cliente, com a data da compra
    $convertidos++;
}
// Os demais: metade já foi abordada pelo vendedor
foreach ($criados as $k => [$id]) {
    if ($k % 2 === 1 && strtotime($criados[$k][1]) < strtotime('-3 days')) {
        Banco::executar("UPDATE clientes SET contatado = 1, ultimo_contato_em = DATE(?) + INTERVAL 1 DAY WHERE id = ? AND tipo = 'lead'", [$criados[$k][1], $id]);
    }
}

return "Leads (demonstração): $total formulários recebidos, " . count($criados) . " leads novos, $convertidos viraram clientes.";
