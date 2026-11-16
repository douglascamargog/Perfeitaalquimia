#!/usr/bin/env python3
"""
Monta as imagens do One (WebP animado) a partir das folhas de sprites em ferramentas/one/.

Cada folha tem 8 quadros: 4 colunas x 2 linhas, lidos da esquerda para a direita, de cima para baixo (1 a 8).
As animações ficam DENTRO da imagem: funcionam em todo lugar onde o One aparece, sem código nas telas.

    python3 ferramentas/montar_one.py

Para uma folha nova (ex.: comemorando.webp): copie para ferramentas/one/ e acrescente em REACOES abaixo.
Saída: public/assets/img/one/one-<reacao>-padrao.webp e one-<reacao>-invertido.webp (espelhada).
Precisa do Pillow (pip install pillow).
"""
from pathlib import Path
from PIL import Image, ImageOps

RAIZ = Path(__file__).resolve().parent.parent
FOLHAS = RAIZ / 'ferramentas' / 'one'
SAIDA = RAIZ / 'public' / 'assets' / 'img' / 'one'
LARGURA, ALTURA = 276, 300  # mesmo formato de sempre (460 x 500), menor para o arquivo ficar leve

# reação -> (folha, [(quadro 1-8, milissegundos), ...]). O último quadro "segura" a pose antes de repetir.
REACOES = {
    # boas-vindas: acena e fica parado sorrindo um tempo
    'ola':      ('acenando', [(1, 160), (2, 160), (3, 160), (4, 200), (3, 160), (7, 160), (8, 300), (1, 4000)]),
    # dicas leves: parado, sorrindo, e dá uma piscadinha de vez em quando
    'piscando': ('acenando', [(1, 3500), (5, 220), (1, 2500), (8, 400)]),
    # conquista: acena animado, continência e piscadela (até chegar a folha "comemorando")
    'feliz':    ('acenando', [(3, 180), (4, 200), (5, 220), (6, 220), (3, 180), (5, 260), (1, 2500)]),
    # pensando: mão no queixo, olha para cima, faz "hmm"
    'pensando': ('pensando', [(1, 600), (2, 300), (3, 300), (4, 500), (5, 400), (6, 300), (3, 400), (4, 900)]),
    # ideia / sugestão: pensa e chega na resposta, sorrindo
    'ideia':    ('pensando', [(3, 300), (4, 300), (6, 250), (7, 300), (8, 3500)]),
    # algo deu errado / atrasado: triste, com a lágrima
    'ops':      ('triste', [(1, 600), (2, 400), (3, 400), (4, 500), (5, 600), (6, 500), (7, 400), (8, 2500)]),
    # indo buscar / carregando: corre sem parar
    'correndo': ('correndo', [(i, 90) for i in range(1, 9)]),
}


def quadros(folha: str) -> list[Image.Image]:
    """Os 8 quadros da folha, todos com o mesmo recorte (o personagem não 'treme' na animação)."""
    im = Image.open(FOLHAS / f'{folha}.webp').convert('RGBA')
    alfa = im.getchannel('A').point(lambda v: 255 if v > 60 else 0)  # tira o "pó" quase invisível do fundo
    im.putalpha(Image.composite(im.getchannel('A'), Image.new('L', im.size, 0), alfa))
    w, h = im.size[0] // 4, im.size[1] // 2
    celulas = [im.crop((c * w, r * h, (c + 1) * w, (r + 1) * h)) for r in range(2) for c in range(4)]
    caixas = [c.getchannel('A').getbbox() for c in celulas]
    uniao = (min(b[0] for b in caixas), min(b[1] for b in caixas), max(b[2] for b in caixas), max(b[3] for b in caixas))
    recortes = [c.crop(uniao) for c in celulas]
    esc = min((LARGURA - 12) / recortes[0].width, (ALTURA - 10) / recortes[0].height)
    tam = (round(recortes[0].width * esc), round(recortes[0].height * esc))
    saida = []
    for r in recortes:
        tela = Image.new('RGBA', (LARGURA, ALTURA), (0, 0, 0, 0))
        r = r.resize(tam, Image.LANCZOS)
        tela.paste(r, ((LARGURA - tam[0]) // 2, ALTURA - 6 - tam[1]), r)  # apoiado embaixo
        saida.append(tela)
    return saida


def salvar(nome: str, imagens: list[Image.Image], tempos: list[int]) -> None:
    for variacao, lista in (('padrao', imagens), ('invertido', [ImageOps.mirror(i) for i in imagens])):
        arquivo = SAIDA / f'one-{nome}-{variacao}.webp'
        lista[0].save(arquivo, 'WEBP', save_all=True, append_images=lista[1:], duration=tempos, loop=0, quality=86, method=6,
                      lossless=False, allow_mixed=False)
        print(f'{arquivo.name}: {len(lista)} quadros, {arquivo.stat().st_size // 1024} KB')


cache: dict[str, list[Image.Image]] = {}
for reacao, (folha, sequencia) in REACOES.items():
    q = cache.setdefault(folha, quadros(folha))
    salvar(reacao, [q[n - 1] for n, _ in sequencia], [ms for _, ms in sequencia])

# Ícones (aba do navegador): o One parado, sorrindo
icone = cache['acenando'][0]
caixa = icone.getchannel('A').getbbox()
icone = icone.crop(caixa)
lado = max(icone.size)
quadrado = Image.new('RGBA', (lado, lado), (0, 0, 0, 0))
quadrado.paste(icone, ((lado - icone.width) // 2, (lado - icone.height) // 2), icone)
quadrado.resize((64, 64), Image.LANCZOS).save(SAIDA / 'one-icone-64.png')
quadrado.resize((192, 192), Image.LANCZOS).save(SAIDA / 'one-icone-192.png')
print('ícones ok')
