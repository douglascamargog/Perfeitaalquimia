#!/usr/bin/env python3
"""
Gera public/assets/css/tema-escuro.css: a versão escura das cores FIXAS dos CSS do sistema.

As cores principais (fundo, cartão, texto, borda, primária...) já mudam sozinhas pelas variáveis
(app/Services/Aparencia.php). Esta ferramenta cuida do resto: selos, pílulas e cartões com cor própria.
  - fundo claro fixo  -> o mesmo tom, escurecido (misturado com o cartão escuro)
  - texto escuro fixo -> o mesmo tom, clareado
  - borda clara fixa  -> o mesmo tom, escurecido
Tudo fica dentro de "html.escuro" e "@media screen" (a impressão continua clara).

    python3 ferramentas/gerar_tema_escuro.py      (rode de novo sempre que mudar as cores de algum CSS)
"""
import re
from pathlib import Path

RAIZ = Path(__file__).resolve().parent.parent
CSS = RAIZ / 'public' / 'assets' / 'css'
SAIDA = CSS / 'tema-escuro.css'
PULAR = {'tema-escuro.css', 'catalogo.css', 'orcamento-cliente.css', 'op-impressa.css', 'ficha-impressa.css'}

COR = re.compile(r'#[0-9a-fA-F]{3,8}\b|rgba?\([^)]*\)|\bwhite\b')


def rgb(c: str):
    c = c.strip().lower()
    if c == 'white':
        return (255, 255, 255, 1.0)
    if c.startswith('#'):
        h = c[1:]
        if len(h) in (3, 4):
            h = ''.join(x * 2 for x in h)
        a = int(h[6:8], 16) / 255 if len(h) == 8 else 1.0
        return (int(h[0:2], 16), int(h[2:4], 16), int(h[4:6], 16), a)
    n = [float(x) for x in re.findall(r'[\d.]+', c)]
    return (n[0], n[1], n[2], n[3] if len(n) > 3 else 1.0)


def luz(c: str) -> float:
    r, g, b, _ = rgb(c)
    return (0.299 * r + 0.587 * g + 0.114 * b) / 255


def escurecer(c: str, quanto: int = 18) -> str:
    """Fundo claro -> o mesmo tom, só que escuro (sobre o cartão escuro)."""
    return f'color-mix(in srgb, {c} {quanto}%, var(--card))'


def clarear(c: str) -> str:
    """Texto escuro -> o mesmo tom, legível no escuro."""
    return f'color-mix(in srgb, {c} 45%, #ffffff)'


def regras(texto: str):
    """(media, seletor, declarações) — entende @media de um nível; pula @media print e @keyframes."""
    texto = re.sub(r'/\*.*?\*/', '', texto, flags=re.S)
    i, n, media = 0, len(texto), None
    pilha = []
    inicio = 0
    while i < n:
        ch = texto[i]
        if ch == '{':
            cabeca = texto[inicio:i].strip()
            if cabeca.startswith('@'):
                pilha.append(cabeca)
                inicio = i + 1
            else:
                fim = texto.index('}', i)
                yield (pilha[-1] if pilha else None, cabeca, texto[i + 1:fim])
                i = fim
                inicio = i + 1
        elif ch == '}':
            if pilha:
                pilha.pop()
            inicio = i + 1
        i += 1


def converter(media, seletor, decls):
    if media and ('print' in media or 'keyframes' in media or media.startswith('@font-face') or 'prefers-reduced' in media):
        return None
    # logotipos e fotos de produto ficam com o fundo branco de propósito
    if not seletor or seletor.startswith('@') or ':root' in seletor or 'html.escuro' in seletor or 'logo' in seletor:
        return None
    novas = []
    for d in decls.split(';'):
        if ':' not in d:
            continue
        prop, valor = d.split(':', 1)
        prop, valor = prop.strip().lower(), valor.strip()
        if 'var(' in valor and not COR.search(valor):
            continue
        cores = COR.findall(valor)
        if not cores:
            continue
        if prop in ('background', 'background-color', 'background-image'):
            eh_clara = lambda c: rgb(c)[3] > 0.5 and luz(c) > 0.78
            if not any(eh_clara(c) for c in cores):
                continue
            novas.append(f'{prop}: ' + COR.sub(lambda m: escurecer(m.group()) if eh_clara(m.group()) else m.group(), valor))
        elif prop == 'color':
            c = cores[0]
            if rgb(c)[3] > 0.5 and luz(c) < 0.42:
                novas.append(f'color: {clarear(c)}')
        elif prop.startswith('border') and prop != 'border-radius':
            eh_clara = lambda c: rgb(c)[3] > 0.5 and luz(c) > 0.78
            if any(eh_clara(c) for c in cores):
                novas.append(f'{prop}: ' + COR.sub(lambda m: escurecer(m.group(), 35) if eh_clara(m.group()) else m.group(), valor))
    # Fundo na cor de destaque (menta/lima): continua claro no escuro, então o texto dentro dele fica escuro
    destaque = re.search(r'background[^;]*var\(--(secundaria|lima)\)', decls) and 'secundaria-texto' not in decls
    if destaque and not novas:
        alvo = ', '.join(f'html.escuro {s.strip()}, html.escuro {s.strip()} *' for s in seletor.split(',')
                         if s.strip() and ':hover' not in s and '::' not in s)
        return (media, alvo + ' { color: var(--secundaria-texto); }') if alvo else None
    if not novas:
        return None
    alvo = ', '.join('html.escuro ' + s.strip() for s in seletor.split(',') if s.strip())
    return media, alvo + ' { ' + '; '.join(novas) + '; }'


def main():
    blocos = {}
    for arq in sorted(CSS.glob('*.css')):
        if arq.name in PULAR:
            continue
        for media, sel, decls in regras(arq.read_text(encoding='utf-8')):
            r = converter(media, sel, decls)
            if r:
                blocos.setdefault((arq.name, r[0]), []).append(r[1])
    linhas = ['/* GERADO por ferramentas/gerar_tema_escuro.py — não edite à mão (rode a ferramenta de novo).',
              '   Versão escura das cores fixas dos CSS. As cores principais vêm das variáveis (Aparencia.php). */',
              '@media screen {']
    total = 0
    for (arquivo, media), lista in blocos.items():
        linhas.append(f'/* {arquivo}{" " + media if media else ""} */')
        if media:
            linhas.append(media + ' {')
        linhas.extend(lista)
        if media:
            linhas.append('}')
        total += len(lista)
    linhas.append('}')
    SAIDA.write_text('\n'.join(linhas) + '\n', encoding='utf-8')
    print(f'{SAIDA.relative_to(RAIZ)}: {total} regras')


main()
