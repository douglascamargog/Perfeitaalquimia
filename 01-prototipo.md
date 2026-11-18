# 01 — O protótipo do One (Fase 0) e tudo o que foi decidido até a V17.9

> Documento de passagem para uma conversa nova que vai trabalhar direto na pasta do sistema.
> O One está em **produção na V17.9**: hospedado na HostGator, com os dados reais da empresa.
> Este texto explica **por que** as coisas são como são. O **como** está no código e no `CLAUDE.md`.
> Em caso de conflito, vale o que o usuário pedir agora. Na dúvida, pergunte antes de mudar uma regra.

---

## 1. O que foi o protótipo, para quem e o que a diretoria decidiu

### Objetivo
- O protótipo começou em **12/11/2026**, como **Fase 0**.
- Era uma demonstração para a **diretoria (os 2 sócios)**. O objetivo era aprovar duas coisas: a troca do ERP Nota10 pelo **Omie** e a criação de um sistema próprio.
- Esse sistema próprio substituiria:
  - o **Leads2b** (CRM);
  - os **dois catálogos de pedido**: o Catálogo Mobile e o dos vendedores externos.

### Como foi construído
- PHP 8 + MySQL, pensado desde o início para a hospedagem compartilhada da HostGator: sem Node e sem processo rodando o tempo todo; o que roda sozinho é cron.
- O Omie era **simulado** (`OmieFake`), atrás de uma camada que se troca só por configuração. A ideia era mostrar o fluxo completo sem ter o Omie contratado.
- Reaproveitou o visual e a lógica da **Carteira Comercial V4** (`referencias/dashboard.html`): funil, drawer do cliente, mapa, filtros e linha do tempo. As 499 lojas da Grande Porto Alegre viraram dado inicial.
- Reaproveitou o conteúdo da **Academia Comercial** (`referencias/Central_TelevendasPA_V2.html`): cerca de 159 produtos, falas, objeções e condições.

### Primeiras urgências do usuário
Ele precisava de duas coisas funcionando logo:
1. a **Central do Televendas** (conteúdo de treino e consulta);
2. a **prospecção especial da Black Friday**.

### O que aconteceu depois
- Em **13/11** a Fase 0 ficou pronta. O usuário escolheu seguir aperfeiçoando ("Veja o que podemos aperfeiçoar"), e o protótipo passou a ser usado de verdade pela equipe.
- Em **17/11** a regra do projeto mudou: o One é o **sistema definitivo**, na HostGator e integrado ao Omie real.
  - Os dados simulados (`OmieFake`, `TrayFake`, seeds `_demo`) ficaram só para testes automáticos e instalação de teste.
- Houve um **teste de carga com 30 funcionários simultâneos**, porque o usuário perguntou se a HostGator aguentaria. Ele avalia o **plano Turbo** (NVMe) ou um servidor próprio no futuro.

### O que a diretoria quer ver (decidido depois, com o perfil "Diretoria")
- **Os 2 sócios só veem e aprovam.** Um deles **também vende** e tem carteira.
- A diretoria recebe **aviso no sino** apenas de:
  - etapas do Desenvolvimento de Produto;
  - pedidos confirmados (para o sócio que vende);
  - matéria-prima, embalagem ou estoque acabando;
  - meta batida;
  - ordem de produção criada;
  - problemas.
- **Ordem de produção finalizada** avisa apenas a diretoria.

---

## 2. Decisões de negócio e de visual (com o motivo)

### Nome, mascote e versões
- **Nome: "One"**, para o sistema e para o mascote. Roda num subdomínio do site da empresa.
  - **Descartado: "Elo".** O usuário gostou, mas o nome precisava funcionar também como mascote ajudante.
  - O mascote veio do usuário: primeiro um zip, depois *sprite sheets*.
- **Humores do mascote:**
  - dorme depois das **17h45**, com mensagem de descanso;
  - aparece com **balões** no aniversário da pessoa;
  - fica **bravo ou furioso** quando há pendência séria.
  - **Motivo:** deixar o sistema "vivo" e chamar atenção só quando importa.
