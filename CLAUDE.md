# CLAUDE.md — Sistema Unificado Perfeita Alquimia

> Instruções permanentes para o Claude Code. Leia este arquivo inteiro no início de cada sessão.
> Especificação completa (fonte de verdade do negócio): https://claude.ai/artifact/UVUXefvi1ZqJbV5BkFJyhM

## 1. Contexto

A Perfeita Alquimia é uma fabricante de suplementos e produtos naturais (RS) que vende B2B para lojistas,
distribuidores e clientes de terceirização/marca própria em todo o Brasil.

Este sistema substitui o **Leads2b** (CRM) e os **dois catálogos de pedido** (Catálogo Mobile e o dos externos),
e trabalha integrado ao **Omie** (ERP, no lugar do Nota10), ao **Ligue Talk** (telefonia/WhatsApp) e à **Tray** (loja virtual e marketplaces).
O sistema de RH fica **fora do escopo**.

**Situação atual: SISTEMA DEFINITIVO EM PRODUÇÃO.** O One (nome do sistema) não é mais protótipo nem apresentação:
roda na **HostGator** com os dados reais da empresa e é integrado ao **Omie** de verdade.
- Toda mudança é pensada para quem usa todo dia: nada de quebrar tela, perder dado ou mudar regra sem avisar (seção 14).
- Os dados simulados (`OmieFake`, `TrayFake`, seeds `_demo`) existem só para **testes automáticos e instalação de teste**, nunca em produção.
- Antes de mexer em algo que fala com o Omie, a Tray, o Ligue Talk ou o Google, lembrar que é o sistema real da empresa:
  testar contra o simulado (pasta `tests/recursos/`) e explicar ao usuário o que muda na integração real.

Quem mantém o projeto: uma única pessoa (designer/diretor de marketing, não programador), com Claude Code no plano Pro.
Portanto: código simples, legível, comentado em português, poucas dependências, fácil de editar.

## 2. Stack e hospedagem

- **Back-end:** PHP 8.x + MySQL/MariaDB (PDO, prepared statements sempre).
- **Front-end:** HTML + CSS + JavaScript puro (ES modules). Sem React/Vue/build step.
  Bibliotecas permitidas via CDN: MapLibre GL (mapa, já usado na Carteira), Chart.js (gráficos). Nada além disso sem perguntar.
- **Produção (definitivo):** HostGator — hospedagem compartilhada, Apache, PHP 8, MySQL, cron disponível, **sem Node nem processos permanentes**.
  Tudo que roda sozinho é **cron** (a cada 15/30/60 min ou diário); nada de fila em memória, websocket ou serviço contínuo.
  Mantém o código portável (sem depender de recurso exclusivo da HostGator), caso um dia mude de servidor.
- **App do externo:** PWA (manifest + service worker + IndexedDB) servido pelo mesmo sistema.
- Configurações sensíveis (banco) em `config/config.php`, **fora do git** (`config.example.php` versionado).
  Chaves das integrações (IA, Tray, Ligue Talk, Google Maps...) são cadastradas pela tela **⚙️ Administração** e ficam em
  `config/segredos.php` (fora do git, permissão 600, nunca mostradas de novo na tela). Nunca pedir chave ou senha no chat.

## 3. Estrutura de pastas (proposta — confirmar em modo de planejamento)

