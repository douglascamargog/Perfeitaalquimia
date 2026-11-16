<?php
/**
 * Treinamento — trilhas do Comercial (Televendas, Externo, Prospecção, Terceirização). V13.2: aprofundado.
 * Formato: [chave, tipo (setor|vizinho), título, resumo, minutos, texto, [[pergunta, [opções], índice da certa, explicação], ...]]
 * No texto: ![legenda](assets/img/treinamento/x.jpg) = print (ferramentas/capturar_treinamento.js) ·
 *   {{config:chave}} = valor atual da configuração · {{pedido_minimo}} / {{condicoes_pagamento}} = tabelas atuais ·
 *   ?? situação || o que fazer = caso prático · ! = atenção (inclui "confira com a sua gestão": regra que a empresa precisa validar).
 */

// =====================================================================
// Módulos usados em mais de uma trilha
// =====================================================================
$rotina = <<<'TXT'
## Objetivo
Ao final deste módulo você sabe **por onde começar o dia**, o que olhar em cada tela e como terminar o dia sem deixar nenhum cliente sem próximo passo.

## A tela de entrada: o seu Início
Ao entrar, o One mostra o resumo do seu dia: retornos de hoje e atrasados, sua meta, aniversariantes e avisos. Cada cartão leva direto para o assunto.
![O Início da vendedora: retornos de hoje, meta e avisos do dia](assets/img/treinamento/inicio-vendedora.jpg)

## Meu mês: a meta e o ritmo
Em [Meu mês](/meu-mes) você vê a meta do mês, quanto já vendeu e **quanto precisa por dia útil** para chegar lá. "Para hoje" junta os contatos mais importantes do dia.
![Meu mês: o anel mostra a meta; "Para hoje" lista os contatos do dia](assets/img/treinamento/meu-mes.jpg)

## A rotina que funciona (passo a passo)
1. **Abra o Meu mês** (2 minutos): veja quanto falta e o ritmo do dia.
2. **Agenda, atrasados primeiro.** Retorno atrasado é cliente esperando. Conclua ou reagende cada um.
3. **Agenda de hoje.** Os retornos que você combinou para hoje.
4. **Recompra.** Clientes que já deveriam ter comprado de novo, em ordem de valor.
5. **Reativação.** Seus clientes sem comprar há mais de {{config:carteira.dias_inatividade}} dias.
6. **Carteira filtrada.** Use o tempo que sobrar para os clientes "Em negociação" e "Aguardando retorno" do funil.
7. **Antes de sair:** todo cliente com quem você falou tem o contato registrado e o próximo passo agendado.

> Regra de ouro: terminou a conversa → **registre** e **agende**. Cliente sem próximo passo é cliente esquecido.

## Erros comuns
- Começar ligando para clientes "aleatórios" e deixar os atrasados para depois.
- Combinar um retorno de boca e não agendar: ele não aparece na Agenda e se perde.
- Registrar só "liguei": escreva o que foi combinado (produtos, valores, prazo, quem decide).

?? São 9h e você tem 6 retornos atrasados, 4 para hoje e 13 clientes na Recompra. Por onde começa? || Pelos 6 atrasados (o cliente já esperava seu contato), depois os 4 de hoje. A Recompra vem em seguida, começando pelos primeiros da lista, que valem mais.
?? O cliente pediu para você ligar "semana que vem". O que fazer antes de desligar? || Combine o dia (e se possível o horário), agende na ficha em Agendar e registre o que ele precisa ver até lá. No dia, ele aparece na sua Agenda.
TXT;
$rotinaPerguntas = [
    ['Qual a ordem certa para começar o dia?', ['Recompra, depois Agenda de hoje', 'Agenda: atrasados primeiro, depois os de hoje, e então Recompra', 'Reativação primeiro', 'Ligar para clientes novos'], 1, 'Atrasados são clientes que já esperavam o seu contato.'],
    ['Onde ver quanto precisa vender por dia útil para bater a meta?', ['No Meu mês', 'Na Biblioteca de produtos', 'Na Agenda', 'Em Configurações'], 0, 'O Meu mês mostra a meta, o vendido e o ritmo necessário.'],
    ['O cliente pediu retorno na semana que vem. O correto é:', ['Lembrar de cabeça', 'Agendar na ficha do cliente (vai para a Agenda no dia)', 'Anotar num papel', 'Mandar mensagem só no dia'], 1, 'O agendamento coloca o cliente na sua Agenda no dia certo.'],
    ['Depois de quantos dias sem compra o cliente aparece na Reativação (configuração atual)?', ['30', '60', 'O número definido em Configurações (o módulo mostra o valor atual)', 'Nunca aparece'], 2, 'O número é configurável; o treinamento mostra o valor atual.'],
];

$carteira = <<<'TXT'
## Objetivo
Saber encontrar qualquer cliente, entender a situação dele em segundos e **registrar tudo** o que acontece, para você e para quem vier depois.

## A sua carteira
Todo cliente tem **um responsável**. Os seus estão em [Carteira](/carteira), com quatro abas:
- **Visão geral:** números da carteira e o mapa.
- **Lista:** a tabela com filtros. É a mais usada no dia a dia.
- **Funil:** cada cliente na etapa da negociação.
- **Mapa:** onde estão os clientes (ótimo para planejar visitas).
![Carteira › Lista: filtros no alto e os clientes em ordem](assets/img/treinamento/carteira-lista.jpg)

### Filtros que valem ouro
- **Situação de compra:** "sem compra há 60 dias" é uma lista pronta de ligações.
- **Etapa:** veja só quem está "Em negociação" ou "Aguardando retorno".
- **Cidade/UF:** para campanhas regionais ou rotas.
- **Segmento:** produtos naturais, suplementos, farmácia.

## O funil (etapas da negociação)
Validar cadastro → Não abordado → Contato realizado → Em negociação → Proposta enviada → Aguardando retorno → **Cliente ativo** (ou Sem interesse / Inativo).
![Carteira › Funil: arraste o cliente para a etapa em que ele está](assets/img/treinamento/carteira-funil.jpg)
Mantenha a etapa sempre certa: é por ela que a gestão enxerga as negociações em andamento.

## A ficha do cliente (o lugar mais importante do sistema)
Clique no nome do cliente para abrir a ficha.
![A ficha: dados, compras dos últimos 12 meses, próxima compra prevista e os atalhos](assets/img/treinamento/ficha-cliente.jpg)
O que olhar antes de ligar:
1. **Os quatro números do topo:** quanto comprou em 12 meses, ticket médio, última compra e **próxima compra prevista**.
2. **Selos:** "Em cobrança" quer dizer título em atraso (o boleto fica bloqueado).
3. **O que mais compra:** a base da sua oferta de reposição.
4. **Próximas atividades** e a **linha do tempo**: o que já foi conversado, por quem e quando.

