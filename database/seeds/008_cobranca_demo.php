<?php
/**
 * DEMONSTRAÇÃO da Cobrança (só na instalação nova; "_demo" no nome = nunca roda em atualização).
 * - O usuário demo do Financeiro passa a ter também a função Cobrança.
 * - Lê os títulos simulados do OmieFake e espalha os casos pelo funil (tentativas, sem contato,
 *   negociação, promessas, um pago e um acordo) para a tela ficar parecida com o uso real.
 */
use App\Core\Banco;
use App\Services\CobrancaService;

$financeiro = Banco::valor("SELECT u.id FROM usuarios u JOIN perfis p ON p.id = u.perfil_id WHERE p.slug = 'financeiro' ORDER BY u.id LIMIT 1");
$funcao = Banco::valor("SELECT id FROM funcoes WHERE slug = 'cobranca'");
if ($financeiro && $funcao) {
    Banco::executar('INSERT IGNORE INTO usuario_funcoes (usuario_id, funcao_id) VALUES (?, ?)', [$financeiro, $funcao]);
}

$r = CobrancaService::importarDoErp(null);
$casos = Banco::todos("SELECT id FROM cobrancas WHERE situacao = 'aberta' ORDER BY vencimento_mais_antigo DESC, id");
$etapa = fn(string $tipo) => (int) (CobrancaService::etapaDoTipo($tipo)['id'] ?? 0);
$quem = (int) ($financeiro ?: 1);

foreach ($casos as $i => ['id' => $id]) {
    $id = (int) $id;
    switch ($i % 8) {
        case 1: // uma tentativa sem sucesso
            CobrancaService::registrarTentativa($id, 'Não atendeu', 'Ligação', '', $quem);
            break;
        case 2: // três tentativas: vai para "Sem contato — buscar contatos"
            foreach (['Não atendeu', 'Caixa postal', 'Mensagem sem resposta'] as $resultado) {
                CobrancaService::registrarTentativa($id, $resultado, $resultado === 'Mensagem sem resposta' ? 'WhatsApp' : 'Ligação', '', $quem);
            }
            break;
        case 3: // em negociação
            CobrancaService::registrarTentativa($id, 'Atendeu', 'Ligação', 'Pediu proposta de parcelamento', $quem);
            if ($etapa('aberta')) {
                $negociacao = Banco::valor("SELECT id FROM cobranca_etapas WHERE tipo = 'aberta' AND ativa = 1 ORDER BY ordem LIMIT 2, 1");
                $negociacao && CobrancaService::mudarEtapa($id, (int) $negociacao, $quem);
            }
            break;
        case 4: // promessa para hoje
            CobrancaService::registrarPromessa($id, date('Y-m-d'), 0, $quem);
            break;
        case 5: // promessa para daqui a alguns dias
            CobrancaService::registrarPromessa($id, date('Y-m-d', strtotime('+' . (2 + $i % 5) . ' days')), 0, $quem);
            break;
        case 6: // já recuperado (aparece em "Recuperado no mês")
            if ($i < 12 && $etapa('pago')) {
                CobrancaService::mudarEtapa($id, $etapa('pago'), $quem, 'Pagou por Pix');
            }
            break;
        case 7:
            if ($i < 16 && $etapa('acordo')) {
                CobrancaService::mudarEtapa($id, $etapa('acordo'), $quem, 'Acordo em 3 parcelas');
            }
            break;
    }
}

return sprintf('Cobrança (demonstração): %d casos criados a partir dos títulos simulados.', $r['casos']);
