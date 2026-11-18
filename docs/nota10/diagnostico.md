# Diagnóstico do dump do Nota10 (para a importação no One)

> Gerado em 18/11/2026 a partir do `dump.txt` de 17/11/2026 18:40 (MySQL 5.7.43, banco `sabor`, 965 MB).
> Segue a seção 5 do `docs/nota10/LEIA-PRIMEIRO.md`.
> **Sem dados pessoais**: só contagens, códigos, valores distintos de campos de situação/tipo, CFOPs, formatos de data,
> % de preenchimento. Os vendedores aparecem pelo código e primeiro nome (são funcionários). CNPJs citados são das
> empresas emitentes do próprio grupo (pessoa jurídica), não de clientes.
> Data de referência para "vencido"/"há X dias": **17/11/2026** (data do backup).

> ## ⚠️ Decisão geral do usuário (18/11/2026): **TODOS os dados do Nota10 vão para o One**
> "Todos os dados do Nota10 têm que ir para o One, porque é a nossa base de dados."
> - **Nada é descartado nem filtrado**: todas as empresas emitentes, todos os anos, todos os tipos de cadastro, contas a receber
>   **e a pagar**, follow-up, eventos de NF-e, estoque, logs e também os itens sem nota.
> - O que tem lugar numa tela do One vai para a tabela certa (clientes, pedidos, títulos…). O que **não tem tela ainda**
>   (contas a pagar, centros de custo, contas bancárias, logs, configuração, itens órfãos, tabelas fiscais) fica **guardado inteiro
>   nas tabelas `n10_*`** do One, para consulta e para virar tela depois. Nenhuma linha do dump fica de fora.
> - Isso **muda decisões antigas** do `LEIA-PRIMEIRO.md` (seção 4): a nº 1 ("contas a pagar: não") e a lista de "não trazer" da seção 3.
>   A `Leitor.php` precisa ler **todas** as tabelas com dados (hoje lê só a lista `TABELAS`).
> - Este diagnóstico continua sem dados pessoais porque é um relatório que vai para o git/chat; **os dados em si vão completos
>   para o One** pela tela Administração › Importar Nota10.

---

## 0. Resumo em 10 linhas

1. O dump lê sem erro: as 28 tabelas com dados têm o número de colunas certo em todas as linhas.
2. **Clientes: 57.188 cadastros, mas 49.074 (86%) são `P` = prospects**, quase todos com CNPJ e sem compra. Clientes de verdade (`C`) são **7.064**.
3. **Vendedor = cadastro tipo `R` dentro da tabela `clientes`** (não é `usuarios`). `notas.codigovendedor` e `clientes.representante` apontam para `clientes.numero`.
4. **Pedidos/notas: 61.370**, de 12/2020 a 17/11/2026. Venda (`V`) = 48.524. Só 58,5% têm número de NF de saída.
5. **Há 2 empresas emitentes principais** (37.285 e 23.164 notas) e mais 2 pequenas. **Decidido: todas entram**, com o emitente gravado em cada pedido e título.
6. **80.098 itens (11.877 GUIDs, R$ 13,3 mi) apontam para notas que não existem** (a maioria alterada em 2023). Não dá para ligá-los a um pedido: ficam guardados em `n10_itensnota` e aparecem no relatório da importação.
7. Contas a receber (`R`): 100.584 títulos; **2.207 abertos e vencidos (R$ 2,02 mi), a maioria de 2021–2023**. Se entrarem como "aberto", bloqueiam boleto desses clientes.
8. `chaveguid` dos títulos é **único e sempre preenchido**: serve como `titulos_receber.nota10_guid`.
9. Todas as datas estão em texto `aaaa-mm-dd` (algumas com hora); poucas exceções listadas na seção 9. Não há `dd/mm/aaaa` nos campos de data principais.
10. Tabelas que o plano previa usar e **estão vazias** no dump: `contatos`, `nfref`, `compras`, `itenscompra`, `producao`, `composicao`, `orcamentos`.

---

## 1. Tabelas e quantidades

> Onde a tabela abaixo diz "não trazer" ou "não precisa": pela decisão geral, **entra mesmo assim**, guardada em `n10_<tabela>`.

| Tabela | Linhas | Uso previsto no One |
|---|---:|---|
| `clientes` | 57.188 | clientes / leads / fornecedores / transportadoras / vendedores (separar por `TipoCliente`) |
| `notas` | 61.370 | `pedidos` |
| `itensnota` | 484.350 | `pedido_itens` |
| `contaspagrec` | 132.654 | `titulos_receber` (só tipo R: 100.584) |
| `produtos` | 3.003 | `produtos` |
| `precos` | 2.843 | tabela de preço |
| `tabelaprecos` | 1 | só a tabela "Base" (número 1, padrão) |
| `estoques` | 3.002 | estoque acabado (1 linha por produto, **sem lote**) |
| `grupos` | 44 | grupos de produto |
| `formapgto` | 536 | formas/condições de pagamento (todas `Ativo=1`) |
| `followup` | 450 | linha do tempo / tarefas |
| `eventosnfe` | 4.350 | cartas de correção e cancelamentos de NF-e |
| `usuarios` | 49 | ligar a pessoas do One (17 ativos) |
| `contas` | 36 | contas bancárias (não trazer) |
| `centros` | 146 | centros de custo (não trazer — só usados em contas a pagar) |
| `cidades` | 5.573 | tabela IBGE (não precisa) |
| `apoio` | 2 | nomes dos **canais** (ver seção 3.4) |
| `impostos` / `icms` | 43 / 27 | fiscal (não trazer) |
| `logestoque` | 366.466 | histórico de movimento de estoque (2021–2026) |
| `logsistema` | 100.893 | log de telas — **só 2026** |
| `logsql` | 173.555 | log de comandos SQL — **só 2026**, contém dados pessoais em texto livre |
| `configuracao` | 1.040 | não trazer |
| `perfisimpressoras`, `licencas`, `emailstemplates`, `equivalencia`, `itemfornecedor` | 16 / 3 / 1 / 5 / 1 | não trazer |

