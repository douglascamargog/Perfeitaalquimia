<?php
/**
 * E-commerce completo (V14.0) — DADOS ESSENCIAIS (idempotente): regras da cópia dos pedidos da Tray e dos alertas.
 */
use App\Core\Banco;

$configs = [
    ['ecommerce.meses_historico', '12', 'numero', 'Meses de histórico na primeira leitura da loja', 'Na primeira vez o One copia esse tanto de meses de pedidos (aos poucos, em várias rodadas).', 97],
    ['ecommerce.detalhes_por_rodada', '300', 'numero', 'Pedidos completos lidos por rodada', 'Cada pedido completo gasta 1 requisição (a Tray permite 10 mil por dia). O cron roda a cada 30 minutos.', 98],
    ['ecommerce.alerta_horas_nf', '24', 'numero', 'Alerta: pedido pago sem nota fiscal há (horas)', 'O painel do E-commerce avisa os pedidos pagos que estão sem nota há mais que esse tempo.', 99],
    ['ecommerce.sinc_ate', '', 'texto', 'Pedidos lidos até (interno)', 'Preenchido sozinho. Apague para o One ler o histórico de novo.', 100],
    ['ecommerce.ultimo_erro', '', 'texto', 'Último erro ao ler a loja (interno)', 'Preenchido sozinho.', 101],
];
foreach ($configs as [$chave, $valor, $tipo, $rotulo, $descricao, $ordem]) {
    if (!Banco::valor('SELECT 1 FROM configuracoes WHERE chave = ?', [$chave])) {
        Banco::inserir('configuracoes', ['chave' => $chave, 'valor' => $valor, 'tipo' => $tipo, 'grupo' => 'E-commerce (Tray)', 'rotulo' => $rotulo,
            'descricao' => $descricao, 'ordem' => $ordem]);
    }
}
// A descrição antiga falava do config.php
Banco::executar("UPDATE configuracoes SET descricao = 'fake = demonstração · tray = loja real (chaves em ⚙️ Administração › Tray)' WHERE chave = 'ecommerce.provedor'");
// Nomes de produtos dos pedidos já importados da Tray que vieram com HTML ("<br /><strong>Tamanho</strong> 02...")
foreach (Banco::todos("SELECT i.id, i.nome FROM pedido_itens i JOIN pedidos p ON p.id = i.pedido_id WHERE p.tipo_venda = 'ecommerce' AND (i.nome LIKE '%<%' OR i.nome LIKE '%Disponibilidade:%')") as $i) {
    Banco::executar('UPDATE pedido_itens SET nome = ? WHERE id = ?', [mb_substr(\App\Integrations\Tray\TrayTexto::nome($i['nome']), 0, 200), $i['id']]);
}
return null;
