# CLAUDE.md — Sistema Unificado Perfeita Alquimia

> Instruções permanentes para o Claude Code. Leia este arquivo inteiro no início de cada sessão.
> Especificação completa (fonte de verdade do negócio): https://claude.ai/artifact/UVUXefvi1ZqJbV5BkFJyhM

## 1. Contexto

A Perfeita Alquimia é uma fabricante de suplementos e produtos naturais (RS) que vende B2B para lojistas,
distribuidores e clientes de terceirização/marca própria em todo o Brasil.

Este sistema substitui o **Leads2b** (CRM) e os **dois catálogos de pedido** (Catálogo Mobile e o dos externos),
e vai trabalhar integrado ao **Omie** (novo ERP, no lugar do Nota10) e ao **Ligue Talk** (telefonia/WhatsApp).
O sistema de RH fica **fora do escopo**.

**Entrega atual: Fase 0 — protótipo completo para apresentar à diretoria** e aprovar a contratação do Omie.
Todos os módulos navegáveis, com **dados simulados no lugar do Omie**.

Quem mantém o projeto: uma única pessoa (designer/diretor de marketing, não programador), com Claude Code no plano Pro.
Portanto: código simples, legível, comentado em português, poucas dependências, fácil de editar.

## 2. Stack e hospedagem

- **Back-end:** PHP 8.x + MySQL/MariaDB (PDO, prepared statements sempre).
- **Front-end:** HTML + CSS + JavaScript puro (ES modules). Sem React/Vue/build step.
  Bibliotecas permitidas via CDN: MapLibre GL (mapa, já usado na Carteira), Chart.js (gráficos). Nada além disso sem perguntar.
- **Protótipo:** HostGator (hospedagem compartilhada, Apache, cron disponível, sem Node/processos permanentes).
- **Definitivo:** servidor próprio da empresa. O código não pode depender de nada específico da HostGator.
- **App do externo:** PWA (manifest + service worker + IndexedDB) servido pelo mesmo sistema.
- Configurações sensíveis (banco, chaves do Omie) em `config/config.php`, **fora do git** (`config.example.php` versionado).

## 3. Estrutura de pastas (proposta — confirmar em modo de planejamento)

```
/public            → única pasta exposta na web (index.php, assets, catalogo/, app/ do PWA)
/app
  /Controllers     → uma classe por módulo
  /Models          → acesso ao banco
  /Services        → regras de negócio (pedido mínimo, carteira, recompra...)
  /Integrations
    /Omie          → OmieClient (real) + OmieFake (dados simulados) com a MESMA interface
    /LigueTalk     → idem (plano B: registro manual)
  /Views           → templates PHP
/config            → config.php (fora do git), config.example.php
/database
  /migrations      → SQL numerado: 001_usuarios.sql, 002_clientes.sql...
  /seeds           → dados simulados do protótipo
/cron              → scripts agendados (sincronização Omie, fila de reativação)
/referencias       → arquivos originais (não servir na web)
```

## 4. Regras invioláveis

1. **Interface em português do Brasil.** Datas `dd/mm/aaaa`, moeda `R$ 1.234,56`.
2. **Nunca usar nomes de funcionários em regras, filas ou código.** Tudo usa **funções**:
   Televendas, Externo, Prospecção e Reativação, Terceirização e Marca Própria, Marketing, Produção.
   Pessoas são apenas vinculadas a funções (os funcionários mudam).
3. **Toda regra comercial é configurável pelo admin** (tabela `configuracoes` ou tabelas próprias), nunca fixa no código:
   condições de pagamento, descontos, parcelas, pedido mínimo por região, dias de inatividade, etapas do funil, segmentos.
4. **Omie é a fonte da verdade** de produtos, preços, estoque, faturamento, financeiro e dados fiscais do cliente.
   **O sistema é a fonte da verdade** de prospecção, funil, histórico, tarefas, carteira/vendedor responsável, catálogo, metas e treinamento.
5. **Toda integração externa passa por uma interface** em `/app/Integrations`. O protótipo usa a versão Fake;
   trocar para a real deve ser só mudar a configuração.
6. **Segurança:** login individual (fim da senha compartilhada), `password_hash`/`password_verify`, sessão segura,
   token CSRF em todo formulário/POST, escape de toda saída (`htmlspecialchars`), log de auditoria
   (quem, o quê, quando) em alterações de clientes, pedidos e configurações.
7. **LGPD:** dados de clientes nunca em URLs públicas nem em logs abertos.
8. Antes de cada módulo novo: **entrar em modo de planejamento**, propor tabelas + telas e esperar aprovação.
9. Commits pequenos, um por funcionalidade, mensagem em português.

## 5. Perfis de acesso

