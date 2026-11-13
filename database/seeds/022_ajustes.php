<?php
/**
 * Ajustes depois da Fase 0 — DADOS ESSENCIAIS (instalação e atualizações; idempotente).
 *  1. Catálogo sem produtos: se o ERP é o simulado e ainda não há nenhum preço, carrega preços e estoque agora
 *     (antes só a demonstração ou a sincronização carregavam; em servidor atualizado o lojista via a vitrine vazia).
 *  2. Backup automático: quantos dias guardar.
 *  3. Mensagens da Recompra e da Cobrança passam a ser modelos em Mensagens prontas.
 *  4. Academia: aulas "Como usar o One" (no lugar das aulas do Leads2b, que ficam guardadas).
 */
use App\Core\Banco;
use App\Integrations\Integracoes;
use App\Services\ErpSync;

$resumo = [];

// ---------- 1. Preços e estoque iniciais ----------
if (Integracoes::erpSimulado() && !Banco::valor('SELECT 1 FROM produto_precos LIMIT 1')) {
    $resumo[] = ErpSync::precos() . ' preços e ' . ErpSync::estoque() . ' estoques carregados do ERP simulado';
}

// ---------- 2. Configurações novas ----------
$configuracoes = [
    ['backup.dias', '14', 'numero', 'Backup', 'Guardar os backups automáticos por (dias)', 'O cron cron/backup.php faz um backup do banco por dia e apaga os mais antigos que isso', 80],
];
foreach ($configuracoes as [$chave, $valor, $tipo, $grupo, $rotulo, $descricao, $ordem]) {
    if (!Banco::valor('SELECT 1 FROM configuracoes WHERE chave = ?', [$chave])) {
        Banco::inserir('configuracoes', compact('chave', 'valor', 'tipo', 'grupo', 'rotulo', 'descricao', 'ordem'));
    }
}

return $resumo ? implode(' · ', $resumo) : null;