- **Versões `ONE_Vx.y`:** mudança pequena muda a decimal; mudança grande muda o número inteiro.
  - As notas de versão são escritas **para a equipe comum**, sem termo técnico. O recado técnico vai no campo `admin`.
  - Todo mundo é avisado (sino + janela "Novidades", uma vez por pessoa).
  - **Motivo:** o usuário quer publicar versões sem atrapalhar quem está usando e sem perder dados. Por isso as migrations só acrescentam e o backup é automático na primeira visita.

### Visual
- **Cores padrão: petróleo `#025150` + menta `#5cd4b6`**, as cores da empresa.
  - Elas **substituíram o lima `#caff4d`** da Carteira antiga. A seção 9 do `CLAUDE.md` ainda cita o lima: está desatualizada.
- **Cada pessoa pode personalizar as cores** do seu One.
- **Modo escuro** com botão no topo. As cores escuras são geradas por `ferramentas/gerar_tema_escuro.py`, que deve rodar sempre que o CSS mudar.
- O **catálogo** mantém a identidade da Perfeita Alquimia, mas muda por **campanha**, com banners e cores fáceis de trocar.
- Pedidos de usabilidade que viraram regra:
  - **salvar não pode jogar a página para o topo**;
  - **botões de salvar não podem ficar cortados**;
  - **a foto da pessoa aparece em todo lugar** em que ela é responsável (vendedor, responsável pela tarefa, etc.).
- **Página da empresa:**
  - expansível para página inteira;
  - linha do tempo com print, nota e agendamento;
  - **foi refeita** porque ficou "pobre demais" e depois "grande demais".
- **Mapa da Carteira:** usa o **Google Maps** (geocodificação exata, com "Ajustar no mapa" na ficha).
  - **Descartado:** o mapa anterior, impreciso. O usuário achou "horrível".
  - Vendas por estado do e-commerce usam o **mapa SVG do Brasil**.
- **Clima real** na página inicial. A primeira versão da página foi considerada "sem graça", e o clima chegou a aparecer errado: já foi corrigido.

### Menu e acessos
- **Todo mundo vê todas as abas do menu.** O acesso é por aba: **ver** ou **editar**. As telas que a pessoa não pode abrir mostram **cadeado**.
  - **Motivo:** transparência; cada um sabe o que existe.
- **Organização do menu:**
  - **Comercial** tem um seletor de setor;
  - **Reativação** fica dentro do setor do vendedor;
  - **Estoque** fica dentro de Produção;
  - **Desenvolvimento** fica dentro de Produtos;
  - **Pessoas** fica na barra do topo;
  - **Administração** é um ícone de engrenagem;
  - **Academia passou a se chamar Treinamento**.
- **A página inicial do admin mostra a área da pessoa.** O admin é do Marketing, então vê o Marketing, e não as metas dos vendedores.
- **Fim da senha compartilhada:** cada pessoa tem o seu login.

### Perfil e pessoas
- **Perfil no estilo de rede social:**
  - sobre, hobbies, favoritos, família/pets, capa e links de redes sociais;
  - **sem postagens**;
  - vendas aparecem **só como selos**, nunca como números.
- **Cada pessoa edita os próprios dados.** O seletor de "área que acompanha" é **só do admin**.
- **Apelido:** só a própria pessoa vê. Aniversários mostram o **nome real**.
- **Aniversário:** a decoração aparece para o aniversariante, e há um **mural** para todos.
- **Comunicados** podem ser dirigidos a setores.
- **Fotos de quem está online** ficam visíveis para toda a equipe.

### Setores e carteiras
- **Televendas, Externo, Terceirização e Prospecção/Reativação** são setores **separados**, cada um com a **sua carteira**.
  - Cada pessoa vê **só o próprio setor**.
  - **A gestora do Televendas também é vendedora**, com carteira própria. Isso foi corrigido duas vezes: ela não pode sumir da equipe nem ver carteiras de outros setores.
- **Quando um vendedor sai, os clientes ficam parados** (aguardando redistribuição). Não são distribuídos automaticamente.
- **Quem mexe nas carteiras:** admin, gestor e o gestor do próprio setor.
- **Importação inicial:** só as 499 lojas, colocadas na **fila da Prospecção**.