## Registrar na linha do tempo
A caixa da linha do tempo tem três abas:
- **📝 Anotação:** qualquer informação (dá para anexar print ou PDF, como o pedido que o cliente mandou por WhatsApp).
![Aba Anotação: escreva e anexe prints](assets/img/treinamento/ficha-cliente-anotacao.jpg)
- **📞 Contato:** canal (ligação, WhatsApp, visita), resumo da conversa e o próximo contato.
![Aba Contato: canal, resumo e próximo contato](assets/img/treinamento/ficha-cliente-contato.jpg)
- **📅 Agendar:** o que, dia, hora e para quem. Vai para a Agenda e avisa no dia.
![Aba Agendar: entra na Agenda do dia escolhido](assets/img/treinamento/ficha-cliente-agendar.jpg)

### Como escrever um bom registro
Ruim: "Liguei." · Bom: "Falei com Ana (compradora). Quer repor magnésios e colágeno; decide sexta, depois de ver o estoque. Mandar orçamento quinta."

## A Agenda
Em [Agenda](/agenda): atrasados no topo, depois os de hoje e a semana. Ao fazer o contato, **conclua**. Não deu? **Reagende** com um clique.
![Agenda: atrasados em destaque e a semana](assets/img/treinamento/agenda.jpg)

## Atalhos da ficha
- **Ligar / WhatsApp / Rota:** abrem direto, e a ligação fica registrada.
- **Mensagem pronta:** escolha o modelo; o texto vem com o nome da loja e o seu link do catálogo.
- **Buscar contatos com IA:** quando ninguém atende, procura telefones e e-mails novos da empresa.
- **Gerar acesso ao catálogo:** o cliente passa a ver preços e pedir sozinho pelo catálogo.

! Dados do cliente são para o trabalho: não tire print da carteira para grupos pessoais (LGPD).

?? Você abre a ficha e vê o selo "Em cobrança". O cliente quer fazer um pedido grande no boleto. O que dizer? || Explique com cuidado que, com título em aberto, o pedido pode ser feito com Pix ou cartão, e que regularizando o título o boleto volta. Não prometa boleto, porque o sistema bloqueia. Se ele quiser negociar o atraso, registre e avise a Cobrança.
?? A colega saiu de férias e você assumiu um cliente dela que liga pedindo "aquele preço que ela combinou". || Abra a linha do tempo da ficha: o combinado deve estar registrado. Se estiver, siga. Se não estiver, não confirme de memória: diga que vai checar e retorne no mesmo dia (registre e agende).
TXT;
$carteiraPerguntas = [
    ['Qual aba da linha do tempo usar para registrar uma ligação com o resumo e o próximo contato?', ['Anotação', 'Contato', 'Agendar', 'Nenhuma: não precisa registrar'], 1, 'A aba Contato guarda canal, resumo e próximo contato.'],
    ['O que significa o selo "Em cobrança" na ficha?', ['Cliente novo', 'Cliente com título em atraso: boleto bloqueado', 'Cliente VIP', 'Cliente sem vendedora'], 1, 'Com título em atraso, só Pix ou cartão.'],
    ['Qual registro está bom?', ['"Liguei."', '"Falei com Ana (compradora): quer repor magnésios, decide sexta; mandar orçamento quinta."', '"Cliente chato."', '"Ok."'], 1, 'Quem, o quê, quando e o próximo passo.'],
    ['Ninguém atende no telefone do cadastro. Qual recurso da ficha ajuda?', ['Buscar contatos com IA', 'Apagar o cliente', 'Mudar o segmento', 'Exportar CSV'], 0, 'Ela procura telefones e e-mails novos da empresa.'],
    ['Você assumiu um cliente de uma colega. Onde ver o que já foi combinado?', ['Na linha do tempo da ficha', 'No WhatsApp da colega', 'Em Configurações', 'Na Biblioteca de produtos'], 0, 'Tudo registrado fica na ficha, com autor e data.'],
];

$pedido = <<<'TXT'
## Objetivo
Montar um pedido **certo na primeira vez**: preço do cliente, pedido mínimo, campanha, desconto e pagamento, sem voltar do Financeiro.

## Passo a passo: pedido no sistema
1. Na ficha do cliente, clique em **Novo pedido** (ou em [Pedidos](/pedidos) › Novo pedido e escolha o cliente).
2. A **tabela de preço do cliente** (Lojista, Distribuidor ou Terceirização) já vem aplicada. Não existe preço "de cabeça".
3. Digite as **quantidades** por unidade (não vendemos kits). Use a busca para achar o produto pelo nome ou código; "só escolhidos" mostra o que já entrou.
![Novo pedido: produtos com o preço do cliente e a quantidade de cada um](assets/img/treinamento/pedido-novo.jpg)
4. Acompanhe o **resumo** ao lado: total, faixa de campanha, pedido mínimo e formas de pagamento disponíveis.
![O resumo do pedido: campanha, desconto, pedido mínimo atingido e pagamento](assets/img/treinamento/pedido-carrinho.jpg)
5. Escolha a **condição de pagamento** e escreva na **observação** o que for combinado de entrega.
6. **Enviar pedido:** ele vai para o ERP como orçamento e para a **aprovação do Financeiro**.

## Pedido mínimo por região
O carrinho mostra quanto falta para o mínimo da região do cliente. Valores atuais:
{{pedido_minimo}}

## Formas e condições de pagamento
{{condicoes_pagamento}}
Cliente com título em atraso não pode usar boleto: o sistema bloqueia e mostra só Pix e cartão.

## Desconto da vendedora
Você pode dar até **{{config:pedidos.desconto_max_pct}}%** de desconto sem aprovação, por item ou no pedido todo. Acima disso, o pedido vai com o pedido de aprovação e o Financeiro decide. **Explique o motivo na observação.**

## Campanhas
Quando há campanha no ar, o resumo mostra a faixa atingida e **quanto falta para a próxima** (ex.: "faltam R$ 1.550 para frete grátis"). Use isso na conversa: "Com mais R$ 1.550 você ganha o frete; quer completar com o colágeno que acabou?". Veja as campanhas em [Campanhas](/campanhas).
![Campanhas no ar: período, para quem vale e as faixas](assets/img/treinamento/campanhas.jpg)

