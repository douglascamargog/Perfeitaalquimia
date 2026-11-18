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
            ['Planejador de visitas', 'roteiros.gerenciar', 'roteiros.usar', 'Quem é da função Externo já usa. Ver: os roteiros de todos · Editar: montar os próprios roteiros'],
            ['Pedidos', 'pedidos.ver_todos', 'pedidos.criar', 'Ver: todos os pedidos · Editar: criar pedidos (vê os próprios)'],
            ['Aprovação de pedidos', null, 'pedidos.aprovar', ''],
            ['Equipe de vendas', null, 'equipe.ver', 'Meta da equipe, números das frentes e ranking'],
            ['Terceirização', 'terceirizacao.ver', 'terceirizacao.editar', 'Editar: produtos dos clientes e registro de rótulos'],
            ['Cobrança', 'cobranca.ver', 'cobranca.gerenciar', ''],
            ['Campanhas comerciais', 'campanhas.ver', 'campanhas.gerenciar', 'Ver: campanhas no ar e agendadas · Editar: criar campanhas, faixas, brindes, promoções e o visual do catálogo'],
        ],
        'Gestão' => [
            ['Painel de vendas', null, 'painel.ver', ''],
            ['Análise de clientes', null, 'clientes.analise', 'Todos os clientes da empresa com filtros (UF, cidade, vendedor...), média mensal e ticket'],
            ['Metas', 'metas.ver', 'metas.gerenciar', ''],
            ['Acompanhamento (ligações e gravações)', null, 'acompanhamento.ver', 'A gestora do setor vê a equipe dela.'],
            ['Carteiras (configuração)', null, 'carteiras.gerenciar', 'Passar carteiras, mover e importar clientes. A gestora do setor usa nas carteiras do setor dela.'],
        ],
        'Marketing' => [
            ['Mural de posts e Planejamento', 'marketing.ver', 'marketing.editar', ''],
            ['E-commerce', null, 'ecommerce.ver', ''],
            ['Leads do site', null, 'leads.ver', ''],
        ],
        'Conteúdo' => [
            ['Produtos', 'produtos.ver', 'produtos.tecnico', 'Ver: produtos e documentos (também o Catálogo, só leitura) · Editar: parte técnica (composição, ficha técnica, embalagens e documentos)'],
            ['Catálogo e conteúdo dos produtos (editar)', null, 'catalogo.editar', 'Vitrine do cliente (visível, chamada, ordem, códigos) e o texto dos produtos'],
            ['Treinamento (editar)', null, 'academia.editar', 'Todos fazem o próprio treinamento. Editar: módulos, provas e o passo a passo do One em cada tela'],
            ['Treinamento da equipe', null, 'treinamento.equipe', 'Ver o progresso de cada pessoa e reiniciar o tutorial das telas'],
            ['Mensagens prontas', 'mensagens.ver', 'mensagens.gerenciar', 'Usar as mensagens na ficha do cliente fica nas ações especiais'],
        ],
        'Desenvolvimento de Produto' => [
            ['Fórmulas (Responsável Técnica)', 'formulas.ver', 'formulas.editar', 'Editar: criar fórmulas, versões e testes. Aprovar (vira a ficha técnica) fica nas ações especiais.'],
            ['Projetos de produto novo', 'desenvolvimento.ver', 'desenvolvimento.gerenciar', 'Editar: criar, pausar e voltar etapa. Cada etapa é concluída por quem é da função dela (Técnica, Marketing, Qualidade, Produção).'],
        ],
        'Produção' => [
            ['Produção e matérias-primas', 'producao.ver', 'producao.gerenciar', ''],
        ],
        'Depósito e compras' => [
            ['Depósito (separação, despacho, lotes, recebimento)', 'deposito.ver', 'deposito.operar', 'Ver: a fila e os despachos · Editar: separar, despachar e receber'],
            ['Compras da empresa (Financeiro)', 'compras.ver', 'compras.lancar', 'Ver: lançamentos e itens comprados · Editar: lançar compras/NF, fornecedores e documentos dos itens (a função Qualidade também lança)'],
            ['Orçamentos e compras', 'compras.ver', 'compras.gerenciar', 'Quem é da função Qualidade já faz orçamentos. Quem é escolhido para aprovar recebe o orçamento no sino.'],
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
        'carteira.excluir'    => 'Excluir, restaurar e juntar lojas repetidas',
        'credito.ver'         => 'Ver crédito e títulos do cliente',
        'cobranca.enviar'     => 'Enviar cliente para a Cobrança',
        'contatos.buscar_ia'  => 'Buscar contatos na internet com IA',
        'catalogo.acessos'    => 'Gerar acesso do cliente ao catálogo',
        'mensagens.usar'      => 'Usar mensagens prontas na ficha do cliente',
        'marketing.aprovar'   => 'Aprovar posts antes da publicação',
        'midia.gerenciar'     => 'Biblioteca de mídia',
        'formulas.aprovar'    => 'Aprovar fórmulas (a aprovada vira a ficha técnica do produto)',
        'acompanhar.somente'  => 'Diretoria: só acompanha e aprova (vê, mas não altera nada)',
        'diretoria.vende'     => 'Diretoria que também vende (trabalha a própria carteira, pedidos e orçamentos)',
    ],
];
