# Como instalar o One na HostGator (subdomínio)

Tempo estimado: 15 minutos. Você só vai usar o **cPanel** pelo navegador; não precisa de terminal.

> **Não apague nem substitua a pasta `public_html/carteira`.** Ela é a Carteira atual da equipe.
> O One fica numa pasta separada, e os dois funcionam lado a lado até a troca definitiva.

---

## 1. Criar o subdomínio

cPanel › **Domínios** (ou **Subdomínios**) › Criar:

- Subdomínio: `one` · Domínio: `somosalquimia.com.br`
- **Raiz do documento (Document Root):** `public_html/one/public`  ← importante: termina em `/public`

(Se o cPanel não deixar escolher a raiz, use `public_html/one`. Funciona também: o `.htaccess` do pacote cuida disso.)

## 2. Enviar os arquivos

cPanel › **Gerenciador de arquivos** › entre em `public_html`:

1. **Carregar** › envie o arquivo `one-perfeita-alquimia-AAAA-MM-DD.zip`.
2. Clique com o botão direito no zip › **Extrair** › extrair em `public_html`.
   Vai aparecer a pasta `public_html/one` com as pastas `app`, `config`, `public`, `referencias`...
3. Pode apagar o `.zip` depois de extrair.

## 3. Versão do PHP

cPanel › **MultiPHP Manager** › marque o domínio `one.somosalquimia.com.br` › escolha **PHP 8.1 ou mais novo** (8.2 ou 8.3 se tiver) › Aplicar.

## 4. Criar o banco de dados

cPanel › **Bancos de dados MySQL**:

1. **Criar novo banco:** por exemplo `one` (o cPanel vai chamar de `doug9130_one`).
2. **Adicionar novo usuário:** por exemplo `one` (vira `doug9130_one`) com uma senha forte. **Anote a senha.**
3. **Adicionar usuário ao banco:** escolha o usuário e o banco › marque **Todos os privilégios** › Fazer alterações.

## 5. Preencher a configuração

Gerenciador de arquivos › `public_html/one/config/config.php` › **Editar**. Troque só estas 3 linhas:

```php
'nome'    => 'doug9130_one',          // nome completo do banco (com o prefixo)
'usuario' => 'doug9130_one',          // nome completo do usuário (com o prefixo)
'senha'   => 'a-senha-que-você-anotou',
```

Anote também o valor de **`instalar_token`** (já vem preenchido): você vai usar no próximo passo. Salve.

## 6. Instalar

Abra no navegador: **https://one.somosalquimia.com.br/instalar**

- Cole o **token** do passo 5 › deixe marcado "Carregar dados de demonstração" › **Instalar / atualizar**.
- Deve aparecer a lista: tabelas criadas, "499 lojas da Carteira V4 carregadas", "Academia: 161 produtos"...

## 7. Desligar a tela de instalação (segurança)

Volte ao `config/config.php` e deixe o token vazio:

```php
'instalar_token' => '',
```

Salve. A tela `/instalar` passa a mostrar "desligada".

## 8. Entrar

**https://one.somosalquimia.com.br** › use `admin@demo.local` / `Demo@2026`.

Antes de usar com dados reais:

1. Em **Usuários**, crie o seu login de admin com seu e-mail e uma senha forte, e os logins da equipe.
2. **Desative** os usuários de demonstração (`...@demo.local`).
3. No `config.php`, troque `'modo_demo' => true` por `false` para tirar a faixa "dados simulados" e os logins de teste da tela de entrada.

## 9. Tarefa diária (opcional, mas recomendado)

cPanel › **Cron Jobs** › adicionar, uma vez por dia (ex.: 6h):

```
php /home/doug9130/public_html/one/cron/fila_reativacao.php
```

Ela envia para a fila de Reativação os clientes sem compra há mais de X dias (X fica em Configurações).

Adicione também a do **ERP** a cada 30 minutos (preços do catálogo, envio de pedidos e faturamento):

```
0,30 * * * * php /home/doug9130/public_html/one/cron/sincronizar_erp.php
```

E, uma vez por dia (ex.: 7h), a da **Cobrança** (lê os títulos vencidos/pagos do ERP e devolve
para "Tentativa de contato" as promessas de pagamento que venceram):

```
php /home/doug9130/public_html/one/cron/cobranca.php
```

## 9a. HTTPS (obrigatório para o app do externo)

