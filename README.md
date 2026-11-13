# One — sistema unificado da Perfeita Alquimia (Fase 0 · protótipo)

**One** é o nome do sistema e do mascote ajudante: um caldeirão de alquimista com um broto
(arquivos em `public/assets/img/one/`, 6 expressões × 2 variações). O One aparece no login, no Início
(resumo do dia), nas telas de erro e no botão flutuante, que mostra dicas conforme os dados de cada pessoa
(`app/Services/OneAssistente.php`).


Carteira e CRM, catálogo B2B com pedido, app do externo, painel de vendas, Academia e produção num só sistema,
integrado ao Omie (no protótipo: **dados simulados**). Especificação completa em `CLAUDE.md`.

PHP 8 + MySQL/MariaDB, HTML/CSS/JS puro. Sem Composer e sem etapa de build.

## Rodar no seu computador

> Para a busca real de contatos com IA, rode também `composer install` (cria a pasta `vendor/`).

1. Crie um banco MySQL/MariaDB vazio (ex.: `perfeita`, utf8mb4).
2. Copie `config/config.example.php` para `config/config.php` e preencha os dados do banco.
3. Crie as tabelas e os dados de demonstração:
   ```
   php database/migrar.php
   ```
4. Suba o servidor e abra http://localhost:8000:
   ```
   php -S localhost:8000 -t public public/index.php
   ```

## Publicar na HostGator (sem terminal)

1. Envie **a pasta inteira** do projeto para `public_html` (ou para uma subpasta). O `.htaccess` da raiz manda tudo para `/public`;
   as pastas `app/`, `config/`, `database/` e `storage/` não ficam acessíveis pela web.
   (Se puder apontar o domínio direto para a pasta `public/`, melhor ainda.)
2. Crie o banco e o usuário MySQL no cPanel.
3. Crie `config/config.php` (a partir do exemplo) com os dados do banco e um `instalar_token` qualquer (ex.: uma frase longa).
4. Acesse `https://seu-dominio/instalar`, digite o token e clique em **Instalar / atualizar**.
5. Depois de instalar, **apague o `instalar_token`** do config.php.
6. A pasta `storage/` precisa de permissão de escrita.

## Dados da Carteira (as 499 lojas)

As 499 lojas da Carteira Comercial V4 são lidas de `referencias/dashboard.html` na instalação.
Esse arquivo **não vai para o git** (dados de clientes — LGPD): envie a pasta `referencias/` por FTP junto com o projeto
antes de rodar a instalação. Sem ele, o sistema instala normalmente, só sem as lojas.

Da mesma forma, a **Academia** (161 produtos, trilha, aulas e as imagens/vídeos) é lida de
`referencias/Central_TelevendasPA_V2.html` na instalação. As imagens vão para `public/media/academia/` (fora do git).
Os textos que eram fixos na Central (página da empresa, mídias do marketing, guia de atendimento) estão em
`database/seeds/dados/academia_paginas.php`.

Para trazer o trabalho que a equipe fez na Carteira V4 (etapas, contatos, histórico), use **Carteira › Importar**
com o "Backup JSON" exportado pela V4.

## Meu mês e meta da equipe

- Cada pessoa cadastra a própria meta, registra as vendas e vê quantidade de vendas, ticket médio, projeção do dia e do mês.
- O **gestor** (perfil Gestor = supervisor de vendas) cadastra a **meta da equipe** e lança o **total vendido pela equipe até o momento**;
  o sistema recalcula sozinho a projeção do dia (ritmo), a projeção do mês e o necessário por dia.
- Regra da projeção: vendido ÷ dias de trabalho decorridos × dias de trabalho do mês (o dia de referência conta como completo).

## Cobrança e busca de contatos com IA

- **Cobrança** (menu Comercial; perfis Financeiro, Gestor e Admin): clientes com títulos em atraso, em um **funil próprio**
  (Título vencido → Tentativa de contato → Sem contato — buscar contatos → Em negociação → Promessa de pagamento → Pago / Acordo / Jurídico / Perda).
  As etapas ficam em Configurações › Listas › Funil de cobrança.