**Vazias (0 linhas):** `contatos`, `nfref`, `compras`, `itenscompra`, `producao`, `composicao`, `orcamentos`, `os`, `comandas`,
`movcaixa`, `itenscancelados`, `romaneios`, `locacoes`, `mensalidades`, `inventario`, `listas`, `posicoesvenda`, `nfse`,
`emails`, `emailsaenviar`, `etiquetas`, `estatisticas`, `ibpt`, `interface`, `terminal`, `copias`.
→ **Contatos, fichas técnicas, ordens de produção e compras não existem no Nota10 desta empresa.** As linhas da tabela de mapeamento
do LEIA-PRIMEIRO para essas tabelas podem ser ignoradas na importação.

---

## 2. Clientes (`clientes`, 57.188)

### 2.1 `TipoCliente`

| Valor | Qtde | Interpretação provável | Com pedido `V` |
|---|---:|---|---:|
| `P` | 49.074 | **Prospect** (base de CNPJs; 48.771 com CNPJ) | 19 |
| `C` | 7.064 | Cliente | 6.605 |
| `R` | 493 | **Representante/vendedor** (153 CPF, 52 CNPJ, 287 vazio) | 190 |
| `F` | 459 | Fornecedor | 11 |
| `CP` | 36 | Cliente + prospect? | 9 |
| `CF` | 28 | Cliente + fornecedor | 27 |
| `T` | 24 | Transportadora | 0 |
| `TC` | 5 | Transportadora + cliente | 5 |
| `RC`, `TF`, `TR` | 1 cada | combinações | — |
| `ATIVO`, `63.652` | 1 cada | **erro de digitação** | — |

`ativo`: S = 55.837, N = 1.351. `Status`: vazio em 100% (não usar). `situacao`: Liberado 4.441, Bloqueado 606 (8,8% preenchido).

Por tipo e ativo: C (S 6.383 / N 681), P (S 48.868 / N 206), R (S 50 / N 443), F (S 458 / N 1).

### 2.2 Documento (`cpf_cnpj`)

| | Qtde |
|---|---:|
| CNPJ (14 dígitos) | 56.107 |
| CPF (11 dígitos) | 513 |
| vazio | 567 |
| outro tamanho | 1 |

Documentos repetidos: **4 documentos** aparecem em mais de um cadastro (8 cadastros no total) → a deduplicação por CNPJ do One resolve.

### 2.3 Preenchimento dos campos

| Campo | % preenchido | Obs. |
|---|---:|---|
| `nome`, `razao` | 100% | |
| `endereco` | 99,0% | endereço inteiro numa linha; `endlog` 43%, `endnum` 46%, `endcomp` 17% |
| `bairro` / `cidade` / `estado` | 98,8% / 98,9% / 99,0% | `estado` tem **13 cadastros com UF inválida** (RI, VI, LO, BL, DU, CA, FR, ME, C, aspas) |
| `codmunic` (IBGE 7 díg.) | 98,7% | 56.430 válidos |
| `cep` | 44,1% | 23.857 com 8 dígitos |
| `telefones` / `celular_1` / `celular_2` / `fax` | 53,7% / 9,9% / 1,3% / 8,4% | 30.964 cadastros com algum telefone |
| `email` / `email_NFe` / `email_fin` | 30,9% / 13,4% / 0,2% | |
| `contato` | 97,5% | nome da pessoa de contato (vai para `cliente_contatos`) |
| `rg_ie` | 18,4% | 63 "ISENTO" |
| `tipocontribuinte` | 100% | 9 = 53.794, 1 = 3.341, 2 = 53 |
| `obs` / `Ocorrencias` / `refer` | 58,3% / 19,7% / 2,0% | texto livre |
| `website` | 54,8% | |
| `datnas` | 1,5% | `aaaa-mm-dd` |
| `entrada` / `ultima` (1ª/última compra) | 11,6% / 11,6% | `aaaa-mm-dd`, 2020–2026 |
| `limitecredito` > 0 | 8,6% | |
| `codigoexterno` / `GuidExterno` | 98,2% / 97,0% | origem externa (provável importação da base de prospecção) |
| `cfop` (CFOP padrão do cliente) | 3,1% | 5405 = 984, 6102 = 434, 6404 = 162, 5102 = 135, 6101 = 42, outros 11 |
| `transportadora1` | 5,0% | aponta para `clientes.numero` (1230 = 1.806, 1235 = 972, …) |
| `IdSegmento` | **0%** | não usar |
| `segmento1` ≠ 0 | 8,4% | 2 = 2.471, 3 = 2.341, 4 = 1 |
| `segmento2` ≠ 0 | 8,4% | 3 = 1.381, 5 = 1.227, 4 = 1.154, 2 = 1.051 |
| `Grupo` | 1,0% | B 277, A 115, D 83, C 70, F 2, FIT 1, S 1 (curva ABC?) |
| `IdCanal` ≠ 0 | 8,6% | 2 = 4.341, 1 = 587, 3 = 1 |
| `carteira` ≠ 0 | 28,1% | ver seção 3.3 |
| `codigoregiao`, `desconto`, `taxa`, `Taxaentrega`, `convenio`, `Mensalidade`, `Recorrencia`, `CST`, `verificado`, `inscricaomunicipal`, `contatocomplementar_*` | 0% útil | ignorar |

**Não existe tabela de nomes para `segmento1`/`segmento2`/`Grupo`** no dump (nem em `apoio`, nem em `configuracao`). Ver decisões.

### 2.4 Endereço de entrega

| Situação | Cadastros |
|---|---:|
| Campos `entrega_*` preenchidos | **145** (0,3%) — UF: RS 39, SP 38, PR 15, SC 11, MG 9, outras 33 |
| Palavra "entreg" em `obs`/`Ocorrencias`/`refer`, **sem** campos `entrega_*` | **166** → candidatos a `entrega_revisar` |
| Palavra "entreg" na obs **e** campos preenchidos | 80 |
| Notas com "entreg" em `notas.Obs` | 628 |

### 2.5 Recência de compra (pedidos `V` não cancelados)

6.743 cadastros têm pelo menos um pedido `V`. Última compra (só `TipoCliente = C`):

| Há | Clientes C |
|---|---:|
| 0–30 dias | 209 |
| 31–60 | 148 |
| 61–90 | 126 |
| 91–180 | 303 |
| 181–365 | 371 |
| 1–2 anos | 805 |
| mais de 2 anos | 4.521 |

