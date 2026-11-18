# Importação do Nota10 para o One — contexto para quem for ajudar

> Resumo de tudo o que já foi decidido e construído na conversa principal do projeto.
> Leia também o `CLAUDE.md` da raiz (regras do projeto) antes de qualquer coisa.

> ## ⚠️ Situação atual: V17.5 (18/11/2026) — a importação JÁ ESTÁ PRONTA
> Este arquivo é o resumo inicial (escrito na V16.8). Depois dele vieram, nesta ordem, e **valem mais que as seções 3 a 5 abaixo**:
> 1. `docs/nota10/diagnostico.md` — análise do dump real (sem dados pessoais).
> 2. `docs/nota10/regras-importacao.md` — regras da importação, com as decisões do usuário (ex.: **tudo** do Nota10 entra, inclusive
>    contas a pagar, guardadas nas tabelas `n10_*`).
> 3. Decisões tomadas depois, na conversa principal: painéis mostram **só a Perfeita Alquimia** (pedidos de outras empresas ficam com
>    situação `outra_empresa`); título vencido de **qualquer** empresa do grupo bloqueia o boleto; a tabela "Base" vira a **Lojista**;
>    `StatusPedido` 0 Cadastrado · 1 Processando · 2 Faturado · 3 Cancelado · 4 Em separação · 5 Despachado (confirmado);
>    vendedor ativo sem cadastro no One → "Criar funcionário no One" na tela de ligações.
> 4. O código da importação: `app/Services/Nota10/Importador.php` (etapas), `Conferencia.php` (tela de ligações), `Leitor.php` (leitura).
> 5. `docs/nota10/revisao-V17.0.md` e `revisao-V17.1.md` — revisões feitas no computador do usuário e as respostas
>    (V17.1: colunas em qualquer grafia; V17.2: importar o backup da virada por cima sem duplicar e atualizando pedidos e cadastros).
> (V17.3: os títulos vencidos também abrem casos na Cobrança. V17.4: a Cobrança é montada a partir dos títulos da ficha do cliente — `titulos_receber` é a fonte única; baixa nos dois sentidos.)
> (V17.5: faturamento pela **data do pedido** em todo o One e "Outras saídas" ligadas pelo nº do pedido + cliente — ver `docs/nota10/conferencia-faturamento.md`.)
> **Próximo passo:** importar o backup real no One (V17.3) e conferir o relatório "Vindo do Nota10, no One agora" com os números
> do `regras-importacao.md` (seção 1.2) e do `diagnostico.md`.

## 1. A situação

- O **One** é o sistema da Perfeita Alquimia (PHP 8 + MySQL, HostGator). Ele substitui o Leads2b, os catálogos e, aos poucos, o Nota10 (o Omie vai ser o ERP).
- O **Nota10** é o ERP antigo. O backup do banco dele (MySQL 5.7, banco chamado **`sabor`**) foi exportado com mysqldump como `dump.txt` (~920 MB, UTF-8, fim de linha CRLF).
- O usuário quer **todos os dados** do Nota10 dentro do One: clientes reais, pedidos reais, títulos, histórico de contato, etc.
- O backup tem dados pessoais de clientes (LGPD): **nada desses dados vai para o git, para o chat ou para fora do computador/servidor**. Relatórios e conclusões, só com números, códigos e nomes de campos.

## 2. Arquivos importantes no repositório

| Arquivo | O que é |
|---|---|
| `docs/nota10/estrutura_one.sql` | Estrutura (CREATE TABLE) de **todas** as tabelas do One, sem dados |
| `database/migrations/056_nota10.sql` | Campos novos criados para receber o Nota10 (cliente, pedido, item, produto) + tabelas `cliente_contatos`, `titulos_receber`, `nota10_importacoes`, `nota10_mapas` |
| `database/migrations/057_endereco_entrega.sql` | Endereço de entrega (cópia no pedido, `entrega_revisar`) |
| `database/migrations/058_bonificacao.sql`, `059_...` | Bonificação por item (CFOP próprio) |
| `app/Services/Nota10/Leitor.php` | Lê o backup em partes para tabelas **`n10_*`** (área separada). Só as tabelas úteis (lista `TABELAS`) |
| `app/Services/Nota10/Conferencia.php` | Conferência antes de importar: contagens, problemas, CFOP → tipo de operação, palpites de ligação |
| `app/Services/EntregaService.php` | Regra do endereço de entrega e a detecção de "entregar em..." na observação |
| `app/Services/PedidoService.php` | Regras do pedido (CFOP por item: `cfop()`, bonificação, entrega) |
| `tests/recursos/nota10_amostra.sql` | Amostra **fictícia** com a estrutura real do Nota10 (para testes) |

## 3. Como o mapeamento foi planejado (Nota10 → One)