## Orçamento por link (WhatsApp)
Em vez de enviar, clique em **Gerar orçamento para o cliente**: ele recebe um link, abre no celular, muda quantidades e **aprova**. Aprovado, **vira pedido sozinho**. O orçamento vale {{config:orcamento.validade_dias}} dias.
![Orçamentos: o que foi enviado, aberto e aprovado](assets/img/treinamento/orcamentos.jpg)
Acompanhe tudo em [Orçamentos](/orcamentos): enviados, abertos pelo cliente, aprovados e vencidos.
**Modelos de pedido** guardam listas que você manda sempre (ex.: "reposição chás farmácia").

## Depois de enviar
Acompanhe em [Pedidos](/pedidos): Aguardando aprovação → Aprovado → Faturado (ou Recusado, com o motivo). Você recebe o aviso no sino em cada mudança.
![Pedidos: a situação de cada pedido](assets/img/treinamento/pedidos-lista.jpg)

## Erros comuns
- Prometer prazo de entrega antes do pedido ser aprovado e separado.
- Dar desconto acima do limite sem explicar o motivo, e o pedido volta.
- Esquecer o pedido mínimo e descobrir só no fim da ligação: olhe a barra enquanto monta.
- Usar condição de outro canal (loja virtual, campanha encerrada).

! Confira com a sua gestão: prazos de entrega por região e política de troca e devolução. Até lá, não prometa nada que não esteja escrito.

?? O pedido deu R$ 380 e o mínimo da região do cliente é maior. O cliente diz que só precisa disso agora. || Mostre o que falta e ofereça complementos que ele já compra (veja "O que mais compra" na ficha) ou a campanha do mês. Se mesmo assim não fechar, agende o retorno para quando ele precisar repor mais e registre.
?? O cliente pede 15% de desconto e o seu limite é menor. || Ofereça primeiro o que não depende de aprovação: a faixa da campanha, o desconto do Pix, completar para frete grátis. Se ainda assim precisar, envie com o desconto e o motivo na observação, avisando o cliente que depende de aprovação. Não confirme antes.
?? O cliente aprovou o orçamento pelo link, mas mudou as quantidades. || Normal: o pedido nasce com o que ele aprovou. Confira em Pedidos e, se algo não fizer sentido (ex.: quantidade muito alta), ligue para confirmar antes da aprovação do Financeiro.
TXT;
$pedidoPerguntas = [
    ['De onde vem o preço dos produtos no pedido?', ['A vendedora digita', 'Da tabela de preço do cliente (Lojista, Distribuidor ou Terceirização)', 'Do site', 'Do concorrente'], 1, 'A tabela do cliente vem aplicada automaticamente.'],
    ['O cliente tem título em atraso. Quais formas aparecem?', ['Todas', 'Só Pix e cartão', 'Só boleto', 'Nenhuma'], 1, 'O boleto fica bloqueado.'],
    ['Desconto acima do seu limite:', ['É proibido', 'Vai com pedido de aprovação; explique o motivo na observação', 'Passa sem ninguém ver', 'Só pelo catálogo'], 1, 'Acima do limite, o Financeiro decide.'],
    ['O cliente aprovou o orçamento pelo link. O que acontece?', ['Nada', 'Vira pedido sozinho', 'Vai para a Cobrança', 'Precisa digitar de novo'], 1, 'Orçamento aprovado vira pedido.'],
    ['O resumo diz "faltam R$ 1.550 para frete grátis". Melhor atitude:', ['Ignorar', 'Oferecer completar com produtos que o cliente já compra', 'Dar o frete grátis por conta', 'Cancelar o pedido'], 1, 'Use a campanha para aumentar o pedido com o que faz sentido para a loja.'],
    ['Quando você pode prometer o prazo de entrega?', ['Antes de montar o pedido', 'Só seguindo a política da empresa, depois de aprovado', 'Sempre "amanhã"', 'Nunca fale de prazo'], 1, 'Prazo depende de aprovação e separação; siga a política.'],
];

$atendimento = <<<'TXT'
## Objetivo
Conduzir a conversa como consultora: **entender a loja antes de oferecer**, responder objeções sem desconto automático e nunca prometer o que o produto não faz.

## Antes de ligar (1 minuto)
- Ficha: última compra, o que mais compra, títulos, último registro.
- Defina o **objetivo da ligação**: repor? apresentar campanha? recuperar? Uma ligação, um objetivo.

## Abertura
> "Oi, [nome]! Aqui é [seu nome], da Perfeita Alquimia. Tudo bem? Te liguei porque [motivo: está chegando a época da sua reposição / começou a campanha do mês]. Tem dois minutinhos?"

## Diagnóstico: perguntas que vendem
- "Como estão saindo os magnésios / colágenos / chás?"
- "O que os seus clientes mais pedem que você não tem?"
- "Tem algum item parado na prateleira?"
- "Qual investimento você pensa para esse pedido e para quando precisa?"
- "Quem mais participa da decisão?"
Escute e **anote na ficha** enquanto fala.

## Oferta
- Ofereça **poucos itens, com motivo**: "Você vende bem o 8+ Magnésio; o Citrato de Magnésio atende quem procura outra forma do mineral."
- Use **reposição** (o que ele já vende) + **um item novo** com argumento.
- Mostre a **campanha** e quanto falta para a próxima faixa.

## Objeções (e como responder)
- **"Está caro."** "Você compara por unidade ou o pedido total? Me diz qual produto e quantidade, que eu confiro. Às vezes o Pix ou a faixa da campanha já resolvem."
- **"Estou com estoque."** "Ótimo! Quais estão saindo mais? Posso deixar agendado para [data] e já separo a campanha para você."
- **"Vou pensar."** "Claro. O que falta avaliar: os produtos, o valor ou o momento? Te procuro em [data]?" (e **agende**).
- **"O fornecedor X faz mais barato."** "Entendo. O nosso diferencial é a fabricação própria, a composição de cada fórmula e a reposição rápida. Quer comparar um item lado a lado?"
- **"Esse produto trata [doença]?"** "Posso te passar a composição e o rótulo. Suplemento não é remédio; para saber se é indicado para alguém, o ideal é um profissional de saúde."

## Fechamento
Resuma o pedido, confirme pagamento e entrega e diga o próximo passo: "Vou mandar o orçamento agora pelo WhatsApp; é só aprovar no link."

## Mensagens prontas
Em [Mensagens prontas](/mensagens) há modelos de prospecção, reativação, recompra, cobrança e pós-venda. Na ficha, escolha o modelo: o nome da loja e o seu link entram sozinhos.
![Mensagens prontas: modelos com {nome} e {link_vendedor}](assets/img/treinamento/mensagens.jpg)

