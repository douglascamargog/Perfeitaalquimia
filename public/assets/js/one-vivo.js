// =====================================================================
// One presente na tela (V12.3)
//  - Balão: quando há algo importante na tela, o One puxa conversa sozinho (uma vez por tela por dia),
//    com botões que já fazem a coisa (abrir cliente, ligar, WhatsApp, deixar para amanhã...).
//  - "O que dá pra fazer aqui?": a tela explicada em poucos passos.
//  - Comemoração: confete + One feliz (pedido enviado, agenda zerada, meta batida...).
//  - window.One.dizer(humor, texto): outras telas podem fazer o One falar (ex.: faixa da campanha no pedido).
//  - Tutorial da tela (V13.0, one-tutorial.js): para pessoas novas abre sozinho até ela marcar "Aprendi";
//    para todos, abre em "O que dá pra fazer aqui?".
// Quem desligou em Meu perfil › One não recebe balões sozinhos (a ajuda e as comemorações continuam).
// =====================================================================
import { iniciarTutorial } from './one-tutorial.js';
const BASE = document.querySelector('meta[name="base"]')?.content || '';
const CSRF = document.querySelector('meta[name="csrf"]')?.content || '';
const botao = document.querySelector('[data-one-abrir]');
const img = (humor) => `${BASE}/assets/img/one/one-${humor}-padrao.webp`;
const reduzir = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
const pagina = location.pathname.slice(BASE.length) || '/';
const chaveHoje = 'one-balao:' + pagina + ':' + new Date().toISOString().slice(0, 10);
const guardar = (k, v) => { try { localStorage.setItem(k, v); } catch { /* sem armazenamento */ } };
const lido = (k) => { try { return localStorage.getItem(k); } catch { return null; } };

let balao = null;
let ajuda = null;
let tutorial = null;

function el(tag, attrs = {}, ...filhos) {
  const e = document.createElement(tag);
  Object.entries(attrs).forEach(([k, v]) => (k === 'class' ? (e.className = v) : e.setAttribute(k, v)));
  filhos.flat().forEach((f) => f != null && e.append(f));
  return e;
}

function fechar() {
  balao?.classList.add('saindo');
  balao?.removeAttribute('data-one-balao'); // o que está saindo já não conta como balão aberto
  const b = balao;
  setTimeout(() => b?.remove(), 200);
  balao = null;
}

/** Mostra um balão ao lado do One. conteudo: elementos já montados */
function mostrar(humor, conteudo, { classe = '', fixo = false } = {}) {
  fechar();
  balao = el('div', { class: 'one-balao ' + classe, role: 'status', 'aria-live': 'polite', 'data-one-balao': '' },
    el('img', { src: img(humor), alt: '', width: '46', height: '50' }),
    el('div', { class: 'one-balao-corpo' }, conteudo),
    el('button', { type: 'button', class: 'one-balao-fechar', 'aria-label': 'Fechar', 'data-one-balao-fechar': '' }, '✕'));
  document.body.append(balao);
  botao?.classList.add('falando');
  if (!fixo) {
    // some sozinho depois de um tempo se a pessoa não mexer
    let t = setTimeout(fechar, 20000);
    balao.addEventListener('mouseenter', () => clearTimeout(t));
    balao.addEventListener('mouseleave', () => { t = setTimeout(fechar, 8000); });
  }
}

function acaoEl(a) {
  if (a.tipo === 'post') {
    const f = el('form', { method: 'post', action: BASE + a.url, class: 'one-acao-form' }, el('input', { type: 'hidden', name: '_csrf', value: CSRF }));
    Object.entries(a.campos || {}).forEach(([k, v]) => f.append(el('input', { type: 'hidden', name: k, value: v })));
    f.append(el('button', { type: 'submit', class: 'one-acao' }, a.rotulo));
    return f;
  }
  const externo = a.tipo === 'externo';
  return el('a', { class: 'one-acao' + (a.rotulo === 'WhatsApp' ? ' whats' : ''), href: externo ? a.url : BASE + a.url, ...(externo && a.url.startsWith('http') ? { target: '_blank', rel: 'noopener noreferrer' } : {}) }, a.rotulo);
}