→ ~1.157 clientes compraram no último ano; ~5.300 vão direto para a fila de Prospecção e Reativação se a regra for "sem compra há X dias".

---

## 3. Vendedores, carteiras, canais

### 3.1 Onde está o vendedor

- `notas.codigovendedor` → `clientes.numero` com `TipoCliente = R` em **53.065 notas**; = 0 em 7.326; aponta para tipo C em 877, F em 16, P em 3, RC em 2. 447 códigos distintos.
- `clientes.representante` → tipo R em 10.148 clientes; **tipo C em 45.353** (quase tudo o código 57207); 0/inexistente em 1.685.
- `itensnota.codigovendedor` é sempre 0 (não usar).
- `usuarios.Vendedor` (números 3–10) e `clientes.carteira` usam a **mesma numeração** (ver 3.3), que é diferente do código R.
- Os nomes dos cadastros R terminam em "FUNCIONÁRIO(A)"; há também R compartilhados ("BEATRIZ/RUBENS", "JOAO E GISIANE"…) e 2 representantes externos (empresas).

### 3.2 Códigos de vendedor com venda desde 2025 (para a tela de ligação `nota10_mapas`)

| Código | Primeiro nome | Ativo no N10 | Notas | Última |
|---|---|---|---:|---|
| 1286 | Ronaldo | S | 4.650 | 17/11/2026 |
| 1237 | José | N | 4.413 | 29/01/2026 |
| 55119 | Beatriz | N | 3.048 | 31/07/2026 |
| 55388 | Rubens | N | 2.718 | 02/07/2025 |
| 3666 | Fabrício | N | 2.193 | 11/06/2026 |
| 4548 | Gisiane | N | 1.916 | 01/10/2026 |
| 56310 | Márcio C. | N | 1.902 | 13/11/2026 |
| 55857 | Keila | N | 1.512 | 28/09/2026 |
| 269 | Derli (cadastro "- CLIENTE") | S | 1.503 | 16/11/2026 |
| 4549 | Jennifer | N | 1.205 | 02/06/2025 |
| 57949 | João Batista | S | 857 | 17/11/2026 |
| 55168 | Ricardo | N | 770 | 19/02/2025 |
| 1962 | Derli | S | 757 | 17/11/2026 |
| 58250 | Jeina | S | 743 | 17/11/2026 |
| 56868 | Ademar | N | 637 | 31/01/2025 |
| 1245 | Keila (2º cadastro) | N | 622 | 11/08/2026 |
| 1960 | Márcio P. | N | 580 | 09/10/2026 |
| 58094 | Jéssica V. | N | 504 | 05/10/2026 |
| 4054 | Jéssica M. | N | 382 | 25/02/2025 |
| 55249 | Elisandra | N | 365 | 18/07/2025 |
| 58437 | "Zé e Vanessa" (compartilhado) | S | 276 | 29/09/2026 |
| 1236 | Jéssica B. | N | 183 | 09/10/2025 |
| 56198 | Gabriel | N | 136 | 20/07/2026 |
| 58531 | Gabriela | S | 134 | 17/11/2026 |
| 58529 | Yasmim | S | 125 | 17/11/2026 |
| 56806 / 56588 / 56726 / 56879 / 56891 / 56961 / 57072 / 57993 / 58025 / 58448 | compartilhados (dois nomes) | — | 1–125 | 2025–2026 |
| 1495, 58316, 58262, 58530, 58151, 58402, 58459, 56268, 58213, 58214, 56618, 58434, 58506 ("Vendas Marketing") | outros funcionários | — | 1–83 | 2025–2026 |
| 58162, 56658 | representantes externos (empresa) | N | 15 / 1 | 2026 |

Carteira de clientes (`clientes.representante`) mais usada: **57207 (Renata, cadastro tipo C, inativo) com 45.255 cadastros** — praticamente todos os prospects P. Depois 55249 (1.824), 58529 (1.689), 58250 (1.552), 58531 (1.305), 58213 (401), 58151 (393), 58214 (369), 56868 (342), 57949 (269), 1286 (233).

### 3.3 `clientes.carteira` × `usuarios`

| carteira | Cadastros (C / P / outros) | Usuário com esse número |
|---|---|---|
| 0 | 852 / 38.947 / 557 | — |
| 1 | 1.113 / 7.460 / 31 | nenhum |
| 2 | 742 / — / 3 | nenhum |
| 3 | 1.012 / 3 / 7 | Vendedor=3 → Jennifer |
| 4 | 703 / — / 1 | Vendedor=4 → Gisiane |
| 5 | 1.344 / — / 7 | Vendedor=5 → Keila |
| 6 | 62 / 2.222 / 8 | Vendedor=6 → Beatriz |
| 7 | 109 / 3 / 2 | Vendedor=7 → Renata; carteiras=7 → Jéssica V. |
| 8 | 76 / 1 / — | Vendedor=8 → Deise; carteiras=8 → Miriel, Marivana |
| 10 | 366 / — / — | Vendedor=10 → Buonocore |
| 4548 | 651 / 109 / 18 | igual ao código R 4548 (Gisiane) |

### 3.4 Canal (`IdCanal`)

Tabela `apoio` (tabela = "canais"): **id 2 = PERFEITA ALQUIMIA, id 3 = TERCEIRIZAÇÃO**. O id 1 não tem nome no dump.
Uso: clientes (2 = 4.341, 1 = 587, 3 = 1); notas (2 = 27.376, 1 = 16.606, 3 = 450, 0 = 14.523); produtos (3 = 195, 2 = 69).

### 3.5 Usuários do Nota10 (49)

Ativos (17): SISTEMA/CALLCENTER/PRODUCAO/DEPOSITO (genéricos), Letícia, Fabiana, Alexandre, Derli, Lucas D., Juliano, Taís, Maria Eduarda,
João Batista, Douglas Camargo, Miriel, Jeina, Ismael, Manuela, Yasmim. `notas.usuario` (quem digitou) tem 71 códigos distintos,
alguns que não existem mais em `usuarios` (ex.: 999 = 26 notas).

---

## 4. Pedidos / notas (`notas`, 61.370)

