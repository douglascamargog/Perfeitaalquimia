<?php
/**
 * Todas as rotas (endereços) do sistema.
 *
 * Formato: [método, caminho, 'Controller@ação', permissão]
 *   permissão: 'publico' | 'logado' | uma permissão de app/permissoes.php
 *
 * Para criar uma tela nova: acrescente uma linha aqui, um método no controller e um template em app/Views.
 */
return [
    // ---------- Acesso ----------
    ['GET',  '/entrar',        'AuthController@formulario', 'publico'],
    ['POST', '/entrar',        'AuthController@entrar',     'publico'],
    ['POST', '/sair',          'AuthController@sair',       'logado'],
    ['GET',  '/minha-senha',   'AuthController@formSenha',  'logado'],
    ['POST', '/minha-senha',   'AuthController@trocarSenha', 'logado'],

    // ---------- Instalação pelo navegador (desligada se instalar_token = '') ----------
    ['GET',  '/instalar',      'InstalarController@index',    'publico'],
    ['POST', '/instalar',      'InstalarController@executar', 'publico'],

    // ---------- Início ----------
    ['GET',  '/',              'InicioController@index', 'logado'],

    // ---------- Carteira e CRM ----------
    ['GET',  '/carteira',                       'CarteiraController@visaoGeral', 'carteira.ver_propria|carteira.ver_todas'],
    ['GET',  '/carteira/lista',                 'CarteiraController@lista',      'carteira.ver_propria|carteira.ver_todas'],
    ['GET',  '/carteira/funil',                 'CarteiraController@funil',      'carteira.ver_propria|carteira.ver_todas'],
    ['GET',  '/carteira/mapa',                  'CarteiraController@mapa',       'carteira.ver_propria|carteira.ver_todas'],
    ['GET',  '/carteira/mapa.json',             'CarteiraController@mapaDados',  'carteira.ver_propria|carteira.ver_todas'],
    ['GET',  '/carteira/exportar.csv',          'CarteiraController@exportar',   'carteira.importar'],
    ['GET',  '/carteira/clientes/novo',         'ClienteController@novo',        'carteira.ver_propria|carteira.ver_todas'],
    ['POST', '/carteira/clientes',              'ClienteController@criar',       'carteira.ver_propria|carteira.ver_todas'],
    ['GET',  '/carteira/clientes/{id}/ficha',   'ClienteController@ficha',       'carteira.ver_propria|carteira.ver_todas'],
    ['GET',  '/carteira/clientes/{id}/editar',  'ClienteController@editar',      'carteira.ver_propria|carteira.ver_todas'],
    ['POST', '/carteira/clientes/{id}',         'ClienteController@atualizar',   'carteira.ver_propria|carteira.ver_todas'],
    ['POST', '/carteira/clientes/{id}/ficha',   'ClienteController@salvar',      'carteira.ver_propria|carteira.ver_todas'],
    ['POST', '/carteira/clientes/{id}/contato', 'ClienteController@contato',     'carteira.ver_propria|carteira.ver_todas'],
    ['POST', '/carteira/clientes/{id}/ligacao', 'ClienteController@ligacao',     'carteira.ver_propria|carteira.ver_todas'],
    ['POST', '/carteira/clientes/{id}/etapa',   'ClienteController@etapa',       'carteira.ver_propria|carteira.ver_todas'],

    ['GET',  '/agenda',                         'AgendaController@index',        'carteira.ver_propria|carteira.ver_todas'],
    ['POST', '/agenda/{id}/concluir',           'AgendaController@concluir',     'carteira.ver_propria|carteira.ver_todas'],

    ['GET',  '/reativacao',                     'ReativacaoController@index',    'carteira.ver_propria|carteira.ver_todas'],
    ['POST', '/reativacao/processar',           'ReativacaoController@processar', 'carteira.ver_todas'],
    ['POST', '/reativacao/{id}/devolver',       'ReativacaoController@devolver', 'carteira.ver_propria|carteira.ver_todas'],

    // ---------- Administração ----------
    ['GET',  '/usuarios',               'UsuarioController@index',    'usuarios.gerenciar'],
    ['GET',  '/usuarios/novo',          'UsuarioController@novo',     'usuarios.gerenciar'],
    ['POST', '/usuarios',               'UsuarioController@criar',    'usuarios.gerenciar'],
    ['GET',  '/usuarios/{id}/editar',   'UsuarioController@editar',   'usuarios.gerenciar'],
    ['POST', '/usuarios/{id}',          'UsuarioController@atualizar', 'usuarios.gerenciar'],

    ['GET',  '/funcoes',                'FuncaoController@index',     'usuarios.gerenciar'],
    ['POST', '/funcoes',                'FuncaoController@criar',     'usuarios.gerenciar'],
    ['POST', '/funcoes/{id}',           'FuncaoController@atualizar', 'usuarios.gerenciar'],

    ['GET',  '/configuracoes',          'ConfigController@index',  'config.gerenciar'],
    ['POST', '/configuracoes',          'ConfigController@salvar', 'config.gerenciar'],

    ['GET',  '/auditoria',              'AuditoriaController@index', 'auditoria.ver'],
];
