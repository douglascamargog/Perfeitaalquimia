<?php
/**
 * Marketing por funções — DADOS ESSENCIAIS (instalação e atualizações; idempotente).
 *  - funções: Social Media, Gestor de Marketing, E-commerce e Marketplaces (a Qualidade já existe);
 *  - tipos de tarefa e a função que recebe cada um (Configurações › Listas › Tipos de tarefa do Marketing);
 *  - a etapa "Criação do rótulo" do Desenvolvimento passa para o Gestor de Marketing (só se ainda estiver como veio de fábrica).
 */
use App\Core\Banco;

$funcoes = [
    ['social_media', 'Social Media', 'Posts, artes e imagens para as redes e materiais de divulgação', 'marketing'],
    ['gestor_marketing', 'Gestor de Marketing', 'Aprova tudo do Marketing e cria os rótulos', 'marketing,desenvolvimento'],
    ['ecommerce', 'E-commerce e Marketplaces', 'Perguntas dos marketplaces, campanhas de desconto, cadastro de produtos, envios FULL e ofertas relâmpago', 'marketing'],
];
foreach ($funcoes as [$slug, $nome, $descricao, $areas]) {
    if (!Banco::valor('SELECT 1 FROM funcoes WHERE slug = ?', [$slug])) {
        Banco::executar('INSERT INTO funcoes (slug, nome, descricao, areas_foco, ordem) SELECT ?, ?, ?, ?, COALESCE(MAX(ordem), 0) + 1 FROM funcoes',
            [$slug, $nome, $descricao, $areas]);
    }
}

if (!Banco::valor('SELECT 1 FROM mkt_tipos LIMIT 1')) {
    $tipos = [
        ['Arte ou post para as redes sociais', 'social_media'],
        ['Material de divulgação (folder, banner, cartaz)', 'social_media'],
        ['Rótulo ou embalagem', 'gestor_marketing'],
        ['Pergunta de cliente no marketplace ou site', 'ecommerce'],
        ['Campanha de desconto ou oferta relâmpago', 'ecommerce'],
        ['Cadastrar produto na loja ou nos marketplaces', 'ecommerce'],
        ['Envio FULL (Shopee / Mercado Livre)', 'ecommerce'],
        ['Outro assunto', 'gestor_marketing'],
    ];
    foreach ($tipos as $i => [$nome, $funcao]) {
        Banco::inserir('mkt_tipos', ['nome' => $nome, 'funcao_slug' => $funcao, 'ordem' => ($i + 1) * 10]);
    }
}

if (!Banco::valor("SELECT 1 FROM migracoes WHERE arquivo = 'mkt:rotulo-gestor'")) {
    Banco::executar("UPDATE dp_etapas SET funcao_slug = 'gestor_marketing' WHERE nome = 'Criação do rótulo' AND funcao_slug = 'marketing'");
    Banco::executar("UPDATE dp_etapas SET aprovadores = REPLACE(aprovadores, 'marketing', 'gestor_marketing')
        WHERE nome = 'Aprovação da prova da gráfica' AND FIND_IN_SET('marketing', aprovadores) > 0");
    Banco::inserir('migracoes', ['arquivo' => 'mkt:rotulo-gestor']);
}
return null;
