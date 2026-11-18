# Revisão da V17.1 (Importar Nota10) contra o backup real

> Revisão do `ONE_V17.1.zip` (18/11/2026 14:08), no computador do usuário, comparando o código com o `dump.txt` real de 17/11/2026.
> Sem dados pessoais. Não há PHP nem MySQL nesta máquina: é revisão de leitura do código, não execução.
> Lido na ordem pedida: `CLAUDE.md`, `docs/nota10/LEIA-PRIMEIRO.md`, `regras-importacao.md`, `revisao-V17.0.md` (com a resposta) e
> `app/Services/Nota10/`. Obs.: o `LEIA-PRIMEIRO.md` do pacote **não tem** o quadro "Situação atual" no topo (continua dizendo "até a V16.8").

## ✅ Resolvido desde a V17.0

| Ponto | Conferência |
|---|---|
| Nomes de coluna (bloqueante) | **Corrigido.** Toda leitura de `n10_*` passa por `linhas()` (`array_change_key_case`, minúsculas). Conferi **todas** as chaves `$x['...']` do `Importador.php` contra as colunas do backup real: todas existem; as únicas que não existem no backup são internas do código (`_tipo`, `nomecad` = alias da consulta, `nota10_numero` = coluna do One). |
| `TR` | Corrigido: vira cliente e transportadora. |
| Bloqueio de boleto por título de qualquer empresa | Decisão do usuário registrada (18/11). Ok. |
| "Leitor não lê todas as tabelas" (meu ponto 1 da V17.0) | **Eu estava errado**: o `Leitor::executar` já guarda todas as tabelas (só pula as que começam com `n10_`); `TABELAS` serve só para a conferência. |

## ❌ Problema 1 — ler um backup novo depois de importar duplica cadastros sem CNPJ/CPF

Cenário previsto: importar agora (teste com o backup de 17/11) e, na virada, ler e importar o **backup final**.

- Ao ler um backup novo, `Leitor::lerParte` (posição 0) chama `limparArea()`, que apaga **todas** as tabelas `n10_%`, incluindo a
  **`n10_x_cliente`** (o mapa "número do Nota10 → cliente do One").
- `etapaPreparar` só faz `CREATE TABLE IF NOT EXISTS n10_x_cliente` e apaga ligações órfãs; **não reconstrói** o mapa a partir de
  `clientes.nota10_numero`.
- Na etapa cadastros, `$feitos` vem vazio. Quem tem CNPJ/CPF é reencontrado pelo documento (`$porDoc`) e não duplica, mas **quem não tem
  documento válido é inserido de novo**. No backup real são cerca de **600 cadastros** (567 sem documento + 1 com tamanho errado + parte dos 2 com tipo
  digitado errado), e todos os pedidos, títulos e follow-ups deles passariam a apontar para a cópia nova.

**Correção sugerida** (no começo de `etapaPreparar`, depois do `CREATE TABLE`):
```sql
INSERT IGNORE INTO n10_x_cliente (numero, cliente_id)
SELECT nota10_numero, MIN(id) FROM clientes WHERE nota10_numero IS NOT NULL AND excluido_em IS NULL GROUP BY nota10_numero;
```
(ou fazer o `limparArea()` pular as tabelas `n10_x_*`). **Teste:** importar a amostra, ler a mesma amostra de novo, importar de novo →
o número de clientes não pode mudar.

## ❌ Problema 2 — pedido que mudou de situação entre os dois backups não é atualizado

- `etapaPedidos` pula todo pedido cujo `nota10_guid` já existe (`$feitos` → `continue`). Os títulos são atualizados
  (`ON DUPLICATE KEY UPDATE`), mas **os pedidos não**.
- Na virada, os pedidos recentes do teste vão ter mudado no Nota10: "Pedido" → "NFe emitida", Em separação (4) → Despachado (5),
  cancelados. No dump de 17/11, cerca de **6.100 pedidos de venda** (contando os gêmeos de bonificação) estão sem NF e com StatusPedido diferente de 5
(4, 0–3 ou vazio); os mais recentes são justamente os que ainda vão andar até a virada.
  Esses ficariam como **"histórico"** e **sem número de NF** no One para sempre, fora do faturamento e da última compra.
