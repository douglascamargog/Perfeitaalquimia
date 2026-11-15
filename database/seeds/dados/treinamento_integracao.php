<?php
/**
 * Treinamento (V13.0) — trilha "Integração", para todos. Formato de cada módulo:
 * [chave, tipo (setor|vizinho), título, resumo, minutos, texto, [[pergunta, [opções], índice da certa, explicação], ...]]
 * Texto: ## título · - lista · 1. passo · > destaque · ! atenção · **negrito** · [tela](/caminho)
 */
return [
    ['chave' => 'integracao', 'funcao' => null, 'titulo' => 'Integração', 'icone' => '👋', 'vizinhos' => '',
     'descricao' => 'Para todo mundo: a empresa, o One, quem faz o quê e como cuidamos dos dados.',
     'modulos' => [
        ['int-empresa', 'setor', 'Prazer, somos a Perfeita Alquimia', 'Quem somos, o que fabricamos e para quem vendemos.', 6, <<<'TXT'
## Bem-estar está no centro do nosso trabalho
A Perfeita Alquimia fabrica **produtos naturais e suplementos**, com fábrica própria e sede em **São Leopoldo (RS)**. O portfólio reúne cápsulas, chás, gotas, líquidos, pós, dose única e alimentos.

## As frentes do negócio
- **Marca própria:** produtos com a nossa marca, vendidos para lojas de todo o Brasil.
- **Venda para lojistas (B2B):** lojas de produtos naturais, suplementos e farmácias compram por CNPJ, pelo televendas, pelo vendedor externo ou pelo catálogo.
- **Terceirização:** fabricamos produtos com a marca de outras empresas.
- **Consumidor final:** loja virtual e marketplaces.

## As linhas de produtos
- Cápsulas (vitaminas, minerais, associações)
- Pós e bebidas, com sabores e tamanhos
- Gotas e líquidos
- Chás: a granel (ervas soltas) e em sachês
- Dose única em displays
- Alimentos e especiarias

> Em uma frase: "Somos uma empresa de produtos naturais e suplementos com fabricação própria em São Leopoldo, no Rio Grande do Sul."

! Suplemento não é remédio: nunca prometa tratamento, cura ou prevenção de doença. Dúvida técnica vai para a Responsável Técnica.
TXT, [
            ['Onde fica a fábrica da Perfeita Alquimia?', ['Porto Alegre', 'São Leopoldo (RS)', 'Curitiba (PR)', 'Não temos fábrica própria'], 1, 'Fabricação própria, com sede em São Leopoldo, no Rio Grande do Sul.'],
            ['O que é "terceirização" aqui na empresa?', ['Contratar outra fábrica para produzir para nós', 'Fabricar produtos com a marca de outras empresas', 'Vender pela loja virtual', 'Terceirizar a entrega'], 1, 'Na terceirização, o cliente desenvolve a linha dele e nós fabricamos com a marca dele.'],
            ['Um cliente pergunta se um suplemento cura uma doença. O certo é:', ['Dizer que sim, se o produto for bom', 'Explicar que suplemento não é remédio e encaminhar a dúvida técnica', 'Mudar de assunto', 'Indicar outro produto que cura'], 1, 'Nunca prometa tratamento ou cura. Passe a composição e encaminhe dúvidas técnicas à Responsável Técnica.'],
        ]],
        ['int-one', 'setor', 'Primeiros passos no One', 'Menu, busca, sino, o One ajudante e o seu perfil.', 7, <<<'TXT'
## O sistema se chama One
O One junta numa tela só o que antes ficava espalhado: clientes, pedidos, catálogo, produção, marketing e treinamento. O mascote ajudante também se chama One.

## O que você precisa saber logo no primeiro dia
1. **Seu login é só seu.** Não empreste a senha: tudo o que é feito fica registrado com o seu nome.
2. **Menu:** as telas ficam agrupadas por área. 🔒 = tela que não é do seu acesso; 👁 = você só consulta.
3. **Busca rápida:** aperte **/** em qualquer tela para achar cliente, produto ou código.
4. **Sino:** avisos de pedidos, tarefas, recados e metas.
5. **O One:** clique no mascote no canto da tela. Ele dá dicas, responde perguntas e mostra o passo a passo de cada tela em "O que dá pra fazer aqui?".

## O tutorial de cada tela
Na primeira vez em cada tela, o One mostra o passo a passo. Ele **volta a mostrar** até você clicar em **"Aprendi tudo desta tela"**. Quando você aprender todas as telas do seu acesso, ele comemora e para de abrir sozinho.

## Seu perfil
Em [Meu perfil](/meu-perfil) você coloca foto, troca a senha, escolhe as cores do sistema e diz se o One pode puxar conversa.
TXT, [
            ['Como abrir a busca rápida em qualquer tela?', ['Apertando a tecla /', 'Só pelo menu', 'Apertando F5', 'Não existe busca'], 0, 'A tecla / abre a busca rápida de clientes, produtos e códigos.'],
            ['O que significa o cadeado 🔒 no menu?', ['A tela está em manutenção', 'A tela não é do seu acesso', 'A tela é secreta da diretoria', 'Você precisa trocar a senha'], 1, 'Cadeado = sem acesso; olho = só consulta.'],
            ['Até quando o One mostra o tutorial de uma tela para quem é novo?', ['Só uma vez', 'Até a pessoa clicar em "Aprendi tudo desta tela"', 'Para sempre', 'Só se o gestor pedir'], 1, 'Ele volta a mostrar a cada visita até você confirmar que aprendeu.'],
        ]],
        ['int-setores', 'setor', 'Quem faz o quê na empresa', 'Os setores e o caminho de um pedido, do cliente até a entrega.', 8, <<<'TXT'
## Funções, não nomes
No One, as regras usam **funções** (setores), nunca nomes de pessoas: Televendas, Externo, Prospecção e Reativação, Terceirização, Cobrança, Financeiro, Marketing, Social Media, E-commerce, Produção, Responsável Técnica, Qualidade, Depósito e Diretoria. As pessoas mudam; as funções ficam.

## O caminho de um pedido
1. **Venda:** o cliente pede no catálogo, a vendedora monta no sistema ou o externo anota no app.
2. **Validação:** o One confere pedido mínimo da região, tabela de preço do cliente e forma de pagamento.
3. **Aprovação:** o **Financeiro** confere o crédito e aprova.
4. **Omie:** o pedido vai para o ERP, onde sai a nota fiscal.
5. **Depósito:** separa, confere lote e validade e despacha com rastreio.
6. **Linha do tempo:** tudo volta para a ficha do cliente.

## Quem abastece quem
- **Produção** fabrica e mantém o estoque acabado; a **Qualidade** compra matérias-primas e cuida dos rótulos; a **Responsável Técnica** cuida das fórmulas e documentos.
- **Marketing** cria artes, posts, campanhas e cadastra produtos; peça em [Pedir ao Marketing](/marketing/pedir).
- **Diretoria** acompanha os números e aprova o que for pedido.

> Na sua trilha, o bloco "Áreas com que você tem contato" explica melhor os setores que mais conversam com você.
TXT, [
            ['Quem aprova os pedidos antes de irem para o Omie?', ['O Depósito', 'O Financeiro', 'O Marketing', 'A própria vendedora'], 1, 'O Financeiro confere o crédito e aprova.'],
            ['Por que o One usa funções em vez de nomes de pessoas nas regras?', ['Porque é mais bonito', 'Porque as pessoas mudam e as funções ficam', 'Por causa da LGPD', 'Porque nomes são difíceis de escrever'], 1, 'Quando alguém muda de setor ou sai, as regras continuam funcionando.'],
            ['Precisa de uma arte para um cliente. Onde pede?', ['No WhatsApp pessoal da designer', 'Em Pedir ao Marketing', 'Na Agenda', 'No sino'], 1, 'Pedir ao Marketing guarda o pedido, o prazo e avisa quando fica pronto.'],
        ]],
        ['int-lgpd', 'setor', 'Segurança e LGPD', 'Senha, dados de clientes e o que nunca fazer.', 6, <<<'TXT'
## Sua senha
- Cada pessoa tem o **próprio login**. Nunca use o login de outra pessoa nem passe o seu.
- Troque a senha em [Meu perfil](/meu-perfil) se desconfiar que alguém viu.
- Saia do sistema (botão no canto de cima) quando usar um computador compartilhado.

## Dados de clientes (LGPD)
A Lei Geral de Proteção de Dados protege as informações das pessoas. No dia a dia:
- Use os dados dos clientes **só para o trabalho**.
- **Não** mande listas de clientes, planilhas ou prints da carteira para fora da empresa nem para grupos pessoais.
- **Não** anote dados de clientes em papéis soltos ou aplicativos pessoais.
- Exportar planilha só quando precisar e apague depois de usar.
- Cliente pediu para não receber mais contato? Registre na ficha e respeite.

## Tudo fica registrado
Alterações em clientes, pedidos e configurações ficam na **auditoria**: quem fez, o quê e quando.

! Recebeu um link ou arquivo estranho pedindo a sua senha? Não clique e avise o administrador.
TXT, [
            ['Uma colega esqueceu a senha e pede para usar o seu login. O que fazer?', ['Emprestar só hoje', 'Não emprestar; ela pede uma nova senha ao administrador', 'Passar a senha por WhatsApp', 'Entrar e deixar o computador aberto para ela'], 1, 'Cada login é individual: o que é feito fica no nome de quem está logado.'],
            ['Posso mandar a planilha da carteira para o meu e-mail pessoal?', ['Sim, para trabalhar em casa', 'Não: dados de clientes não saem da empresa', 'Sim, se apagar depois', 'Só se for pequena'], 1, 'LGPD: dados de clientes só para o trabalho, dentro dos canais da empresa.'],
            ['O cliente pediu para não receber mais mensagens. O certo é:', ['Continuar mandando promoções', 'Registrar na ficha e respeitar', 'Passar o cliente para outra vendedora', 'Apagar o cliente'], 1, 'Registre a preferência na ficha e respeite a decisão.'],
        ]],
        ['int-comunicacao', 'setor', 'Comunicação no dia a dia', 'Sino, recados, comunicados, Pessoas e o Pergunte ao One.', 5, <<<'TXT'
## Onde as coisas chegam
- **Sino:** avisos do sistema para você (pedido aprovado, tarefa nova, meta batida, pedido do Marketing pronto).
- **Comunicados:** avisos da empresa ou do seu setor; aparecem numa janela até você marcar como lido.
- **Novidades:** quando o sistema ganha uma versão nova, uma janela conta o que mudou.

## Conhecer a equipe
Em [Pessoas](/pessoas) você vê o perfil dos colegas, deixa recados e elogios e descobre os aniversariantes. Mantenha o seu perfil atualizado: foto, status e um pouco sobre você.

## Pergunte ao One
Abra o One e escreva a dúvida do jeito que falaria com um colega: "como faço um orçamento?", "quanto falta para a minha meta?". Ele responde com o caminho certo no sistema.

> Dica: esqueceu como funciona uma tela? Chame o One e clique em "O que dá pra fazer aqui?".
TXT, [
            ['Onde chegam os avisos do sistema só para você (pedido aprovado, tarefa nova)?', ['No sino', 'No e-mail', 'Na Biblioteca de produtos', 'Em Configurações'], 0, 'O sino junta os avisos pessoais.'],
            ['Como rever o passo a passo de uma tela?', ['Pedindo ao administrador', 'Chamando o One e clicando em "O que dá pra fazer aqui?"', 'Não tem como', 'Trocando a senha'], 1, 'O One mostra o tutorial da tela a qualquer momento.'],
        ]],
    ]],
];