### 4.1 Por ano (`data`, sempre `aaaa-mm-dd`)

| 2020 | 2021 | 2022 | 2023 | 2024 | 2025 | 2026 (até 17/11) |
|---:|---:|---:|---:|---:|---:|---:|
| 11 | 4.668 | 10.691 | 16.111 | 10.903 | 11.614 | 7.372 |

### 4.2 `tipooperacao`

| Valor | Qtde | Provável significado (pelo CFOP/natureza) |
|---|---:|---|
| `V` | 48.524 | **Pedido de venda** (com ou sem NF) |
| `E` | 5.217 | Entrada (compras; 2.948 são "ALTERACAO MANUAL DE ESTOQUE", sem CFOP) |
| `S` | 5.068 | **NF de saída avulsa**: venda 6102/5102/6101, bonificação 6910/5910, amostra 6911, outras 6949 |
| `O` | 1.835 | só 2025–2026, sem NF, StatusPedido 0 → **orçamento?** (R$ 2,76 mi) |
| `C` | 591 | só 2025–2026, com `nota_e`, status_nf R → **compra/NF de fornecedor recebida?** |
| `D` | 113 | devolução (CFOP 5405/6102/2201/5202…) |
| `F` | 21 | devolução a fornecedor (6202/2202/5202/6556) |
| vazio | 1 | lixo |

Emitente × tipo: o emitente **15652740000180** tem todos os O e C; os dois principais têm V, S, E, D, F.

### 4.3 Empresa emitente (`notas.emitente`; o mesmo campo existe em `contaspagrec.Emitente`)

| Emitente | Notas | Títulos (pagrec) |
|---|---:|---:|
| A — 15.652.740/0001-80 | 37.285 (V 28.757) | 64.978 |
| B — 34.773.706/0001-08 | 23.164 (V 18.902) | 62.505 |
| C — 00.949.190/0001-98 | 867 (V 847) | 2.186 |
| D — 24.913.026/0001-99 | 31 | 67 |
| 16 outros (1–4 notas cada, só `E`) | 23 | — |

As contas bancárias têm siglas "ALQ", "SABOR" e "FRAN", o que indica mais de uma empresa do grupo no mesmo banco `sabor`.

### 4.4 Campos de situação

| Campo | Valores |
|---|---|
| `StatusPedido` | 5 = 26.962 · 4 = 8.598 · 0 = 5.688 · 2 = 63 · 1 = 54 · 3 = 36 · vazio/NULL = 19.969 |
| `status_nf` | E = 35.337 · R = 2.007 · C = 284 · D = 7 · vazio = 23.735 |
| `PedidoCancelado` | 0 = 60.938 · 1 = 432 |
| `prevenda` | **sempre 0** (não usado) |
| `modelo` | **sempre NULL** (não usado) |
| `Serie` | 1 = 56.066 · 0 = 4.464 · 2 = 840 |
| `nota_s > 0` | 35.905 (58,5%) |
| `nota_e > 0` | 2.025 (3,3%) |
| `finNFe` (12,6% preenchido) | 1 = 5.980 · 0 = 1.725 · 4 = 5 |
| `codigo_crt` | 3 = 47.348 · 1 = 11.335 · 0 = 2.687 |
| `tabelaprecovenda` | 1 = 58.411 · 0 = 2.950 · −1 = 9 |
| `origem` | 0 = 58.419 · 9 = 2.951 |
| `IdCanal` | ver 3.4 |

Leitura provável de `status_nf`: **E = emitida/autorizada** (tem chave `prefnfe` e protocolo; 30.476 também com XML), **R = recebida** (NF de terceiro, entradas),
**C = cancelada** (254 com protocolo de cancelamento + XML), **D = denegada** (7).

### 4.5 Pedidos de venda (`V`) — combinações que importam

| StatusPedido | status_nf | nota_s>0 | PedidoCancelado | Qtde | Total (R$) | Sugestão de `pedidos.situacao` |
|---|---|---|---|---:|---:|---|
| 5 | E | sim | 0 | 16.018 | 26.043.423 | faturado |
| NULL | E | sim | 0 | 11.357 | 13.203.585 | faturado |
| 4 | E | sim | 0 | 3.977 | 5.131.214 | faturado |
| 0 / 2 / 3 | E | sim | 0 | 73 | — | faturado |
| **5** | vazio | **não** | 0 | **10.342** | **6.832.672** | ? (pedido concluído **sem NF**) |
| **NULL** | vazio | não | 0 | **3.470** | **3.475.714** | ? |
| **4** | vazio | não | 0 | **2.577** | **913.462** | ? |
| 0 / 1 / 2 / 3 | vazio | não | 0 | 89 | — | ? (em aberto/orçamento) |
| qualquer | C | sim | 0 ou 1 | ~200 | — | cancelado |
| qualquer | vazio | não | 1 | 66 | — | cancelado |
| qualquer | **E** | sim | **1** | **337** | ≥ 446.000 | ⚠️ pedido marcado cancelado **com NF autorizada e não cancelada** |

Outras inconsistências:
- `status_nf = C` (NF cancelada) com `PedidoCancelado = 0`: **256 notas**.
- 46 notas sem nenhum item (21 entradas, 11 S, 7 O, 4 V…).
- 3.869 notas sem cliente (`numerocliente` 0/NULL; quase todas `E`), 4 com cliente inexistente.

### 4.6 CFOPs

**Cabeçalho** (`notas.cfop`, 68% preenchido; vazio em ~11.600 V e 4.701 E):
6102 = 15.750 · 5102 = 8.997 · **5405 = 6.941** · 6101 = 4.750 · 6910 = 2.089 · 5101 = 1.364 · **6404 = 841** · 5910 = 369 · 6949 = 121 · 6911 = 106 ·
2949 = 83 · 5949 = 34 · 6201 = 32 · 1949 = 29 · 6202 = 29 · 2201 = 26 · 6104 = 21 · 2202 = 19 · 1201 = 12 · mais 24 CFOPs com < 10.

**Itens** (`itensnota.cfop`, 69,4% preenchido):
6102 = 126.990 · 5405 = 79.298 · 5102 = 63.769 · 6101 = 37.085 · 5101 = 9.549 · 6404 = 6.545 · **6910 = 6.521** · 6911 = 1.369 · **5910 = 1.255** · 6949 = 1.185 · 2949 = 798 · mais 34 CFOPs.

