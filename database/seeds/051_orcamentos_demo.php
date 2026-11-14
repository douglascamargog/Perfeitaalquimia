<?php
/**
 * DEMONSTRAÇÃO dos modelos de pedido (só na instalação nova):
 *  - campanha da gestão para todos os setores ("Campanha Black Friday", vale 30 dias);
 *  - modelo pessoal da Televendas 1 ("Primeiro pedido — loja pequena").
 */
use App\Core\Banco;

if (Banco::valor('SELECT 1 FROM pedido_modelos LIMIT 1')) {
    return null;
}
$gestor = (int) Banco::valor("SELECT id FROM usuarios WHERE email = 'gestor@demo.local'");
$vend = (int) Banco::valor("SELECT id FROM usuarios WHERE email = 'televendas1@demo.local'");
$produtos = array_map('intval', array_column(Banco::todos('SELECT p.id FROM produtos p JOIN produto_precos pp ON pp.produto_id = p.id
    AND pp.tabela_preco_id = (SELECT id FROM tabelas_preco WHERE ativa = 1 ORDER BY id LIMIT 1)
    WHERE p.ativo = 1 AND p.visivel_catalogo = 1 ORDER BY p.nome LIMIT 8'), 'id'));
if (!$gestor || !$vend || count($produtos) < 8) {
    return null;
}
$modelos = [
    ['Campanha Black Friday', 'Os mais vendidos com o kit de vitrine', 'campanha', null, date('Y-m-d', strtotime('+30 days')), $gestor, [0 => 24, 2 => 24, 4 => 12, 6 => 12]],
    ['Primeiro pedido — loja pequena', 'Sugestão para quem nunca comprou', 'pessoal', $vend, null, $vend, [1 => 6, 3 => 6, 5 => 6, 7 => 6]],
];
foreach ($modelos as [$nome, $descricao, $tipo, $dono, $validade, $autor, $itens]) {
    $id = Banco::inserir('pedido_modelos', array_filter(['nome' => $nome, 'descricao' => $descricao, 'tipo' => $tipo, 'dono_id' => $dono,
        'validade_ate' => $validade, 'criado_por' => $autor], fn($v) => $v !== null));
    foreach ($itens as $n => $q) {
        Banco::inserir('pedido_modelo_itens', ['modelo_id' => $id, 'produto_id' => $produtos[$n], 'quantidade' => $q]);
    }
}
return null;
