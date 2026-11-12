# Sistema Unificado Perfeita Alquimia — Fase 0 (protótipo)

Carteira e CRM, catálogo B2B com pedido, app do externo, painel de vendas, Academia e produção num só sistema,
integrado ao Omie (no protótipo: **dados simulados**). Especificação completa em `CLAUDE.md`.

PHP 8 + MySQL/MariaDB, HTML/CSS/JS puro. Sem Composer e sem etapa de build.

## Rodar no seu computador

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
| financeiro@demo.local | Financeiro |
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
