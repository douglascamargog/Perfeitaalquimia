# Revisão da V17.0 (Importar Nota10) contra o backup real

> Revisão do `ONE_V17.0.zip` (18/11/2026 13:55), feita no computador do usuário comparando o código com o `dump.txt` real
> de 17/11/2026. Sem dados pessoais. Não deu para rodar o PHP aqui (não há PHP nem MySQL nesta máquina): é revisão de código.

## ❌ Bloqueante: nomes de coluna com maiúsculas diferentes do backup real

O `Leitor` cria as tabelas `n10_*` com os nomes de coluna **exatamente como estão no backup**. No backup real (`sabor`, MySQL 5.7)
quase todos os nomes são **minúsculos**: `numero`, `cpf_cnpj`, `razao`, `tipooperacao`, `numerocliente`, `totalnota`, `codigoproduto`…
A amostra de teste `tests/recursos/nota10_amostra.sql` usa outra grafia (`Numero`, `CPF_CNPJ`, `Razao`, `TipoOperacao`…), por isso os
testes passaram.

- **No SQL** isso não importa (o MySQL não diferencia maiúsculas em nomes de coluna): as consultas em texto funcionam.
- **No PHP importa**: `Banco::todos('SELECT * FROM n10_...')` devolve as chaves com a grafia real, e o `Importador` lê `$l['Numero']`,
  `$n['TipoOperacao']`, `$p['CodigoProduto']`… que **não existem** → viram `null`.

Efeito com o backup real, se a V17.0 rodar como está:

| Etapa | O que aconteceria |
|---|---|
| produtos | `CodigoProduto` nulo → **todos os produtos pulados** |
| cadastros | `Numero` nulo → todos os cadastros com número 0; `Razao`/`Nome` nulos → "Cadastro 0"; `CPF_CNPJ` nulo → todos em "Validar cadastro"; `Representante` nulo → ninguém na carteira pela regra (b); `Ativo` nulo → todos ativos. Só o 1º cadastro entra em `n10_x_cliente` (número 0) |
| pedidos | `TipoOperacao` e `NumeroCliente` nulos → **nenhum pedido** ("notas_sem_cliente") |
| itens / ligar / títulos / follow-up | ficam vazios ou sem ligação, porque dependem dos clientes e pedidos acima (as partes em SQL puro funcionam, mas não acham nada para ligar) |
| compras | `TipoOperacao` no SQL funciona, mas `Razao`, `CPF_CNPJ`, `Nota_E`, `TotalNota`, `GUID_Nota` no PHP vêm nulos |

**72 chaves** do `Importador.php` têm grafia diferente do backup real (lista abaixo).

### Correção sugerida

1. Em todo `Banco::todos(...)` que lê `n10_*` dentro do `Importador`, passar a linha por `array_change_key_case($linha, CASE_LOWER)` e usar
   **as chaves em minúsculas** no código (`$l['numero']`, `$n['tipooperacao']`…). Funciona com qualquer grafia de backup.
   (Alternativa: `PDO::ATTR_CASE => PDO::CASE_LOWER` só durante a importação.)
2. **Teste que pega o erro:** criar uma segunda amostra (ou mudar a atual) com os nomes de coluna **em minúsculas, como no backup real**
   (`CREATE TABLE` da `clientes` e da `notas` copiados do `docs/nota10/estrutura` do backup real: só a estrutura, sem dados) e rodar a
   importação inteira com as duas grafias.
3. Depois da correção: conferir no relatório final se os números batem com a seção 1.2 do `regras-importacao.md`
   (Yasmim ~1.656, Jeina ~1.521, Gabriela ~1.269… fila ~50.657) e com o diagnóstico (gêmeos ~11.024, NFs de saída ligadas ≥ 1.230).

