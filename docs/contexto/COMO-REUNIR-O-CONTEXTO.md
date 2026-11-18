# Como reunir o contexto das conversas para o chat novo

**Ideia principal:** em vez de passar o resumo de um chat para o outro (cada passagem perde detalhes, como telefone sem fio),
cada conversa escreve **a sua parte** num arquivo separado, e todos os arquivos ficam juntos nesta pasta `docs/contexto/`.
O chat novo lê o código (que é a verdade) + todos esses arquivos de uma vez.

| Arquivo | Quem escreve | Situação |
|---|---|---|
| `LEIA-PRIMEIRO.md` | conversa que construiu o One (V1.0 → V17.9) | ✅ pronto |
| `01-prototipo.md` | conversa do protótipo (antes do One) | colar o texto A abaixo e salvar a resposta |
| `02-banco-de-dados.md` | conversa que organiza o banco / dump do Nota10 | colar o texto B abaixo e salvar a resposta |

## Passo a passo

1. Abra a conversa do **protótipo**, cole o **texto A**, e salve a resposta como `docs/contexto/01-prototipo.md`.
2. Abra a conversa do **banco de dados**, cole o **texto B**, e salve a resposta como `docs/contexto/02-banco-de-dados.md`.
3. No seu computador, baixe o projeto do GitHub na pasta nova (branch `ccr-12815ad2-flpiv1`) e coloque os dois arquivos em `docs/contexto/`.
   Se a conversa do banco tiver arquivos (diagnósticos, scripts), coloque em `docs/contexto/banco/`. **Nada com dados de clientes.**
4. Abra o chat novo nessa pasta e cole o **texto C**.

---

## Texto A — para a conversa do protótipo

```
Estou passando o projeto para um chat novo que vai trabalhar direto na pasta do sistema (o One, que nasceu deste protótipo
e hoje está em produção na versão V17.9). Escreva um documento em Markdown, para eu salvar como "01-prototipo.md", com tudo
o que só esta conversa sabe e que ajudaria quem for continuar:
1. O que era o protótipo, para quem foi apresentado e o que a diretoria aprovou ou pediu.
2. Decisões de negócio e de visual tomadas aqui (e o motivo de cada uma), inclusive ideias que foram descartadas e por quê.
3. Regras da empresa que você aprendeu comigo (vendas, carteiras, setores, clientes, produtos, financeiro) que talvez não estejam escritas no sistema.
4. Ideias e pedidos que ficaram para depois.
5. Qualquer coisa que você ache que eu esqueceria de contar.
Não inclua dados pessoais de clientes (nomes, CPF, telefones, e-mails). Nomes de funcionários só como "função" (ex.: vendedora do Televendas).
Responda só com o documento.
```

## Texto B — para a conversa do banco de dados

```
Estou passando o projeto para um chat novo que vai trabalhar direto na pasta do sistema One (versão V17.9).
O One já tem a importação do Nota10 pronta (tela Administração › Importar Nota10; regras em docs/nota10/regras-importacao.md
e docs/nota10/diagnostico.md, que você mesmo ajudou a escrever, e a conferência do faturamento em docs/nota10/conferencia-faturamento.md).
Escreva um documento em Markdown, para eu salvar como "02-banco-de-dados.md", com tudo o que só esta conversa sabe:
1. Como está o banco hoje (o dump do Nota10, o banco da HostGator, o que já foi importado e conferido, e com que números).
2. Achados sobre os dados reais que ainda não viraram regra no sistema (duplicados, campos estranhos, exceções, inconsistências).
3. Conferências feitas (ex.: faturamento por mês) e o que ainda não bate, com os números.
4. Scripts ou consultas úteis que você criou (sem dados pessoais), e onde estão no meu computador.
5. O que você recomenda fazer a seguir no banco.
Sem dados pessoais de clientes (só contagens, códigos, nomes de campos). Responda só com o documento.
```

## Texto C — para o chat novo (na pasta do projeto)

```
Este é o One, o sistema da Perfeita Alquimia, em produção. Antes de qualquer coisa, leia nesta ordem:
CLAUDE.md, docs/contexto/LEIA-PRIMEIRO.md, docs/contexto/01-prototipo.md, docs/contexto/02-banco-de-dados.md,
e os arquivos de docs/nota10/. Depois me diga, em poucas linhas:
1. o que você entendeu do sistema e do jeito de trabalhar (versões, pacote, testes);
2. o que ficou contraditório entre os documentos (se algo divergir, o código e o CLAUDE.md valem mais);
3. o que você sugere como próximos passos, em ordem de prioridade.
Não mude nada ainda.
```

## Dicas

- **O código é a fonte principal.** Os documentos explicam o porquê; o chat novo confirma tudo no código antes de mexer.
- Se uma conversa antiga lembrar de algo diferente do que está no sistema hoje, **o sistema e o CLAUDE.md valem mais**
  (muita coisa mudou depois: faturamento pela data do pedido, cobrança ligada à ficha, desenvolvedor do sistema...).
- Trabalhe numa **branch só** (hoje `ccr-12815ad2-flpiv1`) para não haver duas versões diferentes do One.
- O número da versão continua de onde parou: a próxima é a **V18.0** (ou V17.10, se for pequena).
