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
        'versao' => '13.7', 'data' => '2026-11-16', 'titulo' => 'O One dormindo de verdade',
        'melhoria' => [
            'Depois do horário, o One acena, boceja, deita e dorme com os "Zzz".',
            'O One pensativo ficou mais expressivo: mão no queixo e sobrancelha levantada com um sorrisinho.',
        ],
    ],
    [
        'versao' => '13.6', 'data' => '2026-11-16', 'titulo' => 'Hora de descansar',
        'novo' => [
            'Depois das 17h45 o One dorme e avisa que é hora de descansar.',
            'O "bom dia" do Início muda com o seu dia: no seu aniversário o One aparece com balões; com retornos atrasados, preocupado; com a meta batida, pulando de alegria.',
        ],
        'admin' => 'O horário de descanso fica em Configurações › One (IA) › "Hora de descansar" (vazio desliga).',
    ],
    [
        'versao' => '13.5', 'data' => '2026-11-16', 'titulo' => 'Mais reações do One',
        'novo' => [
            'O One pula de alegria nas suas conquistas (pedido enviado, meta batida, agenda zerada) e traz balões e chapeuzinho nos aniversários e quando a equipe bate a meta.',
            'E fica bravo, de mãos na cintura, quando algo sério está parado, como um pedido esperando aprovação há mais de um dia. Se passar de três dias, ele fica vermelho de raiva.',
        ],
    ],
    [
        'versao' => '13.4', 'data' => '2026-11-16', 'titulo' => 'O One ganhou vida',
        'novo' => ['O One agora se mexe e reage ao que está acontecendo: acena quando você chega, pisca quando está só esperando você, pensa com a mão no queixo enquanto procura uma resposta, fica tristinho quando algo está atrasado e sai correndo quando vai buscar alguma coisa para você.'],
        'melhoria' => ['Ele não fica mais correndo o tempo todo: no canto da tela, fica parado e sorrindo.'],
    ],
    [
        'versao' => '13.3', 'data' => '2026-11-16', 'titulo' => 'O One de cara nova',
        'novo' => ['O One ganhou um visual novo: agora é um brotinho verde, com expressões para cada momento: acenando quando você chega, comemorando as conquistas, pensativo enquanto procura uma resposta e tristinho quando algo dá errado.'],
    ],
    [
        'versao' => '13.2', 'data' => '2026-11-16', 'titulo' => 'Treinamento do Comercial aprofundado',
        'novo' => [
            'Trilha nova "Conhecimento de produto" para quem vende: como ler a ficha (porção, quantidade por porção), cada linha (cápsulas, chás, pós, dose única, gotas), as diferenças que confundem o cliente, o que pode e o que não pode ser dito e como montar o mix de cada loja.',
            'Casos práticos em cada módulo: situações do dia a dia (cliente que pede desconto, pedido abaixo do mínimo, loja que fechou...) com o que fazer. É só clicar para ver a resposta.',
        ],
        'melhoria' => [
            'Televendas, Externo, Prospecção e Terceirização reescritos com passo a passo e prints das telas do One, erros comuns e provas mais completas.',
            'As regras aparecem com os valores de hoje: pedido mínimo de cada região, formas de pagamento, limite de desconto e dias para a Reativação. Mudou a regra, o treinamento acompanha.',
        ],
        'admin' => 'Os prints do treinamento são gerados com os dados de demonstração (ferramentas/capturar_treinamento.js) e vêm no pacote. Pontos marcados "Confira com a sua gestão" (prazos de entrega, troca e devolução, mínimos de terceirização) pedem a regra oficial da empresa: dá para escrever em Treinamento › Editar. Próxima etapa: Fábrica, Marketing, Financeiro e gestão.',
    ],
    [
        'versao' => '13.1', 'data' => '2026-11-16', 'titulo' => 'Ajuste no passo a passo do One',
        'correcao' => ['O passo a passo do One aberto em "O que dá pra fazer aqui?" mostrava a palavra "null" embaixo dos botões. Não mostra mais.'],
    ],
    [
        'versao' => '13.0', 'data' => '2026-11-16', 'titulo' => 'Treinamento novo, feito para cada setor',
        'novo' => [
            'Treinamento refeito: cada setor tem a sua trilha (Televendas, Externo, Produção, Marketing, Depósito, Qualidade, Financeiro e todos os outros), mais a Integração para todo mundo e "Áreas com que você tem contato", que explica como trabalham os setores que conversam com o seu.',
            'Cada módulo termina com uma provinha: acertou 70%, está concluído (pode refazer quantas vezes quiser). Terminou a trilha do seu setor? Ganha um selo no perfil.',
            'O One agora ensina cada tela: quem é novo vê o passo a passo, com a tela apontando cada parte, até clicar em "Aprendi tudo desta tela".',
            'Para todo mundo: chame o One e clique em "O que dá pra fazer aqui?" para ver o passo a passo da tela em que você está.',
        ],
        'melhoria' => ['As fichas dos produtos agora ficam na Biblioteca de produtos, dentro do Treinamento, abertas para todos os setores.'],
        'admin' => 'Gestores, Gestor de setor e Diretoria acompanham em Treinamento › Equipe (módulos, notas e telas aprendidas) e podem "Reiniciar tutorial" de alguém. Admin e Marketing editam módulos, provas e o passo a passo de cada tela em Treinamento › Editar; o que for editado lá não é trocado pelas atualizações. Quem já usava o sistema não é interrompido pelo tutorial; quem for criado em Usuários a partir de agora recebe. A nota mínima da prova fica em Configurações › Treinamento. As aulas antigas da Academia saíram da tela e continuam guardadas no banco.',
    ],
    [
        'versao' => '12.7', 'data' => '2026-11-16', 'titulo' => 'Ligue Talk de verdade',
        'novo' => ['Com o Ligue Talk ligado, as suas ligações e gravações aparecem sozinhas no Acompanhamento e na ficha do cliente, sem precisar registrar à mão.'],
        'correcao' => ['Previsão do tempo no Início: o "hoje" agora mostra só as horas que ainda faltam (antes contava a chuva que já tinha passado), e à noite a dica fala do tempo de amanhã.'],
        'admin' => 'O Ligue Talk agora é configurado pela tela: ⚙️ Administração › Ligue Talk. Cole o código da empresa (company_id) e o token da API (pedidos ao suporte do Ligue Talk), escolha o modo "API do Ligue Talk" e clique em "Testar conexão": aparece a lista dos usuários do Ligue Talk e quem já está ligado a alguém do One. Para cada pessoa, preencha o "Usuário no Ligue Talk" em Pessoas e acessos. O token fica em config/segredos.php (protegido, fora do banco e dos pacotes) e não aparece de novo. "Remover" volta ao registro manual.',
    ],
    [
        'versao' => '12.6', 'data' => '2026-11-16', 'titulo' => 'One para todos os setores',
        'novo' => [
            'O One agora dá dicas para todos os setores: Diretoria (faturado do mês, pedidos parados, cobrança), Marketing (tarefas atrasadas e pedidos novos), Produção e Técnica (ordens atrasadas e para aprovar), Financeiro, Depósito e Gestão.',
            'Para todo mundo: aviso quando o Marketing termina um pedido seu, recados novos no seu perfil e um "Você sabia?" por dia.',
        ],
        'melhoria' => ['O painel do One ficou organizado em duas abas: "Para você" (as dicas) e "Perguntar" (a conversa, com o campo de escrever embaixo, como num chat).'],
    ],
    [
        'versao' => '12.5', 'data' => '2026-11-15', 'titulo' => 'Chave da IA pela tela',
        'melhoria' => ['Ajustes internos para ligar a IA do One com mais facilidade.'],
        'admin' => 'A chave da Anthropic agora é cadastrada pela tela: ⚙️ Administração › Pergunte ao One › "Chave da IA" (não precisa mais editar o config.php). Ela fica em config/segredos.php, protegida, fora do banco e dos pacotes, e nunca aparece de novo. Depois use "Testar a IA agora".',
    ],
    [
        'versao' => '12.4', 'data' => '2026-11-15', 'titulo' => 'Pergunte ao One',
        'novo' => [
            'Pergunte ao One: abra o One e escreva a sua dúvida, do jeito que falaria com um colega. Ex.: "como faço um orçamento?", "quanto falta para a minha meta?", "quais campanhas estão no ar?".',
            'Ele responde com o caminho certo no sistema (com o link da tela), os seus números do mês e as campanhas. Lembra do assunto enquanto a conversa está aberta.',
        ],
        'melhoria' => ['Menu: a escolha do setor no Comercial ficou numa linha só ("Setor ▾"), mais limpa.'],
        'admin' => 'A IA (Claude) vem DESLIGADA: sem ela, o One responde com as aulas "Como usar o One" e os dados do sistema, sem custo e sem nada sair do servidor. Para ligar: a chave da Anthropic no config.php (integracoes.anthropic.chave, a mesma da busca de contatos) e Configurações › One (IA). Lá também: se a IA pode consultar clientes (LGPD, desligado por padrão; cada pessoa só vê a própria carteira) e o limite de perguntas por hora. O texto das perguntas não é guardado; o uso e o custo estimado aparecem em Administração.',
    ],
    [
        'versao' => '12.3', 'data' => '2026-11-15', 'titulo' => 'O One mais presente',
        'novo' => [
            'O One puxa conversa sozinho quando há algo importante na tela: retornos atrasados na Agenda, por qual cliente começar na Recompra, pedidos esperando na Aprovação, e-commerce com horário no Depósito e outros.',
            'Os balões do One trazem botões que já fazem a coisa: abrir o cliente, ligar, mandar WhatsApp com a mensagem pronta, deixar o retorno para amanhã, repetir o último pedido.',
            '"O que dá pra fazer aqui?": o One explica a tela aberta em poucos passos, com o link para a aula da Academia.',
            'O One comemora com você: pedido enviado, cliente reconquistado, agenda do dia zerada, meta batida e cada faixa da campanha que o cliente ganha no pedido.',
            'Não quer que ele puxe conversa? Em Meu perfil › O One dá para deixar ele quietinho (ele só fala quando você clica). "Hoje não" no balão também funciona.',
        ],
    ],
    [
        'versao' => '12.2', 'data' => '2026-11-15', 'titulo' => 'Mais rápido com muitos clientes',
        'melhoria' => [
            'O sistema ficou mais rápido para carteiras grandes: o Início dos gestores, o sino, a Recompra, as Carteiras e o Acompanhamento abrem bem mais depressa.',
            'Aprovação de pedidos: a fila mostra 30 pedidos por página (os mais antigos primeiro), sem demora mesmo com muitos pedidos esperando.',
        ],
        'admin' => 'Teste de carga com 30 pessoas e 20 mil clientes: dia normal com metade das telas abrindo em até 0,02 s e 95% em até 0,14 s, sem nenhum erro. Novos índices no banco (migration 044). O teste pode ser repetido: tests/carga/LEIA-ME.md.',
    ],
    [
        'versao' => '12.1', 'data' => '2026-11-15', 'titulo' => 'Menu mais enxuto',
        'novo' => [
            'Comercial: Televendas, Externo, Terceirização e Prospecção agora ficam juntos no grupo Comercial. Quem trabalha em mais de um setor escolhe o setor no alto do grupo.',
            'Reativação em cada setor: cada vendedora vê os próprios clientes sem compra há algum tempo e tenta reconquistá-los. O cliente continua com ela.',
            'Treinamento: a Academia e as aulas "Como usar o One" ganharam um menu próprio.',
            'Pessoas virou um ícone no alto da tela, ao lado do sino.',
        ],
        'melhoria' => [
            'Estoque (matérias-primas, rótulos e estoque acabado) agora fica dentro de Produção.',
            'Produtos novos e as tarefas de desenvolvimento agora ficam dentro de Produtos.',
            'Trocar a senha fica em Meu perfil (clique no seu nome no alto da tela).',
            'Sem a faixa "Ambiente de demonstração" no alto das telas.',
        ],
        'admin' => 'Administração saiu do menu lateral: agora é a engrenagem ao lado do botão Sair, com uma página de cartões (Pessoas e acessos, Configurações, Comunicados, Integração, Atualização, Auditoria). Reativação: Configurações › Carteira › "Quem reativa o cliente sem compra" (vendedora = padrão; fila = jeito antigo).',
    ],
    [
        'versao' => '12.0', 'data' => '2026-11-15', 'titulo' => 'Campanhas comerciais e catálogo novo',
        'novo' => [
            'Comercial › Campanhas: campanhas do mês ou da semana com faixas pelo valor do pedido. Exemplos: "acima de R$ 2.000, 10% de desconto", "acima de R$ 10.000, ganha uma bicicleta" ou "frete grátis".',
            'Produtos em promoção na campanha: o cliente vê o preço antigo riscado e o novo, com o selo "-X%".',
            'Cada campanha escolhe para quem vale: todos os clientes, uma tabela de preço, uma região, um estado ou um setor.',
            'Catálogo do cliente com visual novo: banner grande, as faixas da campanha, as ofertas em destaque e a lista de produtos. As cores e o banner mudam com a campanha.',
            'Enquanto compra, o cliente vê quanto falta para a próxima faixa ("Faltam R$ 200 para você ganhar 10% de desconto") e o que já ganhou.',
            'Novo pedido da equipe: o resumo mostra a campanha, o que o cliente já ganhou e quanto falta, e os preços promocionais aparecem na lista.',
            'O pedido guarda o que a campanha deu: desconto, brinde e frete grátis aparecem na página do pedido, na Aprovação e na separação ("colocar o brinde na caixa").',
        ],
        'melhoria' => [
            'Com duas campanhas ao mesmo tempo, o cliente ganha o melhor de cada uma: o maior desconto, todos os brindes conquistados e o frete grátis.',
            'Duplicar uma campanha cria a do mês seguinte com tudo pronto. Dá para desligar e ligar sem apagar.',
        ],
        'admin' => 'Gestor cria e muda campanhas (permissão campanhas.gerenciar); vendedores, Financeiro e Marketing só veem. Visual padrão do catálogo em Comercial › Campanhas › Visual do catálogo.',
    ],
    [
        'versao' => '11.8', 'data' => '2026-11-15', 'titulo' => 'Desconto da vendedora no pedido',
        'novo' => [
            'Novo pedido: coluna "Desc. %" em cada produto e o campo "Desconto no pedido (%)". O total é recalculado na hora.',
            'O resumo mostra o desconto total em R$ e em %. Passou do limite, o aviso fica vermelho: o pedido vai para a Aprovação marcado "desconto acima do limite" e os gestores são avisados.',
            'A página do pedido mostra o preço de tabela, o desconto de cada produto e quanto a vendedora deu de desconto.',
        ],
        'melhoria' => ['O pedido mínimo é conferido pelo preço de tabela, igual na tela e no envio.'],
        'admin' => 'Configurações › Pedidos: desconto máximo da vendedora sem aprovação (padrão 10%).',
    ],
    [
        'versao' => '11.7', 'data' => '2026-11-15', 'titulo' => 'Tabela nutricional e ficha técnica para imprimir',
        'novo' => [
            'Ficha técnica: bloco "Tabela nutricional" no modelo da Anvisa. Preencha porção, porções por embalagem e nutrientes, e a tabela vai sendo desenhada ao lado.',
            'O %VD é calculado sozinho pelos valores diários de referência da Anvisa para adultos. Dá para digitar outro valor quando precisar.',
            'Baixe a tabela nutricional como imagem (PNG em alta resolução) ou PDF, prontos para mandar à gráfica.',
            'Botão "Imprimir ficha técnica": documento A4 com identificação, composição (com % de cada matéria-prima), ingredientes, tabela nutricional, modo de uso, armazenamento, alergênicos, advertências, embalagem e assinatura da Responsável Técnica.',
            'As tabelas nutricionais que já estavam nos produtos (Academia) foram trazidas para a ficha técnica, com o %VD recalculado pelos valores atuais da Anvisa.',
        ],
    ],
    [
        'versao' => '11.6', 'data' => '2026-11-15', 'titulo' => 'Pedir ao Marketing mais enxuto',
        'melhoria' => [
            'Pedir ao Marketing: saíram da lista de assuntos "Rótulo ou embalagem", "Campanha de desconto ou oferta relâmpago" e "Envio FULL". Os pedidos antigos desses assuntos continuam guardados.',
        ],
        'admin' => 'Os assuntos podem ser religados em Configurações › Listas › Tipos de tarefa do Marketing.',
    ],
    [
        'versao' => '11.5', 'data' => '2026-11-15', 'titulo' => 'Ordem de produção no modelo da empresa e lotes das matérias-primas',
        'novo' => [
            'Ordem de produção impressa no modelo da empresa (PLA08.1 · Check list – Elaboração produto), em A4 deitado e numa folha só.',
            'A OP impressa já sai preenchida: peso unitário e peso em gramas de cada matéria-prima, fabricante, CAS/INS, lote do fabricante, fabricação, validade, embalagens, total de pó e perda.',
            'Na OP impressa ficam em branco, para anotar na fábrica: hora e responsável de cada etapa, teste de pesagem das 50 cápsulas, peso médio e tempo de fabricação.',
            'Lotes das matérias-primas: na entrada da compra (Produção ou recebimento no Depósito) informe o lote do fabricante, a fabricação e a validade.',
            'A OP sugere sozinha o lote que vence primeiro. Dá para escolher outro, e na conclusão fica registrado de qual lote veio cada matéria-prima.',
            'A Responsável Técnica revisa e aprova a OP no One, e o nome dela sai impresso em "Revisado por" e "Aprovado por".',
            'Na OP nova: forma (cápsula, frasco/pote, sachê, líquido), cápsulas por frasco, amostra, fornecedor da matéria-prima e data de entrada na produção.',
            'No cadastro da matéria-prima: fabricante e CAS/INS.',
        ],
        'admin' => 'Configurações › Produção: código, elaboração e revisão do documento da OP e se o lote é obrigatório na entrada de matéria-prima (ligado por padrão; embalagens e itens de consumo não pedem lote).',
    ],
    [
        'versao' => '11.4', 'data' => '2026-11-15', 'titulo' => 'Pedidos do e-commerce na separação',
        'novo' => [
            'Depósito: os pedidos do e-commerce (Mercado Livre, Shopee, Amazon e loja própria) chegam sozinhos da Tray e entram na fila de separação assim que a nota fiscal é emitida.',
            'Cada pedido mostra o horário limite do canal ("sai até 13:00 · faltam 1h20"). Fica laranja quando falta menos de 1 hora e vermelho quando passa do horário. Os mais urgentes vão para o topo da fila.',
            'Filtro na separação: Todos, Lojistas, E-commerce e Terceirização, com a quantidade de cada um e um selo colorido em cada pedido.',
            'Botão "Buscar pedidos da Tray" para trazer na hora. A busca também roda sozinha a cada 15 minutos.',
        ],
        'melhoria' => [
            'Os compradores do e-commerce não entram na carteira, nas metas nem no painel de vendas da equipe. O e-commerce continua com o painel próprio.',
        ],
        'admin' => 'Configurações › Depósito: horário limite de cada canal (padrão: Mercado Livre 13:00, Shopee 11:00, Amazon 12:00, loja própria sem horário). Agende no cPanel o arquivo cron/pedidos_ecommerce.php a cada 15 minutos. Com a loja real ligada (Configurações › E-commerce), confira com a Tray os campos de marketplace e nota fiscal na primeira importação.',
    ],
    [
        'versao' => '11.3', 'data' => '2026-11-15', 'titulo' => 'Caixas sempre aparecem na separação',
        'correcao' => [
            'Separação do pedido: o quadro "Caixas e embalagem usadas" aparece sempre. Toda caixa cadastrada entra na lista, mesmo sem a categoria "Embalagem de envio".',
            'Sem nenhuma caixa cadastrada, o quadro explica como cadastrar e tem o botão "+ Cadastrar caixa", que já abre o cadastro preenchido.',
        ],
    ],
    [
        'versao' => '11.2', 'data' => '2026-11-15', 'titulo' => 'Caixas usadas na separação',
        'novo' => [
            'Depósito: na separação, anote qual caixa (e fita, se quiser) e quantas foram usadas para embalar o pedido. Dá baixa sozinha no estoque das caixas.',
            'Corrigiu a quantidade antes de concluir? O estoque é acertado sozinho, sem baixar duas vezes.',
            'No despacho, os volumes já vêm preenchidos com o número de caixas. A embalagem usada fica no histórico do pedido.',
            'As caixas entram no consumo do item: a página da caixa em Compras da empresa mostra quanto tempo o estoque dura e quando comprar de novo.',
        ],
        'melhoria' => [
            'Separação mais rápida: não precisa mais digitar a validade. Ela vem sozinha do lote, e o "vence em…" aparece ao lado do lote para conferir.',
        ],
        'admin' => 'Configurações › Depósito: categorias que aparecem como caixa/embalagem de envio (padrão "Embalagem de envio") e se a caixa é obrigatória para concluir a separação. Cadastre as caixas em Compras da empresa › Itens com essa categoria.',
    ],
    [
        'versao' => '11.1', 'data' => '2026-11-15', 'titulo' => 'Foto de quem é responsável em todo o One',
        'melhoria' => [
            'A foto (ou as iniciais) aparece junto do nome de quem é responsável em todo o sistema: Agenda, Pedidos de compra (quem pediu, quem aprovou, quem recebeu), ordens de produção, Depósito (separação e despacho), tarefas do Marketing, Desenvolvimento, documentos, históricos e linhas do tempo.',
            'Painel de vendas e Metas: cada vendedor com a foto no ranking.',
            'Orçamento por link: o cliente vê a foto de quem preparou o orçamento.',
        ],
    ],
    [
        'versao' => '11.0', 'data' => '2026-11-15', 'titulo' => 'Perfil com a sua cara, fornecedores e menu organizado',
        'novo' => [
            'Seu perfil com a sua cara: status do dia ("☕ focada em ligações hoje", some sozinho), frase, cor e capa do perfil, interesses e "pergunte-me sobre".',
            'Personalize o perfil em Meu perfil › Personalizar: escolha a ordem dos blocos e esconda os que não quiser mostrar.',
            'Recados: deixe um recado no perfil de um colega a qualquer hora (não só no aniversário). A pessoa recebe o aviso no sino.',
            'Elogios entre colegas: "Ajudou muito", "Arrasou na venda", "Salvou o dia"… Cada um aparece no perfil e avisa quem foi elogiado.',
            'Conquistas no perfil: tempo de casa, metas batidas, elogios recebidos e perfil completo (sem valores de venda).',
            'Fornecedores: cada fornecedor com o que ele fornece, o preço comparado com os outros fornecedores, as NFs, o prazo de entrega e os documentos.',
            'Laudos e documentos das matérias-primas vencendo aparecem no painel da Produção, e o One avisa a Produção e a Qualidade.',
        ],
        'melhoria' => [
            'Meu mês: as vendas entram sozinhas pelos pedidos faturados no Omie. Não precisa mais registrar venda.',
            'Meta da equipe: mostra para quais setores vale (Televendas + Prospecção e Reativação) e só esses setores veem a participação no Meu mês.',
            'Gráfico da meta da equipe: mostra todos os dias do mês (inclusive sábado e feriado com lançamento), marca os dias sem expediente e a linha não "despenca" por um valor digitado errado.',
            'Menu mais organizado: Compras num lugar só (Pedidos de compra, Compras da empresa e Fornecedores), Estoque separado da Produção e Metas dentro de Resultados.',
            'Telas longas mais leves: Produtos, Catálogo, Fichas técnicas e Pedidos da Terceirização mostram uma parte por vez, com "Mostrar mais".',
            'Os gráficos da Equipe de vendas e da Produção aparecem sempre, também sem internet.',
            'Equipe: cartões com status, frase e elogios de cada pessoa.',
        ],
        'admin' => 'Perfis novos em Pessoas e acessos: Qualidade e Gestor de setor (passe as pessoas para eles; ninguém muda sozinho). Configurações › Metas: setores da meta da equipe e de onde vêm as vendas do Meu mês (omie ou manual). Configurações › Pessoas: tipos de elogio e horas do status do dia.',
    ],
    [
        'versao' => '10.5', 'data' => '2026-11-15', 'titulo' => 'Compras da empresa e página completa de cada item',
        'novo' => [
            'Financeiro › Compras da empresa: tudo que é comprado (matéria-prima, potes, rótulos, material de escritório, limpeza…) fica anotado num lugar só, com o total do mês por categoria.',
            'Lançar compra: fornecedor, número e arquivo da NF e os itens com quantidade e valor. Ao salvar, entra no estoque e a NF vai para os documentos de cada item.',
            'Notas do Omie entram sozinhas no estoque. Quem compra, a Produção (quando é matéria-prima, embalagem ou rótulo) e a Diretoria recebem o aviso no sino. Um item que o One ainda não conhece aparece em "Do Omie" para ligar uma vez só.',
            'Cada item tem uma página própria: estoque, quanto dura em média, de quantos em quantos dias é comprado, próxima compra prevista, preço médio e gasto dos últimos 12 meses.',
            'Vários fornecedores para o mesmo item, com o último preço de cada um, o menor preço em destaque e a escolha do fornecedor principal.',
            'Documentos do item (laudo, ficha técnica, FISPQ, certificado e NF), com validade e lote. Os que estão vencendo ficam marcados.',
            'Os orçamentos da Qualidade recebidos no Depósito viram compra lançada, com a NF anexada no recebimento.',
        ],
        'melhoria' => [
            'Página da matéria-prima com cartões, abas (Resumo, Fornecedores, Compras, Documentos, Estoque) e gráfico de compras por mês.',
            'Desenvolvimento de Produto com visual futurista: balões e janelas de vidro com brilho neon, fundo quadriculado e fios de luz.',
            'Foto e nome do vendedor responsável em todo lugar: carteira, funil, ficha do cliente, pedidos, aprovação, orçamentos, Depósito, Cobrança e Recompra.',
            'Página da empresa mais organizada: próximas atividades em linhas compactas (concluir com ✓), "O que mais compra" com a foto de cada produto e barra de quanto pesa nas compras.',
            'Linha do tempo da empresa mais enxuta, com filtros (Contatos, Anotações, Pedidos e orçamentos, Etapas, Cobrança). A lista de pedidos rola dentro do quadro.',
        ],
        'correcao' => [
            'Página da empresa: a coluna da direita (mensagem pronta, dados, contatos) voltou a rolar até o fim.',
        ],
        'admin' => 'Permissão nova "Lançar compras da empresa" (perfil Financeiro; a função Qualidade também lança). Categorias dos itens em Configurações › Compras. Aviso de compras para a Diretoria em Configurações › Diretoria.',
    ],
    [
        'versao' => '10.4', 'data' => '2026-11-14', 'titulo' => 'Resultados, Meu mês e Agenda de cara nova',
        'melhoria' => [
            'Meu mês: anel da meta, aviso de ritmo ("No ritmo" ou "Atrás do ritmo · precisa de R$ X por dia") e quanto falta por dia de trabalho.',
            'Meu mês: "Para fazer hoje" junta os retornos atrasados e de hoje com os clientes de recompra, com Abrir e WhatsApp num clique.',
            'Meu mês: números organizados em três blocos (Minhas vendas, Projeção, Na equipe) e o gráfico de vendas por dia mostra a linha do necessário por dia.',
            'Agenda em semana: cada dia numa coluna, cartões coloridos por tipo, atrasados em destaque no topo e o progresso de hoje.',
            'Agenda: botão Reagendar (hoje, amanhã ou outra data) e Concluir direto no cartão. A visão em Lista continua disponível.',
            'Acompanhamento: cada pessoa da equipe em um cartão com a barra da meta, pódio dos três primeiros e os números lado a lado (nada mais cortado na tela).',
            'Resumo da vendedora: anel da meta, ritmo, ligações por dia (atendidas e não atendidas), contatos por canal e pontos de atenção.',
            'Os gráficos aparecem sempre, também no celular, na impressão e sem internet.',
        ],
    ],
    [
        'versao' => '10.3', 'data' => '2026-11-14', 'titulo' => 'Ficha do cliente mais prática e discador',
        'novo' => [
            'Caixa rápida na linha do tempo do cliente: escreva uma anotação e cole o print de tela com Ctrl+V (ou anexe imagem/PDF). O print fica guardado na linha do tempo.',
            'Agendar atividade direto na ficha (ligar, WhatsApp, visita, reunião, enviar orçamento) com dia e hora: vai para a Agenda e aparece em "Próximas atividades", com o botão Concluir.',
            'Discador ao lado do sino (Televendas e Cobrança): digite qualquer número e ligue pelo Ligue Talk. Se o número for de um cliente, o One mostra a ficha e a ligação vai para a linha do tempo dele.',
            'Letreiro no topo: quando entra um pedido, passa "Novo pedido feito por (vendedor)" por 30 segundos para todo mundo.',
            'Foto do produto: na página do produto, "Trocar foto" atualiza a foto no catálogo, na Academia, nos pedidos e nos orçamentos.',
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
