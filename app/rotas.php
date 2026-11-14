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
    ['GET',  '/carteira/clientes/{id}',         'ClienteController@pagina',      'carteira.ver_propria|carteira.ver_todas'],
    ['GET',  '/carteira/clientes/{id}/ficha',   'ClienteController@ficha',       'carteira.ver_propria|carteira.ver_todas'],
    ['GET',  '/carteira/clientes/{id}/editar',  'ClienteController@editar',      'carteira.ver_propria|carteira.ver_todas'],
    ['POST', '/carteira/clientes/{id}',         'ClienteController@atualizar',   'carteira.ver_propria|carteira.ver_todas'],
    ['POST', '/carteira/clientes/{id}/ficha',   'ClienteController@salvar',      'carteira.ver_propria|carteira.ver_todas'],
    ['POST', '/carteira/clientes/{id}/contato', 'ClienteController@contato',     'carteira.ver_propria|carteira.ver_todas'],
    ['POST', '/carteira/clientes/{id}/ligar',   'ClienteController@ligar',       'carteira.ver_propria|carteira.ver_todas'],
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

    // ---------- Pedidos, aprovação e produtos ----------
    ['GET',  '/pedidos',                        'PedidoController@index',   'pedidos.criar|pedidos.ver_todos|pedidos.aprovar'],
    ['GET',  '/pedidos/novo',                   'PedidoController@novo',    'pedidos.criar'],
    ['POST', '/pedidos',                        'PedidoController@criar',   'pedidos.criar'],
    ['GET',  '/pedidos/{id}',                   'PedidoController@ver',     'pedidos.criar|pedidos.ver_todos|pedidos.aprovar'],
    ['POST', '/pedidos/{id}/cancelar',          'PedidoController@cancelar', 'pedidos.criar|pedidos.ver_todos'],
    ['POST', '/pedidos/{id}/simular-faturamento', 'PedidoController@simularFaturamento', 'pedidos.aprovar|pedidos.ver_todos'],
    ['GET',  '/aprovacao',                      'AprovacaoController@index',   'pedidos.aprovar'],
    ['POST', '/aprovacao/{id}/aprovar',         'AprovacaoController@aprovar', 'pedidos.aprovar'],
    ['POST', '/aprovacao/{id}/recusar',         'AprovacaoController@recusar', 'pedidos.aprovar'],
    // Catálogo (vitrine do cliente): visível, chamada, ordem, códigos e preços
    ['GET',  '/produtos/catalogo',                   'CatalogoAdminController@index',     'produtos.ver|catalogo.editar'],
    ['POST', '/produtos/catalogo/sincronizar',       'CatalogoAdminController@sincronizar', 'catalogo.editar'],
    ['POST', '/produtos/catalogo/codigos',           'CatalogoAdminController@importarCodigos', 'catalogo.editar'],
    ['POST', '/produtos/catalogo/codigos/confirmar', 'CatalogoAdminController@confirmarCodigos', 'catalogo.editar'],
    ['POST', '/produtos/catalogo/{id}',              'CatalogoAdminController@salvar',    'catalogo.editar'],
    // Produtos (tudo sobre o produto, para a equipe)
    ['GET',  '/produtos',                            'ProdutoController@index',           'produtos.ver|catalogo.editar|produtos.tecnico'],
    ['GET',  '/produtos/documentos/{id}',            'ProdutoController@baixar',          'produtos.ver|catalogo.editar|produtos.tecnico'],
    ['POST', '/produtos/documentos/{id}/arquivar',   'ProdutoController@arquivarDocumento', 'produtos.tecnico'],
    ['GET',  '/produtos/{slug}',                     'ProdutoController@ver',             'produtos.ver|catalogo.editar|produtos.tecnico'],
    ['POST', '/produtos/{slug}/conteudo',            'ProdutoController@salvarConteudo',  'academia.editar|catalogo.editar'],
    ['POST', '/produtos/{slug}/composicao',          'ProdutoController@salvarComposicao', 'produtos.tecnico'],
    ['POST', '/produtos/{slug}/ficha',               'ProdutoController@salvarFicha',     'produtos.tecnico'],
    ['POST', '/produtos/{slug}/embalagens',          'ProdutoController@salvarTecnico',   'produtos.tecnico'],
    ['POST', '/produtos/{slug}/documentos',          'ProdutoController@enviarDocumento', 'produtos.tecnico'],
    ['POST', '/carteira/clientes/{id}/acesso-catalogo', 'ClienteController@acessoCatalogo', 'catalogo.acessos'],

    // ---------- Gestão › Carteiras (Admin, Gestor e gestora do setor) ----------
    ['GET',  '/carteiras',                          'CarteirasController@index',     'carteiras.gerenciar|setor.gestao'],
    ['POST', '/carteiras',                          'CarteirasController@criar',     'carteiras.gerenciar|setor.gestao'],
    ['GET',  '/carteiras/importacao/{id}',          'CarteirasController@previa',    'carteiras.gerenciar|setor.gestao'],
    ['POST', '/carteiras/importacao/{id}/confirmar', 'CarteirasController@confirmar', 'carteiras.gerenciar|setor.gestao'],
    ['POST', '/carteiras/importacao/{id}/cancelar', 'CarteirasController@cancelar',  'carteiras.gerenciar|setor.gestao'],
    ['GET',  '/carteiras/{id}',                     'CarteirasController@ver',       'carteiras.gerenciar|setor.gestao'],
    ['GET',  '/carteiras/{id}/exportar.csv',        'CarteirasController@exportar',  'carteiras.gerenciar|setor.gestao'],
    ['POST', '/carteiras/{id}',                     'CarteirasController@editar',    'carteiras.gerenciar|setor.gestao'],
    ['POST', '/carteiras/{id}/passar',              'CarteirasController@passar',    'carteiras.gerenciar|setor.gestao'],
    ['POST', '/carteiras/{id}/juntar',              'CarteirasController@juntar',    'carteiras.gerenciar|setor.gestao'],
    ['POST', '/carteiras/{id}/mover',               'CarteirasController@mover',     'carteiras.gerenciar|setor.gestao'],
    ['POST', '/carteiras/{id}/importar',            'CarteirasController@importar',  'carteiras.gerenciar|setor.gestao'],

    // ---------- Gestão › Acompanhamento (ligações, gravações, contatos e reativações por vendedora) ----------
    ['GET',  '/acompanhamento',                     'AcompanhamentoController@index',    'acompanhamento.ver|setor.gestao|cobranca.gerenciar'],
    ['GET',  '/acompanhamento/pessoa/{id}',         'AcompanhamentoController@pessoa',   'acompanhamento.ver|setor.gestao'],
    ['GET',  '/acompanhamento/cobranca/{id}',       'AcompanhamentoController@cobranca', 'acompanhamento.ver|cobranca.gerenciar'],
    ['GET',  '/ligacoes/{id}/gravacao',             'AcompanhamentoController@gravacao', 'logado'],
    ['POST', '/telefonia/discador',                 'AcompanhamentoController@discador', 'carteiras.gerenciar|setor.gestao'],
    ['POST', '/telefonia/testar',                   'AcompanhamentoController@testar',   'integracao.gerenciar'],
    ['POST', '/telefonia/sincronizar',              'AcompanhamentoController@sincronizar', 'integracao.gerenciar'],

    // ---------- Painel de vendas, metas e recompra ----------
    ['GET',  '/painel',                 'PainelController@index',    'painel.ver'],
    ['GET',  '/painel/exportar.csv',    'PainelController@exportar', 'painel.ver'],
    ['GET',  '/metas',                  'MetasController@index',     'metas.ver|metas.gerenciar'],
    ['POST', '/metas',                  'MetasController@salvar',    'metas.gerenciar'],
    ['POST', '/metas/copiar',           'MetasController@copiar',    'metas.gerenciar'],
    ['GET',  '/recompra',               'RecompraController@index',  'recompra.ver'],
    ['POST', '/recompra/{id}/agendar',  'RecompraController@agendar', 'recompra.ver'],

    // ---------- App do vendedor externo (PWA offline) ----------
    ['GET',  '/app',                      'AppController@index',       'app.usar'],
    ['GET',  '/app/manifest.webmanifest', 'AppController@manifest',    'publico'],
    ['GET',  '/app/sw.js',                'AppController@sw',          'publico'],
    ['GET',  '/app/api/dados',            'AppController@dados',       'app.usar'],
    ['POST', '/app/api/sincronizar',      'AppController@sincronizar', 'app.usar'],
    ['GET',  '/app/api/situacao',         'AppController@situacao',    'app.usar'],

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

    // ---------- Prospecção (fila "Para abordar" + base de CNPJs) ----------
    ['GET',  '/prospeccao',                      'ProspeccaoController@index',           'prospeccao.ver|prospeccao.usar'],
    ['GET',  '/prospeccao/empresas',             'ProspeccaoController@empresas',        'prospeccao.ver|prospeccao.usar'],
    ['POST', '/prospeccao/enviar',               'ProspeccaoController@enviar',          'prospeccao.usar'],

    // ---------- Captura de leads (site e anúncios) ----------
    ['POST',    '/leads/receber',                'LeadController@receber',               'api-publica'],
    ['OPTIONS', '/leads/receber',                'LeadController@preflight',             'api-publica'],
    ['GET',     '/seja-revendedor',              'LeadController@pagina',                'publico'],
    ['POST',    '/seja-revendedor',              'LeadController@enviarPagina',          'publico'],
    ['GET',     '/leads',                        'LeadController@index',                 'leads.ver'],
    ['GET',     '/leads/instalar',               'LeadController@instalar',              'leads.ver'],

    // ---------- Mensagens prontas de WhatsApp ----------
    ['GET',  '/mensagens',                       'MensagemController@index',             'mensagens.ver|mensagens.gerenciar'],
    ['GET',  '/mensagens/nova',                  'MensagemController@nova',              'mensagens.gerenciar'],
    ['POST', '/mensagens',                       'MensagemController@criar',             'mensagens.gerenciar'],
    ['GET',  '/mensagens/{id}',                  'MensagemController@editar',            'mensagens.gerenciar'],
    ['POST', '/mensagens/{id}',                  'MensagemController@atualizar',         'mensagens.gerenciar'],
    ['POST', '/mensagens/{id}/usar',             'MensagemController@usar',              'mensagens.usar'],

    // ---------- Produção e matérias-primas (ver: producao.ver · mexer: producao.gerenciar) ----------
    ['GET',  '/producao',                        'ProducaoController@painel',            'producao.gerenciar|producao.ver'],
    ['GET',  '/producao/acabados',               'ProducaoController@acabados',          'producao.gerenciar|producao.ver'],
    ['GET',  '/producao/acabados/{id}',          'ProducaoController@acabado',           'producao.gerenciar|producao.ver'],
    ['POST', '/producao/acabados/{id}/minimo',   'ProducaoController@minimo',            'producao.gerenciar'],
    ['POST', '/producao/acabados/{id}/ajuste',   'ProducaoController@ajuste',            'producao.gerenciar'],
    ['POST', '/producao/reabastecer/{id}',       'ProducaoController@reabastecer',       'producao.gerenciar'],
    ['GET',  '/producao/materias',               'MateriaPrimaController@index',         'producao.gerenciar|producao.ver'],
    ['GET',  '/producao/materias/nova',          'MateriaPrimaController@nova',          'producao.gerenciar'],
    ['POST', '/producao/materias',               'MateriaPrimaController@criar',         'producao.gerenciar'],
    ['GET',  '/producao/materias/{id}',          'MateriaPrimaController@ver',           'producao.gerenciar|producao.ver'],
    ['POST', '/producao/materias/{id}',          'MateriaPrimaController@atualizar',     'producao.gerenciar'],
    ['POST', '/producao/materias/{id}/entrada',  'MateriaPrimaController@entrada',       'producao.gerenciar'],
    ['POST', '/producao/materias/{id}/ajuste',   'MateriaPrimaController@ajuste',        'producao.gerenciar'],
    ['GET',  '/producao/fichas',                 'FichaTecnicaController@index',         'producao.gerenciar|producao.ver|produtos.tecnico'],
    ['GET',  '/producao/fichas/{id}',            'FichaTecnicaController@ver',           'producao.gerenciar|producao.ver|produtos.tecnico'],
    ['POST', '/producao/fichas/{id}',            'FichaTecnicaController@salvar',        'produtos.tecnico'],
    ['GET',  '/producao/ordens',                 'OrdemProducaoController@index',        'producao.gerenciar|producao.ver'],
    ['GET',  '/producao/ordens/nova',            'OrdemProducaoController@nova',         'producao.gerenciar'],
    ['GET',  '/producao/ordens/previa',          'OrdemProducaoController@previa',       'producao.gerenciar'],
    ['POST', '/producao/ordens',                 'OrdemProducaoController@criar',        'producao.gerenciar'],
    ['GET',  '/producao/ordens/{id}',            'OrdemProducaoController@ver',          'producao.gerenciar|producao.ver'],
    ['GET',  '/producao/ordens/{id}/imprimir',   'OrdemProducaoController@imprimir',     'producao.gerenciar|producao.ver'],
    ['POST', '/producao/ordens/{id}/concluir',   'OrdemProducaoController@concluir',     'producao.gerenciar'],
    ['POST', '/producao/ordens/{id}/cancelar',   'OrdemProducaoController@cancelar',     'producao.gerenciar'],

    // ---------- Notificações (sino do topo) ----------
    ['GET',  '/notificacoes',                   'NotificacaoController@index',       'logado'],
    ['GET',  '/notificacoes/resumo',            'NotificacaoController@resumo',      'logado'],
    ['POST', '/notificacoes/marcar-todas',      'NotificacaoController@marcarTodas', 'logado'],
    ['GET',  '/notificacoes/{id}',              'NotificacaoController@abrir',       'logado'],

    // ---------- Busca rápida do topo ----------
    ['GET',  '/busca',                          'BuscaController@rapida',     'logado'],

    // ---------- Meu perfil (foto, aniversário, contatos) ----------
    ['GET',  '/novidades',                      'NovidadesController@index',  'logado'],
    ['POST', '/novidades/vista',                'NovidadesController@vista',  'logado'],
    // Equipe: perfil das pessoas e mural de aniversário
    ['GET',  '/pessoas',                        'PessoaController@index',                'logado'],
    ['GET',  '/pessoas/{id}',                   'PessoaController@ver',                  'logado'],
    ['POST', '/meu-perfil/publico',             'PessoaController@salvarPerfil',         'logado'],
    ['POST', '/aniversario/{id}/mensagens',     'PessoaController@parabens',             'logado'],
    ['POST', '/aniversario/mensagens/{id}/apagar', 'PessoaController@apagarParabens',    'logado'],
    ['GET',  '/comunicados',                    'ComunicadoController@index',            'logado'],
    ['POST', '/comunicados',                    'ComunicadoController@emitir',           'comunicados.emitir'],
    ['POST', '/comunicados/{id}/ler',           'ComunicadoController@ler',              'logado'],
    ['POST', '/comunicados/{id}/encerrar',      'ComunicadoController@encerrar',         'comunicados.emitir'],
    ['GET',  '/equipe',                         'EquipeController@index',                'equipe.ver'],
    // Terceirização (marca própria do cliente)
    ['GET',  '/terceirizacao',                  'TerceirizacaoController@clientes',      'terceirizacao.ver|terceirizacao.editar'],
    ['GET',  '/terceirizacao/pedidos',          'TerceirizacaoController@pedidos',       'terceirizacao.ver|terceirizacao.editar'],
    ['GET',  '/terceirizacao/produtos',         'TerceirizacaoController@produtos',      'terceirizacao.ver|terceirizacao.editar'],
    ['POST', '/terceirizacao/produtos',         'TerceirizacaoController@salvarProduto', 'terceirizacao.editar'],
    ['POST', '/terceirizacao/produtos/{id}',    'TerceirizacaoController@salvarProduto', 'terceirizacao.editar'],
    ['GET',  '/terceirizacao/rotulos',          'TerceirizacaoController@rotulos',       'terceirizacao.ver|terceirizacao.editar'],
    ['POST', '/terceirizacao/rotulos/{id}',     'TerceirizacaoController@movimentar',    'terceirizacao.editar'],
    ['GET',  '/meu-perfil',                     'MeuPerfilController@index',  'logado'],
    ['POST', '/meu-perfil',                     'MeuPerfilController@salvar', 'logado'],
    ['POST', '/meu-perfil/aparencia',           'MeuPerfilController@aparencia', 'logado'],

    // ---------- Academia (Central de Televendas) ----------
    ['GET',  '/academia',                       'AcademiaController@inicio',      'academia.ver'],
    ['GET',  '/academia/trilha',                'AcademiaController@trilha',      'academia.ver'],
    ['GET',  '/academia/trilha/{id}',           'AcademiaController@modulo',      'academia.ver'],
    ['GET',  '/academia/produtos',              'AcademiaController@produtos',    'academia.ver'],
    ['GET',  '/academia/produtos/{slug}',       'AcademiaController@produto',     'academia.ver'],
    ['GET',  '/academia/atendimento',           'AcademiaController@atendimento', 'academia.ver'],
    ['GET',  '/academia/sistema',               'AcademiaController@sistema',     'academia.ver'],
    ['GET',  '/academia/sistema/{id}',          'AcademiaController@sistema',     'academia.ver'],
    ['GET',  '/academia/crm',                   'AcademiaController@crm',         'academia.ver'],
    ['GET',  '/academia/crm/{id}',              'AcademiaController@crm',         'academia.ver'],
    ['POST', '/academia/progresso',             'AcademiaController@marcar',      'academia.ver'],

    // ---------- Administração ----------
    ['GET',  '/usuarios',               'UsuarioController@index',    'usuarios.gerenciar'],
    ['GET',  '/usuarios/novo',          'UsuarioController@novo',     'usuarios.gerenciar'],
    ['POST', '/usuarios',               'UsuarioController@criar',    'usuarios.gerenciar'],
    ['GET',  '/usuarios/{id}/editar',   'UsuarioController@editar',   'usuarios.gerenciar'],
    ['POST', '/usuarios/{id}',          'UsuarioController@atualizar', 'usuarios.gerenciar'],

    ['GET',  '/acessos',                'AcessoController@index',     'usuarios.gerenciar'],
    ['POST', '/acessos',                'AcessoController@salvar',    'usuarios.gerenciar'],

    ['GET',  '/funcoes',                'FuncaoController@index',     'usuarios.gerenciar'],
    ['POST', '/funcoes',                'FuncaoController@criar',     'usuarios.gerenciar'],
    ['POST', '/funcoes/{id}',           'FuncaoController@atualizar', 'usuarios.gerenciar'],

    ['GET',  '/configuracoes',          'ConfigController@index',  'config.gerenciar'],
    ['POST', '/configuracoes',          'ConfigController@salvar', 'config.gerenciar'],

    ['GET',  '/configuracoes/listas',                 'ListaController@index',  'config.gerenciar'],
    ['POST', '/configuracoes/listas/{lista}',         'ListaController@criar',  'config.gerenciar'],
    ['POST', '/configuracoes/listas/{lista}/{id}',    'ListaController@salvar', 'config.gerenciar'],

    ['GET',  '/sistema/dados-de-teste',               'SimuladorController@index',     'sistema.atualizar'],
    ['POST', '/sistema/dados-de-teste/gerar',         'SimuladorController@gerar',     'sistema.atualizar'],
    ['POST', '/sistema/dados-de-teste/apagar',        'SimuladorController@apagar',    'sistema.atualizar'],
    ['GET',  '/sistema/atualizacao',                  'AtualizacaoController@index',   'sistema.atualizar'],
    ['POST', '/sistema/atualizacao/aplicar',          'AtualizacaoController@aplicar', 'sistema.atualizar'],
    ['POST', '/sistema/atualizacao/backup',           'AtualizacaoController@backup',  'sistema.atualizar'],

    ['GET',  '/integracao',             'IntegracaoController@index',       'integracao.gerenciar'],
    ['POST', '/integracao/testar',      'IntegracaoController@testar',      'integracao.gerenciar'],
    ['POST', '/integracao/sincronizar', 'IntegracaoController@sincronizar', 'integracao.gerenciar'],

    ['GET',  '/auditoria',              'AuditoriaController@index', 'auditoria.ver'],
];
