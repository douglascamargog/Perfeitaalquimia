<?php
/**
 * Abas do sistema para a tela Administração › Acessos.
 *
 * Cada aba tem a permissão de VER e a de EDITAR:
 *  - 'ver' => null  → a aba é "tem acesso / não tem" (a tela não tem modo só leitura);
 *  - quem tem 'editar' também vê.
 * As "ações especiais" são permissões avulsas (caixinhas na mesma tela).
 * O perfil Admin sempre tem tudo e não aparece na grade.
 */
return [
    'abas' => [
        // grupo => [ [título, ver, editar, observação], ... ]
        'Comercial' => [
            ['Carteira, Agenda e Reativação', null, 'carteira.ver_propria', 'Cada um vê e trabalha a própria carteira ("ver todas" fica nas ações especiais)'],
            ['Meu mês', null, 'meu_mes.usar', ''],
            ['Recompra', null, 'recompra.ver', ''],
            ['Prospecção', 'prospeccao.ver', 'prospeccao.usar', 'Ver: a fila e a base de CNPJs · Editar: enviar empresas para o funil'],
            ['App do externo', null, 'app.usar', ''],
            ['Pedidos', 'pedidos.ver_todos', 'pedidos.criar', 'Ver: todos os pedidos · Editar: criar pedidos (vê os próprios)'],
            ['Aprovação de pedidos', null, 'pedidos.aprovar', ''],
            ['Equipe de vendas', null, 'equipe.ver', 'Meta da equipe, números das frentes e ranking'],
            ['Terceirização', 'terceirizacao.ver', 'terceirizacao.editar', 'Editar: produtos dos clientes e registro de rótulos'],
            ['Cobrança', 'cobranca.ver', 'cobranca.gerenciar', ''],
        ],
        'Gestão' => [
            ['Painel de vendas', null, 'painel.ver', ''],
            ['Metas', 'metas.ver', 'metas.gerenciar', ''],
        ],
        'Marketing' => [
            ['Mural de posts e Planejamento', 'marketing.ver', 'marketing.editar', ''],
            ['E-commerce', null, 'ecommerce.ver', ''],
            ['Leads do site', null, 'leads.ver', ''],
        ],
        'Conteúdo' => [
            ['Produtos', 'produtos.ver', 'catalogo.editar', ''],
            ['Academia', 'academia.ver', 'academia.editar', ''],
            ['Mensagens prontas', 'mensagens.ver', 'mensagens.gerenciar', 'Usar as mensagens na ficha do cliente fica nas ações especiais'],
        ],
        'Produção' => [
            ['Produção e matérias-primas', 'producao.ver', 'producao.gerenciar', ''],
        ],
        'Administração' => [
            ['Usuários, Funções e Acessos', null, 'usuarios.gerenciar', 'Cuidado: quem tem isto pode mudar os acessos de todos'],
            ['Comunicados (emitir)', null, 'comunicados.emitir', 'Todos recebem; aqui é quem pode enviar'],
            ['Configurações e Listas', null, 'config.gerenciar', ''],
            ['Integração Omie', null, 'integracao.gerenciar', ''],
            ['Atualização e backup', null, 'sistema.atualizar', ''],
            ['Auditoria', null, 'auditoria.ver', ''],
        ],
    ],
    'especiais' => [
        'carteira.ver_todas'  => 'Ver todas as carteiras (não só a própria)',
        'carteira.transferir' => 'Transferir cliente para outro vendedor',
        'carteira.importar'   => 'Importar e exportar planilhas da carteira',
        'credito.ver'         => 'Ver crédito e títulos do cliente',
        'cobranca.enviar'     => 'Enviar cliente para a Cobrança',
        'contatos.buscar_ia'  => 'Buscar contatos na internet com IA',
        'catalogo.acessos'    => 'Gerar acesso do cliente ao catálogo',
        'mensagens.usar'      => 'Usar mensagens prontas na ficha do cliente',
        'marketing.aprovar'   => 'Aprovar posts antes da publicação',
        'midia.gerenciar'     => 'Biblioteca de mídia',
    ],
];
