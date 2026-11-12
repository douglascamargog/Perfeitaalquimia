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

    // ---------- Catálogo B2B (área do cliente; o controller confere o login do cliente) ----------
    ['GET',  '/catalogo',                      'CatalogoController@vitrine',     'publico'],
    ['GET',  '/catalogo/produto/{slug}',       'CatalogoController@produto',     'publico'],
    ['GET',  '/catalogo/v/{codigo}',           'CatalogoController@vendedor',    'publico'],
    ['GET',  '/catalogo/entrar',               'CatalogoController@formEntrar',  'publico'],
    ['POST', '/catalogo/entrar',               'CatalogoController@entrar',      'publico'],
    ['POST', '/catalogo/codigo',               'CatalogoController@pedirCodigo', 'publico'],
    ['POST', '/catalogo/senha',                'CatalogoController@definirSenha', 'publico'],
    ['POST', '/catalogo/sair',                 'CatalogoController@sair',        'publico'],
    ['POST', '/catalogo/carrinho/quantidade',  'CatalogoController@quantidade',  'publico'],
    ['GET',  '/catalogo/carrinho',             'CatalogoController@carrinho',    'publico'],
    ['POST', '/catalogo/finalizar',            'CatalogoController@finalizar',   'publico'],
    ['GET',  '/catalogo/pedidos',              'CatalogoController@pedidos',     'publico'],
    ['POST', '/catalogo/pedidos/{id}/repetir', 'CatalogoController@repetir',     'publico'],
    ['POST', '/catalogo/pedidos/{id}/cancelar', 'CatalogoController@cancelar',   'publico'],

    // ---------- Início ----------
    ['GET',  '/',              'InicioController@index', 'logado'],
    ['GET',  '/one/falas',     'OneController@falas',    'logado'],   // One, o ajudante

    // ---------- Carteira e CRM ----------
    ['GET',  '/carteira',                       'CarteiraController@visaoGeral', 'carteira.ver_propria|carteira.ver_todas'],
    ['GET',  '/carteira/campanha',              'CarteiraController@campanhaAtual', 'carteira.ver_propria|carteira.ver_todas'],
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

    ['GET',  '/carteira/importar',                    'ImportacaoController@index',     'carteira.importar'],
    ['POST', '/carteira/importar',                    'ImportacaoController@enviar',    'carteira.importar'],
    ['GET',  '/carteira/importar/{id}',               'ImportacaoController@previa',    'carteira.importar'],
    ['POST', '/carteira/importar/{id}/confirmar',     'ImportacaoController@confirmar', 'carteira.importar'],
    ['POST', '/carteira/importar/{id}/cancelar',      'ImportacaoController@cancelar',  'carteira.importar'],

    ['GET',  '/agenda',                         'AgendaController@index',        'carteira.ver_propria|carteira.ver_todas'],
    ['POST', '/agenda/{id}/concluir',           'AgendaController@concluir',     'carteira.ver_propria|carteira.ver_todas'],

    ['GET',  '/reativacao',                     'ReativacaoController@index',    'carteira.ver_propria|carteira.ver_todas'],
    ['POST', '/reativacao/processar',           'ReativacaoController@processar', 'carteira.ver_todas'],
    ['POST', '/reativacao/{id}/devolver',       'ReativacaoController@devolver', 'carteira.ver_propria|carteira.ver_todas'],

    // ---------- Cobrança (funil próprio) ----------
    ['GET',  '/cobranca',                       'CobrancaController@funil',       'cobranca.ver'],
    ['GET',  '/cobranca/lista',                 'CobrancaController@lista',       'cobranca.ver'],
    ['GET',  '/cobranca/imprimir',              'CobrancaController@imprimir',    'cobranca.ver'],
    ['GET',  '/cobranca/exportar.csv',          'CobrancaController@exportar',    'cobranca.ver'],
    ['POST', '/cobranca/importar',              'CobrancaController@importar',    'cobranca.gerenciar'],
    ['GET',  '/cobranca/{id}/caso',             'CobrancaController@caso',        'cobranca.ver'],
    ['POST', '/cobranca/{id}/tentativa',        'CobrancaController@tentativa',   'cobranca.gerenciar'],
    ['POST', '/cobranca/{id}/promessa',         'CobrancaController@promessa',    'cobranca.gerenciar'],
    ['POST', '/cobranca/{id}/etapa',            'CobrancaController@etapa',       'cobranca.gerenciar'],
    ['POST', '/cobranca/{id}/responsavel',      'CobrancaController@responsavel', 'cobranca.gerenciar'],
    ['POST', '/cobranca/{id}/observacoes',      'CobrancaController@observacoes', 'cobranca.gerenciar'],
    ['POST', '/cobranca/titulos/{id}/pago',     'CobrancaController@tituloPago',  'cobranca.gerenciar'],
    ['POST', '/carteira/clientes/{id}/cobranca', 'CobrancaController@enviar',     'cobranca.enviar'],

    // ---------- Busca de contatos com IA (ficha do cliente e caso de cobrança) ----------
    ['GET',  '/clientes/{id}/contatos-ia',          'BuscaContatoController@ultima',  'contatos.buscar_ia'],
    ['POST', '/clientes/{id}/contatos-ia',          'BuscaContatoController@buscar',  'contatos.buscar_ia'],
    ['POST', '/clientes/{id}/contatos-ia/aplicar',  'BuscaContatoController@aplicar', 'contatos.buscar_ia'],

    // ---------- Marketing (mural de posts, planejamento, e-commerce) ----------
    ['GET',  '/marketing',                         'MarketingController@mural',        'marketing.ver'],
    ['GET',  '/marketing/feed',                    'MarketingController@feed',         'marketing.ver'],
    ['GET',  '/marketing/planejamento',            'MarketingController@planejamento', 'marketing.ver'],
    ['POST', '/marketing/campanhas',               'MarketingController@salvarCampanha', 'marketing.editar'],
    ['POST', '/marketing/campanhas/{id}',          'MarketingController@salvarCampanha', 'marketing.editar'],
    ['GET',  '/marketing/ecommerce',               'MarketingController@ecommerce',    'ecommerce.ver'],
    ['POST', '/marketing/ecommerce/atualizar',     'MarketingController@atualizarEcommerce', 'ecommerce.ver'],
    ['POST', '/marketing/ecommerce/testar',        'MarketingController@testarEcommerce', 'config.gerenciar'],
    ['GET',  '/marketing/posts/novo',              'PostController@novo',              'marketing.editar'],
    ['POST', '/marketing/posts',                   'PostController@criar',             'marketing.editar'],
    ['GET',  '/marketing/posts/{id}',              'PostController@ver',               'marketing.ver'],
    ['POST', '/marketing/posts/{id}',              'PostController@salvar',            'marketing.editar'],
    ['POST', '/marketing/posts/{id}/data',         'PostController@data',              'marketing.editar'],
    ['POST', '/marketing/posts/{id}/etapa',        'PostController@etapa',             'marketing.editar|marketing.aprovar'],
    ['POST', '/marketing/posts/{id}/enviar',       'PostController@enviarAprovacao',   'marketing.editar'],
    ['POST', '/marketing/posts/{id}/aprovar',      'PostController@aprovar',           'marketing.aprovar'],
    ['POST', '/marketing/posts/{id}/ajuste',       'PostController@ajuste',            'marketing.aprovar'],
    ['POST', '/marketing/posts/{id}/publicar',     'PostController@publicar',          'marketing.editar'],
    ['POST', '/marketing/posts/{id}/comentar',     'PostController@comentar',          'marketing.ver'],

    // ---------- Meu mês (meta individual e da equipe) ----------
    ['GET',  '/meu-mes',                        'MeuMesController@index',          'meu_mes.usar'],
    ['POST', '/meu-mes/meta',                   'MeuMesController@salvarMeta',     'meu_mes.usar'],
    ['POST', '/meu-mes/vendas',                 'MeuMesController@registrarVenda', 'meu_mes.usar'],
    ['POST', '/meu-mes/vendas/{id}/excluir',    'MeuMesController@excluirVenda',   'meu_mes.usar'],
    ['POST', '/meu-mes/folga',                  'MeuMesController@folga',          'meu_mes.usar'],
    ['POST', '/meu-mes/tarefas',                'MeuMesController@salvarTarefa',   'meu_mes.usar'],
    ['POST', '/meu-mes/tarefas/{id}/concluir',  'MeuMesController@concluirTarefa', 'meu_mes.usar'],
    ['POST', '/meu-mes/tarefas/{id}/excluir',   'MeuMesController@excluirTarefa',  'meu_mes.usar'],
    ['POST', '/meu-mes/equipe',                 'MeuMesController@salvarEquipe',   'metas.gerenciar'],

    // ---------- Academia (Central de Televendas) ----------
    ['GET',  '/academia',                       'AcademiaController@inicio',      'academia.ver'],
    ['GET',  '/academia/trilha',                'AcademiaController@trilha',      'academia.ver'],
    ['GET',  '/academia/trilha/{id}',           'AcademiaController@modulo',      'academia.ver'],
    ['GET',  '/academia/produtos',              'AcademiaController@produtos',    'academia.ver'],
    ['GET',  '/academia/produtos/{slug}',       'AcademiaController@produto',     'academia.ver'],
    ['GET',  '/academia/atendimento',           'AcademiaController@atendimento', 'academia.ver'],
    ['GET',  '/academia/crm',                   'AcademiaController@crm',         'academia.ver'],
    ['GET',  '/academia/crm/{id}',              'AcademiaController@crm',         'academia.ver'],
    ['POST', '/academia/progresso',             'AcademiaController@marcar',      'academia.ver'],

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

    ['GET',  '/configuracoes/listas',                 'ListaController@index',  'config.gerenciar'],
    ['POST', '/configuracoes/listas/{lista}',         'ListaController@criar',  'config.gerenciar'],
    ['POST', '/configuracoes/listas/{lista}/{id}',    'ListaController@salvar', 'config.gerenciar'],

    ['GET',  '/sistema/atualizacao',                  'AtualizacaoController@index',   'sistema.atualizar'],
    ['POST', '/sistema/atualizacao/aplicar',          'AtualizacaoController@aplicar', 'sistema.atualizar'],
    ['POST', '/sistema/atualizacao/backup',           'AtualizacaoController@backup',  'sistema.atualizar'],

    ['GET',  '/auditoria',              'AuditoriaController@index', 'auditoria.ver'],
];