O app do vendedor externo (`/app`) só funciona offline com **HTTPS**. No cPanel › **SSL/TLS Status** › marque o subdomínio › **Run AutoSSL**
(grátis). Depois abra `https://one.somosalquimia.com.br/app` no celular e instale (Android: menu ⋮ › Instalar app; iPhone: Compartilhar ›
Adicionar à Tela de Início).

## 9b. Ligar a busca de contatos com IA (quando quiser)

Até ligar, o botão **Buscar contatos com IA** funciona em modo demonstração (contatos fictícios, sem custo).

1. Crie uma conta em **console.anthropic.com**, cadastre um cartão e crie uma chave em **API Keys**.
   Dica: em **Limits**, defina um limite de gasto mensal (ex.: US$ 20).
2. No cPanel › Gerenciador de arquivos › `one/config/config.php`, cole a chave em `'anthropic' => ['chave' => '...']`.
   (Num sistema que já estava instalado, acrescente o bloco `'anthropic'` copiando de `config.example.php`.)
3. No One, como admin: **Configurações › Busca de contatos (IA) › Provedor da busca** = `claude`.

Custo estimado: de US$ 0,10 a 0,30 por empresa pesquisada. O admin vê o total do mês no topo da tela **Cobrança**.
O limite de buscas por dia também fica em Configurações. Só dados públicos da empresa (CNPJ, nome, cidade) vão para a IA.

## 9c. Ligar o e-commerce real (Tray)

Até ligar, o painel **Marketing › E-commerce** mostra números simulados. Para ver os dados reais da loja:

1. **Chaves do aplicativo (só a Tray libera).** A API da Tray funciona por "aplicativo": a empresa cadastra um app no
   programa de parceiros da Tray (developers.tray.com.br › Quero ser parceiro) e recebe por e-mail a **consumer key**
   e a **consumer secret**. Se a agência que cuida da loja já tem um aplicativo Tray, ela pode fornecer essas chaves.
2. **Autorizar na loja.** No painel da loja Tray › **Meus Aplicativos** › o aplicativo › **Acessar**: aparece o **code**.
   O **endereço da API** é o endereço da loja + `/web_api` (ex.: `https://www.sualoja.com.br/web_api`).
3. No cPanel › `one/config/config.php`, preencha o bloco `'tray'` (num sistema já instalado, copie o bloco de `config.example.php`):
   ```
   'tray' => ['api' => 'https://www.sualoja.com.br/web_api', 'consumer_key' => '...', 'consumer_secret' => '...', 'code' => '...'],
   ```
4. No One, como admin: **Configurações › E-commerce (Tray) › Origem dos números** = `tray`.
5. **Marketing › E-commerce › Testar conexão.** Deu certo? Clique em **Atualizar agora**. A primeira leitura busca 13 meses
   de pedidos e pode levar alguns minutos (a Tray limita 180 consultas por minuto). Os números simulados são apagados nessa hora.
6. Cron de hora em hora (cPanel › Cron Jobs):
   ```
   0 * * * * php /home/doug9130/public_html/one/cron/ecommerce.php
   ```

O One só lê números (pedidos, valores, produtos). Não grava nada na loja e não guarda nome, e-mail ou endereço de comprador.
Pedidos cancelados não entram no faturamento.

## 10. Trazer o trabalho da Carteira atual

Na Carteira atual (`/carteira`), use **Backup JSON** para baixar o arquivo. No One: **Carteira › Importar** › envie o arquivo › confira a pré-visualização › Confirmar.
As etapas, contatos e histórico da equipe entram nas lojas correspondentes.

---

### Se algo der errado

| Sintoma | O que fazer |
|---|---|
| Página em branco ou "erro 500" | Confira a versão do PHP (passo 3) e os dados do banco no `config.php` (passo 5). |
| "Configuração não encontrada" | O arquivo `config/config.php` não existe ou está em outra pasta. |
| Instalação diz "Token inválido" | Copie o token de novo do `config.php`, sem espaços. |
| Fotos da Academia não aparecem | A pasta `public/media` precisa de permissão de escrita (755) antes de instalar; rode o `/instalar` de novo. |
| Lojas ou produtos não carregaram | Confira se a pasta `referencias/` tem os arquivos `dashboard.html` e `Central_TelevendasPA_V2.html`. |

Os erros ficam registrados em `public_html/one/storage/logs/php-erros.log` (não aparecem para o usuário, por segurança).
