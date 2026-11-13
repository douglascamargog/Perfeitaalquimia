<?php
/**
 * Produção e matérias-primas — DADOS ESSENCIAIS (instalação e atualizações; idempotente).
 */
use App\Core\Banco;

// producao.gerenciar já existe (perfil Produção). producao.ver = ver sem editar (Gestor).
Banco::executar('INSERT IGNORE INTO perfil_permissoes (perfil_id, permissao) SELECT id, ? FROM perfis WHERE slug = ?', ['producao.gerenciar', 'producao']);
Banco::executar('INSERT IGNORE INTO perfil_permissoes (perfil_id, permissao) SELECT id, ? FROM perfis WHERE slug = ?', ['producao.ver', 'gestor']);

$configuracoes = [
    ['producao.unidades', 'kg,g,L,ml,un', 'lista', 'Produção', 'Unidades das matérias-primas', 'Separadas por vírgula', 60],
    ['producao.baixar_no_faturamento', '1', 'booleano', 'Produção', 'Baixar o estoque acabado quando o pedido é faturado', 'Assim o reabastecimento acompanha as vendas', 61],
    ['producao.dias_consumo', '30', 'numero', 'Produção', 'Dias usados para calcular o consumo médio', 'Base da previsão "acaba em X dias" e das vendas médias', 62],
    ['producao.dias_cobertura', '30', 'numero', 'Produção', 'Ordem de reabastecimento cobre quantos dias de venda', 'A ordem sugerida produz o suficiente para esse período (no mínimo o dobro do estoque mínimo)', 63],
    ['producao.dias_alerta', '15', 'numero', 'Produção', 'Avisar quando a matéria-prima acabar em menos de (dias)', 'Pelo consumo médio', 64],
];
foreach ($configuracoes as [$chave, $valor, $tipo, $grupo, $rotulo, $descricao, $ordem]) {
    if (!Banco::valor('SELECT 1 FROM configuracoes WHERE chave = ?', [$chave])) {
        Banco::inserir('configuracoes', compact('chave', 'valor', 'tipo', 'grupo', 'rotulo', 'descricao', 'ordem'));
    }
}
