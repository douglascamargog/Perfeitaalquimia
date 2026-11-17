<?php
/**
 * Treinamento — trilhas do Marketing (Marketing, Social Media, Gestor de Marketing, E-commerce) e da Fábrica
 * (Produção, Responsável Técnica, Qualidade, Depósito). V13.8: aprofundado, no padrão do Comercial
 * (prints, regras com os valores atuais, casos práticos). Formato igual ao de treinamento_comercial.php.
 */

// =====================================================================
// Módulos usados em mais de uma trilha do Marketing
// =====================================================================
$tarefasMkt = <<<'TXT'
## Objetivo
Organizar o trabalho do Marketing num quadro só: o que chega da empresa, o que está em andamento, o que espera aprovação e o que já foi entregue.

## O Início do Marketing
Em [Início do Marketing](/marketing/inicio) ficam o resumo do mês, o calendário e o **quadro de tarefas**.
![Início do Marketing: números do mês, calendário e o quadro de tarefas](assets/img/treinamento/marketing-inicio.jpg)
O quadro tem quatro colunas: **a fazer → fazendo → aprovação → feito**. Arraste o cartão para mudar a etapa. Cada cartão mostra o responsável, o prazo e quem pediu.

## Os pedidos da empresa
Os setores pedem artes, materiais, cadastros e envios em **Pedir ao Marketing**. Cada pedido vira um cartão com prazo.
![Pedir ao Marketing: o formulário e os pedidos de quem pediu](assets/img/treinamento/marketing-pedir.jpg)
Quando você conclui, **quem pediu recebe o aviso** no sino (e o One comemora com a pessoa).

## Passo a passo de uma tarefa
1. Abra o cartão e leia o pedido inteiro (anexos, referências, para quem é).
2. Faltou informação? Comente **no próprio cartão**: fica registrado e a pessoa é avisada.
3. Mova para "fazendo" quando começar.
4. Terminou? Anexe o arquivo final e mova para **aprovação** (se precisa do OK do gestor) ou **feito**.

## Prazos e prioridade
- Urgência de verdade (cliente esperando, campanha começando amanhã) vem antes.
- Prazo impossível? Combine outro **no cartão** e avise quem pediu. Não deixe atrasar em silêncio.

?? A vendedora pediu "uma arte para o cliente" sem dizer o produto nem o formato. || Comente no cartão perguntando produto, formato (post, story, A4), texto e prazo. Deixe o cartão em "a fazer" até a resposta. Ela recebe o aviso do comentário.
?? Chegaram três pedidos para hoje e só dá tempo de fazer dois. || Priorize o que tem cliente ou campanha esperando, avise nos cartões o novo prazo do terceiro e, se necessário, peça ajuda ao Gestor de Marketing para decidir.
TXT;
$tarefasPerguntas = [
    ['Você terminou uma arte pedida pela vendedora. Como ela fica sabendo?', ['Recebe o aviso no sino quando a tarefa é concluída', 'Precisa adivinhar', 'Por e-mail pessoal', 'Não fica sabendo'], 0, 'Concluiu, o One avisa quem pediu.'],
    ['Para onde vai uma tarefa que precisa do OK do gestor?', ['Para a coluna "aprovação"', 'Para o lixo', 'Para a Cobrança', 'Para o Depósito'], 0, 'O Gestor de Marketing aprova ou devolve.'],
    ['Faltou informação num pedido. Onde perguntar?', ['No próprio cartão (fica registrado e avisa a pessoa)', 'No corredor', 'No WhatsApp pessoal', 'Não pergunta, inventa'], 0, 'Comentário no cartão fica no histórico.'],
    ['O prazo pedido é impossível. O certo é:', ['Deixar atrasar', 'Combinar outro prazo no cartão e avisar quem pediu', 'Recusar sem falar', 'Fazer de qualquer jeito'], 1, 'Prazo combinado, ninguém é surpreendido.'],
];