! Nunca diga que um suplemento "cura", "trata" ou "previne" doença. Dúvida técnica: registre e encaminhe para a Responsável Técnica.

?? A loja diz que "magnésio é tudo igual" e quer o mais barato. || Explique sem desmerecer: o mineral é o mesmo, o que muda é o composto e a quantidade de magnésio por porção (está na ficha). Mostre os dois na Biblioteca e deixe a loja escolher pelo perfil dos clientes dela.
?? Uma cliente pergunta se pode indicar um chá para a filha de 5 anos. || Não indique. Diga que o rótulo traz as advertências e que, para crianças, quem orienta é o pediatra. Ofereça mandar a ficha do produto.
?? O comprador diz "manda a tabela inteira que eu vejo". || Mande, mas não pare aí: pergunte as 2 ou 3 linhas que mais vendem e marque um retorno curto para montar o pedido junto. Lista sem conversa raramente vira pedido.
TXT;
$atendimentoPerguntas = [
    ['O que fazer antes de oferecer produtos?', ['Mandar a tabela completa', 'Fazer perguntas de diagnóstico sobre a loja', 'Dar desconto', 'Falar da concorrência'], 1, 'Perguntas primeiro: a oferta fica certa.'],
    ['"Vou pensar." A melhor resposta é:', ['"Tá bom, tchau."', 'Perguntar o que falta avaliar e agendar o retorno', 'Dar 20% de desconto', 'Insistir até ele comprar'], 1, 'Descubra a dúvida e agende.'],
    ['O cliente pergunta se o produto trata uma doença. Você:', ['Diz que sim', 'Explica que suplemento não é remédio e passa a composição', 'Inventa um estudo', 'Muda de assunto'], 1, 'Nunca prometa tratamento ou cura.'],
    ['"Magnésio é tudo igual." O argumento correto:', ['"É sim, leva o mais barato."', 'O mineral é o mesmo; mudam o composto e a quantidade por porção', '"O nosso é remédio."', '"Não sei."'], 1, 'Composto e quantidade por porção estão na ficha.'],
    ['Onde estão os modelos de mensagem com o nome da loja e o seu link?', ['Mensagens prontas (e na ficha do cliente)', 'Configurações', 'Auditoria', 'Biblioteca'], 0, 'Na ficha, escolha o modelo e o texto vem pronto.'],
];

$recompra = <<<'TXT'
## Objetivo
Vender mais para quem **já compra**: é o cliente mais barato de conquistar e o que mais rende.

## Como o One calcula a recompra
Cada cliente tem o **próprio ritmo**: o One calcula o intervalo médio entre as compras (ex.: "compra a cada ~26 dias") e mostra a **próxima compra prevista** na ficha. Passou do prazo? O cliente entra em [Recompra](/recompra).
![Recompra: os mais valiosos e mais atrasados primeiro](assets/img/treinamento/recompra.jpg)

## Passo a passo na Recompra
1. Comece pelo topo (o One ordena por quanto o cliente compra × quanto está atrasado).
2. Abra a ficha e veja **o que mais compra**: a reposição se monta sozinha.
3. Ligue ou use a mensagem pronta de recompra:
> "{{config:recompra.mensagem}}"
4. Fechou? Pedido ou orçamento por link. Não fechou? Agende (o botão está no próprio cartão).

## Reativação
Clientes sem comprar há mais de **{{config:carteira.dias_inatividade}} dias** aparecem em [Reativação](/reativacao). Eles continuam na sua carteira.
![Reativação: os seus clientes parados há mais tempo](assets/img/treinamento/reativacao.jpg)
- Pergunte o que mudou: trocou de fornecedor? Fechou uma linha? Mudou o comprador?
- Ofereça a campanha do mês e um produto novo com argumento.
- **Registre o motivo** de não ter comprado: ajuda a empresa a entender as perdas.
- Se a loja fechou ou não quer mais contato, mova no funil para "Sem interesse" ou "Inativo", com o motivo.

> Quando um cliente da Reativação volta a comprar, o One comemora com você. 🎉

?? O cliente da Recompra diz que agora compra de um concorrente porque o prazo de entrega é menor. || Registre o motivo (é informação valiosa). Pergunte quais itens ele ainda não acha lá e ofereça esses. Agende um retorno em 30 dias. Se o prazo for um problema recorrente, avise a gestão.
?? Na Reativação aparece uma loja que você sabe que fechou. || Mova no funil para "Inativo" e registre "loja fechada". Assim ela sai das suas filas e não pesa nos números.
TXT;
$recompraPerguntas = [
    ['Como o One sabe que um cliente está atrasado na recompra?', ['Pelo intervalo médio entre as compras dele', 'Pela cidade', 'Pelo segmento', 'Sorteio'], 0, 'Cada cliente tem o próprio ritmo.'],
    ['Por que começar a Recompra pelo topo da lista?', ['Ordem alfabética', 'Os primeiros valem mais: compram mais e estão mais atrasados', 'São os mais novos', 'Tanto faz'], 1, 'A lista é ordenada por valor × atraso.'],
    ['Na Reativação, a loja diz que fechou. O que fazer?', ['Deixar como está', 'Mover para "Inativo" e registrar o motivo', 'Apagar a ficha', 'Mandar para a Cobrança'], 1, 'O motivo ajuda a empresa e limpa a sua fila.'],
    ['O cliente reativado fica com quem?', ['Com você (sua carteira)', 'Com a Diretoria', 'Com o Marketing', 'Sem dono'], 0, 'A reativação fica com a vendedora da carteira.'],
];

$vizComercial = <<<'TXT'
## O que o Comercial faz
O Comercial atende os **lojistas** (B2B): Televendas por telefone e WhatsApp, Externo visitando as lojas, Prospecção buscando lojas novas e Terceirização atendendo quem fabrica com a marca própria. Cada cliente tem uma **vendedora responsável** na Carteira, e tudo o que é combinado fica na **linha do tempo** da ficha.

## Como o trabalho do Comercial chega até você
- **Financeiro:** pedidos para aprovar, com a observação da vendedora (desconto, prazo, combinado).
- **Depósito:** pedidos aprovados e faturados para separar; a observação traz combinados de entrega.
- **Marketing:** pedidos de arte e material em Pedir ao Marketing; leads dos anúncios viram contatos da Prospecção.
- **Produção:** produtos em falta que a vendedora precisa prometer ao cliente.

## Como ajudar o Comercial
- Aprove ou recuse rápido e **com motivo claro**.
- Avise quando um produto vai faltar ou atrasar: a vendedora avisa o cliente antes de ele reclamar.
- Precisa falar de um cliente? Abra a ficha e leia a linha do tempo antes de ligar para ele.
TXT;