| Perfil | Acesso |
|---|---|
| Admin | Tudo, incluindo configurações e usuários (sócios e diretor) |
| Gestor | Todas as carteiras, relatórios e metas |
| Vendedor | Só a própria carteira, catálogo, pedidos e Academia |
| Financeiro | Aprovação de pedidos, crédito, relatórios de vendas |
| Marketing | Leads de tráfego, Academia, conteúdo de produtos e catálogo |
| Produção | Matérias-primas, fichas técnicas, ordens de produção, reabastecimento |
| Cliente (catálogo) | Login por CNPJ + senha ou código por e-mail/WhatsApp; vê só a própria tabela de preço e pedidos |

## 6. Módulos

### 6.1 Carteira e CRM (substitui o Leads2b)
- Cliente/lead: razão social, nome fantasia, CNPJ, endereço, cidade, UF, região, segmento, telefone, WhatsApp, e-mail,
  redes sociais, tabela de preço, vendedor responsável, função, origem (incl. UTM), observações.
- Segmentos padrão: Produtos naturais, Suplementos, Farmácia, Outros.
- Funil configurável. Padrão: Validar cadastro → Não abordado → Contato realizado → Em negociação → Proposta enviada →
  Aguardando retorno → Cliente ativo / Sem interesse / Inativo.
- Linha do tempo: contatos, ligações, mensagens, pedidos, mudanças de etapa (autor + data).
- Tarefas/próximos contatos: atrasados, hoje, futuros.
- **Regra de carteira:** todo cliente tem um responsável. Sem compra há X dias (configurável) → vai para a fila da função
  Prospecção e Reativação. Reativado → volta para um vendedor.
- Filtros: UF, cidade, região, segmento, etapa, prioridade, vendedor, situação de compra. Paginação no servidor.
- Mapa do Brasil (MapLibre) com agrupamento por estado/cidade.
- Importação CSV/XLSX (carteiras por vendedor; carteira da Black Friday) com pré-visualização e deduplicação por CNPJ. Exportação CSV.
- Banco preparado para dezenas de milhares de lojas: índices em CNPJ, UF, cidade, segmento, responsável, etapa.

### 6.2 Prospecção automática
- Base pública de CNPJs da Receita Federal filtrada pelos CNAEs dos clientes atuais.
- Usuário escolhe UF/cidade → lista empresas ativas, marcando as que já estão na carteira → botão "enviar para o funil".
- **Protótipo:** amostra simulada de um estado (RS). Importação da base real é fase posterior.

### 6.3 Catálogo B2B com pedido (substitui os dois catálogos)
- Página pública com produtos, fotos e descrições, **sem preço e sem estoque**.
- Preço só após login do cliente (CNPJ cadastrado + senha ou código). Mostra a tabela dele: **Lojista, Distribuidor ou Terceirização**.
- Sem kits: venda por unidade.
- **Pedido mínimo por região/UF** (configurável); o carrinho mostra quanto falta.
- Pagamento padrão: cartão, Pix, boleto. Condições configuráveis.
- **Link por vendedor** (`/catalogo/v/{codigo}`): pedidos por esse link vão para aquele vendedor.
- Botão "repetir último pedido".
- Cliente com título em atraso: boleto bloqueado, só Pix ou cartão.
- Produtos vêm do **mesmo cadastro da Academia** (mesma foto e ficha).

### 6.4 App do vendedor externo (PWA offline)
- Instalável no celular; carteira, produtos e preços em cache (IndexedDB).
- Anota pedidos sem internet; envia sozinho ao voltar a rede (Background Sync ou checagem ao abrir/online),
  **sem duplicar** (cada pedido tem UUID gerado no aparelho; servidor ignora UUID repetido).
- Status: aguardando envio → enviado → aprovado.

### 6.5 Fluxo do pedido
1. Origem: catálogo (cliente), sistema (vendedor) ou app (externo).
2. Validações: pedido mínimo da região, tabela de preço do cliente, forma de pagamento, crédito.
3. Envio ao Omie como **orçamento / etapa a aprovar** (no protótipo: OmieFake).
4. Financeiro aprova.
5. Status e faturamento voltam para a linha do tempo do cliente.

### 6.6 Painel de vendas e metas
- Faturamento, pedidos, ticket médio, recorrência por cliente, vendedor, função, região, período.
- Clientes sem compra há 30/60/90 dias.
- **Alerta de recompra:** intervalo médio de compra por cliente; avisa quando passar do prazo.
- Metas por vendedor e por função.

### 6.7 Academia (treinamento)
- Migrar a Academia Comercial atual: trilha, frentes do negócio, fichas técnicas, falas, objeções, condições.
- Extrair as ~170 imagens base64 do HTML para `/public/media/` (biblioteca de mídia).
- Trocar o módulo "Leads2b na prática" por "Como usar o sistema".

