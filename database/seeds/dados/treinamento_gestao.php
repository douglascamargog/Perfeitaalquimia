<?php
/**
 * Treinamento (V13.0) — trilhas da Cobrança, do Financeiro e da Diretoria.
 * Formato igual ao de treinamento_integracao.php. Cada trilha tem um módulo "vizinho": como o setor trabalha,
 * para os setores que têm contato com ele.
 */

// ---------- texto usado em mais de uma trilha ----------
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
