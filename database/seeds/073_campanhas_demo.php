<?php
/**
 * Campanhas comerciais (V12.0) — DEMONSTRAÇÃO (só instalação nova):
 *  - campanha do mês (todos): 10% acima de R$ 2.000, frete grátis acima de R$ 5.000, bicicleta acima de R$ 10.000
 *    e 6 produtos em promoção (3 em destaque);
 *  - campanha da semana (só Distribuidor): 15% acima de R$ 8.000.
 */
use App\Core\Banco;

if (Banco::valor('SELECT 1 FROM campanhas LIMIT 1')) {
    return 'Já há campanhas.';
}
$admin = (int) Banco::valor("SELECT u.id FROM usuarios u JOIN perfis p ON p.id = u.perfil_id WHERE p.slug = 'admin' ORDER BY u.id LIMIT 1") ?: null;
$meses = [1 => 'janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
$mes = $meses[(int) date('n')];

$id = Banco::inserir('campanhas', [
    'nome' => "Campanha de $mes", 'titulo' => 'Ofertas de ' . $mes, 'chamada' => 'Quanto mais você leva, mais ganha: desconto, frete grátis e até uma bicicleta para a sua loja.',
    'selo' => 'até 10% OFF', 'inicio' => date('Y-m-01'), 'fim' => date('Y-m-t'), 'ativa' => 1, 'publico' => 'todos', 'soma_desconto_vendedor' => 1,
    'tema' => 'claro', 'criado_por' => $admin,
]);
foreach ([[2000, 'desconto', 10, null], [5000, 'frete', 0, null], [10000, 'brinde', 0, 'Bicicleta']] as [$valor, $tipo, $pct, $brinde]) {
    Banco::inserir('campanha_faixas', ['campanha_id' => $id, 'a_partir_de' => $valor, 'tipo' => $tipo, 'desconto_pct' => $pct, 'brinde' => $brinde]);
}
$produtos = Banco::todos('SELECT p.id FROM produtos p JOIN produto_precos pp ON pp.produto_id = p.id
    WHERE p.ativo = 1 AND p.visivel_catalogo = 1 AND p.imagem_id IS NOT NULL GROUP BY p.id ORDER BY p.ordem, p.nome LIMIT 6');
foreach ($produtos as $n => $p) {
    Banco::inserir('campanha_produtos', ['campanha_id' => $id, 'produto_id' => $p['id'], 'desconto_pct' => [20, 15, 12, 10, 10, 8][$n], 'destaque' => $n < 3 ? 1 : 0]);
}

$distribuidor = Banco::valor("SELECT id FROM tabelas_preco WHERE nome LIKE 'Distribuidor%' LIMIT 1");
if ($distribuidor) {
    $segunda = date('Y-m-d', strtotime('monday this week'));
    $s = Banco::inserir('campanhas', [
        'nome' => 'Semana do distribuidor', 'titulo' => 'Semana do distribuidor', 'chamada' => 'Só esta semana: 15% acima de R$ 8.000.',
        'selo' => '15% OFF', 'inicio' => $segunda, 'fim' => date('Y-m-d', strtotime("$segunda +6 day")), 'ativa' => 1,
        'publico' => 'tabela', 'publico_valores' => (string) $distribuidor, 'soma_desconto_vendedor' => 0, 'tema' => 'claro', 'ordem' => 1, 'criado_por' => $admin,
    ]);
    Banco::inserir('campanha_faixas', ['campanha_id' => $s, 'a_partir_de' => 8000, 'tipo' => 'desconto', 'desconto_pct' => 15]);
}
return 'Campanhas de exemplo: do mês (todos) e da semana (Distribuidor).';
