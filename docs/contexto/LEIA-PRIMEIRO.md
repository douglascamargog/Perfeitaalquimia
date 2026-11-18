# One — contexto completo para quem continuar o projeto

> Escrito em 18/11/2026, na versão **ONE_V17.9**, pela conversa que construiu o sistema desde a V1.0 (branch `ccr-12815ad2-flpiv1`).
> Ordem de leitura: **1)** `CLAUDE.md` (regras permanentes) → **2)** este arquivo → **3)** `app/versoes.php` (100 versões, o que mudou em cada uma)
> → **4)** as notas das outras conversas nesta pasta (`docs/contexto/`), se existirem → **5)** `docs/nota10/` (importação do ERP antigo).
> Nada aqui tem dados de clientes (LGPD).

## 1. O que é o One, em uma frase

Sistema interno da Perfeita Alquimia (fabricante de suplementos, RS, vende B2B no Brasil todo) que substituiu o Leads2b (CRM),
os dois catálogos de pedido e boa parte do Nota10 (ERP antigo). Roda **em produção** na HostGator com os dados reais, integrado ao Omie (ERP novo),
Tray (loja virtual), Ligue Talk (telefonia), Google Maps e Claude (IA). Quem mantém: **uma pessoa só, que não é programadora**
(designer / diretor de marketing). Por isso: código simples, comentado em português, sem build, poucas dependências.

## 2. Números do projeto (V17.9)

| | |
|---|---|
| Versões publicadas | 100 (V1.0 → V17.9), todas descritas em `app/versoes.php` |
| Commits | 83 nesta branch |
| Migrations (banco) | 64 em `database/migrations/` (001 → 064), só acrescentam |
| Seeds | 98 em `database/seeds/` (essenciais idempotentes + `_demo` só para teste) |
| Controllers / Services | 75 / 78 |
| Testes automáticos | 81 arquivos Playwright em `tests/` (`bash tests/rodar.sh`) |
| Rotinas automáticas (cron) | 8 em `cron/` |

## 3. Stack e como as peças se encaixam

- **PHP 8 + MySQL/MariaDB** (produção: MySQL 5.7 → nada de `WITH`, funções de janela nem `REGEXP_REPLACE`). PDO, sempre prepared statements.
- **Front:** HTML + CSS + JS puro (ES modules). Bibliotecas por CDN só **MapLibre** e **Chart.js**. Diagramas são SVG desenhado à mão (ex.: `public/assets/js/organograma.js`).
- **MVC próprio, sem framework:**
  - `app/rotas.php` → lista `[método, caminho, Controller@ação, permissão]` (`'logado'`, `'publico'` ou permissão; várias com `|`).
  - `app/Core/` → `Banco` (`valor/um/todos/executar/inserir/atualizar/transacao`), `Auth` (login, `pode()`, funções, modo desenvolvedor), `Config` (tabela `configuracoes`), `Menu`, `Roteador`, `Csrf`, `Sessao`, `Auditoria`, `Instalador`.
  - `app/Controllers/` (telas) → `app/Services/` (regras) → `app/Views/` (PHP com `e()` para escapar).
  - `app/menu.php` (menu lateral), `app/permissoes.php` (permissões e perfis), `app/acessos.php` (tela de Acessos), `app/versoes.php` (novidades).
  - `app/dados/` → dados fixos editáveis (ex.: `interligacoes.php`, o organograma de interligações).
  - `app/Integrations/` → cada integração com **interface + versão real + versão simulada (Fake)**: Omie, Tray, LigueTalk, OneIA (Claude), BuscaContatos, BaseCnpj, Mapas.
- **Tema:** variáveis em `public/assets/css/base.css`; modo escuro gerado por `python3 ferramentas/gerar_tema_escuro.py` (rodar sempre que mudar CSS).
- **Segredos:** banco em `config/config.php`; chaves das integrações cadastradas pela tela e guardadas em `config/segredos.php` (fora do git e dos pacotes). **Nunca pedir chave no chat.**

## 4. Como uma versão nova é feita e entregue (o ritual)

