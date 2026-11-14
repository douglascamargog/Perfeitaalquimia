<?php
/**
 * Desenvolvimento de Produto — DADOS ESSENCIAIS (instalação e atualizações; idempotente).
 *  - função "Qualidade" (o "coringa": código de barras, compras, gráfica, entrada dos rótulos);
 *  - as 11 etapas padrão (depois o admin muda em Configurações › Etapas do desenvolvimento);
 *  - permissões: ver (toda a equipe) e gerenciar (criar/pausar/voltar etapa: Gestor e Marketing).
 */
use App\Core\Banco;

if (!Banco::valor("SELECT 1 FROM funcoes WHERE slug = 'qualidade'")) {
    Banco::executar("INSERT INTO funcoes (slug, nome, descricao, areas_foco, ordem)
        SELECT 'qualidade', 'Qualidade', 'Código de barras, compras de matéria-prima, gráfica e entrada dos rótulos', 'desenvolvimento,producao', COALESCE(MAX(ordem), 0) + 1 FROM funcoes");
}
// Quem já cuida de produto passa a acompanhar o Desenvolvimento
foreach (['tecnica', 'marketing', 'producao'] as $slug) {
    Banco::executar("UPDATE funcoes SET areas_foco = CONCAT(COALESCE(NULLIF(areas_foco, ''), ''), IF(COALESCE(areas_foco, '') = '', '', ','), 'desenvolvimento')
        WHERE slug = ? AND FIND_IN_SET('desenvolvimento', COALESCE(areas_foco, '')) = 0", [$slug]);
}

if (!Banco::valor('SELECT 1 FROM dp_etapas LIMIT 1')) {
    $etapas = [
        ['Ideia e briefing', null, null, null],
        ['Formulação e ficha técnica', 'tecnica', 'ficha', null],
        ['Informações de rotulagem', 'tecnica', 'composicao', null],
        ['Código de barras, NCM e cadastro', 'qualidade', 'codigo', null],
        ['Criação do rótulo', 'marketing', 'arte', null],
        ['Compra de matéria-prima e embalagens', 'qualidade', 'compras', null],
        ['Envio à gráfica', 'qualidade', 'grafica', null],
        ['Aprovação da prova da gráfica', 'qualidade', 'aprovacao', 'marketing,qualidade'],
        ['Recebimento dos rótulos no estoque', 'qualidade', 'recebimento', null],
        ['Lote piloto', 'producao', 'piloto', null],
        ['Lançamento', null, 'lancamento', null],
    ];
    foreach ($etapas as $i => [$nome, $funcao, $checagem, $aprovadores]) {
        Banco::inserir('dp_etapas', ['nome' => $nome, 'ordem' => $i + 1, 'funcao_slug' => $funcao, 'checagem' => $checagem, 'aprovadores' => $aprovadores]);
    }
}

$permissoes = [
    'desenvolvimento.ver'       => ['gestor', 'vendedor', 'financeiro', 'marketing', 'producao', 'tecnica'],
    'desenvolvimento.gerenciar' => ['gestor', 'marketing'],
];
foreach ($permissoes as $perm => $perfis) {
    foreach ($perfis as $perfil) {
        Banco::executar('INSERT IGNORE INTO perfil_permissoes (perfil_id, permissao) SELECT id, ? FROM perfis WHERE slug = ?', [$perm, $perfil]);
    }
}
return null;