### Pedidos, catálogo e campanhas
- **Orçamento por link de WhatsApp:**
  - **sem senha** para o cliente;
  - o link pode ser enviado como **imagem**;
  - vira pedido sozinho quando o cliente aceita.
- **Modelos de pedido** de cada vendedor, além dos modelos da gestão.
- **Desconto do vendedor tem limite**; acima dele, precisa de **aprovação**.
- **Campanhas:**
  - tipos: **% de desconto por faixa**, **brinde**, **preço promocional** e **frete grátis**;
  - o público é escolhido em cada campanha;
  - **cliente em duas campanhas ganha o melhor de cada tipo**.
- **Sem kits:** venda por unidade.
- Título em atraso bloqueia o boleto (só Pix ou cartão). O pedido mínimo é por UF.

### Omie (direção combinada)
- **Vêm do Omie:** notas fiscais, códigos dos vendedores e condições de pagamento.
- **A nota é emitida automaticamente antes de o pedido chegar ao Depósito.**
- **No dia da integração real, apagar do One:** clientes, pedidos, leads e carteiras. Depois, puxar tudo do Omie.
  - **Motivo:** o Omie é a fonte da verdade, e os dados atuais são de teste ou provisórios.
- **Os produtos do One se ligam aos do Omie pelo mesmo código.**
- **Compras lançadas no Omie entram sozinhas**, com aviso para o comprador, a Produção e a Diretoria.
  - Financeiro e Qualidade também podem lançar compras.

### Produção, Depósito e Qualidade
- **Ordem de produção (OP):**
  - segue o modelo **PLA08.1**, melhorado para impressão;
  - registra o lote de cada matéria-prima;
  - **só a Responsável Técnica revisa e aprova**.
- A OP **lista tudo o que foi usado**: pote, lacre, cápsulas, tampa, rótulo.
  - A prévia mostra o **máximo de unidades possível**, considerando a **perda medida depois da primeira produção**.
- **Estoque de rótulos** de terceirização é **baixado na OP** e aparece também em Produção.
- **Tabela nutricional:** digitada à mão.
- **Qualidade:**
  - compra matéria-prima e rótulos;
  - faz cotações e **escolhe quem as aprova**;
  - é a **função "coringa"** do Desenvolvimento de Produto.
- **Depósito / e-commerce:**
  - o pedido entra na fila de separação quando fica **"Pago / a enviar"**;
  - horários de corte padrão: **Mercado Livre 13h, Shopee 11h, Amazon 12h**;
  - aba ou filtro separado para **lojistas, e-commerce e terceirização**;
  - as **caixas usadas** são registradas e baixadas do estoque;
  - **a validade não é pedida na separação**, porque vem do lote. Pedir foi descartado por ser trabalho repetido.

### Desenvolvimento de Produto ("Produtos novos")
- **11 etapas:**
  1. briefing;
  2. formulação;
  3. rotulagem;
  4. código de barras / NCM;
  5. arte do rótulo;
  6. compra da matéria-prima;
  7. gráfica;
  8. aprovação da prova;
  9. recebimento dos rótulos;
  10. lote piloto;
  11. lançamento.
- **Formulação, rotulagem e criação do rótulo podem correr ao mesmo tempo.**
- **A prova é aprovada pelo Marketing e pela Qualidade.**
- **O mesmo fluxo serve para a terceirização.**
- **Visual de jogo em 3D (Three.js)** com 3 embalagens: pote, frasco e caixinha.
  - O formato do pote de cápsulas é o real.
  - Pote preto ou branco, com tampas de várias cores.
  - **A arte do rótulo envolve o produto 3D.**
- **Fórmulas (área da Responsável Técnica):** a **fórmula aprovada vira a ficha técnica** do produto, usada no custo e na Produção.

### Terceirização
- **Existem os dois casos:**
  - produto da fábrica com a marca do cliente;
  - fórmula própria do cliente.
- Tem **área própria no menu**.
- O **ranking da equipe de vendas é visível para todos**.

### Marketing
- O Marketing tem **4 funções**:
  - social media;
  - gestor de marketing, que também cria os rótulos (é o usuário que mantém o One);
  - assistente de e-commerce;
  - Qualidade, como apoio.
