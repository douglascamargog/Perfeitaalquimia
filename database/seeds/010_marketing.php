<?php
/**
 * Marketing — DADOS ESSENCIAIS (instalação e atualizações; idempotente).
 * Etapas dos posts, permissões e configurações. Tudo editável depois.
 */
use App\Core\Banco;

// ---------- 1. Etapas do post (Configurações › Listas › Etapas dos posts) ----------
if (!Banco::valor('SELECT 1 FROM mkt_post_etapas LIMIT 1')) {
    $etapas = [
        ['Ideia',                 '#94a3b8', 'rascunho'],
        ['Em produção',           '#38bdf8', 'producao'],
        ['Aguardando aprovação',  '#f59e0b', 'aprovacao'],
        ['Ajustar',               '#ef4444', 'ajuste'],
        ['Aprovado',              '#a3e635', 'aprovado'],
        ['Agendado',              '#a78bfa', 'agendado'],
        ['Publicado',             '#16a34a', 'publicado'],
    ];
    foreach ($etapas as $i => [$nome, $cor, $tipo]) {
        Banco::inserir('mkt_post_etapas', ['nome' => $nome, 'ordem' => $i + 1, 'cor' => $cor, 'tipo' => $tipo]);
    }
}

// ---------- 2. Permissões ----------
$permissoes = [
    'marketing.ver'     => ['marketing', 'gestor'],
    'marketing.editar'  => ['marketing'],
    'marketing.aprovar' => [],                       // admin tem tudo ('*')
    'ecommerce.ver'     => ['marketing', 'gestor', 'financeiro'],
];
foreach ($permissoes as $permissao => $perfis) {
    foreach ($perfis as $perfil) {
        Banco::executar('INSERT IGNORE INTO perfil_permissoes (perfil_id, permissao) SELECT id, ? FROM perfis WHERE slug = ?', [$permissao, $perfil]);
    }
}

// ---------- 3. Configurações ----------
$configuracoes = [
    ['marketing.posts_por_semana_meta', '5', 'numero', 'Marketing', 'Meta de posts por semana', 'Semanas abaixo disso aparecem como "fracas" no mural', 90],
    ['marketing.aprovacao_obrigatoria', '1', 'booleano', 'Marketing', 'Post precisa de aprovação para ser agendado/publicado', '', 91],
    ['marketing.datas_comemorativas',
        '20/01 Dia do Farmacêutico, 08/03 Dia da Mulher, 07/04 Dia Mundial da Saúde, 10/05 Dia das Mães (2º domingo), 09/08 Dia dos Pais (2º domingo), 21/09 Dia da Árvore, 01/10 Outubro Rosa, 01/11 Novembro Azul, 27/11 Black Friday, 30/11 Cyber Monday, 25/12 Natal, 01/01 Janeiro Branco',
        'lista', 'Marketing', 'Datas comemorativas no calendário', 'Formato: dd/mm Nome, separadas por vírgula. Revise as datas móveis a cada ano', 92],
    ['ecommerce.provedor', 'fake', 'texto', 'E-commerce (Tray)', 'Origem dos números do e-commerce',
        'fake = demonstração · tray = loja real (precisa das chaves no config.php)', 95],
    ['ecommerce.ultima_sincronizacao', '', 'texto', 'E-commerce (Tray)', 'Última atualização dos números', 'Preenchido sozinho (cron ou botão "Atualizar agora")', 96],
];
foreach ($configuracoes as [$chave, $valor, $tipo, $grupo, $rotulo, $descricao, $ordem]) {
    if (!Banco::valor('SELECT 1 FROM configuracoes WHERE chave = ?', [$chave])) {
        Banco::inserir('configuracoes', compact('chave', 'valor', 'tipo', 'grupo', 'rotulo', 'descricao', 'ordem'));
    }
}