1. Mudança no banco = **migration nova** numerada (nunca editar uma publicada; só `CREATE TABLE`, `ADD COLUMN` com DEFAULT, `CREATE INDEX`).
2. Dados essenciais em seed idempotente; dados de demonstração em seed `_demo` (só instalação nova).
3. Testes Playwright da funcionalidade em `tests/teste_<nome>.js` (rodar com servidor `php -S 127.0.0.1:8000 -t public public/index.php` e banco de **teste**).
4. Recriar o banco do zero (`php database/migrar.php`) **e** testar a atualização sobre um banco com dados (`php database/migrar.php --atualizacao`).
5. Acrescentar a versão no **topo** de `app/versoes.php` (`novo` / `melhoria` / `correcao` escritos para a equipe; `admin` = recado técnico).
6. Se a versão mexe na troca de informação entre setores: atualizar `app/dados/interligacoes.php` (o Organograma).
7. Commit em português → `bash ferramentas/gerar_pacote.sh --atualizacao` → gera `pacote/ONE_V<versão>.zip`.
8. O usuário sobe o zip na HostGator (passo a passo em `LEIA-ME-ATUALIZACAO.md`); na 1ª visita o One faz backup e aplica o banco sozinho, e avisa a equipe (sino + janela "Novidades").
9. Ao entregar, dizer ao usuário **só o nome da versão e o resumo**, em linguagem simples.

## 5. Mapa dos módulos (menu lateral)

| Grupo | Telas |
|---|---|
| Topo | Início, Organograma (só desenvolvedor), Pessoas |
| Vendas (por setor: Televendas, Externo, Prospecção e Reativação, Terceirização) | Carteira (funil, mapa, ficha do cliente, linha do tempo), Meu mês, Agenda, Planejador de visitas, Recompra, Reativação, Prospecção, App do externo (PWA offline), Pedidos, Orçamentos (link para o cliente aprovar), Terceirização, Análise de clientes |
| Comercial | Campanhas (faixas, brindes, frete, visual do catálogo) |
| Financeiro | Cobrança (funil próprio, ligada aos títulos da ficha), Aprovação de pedidos |
| Gestão | Resultados (Painel de vendas, Equipe, Acompanhamento, Metas), Carteiras |
| Compras | Compras da empresa (NF), Pedidos de compra/cotações, Fornecedores |
| Marketing | Início do Marketing, Pedir ao Marketing, Mural de posts, Planejamento, E-commerce (Tray), Leads do site, Mensagens prontas |
| Produtos | Produtos (ficha técnica, nutricional, documentos), Catálogo, Fórmulas, Produtos novos (desenvolvimento), Tarefas de produto novo, Ligar produtos do Nota10 |
| Treinamento | Meu treinamento, Biblioteca de produtos (Academia), Treinamento da equipe, Editar treinamento; tutorial do One em cada tela |
| Depósito | Separação, Recebimento, Rastrear lote |
| Produção | Produção, Matérias-primas, Fichas técnicas, Ordens de produção, Rótulos, Estoque acabado |
| Administração | Especialistas do One (IA), Etapas do desenvolvimento, Comunicados, Pessoas e acessos (Usuários, Funções, Acessos), Configurações e Listas, Integração Omie, chaves (IA, Tray, Ligue Talk, Google Maps), Desenvolvedor do sistema, Atualização, Importar Nota10, Zerar dados, Auditoria |

Mais: catálogo público B2B (`/catalogo`, preço só após login por CNPJ, link por vendedor), captura de leads do site com UTM, One (mascote/assistente com IA e especialistas por setor), modo escuro, presença online, aniversários, comunicados.

## 6. Acesso: perfis, funções e desenvolvedor (o que costuma confundir)

- **Perfil** (Admin, Gestor, Gestor de setor, Diretoria, Vendedor, Financeiro, Marketing, Produção, Depósito, Qualidade, Técnica) = **o que a pessoa pode abrir/editar** (`perfil_permissoes`). Admin tem `*`.
- **Exceções por pessoa** (`usuario_permissoes`) = dar ou tirar uma permissão de alguém específico.
- **Função** (Televendas, Externo, Prospecção e Reativação, Terceirização, Marketing, Gestor de Marketing, Produção, Cobrança, Técnica, Qualidade, Depósito, Social Media, E-commerce, Diretoria) = **o que a pessoa acompanha**: setor de venda/carteira, filas, Início, sino, especialista do One, área no organograma. "Gestor(a) do setor" é marcado por função.
- **Desenvolvedor do sistema** (V17.9, `usuarios.desenvolvedor`): marcado em Administração; só ele vê Organograma, Atualização, Importar Nota10, Zerar dados e Dados de teste (permissão virtual `sistema.desenvolvedor`, que nem o Admin tem sozinho) e tem o seletor **"Ver o One como"** uma função (usa só aquela função + o perfil de quem trabalha nela). O usuário pediu, numa conversa futura, para **simplificar perfis × funções** (ainda não planejado).
- **Regra de ouro:** nunca nome de pessoa no código; tudo por função/perfil.

## 7. Fontes da verdade e integrações