- **Substituição tributária** existe e é grande: 5405 (dentro do RS) e 6404 (fora). O One hoje só prevê 5102/6102 + 5910/6910.
- **Bonificação no Nota10 era nota separada**: só 1 nota mistura venda e bonificação (5405 + 5910). As notas com bonificação são V (1.674) ou S (777) com CFOP 5910/6910 no cabeçalho.
- Notas com mais de um CFOP nos itens: quase sempre "vazio + CFOP" (vazio + 5405 = 96, vazio + 5102 = 27).
- `natureza` "ALTERACAO MANUAL DE ESTOQUE" = 2.948 notas (tipo E, sem CFOP): **não são pedidos**, são ajuste de estoque.

### 4.7 Outros campos do pedido

| | Notas |
|---|---:|
| Com desconto > 0 | 20.319 |
| Com frete > 0 | 317 |
| Com ST > 0 (cabeçalho) | 2 |
| Com IPI > 0 | 43 |
| Com código de rastreio | 5 |
| Com NF referenciada (`refchavenfe`/`refnota`) | 330 (D 95, E 141, S 63, F 19, V 12) |
| `datasaida` preenchida | 28,8% (2023–2026) |
| Transportadora (`codigotransportadora` ≠ 0) | 52,7% — 1230 = 16.404, 1235 = 8.624, 1243 = 2.267 (são `clientes.numero`) |
| Forma de pagamento | 520 códigos usados; 2 "30 DIAS" = 33.333, 99 "SEM PGTO" = 7.614, 0 = 2.548, 7 "30/45" = 2.229, 1 "A VISTA" = 1.688, 8 "30/45/60" = 1.427, 106 "PIX" = 852 |

---

## 5. Itens (`itensnota`, 484.350)

| Verificação | Resultado |
|---|---:|
| **Itens cuja nota não existe** | **80.098 itens / 11.877 GUIDs / R$ 13,3 mi** (DataAlteracao: 2023 = 8.036 GUIDs, 2024 = 3.255, 2022 = 218, 2025 = 272, 2026 = 96). Nenhum tem título; 50 aparecem no `logestoque`. → **guardar em `n10_itensnota`** (sem pedido) |
| Itens com produto inexistente | 21 itens (2 códigos) |
| Produtos nunca vendidos | 53 |
| Itens com custo > 0 | 329.671 (68%) |
| Itens com desconto > 0 | 23.904 |
| Itens com lote | 56 · com validade: quase nenhum |
| `unidade` | UNID 481.786 · KG 1.456 · MIL 132 · outras 15 (com lixo: "136,00", "2.000,00") |
| `cst` | 00 = 252.314 · 60 = 76.238 · 51 = 19.863 · 20 = 12.624 · 060 = 5.910 (mesmo código com 2 e 3 dígitos) |
| `csosn` (10,9%) | 102 = 34.184 · 101 = 16.214 |
| Campos da reforma (IBS/CBS) | só 5,7% (notas de 2026) |

---

## 6. Produtos, preços e estoque

- **3.003 produtos**; `codigoproduto` numérico de 1 a 6 dígitos (4 dígitos = 1.424; 3 = 882; 6 = 582), **sem duplicados** → casar com o One pelo código é seguro.
- `Ativo`: 1 = 2.324, 0 = 679. `ForaDeLista`: 1 = 2.374.
- EAN com 13 dígitos: 912; 43 EANs repetidos em mais de um produto. NCM com 8 dígitos: 2.077 (926 vazios).
- `fabricante` 21,9% preenchido (PERFEITA ALQUIMIA escrito de 4 jeitos).
- **Grupos** (`grupos`, 44): além dos produtos acabados (CAPSULAS 263, LIQUIDOS 111, CHÁS por gramatura…), há **INSUMOS (366), ROTULOS (179), EMBALAGEM (174)**, material de escritório/limpeza, EPIs, imobilizado. Esses **não são produto de venda**: candidatos a matéria-prima (Produção) ou a ficar de fora. 890 produtos sem grupo.
- `tipoitem`: 0 = 2.449 · 1 = 217 · 10 = 182 · 2 = 154 · 99 = 1.
- **Preços**: só existe a tabela **1 "Base"** (2.841 preços) + 2 na tabela 0; 13 preços de produto inexistente.
  **Não há tabelas Lojista/Distribuidor/Terceirização no Nota10.**
- **Estoque**: 3.002 linhas, uma por produto, **nenhuma com lote**; quantidade > 0 em 1.822.

---

## 7. Contas a receber (`contaspagrec` tipo R, 100.584)

| Situação (calculada) | Qtde | Valor líquido (R$) |
|---|---:|---:|
| pago (`datapagamento` preenchida) | 96.140 | 58.084.848 |
| aberto, a vencer | 1.339 | 1.153.748 |
| **aberto, vencido** | **2.207** | **2.017.078** |
| aberto sem vencimento | 7 | 0 |
| cancelado (`Cancelado=1` ou `DataCancelamento`) | 891 | 1.091.915 |

Vencidos em aberto por ano de vencimento: 2020 = 21 · 2021 = 141 · 2022 = 463 · **2023 = 886** · 2024 = 211 · 2025 = 165 · 2026 = 320.

- `chaveguid`: 100% preenchido e **único** → chave `titulos_receber.nota10_guid`. 
- Ligação com o pedido (`guid_nota`): 97.151 encontram a nota · 125 apontam para nota inexistente · 3.308 sem nota.
- Ligação com o cliente (`credordevedor` → `clientes.numero`): 100.572 ok · 3 inexistentes · 9 vazios.
- `numeroparcela` = 0 em 72% (parcela única ou não informada); até 138 valores.
- `StatusBanco`: 0 = 93.179 · 6 = 38.589 · 2 = 879 (boleto enviado/registrado). `dataenviobanco` 39,7%.
- 30 títulos pagos com valor ≤ 0.
- `FormaPgto` 530 códigos (mesma tabela `formapgto`). `siglaconta`: 36 contas (CAP ALQ 36.771, BAN ALQ 21.950, SANT SABOR 17.989…).
- Contas a pagar (P, 32.070: pagos 30.031 = R$ 58,0 mi · abertos a vencer 1.428 = R$ 5,7 mi · abertos vencidos 593 = R$ 6,9 mi ·
  cancelados 17): **agora entram** (decisão geral). O One ainda não tem tabela de contas a pagar → guardar em `n10_contaspagrec`
  (ou criar `titulos_pagar` numa migration nova). `centro` (23,8% preenchido, 139 códigos) aponta para `centros.codigo`.

