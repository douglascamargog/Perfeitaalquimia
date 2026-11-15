<?php
/**
 * DEMONSTRAÇÃO das Compras da empresa (só na instalação nova):
 *  - itens de uso e consumo (escritório, limpeza, envio) com compras dos últimos 12 meses (recorrência e "compra a cada");
 *  - um segundo fornecedor em algumas matérias-primas (comparar preço);
 *  - notas de entrada simuladas do Omie (storage/omie_fake/notas_entrada.json): duas com itens já ligados e uma com um item novo para ligar.
 */
use App\Core\Banco;
use App\Services\CompraLancamentoService as L;

if (Banco::valor('SELECT 1 FROM compras_lancadas LIMIT 1')) {
    return null;
}
$fin = (int) Banco::valor("SELECT id FROM usuarios WHERE email = 'financeiro@demo.local'") ?: null;
$itens = [
    // nome, unidade, categoria, fornecedor, preço, a cada (dias), quantidade
    ['Papel A4 (resma)', 'un', 'Escritório', 'Papelaria Central', 27.90, 30, 10],
    ['Toner da impressora', 'un', 'Escritório', 'Info Suprimentos', 189.00, 75, 1],
    ['Detergente neutro 5 L', 'un', 'Limpeza', 'Limpa Tudo Distribuidora', 32.50, 21, 4],
    ['Saco de lixo 100 L (pacote)', 'un', 'Limpeza', 'Limpa Tudo Distribuidora', 18.90, 28, 6],
    ['Caixa de papelão para envio', 'un', 'Embalagem de envio', 'Caixas Sul', 2.35, 14, 300],
    ['Fita adesiva transparente', 'un', 'Embalagem de envio', 'Caixas Sul', 6.80, 20, 24],
    ['Café em grão 1 kg', 'un', 'Copa e cozinha', 'Atacado do Café', 54.00, 15, 3],
];
foreach ($itens as $n => [$nome, $un, $cat, $forn, $preco, $cada, $qtd]) {
    $id = Banco::inserir('materias_primas', ['nome' => $nome, 'unidade' => $un, 'tipo' => 'consumo', 'categoria' => $cat, 'fornecedor' => $forn,
        'custo_unitario' => $preco, 'estoque_minimo' => max(1, round($qtd / 2)), 'estoque' => 0, 'ativo' => 1]);
    Banco::inserir('mp_fornecedores', ['materia_prima_id' => $id, 'fornecedor' => $forn, 'ultimo_preco' => $preco, 'principal' => 1]);
    // Compras dos últimos 12 meses no ritmo do item (com pequenas variações de dia e preço)
    for ($dias = 360 - $n * 3; $dias > 3; $dias -= $cada + ($dias % 3) - 1) {
        $data = date('Y-m-d', strtotime("-$dias days"));
        $p = round($preco * (1 + (($dias % 7) - 3) / 100), 2);
        L::lancar(['fornecedor' => $forn, 'numero_nf' => (string) (10000 + $dias * 7 + $n), 'emissao' => $data, 'origem' => 'manual'],
            [['materia_prima_id' => $id, 'quantidade' => $qtd, 'valor_unitario' => $p]], null, $fin, "$data 10:00:00");
        // o que foi usado entre uma compra e outra (uso e consumo baixa por ajuste de inventário)
        $fim = date('Y-m-d', strtotime("$data +" . max(1, $cada - 2) . ' days'));
        if ($fim < date('Y-m-d')) {
            $atual = (float) Banco::valor('SELECT estoque FROM materias_primas WHERE id = ?', [$id]);
            \App\Services\ProducaoService::ajustar($id, max(0, round($atual - $qtd * .9, 3)), 'Uso do período (contagem)', $fin, "$fim 18:00:00");
        }
    }
}

// Segundo fornecedor (mais caro) nas 3 primeiras matérias-primas com fornecedor principal
foreach (Banco::todos("SELECT m.id, m.custo_unitario FROM materias_primas m JOIN mp_fornecedores f ON f.materia_prima_id = m.id AND f.principal = 1
    WHERE m.tipo = 'materia_prima' AND m.custo_unitario > 0 ORDER BY m.id LIMIT 3") as $m) {
    Banco::inserir('mp_fornecedores', ['materia_prima_id' => $m['id'], 'fornecedor' => 'Insumos Brasil (cotação)', 'ultimo_preco' => round($m['custo_unitario'] * 1.12, 4), 'prazo_dias' => 12]);
}

// Notas de entrada simuladas do Omie: duas matérias-primas ligadas por código + um item novo (sem ligação)
$mps = Banco::todos("SELECT id, nome, custo_unitario FROM materias_primas WHERE tipo = 'materia_prima' AND ativo = 1 ORDER BY id LIMIT 2");
foreach ($mps as $i => $m) {
    Banco::executar('UPDATE materias_primas SET omie_codigo = ? WHERE id = ?', ['OMIE-MP-' . (100 + $i), $m['id']]);
}
$notas = [];
foreach ($mps as $i => $m) {
    $notas[] = ['codigo' => 'NFE-DEMO-' . (1 + $i), 'numero' => (string) (55120 + $i), 'serie' => '1', 'fornecedor' => 'Química Fina do Sul', 'cnpj' => '12.345.678/0001-90',
        'emissao' => date('Y-m-d', strtotime('-' . (2 - $i) . ' days')),
        'itens' => [['codigo' => 'OMIE-MP-' . (100 + $i), 'descricao' => $m['nome'], 'quantidade' => 25, 'valor_unitario' => max(1, (float) $m['custo_unitario'])]]];
}
$notas[] = ['codigo' => 'NFE-DEMO-3', 'numero' => '7781', 'serie' => '1', 'fornecedor' => 'Escritório & Cia', 'cnpj' => '98.765.432/0001-10', 'emissao' => date('Y-m-d', strtotime('-1 day')),
    'itens' => [['codigo' => 'OMIE-ESC-55', 'descricao' => 'Caneta esferográfica azul (caixa)', 'quantidade' => 5, 'valor_unitario' => 24.9]]];
if (!is_dir(RAIZ . '/storage/omie_fake')) {
    mkdir(RAIZ . '/storage/omie_fake', 0775, true);
}
file_put_contents(RAIZ . '/storage/omie_fake/notas_entrada.json', json_encode($notas, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
return 'Compras da empresa (demonstração): 7 itens de uso e consumo com 12 meses de compras e 3 notas do Omie para buscar.';