return [
    // =====================================================================
    ['chave' => 'marketing', 'funcao' => 'marketing', 'titulo' => 'Marketing', 'icone' => '📣',
     'vizinhos' => 'televendas,ecommerce,tecnica,social_media',
     'descricao' => 'Tarefas e pedidos da empresa, catálogo e conteúdo dos produtos, campanhas e leads.',
     'modulos' => [
        ['mk-tarefas', 'setor', 'Tarefas e pedidos ao Marketing', 'O quadro do setor e os pedidos que chegam da empresa.', 12, $tarefasMkt, $tarefasPerguntas],
        ['mk-produtos', 'setor', 'Conteúdo dos produtos e catálogo', 'Um cadastro só alimenta catálogo, Biblioteca e loja.', 14, <<<'TXT'
## Objetivo
Manter o conteúdo dos produtos correto e atraente, sabendo que **um cadastro só** aparece em vários lugares.

## Onde o conteúdo do produto aparece
- **Catálogo do cliente** (lojistas, com preço só depois do login).
- **Biblioteca de produtos** do Treinamento (a equipe estuda por ela).
- **Loja virtual e marketplaces** (o E-commerce usa as mesmas fotos e textos).

## A vitrine do cliente
Em [Catálogo](/produtos/catalogo) você decide **o que aparece**, a **ordem**, a **chamada** e confere os preços que vieram do ERP.
![Catálogo: o que aparece para o cliente, a ordem e os preços do ERP](assets/img/treinamento/catalogo-admin.jpg)
1. Produto novo: só aparece quando estiver com foto, chamada e preço.
2. Produto sem estoque por muito tempo: tire da vitrine em vez de deixar o cliente pedir.
3. "Ver o catálogo público" mostra exatamente o que o lojista vê.

## Campanhas e o visual do catálogo
Em [Campanhas](/campanhas) › Visual do catálogo ficam as cores e o banner. Durante uma campanha, ela pode trocar o visual sozinha e volta ao padrão quando termina.

## Textos que podem e que não podem
- Pode: composição, quantidade por porção, apresentação, forma de uso (está na ficha técnica).
- Não pode: "cura", "trata", "emagrece X kg", antes e depois milagroso.

! Texto novo de produto com alguma afirmação de saúde: valide com a Responsável Técnica antes de publicar.

?? A vendedora avisa que o catálogo mostra uma foto antiga do Colágeno. || Troque a foto no cadastro do produto: ela muda no catálogo, na Biblioteca e serve para a loja. Avise o E-commerce, se a loja usa foto própria.
?? O fornecedor de uma matéria-prima mandou um texto dizendo que o produto "previne doenças". || Não use. Peça à Responsável Técnica um texto baseado na composição e na legislação.
TXT, [
            ['Onde o cliente vê o preço dos produtos?', ['No catálogo, só depois de entrar com o login dele', 'Na página pública, para todos', 'No Instagram', 'Em lugar nenhum'], 0, 'Preço e estoque só aparecem com login.'],
            ['Trocou a foto de um produto. Onde ela muda?', ['No catálogo e na Biblioteca: é o mesmo cadastro', 'Só no catálogo', 'Só no Instagram', 'Em lugar nenhum'], 0, 'Um cadastro só alimenta tudo.'],
            ['Um texto de produto pode dizer que ele "trata" uma doença?', ['Sim', 'Não: valide afirmações de saúde com a Responsável Técnica', 'Só no Instagram', 'Só em campanha'], 1, 'Suplemento não é remédio.'],
            ['Produto sem estoque há muito tempo no catálogo:', ['Deixar aparecendo', 'Tirar da vitrine até voltar', 'Aumentar o preço', 'Mudar o nome'], 1, 'Evita pedido que não será atendido.'],
        ]],
        ['mk-leads', 'setor', 'Leads, campanhas e mensagens', 'Do anúncio ao funil da Prospecção, e como medir.', 10, <<<'TXT'
## Leads do site e dos anúncios
O formulário do site e dos anúncios cria o lead no funil da Prospecção, com a **campanha (UTM)** de origem. Em [Leads do site](/leads) você vê quantos vieram de cada campanha e **quantos viraram clientes**.
![Leads do site: quantos por campanha e quantos viraram clientes](assets/img/treinamento/leads.jpg)
O link do formulário e a página "Seja revendedor" estão no topo da tela.

## Como medir uma campanha
1. Use UTM em todo link de anúncio (origem, mídia, campanha).
2. Depois de 2 a 4 semanas, compare em Leads do site: leads, convertidos e de onde vieram.
3. Leve o resultado para o planejamento do mês seguinte.

## Mensagens prontas
Em [Mensagens prontas](/mensagens) ficam os modelos de WhatsApp da equipe (prospecção, reativação, cobrança, pós-venda). Use {nome} e {link_vendedor}. Mensagem boa é curta, com uma pergunta.

## Planejamento
![Planejamento: campanhas e datas comemorativas na linha do tempo](assets/img/treinamento/marketing-planejamento.jpg)
As datas comemorativas da configuração aparecem na linha do tempo. Planeje com o Comercial pelo menos 30 dias antes.

?? O anúncio novo trouxe muitos leads, mas quase nenhum virou cliente. || Olhe em Leads do site de onde vieram e converse com a Prospecção: os leads são lojas (CNPJ) ou consumidores finais? Ajuste o público e o texto do anúncio para falar com lojistas.
TXT, [
            ['Como saber de qual anúncio veio um lead?', ['Pela campanha (UTM) registrada no lead', 'Perguntando ao cliente', 'Não tem como', 'Pelo CNPJ'], 0, 'O UTM vem junto com o formulário.'],
            ['Para avaliar uma campanha, compare:', ['Leads e quantos viraram clientes', 'Só curtidas', 'Só o custo', 'Nada'], 0, 'Conversão em cliente é o que importa no B2B.'],
        ]],
        ['mk-vizinho', 'vizinho', 'Como trabalha o Marketing', 'Como pedir e o que esperar do Marketing.', 5, <<<'TXT'
## O que o Marketing faz
Artes, posts, campanhas, catálogo, cadastro e conteúdo de produtos, materiais para lojistas e os leads dos anúncios.

## Como pedir (passo a passo)
1. Abra [Pedir ao Marketing](/marketing/pedir).
2. Diga **o que** precisa, **para quem** (cliente, campanha), **o formato** e **para quando**.
3. Anexe referências (foto, print, modelo).
4. Acompanhe na mesma tela; quando ficar pronto, você recebe o aviso.

## Prazos
Peça com antecedência. Urgência de verdade? Explique o motivo no pedido.
TXT, [
            ['Qual o jeito certo de pedir uma arte?', ['Em Pedir ao Marketing, com formato, prazo e referência', 'Pelo corredor', 'Por áudio no WhatsApp pessoal', 'Não pedir'], 0, 'O pedido fica registrado e você é avisado quando fica pronto.'],
        ]],
    ]],
    // =====================================================================
    ['chave' => 'social_media', 'funcao' => 'social_media', 'titulo' => 'Social Media', 'icone' => '📱',
     'vizinhos' => 'marketing,gestor_marketing,ecommerce',
     'descricao' => 'Posts, artes e o calendário das redes, com aprovação.',
     'modulos' => [
        ['sm-mural', 'setor', 'O mural de posts', 'Calendário do Instagram, prévia do feed e aprovação.', 14, <<<'TXT'
## Objetivo
Publicar com constância, com aprovação, e com o feed bonito e coerente.

## O calendário
Em [Mural de posts](/marketing) cada post fica no dia em que vai ao ar, com arte, legenda, formato (feed, story, reels) e etapa.
![Mural de posts: o mês com os posts em cada dia](assets/img/treinamento/marketing-mural.jpg)
A meta da empresa é de **{{config:marketing.posts_por_semana_meta}} posts por semana**; o One avisa quando a semana seguinte está vazia.

## As etapas de um post
Ideia → Em produção → Aguardando aprovação → (Ajustar) → Aprovado → Agendado → Publicado.

## Passo a passo
1. **Novo post:** data, formato, arte e legenda.
![Novo post: data, formato, arte e legenda](assets/img/treinamento/marketing-post-novo.jpg)
2. Mova para **Aguardando aprovação**.
3. O Gestor de Marketing aprova ou devolve para **Ajustar**, com comentário.
4. Aprovado, agende na rede social e marque **Agendado**; depois de ir ao ar, **Publicado**.

## Prévia do feed
A aba "Prévia do feed" mostra como o perfil vai ficar com os próximos posts: confira cores, sequência e variedade.
![Prévia do feed: como o perfil vai ficar](assets/img/treinamento/marketing-feed.jpg)

## Boas práticas
- Legenda: primeira linha que prende, depois a informação, e uma chamada para ação.
- Produto: siga o texto da ficha; nada de promessa de saúde.
- Datas comemorativas estão no Planejamento.

?? O post da Black Friday precisa ir ao ar amanhã e ainda está em "Ajustar". || Faça o ajuste do comentário, devolva para "Aguardando aprovação" e avise o gestor de que é para amanhã (comente no post). Não publique sem aprovação.
TXT, [
            ['O que vem antes de agendar o post na rede social?', ['A aprovação do Gestor de Marketing', 'Nada', 'A aprovação do Depósito', 'O pedido mínimo'], 0, 'Posts passam pela aprovação.'],
            ['O post voltou para "Ajustar". O que fazer?', ['Publicar mesmo assim', 'Fazer o ajuste do comentário e mandar de novo para aprovação', 'Apagar', 'Esperar'], 1, 'Ajustou, volta para aprovação.'],
            ['Para que serve a Prévia do feed?', ['Ver como o perfil fica com os próximos posts', 'Ver as vendas', 'Aprovar pedidos', 'Nada'], 0, 'Confere cores e sequência.'],
        ]],
        ['sm-tarefas', 'setor', 'Tarefas e pedidos ao Marketing', 'O quadro do setor e os pedidos que chegam da empresa.', 12, $tarefasMkt, $tarefasPerguntas],
        ['sm-vizinho', 'vizinho', 'Como trabalha o Social Media', 'Posts e redes da empresa.', 3, <<<'TXT'
## O que o Social Media faz
Cria os posts e as artes das redes e dos materiais de divulgação, seguindo o calendário do Mural de posts, com aprovação do Gestor de Marketing.

## Como ajudar
Tem uma novidade, um lançamento ou uma foto boa de cliente (com autorização)? Mande em Pedir ao Marketing: vira pauta.
TXT, [
            ['Tem uma novidade boa para as redes. Como mandar?', ['Em Pedir ao Marketing', 'Postar no perfil pessoal', 'Guardar para você', 'No sino'], 0, 'Vira pauta no calendário.'],
        ]],
    ]],
    // =====================================================================
    ['chave' => 'gestor_marketing', 'funcao' => 'gestor_marketing', 'titulo' => 'Gestor de Marketing', 'icone' => '🎯',
     'vizinhos' => 'marketing,social_media,tecnica,qualidade',
     'descricao' => 'Aprovar o que o Marketing produz, criar os rótulos e planejar as campanhas.',
     'modulos' => [
        ['gm-aprovar', 'setor', 'Aprovar tarefas e posts', 'A coluna de aprovação e o comentário que ajuda.', 8, <<<'TXT'
## O que espera por você
- Tarefas na coluna **aprovação** do [Início do Marketing](/marketing/inicio).
- Posts em **Aguardando aprovação** no Mural.
O One avisa no sino e no Início quando há algo esperando.

## Como aprovar bem
1. Confira com o pedido original: atende o que foi pedido?
2. Confira marca (cores, logo), texto (sem promessa de saúde) e produto certo.
3. Aprove, ou devolva com **comentário claro e específico**: "trocar a foto pelo pote de 60 cápsulas e tirar 'imunidade garantida'".

## Planejamento do mês
Em Planejamento, combine com o Comercial as campanhas e as datas. O que vai para o catálogo entra também em [Campanhas](/campanhas).

?? Uma arte chegou com o logotipo antigo. || Devolva com o comentário e o arquivo do logo atual anexado. Aproveite para checar se outras artes da semana usam o mesmo arquivo.
TXT, [
            ['Ao devolver uma arte, o que ajuda a equipe?', ['Um comentário claro do que mudar', 'Só "refazer"', 'Nada', 'Apagar a tarefa'], 0, 'Comentário claro evita idas e vindas.'],
            ['O que conferir antes de aprovar um post de produto?', ['Marca, produto certo e texto sem promessa de saúde', 'Só a cor', 'Nada', 'O preço do concorrente'], 0, 'Marca, produto e texto.'],
        ]],
        ['gm-rotulos', 'setor', 'Rótulos e embalagens no projeto de produto novo', 'Do texto validado à prova da gráfica.', 10, <<<'TXT'
## Onde o rótulo entra
Em [Produtos novos](/desenvolvimento), as etapas são: Ideia e briefing → Formulação e ficha técnica → Informações de rotulagem → Código de barras, NCM e cadastro → **Criação do rótulo** → Compra de matéria-prima e embalagens → Envio à gráfica → **Aprovação da prova da gráfica** → Recebimento dos rótulos → Lote piloto → Lançamento.
![Produtos novos: cada coluna é uma etapa; cada cartão, um projeto](assets/img/treinamento/desenvolvimento.jpg)

## A sua parte (passo a passo)
1. Use as **informações de rotulagem** validadas pela Técnica (composição, tabela nutricional, advertências).
2. Crie a arte; anexe no projeto e conclua a etapa.
3. Quando a gráfica mandar a **prova**, você e a Qualidade aprovam no projeto (a etapa só avança com os dois).

! Nunca mande para a gráfica um texto obrigatório (composição, advertências, tabela) que não tenha vindo da etapa da Técnica.

?? A gráfica mandou a prova com a tabela nutricional cortada. || Reprove a prova no projeto com o comentário e peça nova prova. Não aprove "para ganhar tempo": rótulo errado vira lote inteiro perdido.
TXT, [
            ['Quem valida os textos obrigatórios do rótulo?', ['A Responsável Técnica', 'O Depósito', 'O cliente final', 'Ninguém'], 0, 'Textos legais passam pela Técnica.'],
            ['A prova da gráfica é aprovada por:', ['Gestor de Marketing e Qualidade', 'Só a gráfica', 'Só o Financeiro', 'Ninguém'], 0, 'A etapa tem dois aprovadores.'],
        ]],
        ['gm-tarefas', 'setor', 'Tarefas e pedidos ao Marketing', 'O quadro do setor e os pedidos que chegam da empresa.', 12, $tarefasMkt, $tarefasPerguntas],
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
        ['ec-loja', 'setor', 'A loja virtual e os marketplaces', 'O painel do E-commerce, os pedidos e o que acompanhar todo dia.', 14, <<<'TXT'
## O painel
Em [E-commerce](/marketing/ecommerce) estão as vendas da loja própria **e** dos marketplaces (Mercado Livre, Shopee, Amazon, Magalu), copiadas da Tray a cada 30 minutos.
![Painel do E-commerce](assets/img/treinamento/ecommerce.jpg)

1. Escolha o **período** (hoje, 7 dias, este mês, 90 dias, 12 meses ou datas livres) e, se quiser, um **canal**, um **estado** ou uma **situação**.
2. Os números comparam com o período anterior do mesmo tamanho (▲ subiu, ▼ caiu).
3. **Faturamento** conta só pedidos **pagos, enviados ou entregues**. Os que estão aguardando pagamento e os cancelados aparecem à parte.

## Vendas por canal
Cada cartão mostra quanto cada canal vendeu, o ticket médio, a fatia do total e os cancelamentos. Clique no cartão para ver só aquele canal.
![Vendas por canal](assets/img/treinamento/ecommerce-canais.jpg)

## O que mais o painel mostra
- **Caminho dos pedidos:** feitos → pagos → com nota → enviados → entregues (e cancelados).
- **Quando as pessoas compram:** dia da semana × hora. Use para programar posts, anúncios e ofertas.
- **Produtos e categorias** que mais vendem, em qual canal cada produto vende mais e os que **pararam de vender**.
- **Formas de pagamento** (e quanto cada uma cancela), **envio e transportadoras** (frete médio e % enviado no prazo).
- **Estados e cidades** que mais compram, e os **cupons** mais usados.
- **Alertas:** pedido pago sem nota há mais de {{config:ecommerce.alerta_horas_nf}} horas, pedido que passou do prazo sem ser enviado e cancelamentos acima do normal.

## Os pedidos
Em **Todos os pedidos** está a lista completa, com busca pelo número da loja, do marketplace ou pelo cupom, e a **planilha** para baixar. Clique no número para ver o pedido: produtos, valores, pagamento, envio, o número no marketplace e em que etapa ele está no Depósito.
![Lista de pedidos do e-commerce](assets/img/treinamento/ecommerce-pedidos.jpg)

Por segurança (LGPD), o One **não guarda** nome, CPF, e-mail, telefone nem endereço do comprador: só a cidade e o estado. Dados do comprador, só no painel da Tray.

## A rotina diária
1. Responder as **perguntas** dos marketplaces (a nota da loja depende do tempo de resposta).
2. Conferir pedidos novos e os parados.
3. Acompanhar campanhas de desconto e ofertas relâmpago.
4. Conferir com o Depósito os envios do dia.

## Horários de envio (combinados com o Depósito)
- Mercado Livre: até {{config:deposito.horario_mercado_livre}}
- Shopee: até {{config:deposito.horario_shopee}}
- Amazon: até {{config:deposito.horario_amazon}}
Pedido que entra depois do horário sai no dia seguinte: considere isso nas ofertas relâmpago.

?? Uma oferta relâmpago vai gerar muitos pedidos às 16h. || Avise o Depósito com antecedência: os pedidos depois do horário de corte saem no dia seguinte, e o prazo do anúncio precisa considerar isso.
TXT, [
            ['Por que responder rápido as perguntas dos marketplaces?', ['A nota e a visibilidade da loja dependem disso', 'Não importa', 'Por causa do Omie', 'Para gastar tempo'], 0, 'Tempo de resposta conta na reputação.'],
            ['O faturamento do painel do E-commerce conta:', ['Só pedidos pagos, enviados ou entregues', 'Todos os pedidos, até os cancelados', 'Só os aguardando pagamento', 'Só a loja própria'], 0, 'Aguardando pagamento e cancelados aparecem separados.'],
            ['Onde ver em que horário as pessoas mais compram?', ['No mapa de calor "Quando as pessoas compram"', 'Na Cobrança', 'No Depósito', 'Não dá para ver'], 0, 'Dia da semana × hora, para programar posts e ofertas.'],
            ['O One guarda o CPF e o telefone do comprador do site?', ['Não: só cidade e estado (LGPD)', 'Sim, de todos', 'Só dos marketplaces', 'Só do Mercado Livre'], 0, 'Dados do comprador ficam só na Tray.'],
            ['Pedido do Mercado Livre que entra depois do horário de corte:', ['Sai no mesmo dia', 'Sai no dia seguinte', 'É cancelado', 'Vai para a Cobrança'], 1, 'O horário de corte vale para o despacho.'],
        ]],
        ['ec-cadastro', 'setor', 'Cadastro de produtos e envios FULL', 'Fotos, descrições e estoque nos centros de distribuição.', 8, <<<'TXT'
## Cadastro
Use as fotos e os textos do cadastro único em [Produtos](/produtos). Ajuste o título para a busca do marketplace, mas sem promessa de saúde.

## Envios FULL
Para os centros de distribuição dos marketplaces:
1. Defina quantidades pelo giro (veja os mais vendidos).
2. Combine com a **Produção** se há estoque acabado suficiente.
3. Peça ao **Depósito** a separação e a etiquetagem no padrão do marketplace.

?? O estoque do FULL está acabando e a Produção diz que o produto só sai em 10 dias. || Pause o anúncio do FULL ou aumente o prazo do anúncio normal; avise o Comercial para não prometer o mesmo estoque aos lojistas.
TXT, [
            ['Envio FULL: com quem combinar a separação?', ['Com o Depósito', 'Com a Cobrança', 'Com a Prospecção', 'Com ninguém'], 0, 'O Depósito separa e etiqueta.'],
        ]],
        ['ec-tarefas', 'setor', 'Tarefas e pedidos ao Marketing', 'O quadro do setor e os pedidos que chegam da empresa.', 12, $tarefasMkt, $tarefasPerguntas],
        ['ec-vizinho', 'vizinho', 'Como trabalha o E-commerce', 'Loja virtual e marketplaces.', 3, <<<'TXT'
## O que o E-commerce faz
Cuida da loja virtual e dos marketplaces: cadastros, perguntas, campanhas, ofertas e envios para os centros de distribuição.

## Para o Depósito
Pedidos do e-commerce têm **horário de corte** por marketplace e aparecem separados na fila. Pedido fora do prazo prejudica a reputação da loja.
Se o pedido for **cancelado na Tray**, ele sai sozinho da fila (mesmo se já estiver sendo separado) e o Depósito recebe um aviso no sino: devolva os produtos à prateleira.
TXT, [
            ['Pedidos do e-commerce no Depósito:', ['Têm horário de corte e aparecem separados', 'São iguais aos de lojista', 'Não passam pelo Depósito', 'Vão para a Cobrança'], 0, 'Têm aba e prazo próprios.'],
        ]],
    ]],
    // =====================================================================
    ['chave' => 'producao', 'funcao' => 'producao', 'titulo' => 'Produção', 'icone' => '🏭',
     'vizinhos' => 'qualidade,tecnica,deposito,terceirizacao',
     'descricao' => 'Ordens de produção, lotes, matérias-primas, estoque acabado e reabastecimento.',
     'modulos' => [
        ['pd-painel', 'setor', 'O painel da fábrica', 'Alertas, ordens planejadas e o que reabastecer.', 8, <<<'TXT'
## Objetivo
Começar o dia sabendo o que produzir, o que está acabando e o que está atrasado.

## O painel
Em [Produção](/producao):
![Produção: alertas de matéria-prima, o que reabastecer e a produção do mês](assets/img/treinamento/producao.jpg)
- **Matérias-primas em alerta:** abaixo do mínimo ou acabando em menos de {{config:producao.dias_alerta}} dias.
- **Reabastecer:** produtos acabados abaixo do mínimo. A sugestão cobre {{config:producao.dias_cobertura}} dias de venda.
- **Ordens planejadas** e a **produção do mês**.

## Como o One calcula a previsão
Consumo médio dos últimos {{config:producao.dias_consumo}} dias → em quantos dias o estoque acaba no ritmo atual.

?? O painel mostra que a cápsula vegetal acaba em 6 dias e há duas ordens planejadas que usam ela. || Avise a Qualidade (que compra) hoje, com a quantidade das ordens. Se a compra não chegar a tempo, reordene a produção para fazer primeiro o que tem matéria-prima.
TXT, [
            ['Como o One calcula em quantos dias uma matéria-prima acaba?', ['Pelo consumo médio dos últimos dias configurados', 'Sorteio', 'Pelo preço', 'Pelo fornecedor'], 0, 'Consumo médio × estoque atual.'],
            ['Matéria-prima acabando: quem você avisa?', ['A Qualidade, que faz as compras', 'O Marketing', 'Ninguém', 'O cliente'], 0, 'A Qualidade cota e compra.'],
        ]],
        ['pd-ordens', 'setor', 'Ordens de produção, do planejamento à conclusão', 'Planejar, separar lotes, aprovar, concluir e imprimir.', 16, <<<'TXT'
## Objetivo
Produzir com rastreabilidade: cada unidade fabricada sabe de quais lotes de matéria-prima veio.

## A lista
Em [Ordens de produção](/producao/ordens): planejadas, concluídas e canceladas, com filtros.
![Ordens de produção: situação, produto, quantidade e datas](assets/img/treinamento/producao-ordens.jpg)

## Passo a passo
1. **Nova ordem:** escolha o produto e a quantidade. O One calcula as matérias-primas pela **ficha técnica** e mostra se falta alguma.
![Nova ordem: produto, quantidade e a prévia do que vai ser usado](assets/img/treinamento/producao-ordem-nova.jpg)
2. Na ordem, confira os **lotes das matérias-primas**: o One sugere o lote que **vence primeiro**; para usar outro, escolha e salve.
![A ordem: lotes sugeridos, fabricante, fabricação e validade](assets/img/treinamento/producao-ordem.jpg)
3. **Imprimir ordem** (modelo da fábrica) para a produção.
4. A **Responsável Técnica revisa e aprova** a ordem.
5. **Concluir** com a quantidade real: as matérias-primas e os lotes **baixam sozinhos** e o produto acabado **entra no estoque**.

## Terceirização
Ordem de produto de cliente usa o **rótulo do cliente**: a ordem mostra o saldo de rótulos. Sem rótulo, não comece.

## Erros comuns
- Concluir com a quantidade planejada quando saiu menos (o estoque fica errado).
- Trocar o lote na prática e não trocar no sistema (perde a rastreabilidade).
- Começar sem a aprovação da Técnica.

?? Planejou 500 unidades e saíram 480 por perda na encapsulação. || Conclua com 480 (quantidade real). Registre a perda na observação. O estoque acabado e o consumo ficam corretos.
?? O lote sugerido da matéria-prima está no fundo do estoque e outro lote está na frente. || Use o sugerido (vence primeiro). Se precisar usar o outro, troque no sistema antes de concluir: o lote certo fica registrado.
TXT, [
            ['De onde o One tira a quantidade de cada matéria-prima da ordem?', ['Da ficha técnica do produto', 'Do chute', 'Do catálogo', 'Do Marketing'], 0, 'A ficha técnica diz quanto vai por unidade.'],
            ['Qual lote o One sugere?', ['O que vence primeiro', 'O mais novo', 'Qualquer um', 'O mais caro'], 0, 'Primeiro que vence, primeiro que sai.'],
            ['Saíram 480 de 500 planejadas. Você conclui com:', ['500', '480 (a quantidade real)', '0', 'Não conclui'], 1, 'Quantidade real mantém o estoque certo.'],
            ['O que acontece ao concluir a ordem?', ['Matérias-primas e lotes baixam e o acabado entra no estoque', 'Nada', 'O pedido é faturado', 'O cliente é avisado'], 0, 'Baixa e entrada automáticas.'],
            ['Quem revisa e aprova a ordem?', ['A Responsável Técnica', 'O Marketing', 'O cliente', 'Ninguém'], 0, 'A aprovação técnica fica registrada na ordem.'],
        ]],
        ['pd-estoque', 'setor', 'Matérias-primas, rótulos e estoque acabado', 'Entradas com lote, inventário, mínimos e previsão.', 14, <<<'TXT'
## Matérias-primas
Em [Matérias-primas](/producao/materias): estoque, unidade, fornecedor, mínimo e **previsão de quando acaba**.
![Matérias-primas: estoque, mínimo e previsão de cada item](assets/img/treinamento/producao-materias.jpg)
Abra o item para ver fornecedores, compras, documentos (laudos) e o estoque por **lote**.
![A página da matéria-prima: resumo, fornecedores, compras, documentos e estoque](assets/img/treinamento/producao-materia.jpg)

### Entrada e ajuste
- **Entrada:** quantidade, lote, fabricante, fabricação e validade. O lote é obrigatório na entrada.
- **Ajuste de inventário:** informe a **contagem física**; o One grava a diferença. O motivo é obrigatório.

## Rótulos
Em [Estoque de rótulos](/producao/rotulos): os nossos e os dos clientes de terceirização, com o alerta de falta.
![Estoque de rótulos: saldo e alertas](assets/img/treinamento/producao-rotulos.jpg)

## Estoque acabado
Em [Estoque acabado](/producao/acabados): produto pronto para vender. Abaixo do **mínimo**, aparece o pedido de reabastecimento. A venda faturada baixa o estoque sozinha.
![Estoque acabado: quantidade e mínimo de cada produto](assets/img/treinamento/producao-acabados.jpg)

?? A contagem física de um item deu 12 kg e o sistema mostra 15 kg. || Faça o ajuste de inventário com 12 kg e escreva o motivo (ex.: "perda na pesagem" ou "contagem do inventário mensal"). Não lance entrada ou saída falsa para "acertar".
TXT, [
            ['Na entrada de matéria-prima, o que é obrigatório?', ['O lote (com fabricação e validade)', 'Nada', 'Só o preço', 'A cor'], 0, 'O lote garante a rastreabilidade.'],
            ['A contagem física deu diferente do sistema. O certo é:', ['Ajuste de inventário com a contagem e o motivo', 'Lançar uma entrada falsa', 'Ignorar', 'Apagar o item'], 0, 'O ajuste registra a diferença.'],
            ['Produto acabado abaixo do mínimo gera:', ['Pedido de reabastecimento', 'Uma multa', 'Nada', 'Um post'], 0, 'O One sugere produzir.'],
        ]],
        ['pd-vizinho', 'vizinho', 'Como trabalha a Produção', 'Prazos, estoque e o que a fábrica precisa.', 4, <<<'TXT'
## O que a Produção faz
Planeja e conclui as ordens de produção (com lotes e aprovação técnica), controla matérias-primas e o estoque acabado e avisa o que precisa reabastecer.

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
     'descricao' => 'Fórmulas, fichas técnicas, tabela nutricional, rotulagem, documentos e aprovação das ordens.',
     'modulos' => [
        ['tc-formulas', 'setor', 'Fórmulas no One', 'Criar, testar e aprovar fórmulas; a aprovada vira a ficha técnica.', 15, <<<'TXT'
## A sua área de fórmulas
Em [Fórmulas](/formulas) ficam todas as fórmulas, cada uma com código (F-0001) e versões. O cartão mostra a forma, a porção e o **custo por embalagem**.
![Fórmulas: lista](assets/img/treinamento/formulas.jpg)

## Passo a passo: uma fórmula nova
1. **+ Nova fórmula** (ou **Criar fórmula** na etapa de ficha técnica de um produto novo: a fórmula já nasce ligada ao projeto e ao produto).
2. Preencha a forma (cápsula, pó, sachê...), a **porção** (ex.: 2 cápsulas), as **porções por embalagem** e, se for cápsula, quanto cabe nela (mg).
3. Coloque os ingredientes **por porção** (mcg, mg, g, ml ou un). Pote, tampa, lacre e rótulo vão **por embalagem**.
   Ingrediente que ainda não existe em Matérias-primas: deixe "não cadastrada" e escreva o nome.
4. Veja as **contas na hora**: massa da porção, % de cada ingrediente, ocupação da cápsula (avisa quando não cabe), custo por porção, por embalagem e do lote piloto, e o que falta de estoque.
![Editor da fórmula com as contas](assets/img/treinamento/formula-editor.jpg)
5. **Salvar fórmula.**

## Testes e versões
- Registre cada teste (data, tamanho, resultado e observações): a fórmula passa para **Em teste**.
- Mudou algo importante? **Nova versão**: a anterior fica guardada como estava, e **Comparar versões** mostra o que entrou, saiu e mudou (e o custo).

## Aprovar = gerar a ficha técnica
Para aprovar: produto ligado, todos os ingredientes cadastrados e unidades que convertem. Ao aprovar:
1. A fórmula **vira a ficha técnica** do produto (quantidade por unidade fabricada, na unidade da matéria-prima: kg, g, L, un).
2. A ficha anterior fica guardada no histórico da fórmula.
3. A **Produção** recebe o aviso; o projeto do produto novo registra a aprovação.

## O especialista em Formulação
O botão **🧪 Perguntar ao especialista** abre o One no especialista de Formulação, que já sabe qual fórmula está aberta: custo, cápsulas, estoque, alternativas de matéria-prima.
> Ele ajuda com contas e ideias, mas a conferência da norma vigente da Anvisa (constituintes, limites por porção, alegações e rotulagem) é sempre sua.

?? A fórmula em cápsula ficou com 112% de ocupação. || Reduza o excipiente, troque o ativo por uma forma mais concentrada, aumente para 3 cápsulas por porção ou use uma cápsula maior. Crie uma nova versão e compare o custo.
TXT, [
            ['A fórmula aprovada:', ['Vira a ficha técnica do produto', 'Fica só guardada', 'Vai para o catálogo', 'Apaga a ficha sem guardar'], 0, 'E a ficha anterior fica no histórico.'],
            ['Pote, tampa e rótulo entram na fórmula:', ['Por embalagem', 'Por porção', 'Não entram', 'Por cápsula'], 0, 'Quantidade por pote/frasco.'],
            ['Para mudar uma fórmula já aprovada:', ['Crie uma nova versão', 'Edite direto', 'Apague e faça outra', 'Peça ao Marketing'], 0, 'A aprovada não muda; a nova versão guarda o histórico.'],
            ['Quem confere a legislação da Anvisa?', ['A Responsável Técnica', 'O especialista do One sozinho', 'A vendedora', 'Ninguém'], 0, 'O One ajuda, a Técnica confere.'],
        ]],
        ['tc-produtos', 'setor', 'A parte técnica dos produtos', 'Composição, ficha técnica, tabela nutricional e documentos.', 14, <<<'TXT'
## A página do produto
Em [Produtos](/produtos), cada produto tem abas técnicas: composição, ficha técnica, embalagens e **documentos** (laudos, registros) com **validade**.
![A página técnica do produto](assets/img/treinamento/produto-tecnico.jpg)
Documentos vencendo aparecem nos alertas {{config:produtos.dias_aviso_documento}} dias antes.

## Ficha técnica
Em [Fichas técnicas](/producao/fichas): a quantidade de cada matéria-prima **por unidade** e o custo. É ela que a Produção usa na ordem.
![Fichas técnicas: produtos e custo](assets/img/treinamento/producao-fichas.jpg)
![A ficha técnica de um produto: matérias-primas por unidade](assets/img/treinamento/producao-ficha.jpg)
Daqui saem a **tabela nutricional (Anvisa)** e a ficha técnica imprimível.

## Passo a passo: mudar uma fórmula
1. Ajuste a ficha técnica (quantidades por unidade).
2. Confira a tabela nutricional gerada.
3. Avise o Marketing se o rótulo precisa mudar (abra um projeto em Produtos novos ou um pedido ao Marketing).
4. Avise a Produção: as próximas ordens já usam a ficha nova.

! Ficha técnica errada vira ordem errada e rótulo errado. Mude com cuidado e registre o motivo.

?? O fornecedor trocou o teor de um extrato. || Ajuste a ficha técnica se a quantidade por unidade precisar mudar, atualize o laudo nos documentos da matéria-prima e verifique se a tabela nutricional e o rótulo continuam corretos.
TXT, [
            ['Que tela a Produção usa para calcular as matérias-primas da ordem?', ['A ficha técnica', 'O catálogo', 'A Agenda', 'O Mural de posts'], 0, 'A ficha técnica alimenta a ordem.'],
            ['Documento técnico vencendo:', ['Aparece nos alertas antes de vencer', 'Some do sistema', 'Não importa', 'Vai para a Cobrança'], 0, 'O One avisa com antecedência.'],
            ['Mudou a fórmula. Quem precisa saber?', ['Marketing (rótulo) e Produção (próximas ordens)', 'Ninguém', 'Só o cliente', 'A Cobrança'], 0, 'Fórmula muda rótulo e produção.'],
        ]],
        ['tc-ordens', 'setor', 'Revisar e aprovar as ordens de produção', 'O que conferir antes de liberar a produção.', 8, <<<'TXT'
## Por que a Técnica aprova
A ordem de produção é o documento da fábrica (modelo {{config:op.codigo_documento}}). A sua aprovação registra que **fórmula, quantidades e lotes** estão corretos.

## O que conferir (passo a passo)
1. Produto e quantidade.
2. Matérias-primas e quantidades (vêm da ficha técnica vigente).
3. **Lotes:** dentro da validade e com laudo.
4. Terceirização: rótulo do cliente correto.
5. Aprove. Fica registrado "revisado e aprovado por" com data.

?? A ordem sugere um lote de matéria-prima cujo laudo venceu. || Não aprove com esse lote: peça para trocar o lote na ordem ou atualizar o laudo nos documentos da matéria-prima. Registre na observação.
TXT, [
            ['Lote de matéria-prima com laudo vencido na ordem:', ['Aprova mesmo assim', 'Pede troca do lote ou laudo atualizado antes de aprovar', 'Ignora', 'Cancela a empresa'], 1, 'Aprovação técnica garante a qualidade.'],
        ]],
        ['tc-projetos', 'setor', 'Produtos novos e dúvidas técnicas', 'As etapas técnicas dos projetos e as perguntas do comercial.', 10, <<<'TXT'
## Produtos novos
Em [Produtos novos](/desenvolvimento), as etapas da Técnica são **Formulação e ficha técnica** e **Informações de rotulagem**. Elas correm em paralelo com outras etapas.
![Um projeto de produto novo: etapas, responsáveis e o que falta](assets/img/treinamento/desenvolvimento-projeto.jpg)
Conclua cada etapa no projeto: ele segue para o próximo setor e todos veem o andamento.

## Dúvidas do comercial
Vendedoras encaminham dúvidas técnicas de clientes. Responda com base na composição e no rótulo, **sem promessa de tratamento**, e de forma que a vendedora consiga repassar.
TXT, [
            ['Ao responder dúvida técnica de cliente, o que nunca fazer?', ['Prometer tratamento ou cura', 'Explicar a composição', 'Mostrar o rótulo', 'Responder com calma'], 0, 'Suplemento não é remédio.'],
            ['Quais etapas de produto novo são da Técnica?', ['Formulação e ficha técnica; informações de rotulagem', 'Lançamento', 'Envio à gráfica', 'Nenhuma'], 0, 'As duas primeiras etapas técnicas.'],
        ]],
        ['tc-vizinho', 'vizinho', 'Como trabalha a Responsável Técnica', 'Fórmulas, documentos, aprovação das ordens e dúvidas técnicas.', 4, <<<'TXT'
## O que a Responsável Técnica faz
Cuida das fórmulas e fichas técnicas, da tabela nutricional, dos textos obrigatórios dos rótulos, dos documentos dos produtos e **aprova as ordens de produção**.

## Como acionar
Dúvida técnica de cliente: registre na ficha do cliente e encaminhe. Rótulo novo: no projeto de Produtos novos. Ordem de produção: ela aprova na própria ordem.
TXT, [
            ['Cliente fez uma pergunta técnica que você não sabe responder:', ['Registrar e encaminhar à Responsável Técnica', 'Inventar uma resposta', 'Ignorar', 'Dizer que cura'], 0, 'Dúvida técnica vai para a Técnica.'],
        ]],
    ]],
    // =====================================================================
    ['chave' => 'qualidade', 'funcao' => 'qualidade', 'titulo' => 'Qualidade', 'icone' => '✅',
     'vizinhos' => 'producao,tecnica,deposito,financeiro',
     'descricao' => 'Compras de matéria-prima, gráfica, código de barras e documentos dos fornecedores.',
     'modulos' => [
        ['ql-compras', 'setor', 'Pedidos de compra, da cotação ao recebimento', 'Pedir, cotar, aprovar, comprar e acompanhar a chegada.', 14, <<<'TXT'
## Objetivo
Comprar no tempo certo, pelo melhor preço, com tudo registrado do pedido à nota.

## A tela
Em [Pedidos de compra](/compras): as **sugestões** (matérias-primas acabando) e os orçamentos em cada situação.
![Pedidos de compra: sugestões e orçamentos](assets/img/treinamento/qualidade-compras.jpg)

## As situações de uma compra
Montando o orçamento → Esperando aprovação → (Pedir outra cotação) → Aprovado · comprar → Comprado · esperando chegar → **Recebido**.

## Passo a passo
1. **Novo orçamento** (ou a partir de uma sugestão): itens e quantidades.
![Novo orçamento: itens, fornecedores e cotações](assets/img/treinamento/qualidade-compra-nova.jpg)
2. Cote com os fornecedores e anexe as cotações. Compare com o **último preço** de cada fornecedor (está na página do item).
3. Mande para **aprovação**.
4. Aprovado: compre e marque **Comprado**, com a previsão de chegada.
5. O Depósito recebe; a compra vira **lançamento** em Compras da empresa, com a NF e o fornecedor.

?? O orçamento voltou com "Pedir outra cotação". || Leia o comentário do aprovador (preço alto? prazo?), cote com outro fornecedor e mande de novo. A compra fica registrada com as duas cotações.
?? A matéria-prima acaba em 5 dias e o fornecedor entrega em 10. || Avise a Produção para reorganizar as ordens, procure um fornecedor com entrega mais rápida (mesmo que mais caro, explique no orçamento) e marque como urgente para a aprovação.
TXT, [
            ['Onde ver as matérias-primas que estão acabando?', ['Nas sugestões dos Pedidos de compra', 'No catálogo', 'Nas Campanhas', 'No sino da vendedora'], 0, 'A previsão de consumo gera as sugestões.'],
            ['Depois de aprovado, o que você faz?', ['Compra e marca "Comprado" com a previsão de chegada', 'Nada', 'Recebe no lugar do Depósito', 'Cancela'], 0, 'O Depósito acompanha a chegada.'],
            ['Para comparar preços, olhe:', ['O último preço de cada fornecedor na página do item', 'O preço de venda', 'O Instagram', 'Nada'], 0, 'O histórico de compras mostra o último preço.'],
        ]],
        ['ql-fornecedores', 'setor', 'Fornecedores, documentos e rótulos', 'Laudos com validade, código de barras e gráfica.', 10, <<<'TXT'
## Fornecedores
Em [Fornecedores](/financeiro/fornecedores): o que cada um fornece, preços comparados, notas, prazo e documentos.
![Fornecedores: cada um com o que fornece e os documentos](assets/img/treinamento/financeiro-fornecedores.jpg)

## Documentos com validade
Na página do item e do fornecedor: laudos, fichas técnicas, FISPQ e certificados, com **validade e lote**. Vencendo, aparece nos alertas {{config:produtos.dias_aviso_documento}} dias antes. Documento vencido deixa o One bravo. 😠

## Rótulos e código de barras
- Código de barras, NCM e cadastro de produto novo (etapa do projeto).
- Envio à gráfica e aprovação da prova (com o Gestor de Marketing).
- Entrada dos rótulos no estoque, inclusive os dos clientes de terceirização.

?? Chegou matéria-prima sem laudo. || Dê entrada com o lote, mas registre a pendência e cobre o laudo do fornecedor. A Técnica não aprova ordem com lote sem laudo.
TXT, [
            ['Laudo do fornecedor com validade perto do fim:', ['Aparece nos alertas', 'Some', 'Vai para o Marketing', 'Não importa'], 0, 'O One avisa documento vencendo.'],
            ['Quem aprova a prova da gráfica com a Qualidade?', ['O Gestor de Marketing', 'O Depósito', 'A Cobrança', 'O cliente'], 0, 'Dois aprovadores na etapa.'],
        ]],
        ['ql-vizinho', 'vizinho', 'Como trabalha a Qualidade', 'Compras de matéria-prima, rótulos e documentos.', 4, <<<'TXT'
## O que a Qualidade faz
Compra matérias-primas (cotação e aprovação), cuida dos rótulos, do código de barras, da gráfica e dos documentos dos fornecedores.

## Para a Produção e o Depósito
Precisa de matéria-prima? Veja a previsão e avise a Qualidade. As compras aprovadas chegam no **Recebimento** do Depósito.
TXT, [
            ['As compras aprovadas aparecem onde para o Depósito?', ['No Recebimento', 'No Mural de posts', 'Na Agenda', 'No catálogo'], 0, 'O Depósito confere e dá entrada.'],
        ]],
    ]],
    // =====================================================================
    ['chave' => 'deposito', 'funcao' => 'deposito', 'titulo' => 'Separação e Expedição', 'icone' => '📦',
     'vizinhos' => 'producao,qualidade,ecommerce,financeiro',
     'descricao' => 'Separar e despachar pedidos, lote e validade, rastreio e recebimento das compras.',
     'modulos' => [
        ['dp-separar', 'setor', 'Separar e despachar', 'A fila, o leitor de código de barras, lote e rastreio.', 16, <<<'TXT'
## Objetivo
Despachar o pedido certo, completo e rastreável, no prazo.

## A fila
Em [Separação](/deposito): os pedidos **com nota** para separar, por tipo (lojista, e-commerce, terceirização). Urgentes e atrasados vêm primeiro. Pedido parado há mais de {{config:deposito.dias_atraso}} dias conta como atrasado.
![Separação: a fila por tipo, com urgentes e prazos](assets/img/treinamento/deposito.jpg)

## As situações
A separar → Separando → Separado → **Despachado**.

## Passo a passo
1. Abra o pedido e clique em **Começar a separar**.
![Separar o pedido: leitor de código de barras, quantidades e lote de cada item](assets/img/treinamento/deposito-separar.jpg)
2. Passe o **leitor de código de barras** em cada produto (ou digite o código e Enter): a quantidade separada sobe sozinha.
3. Informe o **lote** de cada item; a validade vem do lote. Item com dois lotes: "+ outro lote".
4. Informe a **caixa/embalagem de envio** usada.
5. Conclua a separação e, no despacho, informe a **transportadora** e o **rastreio**.

## Horários de corte do e-commerce
Mercado Livre até {{config:deposito.horario_mercado_livre}} · Shopee até {{config:deposito.horario_shopee}} · Amazon até {{config:deposito.horario_amazon}}.

## Rastrear lote
Em [Rastrear lote](/deposito/lotes): para quais clientes foi cada lote. É o que permite um **recolhimento** rápido se for preciso.
![Rastrear lote: digite o lote e veja pedidos e clientes](assets/img/treinamento/deposito-lotes.jpg)

?? O leitor leu um produto que não está no pedido. || A tela avisa "não é de nenhum item deste pedido". Confira se pegou o produto errado na prateleira (parecidos, como os magnésios) e troque. Nunca mande "parecido".
?? Falta 1 unidade de um item no estoque. || Não despache sem combinar: avise a vendedora (pela observação do pedido ou mensagem) para ela falar com o cliente; despache parcial só com o OK dela.
TXT, [
            ['Por que informar o lote de cada item?', ['Para saber depois para quais clientes foi cada lote', 'Por enfeite', 'Para o Marketing', 'Não precisa'], 0, 'O rastreio de lote depende disso.'],
            ['O leitor leu um produto que não é do pedido:', ['Manda mesmo assim', 'Confere e troca pelo produto certo', 'Ignora', 'Cancela o pedido'], 1, 'Nunca mande "parecido".'],
            ['Pedido despachado: o que informar?', ['Transportadora e rastreio', 'O pedido mínimo', 'A meta do mês', 'Nada'], 0, 'Cliente e vendedora acompanham a entrega.'],
            ['Faltou 1 unidade. O certo é:', ['Despachar sem avisar', 'Avisar a vendedora e só despachar parcial com o OK dela', 'Trocar por outro produto', 'Cancelar o pedido'], 1, 'Combine antes.'],
        ]],
        ['dp-receber', 'setor', 'Receber as compras', 'Conferir, dar entrada com lote e registrar divergências.', 8, <<<'TXT'
## Recebimento
Em [Recebimento](/deposito/recebimento) aparecem as compras aprovadas que vão chegar.
![Recebimento: compras esperando chegar](assets/img/treinamento/deposito-recebimento.jpg)

## Passo a passo
1. Confira **quantidade**, **lote** e **validade** com a nota fiscal.
2. Dê entrada: o estoque sobe sozinho e a compra vira lançamento com a NF.
3. Divergência (quantidade, avaria, validade curta)? Registre na observação e avise a Qualidade.

?? A validade do lote que chegou é de 3 meses, e o normal é 2 anos. || Não guarde sem avisar: registre a divergência e avise a Qualidade, que decide com o fornecedor (trocar, devolver ou aceitar).
TXT, [
            ['Chegou menos do que diz a nota:', ['Registrar a divergência e avisar a Qualidade', 'Dar entrada do total da nota', 'Devolver tudo sem falar', 'Ignorar'], 0, 'O registro garante a cobrança do fornecedor.'],
        ]],
        ['dp-vizinho', 'vizinho', 'Como trabalha o Depósito', 'Separação, despacho e recebimento.', 4, <<<'TXT'
## O que o Depósito faz
Separa e despacha os pedidos com nota (lojista, e-commerce e terceirização) com leitor de código de barras, registra lote e validade, informa o rastreio e recebe as compras.

## Como ajudar
- **Vendas:** combinados de entrega na **observação do pedido**.
- **Qualidade:** compras aprovadas com previsão de chegada.
- **E-commerce:** respeite os horários de corte.
TXT, [
            ['Onde deixar um combinado de entrega para o Depósito?', ['Na observação do pedido', 'No WhatsApp pessoal', 'No Mural de posts', 'Não precisa'], 0, 'O Depósito lê a observação ao separar.'],
        ]],
    ]],
];
