<?php
/**
 * Painel de vendas, metas e recompra — DADOS ESSENCIAIS (instalação e atualizações; idempotente).
 */
use App\Core\Banco;

$permissoes = [
    'painel.ver'      => ['gestor', 'financeiro'],
    'metas.gerenciar' => ['gestor'],
    'recompra.ver'    => ['gestor', 'vendedor'],
];
foreach ($permissoes as $permissao => $perfis) {
    foreach ($perfis as $perfil) {
        Banco::executar('INSERT IGNORE INTO perfil_permissoes (perfil_id, permissao) SELECT id, ? FROM perfis WHERE slug = ?', [$permissao, $perfil]);
    }
}

$configuracoes = [
    ['recompra.mensagem', 'Olá, {nome}! Tudo bem? Já faz {dias} dias da sua última compra conosco. Quer que eu prepare a reposição? Pelo catálogo é rapidinho: {link_catalogo}',
        'texto', 'Recompra', 'Mensagem de WhatsApp da recompra', 'Variáveis: {nome} {dias} {link_catalogo}', 50],
    ['recompra.tolerancia_dias', '3', 'numero', 'Recompra', 'Tolerância antes do alerta (dias)', 'Cliente entra na lista quando passa do intervalo médio + estes dias', 51],
];
foreach ($configuracoes as [$chave, $valor, $tipo, $grupo, $rotulo, $descricao, $ordem]) {
    if (!Banco::valor('SELECT 1 FROM configuracoes WHERE chave = ?', [$chave])) {
        Banco::inserir('configuracoes', compact('chave', 'valor', 'tipo', 'grupo', 'rotulo', 'descricao', 'ordem'));
    }
}
