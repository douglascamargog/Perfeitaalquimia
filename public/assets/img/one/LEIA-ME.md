# One — mascote da Perfeita Alquimia

As imagens são **WebP animados** (276 × 300, fundo transparente), montados pela ferramenta
`ferramentas/montar_one.py` a partir das folhas de sprites em `ferramentas/one/` (8 quadros: 4 colunas × 2 linhas).
Cada animação termina com uma pausa, para o One não ficar se mexendo o tempo todo.

`one-<reacao>-padrao.webp` (olhando para a direita) e `one-<reacao>-invertido.webp` (espelhado).

| reação     | folha     | Quando aparece                                              |
|------------|-----------|-------------------------------------------------------------|
| `ola`      | acenando  | Boas-vindas (login, Início, primeiro passo do tutorial)      |
| `piscando` | acenando  | Parado, sorrindo e piscando: botão do One, dicas leves       |
| `feliz`    | acenando  | Conquistas: meta batida, pedido enviado, tutorial aprendido  |
| `ideia`    | pensando  | Sugestão: pensa e chega na resposta, sorrindo                |
| `pensando` | pensando  | Procurando uma resposta (Pergunte ao One)                    |
| `ops`      | triste    | Algo atrasado ou que deu errado                              |
| `correndo` | correndo  | Indo buscar: carregando as dicas, buscando contatos na web   |
| `bravo`    | (estático)| Reservado                                                    |

Folha nova (ex.: comemorando): coloque em `ferramentas/one/`, acrescente em `REACOES` no `montar_one.py`
e rode `python3 ferramentas/montar_one.py`.
