<?php
/**
 * Treinamento (V13.0) — trilhas do Marketing (Marketing, Social Media, Gestor de Marketing, E-commerce)
 * e da Operação (Produção, Responsável Técnica, Qualidade, Depósito). Formato igual ao de treinamento_integracao.php.
 */

$tarefasMkt = <<<'TXT'
## O Início do Marketing
Em [Início do Marketing](/marketing/inicio) ficam o resumo do mês, o calendário e o **quadro de tarefas**: a fazer → fazendo → aprovação → feito. Arraste o cartão para mudar a etapa.

## Pedidos da empresa
Os setores pedem artes, materiais, cadastros e envios em **Pedir ao Marketing**. Cada pedido vira tarefa com prazo. Quando você termina, **quem pediu recebe o aviso** no sino.

## Aprovação
Tarefas e posts que precisam de aprovação vão para a coluna "aprovação"; o Gestor de Marketing aprova ou devolve com o comentário.

> Prazo apertado? Combine com quem pediu pelo próprio pedido: fica tudo registrado.
TXT;
$tarefasPerguntas = [
    ['Você terminou uma arte pedida pelo televendas. Como a vendedora fica sabendo?', ['Recebe o aviso no sino quando a tarefa é concluída', 'Precisa adivinhar', 'Por carta', 'Não fica sabendo'], 0, 'Concluiu, o One avisa quem pediu.'],
    ['Para onde vai uma tarefa que precisa do OK do gestor?', ['Para a coluna "aprovação"', 'Para o lixo', 'Para a Cobrança', 'Para o Depósito'], 0, 'O Gestor de Marketing aprova ou devolve.'],
];