---

## 8. Follow-up, eventos NF-e e logs

- **`followup`** (450, de 2021 a 2024): 446 ligados a cliente existente; 91 fechados; **359 abertos com próximo contato no passado; nenhum com data futura**.
  `usuario` = 0 em 197. → viram eventos na linha do tempo; não há próximos contatos futuros para virar tarefa.
- **`eventosnfe`** (4.350): carta de correção (`CC`, 110110) = 4.053–4.064 · cancelamento (`CANC`, 110111) = 286. Status 135 (registrado) = 4.346.
- **`logestoque`** (366.466, 2021–2026): tipo V 335.072, S 14.802, E 14.357, C 1.841, D 302, O 80, F 12. Tem saldo após cada movimento.
- **`logsistema`** (100.893) e **`logsql`** (173.555): **só 2026** (o Nota10 apaga os antigos). `logsql.comando` guarda SQL com dados pessoais em texto livre.

---

## 9. Formato das datas (todas são texto)

| Campo | Formato | Exceções |
|---|---|---|
| `notas.data`, `data_nf`, `datasaida` | `aaaa-mm-dd` | `data_nf`: 3 anos inválidos (2005, 206, 2302) |
| `notas.dataautorizacao` | `dd/mm/aaaa hh:mm:ss` (33.792) ou `dd/mm/aaaa` (1.845) | |
| `notas.dataautoriza_canc` | `dd/mm/aaaa[ hh:mm:ss]` | 7 com texto |
| `notas.DataAlteracaoItens` | **misto**: `aaaa-mm-dd` (30.470) e `dd/mm/aaaa hh:mm:ss` (28.342) | |
| `contaspagrec.datavencimento` | `aaaa-mm-dd` | ~26 com ano inválido (202, 203, 206, 221–225, 2000, 2002) e 1 vazio |
| `contaspagrec.datapagamento` | `aaaa-mm-dd` | ~14 com ano inválido (1202, 1220, 202, 206, 2062, 2202…) e 4 no futuro (2027–2032) |
| `contaspagrec.datalancamento` | `aaaa-mm-dd` (125.948) ou com hora (6.706) | |
| `clientes.entrada`, `ultima`, `datnas` | `aaaa-mm-dd` | |
| `followup.data` | `aaaa-mm-dd` (6 com hora) | `proximocontato`: 1 ano "202" |
| `eventosnfe.dataautorizacao` | `aaaa/mm/dd hh:mm:ss` | |
| `logestoque.data`, `eventosnfe.data` | `aaaa-mm-dd hh:mm:ss` | |

→ O importador precisa aceitar `aaaa-mm-dd`, `aaaa-mm-dd hh:mm:ss`, `dd/mm/aaaa[ hh:mm:ss]` e `aaaa/mm/dd hh:mm:ss`, e gravar NULL (com aviso no relatório) quando o ano for < 2000 ou > 2030.

---

## 10. Decisões do usuário

Respondidas nesta sessão (ver seção 11). Itens ainda sem resposta ficam marcados como **pendente**.

| # | Pergunta | Resposta |
|---|---|---|
| 0 | O que entra? | **Tudo** (decisão geral no topo) |
| 1 | Quais empresas emitentes entram (A, B, C, D)? | **Todas** (mudou: antes era só A). O emitente fica gravado em cada pedido/título |
| 1b | Título vencido de **outra empresa** (B, C, D) deve bloquear o boleto no catálogo da Perfeita Alquimia? | pendente |
| 2 | Os 49.074 prospects (`P`) entram no One? Como lead "Não abordado" ou ficam fora? | **Entram como lead** |
| 3 | Pedidos `V` sem NF (StatusPedido 5/4/NULL, ~16.400, R$ 11,2 mi): que situação recebem? Contam no faturamento/meta? | **Pelo StatusPedido**: 5 = faturado; os outros = só histórico |
| 4 | Títulos vencidos antigos (2.207, R$ 2,0 mi; 1.511 vencidos até 2023): entram como "aberto" (bloqueiam boleto) ou com data de corte? | **Todos como aberto** |
| 5 | Logs: trazer `logestoque` como histórico de estoque? `logsistema`/`logsql` (só 2026, com dados pessoais)? | **Entram todos** (em `n10_*`; `logsql` só para admin, por ter dados pessoais em texto) |
| 5b | Contas a pagar | **Entram** (mudou a decisão nº 1 do LEIA-PRIMEIRO) |
| 6 | Nomes de `segmento1` (2, 3, 4), `segmento2` (2–5), `Grupo` (A–D) e canal 1 → segmentos do One | pendente (ver na tela do Nota10) |
| 7 | Significado de `StatusPedido` 0–5 e de `tipooperacao` O e C | pendente (confirmar na tela do Nota10) |
| 8 | Produtos dos grupos INSUMOS/ROTULOS/EMBALAGEM/escritório: matéria-prima, produto inativo ou fora? | pendente |
| 9 | Pedidos "cancelados com NF autorizada" (337) e "NF cancelada com pedido ativo" (256): qual campo manda? | sugestão: **a NF manda** (`status_nf`) |
| 10 | Itens órfãos (80.098): descartar? | **Não descartar**: guardar em `n10_itensnota` e listar no relatório |

## 11. Respostas do usuário

Respostas dadas em 18/11/2026, na sessão de análise do dump:

