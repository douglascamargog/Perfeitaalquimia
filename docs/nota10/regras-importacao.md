# Regras da importação Nota10 → One (etapa 2: gravar nos dados do One)

> Especificação para programar a importação de verdade, a partir das tabelas `n10_*` que o `Leitor.php` já cria.
> Base: dump de 17/11/2026 (análise completa em `docs/nota10/diagnostico.md`) e decisões do usuário em 18/11/2026.
> Sem dados pessoais: só regras, códigos e contagens esperadas.
> Contagens = dump de 17/11/2026; a importação final usará um backup novo, então os números vão mudar um pouco.

## 0. Princípios

1. **Tudo do Nota10 vai para o One** (decisão do usuário). O que tem tela vai para a tabela certa; o resto fica na área `n10_*` para consulta.
   - `Leitor::TABELAS` passa a ler **todas** as tabelas com dados (incluir `logsql`, `logsistema`, `configuracao`, `centros`, `contas`,
     `impostos`, `icms`, `cidades`, `perfisimpressoras`, `licencas`, `emailstemplates`).
   - A área `n10_*` **não pode ser apagada** pelo "Zerar dados" nem depois da importação (hoje o `descartar()` apaga: trocar por um aviso).
2. **Idempotente**: rodar de novo não duplica. Chaves: `clientes.nota10_numero`, `pedidos.nota10_guid`, `pedido_itens.nota10_guid`,
   `titulos_receber.nota10_guid` (= `contaspagrec.chaveguid`, único e sempre preenchido), `produtos.nota10_codigo`.
3. **Em partes** (limite de tempo da HostGator), como o Leitor: cada chamada processa um bloco e guarda até onde chegou.
4. **Backup antes**, auditoria, e **relatório final** com as contagens de cada regra (inclusive o que foi só guardado ou marcado).
5. **Emitente**: todas as empresas entram (A = 15.652.740/0001-80 Perfeita Alquimia; B = 34.773.706/0001-08 "Sabor e Nat…";
   C = 00.949.190/0001-98; D = 24.913.026/0001-99). Gravar o CNPJ do emitente em cada pedido e título (`emitente_cnpj`, migration nova)
   para os painéis poderem filtrar por empresa. **Padrão dos painéis do One: só a Perfeita Alquimia** (pendente confirmar).

## 1. Funcionários e carteiras

### 1.1 Quem está ativo (confirmado pelo usuário)

O vendedor no Nota10 é um cadastro `TipoCliente = R` em `clientes`; o código é o `clientes.numero`. **No One, o mesmo número está em
`usuarios.codigo_vendedor`.** A ligação é automática: `nota10 codigovendedor/representante` = `usuarios.codigo_vendedor` de um usuário **ativo**.

| Funcionário | Código(s) no Nota10 |
|---|---|
| Jeina | 58250 |
| Ronaldo | 1286 |
| João Batista | 57949 |
| Yasmim | 58529 |
| Gabriela | 58531 |
| Márcio Chueco | 56310 |
| **Derli** | **1962 e 269** (dois cadastros; o One só tem um `codigo_vendedor` → os dois códigos vão para o mesmo usuário) |
| Raiane | 58530 |
| Márcio Padilha | 1960 |
| Miriel | 58096 (é um cadastro tipo `C` no Nota10, não `R`) |

- Todos os outros códigos (cerca de 440, incluindo os inativos, setores, representantes externos e cadastros de feira) **não têm carteira**.
- Na tela de ligações (`nota10_mapas`, tipo `vendedor`), já vir preenchido pelo `codigo_vendedor`; o admin só confere.
  Se algum código ativo não existir no One, **parar e avisar** (não mandar para a fila sem querer).
- Para Derli: aceitar `269 → mesmo usuário de 1962` (ou o contrário, o que existir no One). Guardar em `nota10_mapas`.

### 1.2 Regra do responsável pelo cliente (`clientes.responsavel_id`)