```
/public            → única pasta exposta na web (index.php, assets, catalogo/, app/ do PWA)
/app
  /Controllers     → uma classe por módulo
  /Models          → acesso ao banco
  /Services        → regras de negócio (pedido mínimo, carteira, recompra...)
  /Integrations
    /Omie          → OmieClient (real, em produção) + OmieFake (simulado, só testes) com a MESMA interface
    /LigueTalk     → idem (modo manual como plano B)
    /Tray          → TrayClient (real) + TrayFake (simulado) + TrayTexto (tradução dos dados)
  /Views           → templates PHP
/config            → config.php (fora do git), config.example.php
/database
  /migrations      → SQL numerado: 001_usuarios.sql, 002_clientes.sql...
  /seeds           → dados essenciais (idempotentes) e de demonstração (`_demo`, só instalação nova)
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
5. **Toda integração externa passa por uma interface** em `/app/Integrations`. **Produção usa a versão real**;
   a versão Fake/simulada fica para os testes automáticos e a instalação de teste. Trocar entre elas é só configuração.
   Limites das APIs (Omie, Tray) são respeitados com cópia local + cron + leitura incremental, nunca consultando a API a cada tela aberta.
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
- **Hoje:** amostra de um estado (RS). Importar a base real da Receita é uma etapa futura (pedir ao usuário antes).

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
3. Envio ao Omie como **orçamento / etapa a aprovar** (nos testes: OmieFake).
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
- **Ligue Talk:** API configurada em ⚙️ Administração › Ligue Talk; o modo manual (1 clique) continua como plano B.
- **Gamificação:** só deixar estrutura prevista (fase final).

## 7. Integração Omie (em produção)
- **Próximo passo combinado:** conferir o `OmieClient` contra o Omie real (nomes exatos de métodos e campos em developer.omie.com.br)
  e levar as chaves para uma tela em ⚙️ Administração › Omie (como Tray e Ligue Talk), com "Testar conexão".
  Hoje o Omie liga por `config.php` (`integracoes.erp = 'omie'` + app_key/app_secret).
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

## 10. Dados de demonstração (só para testes e instalação de teste)
- Ficam nos seeds `_demo` (usuários fictícios por perfil e função, lojas, pedidos de 12 meses, tabelas de preço,
  matérias-primas, fórmulas, e-commerce simulado). **Nunca rodam numa atualização** do sistema em produção.
- Os testes automáticos (`bash tests/rodar.sh`) recriam um banco de teste com esses dados; nunca rodar os testes
  apontando para o banco de produção.
- A faixa "Ambiente de demonstração" só aparece quando `modo_demo` está ligado no `config.php` (em produção: desligado).

## 11. Uso de modelos (economizar limite do plano Pro)
| Tarefa | Modelo |
|---|---|
| Planejar módulo, banco de dados, segurança, integrações | Opus |
| Implementar telas e funcionalidades (padrão) | Sonnet |
| Migração/limpeza de dados, seeds, textos, ajustes pequenos de CSS | Haiku |
| Bug de integração que o Sonnet não resolveu em 2 tentativas | Opus |

- Uma conversa nova por módulo; use `/model` para trocar e `/compact` em sessões longas.
- Não reler arquivos grandes sem necessidade; não reescrever arquivos inteiros para mudanças pequenas.

## 12. Histórico e forma de trabalhar agora
- A Fase 0 (protótipo para a diretoria) foi concluída e aprovada; o sistema virou o definitivo.
- Agora é **evolução contínua em produção**: cada pedido do usuário vira uma versão (seção 14), testada antes de entregar.
- Pedidos grandes ou módulos novos: planejamento primeiro (regra 8), depois implementação em partes com um commit cada.

## 13. Decisões já tomadas (não refazer sem o usuário pedir)
- **Tray (e-commerce):** chaves e "Conectar à loja" em ⚙️ Administração › Tray; o One guarda cópia local dos pedidos
  (cron `pedidos_ecommerce.php` a cada 15 min). Venda conta só com **pagamento confirmado** (`has_payment`), como a
  "Performance da loja" da Tray. Pedido cancelado na Tray sai da fila do Depósito em qualquer etapa antes de despachar.
  Nada de nome/CPF/e-mail/endereço do comprador no One (só cidade/UF).
- **Mapa da Carteira:** localização exata pelo **Google Maps** (chave em ⚙️ Administração › Google Maps, cron `geocodificar.php`),
  com "Ajustar no mapa" na ficha. Vendas por estado do e-commerce usam o mapa SVG do Brasil (`app/dados/brasil_estados.php`).
- **One com IA (Claude):** chave em ⚙️ Administração; **especialistas por setor** (Vendas, Prospecção, Marketing, Formulação,
  Cobrança, Terceirização) editáveis em ⚙️ Administração › Especialistas do One; só consultam, sempre com o acesso da pessoa.
- **Fórmulas** (Responsável Técnica): a fórmula **aprovada vira a ficha técnica** do produto.
- **Treinamento** por setor + tutorial do One em cada tela; conteúdo em `database/seeds/dados/` (seed novo para recarregar).
- **Modo escuro** com botão no topo; cores escuras geradas por `ferramentas/gerar_tema_escuro.py` (rodar ao mudar CSS).
- **Desenvolvedor do sistema** (V17.9): marcado em ⚙️ Administração › Desenvolvedor do sistema (coluna `usuarios.desenvolvedor`, nunca pelo nome).
  Só ele vê as telas de desenvolvimento (itens do menu com `so_desenvolvedor`: Organograma, Atualização, Importar Nota10, Zerar dados, Dados de teste;
  permissão virtual `sistema.desenvolvedor`, que nem o Admin `*` tem sozinho) e tem o seletor "ver o One como" uma função no topo.
- **Organograma — MANTER SEMPRE ATUALIZADO** (pedido do usuário): toda versão que crie, mude ou resolva uma troca de informação entre setores
  atualiza `app/dados/interligacoes.php` (setores, ligações e a situação `ok`/`parcial`/`ilha`) no mesmo commit. Ilha resolvida → `ok`.

## 13.1 Pendências (não inventar — usar placeholder configurável)
- Conferir o `OmieClient` com o Omie real e criar a tela de chaves do Omie (seção 7).
- Condições de pagamento, descontos e prazos definitivos (hoje configuráveis em Configurações).
- Tabela de pedido mínimo por região definitiva.
- Lista de CNAEs dos clientes atuais (prospecção) e importação da base real de CNPJs.
- Homologação do aplicativo na Tray (prazo de 120 dias a partir do envio das credenciais).

## 14. Versões novas com o sistema em uso (regras para não perder dados)

O sistema se chama **One** (e o mascote ajudante também). Ele é o **sistema definitivo** da empresa, na HostGator, com os dados reais
e integrado ao Omie. Toda mudança deve respeitar:

1. **Nunca editar uma migration já publicada.** Mudança no banco = arquivo NOVO numerado em `database/migrations/` (ex.: `006_...sql`).
2. **Migrations só acrescentam:** `CREATE TABLE`, `ADD COLUMN` (com DEFAULT), `CREATE INDEX`. Nada de `DROP TABLE`, `DROP COLUMN`,
   `TRUNCATE` ou `DELETE` de dados. Renomear/remover campo só em duas versões (cria o novo, copia os dados; remove o velho depois, com aprovação).
3. **Dados essenciais** (configurações novas, permissões, listas) vão em seed com `INSERT ... ` que verifica se já existe (idempotente).
4. **Dados de demonstração** vão em seed com `_demo` no nome (ex.: `007_pedidos_demo.php`): só rodam na instalação nova, nunca em atualização.
5. Publicar: `bash ferramentas/gerar_pacote.sh --atualizacao` → passo a passo em `LEIA-ME-ATUALIZACAO.md`
   (backup → extrair; na 1ª visita o One faz backup e aplica sozinho as mudanças do banco).
6. Antes de entregar: recriar o banco do zero (`php database/migrar.php`) **e** testar a atualização sobre um banco com dados.
7. **Número da versão** (pedido do usuário): toda entrega nova acrescenta uma versão no TOPO de `app/versoes.php`, com o que
   mudou (`novo`, `melhoria`, `correcao`) escrito PARA A EQUIPE: o que ela percebe no dia a dia, sem "como fazer", banco,
   backup ou telas de administração. Recado técnico vai em `admin` (só quem administra vê). Atualização pequena muda a decimal (V2.0 → V2.1);
   grande (módulo novo, mudança no jeito de usar) muda o número (V2.x → V3.0). O pacote sai como `pacote/ONE_V2.1.zip`.
   O sistema avisa todos os usuários sozinho (sino + janela "Novidades" uma vez por pessoa). Ao entregar, informar ao
   usuário só o nome da versão (ex.: ONE_V2.1) e o resumo das mudanças.
