<?php
/**
 * Treinamento (V13.0) — trilhas do Comercial, Cobrança, Financeiro e Diretoria.
 * Formato igual ao de treinamento_integracao.php. Cada trilha tem um módulo "vizinho": como o setor trabalha,
 * para os setores que têm contato com ele.
 */

// ---------- textos usados em mais de uma trilha ----------
$carteira = <<<'TXT'
## A sua carteira
Todo cliente tem **um responsável**. Os seus estão em [Carteira](/carteira): Visão geral, Lista, Funil e Mapa.
- Use os **filtros** (cidade, etapa, segmento, situação de compra) para montar a lista do dia.
- O **funil** mostra a etapa de cada negociação: Validar cadastro → Não abordado → Contato realizado → Em negociação → Proposta enviada → Aguardando retorno → Cliente ativo.

## A ficha do cliente
Clique no nome do cliente para abrir a ficha. Nela:
1. **Registre cada contato** (ligação, WhatsApp, visita): vai para a linha do tempo com o seu nome e a data.
2. **Agende o próximo contato**: aparece na sua [Agenda](/agenda) no dia.
3. Veja **o que ele mais compra** e use **"Repetir último pedido"**.
4. Faça **pedido** ou **orçamento** dali mesmo.

> Regra de ouro: terminou a conversa? Registre o que foi combinado e agende o próximo passo. Cliente sem próximo passo é cliente esquecido.

! Cliente sem compra há muitos dias aparece em Reativação, e passou do tempo normal de recompra aparece em Recompra. Os dois continuam com você.
TXT;
$carteiraPerguntas = [
    ['Terminou uma ligação com o cliente. O que não pode faltar?', ['Nada, só desligar', 'Registrar o contato e agendar o próximo passo', 'Mandar um áudio para a gestora', 'Exportar a carteira'], 1, 'O registro e o próximo contato mantêm o histórico e a Agenda em dia.'],
    ['Onde aparece o retorno que você agendou na ficha?', ['Na Agenda, no dia marcado', 'No catálogo', 'Só no e-mail', 'Na Biblioteca de produtos'], 0, 'Os retornos agendados entram na Agenda.'],
    ['Cliente que passou do tempo normal entre uma compra e outra aparece em:', ['Recompra', 'Cobrança', 'Configurações', 'Auditoria'], 0, 'A Recompra lista quem passou do intervalo médio de compra.'],
];

$pedido = <<<'TXT'
## Montando o pedido
Em [Pedidos](/pedidos) › **Novo pedido** (ou pela ficha do cliente):
1. Escolha o cliente: a **tabela de preço** dele (Lojista, Distribuidor ou Terceirização) vem sozinha.
2. Coloque os produtos por **unidade** (não vendemos kits).
3. O carrinho mostra **quanto falta para o pedido mínimo** da região e a **faixa de campanha** que o pedido já alcançou.
4. Escolha a **forma de pagamento**. Cliente com título em atraso não pode boleto: só Pix ou cartão.
5. Envie: o pedido vai para a **aprovação do Financeiro** e depois para o Omie.

## Orçamento por link
Em [Orçamentos](/orcamentos) você monta e manda um **link pelo WhatsApp**. O cliente abre, muda quantidades e aprova: **vira pedido sozinho**. Use **Modelos de pedido** para listas que você manda sempre.

## Campanhas e descontos
Veja em [Campanhas](/campanhas) o que está no ar. O desconto que você pode dar tem limite configurado; acima dele, precisa de aprovação.

! Nunca prometa prazo de entrega, troca ou desconto que dependa de aprovação antes de confirmar.
TXT;
$pedidoPerguntas = [
    ['O cliente tem título em atraso. Que formas de pagamento ele pode usar?', ['Qualquer uma', 'Só Pix ou cartão', 'Só boleto', 'Nenhuma'], 1, 'Com título em atraso, o boleto fica bloqueado.'],
    ['Depois que você envia o pedido, ele vai para:', ['O Depósito separar na hora', 'A aprovação do Financeiro', 'O Marketing', 'Direto para o cliente'], 1, 'O Financeiro aprova e então o pedido segue para o Omie.'],
    ['O que acontece quando o cliente aprova o orçamento pelo link?', ['Nada, a vendedora precisa digitar de novo', 'Vira pedido sozinho', 'Vai para a Cobrança', 'É apagado'], 1, 'Orçamento aprovado pelo link vira pedido automaticamente.'],
];

