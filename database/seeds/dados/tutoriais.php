<?php
/**
 * Passo a passo do One em cada tela (V13.0). Usado pelo seed 077_treinamento.php.
 * [rota, título, [[alvo (seletor CSS, ou '' para o balão no meio), texto], ...]]
 * O admin e o Marketing editam em Treinamento › Editar; o que for editado lá não é trocado pelas atualizações.
 */
$cab = '.cabecalho-secao, .cabecalho-pagina';
$acoes = '.cabecalho-secao .acoes, .cabecalho-pagina .botao';
$filtros = '[data-filtros], form.filtros';
$abas = 'nav.abas, nav.pr-abas';

return [
    ['/', 'Início', [
        ['', 'Oi! Eu sou o One, seu ajudante. Na primeira vez que você abrir cada tela, eu mostro rapidinho o que dá pra fazer nela. Vamos lá?'],
        ['.menu-lateral', 'Este é o menu. As telas ficam agrupadas por área (Comercial, Produção, Marketing...). O cadeado 🔒 indica uma tela que não é do seu acesso; o olho 👁 indica que você só consulta.'],
        ['.bento, main', 'O Início é o resumo do seu dia: cada cartão mostra algo que precisa da sua atenção. Clique no cartão para ir direto ao assunto.'],
        ['[data-busca-rapida]', 'A busca rápida acha clientes, produtos e códigos de qualquer tela. Atalho: aperte a tecla / no teclado.'],
        ['[data-sino-botao]', 'No sino chegam os avisos: pedido aprovado, tarefa nova, recado de colega, meta batida.'],
        ['[data-one-abrir]', 'E aqui sou eu! Me clique a qualquer hora: tenho dicas para você e dá pra me perguntar coisas. Em qualquer tela, "O que dá pra fazer aqui?" mostra este passo a passo de novo.'],
        ['', 'Quando terminar de ver cada tela, clique em "Aprendi tudo desta tela". Enquanto não clicar, eu mostro de novo quando você voltar nela.'],
    ]],
    ['/pessoas', 'Pessoas', [
        [$cab, 'Aqui você conhece os colegas: foto, setor, aniversário, gostos e o status de cada um.'],
        [$filtros, 'Procure pelo nome ou filtre por setor.'],
        ['.pe-grade', 'Clique numa pessoa para ver o perfil, deixar um recado ou um elogio.'],
        ['.cabecalho-secao .acoes', 'Em "Editar o meu perfil" você coloca sua foto, capa, status e conta um pouco sobre você.'],
    ]],
    ['/carteira/clientes', 'Ficha do cliente', [
        [$cab, 'Esta é a ficha do cliente: dados, contatos, o que ele mais compra e todo o histórico.'],
        ['', 'Registre cada contato (ligação, WhatsApp, visita). Tudo vai para a linha do tempo, com seu nome e a data.'],
        ['', 'Agende o próximo contato: ele aparece na sua Agenda no dia certo.'],
        ['', '"O que mais compra" e "Repetir último pedido" ajudam a montar a oferta. Daqui também dá para fazer pedido ou orçamento.'],
    ]],
    ['/carteira', 'Carteira', [
        [$cab, 'A Carteira tem todos os seus clientes: cada um tem um responsável.'],
        [$abas, 'Visão geral (resumo e mapa), Lista (com filtros), Funil (etapas da negociação) e Mapa ficam nestas abas.'],
        [$filtros, 'Use os filtros (cidade, etapa, segmento, situação de compra) para montar a sua lista de ligações do dia.'],
        ['[data-mapa], .painel-mapa', 'O mapa mostra onde estão os clientes, agrupados por estado e cidade.'],
        [$acoes, 'Cadastre um estabelecimento novo, importe uma planilha ou exporte a lista em CSV por aqui.'],
        ['', 'Dica: clique no nome do cliente para abrir a ficha com o histórico completo.'],
    ]],
    ['/meu-mes', 'Meu mês', [
        [$cab, 'Meu mês é o seu painel: meta, quanto já vendeu e o que falta.'],
        ['[data-mm-hero], .grade-meu-mes', 'O anel mostra quanto da meta você já fez e o ritmo que precisa por dia útil.'],
        [$abas, 'Nas abas: suas vendas, seus clientes, o calendário do mês e as tarefas.'],
        ['[data-para-hoje]', '"Para hoje" junta o que é mais importante agora: retornos e clientes para ligar.'],
        ['[data-atalho-equipe]', 'Aqui você acompanha a meta da equipe toda.'],
    ]],
    ['/agenda', 'Agenda', [
        [$cab, 'A Agenda mostra os retornos combinados com os clientes.'],
        ['[data-atrasadas], .ag-atrasadas', 'Os atrasados aparecem primeiro: comece por eles.'],
        ['[data-concluir]', 'Fez o contato? Conclua. Precisa deixar para outro dia? Reagende com um clique.'],
        ['[data-ver-lista], .ag-semana-topo', 'Troque entre a semana e a lista com tudo em ordem.'],
        ['', 'Zerou a agenda do dia? Eu comemoro com você! 🎉'],
    ]],
    ['/recompra', 'Recompra', [
        [$cab, 'Recompra: clientes que passaram do tempo normal entre uma compra e outra.'],
        ['.rc-lista', 'Os primeiros da lista valem mais (o quanto compram × quanto estão atrasados).'],
        ['.rc-agendar', 'Ligue ou mande WhatsApp; se não der agora, agende o contato.'],
        ['', 'Dica: na ficha do cliente, "Repetir último pedido" monta o pedido em segundos.'],
    ]],
    ['/reativacao', 'Reativação', [
        [$cab, 'Reativação: os seus clientes que estão sem comprar há muito tempo.'],
        ['section.card', 'Eles continuam com você. Ofereça a campanha do mês e registre o contato na ficha.'],
        ['', 'Cliente reconquistado que volta a comprar? Eu comemoro quando o pedido sair! 🎉'],
    ]],
    ['/campanhas', 'Campanhas', [
        [$cab, 'Campanhas do mês ou da semana: faixas de desconto, brindes, frete grátis e produtos em promoção.'],
        [$abas, 'Em "Visual do catálogo" você muda as cores e o banner que o cliente vê.'],
        ['.cp-lista', 'Cada cartão é uma campanha: período, para quem vale e as faixas. No ar, agendadas e encerradas ficam separadas.'],
        [$acoes, '"+ Nova campanha" cria uma campanha nova. O cliente vê no catálogo, e a vendedora vê ao montar o pedido.'],
    ]],
    ['/cobranca', 'Cobrança', [
        [$cab, 'Cobrança: clientes com títulos em atraso, com funil próprio.'],
        ['.cob-numeros', 'Os números do topo mostram o total em atraso e as promessas de pagamento.'],
        [$abas, 'Funil (arraste o cliente de uma etapa para outra) ou Lista.'],
        [$filtros, 'Filtre por etapa, valor ou dias de atraso.'],
        [$acoes, 'Leia os títulos vencidos do ERP, imprima ou exporte em CSV.'],
    ]],
    ['/prospeccao', 'Prospecção', [
        [$cab, 'Prospecção: leads para abordar, a base de CNPJs e os leads que chegam do site.'],
        ['.bento', 'Os cartões mostram quem está esperando o primeiro contato, os abordados e os convertidos.'],
        [$filtros, 'Filtre por cidade, origem ou situação.'],
        [$acoes, '"Encontrar empresas" busca empresas por cidade; "Funil dos leads" mostra cada lead na etapa em que está.'],
    ]],
    ['/pedidos/novo', 'Novo pedido', [
        ['', 'Novo pedido: escolha o cliente, os produtos e as quantidades. A tabela de preço é a do cliente.'],
        ['', 'O carrinho mostra quanto falta para o pedido mínimo da região e a faixa de campanha que o pedido já alcançou.'],
        ['', 'Escolha a forma de pagamento e envie: o pedido vai para a aprovação do Financeiro.'],
    ]],
    ['/pedidos', 'Pedidos', [
        [$cab, 'Todos os pedidos: do catálogo, do sistema e do app do externo.'],
        [$filtros, 'Filtre por situação (aguardando aprovação, aprovado, faturado), cliente ou período.'],
        ['section.card', 'Clique no pedido para ver os itens, o andamento e o faturamento.'],
        [$acoes, '"Novo pedido" monta um pedido para o cliente. "Abrir catálogo" mostra o que o cliente vê.'],
    ]],
    ['/orcamentos', 'Orçamentos', [
        [$cab, 'Orçamento por link: o cliente abre no WhatsApp, muda as quantidades e aprova.'],
        ['section.card', 'Aprovado pelo cliente, o orçamento vira pedido sozinho.'],
        [$acoes, '"Novo orçamento" monta um; "Modelos de pedido" guarda listas prontas para usar de novo.'],
    ]],
    ['/terceirizacao', 'Terceirização', [
        [$cab, 'Terceirização e Marca Própria: clientes que produzem com a gente com a marca deles.'],
        [$abas, 'Clientes, Pedidos, Produtos por cliente e Rótulos ficam nestas abas.'],
        ['.te-kpis', 'Os números do topo resumem pedidos e alertas.'],
        ['', 'Na aba Rótulos, o alerta avisa quando o rótulo do cliente não dá para as próximas produções: peça para ele enviar.'],
    ]],
    ['/painel', 'Painel de vendas', [
        [$cab, 'O Painel de vendas mostra faturamento, pedidos, ticket médio e recompra.'],
        ['nav.pr-abas', 'Em Resultados, as abas levam para Equipe de vendas, Acompanhamento e Metas.'],
        [$filtros, 'Filtre por período, vendedor, função e região.'],
        ['.bento', 'Cada cartão é um número ou gráfico. Imprima ou exporte em CSV pelos botões do topo.'],
    ]],
    ['/equipe', 'Equipe de vendas', [
        [$cab, 'Equipe de vendas: a meta da equipe e como está cada frente.'],
        ['.meta-equipe', 'A barra mostra quanto a equipe já vendeu da meta do mês.'],
        [$abas, 'Veja todas as frentes juntas ou só Externos, Televendas ou Terceirização.'],
        ['[data-ranking]', 'O ranking mostra quem está puxando o mês.'],
    ]],
    ['/acompanhamento', 'Acompanhamento', [
        [$cab, 'Acompanhamento: tudo de cada vendedora: ligações, gravações, WhatsApp, agenda e meta.'],
        ['.ac-periodo', 'Escolha o período.'],
        ['[data-equipe]', 'Clique na pessoa para ver o dia a dia dela e ouvir as ligações.'],
    ]],
    ['/metas', 'Metas', [
        [$cab, 'Metas do mês por vendedor e por função.'],
        ['section.card', 'Digite a meta de cada pessoa e salve.'],
        [$acoes, '"Copiar metas do mês anterior" adianta o trabalho.'],
    ]],
    ['/carteiras', 'Carteiras', [
        [$cab, 'Carteiras: os clientes de cada vendedora e os que estão sem vendedora.'],
        ['.ct-filtro, form.chips', 'Filtre por setor.'],
        ['.ct-grade', 'Cada cartão é uma carteira. "Passar" move os clientes de uma carteira para outra (quando alguém sai ou chega).'],
        [$acoes, '"Nova carteira" cria uma carteira; também dá para importar clientes por planilha.'],
    ]],
    ['/financeiro/compras', 'Compras da empresa', [
        [$cab, 'Tudo o que a empresa compra: notas, itens, fornecedores e as notas que chegam do Omie.'],
        [$abas, 'Lançamentos, Itens comprados, Lançar compra, Do Omie e Fornecedores ficam nas abas.'],
        [$acoes, '"+ Lançar compra" registra uma nota à mão; o estoque do item sobe sozinho.'],
        ['', 'Abra um item para ver a recorrência, quanto tempo dura e os fornecedores com o preço de cada um.'],
    ]],
    ['/financeiro/fornecedores', 'Fornecedores', [
        [$cab, 'Cada fornecedor: o que fornece, preços comparados, notas, prazo e documentos.'],
        ['.fo-grade', 'Clique no fornecedor para abrir a página dele.'],
    ]],
    ['/aprovacao', 'Aprovação de pedidos', [
        [$cab, 'Pedidos que esperam a aprovação do Financeiro.'],
        ['.ped-fila', 'Confira o crédito do cliente, a forma de pagamento e os títulos em atraso.'],
        ['', 'Aprove ou recuse com o motivo: a vendedora recebe o aviso no sino e o pedido segue para o Omie.'],
    ]],
    ['/marketing/inicio', 'Início do Marketing', [
        [$cab, 'Início do Marketing: resumo, calendário e as tarefas do setor.'],
        [$abas, 'As abas levam ao Mural de posts, Prévia do feed e E-commerce.'],
        ['.mt-quadro', 'O quadro tem as tarefas por coluna: a fazer, fazendo, aprovação e feito. Arraste para mudar.'],
        [$acoes, '"Pedidos ao Marketing" mostra o que a empresa pediu; "Nova tarefa" cria uma tarefa.'],
    ]],
    ['/marketing/pedir', 'Pedir ao Marketing', [
        [$cab, 'Precisa de uma arte, material, cadastro ou envio? Peça ao Marketing por aqui.'],
        ['[data-pedir], form.card', 'Diga o que precisa e para quando. Dá para anexar arquivos.'],
        ['[data-meus-pedidos]', 'Acompanhe aqui os seus pedidos. Quando ficar pronto, eu aviso.'],
    ]],
    ['/marketing/planejamento', 'Planejamento', [
        [$cab, 'Planejamento: campanhas e datas comemorativas na linha do tempo.'],
        ['.mk-gantt-trilho, section.card', 'Cada barra é uma campanha. Planeje com antecedência as datas importantes.'],
    ]],
    ['/marketing/ecommerce', 'E-commerce', [
        [$cab, 'Desempenho da loja virtual e dos marketplaces.'],
        ['.bento', 'Pedidos, faturamento e produtos mais vendidos no período.'],
        [$acoes, 'Teste a conexão e atualize os números por aqui.'],
    ]],
    ['/marketing', 'Mural de posts', [
        [$cab, 'O calendário do Instagram, com aprovação.'],
        ['.mk-filtros', 'Filtre por mês ou situação.'],
        ['[data-mural]', 'Cada post fica no dia em que vai ao ar. Clique para ver a arte, o texto e aprovar.'],
        [$acoes, '"Novo post" coloca um post no calendário.'],
    ]],
    ['/leads', 'Leads do site', [
        [$cab, 'Leads que chegam pelos formulários do site e dos anúncios, por campanha.'],
        [$filtros, 'Filtre por campanha (UTM) e período.'],
        ['[data-tabela-leads]', 'Cada lead entra no funil da Prospecção sozinho.'],
    ]],
    ['/produtos/catalogo', 'Catálogo', [
        [$cab, 'A vitrine do cliente: o que aparece no catálogo, a ordem, a chamada e os preços.'],
        [$acoes, 'Atualize os preços do ERP ou veja o catálogo como o cliente vê.'],
        ['.prod-busca', 'Busque o produto e ajuste o que aparece.'],
    ]],
    ['/produtos', 'Produtos', [
        [$cab, 'Tudo sobre cada produto: parte técnica, ficha, embalagens e documentos.'],
        ['.filtros-produtos', 'Busque por nome, código ou ingrediente; filtre por categoria.'],
        ['.grade-produtos', 'Clique no produto para abrir a página dele, com abas para cada assunto.'],
    ]],
    ['/academia/produtos', 'Biblioteca de produtos', [
        [$cab, 'As fichas dos produtos para estudar: composição, uso, falas e treinamento técnico.'],
        ['.filtros-produtos', 'Busque por nome, ingrediente ou necessidade (sono, digestão, imunidade...).'],
        ['.grade-produtos', 'Abra a ficha e veja as abas: Conheça o produto, Composição, Uso e cuidados e No atendimento.'],
    ]],
    ['/treinamento', 'Meu treinamento', [
        [$cab, 'O seu treinamento, feito para o seu setor.'],
        ['[data-tr-resumo]', 'Aqui está o seu progresso: módulos concluídos e telas do sistema que você já aprendeu.'],
        ['[data-tr-bloco]', 'Comece pela Integração, depois o seu setor e, por fim, as áreas com que você tem contato.'],
        ['', 'Cada módulo termina com uma provinha. Precisa acertar 70% para concluir; pode refazer quantas vezes quiser.'],
        [$abas, 'Em "Todas as trilhas" você consulta o treinamento de outros setores; a Biblioteca tem as fichas dos produtos.'],
    ]],
    ['/mensagens', 'Mensagens prontas', [
        [$cab, 'Modelos de WhatsApp para prospecção, reativação e cobrança.'],
        ['.msg-grade', 'As variáveis {nome} e {link_vendedor} são trocadas pelos dados do cliente quando você usa a mensagem na ficha.'],
        [$acoes, '"Nova mensagem" cria um modelo.'],
    ]],
    ['/desenvolvimento/minhas', 'Tarefas de produto novo', [
        [$cab, 'Projetos de produto novo esperando a sua etapa.'],
        ['.card', 'Abra o projeto, faça a sua parte e conclua a etapa: ele segue para o próximo setor.'],
    ]],
    ['/desenvolvimento/etapas', 'Etapas do desenvolvimento', [
        [$cab, 'A ordem das etapas de um produto novo, a função responsável e quem aprova.'],
        ['form.card', 'Ajuste e salve: os projetos novos seguem esta ordem.'],
    ]],
    ['/desenvolvimento', 'Produtos novos', [
        [$cab, 'Produtos novos: do briefing ao lançamento, etapa por etapa.'],
        ['.dp-quadro', 'Cada coluna é uma etapa; cada cartão é um projeto.'],
        [$acoes, '"Novo projeto" começa um produto; "Minhas tarefas" mostra o que espera por você.'],
    ]],
    ['/deposito/recebimento', 'Recebimento', [
        [$cab, 'Compras que vão chegar: confira e dê entrada.'],
        ['section.card', 'Confira quantidade, lote e validade; ao receber, o estoque sobe sozinho.'],
    ]],
    ['/deposito/lotes', 'Rastrear lote', [
        [$cab, 'Para quais clientes foi cada lote.'],
        [$filtros, 'Digite o lote do produto: aparecem os pedidos e os clientes que receberam.'],
    ]],
    ['/deposito', 'Separação', [
        [$cab, 'Pedidos com nota para separar e despachar.'],
        ['.dep-tipos', 'Separe por tipo: lojista, e-commerce e terceirização.'],
        ['.dep-fila', 'Os mais urgentes vêm primeiro. Abra o pedido, separe, informe lote e validade e despache com o rastreio.'],
    ]],
    ['/compras', 'Pedidos de compra', [
        [$cab, 'Pedir, orçar e aprovar compras até o Depósito receber.'],
        ['[data-sugestoes]', 'As sugestões mostram as matérias-primas que estão acabando.'],
        [$acoes, '"Novo orçamento" começa uma compra: cote com fornecedores e mande para a aprovação.'],
    ]],
    ['/producao/materias', 'Matérias-primas', [
        [$cab, 'Estoque das matérias-primas, embalagens e rótulos.'],
        [$filtros, 'Filtre por tipo ou situação (abaixo do mínimo).'],
        ['[data-tabela-materias]', 'A previsão mostra quando cada item acaba no ritmo atual. A entrada rápida soma no estoque.'],
        [$acoes, '"Nova matéria-prima" cadastra um item.'],
    ]],
    ['/producao/fichas', 'Fichas técnicas', [
        [$cab, 'O que vai em cada unidade de produto e o custo.'],
        [$filtros, 'Busque o produto.'],
        ['section.card', 'Abra a ficha para ver ou ajustar as quantidades de cada matéria-prima.'],
    ]],
    ['/producao/ordens', 'Ordens de produção', [
        [$cab, 'Planejar, concluir e imprimir as ordens de produção.'],
        [$filtros, 'Filtre por situação: planejada, em produção, concluída.'],
        ['section.card', 'Ao concluir uma ordem, as matérias-primas baixam sozinhas e o produto acabado entra no estoque.'],
        [$acoes, '"Nova ordem" planeja uma produção.'],
    ]],
    ['/producao/rotulos', 'Estoque de rótulos', [
        [$cab, 'Rótulos nossos e dos clientes de terceirização.'],
        ['.te-filtro-situacao', 'Veja só os que estão em alerta.'],
        ['section.card', 'Registre entradas e saídas: o saldo atualiza na hora.'],
    ]],
    ['/producao/acabados', 'Estoque acabado', [
        [$cab, 'Produto pronto para vender.'],
        [$filtros, 'Busque o produto ou veja os abaixo do mínimo.'],
        ['[data-tabela-acabados]', 'Ajuste o mínimo de cada produto: abaixo dele, aparece o pedido de reabastecimento.'],
    ]],
    ['/producao', 'Produção', [
        [$cab, 'O resumo da fábrica: alertas, ordens planejadas e a produção do mês.'],
        ['.bento', 'Os cartões mostram matéria-prima acabando, o que precisa reabastecer e as unidades produzidas.'],
        [$acoes, '"Nova ordem" planeja uma produção; "Imprimir" gera a folha.'],
    ]],
    ['/comunicados', 'Comunicados', [
        [$cab, 'Avisos para a empresa toda ou para alguns setores.'],
        ['[data-form-comunicado]', 'Escreva o comunicado e escolha quem recebe: ele aparece numa janela para cada pessoa.'],
        ['[data-emitidos]', 'Veja quem já leu cada comunicado.'],
    ]],
    ['/usuarios', 'Pessoas e acessos', [
        [$cab, 'Os logins da equipe: cada pessoa tem o seu.'],
        ['nav.pr-abas', 'Nas abas: Funções (os setores) e Acessos (o que cada perfil pode ver e editar).'],
        ['', 'Ao criar uma pessoa, marque a função dela: o treinamento, as filas e as regras seguem a função. O One mostra os tutoriais para ela no primeiro acesso.'],
    ]],
    ['/funcoes', 'Funções', [
        [$cab, 'As funções (setores) da empresa. As regras usam funções, nunca nomes de pessoas.'],
        ['form.card', 'Crie ou ajuste uma função e marque se é um setor de venda.'],
    ]],
    ['/acessos', 'Acessos', [
        [$cab, 'O que cada perfil pode ver e editar, aba por aba.'],
        ['[data-grade-acessos]', 'Marque e salve. Exceções por pessoa ficam na ficha dela.'],
    ]],
    ['/configuracoes/listas', 'Listas', [
        [$cab, 'Listas usadas no sistema: segmentos, origens, motivos, categorias...'],
        ['section.card', 'Acrescente ou ajuste os itens de cada lista.'],
    ]],
    ['/configuracoes', 'Configurações', [
        [$cab, 'As regras do negócio: pedido mínimo, dias sem compra, condições de pagamento e mais.'],
        ['section.card', 'Toda regra comercial é ajustada aqui, sem programador. Salve no fim de cada grupo.'],
    ]],
    ['/integracao', 'Integração Omie', [
        [$cab, 'A ligação com o Omie: produtos, preços, estoque e pedidos.'],
        ['.bento', 'Veja o modo (demonstração ou real) e a última sincronização.'],
        [$acoes, 'Teste a conexão e sincronize quando precisar.'],
    ]],
    ['/sistema/atualizacao', 'Atualização', [
        [$cab, 'Atualizar o sistema e conferir os backups.'],
        ['', 'O One faz backup antes de aplicar qualquer atualização.'],
    ]],
    ['/auditoria', 'Auditoria', [
        [$cab, 'Quem mudou o quê e quando: clientes, pedidos e configurações.'],
        ['form', 'Filtre por pessoa, assunto ou período.'],
    ]],
];