Em ordem, a primeira que valer:
1. **Quem fez a última venda** (`notas` tipo `V`, total > 0, não cancelada; mais recente por `data`, `hora`, `id`), **se estiver ativo**.
2. **O vendedor da carteira no Nota10** (`clientes.representante`), **se estiver ativo**.
3. Nenhum dos dois ativo → **sem responsável, na fila da função Prospecção e Reativação**.

Contagem esperada (dump de 17/11):

| Responsável | Clientes (C e combinações) | Prospects (P) |
|---|---:|---:|
| Yasmim | 1.480 | 176 |
| Jeina | 1.477 | 44 |
| Gabriela | 1.130 | 139 |
| Ronaldo | 399 | 8 |
| Márcio Chueco | 224 | 19 |
| Raiane | 106 | 12 |
| João Batista | 98 | 5 |
| Derli | 91 | 1 |
| Miriel | 86 | 4 |
| Márcio Padilha | 55 | 0 |
| **Fila Prospecção e Reativação** | **1.991** | **48.666** |

Pela regra: 1.068 pela última venda, 4.486 pela carteira do Nota10, 50.657 para a fila.
Não entram nessa conta, porque não são clientes: fornecedores `F` (459), transportadoras `T`/`TF` (25) e vendedores `R` (493).

### 1.3 Vendedor do pedido (`pedidos.vendedor_id`)

- Pedido de vendedor **ativo** → `vendedor_id` = o usuário.
- Pedido de vendedor **inativo** (a maioria do histórico) → `vendedor_id` NULL. Mas **guardar o código e o nome do Nota10** no pedido
  (`nota10_vendedor_codigo`, `nota10_vendedor_nome`, migration nova) para o histórico e os relatórios por vendedor antigo não se perderem.
- `codigovendedor = 0` (7.326 notas) → sem vendedor.

### 1.4 Nova versão: "código do vendedor" vira "código do funcionário"

Só **texto da tela** (cadastro de usuário, filtros, relatórios). A coluna continua `usuarios.codigo_vendedor`: renomear coluna exigiria
duas versões (regra 14.2 do CLAUDE.md). Anotar em `app/versoes.php`.

## 2. Cadastros (`clientes` do Nota10)

### 2.1 Pelo `TipoCliente`

| TipoCliente | Qtde | Vai para |
|---|---:|---|
| `C` | 7.064 | `clientes` (cliente) |
| `CF` | 28 | `clientes` **e** fornecedor |
| `CP` | 36 | `clientes` (cliente) |
| `TC` | 5 | `clientes` **e** transportadora |
| `RC` | 1 | `clientes` (o código também pode ser vendedor) |
| `P` | 49.074 | `clientes` como **lead**, etapa **"Não abordado"**, responsável pela regra 1.2 (quase todos vão para a fila) |
| `F` | 459 | **fornecedor** (não é cliente) |
| `T`, `TF`, `TR` | 26 | **transportadora** (não é cliente) |
| `R` | 493 | **vendedor/representante**: não é cliente. Fica em `n10_clientes`; os ativos já existem como usuários no One |
| `ATIVO`, `63.652` | 2 | `clientes` na etapa **"Validar cadastro"** (tipo digitado errado) |

**Fornecedor não é só `TipoCliente = F`.** Também é fornecedor todo cadastro que aparece como `numerocliente` em notas de **Compra (`C`)**,
**Outras entradas (`E`)** ou **Devolução a fornecedor (`F`)**. Exemplo: 31 dos 104 fornecedores das compras estão cadastrados como `C`.
Esses entram como cliente **e** fornecedor.

**Cadastros internos que não são cliente:** o 4405 "ENTRADAS PRODUÇÃO" (532 entradas de produção) e o 57207 (Renata, cadastro tipo C
inativo que segura 45.255 prospects). Ficam fora da Carteira.

**O One não tem tabela de fornecedores nem de transportadoras** → migration nova `fornecedores` (e `transportadoras`, ou um campo de tipo),
com `nota10_numero`. `compras_lancadas.fornecedor` passa a ter `fornecedor_id`.

### 2.2 Campos