$atendimento = <<<'TXT'
## Prepare o contato
- Leia o **histórico** na ficha antes de ligar.
- Confirme com quem você fala e quem cuida das compras.
- Apresente-se: "Sou [seu nome], da Perfeita Alquimia, fabricante de produtos naturais e suplementos de São Leopoldo."

## Pergunte antes de oferecer
- Quais linhas têm mais saída na sua loja hoje?
- Você busca reposição ou novidades para o mix?
- Qual o investimento deste pedido e para quando precisa?
- Tem algum item parado ou fornecedor que não está atendendo bem?

## Falas para objeções
- **"Está caro":** "Você está comparando o valor por unidade ou o total? Me diz a apresentação e a quantidade que eu confiro."
- **"Vou pensar":** "Claro. O que falta avaliar: os produtos, o investimento ou o momento? Posso te procurar em [data]?"
- **"Esse produto trata minha condição?":** "Posso te passar a composição e o rótulo. Para saber se é adequado, é preciso falar com um profissional de saúde."

## Conheça os produtos
Estude as fichas na [Biblioteca de produtos](/academia/produtos): Conheça o produto, Composição, Uso e cuidados e No atendimento.
TXT;
$atendimentoPerguntas = [
    ['Qual o melhor começo de conversa com um lojista novo?', ['Mandar a lista completa de produtos', 'Fazer perguntas para entender a loja antes de oferecer', 'Oferecer desconto logo de cara', 'Pedir o pedido mínimo'], 1, 'Perguntas primeiro: a oferta fica certa para a loja.'],
    ['O cliente diz "está caro". O que perguntar?', ['Nada, dar desconto', 'Se ele compara preço por unidade ou o total, e qual apresentação', 'Se ele quer desistir', 'O CNPJ dele'], 1, 'Investigue a comparação antes de falar de condição.'],
    ['Onde estudar a composição e as falas de um produto?', ['Na Biblioteca de produtos', 'Na Auditoria', 'Em Configurações', 'No sino'], 0, 'A Biblioteca tem as fichas para estudo.'],
];

$vizFinanceiro = <<<'TXT'
## O que o Financeiro faz com o seu pedido
1. Confere o **crédito** do cliente e se há **títulos em atraso**.
2. Confere a **forma de pagamento** e as condições (prazo, parcelas, descontos acima do limite).
3. **Aprova** (o pedido segue para o Omie) ou **recusa com o motivo**; você recebe o aviso no sino.

## Como ajudar o pedido a passar rápido
- Cadastro do cliente completo (CNPJ, endereço, e-mail para nota).
- Forma de pagamento certa: cliente com atraso só paga com Pix ou cartão.
- Desconto acima do seu limite? Explique o motivo na observação.

> Pedido recusado não é o fim: leia o motivo, ajuste com o cliente e envie de novo.
TXT;