- **Instagram** (feed, reels, stories). As **postagens precisam de aprovação**.
- A Marketing foi redesenhada:
  - página inicial;
  - mini calendário;
  - pedidos ao Marketing;
  - lista de tarefas.
- **Descartados por enquanto:** a aba de **planejamento de Marketing** e a página de **leads do site**.
- **Retirados de "Pedir ao Marketing":** os 2 assuntos de e-commerce.

### E-commerce (Tray)
- A Tray começou simulada e hoje está conectada pela **⚙️ Administração › Tray**.
- O cron dos pedidos do e-commerce precisa existir na HostGator. O usuário já esqueceu de criá-lo uma vez.
- **A venda só conta com pagamento confirmado**, igual à "Performance da loja" da Tray. O usuário comparou com prints da Tray.
- **PAR = Particular.**
- **Nenhum dado pessoal do comprador** fica no One: só cidade e UF.

### Assistente One e IA
- O assistente aparece **só quando é importante**: puxa conversa em cada tela, ajuda na tela, comemora e faz ações diretas.
- **Dicas em todos os setores.** Antes eram só no Comercial, e o usuário pediu para mudar.
- **"Pergunte ao One"** usa o Claude.
  - A chave é cadastrada em ⚙️ Administração. Não existe campo no `config.php` para isso: o usuário procurou ali e não achou.
- **Especialistas por setor:** Vendas, Prospecção, Marketing, Formulação, Cobrança e Terceirização.
  - São editáveis em ⚙️ Administração › Especialistas do One.
  - **Só consultam**, sempre com o acesso da pessoa.
- **O chat confundiu o usuário uma vez** e foi simplificado. Mantenha-o simples.

### Treinamento
- **Por setor**, com prints, processos reais, casos e conhecimento de produto, escrito **"com o que existe"**: nada de prometer função que não existe.
- O **One dá um tutorial em cada tela** até a pessoa confirmar que entendeu.
- **Todo o conteúdo do Leads2b foi removido**; no lugar entrou "Como usar o One".
- **Descartado:** o treinamento curto, que o usuário achou "muito superficial". Foi refeito.

### Acompanhamento
- **Vendedores:** meta × realizado, carteira e funil, agenda, WhatsApp.
- **Cobrança:**
  - valor recuperado;
  - tentativas e promessas;
  - carteira em atraso;
  - ligações.
- A Cobrança tem **funil próprio** e uma **busca de contatos novos por CNPJ com IA**.

### Telefonia (Ligue Talk)
- O usuário tinha a documentação da API.
- Há um **ícone de telefone para discar qualquer número** pelo Ligue Talk.
- O modo manual continua como plano B.

---

## 3. Regras da empresa aprendidas (podem não estar escritas no sistema)

- **Venda B2B por CNPJ:** lojas de produtos naturais, suplementos e farmácias. Os 4 segmentos padrão vieram de 9 grafias diferentes na Carteira antiga.
- **Tabelas de preço:** Lojista, Distribuidor e Terceirização. O catálogo público mostra **sem preço e sem estoque**.
- **Cada setor comercial tem a sua carteira**, e uma pessoa não enxerga a carteira de outro setor. A **exceção** é quem gerencia (admin, gestor, diretoria).
- **Cliente sem compra há X dias** vai para Prospecção/Reativação. Na prática, a **reativação acontece dentro do setor do vendedor**.
- **Pessoas mudam; funções ficam.** Nada no código pode usar nome de funcionário.
  - Funções existentes:
    - Televendas;
    - Externo;
    - Prospecção e Reativação;
    - Terceirização e Marca Própria;
    - Marketing (social media, gestor, e-commerce);
    - Produção;
    - Depósito;
    - Qualidade;
    - **Responsável Técnica**;
    - Cobrança;
    - Financeiro;
    - Diretoria.
