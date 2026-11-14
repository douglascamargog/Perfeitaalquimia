<?php
/**
 * DEMONSTRAÇÃO do Depósito e das Compras (só na instalação nova):
 *  - usuário "Depósito (demo)" (perfil Depósito, função Separação e Expedição);
 *  - fila: 5 pedidos faturados a separar (1 urgente), 1 separando e 1 despachado com lote e rastreio;
 *  - 4 orçamentos da Qualidade: esperando aprovação do Financeiro, montando (rótulo de projeto), aprovado (item avulso) e comprado esperando chegar.
 */
use App\Core\Banco;

if (Banco::valor('SELECT 1 FROM compras LIMIT 1') || Banco::valor('SELECT 1 FROM pedidos WHERE expedicao IS NOT NULL LIMIT 1')) {
    return null;
}
$perfil = (int) Banco::valor("SELECT id FROM perfis WHERE slug = 'deposito'");
$dep = (int) Banco::valor("SELECT id FROM usuarios WHERE email = 'deposito@demo.local'");
if (!$dep && $perfil) {
    $dep = Banco::inserir('usuarios', ['nome' => 'Depósito (demo)', 'email' => 'deposito@demo.local', 'senha_hash' => password_hash('Demo@2026', PASSWORD_DEFAULT), 'perfil_id' => $perfil]);
    Banco::executar("INSERT IGNORE INTO usuario_funcoes (usuario_id, funcao_id) SELECT ?, id FROM funcoes WHERE slug = 'deposito'", [$dep]);
}

