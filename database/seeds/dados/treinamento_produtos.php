<?php
/**
 * Treinamento (V13.2) — trilha "Conhecimento de produto", para os setores que vendem ou falam dos produtos.
 * funcao = null e "vizinhos" = as funções que fazem esta trilha.
 * {{produtos:Categoria}} monta a vitrine com os produtos atuais daquela linha (cadastro único de Produtos).
 */
return [
    ['chave' => 'produtos', 'funcao' => null, 'titulo' => 'Conhecimento de produto', 'icone' => '🌿',
     'vizinhos' => 'televendas,externo,prospeccao_reativacao,terceirizacao,marketing,social_media,gestor_marketing,ecommerce,diretoria',
     'descricao' => 'As linhas, como ler uma ficha, as diferenças que confundem o cliente e o que pode (e não pode) ser dito.',
     'modulos' => [
        ['pd-ficha', 'setor', 'Como ler a ficha de um produto', 'Porção, quantidade do nutriente, apresentação e cuidados.', 15, <<<'TXT'
## Objetivo
Responder com segurança as perguntas mais comuns do lojista, **direto da ficha**, sem inventar.

## Onde ficam as fichas
Na [Biblioteca de produtos](/academia/produtos): busque pelo nome, ingrediente ou necessidade ("sono", "digestão") e filtre pela apresentação.
![Biblioteca de produtos: busca, apresentações e temas](assets/img/treinamento/biblioteca.jpg)

## As abas da ficha
![A ficha do 8+ Magnésio: chamada, números da porção e as abas](assets/img/treinamento/ficha-produto.jpg)
1. **Conheça o produto:** o que é, os diferenciais e os temas (ex.: músculos, energia).
2. **Composição:** ingredientes e a tabela nutricional por porção.
3. **Uso e cuidados:** como consumir, conservação, público e restrições.
4. **No atendimento:** perguntas frequentes e falas prontas.
5. **Treinamento técnico** (em alguns produtos, como o Ômega 3): capítulos para aprofundar.
![A aba "No atendimento": perguntas frequentes e falas prontas](assets/img/treinamento/ficha-produto-atendimento.jpg)

## Os quatro números que você precisa saber ler
- **Porção:** a quantidade de uma dose (ex.: "1 g • 2 cápsulas"). **Uma porção pode ter mais de uma cápsula.**
- **Porções por embalagem:** 60 cápsulas com porção de 2 = 30 porções (30 dias tomando 1 porção ao dia).
- **Quantidade do nutriente por porção:** ex.: 350 mg de magnésio por porção (175 mg por cápsula).
- **%VD / %IDR:** quanto a porção representa da recomendação diária.

> Exemplo real: o 8+ Magnésio tem porção de 2 cápsulas com **350 mg de magnésio**; a embalagem de 60 cápsulas rende 30 porções.

## Mineral elementar × composto
O cliente compara "500 mg de citrato" com "350 mg de magnésio" e acha que o primeiro tem mais. Não é bem assim: **500 mg de citrato de magnésio** é o peso do composto; o **magnésio elementar** (o mineral de fato) é só uma parte disso. Compare sempre **a quantidade do mineral por porção**, que está na tabela nutricional.

## Cuidados e público
Leia sempre "Uso e cuidados": muitos produtos são **para maiores de 19 anos**, com contraindicação para gestantes, lactantes e crianças, e pedem orientação profissional para quem usa medicamentos (ex.: anticoagulantes no Ômega 3).

?? O lojista diz: "Esse aqui tem 60 cápsulas, então dura 60 dias." || Depende da porção. Se a porção é de 2 cápsulas por dia, são 30 porções, ou seja, 30 dias. Mostre a linha "porções por embalagem" na ficha.
?? "Esse citrato de magnésio tem 500 mg e o 8+ tem 350 mg, então o citrato é mais forte?" || Não necessariamente: 500 mg pode ser o peso do composto (citrato), não do mineral. Compare a quantidade de magnésio por porção na tabela nutricional das duas fichas.
TXT, [
            ['60 cápsulas com porção de 2 cápsulas rendem:', ['60 porções', '30 porções', '120 porções', '2 porções'], 1, '60 ÷ 2 = 30 porções.'],
            ['Para comparar dois magnésios, o certo é olhar:', ['O peso total do composto', 'A quantidade de magnésio (mineral) por porção', 'A cor do pote', 'O preço apenas'], 1, 'Mineral elementar por porção é o que conta.'],
            ['Onde ficam as perguntas frequentes e falas prontas de um produto?', ['Na aba "No atendimento" da ficha', 'Em Configurações', 'No Mural de posts', 'Na Agenda'], 0, 'A aba No atendimento traz FAQ e falas.'],
            ['A ficha diz "público: maiores de 19 anos". Uma cliente quer dar para o filho de 12. Você:', ['Libera meia dose', 'Explica que o produto não é indicado para essa idade e que o pediatra orienta', 'Diz que não tem problema', 'Indica outro sem ler'], 1, 'Siga o rótulo e encaminhe ao profissional.'],
        ]],
        ['pd-capsulas', 'setor', 'Linha de cápsulas', 'Vitaminas, minerais e associações: a maior linha da empresa.', 14, <<<'TXT'
## O que tem na linha
A linha de cápsulas é a maior da Perfeita Alquimia: vitaminas, minerais, colágenos, fibras, extratos e associações. Abaixo, os produtos atuais (clique para abrir a ficha):
{{produtos:Cápsulas}}

## Famílias que o cliente mais confunde
### Magnésios
Temos várias opções (8+ Magnésio, 5+ Magnésio, Citrato, Cloreto, Magnésio com Inositol...). **O mineral é o mesmo; mudam o composto, a quantidade por porção e os outros componentes** (ex.: o 8+ Magnésio traz taurina e L-treonina). Ajude a loja a ter 2 ou 3 opções que atendam perfis diferentes, em vez de todas.
### Colágenos
Colágeno hidrolisado, tipo I e II, tipo II, com vitaminas, com ácido hialurônico, Verisol. **Não são intercambiáveis:** cada tipo tem uma composição e uma proposta diferente, descrita na ficha. Pergunte o que o cliente final da loja procura e mostre a ficha certa.
### Fibras e "controle de saciedade"
Psyllium, Glucomannan, Farinha de berinjela: fibras com formas de uso diferentes (algumas pedem bastante água). Leia "Uso e cuidados" antes de explicar.

## Como montar a oferta de cápsulas para uma loja
1. Veja na ficha do cliente o que ele **já vende bem**.
2. Ofereça o **complementar** (a ficha do produto tem "relacionados").
3. Uma **novidade** por visita/ligação, com argumento da ficha.

! Não diga que um produto "emagrece", "cura dor" ou "trata" algo. Fale do que a ficha descreve: composição, quantidades, forma de uso.

?? A loja vende muito Colágeno Hidrolisado e pergunta se deve pegar o Colágeno Tipo II também. || São propostas diferentes: o tipo II tem outra composição e outro público, descritos na ficha. Mostre as duas fichas e pergunte se os clientes da loja procuram isso. Se sim, sugira começar com poucas unidades.
TXT, [
            ['Os magnésios da linha diferem em:', ['Nada, são iguais', 'Composto, quantidade por porção e outros componentes', 'Só na cor', 'Só no preço'], 1, 'Mesmo mineral, fórmulas diferentes.'],
            ['Colágeno hidrolisado e colágeno tipo II são:', ['O mesmo produto', 'Produtos com composição e proposta diferentes', 'Remédios', 'Chás'], 1, 'Cada tipo tem a sua ficha.'],
            ['Antes de explicar uma fibra como o Psyllium, leia:', ['Uso e cuidados (forma de uso e água)', 'Só o preço', 'O Instagram', 'Nada'], 0, 'A forma de uso faz diferença.'],
        ]],
        ['pd-chas', 'setor', 'Chás: a granel, em sachês e misturas', 'A diferença de preparo e de público entre as apresentações.', 12, <<<'TXT'
## Três coisas diferentes que o cliente chama de "chá"
1. **Chá a granel:** ervas soltas, preparadas com filtro ou coador. Ótimo para quem toma todo dia e para lojas a granel.
2. **Chá em sachês:** a erva já vem em sachês individuais; prático, bom para presente e para quem toma às vezes.
3. **Pó para preparo de bebida:** dissolve no líquido. **Não é infusão de ervas**: está na linha de Pós e bebidas.

> Exemplo real: Camomila em sachês: 1 sachê numa xícara, água entre 80°C e 90°C, infusão de 3 a 5 minutos.

## A granel
{{produtos:Chás a granel}}

## Em sachês
{{produtos:Chás em sachês}}

## Misturas
Os chás mistos (ex.: misto 30 ervas, Plano 30) têm **vários ingredientes**: leia a composição para responder alergias e restrições.

## Cuidados que valem para todos
- "Este produto não é um medicamento."
- Manter bem fechado, longe da luz e da umidade.
- Gestantes, lactantes e crianças: orientação profissional.

?? A loja quer "o chá de camomila", mas não sabe se pega a granel ou sachê. || Pergunte o perfil: cliente que toma todo dia e compra por peso → granel; quem quer praticidade ou presente → sachês. Muitas lojas vendem as duas.
?? O cliente final pergunta se o pó Vita Zen é um chá. || Não: é pó para preparo de bebida, que se dissolve. Chá é infusão de ervas. Mostre a ficha de cada um.
TXT, [
            ['Chá a granel é:', ['Ervas soltas preparadas com filtro ou coador', 'Pó que dissolve', 'Cápsula', 'Sachê individual'], 0, 'A granel = ervas soltas.'],
            ['Pó para preparo de bebida é chá?', ['Sim', 'Não: dissolve no líquido, não é infusão de ervas', 'Só o de morango', 'Depende da cor'], 1, 'É de outra linha (Pós e bebidas).'],
            ['Para responder sobre alergias num chá misto, olhe:', ['A composição na ficha', 'O nome', 'O preço', 'A embalagem por fora'], 0, 'Misturas têm vários ingredientes.'],
        ]],
        ['pd-pos-dose', 'setor', 'Pós, bebidas e dose única', 'Creatinas, colágenos em pó, shots e displays de dose única.', 10, <<<'TXT'
## Pós e bebidas
Produtos para preparar: creatina monohidratada, colágeno com vitaminas, dolomita, shots matinais, café fit e bebidas saborizadas.
{{produtos:Pós e bebidas}}

## Dose única (displays)
Sachês individuais vendidos em **display**: ótimos para o caixa da loja e para o cliente experimentar.
{{produtos:Dose única}}

## Como vender
- **Creatina:** veja na ficha a apresentação (100 g, 300 g, cápsulas) e a porção. Academias e lojas de suplementos giram mais.
- **Dose única:** sugira o display perto do caixa (compra por impulso) e para quem quer experimentar antes do pote.
- **Sabores:** confira os sabores disponíveis na ficha antes de prometer.

?? A loja nunca vendeu creatina e tem medo de encalhar. || Sugira a dose única com creatina (display) ou a embalagem menor primeiro, e combine um retorno em 30 dias para ver o giro.
TXT, [
            ['Onde o display de dose única costuma vender mais?', ['Perto do caixa', 'No estoque', 'Na vitrine de fora', 'Escondido'], 0, 'Compra por impulso e experimentação.'],
            ['Antes de prometer um sabor ao cliente:', ['Confira na ficha os sabores disponíveis', 'Prometa qualquer um', 'Pergunte ao Depósito', 'Invente'], 0, 'Os sabores estão no cadastro.'],
        ]],
        ['pd-gotas', 'setor', 'Gotas, líquidos, alimentos e especiarias', 'Colágenos líquidos, óleos, xaropes, farinhas e especiarias.', 10, <<<'TXT'
## Gotas e líquidos
{{produtos:Gotas e líquidos}}
Atenção à **porção em gotas ou ml**: compare sempre pela quantidade do nutriente por porção, como nas cápsulas.

## Alimentos e especiarias
Ingredientes para a cozinha e a rotina alimentar (cúrcuma, canela, gengibre, psyllium, farinha de berinjela).
{{produtos:Alimentos e especiarias}}

## Como vender
- Gotas: público que **não gosta de engolir cápsulas**.
- Especiarias: lojas a granel e empórios; sugira junto com chás.

?? O cliente final não consegue engolir cápsulas e quer colágeno. || Mostre os colágenos em gotas (ex.: Colágeno Hidrolisado Verisol 30 ml) e os em pó; compare a porção na ficha.
TXT, [
            ['Um bom público para produtos em gotas é:', ['Quem não gosta de engolir cápsulas', 'Só atletas', 'Ninguém', 'Só crianças'], 0, 'Gotas e pós são alternativas à cápsula.'],
            ['Para comparar dois produtos em gotas, compare:', ['A quantidade do nutriente por porção', 'O tamanho do frasco', 'A cor', 'O nome'], 0, 'Igual às cápsulas: por porção.'],
        ]],
        ['pd-regras', 'setor', 'O que pode e o que não pode ser dito', 'Alegações, Anvisa e como falar de saúde com responsabilidade.', 10, <<<'TXT'
## Por que isso é tão importante
Suplementos alimentares e alimentos **não são medicamentos**. Prometer cura ou tratamento é proibido pela legislação, coloca o cliente em risco e expõe a empresa (e a loja) a multas.

## Pode
- Falar da **composição** e das quantidades (está na ficha).
- Falar da **forma de uso**, conservação e cuidados do rótulo.
- Usar as descrições da ficha ("participa do metabolismo energético", quando estiver escrito lá).
- Dizer "para saber se é indicado para você, consulte um profissional de saúde".

## Não pode
- "Cura", "trata", "previne doença", "substitui remédio", "emagrece X kg".
- Indicar para gestantes, lactantes, crianças ou quem usa medicamentos sem orientação profissional.
- Inventar estudos, depoimentos ou resultados.
- Dar dose diferente da do rótulo.

## Na dúvida
Registre a pergunta na ficha do cliente e encaminhe para a **Responsável Técnica**. Resposta certa amanhã é melhor que resposta errada hoje.

?? O lojista pede uma frase para colocar na gôndola: "Magnésio que acaba com a câimbra". || Não use: é promessa de tratamento. Sugira algo da ficha, como "Magnésio, taurina e L-treonina: 350 mg de magnésio por porção", e peça ao Marketing um material aprovado.
?? A cliente diz que toma anticoagulante e quer Ômega 3. || A ficha diz que quem usa anticoagulantes precisa de orientação profissional antes do uso. Diga isso com cuidado e não indique a compra para ela sem essa orientação.
TXT, [
            ['Qual frase está permitida?', ['"Cura a ansiedade"', '"350 mg de magnésio por porção"', '"Substitui o remédio"', '"Emagrece 5 kg"'], 1, 'Composição e quantidades podem.'],
            ['Cliente usa medicamento e pergunta se pode tomar. Você:', ['Libera', 'Orienta procurar um profissional de saúde', 'Diz para parar o remédio', 'Indica dose dobrada'], 1, 'Medicamento + suplemento: orientação profissional.'],
            ['Dúvida técnica que você não sabe responder:', ['Inventa', 'Registra e encaminha à Responsável Técnica', 'Ignora', 'Procura no Google e repassa'], 1, 'Resposta certa amanhã é melhor que errada hoje.'],
        ]],
        ['pd-mix', 'setor', 'Montando o mix certo para cada loja', 'Perfil da loja, giro e como sugerir sem empurrar.', 10, <<<'TXT'
## Perfis de loja (segmentos)
- **Produtos naturais / empórios:** chás a granel e sachês, especiarias, cápsulas de fitoterápicos e minerais.
- **Suplementos / academias:** creatina, colágenos, magnésios, dose única, ômega 3.
- **Farmácias:** vitaminas e minerais conhecidos, colágenos, fibras, displays de caixa.

## Do que a loja já vende para o que falta
1. Na ficha do cliente, "O que mais compra" mostra o que gira.
2. Pergunte o que os clientes pedem e a loja não tem.
3. Sugira **o complementar** (ficha → relacionados) e **uma** novidade.
4. Comece pequeno no item novo e marque o retorno para ver o giro.

## Giro antes de volume
Loja com produto encalhado não recompra. Melhor vender menos e repor sempre do que lotar a prateleira uma vez.

?? Uma farmácia nova quer "um pouco de tudo". || Monte um mix inicial enxuto com os mais vendidos do segmento farmácia (vitaminas, colágeno, magnésio, um display de dose única) e agende o retorno em 20 a 30 dias para ajustar pelo giro.
TXT, [
            ['Para uma loja de suplementos, um bom começo é:', ['Só especiarias', 'Creatina, colágenos, magnésios e dose única', 'Só chás a granel', 'Nada'], 1, 'Siga o perfil da loja.'],
            ['Item novo numa loja: melhor estratégia?', ['Lotar a prateleira', 'Começar pequeno e voltar para ver o giro', 'Não oferecer', 'Dar de graça'], 1, 'Giro antes de volume.'],
        ]],
    ]],
];