| Nota10 | One | Regra |
|---|---|---|
| `numero` | `nota10_numero` | chave |
| `chave_guid` | `nota10_guid` | |
| registro inteiro | `nota10_dados` (JSON) | nada se perde |
| `razao` / `nome` | razão social / nome fantasia | |
| `cpf_cnpj` | CNPJ/CPF | só dígitos; 11 = CPF, 14 = CNPJ; vazio (567) → "Validar cadastro". Os 4 documentos repetidos: deduplicar pelo CNPJ (manter o de compra mais recente e juntar o histórico) |
| `endereco`, `endnum`, `endcomp`, `bairro`, `cidade`, `estado`, `cep`, `codmunic` | endereço | `endereco` já vem inteiro (99%); `endnum` só em 46%. **UF inválida** (13 cadastros: RI, VI, LO…) → usar a UF do `codmunic` (2 primeiros dígitos IBGE) ou "Validar cadastro". CEP só com 8 dígitos |
| `telefones`, `celular_1`, `celular_2`, `fax` | telefone / WhatsApp | celular → WhatsApp |
| `email`, `email_NFe`, `email_fin` | e-mails | |
| `contato` (97,5%) | `cliente_contatos` (nome) | **a tabela `contatos` do Nota10 está vazia**: o contato é só este campo |
| `rg_ie`, `tipocontribuinte` | IE / contribuinte | "ISENTO" (63) |
| `obs`, `Ocorrencias`, `refer` | observações | juntar com título de cada parte |
| `entrega_*` (145) | endereço de entrega | direto |
| "entreg" em obs/ocorrências/refer sem `entrega_*` (166) | `entrega_revisar` | com o trecho; não adivinhar |
| `ativo` = N (1.351) | inativo | |
| `situacao` = Bloqueado (606) | bloqueado para venda/crédito | |
| `limitecredito` > 0 (8,6%) | limite de crédito | |
| `IdCanal` | canal | 2 = PERFEITA ALQUIMIA · 3 = TERCEIRIZAÇÃO · 1 = sem nome (pendente) |
| `segmento1`, `segmento2`, `Grupo` | segmento / classificação | **pendente**: o dump não tem os nomes desses códigos → `nota10_mapas` tipo `segmento`, o admin liga na tela |
| `entrada`, `ultima` | primeira / última compra | recalcular pelos pedidos importados; usar o campo só se não houver pedido |
| `transportadora1` | transportadora padrão | aponta para `clientes.numero` (tipo T) |

## 3. Notas e pedidos (`notas` + `itensnota`)

### 3.1 Pelo `tipooperacao` (confirmado nas abas da tela do Nota10)

| Código | Aba no Nota10 | Qtde | Vai para | Conta no faturamento? |
|---|---|---:|---|---|
| `V` | Vendas | 48.524 | `pedidos` (regras 3.2 a 3.4) | sim, se faturado |
| `S` | Outras saídas | 5.068 | regra 3.5 | depende |
| `O` | Orçamento | 1.835 | orçamento (tabela `orcamentos` do One ou `pedidos` com situação "orçamento") | **não** (1.127 repetem uma venda) |
| `C` | Compras | 591 | `compras_lancadas` + itens (seção 5) | não |
| `E` | Outras entradas | 5.217 | regra 3.6 | não |
| `D` | Devolução de cliente | 113 | devolução ligada ao cliente (e ao pedido, quando houver NF referenciada) | **desconta** do faturamento do mês |
| `F` | Devolução a fornecedor | 21 | devolução de compra | não |

### 3.2 Situação do pedido de venda (`V`)

`StatusPedido` (provável, pela legenda da tela; o usuário confirma): 0 Cadastrado · 1 Processando · 2 Faturado · 3 Cancelado ·
4 Em separação · 5 Despachado. `status_nf`: E emitida · C cancelada · D denegada · R recebida.

Em ordem, a primeira que valer:
1. `status_nf = C`, **ou** `StatusPedido = 3`, **ou** (`PedidoCancelado = 1` e `status_nf ≠ E`) → **cancelado**.
2. `status_nf = E` (NF autorizada) → **faturado** (`numero_nf` = `nota_s`, `nf_serie` = `Serie`, chave = `prefnfe`, `faturado_em` = `data_nf`
   ou `data`). Se `PedidoCancelado = 1` (139 na Perfeita Alquimia): **faturado, com alerta** no relatório (a NF manda).