- Mesma coisa para um pedido que ganhou o gêmeo de bonificação ou a NF de "Outras saídas" depois do primeiro backup: o `ligar` funciona
  (é `UPDATE`), mas a situação continua a antiga.

**Correção sugerida:** quando o pedido já existe e veio do Nota10 (`origem = 'nota10'`), **recalcular e atualizar** `situacao`,
`situacao_origem`, `numero_nf`, `nf_serie`, `nf_chave`, `nf_situacao`, `faturado_em`, `emitido_em`, `total`, `subtotal`, `desconto`
(só se mudou; registrar em `pedido_historico` "Atualizado pelo Nota10"). **Não** mexer em pedido com `origem` diferente de `nota10` nem
nos campos de expedição que o One já preencheu. **Teste:** importar a amostra; mudar um pedido de StatusPedido 4 sem NF para `status_nf = E`;
ler e importar de novo → o pedido passa a "faturado" com a NF.

## ⚠️ Menores (não bloqueiam o teste)

1. **Cadastro alterado no Nota10 entre os backups** (telefone, endereço, situação "Bloqueado") não é atualizado no One (cadastro já
   importado é pulado). Aceitável se a virada for logo depois do teste; senão, atualizar só os campos vazios no One ou listar no relatório.
2. Etapa "preparar": a tabela da última venda (`n10_x_ultima_venda`) ignora `PedidoCancelado`, mas **não** ignora `status_nf = 'C'`
   (NF cancelada com pedido não marcado: 256 no backup). Um vendedor pode virar responsável por uma venda que foi cancelada.
   Sugestão: `AND COALESCE(n.status_nf, '') <> 'C'`.

## Como testar com segurança agora

A V17.1 pode ser usada para o **teste com o backup de 17/11**, desde que a importação final (backup da virada) só rode depois das
correções 1 e 2, ou que, antes da importação final, os dados do teste sejam zerados (Administração › Zerar dados) para importar do zero.
Depois do teste, conferir o relatório "Vindo do Nota10, no One agora" com a tabela 1.2 do `regras-importacao.md`
(Yasmim ~1.656, Jeina ~1.521, Gabriela ~1.269, Ronaldo ~407, Márcio Chueco ~243; fila ~50.657) e com o diagnóstico (gêmeos ~11.024,
NFs de "Outras saídas" ligadas ≥ 1.230, títulos a receber 100.584).

---

## Resposta (V17.2, 18/11/2026)

| Ponto | O que foi feito |
|---|---|
| Obs. sobre o `LEIA-PRIMEIRO.md` | O quadro "Situação atual" entrou num pacote ONE_V17.1 gerado de novo logo depois (mesmo número). Está em todos os pacotes desde então. |
| ❌ 1. Backup novo duplica cadastros sem CNPJ/CPF | **Corrigido** como sugerido: `etapaPreparar` refaz o `n10_x_cliente` a partir de `clientes.nota10_numero` (`INSERT IGNORE ... GROUP BY nota10_numero`). |
| ❌ 2. Pedido que mudou entre os backups não é atualizado | **Corrigido:** `atualizarPedido()` recalcula situação, NF (número, série, chave, situação), datas, valores, tipo, CFOP e vendedor de pedido com `origem = 'nota10'`; registra "Atualizado pelo Nota10: …" no histórico. Não apaga NF ligada pelas "Outras saídas" e duplicado continua fora do faturamento. Pedido com outra origem nunca é tocado. |
| ⚠️ 1. Cadastro alterado entre os backups | **Feito:** `atualizarCliente()` completa só o que está **vazio** no One (contatos, endereço, documentos, datas) e atualiza o que é do ERP (situação, motivo, limite de crédito). |
| ⚠️ 2. Última venda com NF cancelada | **Corrigido:** `n10_x_ultima_venda` ignora `status_nf = 'C'`. |
| Teste | `teste_nota10_importacao` agora importa a amostra, lê um **backup da virada** (`tests/recursos/nota10_importacao_virada.sql`: pedido em separação que ganhou NF, loja sem CNPJ que ganhou telefone e pedido novo) e importa de novo: nenhum cliente duplicado, pedido vira faturado com a NF e histórico, pedido novo vai para a mesma loja, cadastro completado. 49 verificações. |
