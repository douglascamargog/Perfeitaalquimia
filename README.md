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

## Tarefas agendadas (cron)

No cPanel › Cron Jobs, cadastre (troque o caminho pelo da sua conta):

| Quando | Comando | Para quê |
|---|---|---|
| Todo dia às 6h | `php /home/USUARIO/public_html/cron/fila_reativacao.php` | Manda para a fila de reativação os clientes sem compra há X dias |
| Todo dia às 7h | `php /home/USUARIO/public_html/cron/cobranca.php` | Lê títulos vencidos/pagos do ERP e trata promessas vencidas |

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
| marketing@demo.local | Marketing |
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
