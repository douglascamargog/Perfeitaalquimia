<?php
/**
 * Textos da Academia que, na Central de Televendas V2, ficavam fixos dentro do programa:
 * a página "Nossa empresa", o módulo "Mídias do marketing" e o "Guia de atendimento".
 *
 * Aqui eles viram DADOS (gravados na tabela academia_conteudo pelo seed 006).
 * O texto foi mantido igual ao original; só mudou a organização.
 * Os links (catálogo, grupo do WhatsApp, site, Instagram) ficam em Configurações › Academia.
 */
return [
    // ------------------------------------------------------------------
    'empresa' => [
        'sobretitulo' => 'Boas-vindas · 5 min de leitura',
        'titulo'      => 'Prazer, somos a Perfeita Alquimia.',
        'chamada'     => 'Produtos naturais e suplementos, conhecimento sobre o que oferecemos e proximidade com quem escolhe a nossa marca.',
        'selos'       => ['São Leopoldo · RS', 'Fabricação própria', 'Qualidade de vida em doses naturais.'],
        'quem_somos'  => [
            'titulo'     => 'Bem-estar está no centro do nosso trabalho.',
            'paragrafos' => [
                'A Perfeita Alquimia atua no segmento de produtos naturais e suplementos, com fabricação própria e sede em São Leopoldo, no Rio Grande do Sul. Nosso portfólio reúne cápsulas, chás, gotas, líquidos, pós e ingredientes alimentares.',
                'A marca tem como propósito contribuir para uma rotina de saúde e bem-estar. Confiança, segurança e inovação orientam essa proposta. Para quem chega ao televendas, conhecer a empresa é o primeiro passo para apresentar nossos produtos com clareza.',
            ],
        ],
        'frentes' => [
            'titulo' => 'Entenda as frentes do negócio',
            'itens'  => [
                ['Marca própria', 'Produtos comercializados com a identidade Perfeita Alquimia. O catálogo reúne linhas e apresentações para diferentes preferências de consumo.'],
                ['Venda para lojistas', 'Relacionamento com lojas de produtos naturais, suplementos e outros revendedores. O atendimento ajuda a organizar a escolha dos produtos e a reposição de estoque.'],
                ['Terceirização', 'Fabricação de produtos para outras marcas. É uma frente com projetos próprios, em que o cliente busca desenvolver sua linha para comercialização.'],
            ],
            'nota' => 'A empresa também atende o consumidor final pela loja virtual e por marketplaces. No televendas, o foco deste treinamento é a conversa com o lojista e a continuidade desse relacionamento.',
        ],
        'linhas' => [
            'titulo' => 'Conheça nossas linhas',
            // [linha, descrição, produto de exemplo (slug)]
            'itens'  => [
                ['Cápsulas', 'Vitaminas, minerais e associações com diferentes ingredientes.', 'magnesio'],
                ['Pós e bebidas', 'Fórmulas para preparo, com opções de sabores e tamanhos.', 'magnesio_po'],
                ['Gotas e líquidos', 'Apresentações líquidas com composições próprias.', 'colageno-hidrolisado-verisol-30ml-perfeita-alquimia'],
                ['Chás e infusões', 'Ervas a granel e sachês para diferentes formas de preparo.', 'camomila-10-saches-perfeita-alquimia'],
                ['Dose única', 'Produtos em sachês individuais, apresentados em displays.', 'display-cappuccino-zero-12-saches-perfeita-alquimia'],
                ['Alimentos e especiarias', 'Ingredientes para compor receitas e a rotina alimentar.', 'catalogo-233882'],
            ],
        ],
        'papel' => [
            'titulo'  => 'Você conecta a empresa à realidade de cada loja.',
            'chamada' => 'É na conversa que entendemos o que o lojista procura, quais itens têm saída e como podemos participar do próximo pedido.',
            'itens'   => [
                ['Ouça antes de oferecer', 'Conheça o perfil da loja, a procura dos clientes e o objetivo da compra.'],
                ['Explique com clareza', 'Apresente o produto, seus ingredientes e a diferença entre as opções.'],
                ['Cuide da continuidade', 'Registre o combinado no sistema, acompanhe o pedido e organize o próximo contato.'],
            ],
        ],
        'apresentacao' => [
            'titulo'  => 'Como apresentar a empresa',
            'chamada' => 'Use esta sugestão como ponto de partida e adapte a conversa ao perfil da loja.',
            'fala'    => 'Sou da Perfeita Alquimia, uma empresa de produtos naturais e suplementos com fabricação própria em São Leopoldo, no Rio Grande do Sul. Trabalhamos com cápsulas, chás, gotas e outras apresentações. Quero conhecer um pouco da sua loja para apresentar as linhas que fazem sentido para o seu público.',
        ],
    ],

    // ------------------------------------------------------------------
    'marketing' => [
        'titulo'  => 'Mídias que ajudam a divulgar e vender.',
        'chamada' => 'Aprenda onde encontrar os materiais do marketing e como mostrar esse caminho ao cliente. O lojista também tem acesso às mídias dos produtos pelo nosso catálogo.',
        'canais'  => [
            ['Para sua rotina', 'Grupo do marketing', 'Acompanhe as novas mídias de campanhas, promoções e divulgação pelo grupo de WhatsApp. Leia a orientação que acompanha cada envio antes de usar a peça no atendimento.', 'whatsapp', 'Abrir grupo no WhatsApp'],
            ['Para você e para o cliente', 'Mídias pelo catálogo', 'Cada produto tem um botão que leva à sua pasta no Drive. Mostre esse acesso ao lojista para ele baixar os materiais dos produtos que comprou sempre que precisar.', 'catalogo', 'Abrir catálogo digital'],
        ],
        'passos_catalogo' => [
            'titulo' => 'Encontre o produto e abra suas mídias.',
            'itens'  => [
                ['Abra o catálogo digital.', 'Use o atalho desta página ou o item Catálogo de produtos nos atalhos da Academia.'],
                ['Vá até a página do produto.', 'Localize o produto cujas mídias você ou o cliente querem baixar.'],
                ['Clique em “Baixar imagens do produto”.', 'Esse botão abre a pasta do produto no Google Drive. Na imagem, ele está destacado em vermelho.'],
                ['Ensine o mesmo caminho ao cliente.', 'Envie o link do catálogo junto com a orientação do botão. Assim, o lojista aprende a encontrar as mídias de outros produtos também.'],
            ],
            'imagem' => 'catalog',
        ],
        'pastas' => [
            'titulo'  => 'Escolha uma das quatro pastas.',
            'chamada' => 'Cada pasta organiza um tipo de material. Pergunte onde o cliente quer divulgar e indique a opção mais útil para ele.',
            // [ícone, pasta, para quê, descrição, dica]
            'itens'   => [
                ['phone', 'Stories', 'Para Stories e Status', 'Use os materiais desta pasta nas publicações verticais do Instagram Stories e no Status do WhatsApp.', 'O cliente quer divulgar no Status? Comece aqui.'],
                ['photo', 'Site- Fundo Branco', 'Para apresentar o produto', 'Fotos do produto com fundo branco, úteis para a loja virtual, a vitrine digital e os cadastros de produtos do cliente.', 'O cliente precisa de uma foto limpa do produto? Indique esta pasta.'],
                ['layers', 'Sem fundo / PNG', 'Para criar uma composição', 'Imagens do produto sem fundo, em PNG, para inserir em uma arte, combinar com o visual da loja ou encaminhar a quem cria suas peças.', 'O cliente quer montar sua própria arte? Mostre esta opção.'],
                ['grid', 'Feed Instagram', 'Para o feed da loja', 'Materiais de divulgação para as publicações do feed do Instagram. Ajude o cliente a escolher a mídia do produto que pretende apresentar.', 'O cliente quer fazer uma publicação no feed? Este é o caminho.'],
            ],
            'imagem' => 'folders',
        ],
        'baixar' => [
            'titulo' => 'Baixe o material para usar na divulgação.',
            'itens'  => [
                ['Abra a pasta do formato desejado.', 'Por exemplo: Stories, se a ideia for publicar no Status do WhatsApp.'],
                ['Escolha e abra o arquivo.', 'Veja a mídia para conferir o produto e o conteúdo que será divulgado.'],
                ['Use a opção de download do Drive.', 'Baixe o arquivo no computador ou celular. Depois, use a mídia no canal escolhido.'],
            ],
            'imagem' => 'stories',
            'nota'   => 'A imagem mostra um exemplo de organização. Novas mídias podem ser adicionadas às pastas pelo marketing.',
        ],
        'beneficio' => [
            'titulo'  => 'O cliente compra e recebe apoio para divulgar.',
            'chamada' => 'Não espere o cliente pedir uma imagem. Conte que a Perfeita Alquimia fornece todo o material de marketing dos produtos e mostre como ele pode acessar pelo catálogo.',
            'momentos' => [
                ['Na apresentação', 'Valorize o apoio de marketing como um benefício para o lojista divulgar os produtos.'],
                ['Depois do pedido', 'Envie o catálogo e ensine o caminho até as mídias dos produtos comprados.'],
                ['Quando pedir uma mídia', 'Ajude no acesso e incentive o cliente a salvar o catálogo para as próximas consultas.'],
            ],
        ],
        // {catalogo} é trocado pelo link do catálogo configurado
        'mensagens' => [
            ['Para apresentar o benefício ao lojista', 'Além dos produtos, a Perfeita Alquimia fornece todo o material de marketing para ajudar sua loja na divulgação. Você pode baixar as mídias pelo nosso catálogo: abra a página do produto e clique em “Baixar imagens do produto”. No Drive, escolha entre Stories, Site- Fundo Branco, Sem fundo / PNG e Feed Instagram. Salve o link para acessar os materiais sempre que precisar: {catalogo}'],
            ['Para enviar após o pedido', 'Agora que fechamos seu pedido, você já pode preparar a divulgação! A Perfeita Alquimia fornece todo o material de marketing dos produtos. No catálogo, encontre cada produto que comprou e clique em “Baixar imagens do produto”. A pasta no Drive reúne as opções para Stories, fotos com fundo branco, imagens sem fundo / PNG e Feed Instagram. Salve este link para acessar quando precisar: {catalogo}'],
            ['Para responder a um pedido de mídia', 'Claro! As mídias ficam disponíveis no nosso catálogo: {catalogo} Abra a página do produto e clique em “Baixar imagens do produto”. Você será direcionado à pasta dele no Drive. Depois, escolha a pasta do formato que quer usar e baixe o arquivo. Assim você pode pegar os materiais dos produtos que comprou sempre que precisar. Se tiver dificuldade no primeiro acesso, eu te ajudo!'],
        ],
        'autonomia' => 'O objetivo é dar autonomia com um bom atendimento. Se o cliente tiver dificuldade, acompanhe o primeiro acesso. Ao aprender o caminho, ele pode escolher e baixar os materiais dos produtos que comprou, sem precisar pedir um novo envio à televendas toda vez.',
        'pratica' => [
            'Abra o catálogo, encontre um produto e clique em “Baixar imagens do produto”.',
            'Identifique as quatro pastas e escolha onde buscaria uma mídia para o Status.',
            'Explique o passo a passo a um colega como se ele fosse seu cliente.',
        ],
    ],

    // ------------------------------------------------------------------
    'atendimento' => [
        'chamada' => 'Exemplos para adaptar ao cliente e à política comercial vigente. Os campos entre colchetes devem ser preenchidos antes do envio.',
        // [situação, fala, orientação]
        'falas' => [
            ['Primeiro contato com um lojista', 'Olá, [nome]! Sou [seu nome], da Perfeita Alquimia. Você é a pessoa responsável pelas compras da loja? Quero entender quais linhas vocês trabalham para preparar uma seleção de produtos que faça sentido para o seu público. Posso te fazer duas perguntas rápidas?', 'Objetivo: confirmar o contato e iniciar o diagnóstico, sem disparar uma oferta genérica.'],
            ['Envio de proposta', '[Nome], preparei a proposta com os itens que conversamos. Nela estão as apresentações, quantidades, valores, frete e condições de pagamento. A validade é [data]. Podemos revisar juntos em [dia e horário combinado]?', 'Antes de enviar, confira estoque, total e autorização das condições.'],
            ['“Está caro”', 'Entendo, [nome]. Você está comparando o valor por unidade ou o investimento total? Se me disser qual apresentação e quantidade está considerando, consigo conferir a comparação e ajustar o mix dentro das condições disponíveis.', 'Investigue a comparação. Não ofereça um desconto que dependa de aprovação.'],
            ['“Vou pensar” ou “agora não”', 'Claro. O que ainda precisa avaliar: os produtos, o investimento ou o momento da compra? Se fizer sentido, combinamos um retorno em [data], assim eu te procuro com a informação que falta.', 'Se não houver interesse em novos contatos, respeite a decisão e registre a preferência.'],
            ['“Esse produto trata minha condição?”', 'Posso te passar a composição e as informações do rótulo. Para saber se o uso é adequado à sua condição ou junto aos seus medicamentos, é necessário conversar com um profissional de saúde. Vou confirmar com nosso responsável técnico qualquer dúvida sobre o produto.', 'Não faça diagnóstico ou prescrição. Não prometa tratamento, prevenção ou cura com suplementos.'],
            ['Cliente relata problema no pedido', '[Nome], vou registrar e encaminhar a situação para a área responsável. Pode me confirmar o número do pedido e qual foi a divergência? Depois da conferência, retorno em [prazo combinado] com a posição do atendimento.', 'Registre apenas os dados necessários. Não prometa troca, reembolso ou reenvio antes da autorização aplicável.'],
            ['Recompra e reposição', 'Olá, [nome]! Como foi a saída dos produtos do último pedido? Quais itens estão próximos de acabar? Posso preparar uma reposição com base no que teve mais procura na sua loja.', 'Use o histórico para ajudar o cliente a repor o estoque, sem presumir demanda.'],
        ],
        'condicoes' => [
            'Na cotação, apresente preços, descontos, pagamento, frete e prazo de entrega de acordo com as condições comerciais da sua carteira. Registre o que foi combinado para dar continuidade ao pedido.',
            'Condições da loja virtual, de campanhas e de outros canais não devem ser aplicadas automaticamente ao televendas.',
        ],
    ],
];