| Nota10 | One |
|---|---|
| `clientes` (114 campos) | `clientes` (+ campos da migration 056). O registro original inteiro vai em `clientes.nota10_dados` (JSON). Chave: `nota10_numero` |
| `clientes` com tipo fornecedor / transportadora | **separar**: fornecedores vão para Compras › Fornecedores; transportadoras à parte (`TipoCliente` → ligação escolhida na tela) |
| `contatos` | `cliente_contatos` (com aniversário dia/mês) |
| `usuarios` / `Representante` / `CodigoVendedor` | ligados a pessoas do One na tela (tabela `nota10_mapas`, tipo `vendedor`). Sem pessoa → fila de Prospecção e Reativação |
| `notas` + `itensnota` | `pedidos` + `pedido_itens` (situação faturado/cancelado; `tipo_operacao` pelo CFOP; NF, série, chave, frete, impostos; custo, lote e validade no item). Chave: `pedidos.nota10_guid` |
| `nfref` / `eventosnfe` | devolução / NF cancelada |
| `produtos` + `precos` + `tabelaprecos` | `produtos` (casar pelo **código**; os produtos do One NÃO são apagados). Produto que só existe no Nota10 entra **inativo e fora do catálogo** |
| `contaspagrec` com Tipo **R** | `titulos_receber` (alimenta Cobrança e "título em atraso") |
| `contaspagrec` com Tipo **P** e `centros` | **não trazer** (decisão do usuário) |
| `followup` | linha do tempo (`cliente_eventos`) + próximos contatos abertos viram `tarefas` |
| `estoques`, `logestoque`, `producao`, `composicao`, `compras`, `itenscompra`, `itemfornecedor` | estoque acabado/lotes, ordens de produção (histórico), fichas técnicas, compras |
| PDV/balcão (`comandas`, `movcaixa`, ...), fiscais (`ibpt`, `icms`...), config | não trazer |

## 4. Decisões do usuário (não mudar sem perguntar)

1. **Contas a pagar: não.** Só contas a receber.
2. **Fornecedores e transportadoras: separar** do cadastro de clientes.
3. **Produtos só do Nota10: criar inativos e fora do catálogo** (para o histórico de pedidos ficar completo).
4. **Todos os anos** de pedidos.
5. **Bonificação: na mesma nota**, CFOP por item (venda 5102/6102, bonificação 5910/6910, pela UF de **entrega**), até **15%** sem aprovação. Bonificação não conta no faturamento nem na meta (`pedido_itens.total = 0`, valor em `valor_bonificado`).
6. **Endereço de entrega: um por loja.** Quando o Nota10 tem os campos `entrega_*` preenchidos, vêm direto. Quando o endereço está **só escrito na observação**, o One **não adivinha**: marca `clientes.entrega_revisar` com o trecho, e alguém confirma em Carteira › Endereços de entrega.
7. Antes de importar, o One **zera** os dados do dia a dia (ferramenta Zerar dados, V15.1), mantendo usuários, configurações, produtos, catálogo, Academia/treinamento.

## 5. O que falta (próximo passo)

- **Entender os dados reais do dump**: como o Nota10 marca pedido x orçamento x nota (campos `StatusPedido`, `Status_NF`, `Prevenda`, `PedidoCancelado`, `Nota_S`, `Nota_E`), quais CFOPs aparecem (inclusive substituição tributária 5405/6404), os valores de `TipoCliente`, formato das datas (são texto: `aaaa-mm-dd` ou `dd/mm/aaaa`), quantos cadastros por tipo, vendedores, segmentos (`IdSegmento`, tabela `apoio`), endereços de entrega na observação.
- **Escrever a importação de verdade** no One (será a próxima versão): das tabelas `n10_*` para as tabelas do One, idempotente (pode rodar de novo sem duplicar, pelas chaves `nota10_*`), em partes (limite de tempo da HostGator), com relatório final.

## 6. Como ajudar sem quebrar nada

- **Quem analisa o dump** (no computador do usuário): devolva um **relatório sem dados pessoais** — contagens, códigos, valores distintos de campos de situação/tipo, CFOPs, formatos de data, % de campos preenchidos, nomes dos vendedores (são funcionários, não clientes) — e o que o usuário decidiu. Salve como `docs/nota10/diagnostico.md` e o usuário leva para a conversa principal.
- **Não** gere CSVs com dados de clientes para subir no git. **Não** importe direto no banco da HostGator por fora do One: a importação passa pela tela Administração › Importar Nota10, que respeita as regras (auditoria, backup antes, não duplicar).
- O código do One é mantido na conversa principal (branch `ccr-12815ad2-flpiv1`). Se for mexer no código, faça na mesma branch e avise, para não haver duas versões diferentes.