function mostrarBalao(b) {
  const acoes = el('div', { class: 'one-acoes' }, (b.acoes || []).map(acaoEl), b.link && !(b.acoes || []).length ? acaoEl({ rotulo: b.acao || 'Abrir', tipo: 'link', url: b.link }) : null);
  const rodape = el('div', { class: 'one-balao-rodape' },
    ajuda ? el('button', { type: 'button', 'data-one-ajuda': '' }, 'O que dá pra fazer aqui?') : null,
    el('button', { type: 'button', 'data-one-hoje-nao': '' }, 'Hoje não'));
  mostrar(b.humor || 'ideia', [el('p', {}, b.texto), acoes, rodape]);
}

function abrirTutorial() {
  fechar();
  const painel = document.getElementById('one-painel'); // o painel do One fecha para a tela aparecer
  if (painel && !painel.hidden) { painel.hidden = true; botao?.setAttribute('aria-expanded', 'false'); }
  iniciarTutorial(tutorial, {
    aoAprender: (d) => {
      if (d && d.terminou) { location.reload(); return; } // a comemoração vem na próxima tela
      const falta = d ? d.total - d.feitas : null;
      mostrar('feliz', [el('p', {}, falta > 0 ? `Boa! Você já aprendeu ${d.feitas} de ${d.total} telas. Faltam ${falta}.` : 'Boa! Anotado que você aprendeu esta tela.')]);
    },
  });
}

function mostrarAjuda() {
  if (tutorial) { abrirTutorial(); return; }
  if (!ajuda) return;
  const lista = el('ol', {}, ajuda.passos.filter(Boolean).map((p) => el('li', {}, p)));
  mostrar('piscando', [el('strong', { class: 'one-ajuda-titulo' }, 'Nesta tela: ' + ajuda.titulo), lista,
    ajuda.aula ? el('div', { class: 'one-acoes' }, acaoEl({ rotulo: 'Ver a aula completa', tipo: 'link', url: ajuda.aula })) : null], { classe: 'ajuda', fixo: true });
}

document.addEventListener('click', (ev) => {
  if (ev.target.closest('[data-one-balao-fechar]')) fechar();
  if (ev.target.closest('[data-one-hoje-nao]')) { guardar(chaveHoje, 'nao'); fechar(); }
  if (ev.target.closest('[data-one-ajuda]')) mostrarAjuda();
});
document.addEventListener('keydown', (e) => { if (e.key === 'Escape') fechar(); });

// ---------------------------------------------------------------- comemoração
function confete() {
  if (reduzir) return;
  const tela = el('canvas', { class: 'aniv-confete', 'aria-hidden': 'true' });
  document.body.append(tela);
  const ctx = tela.getContext('2d');
  tela.width = innerWidth; tela.height = innerHeight;
  const cores = ['#5cd4b6', '#025150', '#f472b6', '#facc15', '#60a5fa', '#fb923c'];
  const pedacos = Array.from({ length: 140 }, () => ({ x: Math.random() * innerWidth, y: -20 - Math.random() * innerHeight * 0.6, l: 6 + Math.random() * 6, a: 4 + Math.random() * 4,
    vy: 2 + Math.random() * 3, vx: -1 + Math.random() * 2, g: Math.random() * Math.PI, vg: -0.1 + Math.random() * 0.2, cor: cores[Math.floor(Math.random() * cores.length)] }));
  const inicio = performance.now();
  const quadro = (t) => {
    ctx.clearRect(0, 0, tela.width, tela.height);
    let vivos = 0;
    for (const p of pedacos) {
      p.y += p.vy; p.x += p.vx + Math.sin(p.g) * 0.6; p.g += p.vg;
      if (p.y > tela.height + 20) { if (t - inicio > 2500) continue; p.y = -20; }
      vivos++;
      ctx.save(); ctx.translate(p.x, p.y); ctx.rotate(p.g); ctx.fillStyle = p.cor; ctx.fillRect(-p.l / 2, -p.a / 2, p.l, p.a); ctx.restore();
    }
    if (vivos) requestAnimationFrame(quadro); else tela.remove();
  };
  requestAnimationFrame(quadro);
}

const festa = document.getElementById('one-festa');
let festejou = false;
if (festa) {
  try {
    const f = JSON.parse(festa.textContent);
    festejou = true;
    confete();
    botao?.classList.add('pulando');
    setTimeout(() => mostrar('feliz', [el('strong', { class: 'one-ajuda-titulo' }, f.titulo), el('p', {}, f.texto)], { classe: 'festa' }), 300);
  } catch { /* ignora */ }
}

