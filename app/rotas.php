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
