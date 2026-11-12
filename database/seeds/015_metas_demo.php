<?php
/**
 * DEMONSTRAÇÃO das metas (só na instalação nova; "_demo" no nome = nunca roda em atualização).
 * Metas dos últimos 6 meses e do mês atual, por vendedor e por função, perto do faturado
 * (umas batidas, outras não), para o Painel e as Metas terem histórico.
 */
use App\Core\Banco;
use App\Services\VendasService;

mt_srand(515);
$criador = (int) (Banco::valor("SELECT u.id FROM usuarios u JOIN perfis p ON p.id = u.perfil_id WHERE p.slug = 'gestor' ORDER BY u.id LIMIT 1") ?: 1);
$alvos = [
    'vendedor' => array_column(Banco::todos('SELECT DISTINCT vendedor_id AS id FROM pedidos WHERE vendedor_id IS NOT NULL'), 'id'),
    'funcao'   => array_column(Banco::todos('SELECT DISTINCT c.funcao_id AS id FROM pedidos p JOIN clientes c ON c.id = p.cliente_id WHERE c.funcao_id IS NOT NULL'), 'id'),
];
$n = 0;
for ($i = 6; $i >= 0; $i--) {
    $mes = date('Y-m', strtotime(date('Y-m-01') . " -$i months"));
    $base = $i === 0 ? date('Y-m', strtotime(date('Y-m-01') . ' -1 month')) : $mes; // mês atual: meta a partir do mês passado
    foreach ($alvos as $tipo => $ids) {
        foreach ($ids as $id) {
            $vendido = VendasService::faturadoNoMes($base, $tipo, (int) $id)['valor'];
            if ($vendido <= 0) {
                continue;
            }
            $valor = max(1000, round($vendido * (0.85 + mt_rand(0, 35) / 100) / 500) * 500);
            Banco::executar('INSERT IGNORE INTO metas (mes, tipo, alvo_id, valor, criado_por) VALUES (?, ?, ?, ?, ?)', [$mes, $tipo, $id, $valor, $criador]);
            $n++;
        }
    }
}
return "Metas (demonstração): $n metas de vendedores e funções nos últimos 7 meses.";
