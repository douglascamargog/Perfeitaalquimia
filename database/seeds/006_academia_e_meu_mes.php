<?php
/**
 * 1. Textos da Academia que eram fixos no programa da Central (empresa, mídias do marketing, guia de atendimento).
 * 2. Links da Academia (catálogo, grupo do marketing, site, Instagram) como configurações editáveis.
 * 3. Permissão "Meu mês" para gestores e vendedores.
 * 4. Demonstração: meta da equipe do mês atual e metas/vendas individuais dos vendedores (valores fictícios).
 */
use App\Core\Banco;

mt_srand(2027);

// ---------- 1. Páginas da Academia ----------
foreach (require __DIR__ . '/dados/academia_paginas.php' as $chave => $conteudo) {
    Banco::executar(
        'INSERT INTO academia_conteudo (chave, conteudo_json) VALUES (?, ?) ON DUPLICATE KEY UPDATE conteudo_json = VALUES(conteudo_json)',
        ['pagina_' . $chave, json_encode($conteudo, JSON_UNESCAPED_UNICODE)]
    );
}

// ---------- 2. Links ----------
$links = [
    ['academia.link_catalogo', 'https://online.fliphtml5.com/uyxyy/wjoa/#p=1', 'Link do catálogo digital', 'Usado nas mensagens de mídias do marketing'],
    ['academia.link_grupo_marketing', 'https://chat.whatsapp.com/Ib9h6ld397I3zeWHqDpPVI', 'Link do grupo de WhatsApp do marketing', ''],
    ['academia.link_site', 'https://www.perfeitaalquimia.com.br/', 'Site da empresa', ''],
    ['academia.link_instagram', 'https://www.instagram.com/perfeita.alquimia/', 'Instagram da empresa', ''],
];
foreach ($links as $i => [$chave, $valor, $rotulo, $descricao]) {
    if (!Banco::valor('SELECT 1 FROM configuracoes WHERE chave = ?', [$chave])) {
        Banco::inserir('configuracoes', ['chave' => $chave, 'valor' => $valor, 'tipo' => 'texto', 'grupo' => 'Academia', 'rotulo' => $rotulo, 'descricao' => $descricao, 'ordem' => 40 + $i]);
    }
}

// ---------- 3. Permissão do Meu mês ----------
foreach (['gestor', 'vendedor'] as $perfil) {
    Banco::executar('INSERT IGNORE INTO perfil_permissoes (perfil_id, permissao) SELECT id, ? FROM perfis WHERE slug = ?', ['meu_mes.usar', $perfil]);
}

// ---------- 4. Demonstração do mês atual ----------
$mes = date('Y-m');
$hoje = date('Y-m-d');
$diasTrabalhados = [];
for ($d = 1; $d <= (int) date('d'); $d++) {
    $data = sprintf('%s-%02d', $mes, $d);
    if ((int) date('N', strtotime($data)) <= 5) {
        $diasTrabalhados[] = $data;
    }
}
$gestor = (int) Banco::valor("SELECT id FROM usuarios WHERE email = 'gestor@demo.local'");

// Meta da equipe: o gestor lançou o acumulado em alguns dias do mês
$acumulado = 0;
foreach ($diasTrabalhados as $i => $data) {
    $acumulado += mt_rand(14000, 26000);
    if ($i % 3 === 2 || $data === end($diasTrabalhados)) {
        Banco::inserir('metas_equipe_lancamentos', ['mes' => $mes, 'data' => $data, 'vendido' => $acumulado, 'usuario_id' => $gestor, 'criado_em' => $data . ' 18:00:00']);
    }
}
Banco::inserir('metas_equipe', ['mes' => $mes, 'meta' => 450000, 'vendido' => $acumulado, 'atualizado_por' => $gestor, 'folgas' => json_encode([])]);

// Metas individuais e vendas registradas
$vendedores = ['televendas1@demo.local' => 90000, 'televendas2@demo.local' => 80000, 'externo1@demo.local' => 120000, 'reativacao@demo.local' => 40000, 'terceirizacao@demo.local' => 60000];
foreach ($vendedores as $email => $meta) {
    $id = (int) Banco::valor('SELECT id FROM usuarios WHERE email = ?', [$email]);
    Banco::inserir('meu_mes', ['usuario_id' => $id, 'mes' => $mes, 'meta' => $meta, 'folgas' => json_encode([])]);
    foreach ($diasTrabalhados as $data) {
        if (mt_rand(1, 10) <= 6) {
            Banco::inserir('vendas_registradas', ['usuario_id' => $id, 'data' => $data, 'valor' => mt_rand(800, 7500) + mt_rand(0, 99) / 100, 'descricao' => 'Pedido de demonstração']);
        }
    }
    Banco::inserir('tarefas', ['usuario_id' => $id, 'titulo' => 'Revisar clientes sem compra no mês', 'tipo' => 'outro', 'prioridade' => 'alta', 'vence_em' => date('Y-m-d', strtotime('+2 days'))]);
    Banco::inserir('tarefas', ['usuario_id' => $id, 'titulo' => 'Reunião de equipe', 'tipo' => 'compromisso', 'vence_em' => date('Y-m-d', strtotime('next monday')), 'hora' => '09:00:00', 'notas' => 'Alinhamento semanal de metas']);
}