3. Sem NF e `StatusPedido = 5` → **faturado sem NF** (decisão do usuário). Se houver NF em "Outras saídas" ligada a ele (regra 3.5), usar o número dela.
4. Sem NF e outro StatusPedido → **histórico** (não conta no faturamento nem na meta). Mapear 0 → aguardando, 1 → processando,
   2 → faturado (sem NF), 4 → em separação.

### 3.3 Pedido de bonificação "gêmeo" (juntar, não importar separado)

O Nota10 cria **um segundo pedido com o mesmo número** só com a bonificação (total 0). São **11.024** gêmeos, com 23.417 itens
(valor de tabela R$ 1,95 mi).

- Gêmeo = `V` com `totalnota = 0` cujo `pedido` também existe em outro `V` com total > 0 (do mesmo emitente).
- **Não cria pedido.** Os itens entram **no pedido principal** como itens bonificados: `pedido_itens.total = 0`,
  `valor_bonificado = quantidade × valorunitario`, CFOP 5910/6910 pela UF de entrega (5 = mesma UF do emitente, 6 = outra).
- `pedido_itens.nota10_guid` = GUID do gêmeo, para não duplicar se rodar de novo.
- Se o gêmeo tiver NF própria (754): guardar o número da NF de bonificação no pedido principal (`nf_bonificacao`).
- `V` com total 0 **sem** pedido principal (1.538): entram como pedido de **bonificação/amostra** (total 0, não conta no faturamento).

### 3.4 Itens (`itensnota` → `pedido_itens`)

| Nota10 | One |
|---|---|
| `guid_item` | `nota10_guid` |
| `codigoproduto` | produto (pelo `produtos.nota10_codigo`) |
| `descricaoproduto`, `unidade` | descrição / unidade (a do momento da venda) |
| `quantidade`, `valorunitario`, `valordesconto`, `valortotalitem` | quantidade, preço, desconto, total |
| `cfop` (vazio em 30%) | CFOP do item; se vazio, usar o CFOP do cabeçalho; se também vazio, calcular pela UF |
| `custoproduto` | custo |
| `lote`, `validade` | lote/validade (quase vazios) |
| impostos (`cst`, `csosn`, `valoricms`, `valorst`, `valoripi`, IBS/CBS) | guardar em `nota10_dados` do item (JSON) |

- **Itens sem nota (80.098 itens, 11.877 GUIDs):** não viram item de pedido. Ficam só em `n10_itensnota`, com a contagem no relatório.
- Itens com produto inexistente (21): criar o produto inativo pelo `codigoproduto` + `descricaoproduto`.

### 3.5 Outras saídas (`S`, 5.068)

- **NF de um pedido de venda (pelo menos 1.230):** há um `V` **do mesmo cliente, no mesmo dia e com o mesmo número de itens**, quase sempre
  sem NF (StatusPedido 5). O valor da NF é diferente do pedido (20%, 50%, 5%, 10%…). → **ligar a NF ao pedido** (`numero_nf`, chave, valor
  da NF em `valor_nf`) e **não contar o valor de novo**. O faturamento comercial é o do pedido `V`.
- **Ajuste manual de estoque** (natureza "ALTERACAO MANUAL DE ESTOQUE", 647; tela "MOV MANUAL") → movimento de estoque (saída), não pedido.
- **Bonificação/amostra/remessa** (CFOP 5910, 6910, 6911, 6949, 5949) sem `V` par → pedido de bonificação (total não conta).
- **Venda com NF própria** (CFOP 5102, 6102, 6101, 5405, 6404) **sem `V` par** → pedido **faturado** com `tipo_venda = 'nf_avulsa'`.
  ⚠️ Antes de contar no faturamento, procurar o `V` par com uma janela de ±3 dias (mesmo cliente e mesmos produtos). Se achar, ligar como acima.
