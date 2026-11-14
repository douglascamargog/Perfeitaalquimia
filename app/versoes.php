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