return [
    // =====================================================================
    ['chave' => 'televendas', 'funcao' => 'televendas', 'titulo' => 'Televendas', 'icone' => '📞',
     'vizinhos' => 'financeiro,deposito,marketing,cobranca,producao',
     'descricao' => 'Atendimento e vendas por telefone e WhatsApp para lojistas de todo o Brasil.',
     'modulos' => [
        ['tv-papel', 'setor', 'A rotina do televendas', 'Por onde começar o dia, o que olhar e como terminar sem pendência.', 12, $rotina, $rotinaPerguntas],
        ['tv-carteira', 'setor', 'Carteira, ficha do cliente e agenda', 'Encontrar, entender e registrar: o cliente inteiro numa tela.', 18, $carteira, $carteiraPerguntas],
        ['tv-pedido', 'setor', 'Pedido, orçamento, campanhas e descontos', 'Do carrinho ao pedido aprovado, certo na primeira vez.', 18, $pedido, $pedidoPerguntas],
        ['tv-atendimento', 'setor', 'Abordagem, diagnóstico e objeções', 'Como conduzir a conversa e responder sem dar desconto à toa.', 15, $atendimento, $atendimentoPerguntas],
        ['tv-recompra', 'setor', 'Recompra e reativação', 'Os clientes que mais rendem: quem já compra.', 10, $recompra, $recompraPerguntas],
        ['tv-ligacoes', 'setor', 'Ligações, WhatsApp e o discador', 'Ligar pelo sistema, registrar e acompanhar os seus números.', 8, <<<'TXT'
## Objetivo
Usar os canais de contato pelo próprio One, para que tudo fique registrado sem trabalho extra.

## Ligar pela ficha
O botão **Ligar** da ficha liga para o cliente. Com o Ligue Talk conectado, a ligação e a gravação aparecem sozinhas no Acompanhamento e na ficha. Sem a integração, registre com um clique no fim da ligação.

## WhatsApp
**WhatsApp** na ficha abre a conversa com o número do cliente. Use as **mensagens prontas**: escolha o modelo, revise o texto e envie. Registre o resumo na aba Contato se a conversa gerou algo importante.

## O discador
Para as funções com o discador ligado (Televendas e Cobrança, no padrão; o admin escolhe em Configurações), o ícone de telefone no topo abre o discador: uma fila de ligações para fazer em sequência, com a ficha de cada cliente.

## O que a gestão acompanha
Em Acompanhamento, a gestora vê ligações, gravações, mensagens, agenda e meta de cada pessoa da equipe. Não é para vigiar: é para ajudar quem precisa e repetir o que dá certo.

## Boas práticas
- Ligue em horário comercial da loja; evite o horário de abertura e o de almoço.
- No WhatsApp, uma mensagem curta e uma pergunta por vez.
- Áudio só se o cliente usar áudio com você.

?? O cliente só responde por WhatsApp e não atende ligações. || Registre essa preferência na ficha (aba Anotação) e use o WhatsApp com mensagens curtas e o orçamento por link. Ninguém da equipe vai insistir em ligar.
TXT, [
            ['Com o Ligue Talk conectado, onde aparecem as ligações?', ['Somente no celular', 'Sozinhas na ficha do cliente e no Acompanhamento', 'Em lugar nenhum', 'No Marketing'], 1, 'A integração registra ligações e gravações.'],
            ['O cliente prefere WhatsApp. O que fazer?', ['Ligar mesmo assim todo dia', 'Registrar a preferência na ficha e usar WhatsApp', 'Parar de atender', 'Mandar para outra vendedora'], 1, 'A preferência registrada vale para toda a equipe.'],
        ]],
        ['tv-vizinho', 'vizinho', 'Como trabalha o Comercial', 'Para quem recebe o trabalho das vendedoras.', 5, $vizComercial, [
            ['Onde fica o histórico de contatos com um cliente?', ['Na linha do tempo da ficha', 'No e-mail da vendedora', 'Na Auditoria apenas', 'Em lugar nenhum'], 0, 'Tudo é registrado na ficha.'],
            ['Como ajudar o Comercial quando um produto vai faltar?', ['Avisar com antecedência', 'Esperar o cliente reclamar', 'Não falar nada', 'Cancelar os pedidos'], 0, 'A vendedora avisa o cliente antes do problema.'],
        ]],
    ]],
    // =====================================================================
    ['chave' => 'externo', 'funcao' => 'externo', 'titulo' => 'Vendedor externo', 'icone' => '🚗',
     'vizinhos' => 'financeiro,deposito,marketing,televendas',
     'descricao' => 'Visitas às lojas, pedidos pelo app no celular (mesmo sem internet) e a sua carteira.',
     'modulos' => [
        ['ex-app', 'setor', 'O app do externo, mesmo sem internet', 'Instalar, anotar pedidos offline e enviar sem duplicar.', 14, <<<'TXT'
## Objetivo
Usar o celular como o seu escritório: carteira, produtos, preços e pedidos, **mesmo sem internet**.

## Instalar (uma vez)
1. No celular, abra o One no navegador (Chrome no Android, Safari no iPhone) e entre com o seu login.
2. Abra **App do externo** no menu.
3. Toque em **"Adicionar à tela inicial"** (no menu do navegador). O ícone do One aparece como um aplicativo.

## Antes de sair para a rota
Abra o app **com internet** (Wi-Fi da empresa ou de casa) e toque em **Sincronizar**. O app guarda no celular a sua carteira, os produtos e os seus preços. Se os dados ficarem mais de {{config:app.dias_cache}} dias sem atualizar, o app avisa.
![O app no celular: a carteira, com Novo pedido, Registrar visita, Ligar, WhatsApp e Mapa em cada cliente](assets/img/treinamento/app-externo.jpg)

## Fazendo um pedido na loja (passo a passo)
1. Ache o cliente (busca por nome, cidade ou CNPJ).
2. Toque em **Novo pedido**, coloque as quantidades e confira o total e o pedido mínimo.
3. Escolha o pagamento e salve.
4. Sem internet, o pedido fica **"aguardando envio"**. Quando a internet voltar (ou ao abrir o app), ele vai sozinho.
5. Acompanhe em **Pedidos**: aguardando envio → enviado → aprovado.

> Cada pedido recebe um código feito no celular. Se o envio acontecer duas vezes (sinal ruim), o sistema reconhece e **não duplica**.

## Registrar a visita
Mesmo sem pedido, toque em **Registrar visita** e escreva o que viu e combinou. Vai para a linha do tempo da ficha.

## Erros comuns
- Sair para a rota sem sincronizar: os preços podem estar desatualizados.
- Desinstalar o app com pedidos "aguardando envio" (eles estão só no celular!).
- Anotar pedido no papel "para passar depois".

! Pedidos "aguardando envio" ficam só no seu celular até a internet voltar. Não troque de aparelho nem limpe os dados do navegador antes de enviá-los.

?? Você está numa cidade sem sinal e fez 3 pedidos. Chegando ao hotel, o Wi-Fi funciona. O que acontece? || Abra o app: os 3 pedidos são enviados sozinhos e passam para "enviado". Confira em Pedidos. Não precisa digitar de novo.
?? O cliente diz que o preço do app está diferente do que a televendas passou semana passada. || Sincronize o app (pode haver tabela nova ou campanha encerrada) e confira na ficha a tabela do cliente. Na dúvida, registre e confirme com a gestão antes de fechar.
TXT, [
            ['Você fez um pedido sem internet. O que acontece?', ['Perde o pedido', 'Fica "aguardando envio" e vai sozinho quando a internet voltar', 'Precisa digitar de novo', 'Vai por SMS'], 1, 'O app guarda e envia quando houver conexão.'],
            ['O envio pode duplicar o pedido?', ['Sim, sempre', 'Não: cada pedido tem um código único', 'Só às sextas', 'Depende do cliente'], 1, 'O servidor ignora o mesmo código repetido.'],
            ['O que fazer antes de sair para a rota?', ['Nada', 'Sincronizar o app com internet', 'Desinstalar o app', 'Mandar a carteira por e-mail'], 1, 'Assim carteira e preços ficam atualizados no celular.'],
            ['Por que não desinstalar o app com pedidos "aguardando envio"?', ['Porque é proibido', 'Porque esses pedidos ainda estão só no celular', 'Porque apaga a carteira da empresa', 'Não tem problema'], 1, 'Envie antes de trocar de aparelho.'],
        ]],
        ['ex-visita', 'setor', 'A visita que vende', 'Preparar a rota, conduzir a visita e registrar.', 12, <<<'TXT'
## Objetivo
Transformar cada visita em pedido ou, no mínimo, em informação e próximo passo.

## Planejar a semana
- Use o **Mapa** da [Carteira](/carteira) para agrupar clientes por cidade.
![Carteira › Visão geral: o mapa mostra onde estão os clientes](assets/img/treinamento/carteira-visao.jpg)
- Priorize: recompra atrasada, clientes grandes e quem está "Em negociação".
- Avise o cliente antes ("passo aí quinta de manhã?"): visita marcada vende mais.

## Na loja (roteiro de 15 minutos)
1. **Gôndola:** o que está faltando? O que está parado? Como estão expostos os nossos produtos?
2. **Conversa:** o que sai mais? O que os clientes pedem e a loja não tem?
3. **Oferta:** reposição do que gira + 1 novidade com argumento + campanha do mês.
4. **Material:** precisa de display, cartaz ou amostra? Peça em Pedir ao Marketing.
5. **Fechamento:** pedido no app; confirme pagamento e entrega.

## Depois da visita
Registre (o que viu, o que combinou) e **agende o próximo contato**. Combine se a reposição será pela televendas ou na próxima visita, e escreva isso na ficha para ninguém ligar em duplicidade.

?? A loja tem nossos produtos escondidos na última prateleira. || Ofereça ajuda para reorganizar (sem mexer sem permissão), sugira um display e peça o material ao Marketing. Registre na ficha com uma foto.
?? O comprador não está; quem atende é um funcionário. || Deixe o recado e a campanha, pergunte o melhor horário do comprador, registre e agende uma ligação. Não feche pedido sem quem decide.
TXT, [
            ['Onde planejar a rota de visitas?', ['No Mapa da Carteira', 'Na Biblioteca de produtos', 'Em Comunicados', 'No sino'], 0, 'O mapa mostra os clientes por cidade.'],
            ['Depois da visita, sempre:', ['Nada', 'Registrar a visita e agendar o próximo contato', 'Apagar o cliente', 'Mandar para a Cobrança'], 1, 'Histórico e próximo passo.'],
            ['A loja precisa de um display. Onde pedir?', ['Pedir ao Marketing', 'No WhatsApp pessoal', 'Na Agenda', 'Ao Depósito'], 0, 'O pedido vira tarefa do Marketing.'],
        ]],
        ['ex-carteira', 'setor', 'Carteira, ficha do cliente e agenda', 'Encontrar, entender e registrar: o cliente inteiro numa tela.', 18, $carteira, $carteiraPerguntas],
        ['ex-pedido', 'setor', 'Pedido, orçamento, campanhas e descontos', 'Do carrinho ao pedido aprovado, certo na primeira vez.', 18, $pedido, $pedidoPerguntas],
        ['ex-atendimento', 'setor', 'Abordagem, diagnóstico e objeções', 'Como conduzir a conversa e responder sem dar desconto à toa.', 15, $atendimento, $atendimentoPerguntas],
        ['ex-vizinho', 'vizinho', 'Como trabalha o Vendedor externo', 'Para quem recebe o trabalho do externo.', 4, <<<'TXT'
## O que o externo faz
Visita as lojas, olha a gôndola, apresenta os produtos e anota pedidos no **app do celular**, que funciona sem internet. Os pedidos chegam com a origem "app".

## Como ajudar
- **Financeiro:** pedidos do app podem chegar em lote quando a internet volta; confira normalmente.
- **Depósito:** pedidos de rota costumam ter combinado de entrega; leia a observação.
- **Televendas:** algumas lojas alternam visita e telefone. Combine na ficha quem faz o próximo contato.
- **Marketing:** displays e materiais para as lojas visitadas.
TXT, [
            ['Por que os pedidos do externo podem chegar em lote?', ['O app guarda os pedidos sem internet e envia depois', 'O externo esquece', 'O sistema trava', 'São pedidos falsos'], 0, 'O app funciona offline.'],
        ]],
    ]],
    // =====================================================================
    ['chave' => 'prospeccao_reativacao', 'funcao' => 'prospeccao_reativacao', 'titulo' => 'Prospecção', 'icone' => '🔎',
     'vizinhos' => 'televendas,externo,marketing',
     'descricao' => 'Encontrar lojas novas, fazer o primeiro contato e passar o cliente pronto para a carteira.',
     'modulos' => [
        ['pr-tela', 'setor', 'A tela de Prospecção', 'Leads para abordar, base de CNPJs e leads do site.', 14, <<<'TXT'
## Objetivo
Encontrar as lojas certas, abordar no tempo certo e entregar para a carteira um **cliente com cadastro validado e histórico completo**.

## De onde vêm os leads
1. **Base de CNPJs (Encontrar empresas):** escolha UF e cidade; aparecem empresas ativas com os CNAEs dos nossos clientes (produtos naturais, farmácias, suplementos). As que **já estão na carteira** vêm marcadas.
![Encontrar empresas: as que já são clientes aparecem marcadas](assets/img/treinamento/prospeccao-empresas.jpg)
2. **Site e anúncios:** o formulário cria o lead sozinho, com a campanha de origem (UTM). Entra na etapa "{{config:leads.etapa_inicial}}" e, conforme a configuração, já com uma tarefa "Retornar lead" para hoje.
3. **Indicações, feiras e eventos:** cadastre em Carteira › Novo estabelecimento.

## A tela
![Prospecção: aguardando primeiro contato, abordados, convertidos e de onde vêm os leads](assets/img/treinamento/prospeccao.jpg)
- **Aguardando:** ninguém falou com eles ainda. Lead do site esfria rápido: **retorne no mesmo dia**.
- **Abordados:** já houve contato; acompanhe no funil.
- **Convertidos:** fizeram o primeiro pedido.

## Passo a passo do lead até a carteira
1. **Validar o cadastro:** CNPJ ativo, nome fantasia, cidade, telefone e quem compra.
2. **Primeiro contato** (ligação ou mensagem pronta de prospecção). A etapa vai para "{{config:carteira.etapa_apos_contato}}".
3. **Diagnóstico:** perfil da loja, linhas que vende, volume, quem decide.
4. **Proposta:** orçamento por link com poucos itens certeiros.
5. **Primeiro pedido** → o cliente passa para uma **vendedora** da carteira, com tudo registrado.

## Erros comuns
- Abordar sem olhar se a loja já é cliente de outra vendedora (a busca marca!).
- Deixar lead do site para "amanhã".
- Passar o cliente sem registrar o diagnóstico: a vendedora começa do zero.

?? Um lead do Instagram preencheu o formulário às 10h. Você só viu às 16h. || Ligue ainda hoje. Se não atender, mande a mensagem pronta e agende para amanhã cedo. Registre a origem que veio no lead (a campanha), porque ela mostra ao Marketing qual anúncio funciona.
?? Na busca por CNPJ, a farmácia que você quer abordar já aparece marcada como cliente. || Não aborde: ela tem uma vendedora responsável. Se achar que a loja está abandonada, avise a gestão.
TXT, [
            ['Como saber se uma empresa da base de CNPJs já é cliente?', ['Ela aparece marcada', 'Não tem como', 'Ligando para ela', 'Pelo logotipo'], 0, 'A busca marca quem já está na carteira.'],
            ['Lead do site deve ser retornado:', ['No mesmo dia', 'Em uma semana', 'Quando der', 'Só se ligar de novo'], 0, 'Lead esfria rápido.'],
            ['Lead que fez o primeiro pedido:', ['Fica na Prospecção', 'Passa para uma vendedora da carteira', 'Vai para a Cobrança', 'É apagado'], 1, 'Convertido, ele vai para a carteira.'],
            ['O que não pode faltar ao passar o cliente para a carteira?', ['O diagnóstico registrado na ficha', 'Um presente', 'O seu telefone pessoal', 'Nada'], 0, 'A vendedora continua de onde você parou.'],
        ]],
        ['pr-abordagem', 'setor', 'Primeiro contato e diagnóstico', 'Abrir a conversa com uma loja que não nos conhece.', 15, $atendimento, $atendimentoPerguntas],
        ['pr-mensagens', 'setor', 'Mensagens prontas e o link da vendedora', 'Modelos com {nome} e {link_vendedor} e o catálogo do cliente.', 8, <<<'TXT'
## Mensagens prontas
Em [Mensagens prontas](/mensagens) há modelos por categoria. Na ficha do cliente, escolha o modelo: o One troca **{nome}** pelo nome da loja e **{link_vendedor}** pelo **seu link do catálogo**. Revise e envie pelo WhatsApp.
![Mensagens prontas por categoria](assets/img/treinamento/mensagens.jpg)

## O link da vendedora
Pedidos feitos pelo cliente no catálogo usando o seu link entram **para você**. O catálogo mostra os produtos sem preço; o preço aparece quando o cliente entra com o acesso dele (na ficha: **Gerar acesso ao catálogo**).

## Boas práticas no WhatsApp
- Primeira mensagem: quem você é, de onde fala e **uma** pergunta.
- Não mande a tabela inteira de cara.
- Responda rápido; se não puder, avise quando vai responder.
- Cliente pediu para parar? Registre e respeite.

?? A loja pede o catálogo com preços pelo WhatsApp. || Gere o acesso ao catálogo na ficha e mande o seu link: ela vê os preços da tabela dela e pode pedir sozinha. Os pedidos entram para você.
TXT, [
            ['O que é {link_vendedor}?', ['O link do catálogo da vendedora: pedidos por ele entram para ela', 'O WhatsApp da empresa', 'Um erro', 'O site da Anvisa'], 0, 'Pedidos pelo seu link são seus.'],
            ['Como a loja passa a ver preços no catálogo?', ['Ela vê sem login', 'Com o acesso gerado na ficha (CNPJ e senha ou código)', 'Pelo Instagram', 'Nunca vê'], 1, 'Preço só aparece com login do cliente.'],
        ]],
        ['pr-vizinho', 'vizinho', 'Como trabalha a Prospecção', 'Para quem recebe os clientes novos.', 4, <<<'TXT'
## O que a Prospecção faz
Encontra lojas novas (base de CNPJs, site, anúncios), faz o primeiro contato e o diagnóstico. Quando o lead faz o primeiro pedido, ele **passa para uma vendedora** da carteira.

## O que chega para você
Cliente novo com cadastro validado e o diagnóstico na ficha. **Leia a linha do tempo antes de ligar**: o cliente não precisa repetir o que já contou.

## Marketing e Prospecção
Os leads dos anúncios chegam com a campanha de origem; o Marketing acompanha quantos viraram clientes em Leads do site.
TXT, [
            ['Recebeu um cliente da Prospecção. Antes de ligar:', ['Ler a linha do tempo da ficha', 'Pedir o CNPJ de novo', 'Mandar a tabela inteira', 'Nada'], 0, 'O histórico do primeiro contato está na ficha.'],
        ]],
    ]],
    // =====================================================================
    ['chave' => 'terceirizacao', 'funcao' => 'terceirizacao', 'titulo' => 'Terceirização e Marca Própria', 'icone' => '🏷️',
     'vizinhos' => 'producao,qualidade,tecnica,financeiro,deposito',
     'descricao' => 'Clientes que fabricam com a marca deles: produtos por cliente, pedidos e rótulos.',
     'modulos' => [
        ['te-area', 'setor', 'A área de Terceirização', 'Clientes, pedidos, produtos por cliente e rótulos.', 14, <<<'TXT'
## Objetivo
Atender quem fabrica **com a marca própria** sem deixar a produção parar por falta de rótulo, informação ou aprovação.

## As abas
Em [Terceirização](/terceirizacao):
![Terceirização: clientes, pedidos, produtos por cliente e rótulos](assets/img/treinamento/terceirizacao.jpg)
- **Clientes:** quem produz com a gente, com os números de cada um.
- **Pedidos:** os pedidos de produção (podem ser **mistos**: produtos com a marca do cliente e produtos da nossa marca no mesmo pedido).
- **Produtos por cliente:** o produto com a marca do cliente, **ligado à nossa fórmula** (ficha técnica). O preço é o combinado com aquele cliente.
- **Rótulos:** o estoque dos rótulos que o cliente envia.

## Rótulos: o ponto mais sensível
O rótulo é do cliente: **sem rótulo, não há produção**. O One compara o saldo de rótulos com as ordens planejadas e **alerta** quando não vai dar.
![Rótulos: saldo de cada produto e o alerta de falta](assets/img/treinamento/terceirizacao-rotulos.jpg)
- Avise o cliente **assim que** o alerta aparecer (prazo de gráfica costuma ser longo).
- Quando os rótulos chegam, a Qualidade dá entrada e o saldo sobe.
- Na ordem de produção, os rótulos baixam junto com as matérias-primas.

## Do pedido à entrega (passo a passo)
1. Pedido do cliente (no sistema, com os produtos dele).
2. Aprovação do Financeiro (condições do contrato).
3. **Ordem de produção:** baixa matérias-primas e rótulos.
4. Qualidade confere; o produto acabado entra no estoque.
5. Depósito separa na aba Terceirização e despacha.

! Confira com a sua gestão: quantidade mínima por lote, prazo padrão de produção e o que está no contrato de cada cliente.

?? O cliente quer 3.000 unidades para daqui a 10 dias e o saldo de rótulos é de 1.200. || Avise na hora: com 1.200 rótulos dá para produzir 1.200. Combine a produção parcial ou a data em que os rótulos chegam. Registre na ficha e avise a Produção.
?? Um pedido misto chegou: 500 unidades com a marca do cliente e 12 caixas de chá da nossa marca. || É normal: o sistema separa as linhas. As da marca do cliente vão para a produção (com rótulo dele); as nossas saem do estoque acabado. Confira os dois prazos com o cliente.
TXT, [
            ['Sem o rótulo do cliente:', ['Produz com o nosso', 'Não há produção', 'Produz sem rótulo', 'Tanto faz'], 1, 'O rótulo é do cliente: sem ele, a produção para.'],
            ['O produto "por cliente" está ligado a:', ['A nossa fórmula (ficha técnica)', 'O catálogo público', 'O Instagram', 'Nada'], 0, 'A ficha técnica alimenta a ordem de produção.'],
            ['Apareceu o alerta de rótulos. Quando avisar o cliente?', ['Assim que aparecer', 'No dia da produção', 'Quando ele perguntar', 'Nunca'], 0, 'Gráfica demora; avise cedo.'],
            ['Pedido misto é:', ['Erro do sistema', 'Produtos com a marca do cliente e da nossa marca no mesmo pedido', 'Pedido cancelado', 'Pedido de amostra'], 1, 'O sistema separa as linhas.'],
        ]],
        ['te-projeto', 'setor', 'Novos projetos de marca própria', 'Do briefing ao primeiro lote.', 10, <<<'TXT'
## O caminho de um produto novo
Produto novo de um cliente passa pelas etapas de [Produtos novos](/desenvolvimento): briefing → fórmula (Responsável Técnica) → embalagem e rótulo (Marketing e Qualidade) → documentos → aprovação → primeiro lote.

## O briefing (o que perguntar ao cliente)
- Qual produto, para qual público e em qual apresentação (cápsula, pó, gota, chá)?
- Tem fórmula de referência ou quer a nossa?
- Quantidade do primeiro lote e frequência de recompra.
- Quem faz o rótulo e a embalagem? Já tem marca registrada?
- Precisa de algum documento ou laudo específico?

## Prazos
O prazo depende de matéria-prima, rótulo e agenda da fábrica. **Confirme com a Produção antes de prometer.**

?? O cliente quer lançar "o mesmo colágeno do concorrente". || Peça a lista de ingredientes e quantidades do produto de referência e passe para a Responsável Técnica avaliar no projeto. Nada de prometer igual antes da análise técnica.
TXT, [
            ['Antes de prometer prazo do primeiro lote:', ['Confirmar com a Produção', 'Prometer uma semana', 'Perguntar ao Marketing', 'Não precisa'], 0, 'Depende de matéria-prima, rótulo e agenda.'],
            ['Quem avalia a fórmula de um produto novo?', ['A Responsável Técnica', 'O Depósito', 'O cliente sozinho', 'A Cobrança'], 0, 'A etapa de fórmula é da Técnica.'],
        ]],
        ['te-carteira', 'setor', 'Carteira, ficha do cliente e agenda', 'Encontrar, entender e registrar: o cliente inteiro numa tela.', 18, $carteira, $carteiraPerguntas],
        ['te-vizinho', 'vizinho', 'Como trabalha a Terceirização', 'Para Produção, Qualidade, Técnica e Depósito.', 5, <<<'TXT'
## O que a Terceirização faz
Atende empresas que vendem produtos **com a marca delas**, fabricados por nós: pedidos, produtos por cliente (ligados à nossa fórmula) e o estoque dos rótulos que o cliente envia.

## Como isso chega em você
- **Produção:** ordens com rótulo do cliente; sem rótulo, avise a Terceirização.
- **Qualidade:** entrada dos rótulos do cliente; conferência do lote.
- **Técnica:** fórmulas e documentos dos produtos do cliente.
- **Depósito:** pedidos de terceirização têm separação própria (aba Terceirização).
TXT, [
            ['Ordem de terceirização sem rótulo. Quem avisar?', ['A Terceirização, que fala com o cliente', 'O Marketing', 'Ninguém', 'O Depósito'], 0, 'O rótulo vem do cliente.'],
        ]],
    ]],
];