return [
    // =====================================================================
    ['chave' => 'marketing', 'funcao' => 'marketing', 'titulo' => 'Marketing', 'icone' => '📣',
     'vizinhos' => 'televendas,ecommerce,tecnica,social_media',
     'descricao' => 'Tarefas e pedidos da empresa, campanhas, conteúdo dos produtos e leads.',
     'modulos' => [
        ['mk-tarefas', 'setor', 'Tarefas e pedidos ao Marketing', 'O quadro do setor e os pedidos que chegam da empresa.', 7, $tarefasMkt, $tarefasPerguntas],
        ['mk-produtos', 'setor', 'Conteúdo dos produtos e catálogo', 'O mesmo cadastro alimenta catálogo, Biblioteca e loja.', 7, <<<'TXT'
## Um cadastro só
O produto tem **uma** página em [Produtos](/produtos): foto, chamada, descrição, composição e documentos. O mesmo conteúdo aparece no **catálogo do cliente** e na **Biblioteca de produtos** do treinamento.

## O catálogo
Em [Catálogo](/produtos/catalogo) você decide o que aparece, a ordem e a chamada. **Preço e estoque nunca aparecem sem login** do cliente.

## Campanhas e visual
Em [Campanhas](/campanhas) › Visual do catálogo: cores e banner. Cada campanha pode mudar o visual enquanto estiver no ar.

! Textos de produto: nada de promessa de cura ou tratamento. Na dúvida, valide com a Responsável Técnica.
TXT, [
            ['Onde o cliente vê o preço dos produtos?', ['No catálogo, só depois de entrar com o login dele', 'Na página pública, para todos', 'No Instagram', 'Em lugar nenhum'], 0, 'Preço e estoque só aparecem com login.'],
            ['Mudou a foto de um produto. Onde ela aparece?', ['No catálogo e na Biblioteca de produtos: é o mesmo cadastro', 'Só no catálogo', 'Só no Instagram', 'Em lugar nenhum'], 0, 'Um cadastro só alimenta tudo.'],
        ]],
        ['mk-leads', 'setor', 'Leads, campanhas e mensagens', 'Do anúncio ao funil da Prospecção.', 6, <<<'TXT'
## Leads do site e dos anúncios
O formulário do site e dos anúncios cria o lead no funil da Prospecção, com a **campanha (UTM)** de origem. Em [Leads do site](/leads) você vê quantos vieram de cada campanha e quantos viraram clientes.

## Mensagens prontas
Em [Mensagens prontas](/mensagens) ficam os modelos de WhatsApp da equipe. Use {nome} e {link_vendedor}.

## Planejamento
Em Planejamento ficam as campanhas e datas comemorativas na linha do tempo. Planeje com antecedência com o Comercial.
TXT, [
            ['Como saber de qual anúncio veio um lead?', ['Pela campanha (UTM) registrada no lead', 'Perguntando ao cliente', 'Não tem como', 'Pelo CNPJ'], 0, 'O UTM vem junto com o formulário.'],
        ]],
        ['mk-vizinho', 'vizinho', 'Como trabalha o Marketing', 'Como pedir e o que esperar do Marketing.', 4, <<<'TXT'
## O que o Marketing faz
Artes, posts, campanhas, catálogo, cadastro de produtos, materiais para lojistas e leads dos anúncios.

## Como pedir
Em [Pedir ao Marketing](/marketing/pedir): diga **o que** precisa, **para quem** (cliente, campanha), **para quando** e anexe referências. O pedido vira tarefa e você é avisado quando fica pronto.

## Prazos
Peça com antecedência. Urgência de verdade? Explique o motivo no pedido.
TXT, [
            ['Qual o jeito certo de pedir uma arte?', ['Em Pedir ao Marketing, com prazo e referência', 'Pelo corredor', 'Por áudio no WhatsApp pessoal', 'Não pedir'], 0, 'O pedido fica registrado e você é avisado quando fica pronto.'],
        ]],
    ]],
    // =====================================================================
    ['chave' => 'social_media', 'funcao' => 'social_media', 'titulo' => 'Social Media', 'icone' => '📱',
     'vizinhos' => 'marketing,gestor_marketing,ecommerce',
     'descricao' => 'Posts, artes e o calendário das redes, com aprovação.',
     'modulos' => [
        ['sm-mural', 'setor', 'O mural de posts', 'Calendário do Instagram, prévia do feed e aprovação.', 7, <<<'TXT'
## O calendário
Em [Mural de posts](/marketing) cada post fica no dia em que vai ao ar: arte, legenda, formato (feed, story, reels) e situação.

## Do rascunho ao ar
1. **Novo post:** arte, legenda e data.
2. Mande para **aprovação**.
3. O Gestor de Marketing aprova ou devolve com comentário.
4. Aprovado, agende na rede social.

## Prévia do feed
A aba "Prévia do feed" mostra como o perfil fica com os próximos posts: confira as cores e a sequência.
TXT, [
            ['O que vem antes de agendar o post na rede social?', ['A aprovação do Gestor de Marketing', 'Nada', 'A aprovação do Depósito', 'O pedido mínimo'], 0, 'Posts passam pela aprovação.'],
        ]],
        ['sm-tarefas', 'setor', 'Tarefas e pedidos ao Marketing', 'O quadro do setor e os pedidos que chegam da empresa.', 7, $tarefasMkt, $tarefasPerguntas],
        ['sm-vizinho', 'vizinho', 'Como trabalha o Social Media', 'Posts e redes da empresa.', 3, <<<'TXT'
## O que o Social Media faz
Cria os posts e as artes das redes e dos materiais de divulgação, seguindo o calendário do Mural de posts, com aprovação do Gestor de Marketing.

## Como ajudar
Tem uma novidade, lançamento ou foto boa de cliente? Mande em Pedir ao Marketing: vira pauta.
TXT, [
            ['Tem uma novidade boa para as redes. Como mandar?', ['Em Pedir ao Marketing', 'Postar no seu perfil pessoal', 'Guardar para você', 'No sino'], 0, 'Vira pauta no calendário.'],
        ]],
    ]],
    // =====================================================================
    ['chave' => 'gestor_marketing', 'funcao' => 'gestor_marketing', 'titulo' => 'Gestor de Marketing', 'icone' => '🎯',
     'vizinhos' => 'marketing,social_media,tecnica,qualidade',
     'descricao' => 'Aprovar tudo do Marketing, criar os rótulos e planejar as campanhas.',
     'modulos' => [
        ['gm-aprovar', 'setor', 'Aprovar tarefas e posts', 'A coluna de aprovação e o comentário que ajuda.', 5, <<<'TXT'
## O que espera por você
Tarefas e posts na coluna **aprovação** do [Início do Marketing](/marketing/inicio). Aprove ou **devolva com comentário claro** ("trocar a foto pelo pote de 60 cápsulas").

## Planejamento
Campanhas e datas em Planejamento, combinadas com o Comercial e com as [Campanhas](/campanhas) do catálogo.
TXT, [
            ['Ao devolver uma arte, o que ajuda a equipe?', ['Um comentário claro do que mudar', 'Só "refazer"', 'Nada', 'Apagar a tarefa'], 0, 'Comentário claro evita idas e vindas.'],
        ]],
        ['gm-rotulos', 'setor', 'Rótulos e embalagens', 'Do projeto de produto novo à gráfica.', 6, <<<'TXT'
## No projeto de produto novo
Em [Produtos novos](/desenvolvimento), a etapa de rótulo e embalagem é do Marketing. A Responsável Técnica valida os textos obrigatórios (composição, tabela nutricional, advertências) e a Qualidade cuida do código de barras e da gráfica.

## Cuidados
- Use a **tabela nutricional** gerada pelo sistema a partir da ficha técnica.
- Confira o texto com a Técnica antes de mandar para a gráfica.
TXT, [
            ['Quem valida os textos obrigatórios do rótulo?', ['A Responsável Técnica', 'O Depósito', 'O cliente final', 'Ninguém'], 0, 'Textos legais passam pela Técnica.'],
        ]],
        ['gm-tarefas', 'setor', 'Tarefas e pedidos ao Marketing', 'O quadro do setor e os pedidos que chegam da empresa.', 7, $tarefasMkt, $tarefasPerguntas],
        ['gm-vizinho', 'vizinho', 'Como trabalha o Gestor de Marketing', 'Aprovações e rótulos.', 3, <<<'TXT'
## O que o Gestor de Marketing faz
Aprova o que o Marketing produz (posts, artes, materiais) e cria os rótulos dos produtos novos, junto com a Técnica e a Qualidade.

## Para a Técnica e a Qualidade
O rótulo chega para validação dentro do projeto de produto novo. Valide no próprio projeto para tudo ficar registrado.
TXT, [
            ['Onde validar o rótulo de um produto novo?', ['No próprio projeto em Produtos novos', 'Por e-mail', 'Na Cobrança', 'Na Agenda'], 0, 'Fica registrado no projeto.'],
        ]],
    ]],
    // =====================================================================
    ['chave' => 'ecommerce', 'funcao' => 'ecommerce', 'titulo' => 'E-commerce e Marketplaces', 'icone' => '🛒',
     'vizinhos' => 'deposito,marketing,financeiro',
     'descricao' => 'Loja virtual e marketplaces: perguntas, campanhas, cadastros e envios.',
     'modulos' => [
        ['ec-loja', 'setor', 'A loja virtual e os marketplaces', 'Números, pedidos e o que acompanhar todo dia.', 6, <<<'TXT'
## Os números
Em [E-commerce](/marketing/ecommerce): pedidos, faturamento e produtos mais vendidos no período.

## Todo dia
- Responder as **perguntas** dos marketplaces rápido (a nota da loja depende disso).
- Conferir pedidos novos e os que estão parados.
- Acompanhar campanhas de desconto e ofertas relâmpago.

## Pedidos do e-commerce no Depósito
Os pedidos da loja chegam ao Depósito ("Buscar pedidos da Tray") com separação própria.
TXT, [
            ['Por que responder rápido as perguntas dos marketplaces?', ['A nota e a visibilidade da loja dependem disso', 'Não importa', 'Para gastar tempo', 'Por causa do Omie'], 0, 'Tempo de resposta conta na reputação.'],
        ]],
        ['ec-cadastro', 'setor', 'Cadastro de produtos e envios FULL', 'Fotos, descrições e estoque nos centros de distribuição.', 5, <<<'TXT'
## Cadastro
Use as fotos e textos do cadastro único em [Produtos](/produtos). Nada de promessa de cura nos anúncios.

## Envios FULL
Para os centros de distribuição dos marketplaces, combine com o Depósito a separação e com a Produção o estoque acabado.
TXT, [
            ['Envio FULL para o marketplace: com quem combinar a separação?', ['Com o Depósito', 'Com a Cobrança', 'Com a Prospecção', 'Com ninguém'], 0, 'O Depósito separa e despacha.'],
        ]],
        ['ec-tarefas', 'setor', 'Tarefas e pedidos ao Marketing', 'O quadro do setor e os pedidos que chegam da empresa.', 7, $tarefasMkt, $tarefasPerguntas],
        ['ec-vizinho', 'vizinho', 'Como trabalha o E-commerce', 'Loja virtual e marketplaces.', 3, <<<'TXT'
## O que o E-commerce faz
Cuida da loja virtual e dos marketplaces: cadastros, perguntas, campanhas, ofertas e envios para os centros de distribuição.

## Para o Depósito
Pedidos do e-commerce têm prazo de despacho curto: eles aparecem separados na fila de separação.
TXT, [
            ['Pedidos do e-commerce no Depósito:', ['Aparecem separados e têm prazo de despacho curto', 'São iguais aos de lojista', 'Não passam pelo Depósito', 'Vão para a Cobrança'], 0, 'Têm aba e prazo próprios.'],
        ]],
    ]],
    // =====================================================================
    ['chave' => 'producao', 'funcao' => 'producao', 'titulo' => 'Produção', 'icone' => '🏭',
     'vizinhos' => 'qualidade,tecnica,deposito,terceirizacao',
     'descricao' => 'Ordens de produção, matérias-primas, estoque acabado e reabastecimento.',
     'modulos' => [
        ['pd-ordens', 'setor', 'Ordens de produção', 'Planejar, produzir, concluir e imprimir.', 8, <<<'TXT'
## A ordem de produção
Em [Ordens de produção](/producao/ordens):
1. **Nova ordem:** produto e quantidade. O One calcula as matérias-primas pela **ficha técnica**.
2. **Imprimir** a ordem (modelo da fábrica) para a produção.
3. Registre **lotes** das matérias-primas usadas.
4. **Concluir:** as matérias-primas **baixam sozinhas** e o produto acabado **entra no estoque**.

## Alertas
Em [Produção](/producao): matéria-prima abaixo do mínimo, previsão de quando acaba e produtos acabados que precisam de reabastecimento.

! Ordem de terceirização precisa do rótulo do cliente: confira antes de começar.
TXT, [
            ['O que acontece ao concluir uma ordem de produção?', ['Matérias-primas baixam e o produto acabado entra no estoque', 'Nada', 'O pedido é faturado', 'O cliente é avisado'], 0, 'Baixa e entrada automáticas.'],
            ['De onde o One tira a quantidade de cada matéria-prima da ordem?', ['Da ficha técnica do produto', 'Do chute', 'Do catálogo', 'Do Marketing'], 0, 'A ficha técnica diz quanto vai por unidade.'],
        ]],
        ['pd-estoque', 'setor', 'Matérias-primas e estoque acabado', 'Entradas, inventário, mínimos e previsão.', 7, <<<'TXT'
## Matérias-primas
Em [Matérias-primas](/producao/materias): estoque, unidade, fornecedor, mínimo e **previsão de quando acaba**. A entrada rápida soma no estoque; o ajuste de inventário corrige diferenças.

## Estoque acabado
Em [Estoque acabado](/producao/acabados): produto pronto para vender. Abaixo do mínimo, aparece o **pedido de reabastecimento**.

## Rótulos
Em [Estoque de rótulos](/producao/rotulos): os nossos e os dos clientes de terceirização.
TXT, [
            ['Produto acabado abaixo do mínimo gera:', ['Pedido de reabastecimento', 'Uma multa', 'Nada', 'Um post'], 0, 'O One sugere produzir.'],
        ]],
        ['pd-vizinho', 'vizinho', 'Como trabalha a Produção', 'Prazos, estoque e o que a fábrica precisa.', 4, <<<'TXT'
## O que a Produção faz
Planeja e conclui as ordens de produção, controla matérias-primas e o estoque acabado e avisa o que precisa reabastecer.

## Como ajudar
- **Vendas:** produto em falta? Veja a previsão antes de prometer prazo ao cliente.
- **Qualidade:** compre a matéria-prima antes de acabar (a previsão mostra a data).
- **Terceirização:** garanta o rótulo do cliente antes da ordem.
- **Depósito:** produto acabado entra no estoque quando a ordem é concluída.
TXT, [
            ['Cliente quer um produto em falta. Antes de prometer prazo:', ['Veja a previsão com a Produção', 'Prometa amanhã', 'Diga que nunca mais teremos', 'Cancele o pedido'], 0, 'O prazo depende da agenda da fábrica.'],
        ]],
    ]],
    // =====================================================================
    ['chave' => 'tecnica', 'funcao' => 'tecnica', 'titulo' => 'Responsável Técnica e Química', 'icone' => '🧪',
     'vizinhos' => 'producao,qualidade,gestor_marketing',
     'descricao' => 'Fórmulas, fichas técnicas, tabela nutricional, embalagens e documentos dos produtos.',
     'modulos' => [
        ['tc-produtos', 'setor', 'A parte técnica dos produtos', 'Composição, ficha técnica, embalagens e documentos.', 8, <<<'TXT'
## A página do produto
Em [Produtos](/produtos), cada produto tem abas técnicas: composição, ficha técnica, embalagens e **documentos** (laudos, registros) com **validade**. Documento vencendo aparece nos alertas.

## Ficha técnica
Em [Fichas técnicas](/producao/fichas): a quantidade de cada matéria-prima por unidade e o custo. É ela que a Produção usa na ordem.

## Tabela nutricional
O sistema monta a **tabela nutricional (Anvisa)** e a ficha técnica imprimível a partir da composição.
TXT, [
            ['Que tela a Produção usa para calcular as matérias-primas da ordem?', ['A ficha técnica', 'O catálogo', 'A Agenda', 'O Mural de posts'], 0, 'A ficha técnica alimenta a ordem.'],
            ['Documento técnico vencendo:', ['Aparece nos alertas', 'Some do sistema', 'Não importa', 'Vai para a Cobrança'], 0, 'O One avisa antes de vencer.'],
        ]],
        ['tc-projetos', 'setor', 'Produtos novos e dúvidas técnicas', 'A etapa técnica dos projetos e as perguntas do comercial.', 6, <<<'TXT'
## Produtos novos
Em [Produtos novos](/desenvolvimento), a etapa de fórmula é sua. Conclua a etapa no projeto para ele seguir.

## Dúvidas do comercial
Vendedoras encaminham dúvidas técnicas de clientes. Responda com base na composição e no rótulo, **sem promessa de tratamento**.
TXT, [
            ['Ao responder dúvida técnica de cliente, o que nunca fazer?', ['Prometer tratamento ou cura', 'Explicar a composição', 'Mostrar o rótulo', 'Responder com calma'], 0, 'Suplemento não é remédio.'],
        ]],
        ['tc-vizinho', 'vizinho', 'Como trabalha a Responsável Técnica', 'Fórmulas, documentos e dúvidas técnicas.', 3, <<<'TXT'
## O que a Responsável Técnica faz
Cuida das fórmulas, das fichas técnicas, da tabela nutricional, dos textos obrigatórios dos rótulos e dos documentos dos produtos.

## Como acionar
Dúvida técnica de cliente: registre na ficha e encaminhe. Rótulo novo: no projeto de Produtos novos.
TXT, [
            ['Cliente fez uma pergunta técnica que você não sabe responder. O certo é:', ['Registrar e encaminhar à Responsável Técnica', 'Inventar uma resposta', 'Ignorar', 'Dizer que cura'], 0, 'Dúvida técnica vai para a Técnica.'],
        ]],
    ]],
    // =====================================================================
    ['chave' => 'qualidade', 'funcao' => 'qualidade', 'titulo' => 'Qualidade', 'icone' => '✅',
     'vizinhos' => 'producao,tecnica,deposito,financeiro',
     'descricao' => 'Compras de matéria-prima, gráfica, código de barras e documentos dos fornecedores.',
     'modulos' => [
        ['ql-compras', 'setor', 'Pedidos de compra', 'Pedir, cotar, aprovar e acompanhar até o recebimento.', 7, <<<'TXT'
## O caminho de uma compra
Em [Pedidos de compra](/compras):
1. As **sugestões** mostram as matérias-primas que estão acabando.
2. **Novo orçamento:** cote com os fornecedores.
3. Mande para **aprovação**.
4. Aprovado, o pedido vai ao fornecedor e aparece no **Recebimento** do Depósito.
5. Recebido, vira lançamento em Compras da empresa, com a NF e o fornecedor.
TXT, [
            ['Onde ver as matérias-primas que estão acabando?', ['Nas sugestões dos Pedidos de compra', 'No catálogo', 'Nas Campanhas', 'No sino da vendedora'], 0, 'A previsão de consumo gera as sugestões.'],
        ]],
        ['ql-fornecedores', 'setor', 'Fornecedores e documentos', 'Laudos, certificados e validade.', 5, <<<'TXT'
## Documentos dos fornecedores e dos itens
Na página do item e do [fornecedor](/financeiro/fornecedores): laudos, fichas técnicas, FISPQ e certificados, com **validade e lote**. Documento vencendo aparece nos alertas.

## Rótulos e código de barras
A Qualidade cuida do código de barras, da gráfica e da entrada dos rótulos no estoque.
TXT, [
            ['Laudo do fornecedor com validade perto do fim:', ['Aparece nos alertas', 'Some', 'Vai para o Marketing', 'Não importa'], 0, 'O One avisa documento vencendo.'],
        ]],
        ['ql-vizinho', 'vizinho', 'Como trabalha a Qualidade', 'Compras de matéria-prima, rótulos e documentos.', 3, <<<'TXT'
## O que a Qualidade faz
Compra matérias-primas (cotação e aprovação), cuida dos rótulos, do código de barras e dos documentos dos fornecedores.

## Para a Produção e o Depósito
Precisa de matéria-prima? Veja a previsão e avise a Qualidade. As compras aprovadas chegam no Recebimento do Depósito.
TXT, [
            ['As compras aprovadas pela Qualidade aparecem onde para o Depósito?', ['No Recebimento', 'No Mural de posts', 'Na Agenda', 'No catálogo'], 0, 'O Depósito confere e dá entrada.'],
        ]],
    ]],
    // =====================================================================
    ['chave' => 'deposito', 'funcao' => 'deposito', 'titulo' => 'Separação e Expedição', 'icone' => '📦',
     'vizinhos' => 'producao,qualidade,ecommerce,financeiro',
     'descricao' => 'Separar e despachar pedidos, lote e validade, rastreio e recebimento das compras.',
     'modulos' => [
        ['dp-separar', 'setor', 'Separar e despachar', 'A fila, lote e validade, rastreio.', 7, <<<'TXT'
## A fila de separação
Em [Separação](/deposito) estão os pedidos **com nota** para separar, por tipo: lojista, e-commerce e terceirização. Os mais urgentes vêm primeiro.

## Para cada pedido
1. Separe os itens.
2. Informe **lote e validade** de cada produto (é assim que rastreamos depois).
3. Despache e informe o **rastreio**: o cliente e a vendedora ficam sabendo.

## Rastrear lote
Em [Rastrear lote](/deposito/lotes): para quais clientes foi cada lote. Essencial se houver um recolhimento.
TXT, [
            ['Por que informar lote e validade na separação?', ['Para saber depois para quais clientes foi cada lote', 'Por enfeite', 'Para o Marketing', 'Não precisa'], 0, 'O rastreio de lote depende disso.'],
            ['Pedido despachado: o que informar?', ['O rastreio', 'O pedido mínimo', 'A meta do mês', 'Nada'], 0, 'Com o rastreio, cliente e vendedora acompanham a entrega.'],
        ]],
        ['dp-receber', 'setor', 'Receber as compras', 'Conferir e dar entrada.', 5, <<<'TXT'
## Recebimento
Em [Recebimento](/deposito/recebimento) aparecem as compras aprovadas que vão chegar.
1. Confira **quantidade**, **lote** e **validade** com a nota.
2. Dê entrada: o estoque sobe sozinho.
3. Divergência? Registre na observação e avise a Qualidade.
TXT, [
            ['Chegou menos do que diz a nota. O que fazer?', ['Registrar a divergência e avisar a Qualidade', 'Dar entrada do total da nota', 'Devolver tudo sem falar', 'Ignorar'], 0, 'O registro garante a cobrança do fornecedor.'],
        ]],
        ['dp-vizinho', 'vizinho', 'Como trabalha o Depósito', 'Separação, despacho e recebimento.', 3, <<<'TXT'
## O que o Depósito faz
Separa e despacha os pedidos com nota (lojista, e-commerce e terceirização), registra lote e validade, informa o rastreio e recebe as compras.

## Como ajudar
- **Vendas:** combinados de entrega na observação do pedido.
- **Qualidade:** compras aprovadas com previsão de chegada.
- **E-commerce:** prazos curtos de despacho.
TXT, [
            ['Onde deixar um combinado de entrega para o Depósito?', ['Na observação do pedido', 'No WhatsApp pessoal', 'No Mural de posts', 'Não precisa'], 0, 'O Depósito lê a observação ao separar.'],
        ]],
    ]],
];