- Os casos entram pelo botão **Ler títulos vencidos do ERP** (simulado no protótipo; Omie depois), pelo cron, ou pelo vendedor na ficha do
  cliente (**Enviar para cobrança**). Vão para quem tem a função **Cobrança** (ou para o Financeiro).
- Depois de X tentativas sem resposta (configurável) o caso vai para **Sem contato**. Promessa de pagamento cria uma tarefa para o dia;
  promessa vencida volta para tentativa. Todos os títulos pagos encerram o caso como Pago.
- **Buscar contatos com IA** (ficha do cliente e caso de cobrança): consulta a Receita (grátis) e pesquisa na web
  telefone, WhatsApp, e-mail, Instagram e site. Mostra **sugestões com a fonte**; só grava o que a pessoa marcar
  (o valor antigo fica na linha do tempo e na auditoria). Modo demonstração até o admin ligar a busca real (veja LEIA-ME-INSTALACAO, 9b).
- A busca real usa o SDK oficial da Anthropic (`composer.json`). A pasta `vendor/` não vai para o git: o `gerar_pacote.sh` instala e coloca no zip.

## Catálogo B2B e pedidos

- **Catálogo** em `/catalogo` (para o cliente, no celular): visitante vê produtos sem preço; o cliente entra com o **CNPJ + senha**
  e vê a **tabela dele** (Lojista / Distribuidor / Terceirização). Primeiro acesso ou senha esquecida: código por e-mail
  ou gerado pelo vendedor na ficha do cliente ("Gerar acesso ao catálogo") e enviado pelo WhatsApp.
- **Carrinho** com o pedido mínimo da UF/região (Configurações), formas de pagamento (boleto bloqueado para quem está em cobrança),
  "Meus pedidos" e "Repetir pedido". **Link do vendedor**: `/catalogo/v/{código do vendedor}` (o código fica no cadastro do usuário).
- **Pedidos** (equipe): lista, novo pedido pela ficha do cliente, página do pedido para imprimir. Todo pedido vai ao ERP como orçamento
  (o mesmo pedido nunca entra duas vezes) e passa pela **Aprovação** do Financeiro, com o quadro de crédito do cliente.
  O faturamento volta do ERP pelo cron e atualiza a última compra do cliente. Na demonstração: botão "Simular faturamento".
- **Produtos** (Marketing): o mesmo cadastro da Academia; preços e estoque vêm do ERP; aqui se escolhe o que aparece no catálogo.
- Demonstração: 5 clientes com acesso ao catálogo (senha `Demo@2026`; os CNPJs aparecem no relatório da instalação).

## App do vendedor externo (One Vendas)

- Endereço: `/app` (menu Comercial › App do externo). Abre no celular e **instala como aplicativo**: no Android, menu ⋮ › "Instalar app";
  no iPhone, Compartilhar › "Adicionar à Tela de Início". Entra com o mesmo usuário do sistema.
- Guarda no celular a **carteira da pessoa, os produtos, os preços da tabela de cada cliente** e as fotos. Depois funciona **sem internet**:
  novo pedido (mesmas regras: mínimo da região, boleto bloqueado, condição com valor mínimo) e "Registrar visita".
- Tudo vai para uma fila e é enviado sozinho quando a internet volta (ou no botão "Enviar agora"). Cada item tem um código único:
  reenviar **nunca duplica**. Se o servidor recusar (ex.: preço mudou e ficou abaixo do mínimo), o item fica "com erro" com o motivo,
  e dá para corrigir e reenviar. A tela Pedidos mostra: aguardando envio → enviado → aprovado → faturado (com NF) / recusado.
- Precisa de **HTTPS** no subdomínio (o cPanel da HostGator emite o certificado grátis: SSL/TLS Status › Run AutoSSL).

## Painel de vendas, metas e recompra

- **Painel de vendas** (Gestão; Gestor e Financeiro): faturamento dos **pedidos faturados** (bate com o ERP), pedidos, ticket médio,
  clientes, recorrência, comparação com o período anterior, gráficos (mês a mês com a meta da equipe, UF, origem), vendedores e funções
  com a meta do mês, top clientes e produtos, clientes sem compra há 30/60/90 dias. Filtros: período, vendedor, função, região, UF, origem.
  Imprimir e CSV.