// ---------------------------------------------------------------- balão da tela
window.One = { dizer: (humor, texto) => mostrar(humor, [el('p', {}, texto)]), tutorial: () => mostrarAjuda() };

(async () => {
  if (!botao) return;
  try {
    const r = await fetch(`${BASE}/one/tela?pagina=${encodeURIComponent(pagina + location.search)}`, { headers: { Accept: 'application/json' } });
    if (!r.ok) return;
    const d = await r.json();
    ajuda = d.ajuda;
    tutorial = d.tutorial || null;
    if (tutorial?.automatico) {
      // pessoa nova: o tutorial da tela abre sozinho (espera a janela de novidades/comunicado fechar)
      const aberta = () => [...document.querySelectorAll('[data-novidades], .com-janela-fundo')].some((j) => !j.hidden && j.getClientRects().length);
      let tentativas = 0;
      const tentar = () => { if (aberta() && tentativas++ < 120) setTimeout(tentar, 1000); else abrirTutorial(); };
      setTimeout(tentar, festejou ? 3500 : 700);
      return;
    }
    // Depois do horário de descanso: o One (dormindo) avisa uma vez por dia que é hora de descansar
    const chaveDescanso = 'one-descanso:' + new Date().toISOString().slice(0, 10);
    if (botao.dataset.oneDescanso && !lido(chaveDescanso) && !festejou) {
      setTimeout(() => { mostrar('dormindo', [el('p', {}, botao.dataset.oneDescanso)]); guardar(chaveDescanso, 'visto'); }, 1200);
      return;
    }
    if (d.balao) {
      botao.classList.add('tem-dica'); // pontinho: o One tem algo para falar
      botao.dataset.dica = d.balao.texto;
      // não atrapalha: com a janela de novidades ou um comunicado aberto, o One espera a próxima tela
      if (!festejou && !lido(chaveHoje) && ![...document.querySelectorAll('[data-novidades], .com-janela-fundo')].some((j) => !j.hidden && j.getClientRects().length)) {
        setTimeout(() => { mostrarBalao(d.balao); guardar(chaveHoje, 'visto'); }, 1200);
      }
    }
  } catch { /* sem internet: o One fica quieto */ }
})();

// ---------------------------------------------------------------- Pergunte ao One
// A resposta vem em markdown simples. Aqui tudo é escapado e só viram HTML: **negrito**, [texto](/caminho interno) e listas "- ".
function formatar(texto) {
  const esc = (t) => t.replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const linha = (t) => esc(t)
    .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
    .replace(/\[([^\]]+)\]\((\/[^)\s]*)\)/g, (m, rotulo, caminho) => (caminho.startsWith('//') ? rotulo : `<a href="${BASE}${caminho}">${rotulo}</a>`));
  const blocos = [];
  let lista = [];
  const fecharLista = () => { if (lista.length) { blocos.push('<ul>' + lista.map((i) => `<li>${i}</li>`).join('') + '</ul>'); lista = []; } };
  texto.split('\n').forEach((l) => {
    const m = l.match(/^\s*[-•]\s+(.*)$/);
    if (m) lista.push(linha(m[1]));
    else { fecharLista(); if (l.trim()) blocos.push(`<p>${linha(l)}</p>`); }
  });
  fecharLista();
  return blocos.join('');
}

const formPergunta = document.querySelector('[data-one-pergunte]');
const conversa = document.querySelector('[data-one-conversa]');