// ---------- Fila do depósito: os pedidos faturados mais recentes ----------
$pedidos = array_column(Banco::todos("SELECT id FROM pedidos WHERE situacao = 'faturado' ORDER BY faturado_em DESC, id DESC LIMIT 7"), 'id');
foreach ($pedidos as $n => $id) {
    $desde = date('Y-m-d H:i:s', strtotime('-' . (6 - $n) * 9 . ' hours'));
    $etapa = $n === 0 ? 'despachado' : ($n === 1 ? 'separando' : 'aguardando');
    Banco::executar('UPDATE pedidos SET expedicao = ?, expedicao_desde = ?, expedicao_prioridade = ? WHERE id = ?', [$etapa, $desde, $n === 4 ? 1 : 0, $id]);
    if ($etapa === 'aguardando') {
        continue;
    }
    // Separando / despachado: itens separados com o lote da última ordem de produção do produto
    foreach (Banco::todos('SELECT id, produto_id, quantidade FROM pedido_itens WHERE pedido_id = ?', [$id]) as $i => $item) {
        $op = $item['produto_id'] ? Banco::um("SELECT lote, validade FROM ordens_producao WHERE produto_id = ? AND situacao = 'concluida' AND lote IS NOT NULL
            ORDER BY concluida_em DESC LIMIT 1", [$item['produto_id']]) : null;
        $lote = $op['lote'] ?? ('L' . date('ym') . str_pad((string) ($item['produto_id'] ?? 0), 3, '0', STR_PAD_LEFT));
        $val = $op['validade'] ?? date('Y-m-d', strtotime('+18 months'));
        $qtd = $etapa === 'separando' && $i > 0 ? null : (int) $item['quantidade'];
        Banco::executar('UPDATE pedido_itens SET qtd_separada = ? WHERE id = ?', [$qtd, $item['id']]);
        if ($qtd) {
            Banco::inserir('pedido_item_lotes', ['pedido_id' => $id, 'pedido_item_id' => $item['id'], 'produto_id' => $item['produto_id'],
                'lote' => $lote, 'validade' => $val, 'quantidade' => $qtd, 'criado_por' => $dep ?: null]);
        }
    }
    if ($etapa === 'separando') {
        Banco::executar('UPDATE pedidos SET separado_por = ? WHERE id = ?', [$dep ?: null, $id]);
    } else {
        Banco::executar("UPDATE pedidos SET separado_por = ?, separado_em = ?, despachado_por = ?, despachado_em = ?, transportadora = 'Correios', volumes = 2,
            peso_kg = 3.4, rastreio = 'QB123456789BR' WHERE id = ?", [$dep ?: null, $desde, $dep ?: null, date('Y-m-d H:i:s', strtotime($desde . ' +3 hours')), $id]);
        Banco::inserir('pedido_historico', ['pedido_id' => $id, 'situacao' => 'deposito', 'texto' => 'Despachado · Correios · 2 volume(s) · rastreio QB123456789BR', 'usuario_id' => $dep ?: null]);
    }
}

// ---------- Orçamentos da Qualidade ----------
$qual = (int) Banco::valor("SELECT id FROM usuarios WHERE email = 'qualidade@demo.local'") ?: null;
$fin = (int) Banco::valor("SELECT id FROM usuarios WHERE email = 'financeiro@demo.local'") ?: null;
$mps = Banco::todos("SELECT id, nome, unidade, custo_unitario FROM materias_primas WHERE tipo = 'materia_prima' AND ativo = 1 ORDER BY estoque / GREATEST(estoque_minimo, 1), id LIMIT 2");
$cotar = function (int $compra, string $fornecedor, float $unit, float $frete, int $prazo, string $condicao) {
    $q = (float) Banco::valor('SELECT quantidade FROM compras WHERE id = ?', [$compra]);
    return Banco::inserir('compra_cotacoes', ['compra_id' => $compra, 'fornecedor' => $fornecedor, 'valor_unitario' => $unit, 'frete' => $frete,
        'valor_total' => round($unit * $q + $frete, 2), 'prazo_dias' => $prazo, 'condicao' => $condicao]);
};
$hist = fn(int $compra, ?int $u, string $t) => Banco::inserir('compra_historico', ['compra_id' => $compra, 'usuario_id' => $u, 'texto' => $t]);

if (isset($mps[0])) { // 1) matéria-prima: esperando aprovação do Financeiro
    $m = $mps[0];
    $c = Banco::inserir('compras', ['tipo' => 'materia_prima', 'titulo' => $m['nome'], 'materia_prima_id' => $m['id'], 'quantidade' => 25, 'unidade' => $m['unidade'],
        'motivo' => 'Estoque abaixo do mínimo', 'necessario_ate' => date('Y-m-d', strtotime('+10 days')), 'solicitado_por' => $qual]);
    $base = max(1, (float) $m['custo_unitario']);
    $a = $cotar($c, 'Fornecedor Sul Insumos', round($base * 1.05, 2), 80, 7, 'boleto 28 dias');
    $cotar($c, 'Distribuidora Natural', round($base * 1.12, 2), 0, 5, 'boleto 28/42');
    Banco::atualizar('compras', $c, ['situacao' => 'aprovacao', 'cotacao_id' => $a, 'aprovador_id' => $fin, 'enviado_em' => date('Y-m-d H:i:s', strtotime('-3 hours'))]);
    $hist($c, $qual, 'Orçamento criado.');
    $hist($c, $qual, 'Enviado para aprovação do Financeiro.');
    if ($fin) {
        Banco::inserir('notificacoes', ['usuario_id' => $fin, 'tipo' => 'compra_aprovar', 'titulo' => 'Orçamento para aprovar: ' . $m['nome'], 'link' => '/compras/' . $c]);
    }
}
$projeto = Banco::um('SELECT id, nome FROM dp_projetos ORDER BY id LIMIT 1');
if ($projeto) { // 2) rótulo de um projeto: montando o orçamento
    $c = Banco::inserir('compras', ['tipo' => 'rotulo', 'titulo' => 'Rótulo ' . $projeto['nome'], 'dp_projeto_id' => $projeto['id'], 'quantidade' => 2000, 'unidade' => 'un',
        'motivo' => 'Produto novo (Desenvolvimento)', 'necessario_ate' => date('Y-m-d', strtotime('+25 days')), 'solicitado_por' => $qual]);
    $cotar($c, 'Gráfica Bom Rótulo', 0.38, 45, 12, 'boleto 30 dias');
    $hist($c, $qual, 'Orçamento criado.');
}
// 3) item avulso aprovado: falta comprar
$c = Banco::inserir('compras', ['tipo' => 'avulso', 'titulo' => 'Caixas de papelão para expedição', 'quantidade' => 300, 'unidade' => 'un',
    'motivo' => 'Estoque de caixas do depósito acabando', 'solicitado_por' => $qual]);
$a = $cotar($c, 'Embalagens RS', 2.10, 60, 4, 'Pix');
Banco::atualizar('compras', $c, ['situacao' => 'aprovado', 'cotacao_id' => $a, 'aprovador_id' => $fin, 'enviado_em' => date('Y-m-d H:i:s', strtotime('-2 days')),
    'decidido_em' => date('Y-m-d H:i:s', strtotime('-1 day'))]);
$hist($c, $qual, 'Orçamento criado.');
$hist($c, $fin, 'Aprovado.');
if (isset($mps[1])) { // 4) matéria-prima comprada, esperando chegar
    $m = $mps[1];
    $c = Banco::inserir('compras', ['tipo' => 'materia_prima', 'titulo' => $m['nome'], 'materia_prima_id' => $m['id'], 'quantidade' => 10, 'unidade' => $m['unidade'],
        'motivo' => 'Reposição', 'solicitado_por' => $qual]);
    $a = $cotar($c, 'Fornecedor Sul Insumos', max(1, round((float) $m['custo_unitario'], 2)), 50, 6, 'boleto 28 dias');
    Banco::atualizar('compras', $c, ['situacao' => 'comprado', 'cotacao_id' => $a, 'aprovador_id' => $fin, 'enviado_em' => date('Y-m-d H:i:s', strtotime('-5 days')),
        'decidido_em' => date('Y-m-d H:i:s', strtotime('-4 days')), 'comprado_em' => date('Y-m-d H:i:s', strtotime('-3 days')), 'previsto_em' => date('Y-m-d', strtotime('+2 days'))]);
    $hist($c, $qual, 'Compra feita.');
}
return 'Depósito e compras (demonstração): fila com ' . count($pedidos) . ' pedidos e 4 orçamentos.';