- Guardar o `pedido`/`vpedido` do Nota10 (`nota10_pedido`) em todos.

### 3.6 Outras entradas (`E`, 5.217)

- Cliente **4405 "ENTRADAS PRODUÇÃO"** (532, R$ 34,7 mi, 2022–2026) → **histórico de produção** (o que foi fabricado, por data e produto).
  Destino: ordens de produção concluídas (se a tabela do One permitir histórico) ou `n10_*` com relatório. Não é compra.
- "ALTERACAO MANUAL DE ESTOQUE" (2.301; "MOV MANUAL") → movimento de estoque (entrada).
- As demais (compras antigas, antes de 05/2025, e outras entradas com NF de terceiro) → **histórico de entrada** ligado ao fornecedor
  (`compras_lancadas` com `origem = 'nota10'` quando houver NF de fornecedor).

### 3.7 Outros campos do pedido

| Nota10 | One |
|---|---|
| `guid_nota` | `nota10_guid` |
| `pedido` | `nota10_pedido` |
| `data` + `hora` | `criado_em` |
| `numerocliente` | cliente (pelo `nota10_numero`); 0/NULL (3.869, quase todos `E`) → sem cliente |
| `formapgto` | `forma_pagamento_id` via `nota10_mapas` tipo `forma` (2 "30 DIAS", 99 "SEM PGTO", 1 "A VISTA", 106 "PIX"…; 536 códigos) |
| `tabelaprecovenda` | tabela de preço (só existe a 1 "Base") |
| `totalmercadoria`, `totaldesconto`, `valorfrete`, `totalnota` | subtotal, desconto, frete, total |
| `codigotransportadora`, `Volumes`, `pesobruto`, `CodigoRastreio` | transportadora, volumes, peso, rastreio |
| `Obs`, `ObsInterna` | observações ("entreg" em 628 notas → conferir na revisão de entrega) |
| `IdCanal` | canal |
| `uf_destino` | UF de entrega (usada no CFOP 5/6 da bonificação) |

### 3.8 Duplicados

Mesmo tipo, cliente, data e total > 0: `S` 80 extras, `E` 34, `V` 17, `F` 11, `D` 2, `O` 2, `C` 1. **Importar todos**, marcar
`possivel_duplicado = 1` e **não somar** no faturamento os extras (fica o de menor `id`, ou o que tem NF).

## 4. Contas a receber e a pagar (`contaspagrec`)

### 4.1 Receber (`tipo = R`, 100.584) → `titulos_receber`

| Situação | Regra | Qtde (todas as empresas) |
|---|---|---:|
| cancelado | `Cancelado = 1` ou `DataCancelamento` preenchida | 891 |
| pago | `datapagamento` preenchida | 96.140 |
| aberto | o resto, **inclusive os vencidos antigos** (decisão do usuário) | 3.553 (2.207 vencidos) |

- `chaveguid` → `nota10_guid`; `credordevedor` → cliente; `guid_nota` → pedido (125 apontam para nota que não existe: título sem pedido).
- `documento`, `numeroparcela`, `nossonumero`, `FormaPgto`, `historico`, `datalancamento`, `datavencimento`, `datapagamento`,
  `valorbruto`, `desconto`, `acrescimo`, `valorliquido` → campos com o mesmo nome do One.
- **O título vencido bloqueia o boleto** no catálogo. Pendente: título de **outra empresa** (B, C, D) também bloqueia? Padrão sugerido:
  só os da Perfeita Alquimia bloqueiam.

- **(V17.3) Cobrança:** os títulos em aberto vencidos há mais dias que `cobranca.dias_atraso_entrada` viram **casos na tela de Cobrança**
  (um caso por cliente, `cobrancas.origem = 'nota10'`), como o "Ler títulos vencidos do ERP". Cada título entra em `cobranca_titulos`
  com `omie_codigo = 'n10:' + chaveguid` (não duplica). Título pago/cancelado num backup mais novo recebe baixa no caso.

### 4.2 Pagar (`tipo = P`, 32.070) → entra (decisão "tudo")

