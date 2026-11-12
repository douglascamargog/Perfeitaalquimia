# One — mascote IA da Perfeita Alquimia

Pacote para integrar o One no sistema.

## Conteúdo

- `svg/` — 12 arquivos SVG: 6 expressões × 2 variações (`one-<expressao>-<variacao>.svg`).
- `One.jsx` — componente React com todas as expressões embutidas.
- `one.js` — função sem framework que devolve o SVG como string.

## Expressões

| id         | Quando usar                                              |
|------------|----------------------------------------------------------|
| `ola`      | Boas-vindas e início de conversa                         |
| `pensando` | Enquanto busca dados ou monta a resposta                 |
| `feliz`    | Tarefa concluída, meta batida, confirmação               |
| `ops`      | Erro, dado não encontrado ou pedido não entendido        |
| `ideia`    | Quando traz uma sugestão ou um atalho                    |
| `piscando` | Dicas rápidas e mensagens leves                          |

## Variações

- `padrao` — corpo petróleo (#025150). Use sobre fundo branco ou rosado claro.
- `invertido` — corpo branco. Use sobre o petróleo do header e no botão flutuante.

## Uso — React

```jsx
import One from './One';

<One mood="pensando" variant="padrao" size={32} />
<One mood="ola" variant="invertido" size={40} title="Abrir o One" />
```

## Uso — HTML / JS puro

```js
import { oneSvg } from './one.js';
document.querySelector('#avatar').innerHTML = oneSvg({ mood: 'feliz', size: 48 });
```

Ou direto com o arquivo: `<img src="svg/one-ola-padrao.svg" width="48" alt="One">`.

## Tamanhos

Proporção fixa 200 × 220 (altura = largura × 1,1).
24 px ícone · 32 px mensagens · 48 px cabeçalho do chat · 96 px estado vazio · 160 px boas-vindas.
Não use abaixo de 24 px.

## Cores

Petróleo #025150 · Floresta #09463a · Verde logo #15a454 · Verde escuro #087a45 · Menta #5cd4b6 · Branco #ffffff
