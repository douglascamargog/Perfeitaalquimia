<?php
/**
 * DEMONSTRAÇÃO da Produção (só na instalação nova; "_demo" no nome = nunca roda em atualização).
 *
 * Cria ~26 matérias-primas e fichas técnicas de 15 produtos e SIMULA 90 dias de fábrica,
 * usando as mesmas regras do sistema (ProducaoService):
 *   - compras quando a matéria-prima ia ficar abaixo do mínimo;
 *   - ordens de produção quando o produto acabado ficava abaixo do mínimo;
 *   - baixa do acabado pelas vendas faturadas (pedidos da demonstração).
 * Para o painel ter alertas: 3 matérias-primas ficaram sem compra no último mês e 2 produtos sem produção.
 * Valores e fornecedores são fictícios.
 */
use App\Core\Banco;
use App\Services\ProducaoService as P;

mt_srand(818);
$usuario = (int) (Banco::valor("SELECT u.id FROM usuarios u JOIN perfis p ON p.id = u.perfil_id WHERE p.slug = 'producao' ORDER BY u.id LIMIT 1") ?: 1);

// [nome, código, unidade, custo por unidade (R$), fornecedor]
$materias = [
    ['Cápsula vegetal nº 0', 'MP-001', 'un', 0.018, 'Encapsula Sul (fictício)'],
    ['Gelatina para softgel', 'MP-002', 'kg', 38.00, 'Encapsula Sul (fictício)'],
    ['Pote PET 150 ml com tampa', 'MP-003', 'un', 0.95, 'Embalagens Serra (fictício)'],
    ['Rótulo adesivo', 'MP-004', 'un', 0.22, 'Gráfica Pampa (fictício)'],
    ['Lacre de indução', 'MP-005', 'un', 0.06, 'Embalagens Serra (fictício)'],
    ['Óleo de prímula', 'MP-010', 'kg', 210.00, 'Óleos Naturais BR (fictício)'],
    ['Glucomannan em pó', 'MP-011', 'kg', 95.00, 'Insumos Vida (fictício)'],
    ['Psyllium em pó', 'MP-012', 'kg', 62.00, 'Insumos Vida (fictício)'],
    ['Carvão vegetal ativado', 'MP-013', 'kg', 48.00, 'Insumos Vida (fictício)'],
    ['Cúrcuma em pó', 'MP-014', 'kg', 54.00, 'Especiarias do Vale (fictício)'],
    ['Guaraná em pó', 'MP-015', 'kg', 72.00, 'Especiarias do Vale (fictício)'],
    ['Magnésio dimalato', 'MP-016', 'kg', 135.00, 'Química Fina RS (fictício)'],
    ['Citrato de magnésio', 'MP-017', 'kg', 88.00, 'Química Fina RS (fictício)'],
    ['Vitamina E oleosa', 'MP-018', 'kg', 320.00, 'Química Fina RS (fictício)'],
    ['Óleo de peixe (ômega 3)', 'MP-019', 'kg', 115.00, 'Óleos Naturais BR (fictício)'],
    ['Mix 30 ervas desidratadas', 'MP-020', 'kg', 41.00, 'Ervas da Serra (fictício)'],
    ['Gengibre em pó', 'MP-021', 'kg', 39.00, 'Especiarias do Vale (fictício)'],
    ['Chá verde em folhas', 'MP-022', 'kg', 58.00, 'Ervas da Serra (fictício)'],
    ['Embalagem kraft 120 g', 'MP-030', 'un', 0.70, 'Embalagens Serra (fictício)'],
    ['Embalagem kraft 20 g', 'MP-031', 'un', 0.38, 'Embalagens Serra (fictício)'],
    ['Base líquida (água purificada + conservante)', 'MP-040', 'L', 6.50, 'Química Fina RS (fictício)'],
    ['Colágeno Verisol', 'MP-041', 'kg', 290.00, 'Insumos Vida (fictício)'],
    ['Ácido hialurônico', 'MP-042', 'g', 2.40, 'Química Fina RS (fictício)'],
    ['Vitamina B12 (cianocobalamina)', 'MP-043', 'g', 9.80, 'Química Fina RS (fictício)'],
    ['Frasco conta-gotas 30 ml', 'MP-044', 'un', 1.35, 'Embalagens Serra (fictício)'],
    ['Caixa de embarque', 'MP-050', 'un', 2.10, 'Embalagens Serra (fictício)'],
];
$capsula = ['Cápsula vegetal nº 0' => 60, 'Pote PET 150 ml com tampa' => 1, 'Rótulo adesivo' => 1, 'Lacre de indução' => 1];
$softgel = ['Pote PET 150 ml com tampa' => 1, 'Rótulo adesivo' => 1, 'Lacre de indução' => 1];
$gotas = ['Frasco conta-gotas 30 ml' => 1, 'Rótulo adesivo' => 1, 'Base líquida (água purificada + conservante)' => 0.03];
// Produto (busca pelo nome) => ficha por unidade
$fichas = [
    'Óleo de Prímula - 60'      => $softgel + ['Óleo de prímula' => 0.030, 'Gelatina para softgel' => 0.015],
    'Glucomannan 60'            => $capsula + ['Glucomannan em pó' => 0.030],
    'Psyllium 60'               => $capsula + ['Psyllium em pó' => 0.030],
    'Carvão Vegetal - 60'       => $capsula + ['Carvão vegetal ativado' => 0.024],
    'Cúrcuma - 60'              => $capsula + ['Cúrcuma em pó' => 0.030],
    'Guaraná - 60'              => $capsula + ['Guaraná em pó' => 0.030],
    'Magnésio Dimalato - 60'    => $capsula + ['Magnésio dimalato' => 0.036],
    'Citrato de Magnésio - 60'  => $capsula + ['Citrato de magnésio' => 0.036],
    'Vitamina E - 60'           => $softgel + ['Vitamina E oleosa' => 0.012, 'Gelatina para softgel' => 0.015],
    'Ômega 3'                   => $softgel + ['Óleo de peixe (ômega 3)' => 0.060, 'Gelatina para softgel' => 0.020],
    'Chá misto 30 ervas'        => ['Mix 30 ervas desidratadas' => 0.120, 'Embalagem kraft 120 g' => 1, 'Rótulo adesivo' => 1],
    'Gengibre pó'               => ['Gengibre em pó' => 0.020, 'Embalagem kraft 20 g' => 1, 'Rótulo adesivo' => 1],
    'Chá verde'                 => ['Chá verde em folhas' => 0.020, 'Embalagem kraft 20 g' => 1, 'Rótulo adesivo' => 1],
    'Colágeno Verisol + Ácido Hialurônico - 30' => $gotas + ['Colágeno Verisol' => 0.005, 'Ácido hialurônico' => 0.2],
    'Vitamina B12 - 30'         => $gotas + ['Vitamina B12 (cianocobalamina)' => 0.05],
];
$semCompraNoFim = ['Psyllium em pó', 'Óleo de peixe (ômega 3)', 'Gengibre em pó']; // ficam abaixo do mínimo
$semProducaoNoFim = ['Cúrcuma - 60', 'Chá verde'];                                   // ficam para reabastecer

