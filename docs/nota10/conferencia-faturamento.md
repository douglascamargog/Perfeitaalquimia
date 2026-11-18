# Conferência do faturamento: Nota10 × One (outubro/2026)

> Feita no computador do usuário com o backup de 17/11/2026 (sem dados pessoais). Comparação entre o relatório do Nota10 e o gráfico
> "Faturamento mês a mês" do One depois da importação na V17.2.

| | Pedidos | Valor |
|---|---:|---:|
| Relatório do Nota10 (período 01/10 a 31/10/2026) | 253 | R$ 663.567,16 |
| One, gráfico "Faturamento mês a mês" (out/26) | — | R$ 651.362 |
| **Diferença** | | **R$ 12.205** |

## Como o Nota10 chega em 253 / R$ 663.567,16

É a soma dos **pedidos de venda (`V`) da Perfeita Alquimia com valor > 0, pela data do pedido**, **incluindo** os que tiveram a NF cancelada.
No backup de 17/11 isso dá exatamente **253 pedidos** e R$ 663.609,16. A diferença de R$ 42,00 é de alguma alteração feita no Nota10
depois do backup.

## De onde vem a diferença (reconstruída pelo backup)

| Motivo | Valor |
|---|---:|
| Relatório do Nota10 (no backup) | 663.609,16 |
| − 13 pedidos de **30/10** com NF emitida em **03/11**: o One conta pela **data da NF** e põe em novembro | − 14.752,53 |
| + 5 pedidos de **30/09** com NF emitida em **01/10**: o One põe em outubro | + 3.877,48 |
| − 3 pedidos com **NF cancelada**: o Nota10 soma, o One não | − 3.208,94 |
| + 6 NFs de "Outras saídas" de venda **sem pedido par**: o One soma como venda com NF avulsa, o relatório de Vendas do Nota10 não | + 2.279,05 |
| **= One pelas regras atuais (simulado no backup)** | **651.804,22** |
| One de verdade | 651.362 |

O que sobra (cerca de R$ 440) vem da diferença entre o backup de 17/11 e o que está no One, e dos possíveis duplicados marcados.
**Não há dado perdido nem somado em dobro**: a diferença é de **critério**.

## ❌ As 6 "Outras saídas sem pedido par" de outubro NÃO são vendas avulsas: é venda contada duas vezes

Conferidas uma a uma no backup (NF 124006, 124012, 124020, 124051, 124080, 124149). Todas:
- têm **o mesmo número de pedido** (`notas.pedido`) e **o mesmo cliente** de um pedido de venda `V` que já está no One;
- valem **5% ou 10% do pedido** (ex.: NF de R$ 331,70 para um pedido de R$ 6.634,02);
- não foram ligadas porque a regra da V17.2 (`n10_x_par_saida`) exige **±3 dias** e **o mesmo nº de itens**: aqui a NF saiu 4 a 6 dias
  depois do pedido, ou a NF corresponde ao **gêmeo de bonificação** (total 0, que a regra exclui).

→ Os R$ 2.279,05 de outubro estão **somados em dobro** no One.

**No backup inteiro:** das 4.421 "Outras saídas" (sem os ajustes manuais), **1.781 têm o mesmo número de pedido E o mesmo cliente de
um pedido de venda** (2021 a 2026; CFOP 6102, 5102, 6910, 6404, 5910…; a NF vale 5%, 20%, 50%, 10%… do pedido). A regra atual liga
cerca de 1.230 → **mais de 500 NFs ainda entram como venda avulsa e somam em dobro** (as de bonificação ficam fora do faturamento,
mas também deveriam ser ligadas).

**Correção sugerida (V17.3):** em `etapaPreparar`, ligar a "Outras saídas" ao pedido **primeiro pela chave forte**:
mesmo `emitente` + mesmo `pedido` + mesmo `numerocliente` (pedido de venda com total > 0; se não houver, o gêmeo de bonificação com o
mesmo número → ligar ao principal dele). Só quando não achar, usar a regra atual (±3 dias e mesmo nº de itens). Atenção: o número de
pedido **sozinho não basta** (3.148 batem pelo número, mas só 1.781 são do mesmo cliente). Pedidos já importados que viraram par devem
sair do faturamento (ver o caso de borda da `revisao-V17.2.md`).

## Decisões para a conversa principal

1. **Mês do faturamento: DATA DO PEDIDO** (decisão do usuário, 18/11/2026), como o relatório do Nota10.
   - Gráfico "Faturamento mês a mês", metas, painéis e "Total" por período agrupam pela **data do pedido** (`pedidos.criado_em`), não por
     `faturado_em`/data da NF. A data da NF continua guardada (`emitido_em`, `faturado_em`) para consulta.
   - Confirmar na conversa principal se vale também para os pedidos feitos no próprio One (sugestão: sim, um critério só).
   - **Resultado esperado em out/2026** (backup de 17/11), depois desta decisão e da correção das "Outras saídas":
     R$ 663.609,16 − R$ 3.208,94 (NFs canceladas) − R$ 2.279,05 (NFs contadas em dobro) = **R$ 660.400,22**, em **250 pedidos**.
     O relatório do Nota10 mostra R$ 663.567,16 / 253: a diferença que deve sobrar é só a dos **3 pedidos com NF cancelada**
     (e os R$ 42 alterados no Nota10 depois do backup).
2. **NF cancelada:** o One está certo em não somar (o relatório do Nota10 soma); manter.
3. **"Outras saídas" de venda sem pedido par:** depois da correção acima, as que sobrarem são vendas avulsas de verdade.
   Somar no faturamento (como hoje) ou deixar fora, igual ao relatório de Vendas do Nota10?

A mesma diferença aparece em todos os meses (sobretudo nos que têm pedidos no último dia com NF no mês seguinte).

## Resposta da conversa principal (V17.5, 18/11/2026)

- **Mês do faturamento = data do pedido** (`pedidos.criado_em`) em todo o One: gráficos, metas, Meu mês, painéis, análises, carteiras,
  roteiros, acompanhamento, One com IA e a última compra/intervalo de recompra. Vale também para os pedidos feitos no One (decisão do usuário: um critério só).
  A data da NF continua em `faturado_em` / `emitido_em` e aparece no pedido.
- **"Outras saídas"** (`Importador::etapaPreparar`, tabela `n10_x_par_saida`, coluna `por`): 1) empresa + nº do pedido + cliente de um V com total > 0;
  2) idem com o gêmeo de bonificação → pedido principal; 3) regra antiga (±3 dias e mesmo nº de itens). Relatório: `saidas_par_pedido`, `saidas_par_gemeo`, `saidas_par_data`.
- Saída que já tinha sido importada como venda avulsa e agora tem par: ao "Importar de novo" vira `historico` com `situacao_origem = 'nf_de_pedido'`
  (sai do faturamento; registrado no histórico do pedido).
- **Venda avulsa que sobrar** (sem pedido par): continua somando no faturamento (decisão do usuário).
- **NF cancelada**: continua fora (mantido).