| Sistema | O que vem/vai | Situação |
|---|---|---|
| **Omie** (ERP) | produtos, preços, estoque, pedidos (vão como orçamento "a aprovar"), faturamento, títulos | Em produção via `config.php`. **Pendente:** conferir o `OmieClient` com a API real e criar a tela de chaves (CLAUDE.md §7). Cron `sincronizar_erp.php`, `cobranca.php` |
| **Nota10** (ERP antigo) | histórico 2021–2026: clientes, pedidos, itens, títulos, contatos, fornecedores, compras, follow-up | Importação feita pela tela (V17.0–V17.5). Detalhes em `docs/nota10/` |
| **Tray** | pedidos pagos da loja e marketplaces (cópia local, cron 15 min) | Sem dados pessoais do comprador. Homologação do app na Tray pendente |
| **Ligue Talk** | ligações e gravações → linha do tempo | API ou modo manual |
| **Google Maps** | geocodificação das lojas (cron) | |
| **Claude** | One com IA + especialistas por setor; busca de contatos | Chave pela tela; responde "sem IA" (com aviso) se faltar chave/estiver desligado |

Omie é a fonte da verdade do fiscal/financeiro/estoque; o One é a fonte da verdade de carteira, funil, histórico, tarefas, metas, catálogo e treinamento.

## 8. Decisões de negócio importantes (não mudar sem o usuário pedir)

- **Faturamento conta pela DATA DO PEDIDO** (`pedidos.criado_em`), em todo o sistema, inclusive pedidos feitos no One (V17.5). A data da NF fica em `faturado_em`/`emitido_em`.
- NF cancelada não soma. Pedidos de outras empresas do grupo ficam como `outra_empresa` (painéis mostram só a Perfeita Alquimia).
- Título vencido de **qualquer** empresa do grupo bloqueia o boleto (só Pix/cartão).
- **Cobrança** é montada a partir dos títulos da ficha do cliente (`titulos_receber` é a fonte única); baixa vale nos dois sentidos (V17.4).
- **Bonificação**: na mesma NF, CFOP por item (5910/6910), até 15% sem aprovação; não conta no faturamento nem na meta.
- Um endereço de entrega por loja; "entregar em..." escrito na observação vai para revisão humana.
- Produtos que vieram só do Nota10 entram inativos e são ligados aos do One em Produtos › Ligar produtos do Nota10 (V17.7).
- Tabela "Base" do Nota10 = tabela Lojista.

## 9. Onde estão as coisas que o usuário mais pede

- Organograma de interligações e organização: `/organograma` (só desenvolvedor), dados em `app/dados/interligacoes.php`. **Manter sempre atualizado.**
- O que falta integrar (ilhas, nov/2026): estoque da fábrica × estoque do ERP; fornecedores do Nota10 × tela de Compras; contatos/aniversários das lojas fora da ficha; retorno das campanhas de marketing em vendas; promoção comercial não gera tarefa de marketing; aprovação sem limite de crédito/títulos a vencer (parcial). Lista viva na aba "O que falta ligar".
- Análise completa das integrações (artefato): https://claude.ai/artifact/LddUiHHKJBs5Eqtk8Zxtfo

## 10. Ambiente de teste e armadilhas conhecidas

- Testes recriam o banco `perfeita` — **nunca apontar para produção**. `bash tests/rodar.sh [nomes]`.
- Os produtos de demonstração vêm de `referencias/Central_TelevendasPA_V2.html` (11 MB, **fora do git**). Sem ele, testes que dependem de produtos falham (`teste_ajustes`, `teste_diretoria`, `teste_acessos`); isso não é regressão.
- Depois de subir a versão, a janela "Novidades" cobre a tela nos testes: marcar `usuarios.versao_vista` com a versão atual.
- O seed `098_desenvolvedor_demo.php` marca `admin@demo.local` como desenvolvedor (só instalação de teste).
- `teste_cobranca` tem 2 falhas antigas ("vendedor não vê Cobrança no menu").

## 11. Como o usuário gosta de trabalhar

- Fala português, escreve rápido e às vezes manda pedidos no meio de outro: tratar cada pedido como uma versão.
- Quer ver o resultado (prints/prévias) e receber o pacote zip pronto, com resumo curto e sem jargão.
- Quer tudo **integrado** entre os setores e **configurável** pela tela (nada fixo no código).
- Decisões de negócio: perguntar com opções claras (ele escolhe rápido) e registrar a decisão (CLAUDE.md §13 ou `docs/`).
- Dados de clientes nunca em git, chat ou log.