- **A Responsável Técnica é a única** que aprova OP e fórmula. Alegações de produto passam por ela, e **suplemento não promete cura**.
- **A Qualidade compra** matéria-prima e rótulos e faz as cotações.
- **Financeiro aprova pedidos e crédito.** Também pode lançar compras.
- **Documentos de produto são visíveis para todos.**
- **Cobrança é firme e respeitosa:** sem constranger, sem expor a dívida, em horário comercial (CDC, art. 42).
- **Os arquivos de rótulo são pesados.** O tratamento do tamanho deles ficou para depois.
- **O Omie manda** em produtos, preços, estoque, faturamento, financeiro e dados fiscais. **O One manda** em:
  - prospecção e funil;
  - histórico e tarefas;
  - carteira e vendedor responsável;
  - catálogo, metas e treinamento.

---

## 4. Ideias e pedidos que ficaram para depois

1. **Conferir o `OmieClient` com o Omie real** (nomes exatos de métodos e campos) e criar a tela **⚙️ Administração › Omie**, com "Testar conexão". Hoje o Omie liga pelo `config.php`.
2. **O dia da integração com o Omie:** apagar clientes, pedidos, leads e carteiras do One, importar do Omie e ligar os produtos pelo código. Pedir confirmação ao usuário antes, com backup.
3. **Base real de CNPJs da Receita Federal** e a **lista de CNAEs** dos clientes atuais. Hoje existe só a amostra do RS.
4. **Condições de pagamento, descontos, prazos e pedido mínimo por região** definitivos. Hoje são configuráveis, com valores provisórios.
5. **Importação de carteira por XML:** a origem do arquivo ainda é desconhecida.
6. **Homologação do aplicativo na Tray:** prazo de 120 dias a partir do envio das credenciais.
7. **Anexos nos testes de fórmula.**
8. **Gamificação:** por enquanto, só a estrutura.
9. **Aba de planejamento de Marketing** e **página de leads do site**: só se o usuário pedir.
10. **Tamanho dos arquivos de rótulo:** compressão ou limite.
11. **Plano Turbo da HostGator ou servidor próprio.** O código continua portável.
12. **Ícone do PWA (app do externo) com o mascote novo.**

---

## 5. Coisas que o usuário pode esquecer de mencionar

- **Quem mantém o One não é programador.** Explique em português simples, faça uma pergunta por vez, e o código deve ser comentado e fácil de editar.
- **Nunca peça chave ou senha no chat.** Toda chave entra pelas telas da ⚙️ Administração e fica em `config/segredos.php`.
- **Cada entrega precisa de:**
  - versão nova no topo de `app/versoes.php`;
  - pacote `bash ferramentas/gerar_pacote.sh --atualizacao`, que gera `pacote/ONE_Vx.y.zip`;
  - teste do banco do zero **e** da atualização sobre um banco com dados;
  - nada de editar migration antiga.
  - Ao entregar, informe só o nome da versão e o resumo das mudanças.
- **Crons que precisam existir na HostGator:**
  - sincronização do Omie;
  - fila de reativação;
  - `pedidos_ecommerce.php` (a cada 15 min);
  - `geocodificar.php`.
  - Se algo "não atualiza sozinho", o primeiro suspeito é o cron.
- **O que fazer ao mudar o visual:**
  - rodar `gerar_tema_escuro.py` depois de mudar o CSS;
  - conferir os dois temas;
  - conferir as cores personalizadas por usuário.
- **Tutoriais e treinamento:** ao mudar uma tela, atualize o tutorial do One daquela tela e o treinamento do setor. Eles descrevem "o que existe".
- **Ao adicionar uma tela:** ela entra na matriz de acesso (ver/editar). Quem não tem acesso vê o cadeado.
- **Desatualizado no `CLAUDE.md`:**
  - a cor lima (seção 9): as cores atuais são petróleo + menta;
  - o nome "Academia": hoje é Treinamento.
- **Problemas que já voltaram depois de corrigidos:** dados simulados que contradiziam a tela e o gráfico da equipe de vendas.
  - Sempre que mexer em vendas ou e-commerce, compare com a fonte real: Omie, ou a "Performance da loja" da Tray.
- **LGPD:** nada de dado de cliente em URL pública, em log aberto ou no texto guardado das perguntas ao One (só a contagem de uso). O interruptor `one.ia_dados_clientes` controla se a IA pode ler dados de clientes.
- **Testes:** `bash tests/rodar.sh` recria um banco de teste. **Nunca rode os testes apontando para o banco de produção.**
