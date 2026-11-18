<?php
/**
 * Organograma de interligações: os setores da empresa (como aparecem no One) e o que um entrega para o outro.
 * Base: análise do código feita na V17.7 (quais telas e rotinas de cada setor leem e gravam os dados dos outros).
 *
 * COMO EDITAR (quando uma integração nova for feita):
 *  - 'setores': coluna (0 = sistemas de fora, 1 = Comercial, 2 = Financeiro e Gestão, 3 = Operação), url da tela,
 *    'numero' (indicador vivo, calculado em OrganogramaService::indicadores) e uma frase do que o setor faz.
 *  - 'ligacoes': [de, para, o que passa, situação]. Situação: 'ok' (integrado), 'parcial' (pela metade), 'ilha' (o dado não chega).
 *    Quando uma ilha for resolvida, troque para 'ok' e ajuste o texto.
 */
return [
    'colunas' => ['Sistemas de fora', 'Comercial', 'Financeiro e Gestão', 'Operação'],
    'setores' => [
        // sistemas de fora
        'omie' => ['nome' => 'Omie', 'tipo' => 'ERP', 'coluna' => 0, 'icone' => 'nuvem', 'url' => '/integracao', 'texto' => 'ERP: produtos, preços, estoque, faturamento, notas e títulos. É a fonte da verdade do fiscal e do financeiro.'],
        'nota10' => ['nome' => 'Nota10', 'tipo' => 'ERP antigo', 'coluna' => 0, 'icone' => 'livro', 'url' => '/sistema/nota10', 'texto' => 'Histórico de 2021 a 2026: clientes, pedidos, títulos, contatos, fornecedores e compras, trazidos pela importação.'],
        'tray' => ['nome' => 'Tray', 'tipo' => 'Loja virtual', 'coluna' => 0, 'icone' => 'carrinho', 'url' => '/marketing/ecommerce', 'numero' => 'tray', 'texto' => 'Pedidos da loja e dos marketplaces (cópia local a cada 15 min). Conta só pedido pago; sem dados pessoais do comprador.'],
        'liguetalk' => ['nome' => 'Ligue Talk', 'tipo' => 'Telefonia', 'coluna' => 0, 'icone' => 'telefone', 'url' => '/acompanhamento', 'texto' => 'Ligações e gravações das vendedoras e da cobrança, que vão para a linha do tempo do cliente.'],
        'mapas' => ['nome' => 'Google Maps', 'tipo' => 'Mapas', 'coluna' => 0, 'icone' => 'loja', 'url' => '/carteira', 'texto' => 'Localização exata das lojas: mapa da carteira e planejador de visitas.'],
        'cnpj' => ['nome' => 'Base de CNPJs', 'tipo' => 'Receita Federal', 'coluna' => 0, 'icone' => 'lupa', 'url' => '/prospeccao', 'texto' => 'Empresas ativas por cidade e atividade, para a prospecção.'],
        'ia' => ['nome' => 'One com IA', 'tipo' => 'Claude', 'coluna' => 0, 'icone' => 'faisca', 'url' => '/administracao', 'texto' => 'Especialistas por setor que consultam vendas, cobrança, produção, marketing e prospecção, sempre com o acesso de cada pessoa.'],
        // comercial
        'marketing' => ['nome' => 'Marketing', 'tipo' => 'Comercial', 'coluna' => 1, 'icone' => 'megafone', 'url' => '/marketing', 'numero' => 'marketing', 'texto' => 'Campanhas de preço e brinde, posts, tarefas, leads do site e anúncios.'],
        'prospeccao' => ['nome' => 'Prospecção e Reativação', 'tipo' => 'Comercial', 'coluna' => 1, 'icone' => 'lupa', 'url' => '/prospeccao', 'numero' => 'prospeccao', 'texto' => 'Leads novos, base de CNPJs e a fila de clientes sem compra.'],
        'vendas' => ['nome' => 'Televendas e Externo', 'tipo' => 'Comercial', 'coluna' => 1, 'icone' => 'pessoas', 'url' => '/carteira', 'numero' => 'vendas', 'texto' => 'Carteira, funil, pedidos pelo sistema, catálogo e app offline, agenda e recompra.'],
        'terceirizacao' => ['nome' => 'Terceirização', 'tipo' => 'Comercial', 'coluna' => 1, 'icone' => 'etiqueta', 'url' => '/terceirizacao', 'numero' => 'terceirizacao', 'texto' => 'Clientes de marca própria: produtos com a marca do cliente, pedidos e rótulos.'],
        // financeiro e gestão
        'aprovacao' => ['nome' => 'Aprovação de pedidos', 'tipo' => 'Financeiro', 'coluna' => 2, 'icone' => 'check', 'url' => '/aprovacao', 'numero' => 'aprovacao', 'texto' => 'Confere o crédito do cliente e aprova o pedido, que vai para o ERP.'],
        'cobranca' => ['nome' => 'Cobrança', 'tipo' => 'Financeiro', 'coluna' => 2, 'icone' => 'alerta', 'url' => '/cobranca', 'numero' => 'cobranca', 'texto' => 'Títulos vencidos da ficha do cliente viram casos; promessas, acordos e baixa.'],
        'gestao' => ['nome' => 'Gestão e Diretoria', 'tipo' => 'Gestão', 'coluna' => 2, 'icone' => 'grafico', 'url' => '/resultados', 'numero' => 'gestao', 'texto' => 'Painel de vendas, metas, equipe, acompanhamento e análise de clientes.'],
        // operação
        'deposito' => ['nome' => 'Depósito e Expedição', 'tipo' => 'Operação', 'coluna' => 3, 'icone' => 'caixa', 'url' => '/deposito', 'numero' => 'deposito', 'texto' => 'Fila de separação, lote e validade de cada item, despacho com rastreio.'],
        'producao' => ['nome' => 'Produção', 'tipo' => 'Operação', 'coluna' => 3, 'icone' => 'fabrica', 'url' => '/producao', 'numero' => 'producao', 'texto' => 'Ordens de produção, estoque da fábrica, matérias-primas e reabastecimento.'],
        'compras' => ['nome' => 'Compras', 'tipo' => 'Operação', 'coluna' => 3, 'icone' => 'moeda', 'url' => '/compras', 'numero' => 'compras', 'texto' => 'Cotações, pedidos de compra, notas lançadas e fornecedores.'],
        'desenvolvimento' => ['nome' => 'Desenvolvimento e Formulação', 'tipo' => 'Operação', 'coluna' => 3, 'icone' => 'folha', 'url' => '/desenvolvimento', 'numero' => 'desenvolvimento', 'texto' => 'Projetos de produto novo, fórmulas, fichas técnicas e documentos.'],
    ],
    'ligacoes' => [
        ['marketing', 'prospeccao', 'Leads do site e dos anúncios, com a campanha de origem (UTM)', 'ok'],
        ['cnpj', 'prospeccao', 'Empresas ativas por cidade, marcando quem já é cliente', 'ok'],
        ['prospeccao', 'vendas', 'Lead qualificado e cliente reativado voltam para um vendedor', 'ok'],
        ['vendas', 'prospeccao', 'Cliente sem compra há X dias vai para a fila de reativação', 'ok'],
        ['marketing', 'vendas', 'Campanhas de preço, faixas e brindes no catálogo e no pedido', 'ok'],
        ['vendas', 'aprovacao', 'Pedido para aprovar, com compras dos 12 meses e casos em cobrança', 'ok'],
        ['cobranca', 'vendas', 'Boleto bloqueado para quem tem título vencido; tarefas de contato', 'ok'],
        ['aprovacao', 'omie', 'Pedido aprovado vira orçamento no ERP', 'ok'],
        ['omie', 'cobranca', 'Títulos vão para a ficha do cliente; os vencidos entram na Cobrança e a baixa vale nos dois lados', 'ok'],
        ['omie', 'deposito', 'Pedido faturado entra na fila de separação', 'ok'],
        ['omie', 'vendas', 'Produtos, preços por tabela e estoque', 'ok'],
        ['tray', 'deposito', 'Pedidos pagos da loja virtual (o cancelado sai da fila)', 'ok'],
        ['tray', 'gestao', 'Vendas da loja virtual por dia, produto e estado', 'ok'],
        ['producao', 'deposito', 'Lote e validade de cada item', 'ok'],
        ['vendas', 'producao', 'Média de vendas define o reabastecimento; venda faturada baixa o estoque da fábrica', 'ok'],
        ['compras', 'producao', 'Nota de compra lançada soma o estoque da matéria-prima', 'ok'],
        ['desenvolvimento', 'producao', 'Fórmula aprovada vira a ficha técnica; lote piloto vira ordem', 'ok'],
        ['desenvolvimento', 'marketing', 'Tarefas do lançamento do produto novo', 'ok'],
        ['desenvolvimento', 'compras', 'Amostras e cotações do projeto', 'ok'],
        ['terceirizacao', 'producao', 'Produtos e rótulos com a marca do cliente', 'ok'],
        ['liguetalk', 'vendas', 'Ligações e gravações na linha do tempo', 'ok'],
        ['mapas', 'vendas', 'Localização das lojas (mapa e roteiros)', 'ok'],
        ['nota10', 'vendas', 'Clientes, pedidos e títulos de 2021 a 2026', 'ok'],
        ['ia', 'vendas', 'Especialistas do One consultam os dados de cada setor', 'ok'],
        ['vendas', 'gestao', 'Faturamento pela data do pedido, ligações, tarefas e metas', 'ok'],
        ['cobranca', 'gestao', 'Casos, promessas e valores em atraso', 'ok'],
        // pela metade e ilhas (o que falta ligar)
        ['omie', 'aprovacao', 'Limite de crédito e títulos a vencer ainda não aparecem no quadro de crédito', 'parcial'],
        ['producao', 'vendas', 'O estoque da fábrica não chega ao catálogo, ao pedido e ao app (lá é o estoque do ERP)', 'ilha'],
        ['nota10', 'compras', 'Fornecedores com CNPJ e histórico de compras não aparecem na tela de Fornecedores', 'ilha'],
        ['marketing', 'gestao', 'Não dá para saber quanto cada campanha de marketing vendeu', 'ilha'],
        ['nota10', 'vendas', 'Aniversário e contatos das pessoas da loja ficam só na ficha', 'ilha'],
    ],
];
