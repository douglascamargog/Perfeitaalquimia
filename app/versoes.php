<?php
/**
 * Versões do One — o que mudou em cada uma (aparece para todos na janela "Novidades" e em /novidades).
 *
 * COMO NUMERAR (a mais nova sempre em PRIMEIRO na lista):
 *   - atualização pequena (ajustes, correções, uma tela nova)   → muda a decimal: 2.0 → 2.1 → 2.2
 *   - atualização grande (módulo novo, mudança no jeito de usar) → muda o número: 2.x → 3.0
 * O pacote sai com o nome da versão: ONE_V2.1.zip.
 *
 * Escreva para a EQUIPE (vendedores, financeiro, marketing...): frases curtas sobre o que a pessoa
 * percebe no dia a dia. Nada de "como instalar", backup, banco, telas de administração ou termos técnicos.
 *   'novo'     → o que apareceu de novo para quem usa
 *   'melhoria' → o que ficou melhor ou mais fácil
 *   'correcao' → problemas que a pessoa sentia e foram resolvidos
 *   'admin'    → (opcional) recado técnico só para quem administra o sistema
 *                (Usuários/Acessos, Configurações, Atualização, Integração, backup). A equipe não vê.
 */
return [
    [
        'versao' => '10.3', 'data' => '2026-11-14', 'titulo' => 'Ficha do cliente mais prática e discador',
        'novo' => [
            'Caixa rápida na linha do tempo do cliente: escreva uma anotação e cole o print de tela com Ctrl+V (ou anexe imagem/PDF). O print fica guardado na linha do tempo.',
            'Agendar atividade direto na ficha (ligar, WhatsApp, visita, reunião, enviar orçamento) com dia e hora: vai para a Agenda e aparece em "Próximas atividades", com o botão Concluir.',
            'Discador ao lado do sino (Televendas e Cobrança): digite qualquer número e ligue pelo Ligue Talk. Se o número for de um cliente, o One mostra a ficha e a ligação vai para a linha do tempo dele.',
            'Letreiro no topo: quando entra um pedido, passa "Novo pedido feito por (vendedor)" por 30 segundos para todo mundo.',
        ],
        'melhoria' => [
            'O comunicado do topo passa em movimento até você clicar e marcar "Li e entendi".',
            'A gestão recebe no sino "Novo pedido feito por (vendedor)" a cada pedido novo.',
            'O aviso de que o cliente abriu ou aprovou o orçamento vai só para o vendedor que fez o orçamento.',
        ],
        'admin' => [
            'Configurações › Ligue Talk: funções que veem o discador (padrão: televendas e cobranca). Configurações: liga/desliga o letreiro de pedido novo. Os prints da linha do tempo ficam em storage/clientes (fora da web) e só abrem para quem vê o cliente.',
        ],
    ],
    [
        'versao' => '10.2', 'data' => '2026-11-14', 'titulo' => 'Diretoria',
        'novo' => [
            'Novo acesso para a Diretoria: vê todas as áreas do One (vendas, financeiro, marketing, produção, depósito, compras, desenvolvimento) e aprova o que for pedido a ela, sem mexer no dia a dia.',
            'A Diretoria é avisada no sino do que interessa aos donos: etapa do desenvolvimento concluída e produto lançado, matéria-prima, embalagem, rótulo ou produto acabado abaixo do mínimo, ordem de produção criada e concluída, alguém da equipe batendo a meta e problemas (pedido recusado, despacho sem algum item, cobrança para jurídico).',
            'Diretor que também vende trabalha a própria carteira, pedidos e orçamentos normalmente.',
        ],
        'admin' => [
            'Novo perfil "Diretoria" (Administração › Pessoas e acessos): vê tudo e aprova (pedidos, posts, orçamentos de compras enviados a ela e provas do Desenvolvimento quando a função Diretoria é aprovadora), mas não altera nada nem entra em Configurações, Usuários, Atualização e Integração. Para o sócio que vende: dê a exceção "Diretoria que também vende" e as permissões de vendedor (carteira própria, pedidos, Meu mês); ele altera só os clientes em que é o responsável. Os avisos da Diretoria ligam/desligam em Configurações › Diretoria (também vão para o Admin que acompanha a área Diretoria).',
        ],
    ],
    [
        'versao' => '10.1', 'data' => '2026-11-14', 'titulo' => 'Marketing de cara nova',
        'novo' => [
            'Pedir ao Marketing: qualquer pessoa pede uma arte, um material, um cadastro na loja, um envio FULL ou uma oferta. O pedido vai direto para quem cuida daquele assunto e você é avisado quando ficar pronto.',
            'Em "Pedir ao Marketing" você acompanha a situação de cada pedido (a fazer, fazendo, esperando aprovação, feito) e pode trocar recados com o Marketing.',
            'Início do Marketing: resumo da semana, mini calendário com os posts e os prazos e a lista de tarefas (fazendo agora, a fazer, esperando aprovação e feito).',
            'Na lista de tarefas também aparecem os posts para aprovar e os rótulos do Desenvolvimento que esperam o Marketing.',
            'Cada função do Marketing recebe o que é dela: Social Media (posts e artes), Gestor de Marketing (aprovações e rótulos) e E-commerce e Marketplaces (perguntas, campanhas, cadastros, FULL e ofertas relâmpago).',
        ],
        'correcao' => [
            'A imagem do post na aprovação aparece inteira, sem corte (e abre em tamanho real com um clique).',
            'O aviso de "post para aprovar" agora abre o post direto.',
        ],
        'melhoria' => [
            'Planejamento e Leads do site saíram do menu por enquanto.',
        ],
        'admin' => [
            'Coloque cada pessoa do Marketing na sua função nova (Administração › Pessoas e acessos › Usuários): Social Media, Gestor de Marketing ou E-commerce e Marketplaces. Quem aprova o Marketing precisa da permissão "Aprovar posts". A etapa "Criação do rótulo" do Desenvolvimento passou para o Gestor de Marketing. Qual função recebe cada assunto: Configurações › Listas › Tipos de tarefa do Marketing.',
        ],
    ],
    [
        'versao' => '10.0', 'data' => '2026-11-14', 'titulo' => 'Orçamento por link e modelos de pedido',
        'novo' => [
            'Orçamento para o cliente: no Novo pedido, monte os produtos e toque em "Gerar orçamento". O One cria um link para mandar pelo WhatsApp.',
            'O cliente abre o link no celular sem senha, vê os produtos com os preços da tabela dele, muda as quantidades, acrescenta outros produtos e aprova ou recusa.',
            'Aprovado pelo cliente, o orçamento vira pedido sozinho (aguardando aprovação do Financeiro) e você recebe o aviso no sino. Se recusar, você vê o motivo.',
            'Baixar imagem do orçamento: uma imagem bonita com logo, produtos, total e validade, para mandar no WhatsApp.',
            'Nova aba Orçamentos no seu setor: os que estão esperando o cliente, os aprovados e os recusados ou vencidos. Você também fica sabendo quando o cliente abriu o link.',
            'Modelos de pedido: salve uma lista pronta de produtos e quantidades (ex.: "Primeiro pedido — loja pequena") e use no Novo pedido com um clique. Dá para usar mais de um modelo e ajustar depois.',
            'Campanhas: a gestão cria modelos para toda a equipe ou para um setor, com data para sair do ar.',
            'Tudo do orçamento (criado, enviado, aberto, aprovado ou recusado) aparece na linha do tempo do cliente.',
        ],
        'admin' => [
            'Configurações › Pedidos: validade do orçamento (padrão 15 dias) e a mensagem que vai junto do link no WhatsApp. O link usa só um código secreto (nenhum dado do cliente) e para de funcionar ao vencer ou ser cancelado. Quem cria pedidos cria orçamentos; campanhas: quem vê todos os pedidos ou é gestor(a) de setor.',
        ],
    ],
    [
        'versao' => '9.2', 'data' => '2026-11-14', 'titulo' => 'Menu mais organizado',
        'melhoria' => [
            'Gestão › Resultados: Painel de vendas, Equipe de vendas e Acompanhamento ficam numa entrada só, em abas.',
            '"Equipe" no topo do menu agora se chama "Pessoas" (para não confundir com a Equipe de vendas).',
            'Mensagens prontas passou para o Marketing.',
        ],
        'admin' => [
            'Administração mais enxuta: "Pessoas e acessos" junta Usuários, Funções e Acessos em abas; Configurações e Listas também viraram abas; Etapas do desenvolvimento foi para o grupo Desenvolvimento. "Dados de teste" só aparece no modo de demonstração (modo_demo no config.php).',
        ],
    ],
    [
        'versao' => '9.1', 'data' => '2026-11-14', 'titulo' => 'Produção: máximo e desperdício',
        'novo' => [
            'A prévia da ordem de produção mostra quantas unidades dá para produzir com o estoque de hoje e qual item limita.',
            'Desperdício medido: ao concluir a ordem, preencha "Usado de verdade" com o que foi pesado/contado de cada item. O sistema calcula a perda e passa a somá-la nas próximas ordens daquele produto.',
            'Produto que ainda não foi produzido usa a perda média já medida da matéria-prima em outros produtos.',
            'A ordem concluída mostra o desperdício medido de cada item (previsto × usado).',
        ],
    ],
    [
        'versao' => '9.0', 'data' => '2026-11-14', 'titulo' => 'Depósito e Orçamentos',
        'novo' => [
            'Setor novo: Depósito. Os pedidos com nota emitida entram numa fila de separação: urgentes primeiro, depois do mais antigo para o mais novo, com o painel do dia (a separar, para despachar, atrasados).',
            'Separar o pedido: item por item, com leitor de código de barras, quantidade separada e o lote e a validade de cada item (o sistema sugere o lote que vence primeiro).',
            'Se faltar produto, o depósito escolhe: aguardar (o vendedor e a Produção são avisados) ou despachar sem o que falta (o vendedor e o Financeiro são avisados).',
            'Despacho com transportadora, volumes, peso e código de rastreio. O vendedor recebe aviso, o pedido mostra a expedição e o cliente vê "Enviado" com o rastreio no catálogo.',
            'Rastrear lote: digite o lote e veja para quais clientes ele foi (lista pronta para um recolhimento).',
            'Orçamentos e compras: a Qualidade monta o orçamento de matéria-prima, rótulo, embalagem ou item avulso, com uma ou mais cotações e o anexo do fornecedor, e escolhe quem vai aprovar.',
            'Quem foi escolhido aprova, pede outra cotação ou recusa, pelo sino. Depois de aprovado, a Qualidade registra a compra e o Depósito confere a chegada: matéria-prima e rótulo entram sozinhos no estoque.',
            'Sugestões de orçamento: matérias-primas abaixo do mínimo e produtos novos do Desenvolvimento na etapa de compras.',
        ],
        'melhoria' => [
            'Nova ordem de produção: a prévia mostra tudo o que vai ser usado, separado em matérias-primas e embalagens (pote, tampa, selo, rótulo…), e avisa quando o produto ainda não tem as embalagens cadastradas.',
        ],
        'admin' => [
            'Crie o login de quem trabalha no depósito com o perfil novo "Depósito" e a função "Separação e Expedição". Os orçamentos são feitos por quem tem a função Qualidade. Em Configurações › Depósito: lote obrigatório, transportadoras sugeridas e dias para considerar atrasado.',
        ],
    ],
    [
        'versao' => '8.5', 'data' => '2026-11-14', 'titulo' => 'Ajuste no Desenvolvimento',
        'correcao' => [
            'O painel "Embalagem e cores" do projeto abre por cima dos balões (antes ficava atrás deles) e fecha ao clicar fora.',
        ],
    ],
    [
        'versao' => '8.4', 'data' => '2026-11-14', 'titulo' => 'Rótulos na Produção',
        'novo' => [
            'Produção › Rótulos: o estoque de rótulos num lugar só, dos nossos produtos e dos clientes de terceirização, com mínimo, alerta, o que as ordens planejadas vão gastar e a previsão de quando acaba.',
            'A Produção registra ali a chegada dos rótulos e a contagem. Na ordem de produção eles saem sozinhos.',
            'Os rótulos que chegam pelo Desenvolvimento de Produto já aparecem nessa aba.',
        ],
        'admin' => [
            'Para um rótulo já cadastrado aparecer na aba, marque "É rótulo de produto" no cadastro da embalagem em Produção › Matérias-primas (os com nome "Rótulo..." já foram marcados sozinhos).',
        ],
    ],
    [
        'versao' => '8.3', 'data' => '2026-11-14', 'titulo' => 'Mockup com as cores e a arte de verdade',
        'novo' => [
            'Ao criar um projeto, você escolhe a cor da embalagem (branco ou preto; o frasco também pode ser âmbar) e a cor da tampa: verde, preta, branca, dourada, vermelha, azul... ou qualquer outra cor.',
            'A embalagem já aparece inteira, com a tampa, desde a criação do projeto. Só falta o rótulo.',
            'Quando o Marketing envia a arte do rótulo (PNG ou JPG, planificada), ela envolve a embalagem do jeito que foi enviada, sem distorcer.',
            'Dá para trocar o tipo e as cores a qualquer momento no botão "Embalagem e cores" do projeto.',
        ],
    ],
    [
        'versao' => '8.2', 'data' => '2026-11-14', 'titulo' => 'Desenvolvimento: etapas ao mesmo tempo e o nosso pote',
        'novo' => [
            'Formulação e ficha técnica, Informações de rotulagem, Criação do rótulo e Compras de matéria-prima e embalagens ficam abertas ao mesmo tempo, logo depois do briefing. Cada área faz a sua parte sem esperar a outra.',
            'O projeto segue conforme cada parte é concluída. Só espera quando precisa: o envio à gráfica espera a arte, o lote piloto espera a ficha e as compras, e o lançamento espera tudo.',
            'Etapa concluída continua editável: clique no balão (✓ editar) para corrigir o código de barras, a arte, as compras e o que mais precisar.',
        ],
        'melhoria' => [
            'O pote em 3D ficou igual ao nosso pote de cápsulas: alto, tampa verde-escura brilhante, rótulo com a marca no alto, o nome grande e a faixa verde embaixo, com cápsulas dentro.',
        ],
        'admin' => [
            'Em Administração › Etapas do desenvolvimento há a coluna "Ao mesmo tempo" para escolher quais etapas andam juntas.',
        ],
    ],
    [
        'versao' => '8.1', 'data' => '2026-11-14', 'titulo' => 'Desenvolvimento de Produto virou jogo',
        'novo' => [
            'Ao criar um projeto, você escolhe o que vai fabricar: pote, frasco ou caixinha.',
            'A página do projeto mostra a embalagem em 3D no centro, com balões em volta: o que já foi feito, o que está faltando agora e o que vem depois. Dá para girar a embalagem com o mouse ou o dedo.',
            'A cada etapa concluída a embalagem vai se formando: aparece o conteúdo, o rótulo com a tabela nutricional, o código de barras, a arte do Marketing (a imagem enviada vira o rótulo), a tampa, o selo de aprovado… até a festa do lançamento.',
            'Quando você abre um projeto que avançou desde a sua última visita, a peça nova chega com animação e um aviso de quem fez.',
            'Clique no balão da etapa atual para abrir o que precisa ser feito nela.',
        ],
        'melhoria' => [
            'O quadro de projetos mostra o desenho da embalagem e quanto o produto já está "formado".',
        ],
        'correcao' => [
            'Equipe de vendas e as categorias do catálogo podiam dar erro em alguns servidores. Resolvido.',
        ],
    ],
    [
        'versao' => '8.0', 'data' => '2026-11-14', 'titulo' => 'Desenvolvimento de Produto',
        'novo' => [
            'Setor novo no menu: Desenvolvimento de Produto. Cada produto novo vira um projeto que passa por etapas, do briefing ao lançamento.',
            'Cada área faz a sua parte: a Responsável Técnica cuida da formulação, da ficha técnica e da rotulagem, o Marketing cria o rótulo, a Qualidade cuida do código de barras, das compras, da gráfica e do recebimento dos rótulos, e a Produção faz o lote piloto.',
            'A prova da gráfica é aprovada pelo Marketing e pela Qualidade. "Pedir ajuste" volta para a criação do rótulo com o comentário.',
            'Os rótulos recebidos entram sozinhos no estoque. No lançamento o produto aparece no catálogo (ou, se for de terceirização, nos produtos do cliente).',
            'Minhas tarefas: os projetos esperando a sua área, com aviso no sino.',
        ],
        'admin' => [
            'Coloque o funcionário "coringa" na função nova Qualidade (ele pode continuar também no Marketing). As etapas, a função de cada uma e quem aprova ficam em Administração › Etapas do desenvolvimento.',
        ],
    ],
    [
        'versao' => '7.2', 'data' => '2026-11-14', 'titulo' => 'Correção dos dados de teste',
        'correcao' => [
            'Gerar os dados de teste não dá mais erro, e ficou bem mais rápido.',
        ],
        'admin' => [
            'O erro vinha de recursos de banco que a hospedagem não tem (REGEXP_REPLACE e consultas agrupadas no modo estrito do MySQL). Se algo falhar de novo, a tela de Dados de teste mostra o motivo.',
        ],
    ],
    [
        'versao' => '7.1', 'data' => '2026-11-14', 'titulo' => 'Acompanhamento completo e Cobrança',
        'novo' => [
            'Acompanhamento mais completo de cada vendedora: meta do mês × faturado, WhatsApp e mensagens prontas, retornos no prazo e atrasados, e uma página com abas (Resumo, Ligações, Carteira e funil, Agenda, WhatsApp e contatos, Reativações e pedidos).',
            'Acompanhamento da Cobrança: valor recuperado, tentativas, promessas cumpridas e quebradas, títulos em atraso por etapa e as ligações com gravação de quem cuida da cobrança.',
            'Os números mostram ▲ ▼ em relação ao período anterior.',
        ],
        'correcao' => [
            'O gráfico da meta da equipe agora mostra um ponto por dia (vale o último valor lançado no dia), sem as linhas quebradas.',
            'Na Carteira de cada setor, o filtro de responsável mostra só a equipe daquele setor.',
        ],
        'admin' => [
            'Administração › Dados de teste: gera 60 dias de movimento simulado para as pessoas cadastradas (vendas, cobrança, produção e marketing) e apaga tudo o que foi simulado com um clique (backup antes).',
            'Para alguém aparecer como responsável no setor (ex.: a gestora do Televendas), coloque a pessoa na função do setor em Usuários (e marque "gestor(a) do setor" se coordenar a equipe).',
        ],
    ],
    [
        'versao' => '7.0', 'data' => '2026-11-14', 'titulo' => 'Carteiras e Acompanhamento com o Ligue Talk',
        'novo' => [
            'Gestão › Carteiras: a carteira de cada vendedora e as que estão sem ninguém. Quando alguém sai, a carteira inteira passa para outra pessoa com um clique; também dá para mover clientes entre carteiras, juntar carteiras e criar carteiras novas.',
            'Importar clientes direto para uma carteira (CSV, Excel ou XML, inclusive XML de nota fiscal): quem já existe muda para a carteira e quem é novo entra nela.',
            'Gestão › Acompanhamento: ligações, gravações, tempo falado, contatos, reativações e pedidos de cada vendedora num lugar só, por dia, semana ou mês.',
            'Botão "Ligar" na ficha do cliente, ligado ao Ligue Talk, e "Enviar para o discador" para mandar uma carteira inteira para o discador.',
        ],
        'melhoria' => [
            'Clientes de uma carteira sem vendedora ficam parados esperando alguém assumir (não vão sozinhos para a reativação).',
        ],
        'admin' => [
            'Ligue Talk: em Configurações › Ligue Talk escolha o modo (simulado na demonstração; "api" para o real). Para o real, coloque company_id e token (X-LTK-VND) em config.php › integracoes.ligue_talk e agende cron/sincronizar_ligacoes.php a cada 20 minutos (limite da API: 10 requisições/min). Em Usuários, preencha "Usuário no Ligue Talk" de cada pessoa.',
            'A API v1 do Ligue Talk não disca para um número: o "Ligar" abre o telefone/softphone, e as chamadas e gravações chegam pela sincronização. Configurações › "Campos recebidos" mostra os nomes dos campos que a API devolveu (para conferir a integração).',
            'Desativar um usuário deixa as carteiras dele sem vendedor(a). Gravações de demonstração ficam em storage/gravacoes.',
        ],
    ],
    [
        'versao' => '6.1', 'data' => '2026-11-14', 'titulo' => 'Gestão do setor',
        'novo' => [
            'Quem coordena um setor de venda e também vende (como a gestão do Televendas) continua com a própria carteira e o próprio Meu mês e agora vê a carteira, os pedidos, a agenda e a recompra de toda a equipe do setor.',
            'A gestão do setor pode passar clientes do setor de uma pessoa para outra da equipe, direto na ficha do cliente.',
        ],
        'admin' => [
            'Administração › Usuários › Funções: marque "gestor(a) do setor" ao lado da função de venda. A pessoa continua com o perfil Vendedor; vê só o(s) setor(es) que gerencia, não os outros.',
        ],
    ],
    [
        'versao' => '6.0', 'data' => '2026-11-13', 'titulo' => 'Menu por setores',
        'novo' => [
            'Cada setor de venda tem o seu espaço: Televendas, Externo, Terceirização e Prospecção e Reativação, cada um com a sua carteira.',
            'Dentro do seu setor ficam Carteira, Meu mês, Agenda, Pedidos e Recompra só com os clientes daquele setor.',
        ],
        'melhoria' => [
            'Menu lateral mais limpo: cada setor e cada área (Financeiro, Gestão, Marketing, Produtos, Produção) abre um submenu com as suas abas. O da tela em que você está já vem aberto.',
            'Você vê no menu só o seu setor de venda. O nome do setor aparece ao lado do título da tela.',
        ],
        'admin' => [
            'Administração › Funções: a coluna "Setor de venda" define quais funções têm carteira própria e grupo no menu. Gestor e Admin veem todos os setores; o ✕ ao lado do nome do setor mostra todos juntos.',
        ],
    ],
    [
        'versao' => '5.0', 'data' => '2026-11-13', 'titulo' => 'Produtos completo',
        'novo' => [
            'Produtos foi refeito: cada produto tem a sua página com abas — Visão geral, Conteúdo, Composição, Ficha técnica, Embalagens, Documentos e Histórico.',
            'Documentos dos produtos (laudos, FISPQ, registros, certificados, artes do rótulo) num lugar só, para abrir ou baixar.',
            'A parte técnica (composição, ficha técnica, embalagens e documentos) é preenchida pela Responsável Técnica e Química. Os demais consultam.',
            'Catálogo ganhou o seu próprio item no menu: é a vitrine do cliente (o que aparece, ordem, chamada e preços).',
        ],
        'melhoria' => [
            'Na lista de Produtos, selos mostram o que está faltando: sem ficha técnica, documento vencendo, oculto no catálogo, sem preço.',
            'O texto do produto (descrição, benefícios, perguntas) muda junto na Academia e no catálogo.',
            'Quem cuida da parte técnica recebe aviso do One quando um documento está para vencer.',
        ],
        'admin' => [
            'Perfil novo "Responsável Técnica" e função "Responsável Técnica e Química": vincule a pessoa em Usuários. Em Acessos, a linha Produtos agora tem "Editar" = parte técnica; o Catálogo tem linha própria.',
            'A ficha técnica em Produção › Fichas técnicas passou a ser editada só por quem tem a parte técnica (a Produção continua vendo).',
            'Os documentos ficam em storage/documentos/produtos (fora da pasta pública). Inclua essa pasta no backup do site. Aviso de vencimento: Configurações › Produção.',
            'Matérias-primas têm o campo Tipo (matéria-prima ou embalagem); potes, tampas, rótulos, lacres e caixas já foram classificados.',
        ],
    ],
    [
        'versao' => '4.3', 'data' => '2026-11-13', 'titulo' => 'Equipe e aniversários',
        'novo' => [
            'Equipe (no menu): conheça os colegas. Cada um tem um perfil com foto de capa, um pouco sobre si, hobbies, favoritos, família e pets.',
            'Monte o seu em Meu perfil › Meu perfil público.',
            'No seu aniversário, o One fica todo decorado para você. No Início de todos aparece o mural para deixar os parabéns.',
        ],
        'melhoria' => [
            'Clique na foto de um colega (aniversariantes, quem está online, ranking) para abrir o perfil dele.',
        ],
    ],
    [
        'versao' => '4.2', 'data' => '2026-11-13', 'titulo' => 'Comunicados e o seu apelido',
        'novo' => [
            'Comunicados da administração aparecem em destaque no alto da tela. Clique para ler e em "Li e entendi" para tirar da faixa. Todos ficam em Comunicados.',
        ],
        'melhoria' => [
            'O seu apelido ("Como prefere ser chamado(a)") aparece só para você. Para os colegas, nos aniversariantes, no ranking e em quem está online, aparece o seu nome.',
            'A previsão do tempo do Início agora é de São Leopoldo e mostra a hora em que foi atualizada.',
        ],
        'admin' => [
            'Administração › Comunicados: escolha os setores (funções) ou toda a empresa, a prioridade e até quando fica no topo. Acompanhe quem já leu.',
            'Quem pode emitir: Administração › Acessos › "Comunicados (emitir)" (padrão: só o admin).',
            'A cidade da previsão continua em Configurações › Início (latitude e longitude).',
        ],
    ],
    [
        'versao' => '4.1', 'data' => '2026-11-13', 'titulo' => 'Página do cliente',
        'novo' => [
            'Cada cliente tem a sua página com tudo da empresa: quanto comprou em 12 meses, ticket médio, próxima compra prevista, todos os pedidos, o que mais compra, retornos abertos e a linha do tempo completa.',
            'A ficha lateral continua como acesso rápido. Para ver tudo, clique em "Abrir página completa".',
        ],
    ],
    [
        'versao' => '4.0', 'data' => '2026-11-13', 'titulo' => 'Equipe de vendas, Meu mês do vendedor e Terceirização',
        'novo' => [
            'Equipe de vendas (menu Comercial): a meta da equipe e os números de cada frente (Externos, Televendas e Terceirização), com o ranking do mês.',
            'Meu mês › Meus clientes: seus melhores clientes e quem está há tempo sem comprar, com WhatsApp e a ficha a um clique.',
            'Terceirização (menu Comercial): clientes, pedidos, produtos com a marca de cada cliente e o estoque de rótulos.',
            'No pedido, os produtos com a marca do cliente aparecem junto com os nossos produtos, mesmo para clientes de Televendas ou Externo.',
        ],
        'melhoria' => [
            'Meu mês ficou só seu: suas vendas, sua meta e seus clientes. A meta da equipe está em Equipe de vendas.',
            'Produção: a ordem pode ser para um cliente de terceirização e usa os rótulos dele.',
        ],
        'admin' => [
            'Administração › Funções: cada função tem a frente de venda (Externos, Televendas, Terceirização). Os números da Equipe seguem a carteira do cliente.',
            'Acessos novos: Equipe de vendas e Terceirização (Ver / Ver e editar).',
            'Fórmula própria do cliente: cadastre a ficha técnica em Produção › Fichas técnicas e o preço na tabela Terceirização (ou no próprio produto do cliente).',
        ],
    ],
    [
        'versao' => '3.0', 'data' => '2026-11-13', 'titulo' => 'Visual novo nas cores da Perfeita Alquimia',
        'novo' => [
            'Visual novo, com o verde-petróleo e a menta da Perfeita Alquimia e a fonte da marca.',
            'Escolha as suas cores: em Meu perfil › Aparência há combinações prontas ou você monta a sua. Valem só para você.',
            'Fotinhas de quem está online no alto da tela. Clique para ver quem é e em que aba está (nunca o cliente que a pessoa está vendo).',
        ],
        'melhoria' => [
            'Menu lateral novo: a aba aberta "encaixa" na tela, fica fácil saber onde você está.',
            'As abas de Carteira, Prospecção, Produção, Marketing, Cobrança, Academia e Meu mês viraram abas de pasta.',
        ],
        'admin' => [
            'Cores padrão da empresa em Configurações › Aparência (valem para quem não escolheu as suas e para o catálogo do cliente).',
            'Fonte Montserrat gravada no próprio sistema (public/assets/fonts), sem depender do Google.',
        ],
    ],
    [
        'versao' => '2.4', 'data' => '2026-11-13', 'titulo' => 'Pedido no app e tabela de produtos',
        'correcao' => [
            'App do externo: a parte do pagamento não fica mais na frente dos produtos. Uma barra fina mostra o total e o botão "Pagamento" leva até as formas de pagamento.',
            'Produtos: os títulos das colunas estavam desalinhados e o botão Salvar ficava cortado. A tabela agora cabe na tela e os títulos ficam parados no alto ao rolar.',
        ],
    ],
    [
        'versao' => '2.3', 'data' => '2026-11-13', 'titulo' => 'Meu perfil',
        'novo' => [
            'Em Meu perfil você pode mudar o seu nome, além da foto, do aniversário e dos contatos.',
        ],
        'correcao' => [
            'Fotos tiradas no celular ficam na posição certa.',
        ],
        'admin' => [
            'O que aparece para cada pessoa (Início, sino, dicas) agora é definido só pelo administrador, em Administração › Usuários › "O que aparece para esta pessoa". Saiu do Meu perfil.',
            'Se o servidor não tiver suporte a WebP, a foto é gravada em JPG. Se faltar permissão na pasta public/media/equipe, a mensagem diz isso.',
        ],
    ],
    [
        'versao' => '2.2', 'data' => '2026-11-13', 'titulo' => 'Novidades mais fáceis de ler',
        'admin' => [
            'As novidades de cada versão agora mostram para a equipe só o que muda no dia a dia dela.',
            'Recados técnicos das versões (instalação, banco, telas de administração) aparecem só para quem administra o sistema.',
        ],
    ],
    [
        'versao' => '2.1', 'data' => '2026-11-13', 'titulo' => 'Sistema mais estável nas atualizações',
        'correcao' => [
            'O One saía do ar logo depois de algumas atualizações. Agora as atualizações acontecem sem interromper o seu trabalho.',
        ],
        'admin' => [
            'Para atualizar, basta extrair o pacote: na primeira visita, o One faz o backup do banco e aplica sozinho as mudanças. O botão "Aplicar" não é mais necessário.',
            'Se a atualização do banco falhar, aparece uma página de manutenção e o erro fica em storage/logs/php-erros.log.',
        ],
    ],
    [
        'versao' => '2.0', 'data' => '2026-11-13', 'titulo' => 'Menu completo e Início do seu jeito',
        'novo' => [
            'Todas as abas aparecem no menu. O cadeado 🔒 indica que você não tem acesso; o olho 👁 indica que você pode ver, mas não alterar.',
            'O Início, o sino e as dicas do One mostram o que é da sua função.',
            'Novidades: depois de cada atualização, esta janela mostra o que mudou. Todas as versões ficam no link do rodapé do menu.',
        ],
        'melhoria' => [
            'Vendedores podem acompanhar o Mural de posts e o Planejamento do Marketing.',
            'Ao abrir uma aba sem acesso, aparece uma explicação de como pedir a liberação.',
        ],
        'admin' => [
            'Administração › Acessos: para cada perfil, cada aba fica Sem acesso, Ver ou Ver e editar, mais as ações especiais.',
            'Exceções por pessoa no cadastro do usuário ("Acessos desta pessoa").',
            'Áreas de foco de cada função em Administração › Funções. Quem é admin e trabalha numa área deve ter a função dela marcada.',
            'As mudanças de acesso ficam registradas na Auditoria.',
        ],
    ],
    [
        'versao' => '1.4', 'data' => '2026-11-13', 'titulo' => 'Notificações',
        'novo' => [
            'Sino no topo com o que aconteceu: pedido para aprovar, pedido aprovado, recusado ou faturado, lead novo, cliente que passou para você.',
            'Número vermelho no menu mostrando o que está pendente em cada aba.',
            'Etiqueta "novidade" nos clientes e pedidos que mudaram desde a última vez que você viu.',
        ],
        'correcao' => [
            'Depois de salvar, a página continua onde você estava, sem voltar para o topo.',
        ],
    ],
    [
        'versao' => '1.3', 'data' => '2026-11-13', 'titulo' => 'Código do produto, Meu perfil e busca rápida',
        'novo' => [
            'Código do produto no catálogo, nos pedidos, no app, na Academia e na Produção.',
            'Meu perfil: sua foto, aniversário e contatos. Os aniversariantes do mês aparecem no Início.',
            'Busca rápida no topo (tecla /): encontre um cliente pelo nome, CNPJ ou telefone, ou um produto pelo código.',
            'Academia › Como usar o One: o passo a passo do sistema.',
        ],
        'melhoria' => [
            'As mensagens de WhatsApp da Recompra e da Cobrança são as mesmas de Mensagens prontas.',
        ],
        'correcao' => [
            'O catálogo do lojista não mostrava produtos para colocar no carrinho.',
        ],
        'admin' => [
            'Cadastro e importação dos códigos por planilha em Conteúdo › Produtos.',
            'Cópia de segurança automática do banco todos os dias (data do último backup em Administração › Atualização).',
        ],
    ],
    [
        'versao' => '1.2', 'data' => '2026-11-13', 'titulo' => 'Prospecção, mensagens prontas e leads do site',
        'novo' => [
            'Prospecção: lista de lojas para abordar e busca de empresas por cidade para mandar ao funil.',
            'Mensagens prontas de WhatsApp com o nome do cliente e o seu link, direto da ficha do cliente.',
            'Quem preenche o formulário do site ou dos anúncios entra no funil como lead.',
        ],
        'admin' => [
            'Tela Administração › Integração Omie: o que vem e vai para o ERP, testar conexão e sincronizar.',
        ],
    ],
    [
        'versao' => '1.1', 'data' => '2026-11-13', 'titulo' => 'Produção e matérias-primas',
        'novo' => [
            'Matérias-primas com entrada de compras e previsão de quando acabam.',
            'Fichas técnicas e ordens de produção: ao concluir, o estoque das matérias-primas baixa sozinho.',
            'Estoque de produto pronto com aviso quando precisa produzir mais.',
        ],
    ],
    [
        'versao' => '1.0', 'data' => '2026-11-12', 'titulo' => 'Primeira versão do One',
        'novo' => [
            'Carteira de clientes com funil, mapa, linha do tempo, agenda de retornos e reativação.',
            'Catálogo para o cliente fazer pedidos, com o seu link de vendedor.',
            'Pedidos com aprovação do Financeiro, painel de vendas, metas e alerta de recompra.',
            'App para o vendedor externo, que funciona até sem internet.',
            'Academia, Cobrança e Marketing.',
        ],
    ],
];
