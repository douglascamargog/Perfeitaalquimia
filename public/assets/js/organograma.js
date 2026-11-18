// Organograma: abas, diagrama das interligações (SVG desenhado aqui, sem biblioteca) e o painel de detalhes do setor.
// Os dados vêm de <script id="org-dados"> (app/dados/interligacoes.php + números vivos).

const NS = 'http://www.w3.org/2000/svg';
const LARG = 200;           // largura de cada caixa
const ALT = 92;             // altura de cada caixa
const ESP_V = 20;           // espaço entre caixas
const ESP_H = 96;          // espaço entre colunas
const TOPO = 44;            // espaço para o nome da coluna
const SITUACAO = { ok: 'integrado', parcial: 'pela metade', ilha: 'ilha' };
const COR_COLUNA = ['azul', 'verde', 'ambar', 'petroleo']; // faixa de fundo de cada coluna

// ---------------------------------------------------------------- abas
const abas = document.querySelectorAll('[data-org-aba]');
const paineis = document.querySelectorAll('[data-org-painel]');
function abrirAba(nome) {
  if (![...paineis].some((p) => p.dataset.orgPainel === nome)) nome = 'interligacoes';
  abas.forEach((a) => { const ativa = a.dataset.orgAba === nome; a.classList.toggle('ativa', ativa); a.setAttribute('aria-current', ativa ? 'page' : 'false'); });
  paineis.forEach((p) => { p.hidden = p.dataset.orgPainel !== nome; });
}
abas.forEach((a) => a.addEventListener('click', (ev) => { ev.preventDefault(); history.replaceState(null, '', '#' + a.dataset.orgAba); abrirAba(a.dataset.orgAba); }));
abrirAba(location.hash.replace('#', '') || 'interligacoes');