0. **Decisão geral (substitui as anteriores onde houver conflito): todos os dados do Nota10 vão para o One.** Ver o quadro no topo.
1. **Emitentes: todas as empresas entram** (A 15.652.740/0001-80 = Perfeita Alquimia, B, C, D e as entradas de terceiros).
   _Primeiro o usuário respondeu "só a Perfeita Alquimia"; depois corrigiu para "todos os dados"._
   - Cada pedido e título guarda o CNPJ do emitente (`pedidos`/`titulos_receber` precisam de um campo `emitente_cnpj`, migration nova),
     para os painéis poderem separar por empresa.
   - Os números das seções 2 a 9 (todas as empresas) são os que valem para a importação; a seção 12 fica só como referência da Perfeita Alquimia.
2. **Prospects (`TipoCliente = P`, 49.074): entram como lead**, na etapa "Não abordado", sem vendedor (fila de Prospecção e Reativação).
   O representante 57207 (cadastro inativo que segura 45.255 deles) **não** deve virar vendedor responsável.
3. **Pedidos de venda sem NF: decide o `StatusPedido`.**
   - `StatusPedido = 5` → **faturado** (conta no faturamento, recorrência e recompra), mesmo sem NF.
   - Demais (`4`, `0`–`3`, NULL/vazio) sem NF → **só histórico** (situação própria, fora do faturamento e das metas).
   - Pedido com NF autorizada (`status_nf = E`) continua **faturado** em qualquer StatusPedido.
   - **Pendente:** o significado exato de cada código 0–5 não está no dump; o usuário deve confirmar numa tela do Nota10 antes da
     importação final.
4. **Contas a receber: todos os títulos abertos entram como "aberto"**, inclusive os vencidos antigos (2.207 = R$ 2,02 mi, todas as
   empresas; só a Perfeita Alquimia: 1.062 = R$ 1,09 mi). Pendente (10/1b): se título de outra empresa também bloqueia o boleto. Isso vai **bloquear o boleto** desses clientes no catálogo: avisar o Financeiro antes.

5. **Funcionários ativos (com carteira):** Jeina, Ronaldo, João Batista, Yasmim, Gabriela, Márcio Chueco, Derli, Raiane, Márcio Padilha, Miriel.
   O código do Nota10 é o mesmo `usuarios.codigo_vendedor` do One. Responsável: última venda (se ativo) → carteira do Nota10 (se ativo) → fila.
   Regras completas e contagens em **`docs/nota10/regras-importacao.md`**.

## 11.1 Confirmado pela tela do Nota10 (print enviado pelo usuário, 18/11/2026)

A tela "Lista de pedidos" do Nota10 tem uma aba por tipo, o que confirma `notas.tipooperacao`:

| Aba no Nota10 | `tipooperacao` | Notas |
|---|---|---:|
| Vendas | `V` | 48.524 |
| **Compras** | `C` | 591 ("591 cadastrados" na tela) |
| Devolução de cliente | `D` | 113 |
| Devolução a fornecedor | `F` | 21 |
| Outras saídas | `S` | 5.068 |
| Outras entradas | `E` | 5.217 |
| Orçamento | `O` | 1.835 |
| Serviços / Vendas no PDV | — | 0 (não usados) |

Legenda de situação da mesma tela, na ordem: Cadastrado, Processando, Faturado, Cancelado, Em separação, Despachado.
**Hipótese (a confirmar):** é o `StatusPedido` 0–5 → 0 Cadastrado · 1 Processando · 2 Faturado · 3 Cancelado · 4 Em separação · 5 Despachado.
Bate com os dados: o 3 aparece quase sempre com pedido/NF cancelados, e o 5 é o mais comum com NF autorizada. Se confirmado,
a regra 3 ("StatusPedido 5 = faturado") significa **despachado = faturado**; e `StatusPedido = 3` deve virar **cancelado**.

### Compras (`C`) e fornecedores — entram (decisão geral)

- **591 compras**, de **05/2025 a 11/2026** (antes disso as compras eram lançadas como "Outras entradas" `E`), todas da Perfeita Alquimia,
  total R$ 3,09 mi. Situação: 587 "NFe recebida" (`status_nf = R`), 3 "Pedido" (sem NF), 1 outra.
- **1.815 itens**, **800 produtos** diferentes: 520 sem grupo, INSUMOS 121, MATERIAL DE ESCRITORIO 42, EMBALAGEM 40, ROTULOS 37,
  MATERIAL DE LIMPEZA 17, outros 23.
- **104 fornecedores** nessas compras: 70 cadastrados como `F`, **31 cadastrados como `C` (cliente)**, 2 `R`, 1 `P`.
  → separar fornecedor **só pelo `TipoCliente` perde 31 fornecedores**: quem aparece em compra (`C`), entrada (`E`) ou devolução a
  fornecedor (`F`) também é fornecedor.
- Destino no One: as compras com NF combinam com **`compras_lancadas` + `compras_lancadas_itens`** (número da NF, série, fornecedor,
  CNPJ, emissão, total, itens). Os itens apontam para `materias_primas`, então os produtos comprados (insumos, rótulos, embalagens…)
  precisam existir lá.
- **O One não tem tabela de fornecedores** (hoje é só um texto em `compras_lancadas.fornecedor` e `materias_primas.fornecedor`).
  Para guardar o cadastro completo dos fornecedores (459 `F` + os de compra/entrada) é preciso uma migration nova (`fornecedores`),
  ou, no mínimo, guardá-los em `n10_clientes`.
- As "Outras entradas" (`E`, 5.217) incluem as compras antigas (antes de 05/2025) e 2.948 ajustes manuais de estoque: entram como
  histórico de entrada.

## 11.2 Achados com os prints das outras abas (18/11/2026) — ⚠️ risco de venda contada em dobro

**1. Pedido de bonificação "gêmeo" (explicado pelo usuário).** No Nota10, quando um pedido tem produto bonificado, **é criado
mais um pedido com o mesmo número, só com a bonificação** (para mandar a bonificação sem precisar de nota).
- **11.024 pedidos `V` com total R$ 0,00 têm um "irmão" com o mesmo número de pedido** (11.506 números de pedido aparecem 2+ vezes em `V`).
- Itens desses gêmeos: **23.417 itens**, valor de tabela (qtd × preço) **R$ 1,95 mi**, custo R$ 0,81 mi. O total da nota é 0, mas
  **o item guarda o preço** (`valortotalitem` soma R$ 1,89 mi).