Chaves afetadas (como estão no código → nome real no backup):
`Ativo→ativo`, `Bairro→bairro`, `Celular_1→celular_1`, `Celular_2→celular_2`, `CEP→cep`, `CEST→cest`, `CFOP→cfop`, `Chave_GUID→chave_guid`,
`Cidade→cidade`, `CodigoBarras→codigobarras`, `CodigoProduto→codigoproduto`, `CodigoTransportadora→codigotransportadora`,
`CodigoVendedor→codigovendedor`, `CodMunic→codmunic`, `ComissaoVendedor→comissaovendedor`, `Contato→contato`, `CPF_CNPJ→cpf_cnpj`,
`Custo→custo`, `Data→data`, `data_NF→data_nf`, `dataCadastro→DataCadastro`, `dataEntrega→dataentrega`, `dataSaida→datasaida`,
`DatNas→datnas`, `Desconto→desconto`, `Emitente→emitente`, `EndComp→endcomp`, `Endereco→endereco`, `EndNum→endnum`, `Entrada→entrada`,
`Estado→estado`, `Fabricante→fabricante`, `GUID_Nota→guid_nota`, `Hora→hora`, `InscricaoMunicipal→inscricaomunicipal`,
`LimiteCredito→limitecredito`, `Minimo→minimo`, `MotivoStatus→motivostatus`, `NaturezaOperacao→naturezaoperacao`, `NCM→ncm`,
`Nome→nome`, `Nota_E→nota_e`, `Nota_S→nota_s`, `Numero→numero`, `NumeroCliente→numerocliente`, `Obs→obs`, `Pedido→pedido`,
`PesoBruto→pesobruto`, `PesoLiquido→pesoliquido`, `PrefNFE→prefnfe`, `Quant→quant`, `Razao→razao`, `Refer→refer`,
`Representante→representante`, `RG_IE→rg_ie`, `Situacao→situacao`, `Status_NF→status_nf`, `SUFRAMA→suframa`, `Telefones→telefones`,
`TipoContribuinte→tipocontribuinte`, `TipoOperacao→tipooperacao`, `TotalDesconto→totaldesconto`, `TotalMercadoria→totalmercadoria`,
`TotalNota→totalnota`, `UF_Destino→uf_destino`, `UnidadeVenda→unidadevenda`, `Valor_ST→valor_st`, `ValorFrete→valorfrete`,
`ValorICMS→valoricms`, `ValorIPI→valoripi`, `WebSite→website` (e `cnpj` só existe como `CNPJ` em `formapgto`).

## ⚠️ Diferenças em relação às regras combinadas

1. **`Leitor::TABELAS` não foi ampliada** (regra 0.1 "tudo vai para o One"): `logsql`, `logsistema`, `configuracao`, `centros`, `contas`,
   `impostos`, `icms`, `perfisimpressoras`, `licencas`, `emailstemplates` continuam sem entrar na área `n10_*`.
2. **Título vencido de qualquer empresa bloqueia o boleto** (`etapaRecalcular`). Isso era a **pendência 2** (o diagnóstico sugeria
   só a Perfeita Alquimia). Confirmar com o usuário antes de rodar.
3. **`TR` vira transportadora** (`palpiteTipo`), mas o único cadastro `TR` tem 30 pedidos de venda → melhor `cliente_transportadora`.
4. Entradas de produção (cadastro 4405, 532 notas) e ajustes manuais de estoque ficam só em `n10_notas` (não viram histórico de
   produção nem movimento de estoque). Está de acordo com "o que não tem tela fica em n10_*", só registrar no relatório.

## ✅ Conferido e de acordo com as regras

Situação do pedido (StatusPedido 0–5, NF manda), gêmeos de bonificação juntados ao pedido principal (CFOP 5910/6910, `valor_bonificado`),
NF de "Outras saídas" ligada ao pedido (mesmo cliente, mesmo nº de itens, ±3 dias, sem somar de novo), orçamentos/devoluções/bonificações
fora do faturamento, outras empresas como `outra_empresa`, responsável (última venda ativa → carteira ativa → fila), vendedor antigo guardado
no pedido, fornecedores também por movimento (C/E/F), grupos de insumo/rótulo/embalagem como matéria-prima, títulos pelo `chaveguid`,
duplicados marcados sem somar, backup antes de importar, datas aceitando os 3 formatos.

---

## Resposta (V17.1, 18/11/2026)

| Ponto | O que foi feito |
|---|---|
| ❌ Nomes de coluna (bloqueante) | **Corrigido.** O `Importador` lê toda linha por `linhas()`, que passa as chaves para minúsculas (`array_change_key_case`); o código usa só chaves minúsculas e aliases explícitos nas consultas. Funciona com qualquer grafia. |
| Teste que pega o erro | A amostra `tests/recursos/nota10_importacao.sql` agora tem as colunas **em minúsculas, como o backup real**. Com o motor da V17.0, o `teste_nota10_importacao` falha em 22 verificações; com o da V17.1, passa nas 42. Também passa com a grafia mista. |
| 1. `Leitor::TABELAS` não ampliada | **Já estava resolvido na V17.0**: o `Leitor::executar` guarda **todas** as tabelas do backup (só pula as que começam com `n10_`); a lista `TABELAS` serve só para a tela de conferência. O teste confere que até `n10_comandas` (PDV) é guardada. |
| 2. Título de outra empresa bloqueia o boleto | **Decisão do usuário** (pergunta respondida em 18/11: "Qualquer empresa bloqueia"). Mantido. |
| 3. `TR` → transportadora | **Corrigido:** `TR` vira **cliente e transportadora** (o único `TR` do backup vende). |
| 4. Entradas de produção e ajustes | Mantido em `n10_notas` e contado no relatório ("Ajustes manuais de estoque (guardados)", "notas_E_guardadas"). |
| 3. Conferir números | Pendente: só dá para conferir com o backup real no One (relatório "Vindo do Nota10, no One agora" depois de importar). |