// ---------------------------------------------------------------- diagrama
const fonte = document.getElementById('org-dados');
const desenho = document.querySelector('[data-org-desenho]');
if (fonte && desenho) {
  const dados = JSON.parse(fonte.textContent);
  const setores = Object.fromEntries(dados.setores.map((s) => [s.id, s]));
  const el = (tag, attrs = {}, pai = null) => {
    const n = document.createElementNS(NS, tag);
    Object.entries(attrs).forEach(([k, v]) => n.setAttribute(k, v));
    if (pai) pai.appendChild(n);
    return n;
  };

  // posição de cada caixa: colunas lado a lado, cada coluna centralizada na altura da maior
  const porColuna = dados.colunas.map((_, i) => dados.setores.filter((s) => s.coluna === i));
  const maior = Math.max(...porColuna.map((c) => c.length));
  const alturaTotal = TOPO + maior * (ALT + ESP_V);
  porColuna.forEach((col, i) => {
    const inicio = TOPO + ((maior - col.length) * (ALT + ESP_V)) / 2;
    col.forEach((s, j) => { s.x = i * (LARG + ESP_H) + 10; s.y = inicio + j * (ALT + ESP_V); });
  });
  const larguraTotal = dados.colunas.length * (LARG + ESP_H) - ESP_H + 100; // sobra à direita para as curvas dentro da mesma coluna

  const svg = el('svg', { viewBox: `0 0 ${larguraTotal} ${alturaTotal}`, class: 'org-svg', role: 'img', 'aria-label': 'Setores e as informações que passam entre eles' });
  svg.style.minWidth = Math.round(larguraTotal * 0.78) + 'px'; // no celular, o diagrama rola de lado dentro do quadro
  const defs = el('defs', {}, svg);
  // pontas das setas: uma por cor (integrado tem a cor de quem entrega) e as de pela metade/ilha
  const pontas = new Set(['parcial', 'ilha', ...dados.setores.map((s) => 'cor-' + s.cor)]);
  pontas.forEach((nome) => {
    const m = el('marker', { id: 'seta-' + nome, class: nome, viewBox: '0 0 10 10', refX: 9, refY: 5, markerWidth: 7, markerHeight: 7, orient: 'auto-start-reverse' }, defs);
    el('path', { d: 'M0,0 L10,5 L0,10 z', class: 'seta seta-' + nome }, m);
  });

  // faixas coloridas atrás de cada coluna, com o nome no topo
  dados.colunas.forEach((nome, i) => {
    const x = i * (LARG + ESP_H) + 10;
    el('rect', { x: x - 10, y: 2, width: LARG + 20, height: alturaTotal - 4, rx: 18, class: `org-faixa-coluna cor-${COR_COLUNA[i] || 'ardosia'}` }, svg);
    const t = el('text', { x: x + LARG / 2, y: 24, class: `org-coluna cor-${COR_COLUNA[i] || 'ardosia'}`, 'text-anchor': 'middle' }, svg);
    t.textContent = nome.toUpperCase();
  });

  // portas: cada ligação sai/chega num ponto diferente do lado da caixa, para as linhas não se sobreporem
  const lado = (de, para) => (para.coluna > de.coluna ? ['d', 'e'] : para.coluna < de.coluna ? ['e', 'd'] : ['d', 'd']);
  const portas = {};
  dados.ligacoes.forEach((l, i) => {
    const [a, b] = [setores[l[0]], setores[l[1]]];
    const [la, lb] = lado(a, b);
    (portas[a.id + la] ??= []).push({ i, outro: b });
    (portas[b.id + lb] ??= []).push({ i, outro: a });
  });
  const yPorta = {};
  Object.entries(portas).forEach(([chave, lista]) => {
    const s = setores[chave.slice(0, -1)];
    lista.sort((p, q) => p.outro.y - q.outro.y);
    lista.forEach((p, k) => { (yPorta[p.i] ??= {})[s.id] = s.y + ((k + 1) * ALT) / (lista.length + 1); });
  });

  const camadaLinhas = el('g', { class: 'org-linhas' }, svg);
  const linhas = dados.ligacoes.map((l, i) => {
    const [a, b] = [setores[l[0]], setores[l[1]]];
    const [la, lb] = lado(a, b);
    const x1 = la === 'd' ? a.x + LARG : a.x;
    const x2 = lb === 'd' ? b.x + LARG : b.x;
    const y1 = yPorta[i][a.id];
    const y2 = yPorta[i][b.id];
    const curva = la === lb ? 70 : Math.max(50, Math.abs(x2 - x1) / 2.2);
    const c1 = la === 'd' ? x1 + curva : x1 - curva;
    const c2 = lb === 'd' ? x2 + curva : x2 - curva;
    const g = el('g', { class: `org-lig ${l[3]} cor-${a.cor}`, 'data-de': l[0], 'data-para': l[1] }, camadaLinhas);
    el('path', { d: `M${x1},${y1} C${c1},${y1} ${c2},${y2} ${x2},${y2}`, class: 'org-lig-largo' }, g); // área de toque maior
    el('path', { d: `M${x1},${y1} C${c1},${y1} ${c2},${y2} ${x2},${y2}`, class: 'org-lig-traco', 'marker-end': `url(#seta-${l[3] === 'ok' ? 'cor-' + a.cor : l[3]})` }, g);
    const titulo = el('title', {}, g);
    titulo.textContent = `${a.nome} → ${b.nome}: ${l[2]} (${SITUACAO[l[3]]})`;
    return g;
  });

  // quebra o nome em até 2 linhas de ~24 letras
  const quebrar = (texto, max = 19) => {
    const linhasTexto = [''];
    texto.split(' ').forEach((p) => {
      const atual = linhasTexto[linhasTexto.length - 1];
      if ((atual + ' ' + p).trim().length > max && atual) linhasTexto.push(p); else linhasTexto[linhasTexto.length - 1] = (atual + ' ' + p).trim();
    });
    return linhasTexto.slice(0, 2);
  };

  const camadaCaixas = el('g', { class: 'org-caixas' }, svg);
  const caixas = dados.setores.map((s) => {
    const g = el('g', { class: `org-no cor-${s.cor}`, transform: `translate(${s.x},${s.y})`,
      tabindex: 0, role: 'button', 'aria-label': s.nome, 'data-setor': s.id }, camadaCaixas);
    el('rect', { width: LARG, height: ALT, rx: 14, class: 'org-no-fundo' }, g);
    el('rect', { width: 6, height: ALT - 24, x: 0, y: 12, rx: 3, class: 'org-no-faixa' }, g);
    el('circle', { cx: 32, cy: 30, r: 16, class: 'org-no-icone-fundo' }, g);
    el('use', { href: `${dados.icones}#${s.icone}`, x: 22, y: 20, width: 20, height: 20, class: 'org-no-icone' }, g);
    const tipo = el('text', { x: 56, y: 22, class: 'org-no-tipo' }, g);
    tipo.textContent = (s.tipo || '').toUpperCase();
    quebrar(s.nome).forEach((linha, k) => { const t = el('text', { x: 56, y: 40 + k * 16, class: 'org-no-nome' }, g); t.textContent = linha; });
    if (s.indicador) {
      const t = el('text', { x: 16, y: ALT - 14, class: 'org-no-num' }, g);
      const v = el('tspan', { class: 'org-no-valor' }, t); v.textContent = s.indicador.valor + ' ';
      const r = el('tspan', {}, t); r.textContent = s.indicador.rotulo;
    }
    const nIlhas = dados.ligacoes.filter((l) => l[3] !== 'ok' && (l[0] === s.id || l[1] === s.id)).length;
    if (nIlhas) {
      el('circle', { cx: LARG - 14, cy: 14, r: 9, class: 'org-no-alerta' }, g);
      const t = el('text', { x: LARG - 14, y: 18, class: 'org-no-alerta-n', 'text-anchor': 'middle' }, g); t.textContent = nIlhas;
      const titulo = el('title', {}, g); titulo.textContent = `${s.nome}: ${nIlhas} ligação(ões) por fazer`;
    }
    return g;
  });
  desenho.appendChild(svg);

  // ---------------------------------------------------------------- seleção e detalhes
  const detalhe = document.querySelector('[data-org-detalhe]');
  const detalheSetor = document.querySelector('[data-org-detalhe-setor]');
  const esc = (t) => String(t ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  let selecionado = null;

  function lista(titulo, itens, lado) {
    const corpo = itens.length ? `<ul class="org-lista">${itens.map((l) => {
      const outro = setores[lado === 'de' ? l[0] : l[1]];
      return `<li class="${l[3]}"><span class="org-chip ${l[3]}">${SITUACAO[l[3]]}</span><span class="org-etiqueta cor-${outro.cor}">${esc(outro.nome)}</span><br><span class="pequeno">${esc(l[2])}</span></li>`;
    }).join('')}</ul>` : '<p class="pequeno suave">Nada por aqui.</p>';
    return `<div class="org-detalhe-col"><h3 class="org-sub">${titulo} (${itens.length})</h3>${corpo}</div>`;
  }

  function selecionar(id) {
    selecionado = selecionado === id ? null : id;
    svg.classList.toggle('com-foco', !!selecionado);
    const vizinhos = new Set([selecionado]);
    linhas.forEach((g) => {
      const liga = selecionado && (g.dataset.de === selecionado || g.dataset.para === selecionado);
      g.classList.toggle('ativa', !!liga);
      if (liga) { vizinhos.add(g.dataset.de); vizinhos.add(g.dataset.para); }
    });
    caixas.forEach((g) => { g.classList.toggle('vizinho', !!selecionado && vizinhos.has(g.dataset.setor)); g.classList.toggle('selecionado', g.dataset.setor === selecionado); });
    if (!selecionado) { detalhe.hidden = true; return; }
    const s = setores[selecionado];
    const recebe = dados.ligacoes.filter((l) => l[1] === s.id);
    const entrega = dados.ligacoes.filter((l) => l[0] === s.id);
    detalheSetor.innerHTML = `
      <div class="org-detalhe-cabeca cor-${s.cor}">
        <div><span class="org-no-tipo-html">${esc(s.tipo)}</span><h2>${esc(s.nome)}</h2><p class="pequeno">${esc(s.texto)}</p></div>
        <div class="org-detalhe-acoes">${s.indicador ? `<span class="org-indicador"><strong>${esc(s.indicador.valor)}</strong> ${esc(s.indicador.rotulo)}</span>` : ''}
          <a class="botao pequeno" href="${esc(dados.base + s.url)}">Abrir ${esc(s.nome)}</a>
          <button type="button" class="botao contorno pequeno" data-org-limpar>Fechar</button></div>
      </div>
      <div class="org-detalhe-colunas">${lista('Recebe de', recebe, 'de')}${lista('Entrega para', entrega, 'para')}</div>`;
    detalhe.hidden = false;
    detalhe.className = `card org-detalhe cor-${s.cor}`;
    detalheSetor.querySelector('[data-org-limpar]').addEventListener('click', () => selecionar(selecionado));
    detalhe.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  }

  caixas.forEach((g) => {
    g.addEventListener('click', () => selecionar(g.dataset.setor));
    g.addEventListener('keydown', (ev) => { if (ev.key === 'Enter' || ev.key === ' ') { ev.preventDefault(); selecionar(g.dataset.setor); } });
  });

  // filtro: todas ou só o que falta ligar
  document.querySelectorAll('[data-org-filtro]').forEach((b) => b.addEventListener('click', () => {
    document.querySelectorAll('[data-org-filtro]').forEach((x) => { const ativo = x === b; x.classList.toggle('ativo', ativo); x.setAttribute('aria-pressed', ativo ? 'true' : 'false'); });
    svg.classList.toggle('so-falta', b.dataset.orgFiltro === 'falta');
  }));
}