- NF: 10.264 sem NF · 754 com NF autorizada (CFOP 6910/5910 no cabeçalho em ~1.400) · 6 com NF cancelada.
- CFOP dos itens: vazio 16.461 · **6910 = 3.019 · 5910 = 378** · 5405 = 1.873 · 6102 = 827 · 6404 = 396.
- StatusPedido dos pedidos de total 0: 5 = 7.907 · 4 = 2.063 · vazio = 2.552.
- Mais **1.538 pedidos `V` de total 0 sem irmão** (bonificação/amostra/brinde sozinhos).

→ **Regra para a importação (combina com a decisão nº 5 do LEIA-PRIMEIRO, "bonificação na mesma nota"):**
o gêmeo de total 0 **não vira pedido separado no One**. Os itens dele entram **no pedido principal** (mesmo número `pedido`, total > 0)
como itens bonificados: `pedido_itens.total = 0`, `valor_bonificado = qtd × valorunitario`, CFOP 5910/6910 pela UF de entrega.
O GUID do gêmeo fica guardado (ex.: `pedido_itens.nota10_guid`) para não duplicar se a importação rodar de novo.
Sem isso, o One teria ~11 mil pedidos a mais: **ticket médio, número de pedidos, recorrência e alerta de recompra ficariam errados**
(o faturamento em R$ não muda, porque o total é 0).
Os números de "pedidos faturados" das seções 4.5 e 12 **incluem esses gêmeos** e vão cair quando forem juntados.

**2. Orçamentos duplicam vendas.** A aba Orçamento (`O`, 1.835, desde 2025) tem registros com o mesmo cliente e o mesmo valor de
vendas: **1.127 orçamentos batem com uma venda `V`** (R$ 1,81 mi), 871 no mesmo dia. → `O` entra só como **orçamento/histórico**,
nunca no faturamento.

**3. "MOV MANUAL" = ajuste de estoque.** Nas abas Outras saídas/Outras entradas, situação "MOV MANUAL", pedido "Manual", total 0,
cliente "Não informado": são ajustes manuais (natureza "ALTERACAO MANUAL DE ESTOQUE": 2.301 em `E`, 647 em `S`).
→ Entram como **movimento de estoque**, não como pedido.

**4. Produção está nas Outras entradas.** O cadastro **4405 "ENTRADAS PRODUÇÃO"** recebe **532 entradas (R$ 34,7 mi)**
de 2022 a 2026 (2022 = 91 · 2023 = 86 · 2024 = 66 · 2025 = 180 · 2026 = 109). É o **histórico de produção** (a tabela `producao`
está vazia) → candidato a histórico de ordens de produção no One. Esse "cliente" 4405 **não é cliente nem fornecedor**.

**5. Duplicados exatos** (mesmo tipo, cliente, data e total > 0): `S` 80 registros a mais · `E` 34 · `V` 17 · `F` 11 · `D` 2 · `O` 2 · `C` 1.
Ex.: na Devolução a fornecedor, 13 "Pedido" iguais de R$ 23.990,40 para o mesmo fornecedor em 17–18/10/2023 (emitente Sabor e
Natureza), só um com NF. → importar todos (decisão geral), mas **marcar como "possível duplicado"** no relatório e não somar.

**6. Situação mostrada na lista.** A coluna "Situação" é derivada da NF: "Pedido" (sem NF), "NFe emitida" (`status_nf = E`),
"NFe recebida" (`R`), "MOV MANUAL" (ajuste). A faixa colorida à esquerda segue a legenda de `StatusPedido` (ver 11.1);
pedidos recentes aparecem em verde = "Em separação" (4), o que reforça a hipótese 0–5.

**7. Emitente B = "SABOR E NAT…"** (nome cortado na tela; banco `sabor`). Emitente A = "PERFEITA AL…".

**8. Importar com um dump do dia da virada.** Os prints de 18/11 já têm pedidos novos (Vendas 48.526, Saídas 5.076, Entradas 5.225)
que não estão no dump de 17/11. A importação final precisa de um backup novo, tirado quando o Nota10 parar de ser usado.

## 12. Referência: números só da Perfeita Alquimia (emitente A)

Só para comparação: a importação leva **todas** as empresas. Recontagem com as regras 3 e 4 (e a sugestão 9: a NF manda).

### Pedidos de venda (`V`) do emitente A: 28.757

| Situação no One | Pedidos | Total (R$) |
|---|---:|---:|
| Faturado, com NF (`status_nf = E`) | 16.989 | 28.079.683 |
| Faturado, sem NF (`StatusPedido = 5`) | 8.228 | 5.799.267 |
| Só histórico (sem NF, StatusPedido ≠ 5) | 3.377 | 2.009.283 |
| Cancelado (`status_nf = C`, ou `PedidoCancelado = 1` sem NF autorizada) | 163 | 398.374 |
| ⚠️ dentro dos faturados com NF: marcados `PedidoCancelado = 1` | 139 | 290.968 |

Faturado por ano: 2021 = 506 (R$ 0,73 mi) · 2022 = 1.558 (R$ 2,71 mi) · 2023 = 3.844 (R$ 4,10 mi) · 2024 = 7.115 (R$ 8,64 mi) ·
2025 = 7.476 (R$ 10,43 mi) · 2026 = 4.718 (R$ 7,27 mi).

Outros tipos do emitente A (não são pedido de venda): E 2.891 · S 3.096 · O 1.835 · C 591 · D 107 · F 8.
Itens nas notas do emitente A: **208.612**.

Clientes com venda faturada no emitente A: **4.537**. Última compra: até 90 dias = 500 · 91–365 dias = 705 · 1–2 anos = 826 · mais de 2 anos = 2.506.

### Contas a receber (tipo R) do emitente A: 54.975

| Situação | Títulos | Valor líquido (R$) |
|---|---:|---:|
| Pago | 52.104 | 34.300.543 |
| Aberto a vencer | 1.295 | 1.054.823 |
| **Aberto vencido** | **1.062** | **1.093.895** |
| Cancelado | 514 | 633.182 |

Vencidos por ano: 2021 = 21 · 2022 = 214 · 2023 = 309 · 2024 = 101 · 2025 = 165 · 2026 = 252.
**284 clientes** terão o boleto bloqueado no catálogo por causa desses títulos vencidos.