return [
    // =====================================================================
    ['chave' => 'televendas', 'funcao' => 'televendas', 'titulo' => 'Televendas', 'icone' => '📞',
     'vizinhos' => 'financeiro,deposito,marketing,cobranca,producao',
     'descricao' => 'Atendimento e vendas por telefone e WhatsApp para lojistas de todo o Brasil.',
     'modulos' => [
        ['tv-papel', 'setor', 'O papel do televendas', 'Entender o lojista, montar o mix certo e acompanhar até a entrega.', 6, <<<'TXT'
## O que se espera de você
O televendas cuida do relacionamento com os lojistas da sua carteira: **reposição**, **novidades** e **reativação**. Você é a voz da empresa para a loja.

## A rotina que funciona
1. Abra [Meu mês](/meu-mes): veja a meta e o "Para hoje".
2. Comece pela [Agenda](/agenda): **atrasados primeiro**, depois os de hoje.
3. Passe pela [Recompra](/recompra): clientes que já deveriam ter comprado de novo.
4. Use o tempo livre para [Reativação](/reativacao) e para a lista filtrada da [Carteira](/carteira).
5. No fim do dia: tudo registrado e o próximo contato agendado.

## O que você pode e o que precisa de aprovação
- Pode: montar pedido e orçamento, aplicar campanha, dar desconto até o seu limite.
- Precisa de aprovação: desconto acima do limite, prazo especial, troca ou devolução.
TXT, [
            ['Por onde começar o dia no televendas?', ['Pela Agenda, atrasados primeiro', 'Ligando para clientes aleatórios', 'Pelo Marketing', 'Pela Auditoria'], 0, 'Atrasados primeiro, depois os de hoje.'],
            ['Desconto acima do seu limite:', ['Pode dar sem falar com ninguém', 'Precisa de aprovação', 'É proibido sempre', 'Só no catálogo'], 1, 'Acima do limite configurado, o desconto passa por aprovação.'],
        ]],
        ['tv-carteira', 'setor', 'Carteira, ficha do cliente e agenda', 'Como organizar seus clientes e nunca esquecer um retorno.', 8, $carteira, $carteiraPerguntas],
        ['tv-pedido', 'setor', 'Pedido, orçamento e campanhas', 'Do carrinho ao pedido aprovado.', 8, $pedido, $pedidoPerguntas],
        ['tv-atendimento', 'setor', 'Abordagem, falas e objeções', 'Perguntas que vendem e respostas para as objeções comuns.', 10, $atendimento, $atendimentoPerguntas],
        ['tv-recompra', 'setor', 'Recompra e reativação', 'Os clientes que mais rendem: quem já compra.', 6, <<<'TXT'
## Recompra
O One calcula o **intervalo médio** entre as compras de cada cliente. Passou do prazo, ele entra em [Recompra](/recompra). Os primeiros da lista valem mais (o quanto compram × quanto estão atrasados).

> Fala: "Oi, [nome]! Como foi a saída dos produtos do último pedido? Quais estão acabando? Posso montar a reposição com base no que teve mais procura."

## Reativação
Clientes **sem comprar há muitos dias** (o número é configurado pela empresa) aparecem em [Reativação](/reativacao). Eles **continuam com você**. Ofereça a campanha do mês, pergunte o que mudou na loja e registre o motivo de não ter comprado.

## Mensagens prontas
Use as [Mensagens prontas](/mensagens) de reativação no WhatsApp: {nome} e {link_vendedor} entram sozinhos.

! Quando o cliente reativado faz o pedido, o One comemora com você. 🎉
TXT, [
            ['Como o One sabe que um cliente está "atrasado" na recompra?', ['Pelo intervalo médio entre as compras dele', 'Pelo humor da vendedora', 'Pela cidade', 'Pelo segmento'], 0, 'Cada cliente tem o próprio ritmo de compra.'],
            ['Cliente em Reativação continua com quem?', ['Com você, a vendedora responsável', 'Com a Diretoria', 'Com o Marketing', 'Com ninguém'], 0, 'A reativação fica com a vendedora da carteira.'],
        ]],
        ['tv-vizinho', 'vizinho', 'Como trabalha o Televendas', 'Para quem recebe o trabalho do televendas.', 4, <<<'TXT'
## O que o televendas faz
Atende os lojistas por telefone e WhatsApp: reposição, novidades, campanhas e reativação. Cada cliente tem uma vendedora responsável na Carteira.

## O que o televendas precisa de você
- **Financeiro:** aprovação rápida dos pedidos e o motivo claro quando recusar.
- **Depósito:** prazo de despacho e o rastreio, para avisar o cliente.
- **Marketing:** artes e materiais pedidos em Pedir ao Marketing.
- **Produção:** previsão de produtos em falta.

## Onde ver o que o televendas está fazendo
O histórico de cada cliente está na ficha (linha do tempo). Os pedidos estão em [Pedidos](/pedidos).
TXT, [
            ['Onde fica o histórico de contatos do televendas com um cliente?', ['Na linha do tempo da ficha do cliente', 'No e-mail da vendedora', 'Em lugar nenhum', 'Na Auditoria apenas'], 0, 'Tudo é registrado na ficha.'],
        ]],
    ]],
    // =====================================================================
    ['chave' => 'externo', 'funcao' => 'externo', 'titulo' => 'Vendedor externo', 'icone' => '🚗',
     'vizinhos' => 'financeiro,deposito,marketing,televendas',
     'descricao' => 'Visitas às lojas, pedidos pelo app no celular (mesmo sem internet) e a sua carteira.',
     'modulos' => [
        ['ex-app', 'setor', 'O app do externo, mesmo sem internet', 'Instalar, anotar pedidos offline e enviar sem duplicar.', 8, <<<'TXT'
## Instalar no celular
Abra o One no celular, entre em **App do externo** e escolha **"Adicionar à tela inicial"**. Ele abre como um aplicativo.

## Antes de sair para a rota
Abra o app **com internet**: ele guarda a sua carteira, os produtos e os preços no celular.

## Pedido sem internet
1. Escolha o cliente e os produtos.
2. Salve: o pedido fica **"aguardando envio"**.
3. Quando a internet voltar, ele é **enviado sozinho** (ou ao abrir o app).
4. Status: aguardando envio → enviado → aprovado.

> Cada pedido tem um código gerado no celular: mesmo que o envio aconteça duas vezes, o sistema **não duplica**.

! Antes de sair da loja, confira com o cliente quantidades e forma de pagamento.
TXT, [
            ['Você anotou um pedido sem internet. O que acontece?', ['Perde o pedido', 'Fica aguardando envio e vai sozinho quando a internet voltar', 'Precisa digitar de novo no computador', 'Vai por SMS'], 1, 'O app guarda e envia quando houver conexão.'],
            ['O pedido pode ser duplicado se o envio acontecer duas vezes?', ['Sim, sempre', 'Não: cada pedido tem um código único', 'Só às sextas', 'Depende do cliente'], 1, 'O servidor ignora o mesmo código repetido.'],
            ['O que fazer antes de sair para a rota?', ['Nada', 'Abrir o app com internet para atualizar carteira e preços', 'Desinstalar o app', 'Mandar a carteira por e-mail'], 1, 'Assim os dados ficam guardados no celular.'],
        ]],
        ['ex-visita', 'setor', 'A visita que vende', 'Preparar, conduzir e registrar a visita.', 7, <<<'TXT'
## Antes da visita
- Veja na ficha: o que o cliente mais compra, o último pedido e os títulos em aberto.
- Planeje a rota no **Mapa** da [Carteira](/carteira).

## Na loja
- Olhe a gôndola: o que está faltando, o que está parado.
- Pergunte o que tem saída e o que os clientes da loja procuram.
- Mostre a **campanha do mês** e os lançamentos.
- Feche o pedido no app ou no sistema.

## Depois da visita
Registre a visita na ficha e **agende o próximo contato**. Combine se a reposição vai ser pelo televendas ou na próxima visita.
TXT, [
            ['Onde planejar a rota de visitas?', ['No Mapa da Carteira', 'Na Biblioteca de produtos', 'Em Comunicados', 'No sino'], 0, 'O mapa mostra os clientes por cidade.'],
            ['Depois da visita, o que fazer?', ['Nada', 'Registrar a visita e agendar o próximo contato', 'Apagar o cliente', 'Mandar para a Cobrança'], 1, 'Histórico e próximo passo sempre.'],
        ]],
        ['ex-carteira', 'setor', 'Carteira, ficha do cliente e agenda', 'Como organizar seus clientes e nunca esquecer um retorno.', 8, $carteira, $carteiraPerguntas],
        ['ex-pedido', 'setor', 'Pedido, orçamento e campanhas', 'Do carrinho ao pedido aprovado.', 8, $pedido, $pedidoPerguntas],
        ['ex-vizinho', 'vizinho', 'Como trabalha o Vendedor externo', 'Para quem recebe o trabalho do externo.', 4, <<<'TXT'
## O que o externo faz
Visita as lojas, mostra os produtos na gôndola e anota pedidos no **app do celular**, que funciona sem internet. Os pedidos chegam com a origem "app".

## Como ajudar
- **Financeiro:** pedidos do app chegam em lote quando a internet volta; confira normalmente.
- **Depósito:** pedidos de rota costumam ter combinado de entrega; veja a observação.
- **Televendas:** algumas lojas alternam visita e telefone; combine na ficha quem faz o próximo contato.
TXT, [
            ['Os pedidos do externo podem chegar em lote. Por quê?', ['Porque o app guarda os pedidos sem internet e envia quando a conexão volta', 'Porque o externo esquece', 'Porque o sistema trava', 'Porque são pedidos falsos'], 0, 'O app funciona offline.'],
        ]],
    ]],
    // =====================================================================
    ['chave' => 'prospeccao_reativacao', 'funcao' => 'prospeccao_reativacao', 'titulo' => 'Prospecção', 'icone' => '🔎',
     'vizinhos' => 'televendas,externo,marketing',
     'descricao' => 'Encontrar lojas novas, fazer o primeiro contato e passar o cliente pronto para a carteira.',
     'modulos' => [
        ['pr-tela', 'setor', 'A tela de Prospecção', 'Leads para abordar, base de CNPJs e leads do site.', 7, <<<'TXT'
## De onde vêm os leads
- **Base de CNPJs:** em "Encontrar empresas", escolha estado e cidade; aparecem empresas ativas dos segmentos dos nossos clientes. As que **já estão na carteira** vêm marcadas.
- **Site e anúncios:** formulários criam o lead sozinhos, com a campanha (UTM) de origem.
- **Indicações e eventos:** cadastre na Carteira.

## O caminho do lead
1. Envie para o funil.
2. Primeiro contato (ligação ou WhatsApp com mensagem pronta).
3. Diagnóstico: o que a loja vende, quem compra, quando.
4. Primeiro pedido → o cliente passa para uma **vendedora** da carteira.

> Valide o cadastro antes: CNPJ, nome fantasia, telefone e cidade certos evitam retrabalho.
TXT, [
            ['Como saber se uma empresa da base de CNPJs já é nossa cliente?', ['Ela aparece marcada como já na carteira', 'Não tem como saber', 'Ligando para ela', 'Pela cor do logotipo'], 0, 'A busca marca quem já está na carteira.'],
            ['Lead que fez o primeiro pedido:', ['Fica na Prospecção para sempre', 'Passa para uma vendedora da carteira', 'Vai para a Cobrança', 'É apagado'], 1, 'Convertido, ele vai para a carteira de uma vendedora.'],
        ]],
        ['pr-abordagem', 'setor', 'Primeiro contato e diagnóstico', 'Como abrir a conversa com uma loja que não nos conhece.', 8, $atendimento, $atendimentoPerguntas],
        ['pr-mensagens', 'setor', 'Mensagens prontas e WhatsApp', 'Modelos com {nome} e {link_vendedor}.', 5, <<<'TXT'
## Mensagens prontas
Em [Mensagens prontas](/mensagens) há modelos de **prospecção**, **reativação** e **cobrança**. Na ficha do cliente, escolha o modelo: o One troca {nome} pelo nome do contato e {link_vendedor} pelo **seu link do catálogo**.

## O link da vendedora
Pedidos feitos pelo cliente no catálogo usando o **seu link** (/catalogo/v/seu-código) entram para você.

## Boas práticas no WhatsApp
- Mensagem curta, com o seu nome e o da empresa.
- Uma pergunta por mensagem.
- Não mande lista de preços sem saber a necessidade.
- Cliente pediu para parar? Registre e respeite.
TXT, [
            ['O que é {link_vendedor} numa mensagem pronta?', ['O link do catálogo da vendedora: pedidos por ele entram para ela', 'O WhatsApp da empresa', 'O site da Anvisa', 'Um erro'], 0, 'Pedidos pelo link da vendedora vão para ela.'],
        ]],
        ['pr-vizinho', 'vizinho', 'Como trabalha a Prospecção', 'Para quem recebe os clientes novos.', 4, <<<'TXT'
## O que a Prospecção faz
Encontra lojas novas (base de CNPJs, site, anúncios), faz o primeiro contato e o diagnóstico. Quando o lead faz o primeiro pedido, ele **passa para uma vendedora** da carteira.

## O que chega para você
- Cliente novo com cadastro validado e o histórico do primeiro contato na ficha.
- Leia a linha do tempo antes de ligar: o cliente não precisa repetir o que já contou.

## Marketing e Prospecção
Os leads dos anúncios chegam com a campanha de origem; o Marketing acompanha quantos viraram clientes em Leads do site.
TXT, [
            ['Recebeu um cliente novo da Prospecção. O que fazer antes de ligar?', ['Ler a linha do tempo da ficha', 'Pedir o CNPJ de novo', 'Mandar a tabela inteira', 'Nada'], 0, 'O histórico do primeiro contato está na ficha.'],
        ]],
    ]],
    // =====================================================================
    ['chave' => 'terceirizacao', 'funcao' => 'terceirizacao', 'titulo' => 'Terceirização e Marca Própria', 'icone' => '🏷️',
     'vizinhos' => 'producao,qualidade,tecnica,financeiro,deposito',
     'descricao' => 'Clientes que fabricam com a marca deles: produtos por cliente, pedidos e rótulos.',
     'modulos' => [
        ['te-area', 'setor', 'A área de Terceirização', 'Clientes, pedidos, produtos por cliente e rótulos.', 8, <<<'TXT'
## As abas
Em [Terceirização](/terceirizacao):
- **Clientes:** quem produz com a gente.
- **Pedidos:** os pedidos de produção de cada cliente (podem ser mistos, com produtos da nossa marca).
- **Produtos por cliente:** o produto com a marca do cliente, ligado à nossa fórmula.
- **Rótulos:** o estoque dos rótulos que o cliente envia.

## Rótulos do cliente
O rótulo é do cliente: **sem rótulo, não dá para produzir**. O alerta avisa quando o saldo não cobre as ordens planejadas. Avise o cliente com antecedência.

## Do pedido à entrega
Pedido aprovado → ordem de produção (baixa matérias-primas e rótulos) → Qualidade → Depósito despacha.
TXT, [
            ['Por que o estoque de rótulos do cliente é importante?', ['Sem rótulo do cliente não dá para produzir', 'Porque é bonito', 'Não é importante', 'Porque vai para o catálogo'], 0, 'O rótulo é do cliente: sem ele, a produção para.'],
            ['Onde fica o produto com a marca do cliente?', ['Em Produtos por cliente', 'Na Biblioteca de produtos', 'Em Campanhas', 'No catálogo público'], 0, 'Cada produto do cliente fica ligado à nossa fórmula.'],
        ]],
        ['te-projeto', 'setor', 'Novos projetos de marca própria', 'Do briefing ao primeiro lote.', 6, <<<'TXT'
## Um projeto novo
Produto novo de um cliente passa pelas etapas de [Produtos novos](/desenvolvimento): briefing, fórmula (Responsável Técnica), embalagem e rótulo (Marketing e Qualidade), documentos e aprovação.

## O que combinar com o cliente
- Fórmula e apresentação (cápsula, pó, gota...).
- Quantidade mínima e prazo do primeiro lote.
- Quem fornece o rótulo e a embalagem.
- Documentos e registros que ele precisa.

! Prazo de produção depende de matéria-prima e rótulo. Confirme com a Produção antes de prometer.
TXT, [
            ['Antes de prometer prazo do primeiro lote ao cliente:', ['Confirme com a Produção', 'Prometa uma semana', 'Pergunte ao Marketing', 'Não precisa confirmar'], 0, 'O prazo depende de matéria-prima, rótulo e agenda da fábrica.'],
        ]],
        ['te-carteira', 'setor', 'Carteira, ficha do cliente e agenda', 'Como organizar seus clientes e nunca esquecer um retorno.', 8, $carteira, $carteiraPerguntas],
        ['te-vizinho', 'vizinho', 'Como trabalha a Terceirização', 'Para Produção, Qualidade, Técnica e Depósito.', 4, <<<'TXT'
## O que a Terceirização faz
Atende empresas que vendem produtos **com a marca delas**, fabricados por nós. Cuida dos pedidos, dos produtos por cliente e do estoque de rótulos que o cliente envia.

## Como isso chega em você
- **Produção:** ordens de produção com rótulo do cliente; sem rótulo, avise a Terceirização.
- **Qualidade e Técnica:** fórmula e documentos dos produtos do cliente.
- **Depósito:** pedidos de terceirização têm separação própria (aba Terceirização).
TXT, [
            ['A ordem de um produto de terceirização está sem rótulo. Quem avisar?', ['A Terceirização, que fala com o cliente', 'O Marketing', 'Ninguém', 'O Depósito'], 0, 'O rótulo vem do cliente; a Terceirização cuida disso.'],
        ]],
    ]],
    // =====================================================================
    ['chave' => 'cobranca', 'funcao' => 'cobranca', 'titulo' => 'Cobrança', 'icone' => '💳',
     'vizinhos' => 'financeiro,televendas',
     'descricao' => 'Recuperar títulos em atraso com respeito, registrar promessas e manter o cliente comprando.',
     'modulos' => [
        ['co-tela', 'setor', 'A tela de Cobrança', 'Funil, lista, promessas e títulos do ERP.', 7, <<<'TXT'
## Como funciona
Em [Cobrança](/cobranca) estão os clientes com **títulos em atraso**, num funil próprio. Os títulos vêm do ERP ("Ler títulos vencidos do ERP").

## O dia a dia
1. Comece pelos maiores valores e mais atrasados.
2. Registre cada **tentativa** de contato.
3. Cliente prometeu pagar? Registre a **promessa** com a data: ela aparece para você no dia.
4. Arraste o cliente para a etapa certa.

## Buscar contatos
Cliente sem telefone que atenda? Use "Buscar contatos" na ficha para encontrar contatos novos.

> Enquanto houver atraso, o cliente só compra com Pix ou cartão: o boleto fica bloqueado.
TXT, [
            ['O cliente prometeu pagar na sexta. O que fazer?', ['Registrar a promessa com a data', 'Esperar e ver', 'Apagar o título', 'Mandar para a Prospecção'], 0, 'A promessa aparece para você no dia combinado.'],
            ['Cliente com atraso pode comprar?', ['Não, nunca', 'Sim, só com Pix ou cartão', 'Sim, com boleto', 'Só com aprovação da Diretoria'], 1, 'O boleto fica bloqueado enquanto houver atraso.'],
        ]],
        ['co-conversa', 'setor', 'Como cobrar sem perder o cliente', 'Falas e cuidados na conversa de cobrança.', 6, <<<'TXT'
## O tom da conversa
Cobrança boa **mantém o cliente comprando**. Seja direta e gentil.
> "Oi, [nome], tudo bem? Aqui é [seu nome], da Perfeita Alquimia. Identificamos o título de [data] em aberto. Aconteceu alguma coisa? Como posso te ajudar a regularizar?"

## Cuidados
- Fale só com o responsável financeiro da loja.
- Nunca exponha a dívida para outras pessoas.
- Combine data e forma de pagamento e **registre a promessa**.
- Mensagens prontas de cobrança estão em [Mensagens prontas](/mensagens).

! Ameaça, constrangimento ou contato fora do horário comercial não são permitidos.
TXT, [
            ['Com quem falar sobre o título em atraso?', ['Com o responsável financeiro da loja', 'Com qualquer funcionário', 'Com os clientes da loja', 'Com a vendedora concorrente'], 0, 'A dívida não pode ser exposta a outras pessoas.'],
        ]],
        ['co-vizinho', 'vizinho', 'Como trabalha a Cobrança', 'Para vendedoras e Financeiro.', 4, <<<'TXT'
## O que a Cobrança faz
Recupera os títulos em atraso com um funil próprio, registra tentativas e promessas de pagamento e tenta manter o cliente ativo.

## Como você participa
- **Vendedora:** na ficha, "Enviar para a Cobrança" passa o cliente com o histórico. Combine com a Cobrança antes de oferecer prazo a quem está em atraso.
- **Financeiro:** a baixa do pagamento no ERP tira o cliente da Cobrança.
TXT, [
            ['Cliente da sua carteira está em atraso. Antes de oferecer prazo:', ['Combine com a Cobrança', 'Ofereça boleto em 90 dias', 'Ignore o atraso', 'Cancele o cliente'], 0, 'A Cobrança acompanha o acordo.'],
        ]],
    ]],
    // =====================================================================
    ['chave' => 'financeiro', 'funcao' => 'financeiro', 'titulo' => 'Financeiro', 'icone' => '💰',
     'vizinhos' => 'televendas,cobranca,deposito,qualidade',
     'descricao' => 'Aprovação de pedidos, crédito, compras da empresa e relatórios de vendas.',
     'modulos' => [
        ['fi-aprovacao', 'setor', 'Aprovação de pedidos', 'Crédito, pagamento e o motivo da recusa.', 7, <<<'TXT'
## A fila
Em [Aprovação](/aprovacao) estão os pedidos esperando você. Para cada um:
1. **Crédito:** limite, títulos em aberto e em atraso.
2. **Pagamento:** com atraso, só Pix ou cartão.
3. **Condições:** descontos acima do limite da vendedora, prazos e parcelas.
4. **Aprove** (vai ao Omie como orçamento/etapa a aprovar) ou **recuse com o motivo**.

> O motivo da recusa chega para a vendedora no sino. Seja claro: "título de 10/10 em aberto; aceitar Pix".
TXT, [
            ['Ao recusar um pedido, o que não pode faltar?', ['O motivo claro', 'Um emoji', 'O CNPJ do Financeiro', 'Nada'], 0, 'A vendedora precisa saber o que ajustar.'],
            ['Para onde vai o pedido aprovado?', ['Para o Omie', 'Para o Marketing', 'Para a Prospecção', 'Para o lixo'], 0, 'Aprovado, ele segue para o ERP.'],
        ]],
        ['fi-compras', 'setor', 'Compras da empresa e fornecedores', 'Notas, itens, recorrência e notas do Omie.', 7, <<<'TXT'
## Compras da empresa
Em [Compras da empresa](/financeiro/compras) fica tudo o que a empresa compra: matéria-prima, embalagem, rótulo e uso e consumo.
- **Lançar compra:** nota à mão; o estoque do item sobe e o último preço do fornecedor atualiza.
- **Do Omie:** notas de entrada chegam sozinhas; item novo você liga uma vez a um item do One.
- **Itens comprados:** recorrência, quanto dura e próxima compra prevista.

## Fornecedores
Em [Fornecedores](/financeiro/fornecedores): o que cada um fornece, preços comparados, notas e documentos (laudos, certificados) com validade.
TXT, [
            ['Lançou uma nota de compra à mão. O que acontece com o estoque do item?', ['Sobe sozinho', 'Não muda', 'Zera', 'Vai para o Marketing'], 0, 'O lançamento soma no estoque do item.'],
        ]],
        ['fi-relatorios', 'setor', 'Relatórios de vendas', 'Painel, recorrência e impressão.', 5, <<<'TXT'
## Painel de vendas
Em [Resultados](/painel): faturamento, pedidos, ticket médio e recorrência, por vendedor, função, região e período. Imprima em A4 ou exporte em CSV.

## Recompra e inatividade
O painel mostra clientes sem compra há 30, 60 e 90 dias e o alerta de recompra.
TXT, [
            ['Onde ver faturamento por vendedor e período?', ['No Painel de vendas (Resultados)', 'Na Agenda', 'No Treinamento', 'Nas Mensagens prontas'], 0, 'O painel tem os filtros de período, vendedor e região.'],
        ]],
        ['fi-vizinho', 'vizinho', 'Como trabalha o Financeiro', 'O que acontece com o seu pedido depois que você envia.', 4, $vizFinanceiro, [
            ['Como ajudar o seu pedido a ser aprovado rápido?', ['Cadastro completo e forma de pagamento certa', 'Mandar várias vezes', 'Ligar para a diretoria', 'Não preencher a observação'], 0, 'Cadastro e pagamento corretos evitam recusa.'],
            ['O pedido foi recusado. E agora?', ['Ler o motivo, ajustar com o cliente e enviar de novo', 'Desistir do cliente', 'Reclamar no grupo', 'Apagar o pedido'], 0, 'O motivo diz o que ajustar.'],
        ]],
    ]],
    // =====================================================================
    ['chave' => 'diretoria', 'funcao' => 'diretoria', 'titulo' => 'Diretoria', 'icone' => '🧭',
     'vizinhos' => 'televendas,financeiro,producao,marketing',
     'descricao' => 'Acompanhar a empresa pelos números e aprovar o que for pedido.',
     'modulos' => [
        ['di-numeros', 'setor', 'A empresa pelos números', 'Início, Resultados, metas e alertas.', 6, <<<'TXT'
## Onde olhar
- **Início:** faturado do mês, pedidos parados, cobrança e alertas de produção.
- [Resultados](/painel): painel de vendas, equipe, acompanhamento e metas.
- **Produção:** matéria-prima acabando e reabastecimento.
- **Compras:** notas novas e gastos por categoria.

## O que a Diretoria aprova
Pedidos especiais, compras acima do limite e o que os setores pedirem. Os pedidos de aprovação chegam no sino.

> A Diretoria vê quase tudo **em modo leitura** (👁): consulta sem mexer no trabalho dos setores.
TXT, [
            ['Por que a Diretoria vê muitas telas com o olho 👁?', ['Porque acompanha sem mexer no trabalho dos setores', 'Porque o sistema está com erro', 'Porque não tem acesso', 'Porque é proibido'], 0, 'Modo leitura: acompanha sem alterar.'],
        ]],
        ['di-config', 'setor', 'Regras que a Diretoria decide', 'Pedido mínimo, condições, metas e o que fica nas Configurações.', 5, <<<'TXT'
## Toda regra comercial é configurável
Pedido mínimo por região, dias de inatividade, condições de pagamento, descontos, etapas do funil e segmentos ficam em **Configurações**: mudar não precisa de programador.

## Metas
As metas por vendedor e por função ficam em [Metas](/metas) e alimentam o "Meu mês" de cada pessoa.

## Treinamento da equipe
Em [Treinamento da equipe](/treinamento/equipe) você acompanha quem já fez o treinamento e as notas.
TXT, [
            ['Mudar o pedido mínimo de uma região precisa de programador?', ['Não: fica nas Configurações', 'Sim, sempre', 'Só no fim do ano', 'Só pelo Omie'], 0, 'Regras comerciais são configuráveis.'],
        ]],
        ['di-vizinho', 'vizinho', 'Como trabalha a Diretoria', 'O que a Diretoria acompanha e aprova.', 3, <<<'TXT'
## O que a Diretoria faz
Acompanha a empresa pelos números (vendas, cobrança, produção, compras) e aprova o que os setores pedem.

## Como pedir uma aprovação
Pelo próprio fluxo do sistema (compras, pedidos especiais): o pedido chega no sino da Diretoria. Explique o motivo na observação.
TXT, [
            ['Como a Diretoria fica sabendo de uma aprovação pendente?', ['Pelo sino, no próprio fluxo do sistema', 'Por boato', 'Só em reunião', 'Não fica sabendo'], 0, 'Os pedidos de aprovação chegam no sino.'],
        ]],
    ]],
];