- **Metas** (Gestor): meta do mês por vendedor e por função, faturado, projeção pelo ritmo, quanto falta por dia, histórico de 6 meses,
  "copiar metas do mês anterior". A meta da equipe continua no Meu mês.
- **Recompra** (Comercial): clientes que passaram do próprio intervalo médio de compra (recalculado a cada faturamento), com os produtos
  que costumam comprar, WhatsApp com mensagem pronta, "Novo pedido (repetir)" e "Agendar retorno". O vendedor vê só a carteira dele.

## Prospecção, mensagens prontas e leads do site

- **Prospecção** (`/prospeccao`, Comercial): tudo o que ainda não compra num lugar só.
  - **Para abordar**: fila única dos leads (listas importadas, base de CNPJs, site, anúncios, indicação), "qual origem funciona melhor"
    (conversão por origem), funil dos leads e as abordagens da equipe na semana. Vendedor vê só a carteira dele.
  - **Encontrar empresas**: base pública de CNPJs filtrada pelos CNAEs do nosso público (Configurações › Prospecção), por cidade,
    bairro, atividade e data de abertura, com a cobertura de cada cidade. Marque e envie para o funil (não duplica CNPJ).
    No protótipo é uma **amostra simulada do RS**; a base real da Receita entra na mesma tabela numa fase posterior.
  - A antiga "campanha em destaque" virou parte da Prospecção. As 499 lojas da Carteira V4 estão na origem
    "Lista de prospecção — Grande Porto Alegre".
- **Mensagens prontas** (`/mensagens`, Conteúdo): modelos de WhatsApp com variáveis (`{nome}`, `{link_vendedor}`, `{dias_sem_compra}`...).
  Na ficha do cliente, escolha o modelo: o texto vem preenchido e "Abrir no WhatsApp" registra o contato na linha do tempo.
- **Leads do site** (`/leads`, Marketing): formulários do site e dos anúncios entram sozinhos no funil, com as UTMs, para a função de
  novos leads, com tarefa de retorno para hoje. Tabela por campanha (leads → clientes → faturado).
  - Página pronta para anúncios: `/seja-revendedor?utm_source=instagram&utm_medium=cpc&utm_campaign=...`
  - Formulário no site da empresa: código em **Leads do site › Instalar no site** (envia para `/leads/receber`).
  - Proteções: campo escondido anti-robô, limite por hora e, se quiser, só os sites autorizados (Configurações › Captura de leads).
    O IP nunca é gravado (só uma impressão digital).

## Produção e matérias-primas

- **Matérias-primas** (`/producao/materias`): estoque, mínimo, fornecedor e último custo. O estoque só muda por
  **entrada de compra**, **ajuste de inventário** (com motivo) ou **consumo** de ordem de produção, e tudo fica no histórico.
  A previsão "acaba em X dias" usa o consumo médio dos últimos 30 dias (configurável).
- **Fichas técnicas** (`/producao/fichas`): quanto de cada matéria-prima vai em 1 unidade do produto e o custo por unidade.
- **Ordens de produção** (`/producao/ordens`): a ordem é planejada com prévia do consumo. Ao **concluir**, o sistema baixa as matérias-primas
  (quantidade × ficha) e soma o produto acabado, tudo junto ou nada. Se faltar matéria-prima, não conclui. Também tem a versão para imprimir em A4.
- **Estoque acabado** (`/producao/acabados`): mínimo por produto. Abaixo do mínimo, o sistema sugere a **ordem de reabastecimento**
  (cobre 30 dias de venda, no mínimo o dobro do mínimo). Pedido faturado baixa o estoque acabado (pode desligar em Configurações › Produção).
- Tudo passa pelo `ProducaoService`: se o plano do Omie tiver produção, só ele muda.
- Perfil Produção altera; Gestor só vê (`producao.ver`).

## Marketing

