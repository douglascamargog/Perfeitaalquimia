<?php
/**
 * Treinamento — trilhas da Cobrança, do Financeiro, da Diretoria, da Gestão (gestores) e da Administração (admin).
 * V13.8: aprofundado, no padrão do Comercial. As trilhas "financeiro", "gestor" e "admin" valem pelo PERFIL da pessoa
 * (TreinamentoService::funcoesDe); as outras, pela função.
 */

$vizFinanceiro = <<<'TXT'
## O que o Financeiro faz com o seu pedido
1. Confere o **crédito** do cliente e se há **títulos em atraso**.
2. Confere a **forma de pagamento** e as condições (prazo, parcelas, desconto acima do limite de {{config:pedidos.desconto_max_pct}}%).
3. **Aprova** (o pedido segue para o Omie) ou **recusa com o motivo**: você recebe o aviso no sino.

## Como ajudar o pedido a passar rápido
- Cadastro do cliente completo (CNPJ, endereço, e-mail para a nota).
- Forma de pagamento certa: cliente com atraso só paga com Pix ou cartão.
- Desconto acima do seu limite? **Explique o motivo na observação.**

> Pedido recusado não é o fim: leia o motivo, ajuste com o cliente e envie de novo.
TXT;

return [
    // =====================================================================
    ['chave' => 'cobranca', 'funcao' => 'cobranca', 'titulo' => 'Cobrança', 'icone' => '💳',
     'vizinhos' => 'financeiro,televendas',
     'descricao' => 'Recuperar títulos em atraso com respeito, registrar promessas e manter o cliente comprando.',
     'modulos' => [
        ['co-tela', 'setor', 'O funil da Cobrança', 'Etapas, casos, tentativas, promessas e títulos do ERP.', 16, <<<'TXT'
## Objetivo
Recuperar o dinheiro **e** o cliente: cobrança bem feita mantém a loja comprando.

## Como o cliente entra na Cobrança
Títulos vencidos há mais de **{{config:cobranca.dias_atraso_entrada}} dias** entram sozinhos ("Ler títulos vencidos do ERP"). A vendedora também pode enviar um cliente pela ficha.

## O funil
Título vencido → Tentativa de contato → Sem contato (buscar contatos) → Em negociação → Promessa de pagamento → **Pago** / Acordo fechado / Jurídico ou protesto / Perda.
![Cobrança: o funil com o valor em cada etapa](assets/img/treinamento/cobranca.jpg)
Depois de **{{config:cobranca.tentativas_para_sem_contato}} tentativas sem sucesso**, o caso vai para "Sem contato".

## O caso (passo a passo)
Clique no cartão para abrir o caso:
![O caso: valor em aberto, contatos, busca de contatos e os títulos](assets/img/treinamento/cobranca-caso.jpg)
1. Veja o valor em aberto e os títulos (documento, vencimento, valor).
2. Ligue ou mande WhatsApp; **registre cada tentativa**.
3. Prometeu pagar? Registre a **promessa com a data**: no dia, ela aparece para você.
4. Pagou? **Marcar pago** no título (ou a baixa do ERP faz isso).
5. Ninguém atende? **Buscar contatos com IA** procura telefone, WhatsApp e e-mail novos da empresa.

## Mensagens prontas da Cobrança
- Lembrete: "{{config:cobranca.msg_lembrete}}"
- Para acordo, use o modelo de acordo em Configurações.

## Enquanto houver atraso
O boleto fica bloqueado: o cliente só compra com Pix ou cartão. Avise a vendedora antes de ela oferecer prazo.

?? O cliente prometeu pagar na sexta e não pagou. || Na segunda, registre "promessa não cumprida" e ligue de novo. Duas promessas quebradas: proponha um acordo (parcelas) ou avalie com o Financeiro o próximo passo.
?? A loja diz que já pagou, mas o título continua aberto. || Peça o comprovante, confira com o Financeiro e, se estiver pago, marque pago no título. Nunca discuta: confira primeiro.
TXT, [
            ['Depois de quantos dias de atraso o título entra na Cobrança (configuração atual)?', ['Nunca', 'O número definido em Configurações (o módulo mostra o valor atual)', 'Sempre 1 dia', '1 ano'], 1, 'É configurável.'],
            ['O cliente prometeu pagar na sexta. O que fazer?', ['Registrar a promessa com a data', 'Esperar sem anotar', 'Apagar o título', 'Mandar para a Prospecção'], 0, 'A promessa aparece para você no dia.'],
            ['Ninguém atende no telefone do cadastro. Qual recurso usar?', ['Buscar contatos com IA', 'Desistir', 'Mandar para o Marketing', 'Apagar o cliente'], 0, 'Procura contatos novos da empresa.'],
            ['O cliente diz que já pagou. Primeiro passo:', ['Discutir', 'Pedir o comprovante e conferir com o Financeiro', 'Ignorar', 'Bloquear o cliente'], 1, 'Confira antes de cobrar de novo.'],
        ]],
        ['co-conversa', 'setor', 'Como cobrar sem perder o cliente', 'Falas, acordos e o que nunca fazer.', 10, <<<'TXT'
## O tom da conversa
Direta e gentil. Comece perguntando, não acusando.
> "Oi, [nome], tudo bem? Aqui é [seu nome], da Perfeita Alquimia. Identificamos o título de [data] em aberto. Aconteceu alguma coisa? Como posso te ajudar a regularizar?"

## Acordo
Quando a loja não consegue pagar tudo: proponha parcelas que caibam e registre o acordo no caso (etapa "Acordo fechado"). Combine com o Financeiro os limites do acordo.

## Cuidados
- Fale só com o responsável financeiro da loja.
- Nunca exponha a dívida para outras pessoas.
- Horário comercial, sem ameaça e sem constrangimento.
- Mensagens prontas de cobrança estão em [Mensagens prontas](/mensagens).

! Confira com a sua gestão: limite de parcelas e de desconto em acordo e quando levar para protesto.

?? O dono da loja está irritado e diz que vai parar de comprar. || Escute, reconheça o incômodo e separe os assuntos: o título em aberto e o relacionamento. Ofereça uma solução (acordo) e avise a vendedora da carteira para ela acompanhar o cliente.
TXT, [
            ['Com quem falar sobre o título em atraso?', ['Com o responsável financeiro da loja', 'Com qualquer funcionário', 'Com os clientes da loja', 'Com o concorrente'], 0, 'A dívida não pode ser exposta.'],
            ['A loja não consegue pagar tudo de uma vez:', ['Ameaçar', 'Propor um acordo que caiba e registrar no caso', 'Desistir', 'Mandar para o Marketing'], 1, 'Acordo registrado no caso.'],
        ]],
        ['co-vizinho', 'vizinho', 'Como trabalha a Cobrança', 'Para vendedoras e Financeiro.', 4, <<<'TXT'
## O que a Cobrança faz
Recupera os títulos em atraso com um funil próprio, registra tentativas, promessas e acordos e tenta manter o cliente ativo.

## Como você participa
- **Vendedora:** na ficha, "Enviar para a Cobrança" passa o cliente com o histórico. Combine com a Cobrança antes de oferecer prazo a quem está em atraso.
- **Financeiro:** a baixa do pagamento no ERP tira o título da Cobrança.
TXT, [
            ['Cliente da sua carteira está em atraso. Antes de oferecer prazo:', ['Combine com a Cobrança', 'Ofereça boleto em 90 dias', 'Ignore o atraso', 'Cancele o cliente'], 0, 'A Cobrança acompanha o acordo.'],
        ]],
    ]],
    // =====================================================================
    ['chave' => 'financeiro', 'funcao' => 'financeiro', 'titulo' => 'Financeiro', 'icone' => '💰',
     'vizinhos' => 'televendas,cobranca,deposito,qualidade',
     'descricao' => 'Aprovação de pedidos, crédito, compras da empresa e relatórios de vendas.',
     'modulos' => [
        ['fi-aprovacao', 'setor', 'Aprovação de pedidos', 'Crédito, pagamento, desconto e o motivo da recusa.', 14, <<<'TXT'
## Objetivo
Aprovar rápido o que está certo e barrar, com motivo claro, o que tem risco.

## A fila
Em [Aprovação](/aprovacao) estão os pedidos esperando você (do catálogo, do sistema e do app).
![Aprovação: cada pedido com cliente, valor, pagamento e alertas](assets/img/treinamento/aprovacao.jpg)

## Passo a passo para cada pedido
1. **Crédito:** limite, títulos em aberto e em atraso do cliente.
2. **Pagamento:** com atraso, só Pix ou cartão (o sistema já bloqueia boleto).
3. **Condições:** parcelas e prazos dentro das condições cadastradas:
{{condicoes_pagamento}}
4. **Desconto da vendedora:** acima de {{config:pedidos.desconto_max_pct}}% aparece destacado; leia o motivo na observação.
5. **Aprove** (vai ao Omie na etapa inicial configurada) ou **recuse com o motivo**.

## Tempo é venda
Pedido parado há mais de 24 horas deixa o One bravo; há mais de 3 dias, furioso. Cliente esperando é venda em risco.

?? Um pedido com 15% de desconto, motivo: "cliente novo, primeira compra grande". || Avalie o histórico e a margem; se fizer sentido, aprove. Se não, recuse dizendo o que aceitaria ("aprovo com 10% ou com Pix à vista"), para a vendedora renegociar.
?? O cliente tem um título de R$ 300 vencido há 10 dias e um pedido novo de R$ 8.000 no Pix. || O Pix reduz o risco do pedido novo. Aprove e avise a Cobrança/vendedora para resolver o título em paralelo.
TXT, [
            ['Ao recusar um pedido, o que não pode faltar?', ['O motivo claro (e o que você aceitaria)', 'Um emoji', 'O CNPJ do Financeiro', 'Nada'], 0, 'A vendedora precisa saber o que ajustar.'],
            ['Para onde vai o pedido aprovado?', ['Para o Omie', 'Para o Marketing', 'Para a Prospecção', 'Para o lixo'], 0, 'Aprovado, ele segue para o ERP.'],
            ['Pedido parado há dias na aprovação:', ['Não tem problema', 'É venda em risco: o One avisa (bravo e furioso)', 'É normal', 'Vai sozinho'], 1, 'Agilidade na aprovação.'],
        ]],
        ['fi-compras', 'setor', 'Compras da empresa e fornecedores', 'Notas, itens, recorrência e as notas do Omie.', 12, <<<'TXT'
## Compras da empresa
Em [Compras da empresa](/financeiro/compras) fica tudo o que a empresa compra: matéria-prima, embalagem, rótulo e uso e consumo.
![Compras da empresa: lançamentos, itens, lançar compra, do Omie e fornecedores](assets/img/treinamento/financeiro-compras.jpg)
- **Lançar compra:** nota à mão. O estoque do item sobe e o último preço do fornecedor atualiza.
- **Do Omie:** as notas de entrada chegam sozinhas. Item novo você **liga uma vez** a um item do One; depois entra sozinho.
- **Itens comprados:** recorrência (compra a cada X dias), quanto dura e próxima compra prevista.

## Fornecedores
Em [Fornecedores](/financeiro/fornecedores): o que cada um fornece, preços comparados, notas e documentos com validade.

?? Uma nota do Omie trouxe "Pote PET 250ml" que não existe no One. || Em "Do Omie", ligue o item da nota a um item existente (ou crie o item). Da próxima vez ele entra sozinho no estoque.
TXT, [
            ['Lançou uma nota à mão. O estoque do item:', ['Sobe sozinho', 'Não muda', 'Zera', 'Vai para o Marketing'], 0, 'O lançamento soma no estoque.'],
            ['Item novo vindo do Omie:', ['Liga uma vez a um item do One; depois entra sozinho', 'Precisa lançar à mão sempre', 'Some', 'Vai para a Cobrança'], 0, 'A ligação fica guardada.'],
        ]],
        ['fi-relatorios', 'setor', 'Relatórios de vendas', 'Painel, recorrência e impressão.', 8, <<<'TXT'
## Painel de vendas
Em [Resultados](/painel): faturamento, pedidos, ticket médio e recorrência, por vendedor, função, região e período. Imprima em A4 ou exporte em CSV.
![Painel de vendas: faturamento, pedidos e recorrência](assets/img/treinamento/painel.jpg)

## Clientes sem compra
O painel mostra os clientes sem compra nas faixas de {{config:carteira.faixas_sem_compra}} dias e o alerta de recompra.
TXT, [
            ['Onde ver o faturamento por vendedor e período?', ['No Painel de vendas (Resultados)', 'Na Agenda', 'No Treinamento', 'Nas Mensagens prontas'], 0, 'O painel tem os filtros.'],
        ]],
        ['fi-vizinho', 'vizinho', 'Como trabalha o Financeiro', 'O que acontece com o seu pedido depois que você envia.', 5, $vizFinanceiro, [
            ['Como ajudar o seu pedido a ser aprovado rápido?', ['Cadastro completo e forma de pagamento certa', 'Mandar várias vezes', 'Ligar para a diretoria', 'Não preencher a observação'], 0, 'Cadastro e pagamento corretos evitam recusa.'],
            ['O pedido foi recusado. E agora?', ['Ler o motivo, ajustar com o cliente e enviar de novo', 'Desistir do cliente', 'Reclamar no grupo', 'Apagar o pedido'], 0, 'O motivo diz o que ajustar.'],
        ]],
    ]],
    // =====================================================================
    ['chave' => 'gestor', 'funcao' => 'gestor', 'titulo' => 'Gestão comercial', 'icone' => '📊',
     'vizinhos' => 'financeiro,cobranca,marketing',
     'descricao' => 'Para gestores: metas, carteiras, acompanhamento da equipe, campanhas e o treinamento do time.',
     'modulos' => [
        ['ge-resultados', 'setor', 'Resultados e metas', 'Painel, equipe de vendas e metas do mês.', 12, <<<'TXT'
## Objetivo
Saber todo dia como o mês está indo e agir antes do fim do mês.

## Resultados
Em [Resultados](/painel), as abas: Painel de vendas, Equipe de vendas, Acompanhamento e Metas.
![Equipe de vendas: meta da equipe, frentes e ranking](assets/img/treinamento/equipe-vendas.jpg)

## Metas (passo a passo, todo início de mês)
1. Abra [Metas](/metas).
2. "Copiar metas do mês anterior" e ajuste quem mudou.
3. Defina a meta da equipe (os setores que entram nela são escolhidos em Configurações).
![Metas: por pessoa e por função](assets/img/treinamento/metas.jpg)
As metas aparecem no Meu mês de cada pessoa, com o quanto falta por dia útil.

?? É dia 20 e a equipe está em 55% da meta. || Veja no Acompanhamento quem está abaixo, olhe a Recompra e a Reativação de cada carteira e combine com a equipe uma ação de 10 dias (campanha da semana, lista de recompra).
TXT, [
            ['Onde definir as metas do mês?', ['Em Resultados › Metas', 'Na Agenda', 'No catálogo', 'Em Comunicados'], 0, 'As metas alimentam o Meu mês.'],
            ['Atalho para começar as metas do mês:', ['Copiar metas do mês anterior', 'Apagar tudo', 'Pedir ao Marketing', 'Não tem'], 0, 'Copie e ajuste.'],
        ]],
        ['ge-equipe', 'setor', 'Acompanhamento e carteiras', 'Ligações, gravações, agenda de cada pessoa e a distribuição dos clientes.', 12, <<<'TXT'
## Acompanhamento
Em [Acompanhamento](/acompanhamento): ligações, gravações, WhatsApp, agenda e meta de cada pessoa, por período.
![Acompanhamento: o dia a dia de cada vendedora](assets/img/treinamento/acompanhamento.jpg)
Use para **ajudar**: ouvir uma ligação boa e mostrar para a equipe, ou ajudar quem está travado.

## Carteiras
Em [Carteiras](/carteiras): os clientes de cada vendedora e os sem vendedora. **Passar** move clientes de uma carteira para outra (quando alguém sai, entra ou muda de setor).
![Carteiras: cada carteira e os clientes sem vendedora](assets/img/treinamento/carteiras.jpg)

## Reativação
Clientes sem compra há {{config:carteira.dias_inatividade}} dias aparecem na Reativação e, no modo atual, continuam com a vendedora da carteira.

?? Uma vendedora vai sair de férias por 20 dias. || Não passe a carteira: combine quem atende os retornos dela (agende para a colega pela ficha) e acompanhe a Agenda. Passar carteira é para mudança definitiva.
TXT, [
            ['Para que serve o Acompanhamento?', ['Ajudar a equipe com base no dia a dia de cada um', 'Punir', 'Ver o catálogo', 'Nada'], 0, 'Ferramenta de ajuda e melhoria.'],
            ['Uma vendedora saiu da empresa. O que fazer com os clientes dela?', ['Passar a carteira para outra vendedora', 'Apagar os clientes', 'Deixar sem dono', 'Mandar para a Cobrança'], 0, 'Todo cliente tem um responsável.'],
        ]],
        ['ge-treinamento', 'setor', 'O treinamento do seu time', 'Acompanhar quem fez o quê e reiniciar o tutorial.', 6, <<<'TXT'
## Treinamento da equipe
Em [Treinamento da equipe](/treinamento/equipe): módulos concluídos, média das provas e quantas telas cada pessoa aprendeu com o One.
![Treinamento da equipe: progresso de cada pessoa](assets/img/treinamento/treinamento-equipe.jpg)
- Pessoa nova: confira na primeira semana se ela fez a Integração e a trilha do setor.
- Nota baixa num módulo: converse sobre o assunto; muitas vezes é dúvida real do dia a dia.
- **Reiniciar tutorial:** o One volta a mostrar o passo a passo das telas para a pessoa.
TXT, [
            ['Uma pessoa nova está perdida no sistema. Um recurso útil:', ['Reiniciar o tutorial das telas dela', 'Tirar o acesso', 'Nada', 'Mandar e-mail'], 0, 'O One volta a ensinar cada tela.'],
        ]],
        ['ge-vizinho', 'vizinho', 'Como trabalha a Gestão comercial', 'Metas, carteiras e acompanhamento.', 3, <<<'TXT'
## O que a gestão faz
Define as metas, distribui as carteiras, acompanha a equipe e as campanhas e cuida do treinamento do time.

## Para você
Dúvida sobre meta, carteira ou campanha? Fale com a gestão; mudanças de carteira ficam registradas.
TXT, [
            ['Quem define as metas e distribui as carteiras?', ['A gestão comercial', 'O Depósito', 'O cliente', 'Ninguém'], 0, 'Metas e carteiras são da gestão.'],
        ]],
    ]],
    // =====================================================================
    ['chave' => 'diretoria', 'funcao' => 'diretoria', 'titulo' => 'Diretoria', 'icone' => '🧭',
     'vizinhos' => 'televendas,financeiro,producao,marketing',
     'descricao' => 'Acompanhar a empresa pelos números e aprovar o que for pedido.',
     'modulos' => [
        ['di-numeros', 'setor', 'A empresa pelos números', 'Início da Diretoria, Resultados, avisos e aprovações.', 10, <<<'TXT'
## O Início da Diretoria
![O Início da Diretoria: faturado do mês, pendências e alertas](assets/img/treinamento/inicio-diretoria.jpg)
- **Faturado no mês** e a comparação com o mesmo ponto do mês passado.
- **Pedidos parados** na aprovação e o total **em cobrança**.
- Alertas de **produção** (matéria-prima acabando, reabastecer) e **compras**.

## Onde aprofundar
- [Resultados](/painel): vendas por vendedor, região e período.
- Produção, Compras e Cobrança, em modo leitura (👁).

## Os avisos que você recebe
Configuráveis em Configurações › Diretoria: etapa de produto novo e lançamento, estoque abaixo do mínimo, ordens de produção, metas batidas, problemas (pedido recusado, despacho sem item, cobrança para jurídico) e compras do Omie.
TXT, [
            ['Por que a Diretoria vê muitas telas com o olho 👁?', ['Porque acompanha sem mexer no trabalho dos setores', 'Por erro do sistema', 'Porque não tem acesso', 'Porque é proibido'], 0, 'Modo leitura: acompanha sem alterar.'],
            ['Onde escolher quais avisos a Diretoria recebe?', ['Em Configurações › Diretoria', 'No catálogo', 'Na Agenda', 'Não dá para escolher'], 0, 'Cada aviso liga e desliga.'],
        ]],
        ['di-config', 'setor', 'Regras que a Diretoria decide', 'Pedido mínimo, pagamento, descontos, metas e o que fica nas Configurações.', 8, <<<'TXT'
## Toda regra comercial é configurável
Nada fica "fixo no código": mudar não precisa de programador.
- Pedido mínimo por região (hoje):
{{pedido_minimo}}
- Condições de pagamento:
{{condicoes_pagamento}}
- Desconto máximo da vendedora sem aprovação: **{{config:pedidos.desconto_max_pct}}%**.
- Dias sem compra para a Reativação: **{{config:carteira.dias_inatividade}}**.
- Validade do orçamento: **{{config:orcamento.validade_dias}} dias**.

! Pendências de definição da empresa: tabela definitiva de pedido mínimo, condições e prazos definitivos. Quando decidirem, ajuste em Configurações e o treinamento mostra os valores novos sozinho.
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
    // =====================================================================
    ['chave' => 'admin', 'funcao' => 'admin', 'titulo' => 'Administração do One', 'icone' => '⚙️',
     'vizinhos' => '',
     'descricao' => 'Para quem administra: pessoas e acessos, configurações, integrações, comunicados e atualizações.',
     'modulos' => [
        ['ad-pessoas', 'setor', 'Pessoas, funções e acessos', 'Criar logins, ligar à função certa e dar os acessos.', 12, <<<'TXT'
## Criar o login de uma pessoa (passo a passo)
1. ⚙️ › [Pessoas e acessos](/usuarios) › **Novo**.
![Pessoas e acessos: a lista de logins](assets/img/treinamento/usuarios.jpg)
2. Nome, e-mail e **perfil** (o que pode ver e editar).
3. Marque a **função** (Televendas, Produção...): o treinamento, as filas, o foco e as regras seguem a função.
4. Vendedoras: o **código de vendedor** (link do catálogo) e o **usuário no Ligue Talk**.
5. Senha provisória: a pessoa troca no primeiro acesso. O One mostra o tutorial das telas para ela.

## Funções e acessos
- **Funções:** os setores da empresa. As regras usam funções, nunca nomes de pessoas.
- **Acessos:** a grade do que cada perfil vê e edita, aba por aba. Exceções por pessoa ficam na ficha dela.

## Quando alguém sai
Desative o login (não apague) e passe a carteira para outra vendedora em Carteiras.

?? Uma vendedora passou do Televendas para o Externo. || Troque a função dela (Externo) e confira o acesso ao app. A trilha de treinamento muda sozinha; reinicie o tutorial se quiser que o One mostre as telas novas.
TXT, [
            ['O que define o treinamento e as filas de uma pessoa?', ['A função marcada no login', 'O nome', 'A cor favorita', 'Nada'], 0, 'Tudo segue a função.'],
            ['Alguém saiu da empresa. O login:', ['Desativa (não apaga) e passa a carteira', 'Apaga tudo', 'Deixa ativo', 'Troca a senha para todos'], 0, 'O histórico fica preservado.'],
        ]],
        ['ad-config', 'setor', 'Configurações e listas', 'As regras do negócio, sem programador.', 10, <<<'TXT'
## Configurações
Em [Configurações](/configuracoes) ficam as regras: pedido mínimo, dias sem compra, condições de pagamento, horários do depósito, avisos da Diretoria, One (IA e horário de descanso: {{config:one.hora_descanso}}) e muito mais.
![Configurações: as regras do negócio por grupo](assets/img/treinamento/configuracoes.jpg)
Salve no fim de cada grupo. Toda alteração fica na **auditoria** (quem, o quê, quando).

## Listas
Em Configurações › Listas: segmentos, origens, motivos, categorias. Acrescente sem apagar o que já tem uso.

## Comunicados
Em [Comunicados](/comunicados): avisos para a empresa ou para setores; cada pessoa vê numa janela e você acompanha quem leu.
![Comunicados: escrever, escolher quem recebe e ver quem leu](assets/img/treinamento/comunicados.jpg)
TXT, [
            ['Onde mudar o horário em que o One avisa que é hora de descansar?', ['Em Configurações (One)', 'No código', 'No catálogo', 'Não dá'], 0, 'Configurável, como as outras regras.'],
            ['Toda alteração de configuração fica registrada em:', ['Auditoria', 'Lugar nenhum', 'No Instagram', 'No sino de todos'], 0, 'Quem, o quê e quando.'],
        ]],
        ['ad-integracoes', 'setor', 'Integrações, atualizações e backup', 'Omie, Ligue Talk, IA e como atualizar o One com segurança.', 12, <<<'TXT'
## Omie
Em [Integração Omie](/integracao): modo (demonstração ou real), teste de conexão e sincronização (produtos, preços, estoque, pedidos).
![Integração Omie: modo, última sincronização e testes](assets/img/treinamento/integracao.jpg)
O pedido aprovado vai ao Omie como orçamento na etapa inicial configurada.

## Ligue Talk e IA
Em ⚙️ Administração: o código da empresa e o token do Ligue Talk (com "Testar conexão") e a chave da IA do "Pergunte ao One". As chaves ficam num arquivo protegido do servidor e nunca aparecem de novo na tela.

## Atualizar o One (passo a passo)
1. Receba o pacote **ONE_Vx.y.zip**.
2. Faça o backup (a tela de Atualização mostra o último).
3. Extraia o pacote por cima, no servidor.
4. Abra o One: na primeira visita, ele faz backup e aplica sozinho as mudanças do banco.
5. A equipe vê as novidades numa janela.

! Nunca apague a pasta config/ nem o arquivo de segredos: ali ficam as senhas do banco e as chaves das integrações.
TXT, [
            ['Na primeira visita depois de extrair o pacote de atualização, o One:', ['Faz backup e aplica as mudanças do banco sozinho', 'Apaga os dados', 'Pede um programador', 'Nada'], 0, 'A atualização é automática e segura.'],
            ['Onde ficam as chaves das integrações?', ['Num arquivo protegido do servidor (nunca reaparecem na tela)', 'No catálogo', 'No Instagram', 'Na tela, para todos'], 0, 'Segurança das chaves.'],
        ]],
    ]],
];
