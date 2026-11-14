<?php
/**
 * DEMONSTRAÇÃO do Acompanhamento (só na instalação nova): ligações dos últimos 30 dias de cada pessoa
 * dos setores de venda, com gravações de exemplo (bipes) e o contato na linha do tempo dos clientes.
 */
use App\Core\Banco;
use App\Integrations\LigueTalk\LigueTalkFake;

if (Banco::valor('SELECT 1 FROM ligacoes LIMIT 1')) {
    return null;
}
Banco::executar("UPDATE configuracoes SET valor = 'simulado' WHERE chave = 'telefonia.modo'"); // demonstração: ligações simuladas
mt_srand(4343);
$gravacoes = [LigueTalkFake::gerarGravacao('demo-1', 4), LigueTalkFake::gerarGravacao('demo-2', 6), LigueTalkFake::gerarGravacao('demo-3', 3)];
$pessoas = Banco::todos('SELECT DISTINCT u.id FROM usuarios u JOIN usuario_funcoes uf ON uf.usuario_id = u.id JOIN funcoes f ON f.id = uf.funcao_id WHERE f.setor_venda = 1 AND u.ativo = 1');
$total = 0;
foreach ($pessoas as ['id' => $uid]) {
    $clientes = Banco::todos("SELECT id, COALESCE(NULLIF(telefone, ''), whatsapp) AS tel FROM clientes WHERE responsavel_id = ? AND excluido_em IS NULL AND COALESCE(telefone, whatsapp) IS NOT NULL LIMIT 80", [$uid]);
    if (!$clientes) {
        continue;
    }
    $ritmo = mt_rand(4, 16); // cada pessoa com um ritmo diferente
    for ($d = 30; $d >= 0; $d--) {
        $dia = strtotime("-$d days");
        if ((int) date('N', $dia) >= 6) {
            continue;
        }
        for ($i = 0, $n = max(0, $ritmo + mt_rand(-3, 4)); $i < $n; $i++) {
            $c = $clientes[mt_rand(0, count($clientes) - 1)];
            $s = mt_rand(1, 100);
            $situacao = $s <= 62 ? 'atendida' : ($s <= 90 ? 'nao_atendida' : 'ocupado');
            $dur = $situacao === 'atendida' ? mt_rand(35, 480) : 0;
            $quando = date('Y-m-d', $dia) . sprintf(' %02d:%02d:00', mt_rand(8, 17), mt_rand(0, 59));
            if ($quando > date('Y-m-d H:i:s')) {
                continue;
            }
            Banco::inserir('ligacoes', ['id_externo' => 'demo-' . $uid . '-' . $d . '-' . $i, 'usuario_id' => $uid, 'cliente_id' => $c['id'],
                'numero' => preg_replace('/\D/', '', (string) $c['tel']), 'situacao' => $situacao, 'inicio' => $quando, 'duracao_seg' => $dur,
                'gravacao' => $situacao === 'atendida' ? $gravacoes[mt_rand(0, 2)] : null, 'origem' => 'demonstracao', 'criado_em' => $quando]);
            if ($situacao === 'atendida') {
                Banco::inserir('cliente_eventos', ['cliente_id' => $c['id'], 'usuario_id' => $uid, 'tipo' => 'ligacao',
                    'descricao' => 'Ligação pelo Ligue Talk: atendida (' . intdiv($dur, 60) . ' min)', 'ocorrido_em' => $quando]);
            }
            $total++;
        }
    }
}
// Reativações de exemplo: alguns clientes que compraram neste mês ficaram ~4 meses sem comprar antes disso
$reativados = 0;
foreach ($pessoas as ['id' => $uid]) {
    foreach (Banco::todos("SELECT id, cliente_id, faturado_em FROM pedidos WHERE vendedor_id = ? AND situacao = 'faturado' AND faturado_em >= ? ORDER BY id LIMIT 2",
        [$uid, date('Y-m-01')]) as $p) {
        $reativados += Banco::executar("UPDATE pedidos SET faturado_em = faturado_em - INTERVAL 130 DAY, criado_em = criado_em - INTERVAL 130 DAY
            WHERE cliente_id = ? AND id <> ? AND situacao = 'faturado' AND faturado_em < ? AND faturado_em >= ? - INTERVAL 130 DAY",
            [$p['cliente_id'], $p['id'], $p['faturado_em'], $p['faturado_em']]) ? 1 : 0;
    }
}
return "Acompanhamento (demonstração): $reativados reativações e $total ligações simuladas com gravações de exemplo.";
