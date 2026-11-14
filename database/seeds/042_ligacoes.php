<?php
/**
 * Ligações e Acompanhamento — DADOS ESSENCIAIS (instalação e atualizações; idempotente).
 *  - Configuração "telefonia.modo": manual (registrar com 1 clique) | simulado (demonstração) | api (Ligue Talk real).
 *  - Permissão nova "acompanhamento.ver" (Gestor). A gestora do setor vê a equipe dela.
 *  - Controle da sincronização das ligações/gravações (a API v1 do Ligue Talk é por relatórios, sem webhook).
 */
use App\Core\Banco;

$configs = [
    ['telefonia.modo', 'manual', 'texto', 'Ligue Talk', 'Modo das ligações', 'manual = registrar com 1 clique · simulado = demonstração (ligações e gravações de exemplo) · api = Ligue Talk real (company_id e token no config.php)', 10],
    ['telefonia.campos_recebidos', '', 'texto', 'Ligue Talk', 'Campos recebidos do Ligue Talk (só leitura)', 'Preenchido sozinho na sincronização: os nomes dos campos que a API devolveu (sem dados). Ajuda a conferir a integração.', 30],
    ['telefonia.ultima_sincronizacao', '', 'texto', 'Ligue Talk', 'Última sincronização das ligações', 'Preenchido sozinho (data e hora).', 40],
];
foreach ($configs as [$chave, $valor, $tipo, $grupo, $rotulo, $descricao, $ordem]) {
    if (!Banco::valor('SELECT 1 FROM configuracoes WHERE chave = ?', [$chave])) {
        Banco::inserir('configuracoes', compact('chave', 'valor', 'tipo', 'grupo', 'rotulo', 'descricao', 'ordem'));
    }
}
Banco::executar("INSERT IGNORE INTO perfil_permissoes (perfil_id, permissao) SELECT id, 'acompanhamento.ver' FROM perfis WHERE slug = 'gestor'");
return null;
