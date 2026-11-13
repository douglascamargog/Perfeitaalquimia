# Testes automáticos do One

Cada arquivo `teste_*.js` abre o sistema num navegador de verdade (Playwright), faz o que a equipe faria
(entrar, criar pedido, aprovar, importar planilha…) e confere o resultado **no banco de dados**.
São mais de 400 verificações. Rode **antes de publicar qualquer mudança**: se algo que funcionava quebrou, aparece aqui.

> Atenção: os testes **apagam e recriam o banco `perfeita`**. Use só no computador ou ambiente de teste,
> nunca no servidor com os dados reais. Eles não vão no pacote de atualização.

## Rodar

```bash
bash tests/rodar.sh              # todos (uns 5 minutos)
bash tests/rodar.sh pedidos one  # só alguns
```

O resultado de cada teste fica em `tests/saida/<nome>.txt` (as capturas de tela também vão para `tests/saida/`).

Precisa de PHP 8, MySQL/MariaDB (usuário `root` sem senha no ambiente de teste), Node e Playwright
(`npm i -g playwright` e `npx playwright install chromium`). A pasta `referencias/` precisa existir
(as 499 lojas da Carteira V4 e a Central de Televendas são carregadas na instalação de teste).

## Testar uma atualização com dados

```bash
bash tests/atualizacao.sh /caminho/backup-do-servidor.sql.gz
```

Restaura uma cópia do banco em uso, aplica a atualização e confere que clientes, usuários, histórico e tarefas
continuam com o mesmo número de registros.

## O que cada teste cobre

| Teste | O que confere |
|---|---|
| `base` | Login, senha, perfis e permissões |
| `carteira` | Carteira, ficha, funil, mapa, filtros, contato e ligação |
| `importacao` | Importação CSV, XLSX e backup da Carteira V4, com deduplicação por CNPJ |
| `listas` | Etapas, segmentos e origens configuráveis |
| `meu_mes` | Meta individual, projeção e tarefas |
| `one` | Avisos do One por perfil |
| `cobranca` | Funil da cobrança, promessas e busca de contatos |
| `marketing` | Mural de posts, aprovação e e-commerce |
| `pedidos` | Catálogo, carrinho, pedido mínimo, aprovação e faturamento |
| `painel` | Painel de vendas, metas e recompra |
| `app` | App do externo offline, sem duplicar pedido |
| `academia` | Academia e o treinamento "Como usar o One" |
| `producao` | Matérias-primas, fichas técnicas, ordens e reabastecimento |
| `prospeccao` | Prospecção, mensagens prontas e leads do site |
| `ajustes` | Código do produto, busca rápida, ficha do funcionário, aniversariantes, Início do admin, backup |
| `revisao` | Abre todas as telas de todos os perfis no computador e no celular (erros, avisos e rolagem lateral) |