- **Mural de posts** (Instagram): calendário do mês, arrastar para mudar o dia, prévia do feed, fluxo Ideia → Em produção →
  Aguardando aprovação → Aprovado → Agendado → Publicado. Só quem tem `marketing.aprovar` (Admin) aprova; mudar imagem/legenda
  de post aprovado devolve para aprovação. As etapas ficam em Configurações › Listas.
- **Planejamento**: campanhas em linha do tempo (3 meses), datas comemorativas (Configurações) e vendas do produto da campanha no site.
- **E-commerce (Tray)**: faturamento, pedidos, ticket médio, gráficos e mais vendidos. Simulado até ligar a Tray (LEIA-ME-INSTALACAO, 9c).

## Tarefas agendadas (cron)

No cPanel › Cron Jobs, cadastre (troque o caminho pelo da sua conta):

| Quando | Comando | Para quê |
|---|---|---|
| Todo dia às 6h | `php /home/USUARIO/public_html/cron/fila_reativacao.php` | Manda para a fila de reativação os clientes sem compra há X dias |
| A cada 30 min | `php /home/USUARIO/public_html/cron/sincronizar_erp.php` | Preços e estoque do ERP; reenvia pedidos pendentes; traz o faturamento (NF) |
| Todo dia às 7h | `php /home/USUARIO/public_html/cron/cobranca.php` | Lê títulos vencidos/pagos do ERP e trata promessas vencidas |
| De hora em hora | `php /home/USUARIO/public_html/cron/ecommerce.php` | Copia os números da loja Tray para o painel do e-commerce |

## Logins de demonstração

Senha de todos: `Demo@2026` — usuários fictícios, um por perfil e por função.

| E-mail | Perfil · Função |
|---|---|
| admin@demo.local | Admin |
| gestor@demo.local | Gestor |
| televendas1@demo.local / televendas2@demo.local | Vendedor · Televendas |
| externo1@demo.local | Vendedor · Externo |
| reativacao@demo.local | Vendedor · Prospecção e Reativação |
| terceirizacao@demo.local | Vendedor · Terceirização e Marca Própria |
| financeiro@demo.local | Financeiro · Cobrança |
| marketing@demo.local | Marketing (mural, planejamento, e-commerce) |
| producao@demo.local | Produção |

## Onde fica cada coisa

| Pasta | O que tem |
|---|---|
| `public/` | Única pasta exposta na web: `index.php`, CSS, JS, imagens, mídia, PWA |
| `app/Core/` | Peças básicas: banco, roteador, login, CSRF, auditoria, configurações |
| `app/Controllers/` | Um controller por módulo (recebe o pedido e escolhe a tela) |
| `app/Models/` | Acesso às tabelas |
| `app/Services/` | Regras de negócio (pedido mínimo, carteira, recompra...) |
| `app/Integrations/` | Omie e Ligue Talk. **Fake** (simulado) e **real** com a mesma interface |
| `app/Views/` | Templates das telas |
| `app/rotas.php` | Lista de endereços → controller → permissão |
| `app/menu.php` | Itens do menu lateral |
| `app/permissoes.php` | Permissões e quais perfis têm cada uma |
| `database/migrations/` | Tabelas, em arquivos SQL numerados |
| `database/seeds/` | Dados de demonstração |
| `cron/` | Tarefas agendadas (sincronização Omie, filas) |
| `storage/` | Logs e arquivos gerados (fora do git e da web) |
| `referencias/` | Arquivos originais (fora do git e da web) |

## Trocar o Omie simulado pelo real (Fase 2)

Em `config/config.php`: `'integracoes' => ['erp' => 'omie', 'omie' => ['app_key' => '...', 'app_secret' => '...']]`.
O resto do sistema não muda. O esqueleto da API real está em `app/Integrations/Omie/OmieClient.php`.

## Regras do projeto

- Nunca usar nomes de funcionários em regras, filas ou código. Tudo usa **funções**.
- Toda regra comercial é configurável (tela **Configurações** ou tabelas próprias).
- Toda consulta ao banco usa parâmetros (`Banco::um('... WHERE id = ?', [$id])`).
- Toda saída na tela passa por `e()`. Todo formulário POST tem `<?= csrf_campo() ?>`.