O One não tem contas a pagar → migration nova `titulos_pagar` (mesmos campos + `centro`, `siglaconta`, fornecedor), ou fica em
`n10_contaspagrec` com relatório. `centros` (146) e `contas` (36) vão junto.

## 5. Produtos, matérias-primas, preços e estoque

- **Casar pelo código** (`codigoproduto`, numérico, sem repetidos) com `produtos.nota10_codigo`. Os produtos do One não são apagados.
- Produto que só existe no Nota10 → **inativo e fora do catálogo** (decisão do LEIA-PRIMEIRO).
- **Grupos que não são produto de venda** (INSUMOS 39, ROTULOS 38, EMBALAGEM 40, material de escritório 42, limpeza 47, EPIs 43,
  imobilizado 69, informática 49, máquinas 83, uso e consumo 71) → **`materias_primas`** (`rotulo = 1` para ROTULOS), não `produtos`.
  Isso é necessário para as compras (seção 5.1) apontarem para a matéria-prima. Pendente: confirmar a lista com o usuário.
- `grupos` → categoria. 890 produtos sem grupo (520 deles aparecem em compras → provável matéria-prima; conferir na tela).
- `precos` (tabela 1 "Base", 2.841) → **pendente**: em qual tabela do One (Lojista?) a "Base" entra.
- `estoques` (3.002, sem lote) → saldo atual. `logestoque` (366.466) → histórico de movimentos.

### 5.1 Compras (`C`, 591) → `compras_lancadas` + `compras_lancadas_itens`

`origem = 'nota10'`, `numero_nf` = `nota_e` (ou o número externo), `fornecedor`/`cnpj` do cadastro, `emissao` = `data`,
`valor_total` = `totalnota`, `situacao` = lançado (`status_nf = R`, 587) ou rascunho (3 "Pedido"). Itens → matéria-prima pelo código.

## 6. Linha do tempo e outros

- `followup` (450, 2021–2024) → `cliente_eventos` (autor = usuário do Nota10 pelo nome, se existir no One). **Nenhum próximo contato é futuro**:
  não criar tarefas.
- `eventosnfe` → evento no pedido: carta de correção (4.053) e cancelamento (286), com data e texto.
- `usuarios` do Nota10 (49) → só `n10_usuarios` (os ativos já existem no One).
- `logsql`, `logsistema` (só 2026) → `n10_*`, **só o Admin vê** (têm dados pessoais em texto livre).

## 7. Datas (todas são texto no Nota10)

Aceitar `aaaa-mm-dd`, `aaaa-mm-dd hh:mm:ss`, `dd/mm/aaaa[ hh:mm:ss]` e `aaaa/mm/dd hh:mm:ss`. Ano < 2000 ou > 2030 → NULL e linha no
relatório (cerca de 40 casos em `contaspagrec` e `notas.data_nf`).

## 8. Relatório final (o que conferir depois de importar)

Por empresa: clientes (por tipo e por responsável, tabela 1.2), leads, fornecedores, transportadoras; pedidos por situação, faturamento por
ano, bonificações juntadas (gêmeos), NFs de "Outras saídas" ligadas a pedidos, orçamentos; títulos por situação e clientes com boleto
bloqueado; compras; itens só guardados (órfãos); possíveis duplicados; datas inválidas; cadastros em "Validar cadastro".

## 9. Pendentes (perguntar ao usuário antes ou na tela de ligações)

1. Painéis do One: mostrar só a Perfeita Alquimia ou todas as empresas?
2. Título vencido de outra empresa bloqueia o boleto da Perfeita Alquimia?
3. Confirmar o `StatusPedido` 0–5 (legenda da tela).
4. Nomes de `segmento1`, `segmento2`, `Grupo` (A–D) e do canal 1.
5. A tabela de preço "Base" vai para qual tabela do One?
6. Lista final de grupos que viram matéria-prima.
7. As "Outras saídas" de venda sem par em `V`: contam no faturamento? (padrão sugerido: sim, como `nf_avulsa`, depois da busca de ±3 dias)