// Especialistas do One (V15.0): cada um com a sua conversa e os seus exemplos de pergunta
const barraEsp = document.querySelector('[data-one-especialistas]');
let especialista = '';
try { especialista = sessionStorage.getItem('one-especialista') || ''; } catch { /* navegação privada */ }
function escolherEspecialista(slug, limpar = true) {
  const botoes = [...document.querySelectorAll('[data-one-especialista]')];
  const alvo = botoes.find((b) => b.dataset.oneEspecialista === slug) || botoes.find((b) => b.dataset.oneEspecialista === barraEsp?.dataset.padrao) || botoes[0];
  if (!alvo) return;
  especialista = alvo.dataset.oneEspecialista;
  try { sessionStorage.setItem('one-especialista', especialista); } catch { /* ok */ }
  botoes.forEach((b) => b.setAttribute('aria-selected', String(b === alvo)));
  if (!limpar || !conversa) return;
  conversa.querySelectorAll('.one-msg').forEach((m) => m.remove());
  const vazio = document.querySelector('[data-one-sugestoes]');
  vazio?.removeAttribute('hidden');
  const intro = document.querySelector('[data-one-esp-intro]');
  if (intro) intro.textContent = especialista === 'geral' ? 'Pergunte do seu jeito, como falaria com um colega. Por exemplo:' : `${alvo.dataset.nome}: ${alvo.dataset.descricao} Por exemplo:`;
  const exemplos = document.querySelector('[data-one-exemplos]');
  if (exemplos) {
    exemplos.replaceChildren(...JSON.parse(alvo.dataset.sugestoes || '[]').map((t) => el('button', { type: 'button', 'data-one-exemplo': '' }, t)));
  }
}
if (barraEsp) escolherEspecialista(especialista, true);
document.addEventListener('click', (ev) => {
  const b = ev.target.closest('[data-one-especialista]');
  if (b && b.dataset.oneEspecialista !== especialista) escolherEspecialista(b.dataset.oneEspecialista);
});
function bolha(quem, html) {
  const b = el('div', { class: 'one-msg ' + quem });
  if (quem.startsWith('one')) b.append(el('img', { src: img('piscando'), alt: '', width: '28', height: '31' }));
  const corpo = el('div', { class: 'one-msg-corpo' });
  corpo.innerHTML = html;
  b.append(corpo);
  conversa.append(b);
  conversa.scrollTop = conversa.scrollHeight;
  return b;
}
async function perguntar(texto) {
  if (!texto.trim()) return;
  document.querySelector('[data-one-sugestoes]')?.setAttribute('hidden', '');
  abrirAba('perguntar');
  bolha('pessoa', formatar(texto));
  const pensando = bolha('one pensando', '<p>Deixa eu ver…</p>');
  pensando.querySelector('img').src = img('pensando');
  const botaoEnviar = formPergunta.querySelector('button');
  botaoEnviar.disabled = true;
  try {
    const corpo = new FormData(); corpo.append('pergunta', texto); corpo.append('especialista', especialista); corpo.append('_csrf', CSRF);
    const r = await fetch(`${BASE}/one/perguntar`, { method: 'POST', body: corpo, headers: { Accept: 'application/json', 'X-CSRF-Token': CSRF } });
    const d = await r.json();
    pensando.remove();
    bolha('one', formatar(d.texto || 'Não entendi. Pode perguntar de outro jeito?'));
  } catch {
    pensando.remove();
    bolha('one', '<p>Sem conexão agora. Tente de novo em instantes.</p>');
  } finally {
    botaoEnviar.disabled = false;
  }
}
formPergunta?.addEventListener('submit', (ev) => {
  ev.preventDefault();
  const campo = formPergunta.pergunta;
  const texto = campo.value;
  campo.value = '';
  perguntar(texto);
});
document.addEventListener('click', (ev) => {
  const ex = ev.target.closest('[data-one-exemplo]');
  if (ex) perguntar(ex.textContent);
  if (ev.target.closest('[data-one-limpar]') && conversa) {
    conversa.querySelectorAll('.one-msg').forEach((m) => m.remove());
    document.querySelector('[data-one-sugestoes]')?.removeAttribute('hidden');
    const corpo = new FormData(); corpo.append('esquecer', '1'); corpo.append('especialista', especialista); corpo.append('_csrf', CSRF);
    fetch(`${BASE}/one/perguntar`, { method: 'POST', body: corpo, headers: { 'X-CSRF-Token': CSRF } }).catch(() => {});
  }
});

// Abas do painel: "Para você" (dicas) e "Perguntar" (conversa)
function abrirAba(nome) {
  document.querySelectorAll('[data-one-aba]').forEach((b) => b.setAttribute('aria-selected', String(b.dataset.oneAba === nome)));
  document.querySelectorAll('[data-one-aba-painel]').forEach((p) => { p.hidden = p.dataset.oneAbaPainel !== nome; });
  if (nome === 'perguntar') setTimeout(() => formPergunta?.pergunta.focus(), 50);
}
document.addEventListener('click', (ev) => {
  const aba = ev.target.closest('[data-one-aba]');
  if (aba) abrirAba(aba.dataset.oneAba);
});
