<?php
/**
 * Itens do menu lateral.
 *
 * - 'permissao': quem vê o item (uma permissão ou uma lista; basta ter uma delas)
 * - 'pronto': false = módulo ainda em construção no protótipo (aparece com o selo "em breve")
 * - 'icone': nome de um ícone do arquivo public/assets/img/icones.svg
 */
return [
    ['titulo' => 'Início',          'url' => '/',              'icone' => 'casa',      'grupo' => '',               'permissao' => 'logado',                                  'pronto' => true,  'descricao' => 'Resumo do dia'],
    ['titulo' => 'Carteira',        'url' => '/carteira',      'icone' => 'pessoas',   'grupo' => 'Comercial',      'permissao' => ['carteira.ver_propria', 'carteira.ver_todas'], 'pronto' => false, 'descricao' => 'Clientes, funil, mapa e linha do tempo'],
    ['titulo' => 'Reativação',      'url' => '/reativacao',    'icone' => 'relogio',   'grupo' => 'Comercial',      'permissao' => ['carteira.ver_propria', 'carteira.ver_todas'], 'pronto' => false, 'descricao' => 'Clientes sem compra há X dias'],
    ['titulo' => 'Prospecção',      'url' => '/prospeccao',    'icone' => 'lupa',      'grupo' => 'Comercial',      'permissao' => 'prospeccao.usar',                         'pronto' => false, 'descricao' => 'Empresas ativas por cidade (base da Receita)'],
    ['titulo' => 'Pedidos',         'url' => '/pedidos',       'icone' => 'carrinho',  'grupo' => 'Comercial',      'permissao' => ['pedidos.criar', 'pedidos.ver_todos'],    'pronto' => false, 'descricao' => 'Pedidos do catálogo, do sistema e do app'],
    ['titulo' => 'Aprovação',       'url' => '/aprovacao',     'icone' => 'check',     'grupo' => 'Comercial',      'permissao' => 'pedidos.aprovar',                         'pronto' => false, 'descricao' => 'Financeiro aprova pedidos e confere crédito'],
    ['titulo' => 'Painel de vendas', 'url' => '/painel',       'icone' => 'grafico',   'grupo' => 'Gestão',         'permissao' => 'painel.ver',                              'pronto' => false, 'descricao' => 'Faturamento, ticket médio, recompra'],
    ['titulo' => 'Metas',           'url' => '/metas',         'icone' => 'alvo',      'grupo' => 'Gestão',         'permissao' => 'metas.gerenciar',                         'pronto' => false, 'descricao' => 'Metas por vendedor e por função'],
    ['titulo' => 'Produtos',        'url' => '/produtos',      'icone' => 'caixa',     'grupo' => 'Conteúdo',       'permissao' => 'catalogo.editar',                         'pronto' => false, 'descricao' => 'Fotos, descrições e fichas (mesmo cadastro da Academia)'],
    ['titulo' => 'Academia',        'url' => '/academia',      'icone' => 'livro',     'grupo' => 'Conteúdo',       'permissao' => 'academia.ver',                            'pronto' => false, 'descricao' => 'Treinamento comercial'],
    ['titulo' => 'Mensagens prontas', 'url' => '/mensagens',   'icone' => 'conversa',  'grupo' => 'Conteúdo',       'permissao' => 'mensagens.gerenciar',                     'pronto' => false, 'descricao' => 'Modelos de WhatsApp'],
    ['titulo' => 'Produção',        'url' => '/producao',      'icone' => 'fabrica',   'grupo' => 'Produção',       'permissao' => 'producao.gerenciar',                      'pronto' => false, 'descricao' => 'Matérias-primas, fichas técnicas, ordens'],
    ['titulo' => 'Usuários',        'url' => '/usuarios',      'icone' => 'usuario',   'grupo' => 'Administração',  'permissao' => 'usuarios.gerenciar',                      'pronto' => true,  'descricao' => 'Logins, perfis e funções das pessoas'],
    ['titulo' => 'Funções',         'url' => '/funcoes',       'icone' => 'etiqueta',  'grupo' => 'Administração',  'permissao' => 'usuarios.gerenciar',                      'pronto' => true,  'descricao' => 'Televendas, Externo, Reativação...'],
    ['titulo' => 'Configurações',   'url' => '/configuracoes', 'icone' => 'engrenagem', 'grupo' => 'Administração', 'permissao' => 'config.gerenciar',                        'pronto' => true,  'descricao' => 'Regras comerciais editáveis'],
    ['titulo' => 'Integração Omie', 'url' => '/integracao',    'icone' => 'nuvem',     'grupo' => 'Administração',  'permissao' => 'integracao.gerenciar',                    'pronto' => false, 'descricao' => 'Sincronização e fila de envio'],
    ['titulo' => 'Auditoria',       'url' => '/auditoria',     'icone' => 'escudo',    'grupo' => 'Administração',  'permissao' => 'auditoria.ver',                           'pronto' => true,  'descricao' => 'Quem fez o quê, e quando'],
];