### 6.8 Produção e matérias-primas
- Matérias-primas: unidade (kg, g, L, un), estoque, fornecedor, estoque mínimo.
- Entrada de compras soma estoque.
- Ficha técnica: quantidade de cada matéria-prima por unidade fabricada.
- Ordem de produção: registrar X unidades fabricadas → baixa automática das matérias-primas e soma do produto acabado.
- Alertas: matéria-prima abaixo do mínimo e previsão de quando acaba; produto acabado abaixo do mínimo gera pedido de reabastecimento.
- Fica isolado num Service próprio: se o plano do Omie tiver produção, o estoque passa para o Omie.

### 6.9 Extras
- **Mensagens prontas** de WhatsApp (prospecção, reativação, cobrança) com variáveis `{nome}`, `{link_vendedor}`; abre `wa.me`.
- **Captura de leads** do site/anúncios: endpoint de formulário que cria lead no funil com UTM.
- **Ligue Talk:** botão "registrar ligação" com 1 clique (plano B) até confirmar API/webhook.
- **Gamificação:** só deixar estrutura prevista (fase final).

## 7. Integração Omie (para quando sair do Fake)
- API JSON por POST: `https://app.omie.com.br/api/v1/<grupo>/<recurso>/` com `{"call","app_key","app_secret","param":[...]}`.
- Autenticação por App Key + App Secret (chave fixa, sem OAuth). Tela de configuração para admin trocar as chaves.
- Limites: 240 req/min por IP+App Key+método, 4 simultâneas, 100 registros por página → fila com espera e retentativa.
- Sincronização incremental via cron (15–30 min) usando filtro por data de alteração.
- Pedido de venda via `produtos/pedido/` em etapa inicial configurável (orçamento/a aprovar).
- Guardar o código do Omie em cada registro local (`omie_codigo`) e usar código de integração para evitar duplicidade.

## 8. Arquivos de referência (`/referencias`)
- `dashboard.html` — Carteira Comercial V4 atual: **reaproveitar visual e lógica** (funil, drawer do cliente, mapa,
  filtros, linha do tempo). Ela tem 499 lojas da Grande Porto Alegre que viram dado de seed/importação.
  Os segmentos estão escritos de 9 jeitos diferentes → normalizar para os 4 padrão.
- `Central_TelevendasPA_V2.html` — Academia Comercial: conteúdo dos ~159 produtos e do treinamento.
  Arquivo de 11 MB por causa das imagens embutidas: **ler em partes ou por busca**, nunca inteiro.

## 9. Visual
- Seguir a identidade da Carteira atual: verde escuro, lima `#caff4d`, cards arredondados, tema claro.
- Limpo, moderno e fácil de usar; responsivo (catálogo e app são usados no celular).
- Telas de relatório com botão de imprimir e CSS de impressão em A4.

## 10. Dados simulados do protótipo
- Usuários de exemplo, um por perfil e por função, com nomes fictícios.
- Clientes: as 499 lojas da referência + amostra fictícia de outros estados.
- Pedidos e faturamento fictícios dos últimos 12 meses (para o painel e o alerta de recompra).
- Três tabelas de preço (Lojista, Distribuidor, Terceirização) com valores fictícios.
- Matérias-primas e fichas técnicas de exemplo.
- Marcar claramente na interface: "Ambiente de demonstração — dados simulados".

## 11. Uso de modelos (economizar limite do plano Pro)
| Tarefa | Modelo |
|---|---|
| Planejar módulo, banco de dados, segurança, integrações | Opus |
| Implementar telas e funcionalidades (padrão) | Sonnet |
| Migração/limpeza de dados, seeds, textos, ajustes pequenos de CSS | Haiku |
| Bug de integração que o Sonnet não resolveu em 2 tentativas | Opus |

- Uma conversa nova por módulo; use `/model` para trocar e `/compact` em sessões longas.
- Não reler arquivos grandes sem necessidade; não reescrever arquivos inteiros para mudanças pequenas.

## 12. Ordem de construção da Fase 0
1. Base: estrutura de pastas, conexão, migrations, login por usuário, perfis, layout geral.
2. Carteira e CRM (a partir de `dashboard.html`) + importação das 499 lojas.
3. Catálogo B2B (público + login do cliente + link por vendedor + carrinho com pedido mínimo).
4. Fluxo de pedidos + OmieFake + tela de aprovação do financeiro.
5. Painel de vendas, metas e alerta de recompra.
6. App PWA do externo (offline).
7. Academia (migração + biblioteca de mídia).
8. Produção e matérias-primas.
9. Prospecção (amostra RS) + mensagens prontas + captura de leads.
10. Revisão geral, dados de demonstração e roteiro de apresentação para a diretoria.

## 13. Pendências (não inventar — usar placeholder configurável)
- Resposta do Ligue Talk sobre API/webhook.
- Condições de pagamento, descontos e prazos definitivos.
- Tabela de pedido mínimo por região.
- Carteiras exportadas por vendedor.
- Lista de CNAEs dos clientes atuais.