// ---------- Cadastros ----------
$mpId = [];
foreach ($materias as [$nome, $codigo, $unidade, $custo, $fornecedor]) {
    $mpId[$nome] = P::salvarMateria(null, ['nome' => $nome, 'codigo' => $codigo, 'unidade' => $unidade, 'custo_unitario' => (string) $custo,
        'fornecedor' => $fornecedor, 'ativo' => 1, 'estoque_minimo' => '0'], $usuario);
}
$produtos = [];
foreach ($fichas as $busca => $linhas) {
    // Nome que começa com a busca primeiro; entre parecidos, o que mais vendeu nos últimos 90 dias
    $pid = (int) Banco::valor("SELECT p.id FROM produtos p
        LEFT JOIN pedido_itens i ON i.produto_id = p.id LEFT JOIN pedidos pe ON pe.id = i.pedido_id AND pe.situacao = 'faturado' AND pe.faturado_em >= NOW() - INTERVAL 90 DAY
        WHERE p.nome LIKE ? AND p.ativo = 1 GROUP BY p.id
        ORDER BY p.nome LIKE ? DESC, SUM(CASE WHEN pe.id IS NOT NULL THEN i.quantidade ELSE 0 END) DESC LIMIT 1", ["%$busca%", "$busca%"]);
    if (!$pid || isset($produtos[$pid])) {
        continue;
    }
    P::salvarFicha($pid, array_combine(array_map(fn($n) => $mpId[$n], array_keys($linhas)), array_values($linhas)));
    $vendido = (int) Banco::valor("SELECT COALESCE(SUM(i.quantidade), 0) FROM pedido_itens i JOIN pedidos p ON p.id = i.pedido_id
        WHERE i.produto_id = ? AND p.situacao = 'faturado' AND p.faturado_em >= NOW() - INTERVAL 90 DAY", [$pid]);
    $vendaDia = max(0.3, $vendido / 90);
    $produtos[$pid] = ['busca' => $busca, 'venda_dia' => $vendaDia, 'minimo' => max(10, (int) ceil($vendaDia * 15)),
        'lote' => max(20, (int) (ceil($vendaDia * 25 / 10) * 10))];
}

// Mínimo e lote de compra de cada matéria-prima pelo consumo esperado (≈ 15 dias e ≈ 35 dias)
$arredonda = fn(float $v) => $v >= 1000 ? ceil($v / 500) * 500 : ($v >= 50 ? ceil($v / 10) * 10 : ($v >= 5 ? ceil($v) : ceil($v * 10) / 10));
$consumoDia = array_fill_keys(array_keys($mpId), 0.0);
foreach ($produtos as $pid => $p) {
    foreach (P::ficha($pid) as $l) {
        $consumoDia[$l['nome']] += (float) $l['quantidade'] * $p['venda_dia'];
    }
}
$compra = [];
foreach ($mpId as $nome => $id) {
    $dia = $consumoDia[$nome] ?: 0.5; // caixa de embarque: uso fora da ficha
    $minimo = $arredonda($dia * 15);
    $compra[$nome] = $arredonda($dia * 35);
    Banco::executar('UPDATE materias_primas SET estoque_minimo = ? WHERE id = ?', [$minimo, $id]);
    $custo = (float) Banco::valor('SELECT custo_unitario FROM materias_primas WHERE id = ?', [$id]);
    P::entrada($id, $compra[$nome] * 1.2, $custo, (string) mt_rand(20000, 29999), 'Estoque inicial (demonstração)', $usuario, date('Y-m-d 08:00:00', strtotime('-95 days')));
}

// ---------- Simulação dos últimos 92 dias ----------
$produzir = function (int $pid, int $qtd, string $quando, bool $fimDoPeriodo) use ($usuario, $mpId, $compra, $semCompraNoFim) {
    // Compra o que vai faltar (ou ficar abaixo do mínimo) antes de produzir
    foreach (P::previa($pid, $qtd)['linhas'] as $l) {
        $mp = P::materia($l['materia_prima_id']);
        if ($mp['estoque'] - $l['precisa'] < $mp['estoque_minimo'] && !($fimDoPeriodo && in_array($l['nome'], $semCompraNoFim, true))) {
            $vezes = max(1, (int) ceil(($l['precisa'] + $mp['estoque_minimo'] - $mp['estoque']) / $compra[$l['nome']]));
            $custo = round((float) $mp['custo_unitario'] * (0.95 + mt_rand(0, 12) / 100), 4); // o preço varia um pouco
            P::entrada($l['materia_prima_id'], $compra[$l['nome']] * $vezes, $custo, (string) mt_rand(30000, 99999), '', $usuario, date('Y-m-d 08:30:00', strtotime($quando . ' -2 days')));
        }
    }
    if (!P::previa($pid, $qtd)['ok']) {
        return false; // faltou matéria-prima: não produz (produto fica para reabastecer)
    }
    $op = P::criarOrdem(['produto_id' => $pid, 'quantidade' => $qtd, 'lote' => 'L' . date('ymd', strtotime($quando)) . '-' . $pid,
        'validade' => date('Y-m-d', strtotime($quando . ' +24 months'))], $usuario, 'manual', date('Y-m-d 07:30:00', strtotime($quando)));
    P::concluirOrdem($op, $usuario, null, date('Y-m-d 16:00:00', strtotime($quando)));
    return true;
};

// Produção inicial (antes das vendas do período)
foreach ($produtos as $pid => $p) {
    $produzir($pid, $p['lote'] + $p['minimo'], date('Y-m-d', strtotime('-92 days')), false);
    P::definirMinimo($pid, $p['minimo']);
}
$ordens = 0;
for ($d = 90; $d >= 1; $d--) {
    $dia = date('Y-m-d', strtotime("-$d days"));
    // Vendas faturadas do dia baixam o acabado
    foreach (Banco::todos("SELECT id, faturado_em FROM pedidos WHERE situacao = 'faturado' AND DATE(faturado_em) = ?", [$dia]) as $ped) {
        P::baixarVenda((int) $ped['id'], $ped['faturado_em']);
    }
    if (in_array((int) date('N', strtotime($dia)), [6, 7], true)) {
        continue; // fábrica não produz no fim de semana
    }
    foreach ($produtos as $pid => $p) {
        $estoque = (int) Banco::valor('SELECT quantidade FROM estoque_acabado WHERE produto_id = ?', [$pid]);
        if ($estoque >= $p['minimo'] || ($d <= 20 && in_array($p['busca'], $semProducaoNoFim, true))) {
            continue;
        }
        $ordens += $produzir($pid, $p['lote'], $dia, $d <= 30) ? 1 : 0;
    }
}

// A demonstração sempre mostra ao menos um produto para reabastecer (as vendas simuladas variam com a data)
if (!P::reabastecer() && $produtos) {
    $pid = array_key_first($produtos);
    P::ajustarAcabado($pid, intdiv($produtos[$pid]['minimo'], 2), 'Contagem de inventário (demonstração)', $usuario);
}

// Perda encontrada na contagem de inventário (deixa as matérias-primas "sem compra" abaixo do mínimo)
foreach ($semCompraNoFim as $nome) {
    $mp = P::materia($mpId[$nome]);
    if ($mp['estoque'] >= $mp['estoque_minimo']) {
        P::ajustar($mpId[$nome], round($mp['estoque_minimo'] * 0.55, 3), 'Contagem de inventário: perda por umidade em parte do lote', $usuario, date('Y-m-d 17:00:00', strtotime('-3 days')));
    }
}

// Ordens planejadas para os próximos dias (uma de cada origem)
$ids = array_keys($produtos);
foreach ([[$ids[1], 3, 'manual'], [$ids[8], 5, 'manual']] as [$pid, $emDias, $origem]) {
    P::criarOrdem(['produto_id' => $pid, 'quantidade' => $produtos[$pid]['lote'], 'previsto_para' => date('Y-m-d', strtotime("+$emDias weekdays")),
        'lote' => 'L' . date('ymd', strtotime("+$emDias weekdays")) . '-' . $pid, 'observacoes' => 'Programação da semana.'], $usuario, $origem);
}

return 'Produção (demonstração): ' . count($mpId) . ' matérias-primas, ' . count($produtos) . " fichas técnicas e $ordens ordens concluídas em 90 dias.";
