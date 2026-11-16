// =====================================================================
// Tutorial do One em cada tela (V13.0)
//  - A tela escurece e o One aponta cada parte, um passo de cada vez ("Voltar", "Próximo", "Pular por agora").
//  - No último passo: "Aprendi tudo desta tela ✓" (grava no servidor; para pessoas novas o tutorial
//    abre sozinho em cada tela até ela marcar que aprendeu).
//  - Se a parte apontada não estiver na tela (lista vazia, aba escondida, celular), o balão aparece no meio.
// Usado pelo one-vivo.js: iniciarTutorial(tutorial, { aoAprender }).
// =====================================================================
const BASE = document.querySelector('meta[name="base"]')?.content || '';
const CSRF = document.querySelector('meta[name="csrf"]')?.content || '';
const img = (humor) => `${BASE}/assets/img/one/one-${humor}-padrao.svg`;

let atual = null; // { t, i, fundo, luz, balao, alvo }

function el(tag, attrs = {}, ...filhos) {
  const e = document.createElement(tag);
  Object.entries(attrs).forEach(([k, v]) => (k === 'class' ? (e.className = v) : e.setAttribute(k, v)));
  filhos.flat().forEach((f) => f != null && e.append(f));
  return e;
}

/** O elemento do passo, se existir e estiver visível na tela */
function alvoDo(passo) {
  if (!passo.alvo) return null;
  let e = null;
  try { e = document.querySelector(passo.alvo); } catch { return null; } // seletor inválido: balão no meio
  if (!e || !e.getClientRects().length) return null;
  const r = e.getBoundingClientRect();
  if (r.width < 2 || r.height < 2) return null;
  const estilo = getComputedStyle(e);
  if (estilo.visibility === 'hidden' || estilo.display === 'none') return null;
  return e;
}

function posicionar() {
  if (!atual) return;
  const { luz, balao, alvo } = atual;
  const celular = innerWidth < 640;
  if (!alvo) {
    luz.hidden = true;
    atual.fundo.classList.add('cheio');
    balao.className = 'tut-balao meio' + (celular ? ' celular' : '');
    balao.style.top = balao.style.left = '';
    return;
  }
  atual.fundo.classList.remove('cheio');
  const r = alvo.getBoundingClientRect();
  const folga = 6;
  luz.hidden = false;
  Object.assign(luz.style, { top: r.top - folga + 'px', left: r.left - folga + 'px', width: r.width + folga * 2 + 'px', height: r.height + folga * 2 + 'px' });
  if (celular) {
    balao.className = 'tut-balao celular';
    balao.style.top = balao.style.left = '';
    // no celular o balão fica embaixo; se a parte apontada estiver lá, ele sobe
    if (r.bottom > innerHeight - balao.offsetHeight - 20) balao.classList.add('em-cima');
    return;
  }
  balao.className = 'tut-balao';
  const larg = balao.offsetWidth;
  const alt = balao.offsetHeight;
  let top = r.bottom + 14;
  let left = r.left + r.width / 2 - larg / 2;
  if (top + alt > innerHeight - 10) top = r.top - alt - 14;           // não cabe embaixo: em cima
  if (top < 10) {
    // parte alta (ex.: o menu): o balão vai para o lado, se couber
    top = Math.min(innerHeight - alt - 10, Math.max(10, r.top + 10));
    if (r.right + 14 + larg < innerWidth - 10) left = r.right + 14;
    else if (r.left - 14 - larg > 10) left = r.left - 14 - larg;
  }
  left = Math.max(10, Math.min(innerWidth - larg - 10, left));
  balao.style.top = Math.max(10, top) + 'px';
  balao.style.left = left + 'px';
}

function mostrarPasso(i) {
  const { t, balao } = atual;
  atual.i = i;
  const passo = t.passos[i];
  const ultimo = i === t.passos.length - 1;
  atual.alvo = alvoDo(passo);
  if (atual.alvo) atual.alvo.scrollIntoView({ block: 'center', behavior: 'instant' });
  balao.replaceChildren(...[
    el('div', { class: 'tut-topo' },
      el('img', { src: img(ultimo ? 'feliz' : i === 0 ? 'ola' : 'ideia'), alt: '', width: '40', height: '44' }),
      el('div', {}, el('strong', {}, t.titulo), el('small', {}, `Passo ${i + 1} de ${t.passos.length}`)),
      el('button', { type: 'button', class: 'tut-x', 'aria-label': 'Fechar', 'data-tut-pular': '' }, '✕')),
    el('p', { class: 'tut-texto' }, passo.texto),
    el('div', { class: 'tut-pontos', 'aria-hidden': 'true' }, t.passos.map((_, k) => el('span', { class: k === i ? 'ativo' : k < i ? 'visto' : '' }))),
    el('div', { class: 'tut-botoes' },
      i > 0 ? el('button', { type: 'button', class: 'botao contorno pequeno', 'data-tut-voltar': '' }, '← Voltar') : el('button', { type: 'button', class: 'botao contorno pequeno', 'data-tut-pular': '' }, t.aprendido ? 'Fechar' : 'Pular por agora'),
      ultimo
        ? (t.aprendido
          ? el('button', { type: 'button', class: 'botao pequeno', 'data-tut-pular': '' }, 'Fechar ✓')
          : el('button', { type: 'button', class: 'botao pequeno', 'data-tut-aprendi': '' }, 'Aprendi tudo desta tela ✓'))
        : el('button', { type: 'button', class: 'botao pequeno', 'data-tut-proximo': '' }, 'Próximo →')),
    t.automatico && !ultimo ? el('small', { class: 'tut-nota' }, 'Enquanto você não marcar que aprendeu, eu mostro de novo quando você voltar aqui.') : null,
  ].filter(Boolean)); // replaceChildren escreveria "null" na tela
  posicionar();
  requestAnimationFrame(posicionar); // depois de rolar a tela
  balao.querySelector('[data-tut-proximo], [data-tut-aprendi]')?.focus({ preventScroll: true });
}

export function fecharTutorial() {
  if (!atual) return;
  atual.fundo.remove();
  atual.luz.remove();
  atual.balao.remove();
  removeEventListener('resize', posicionar);
  removeEventListener('scroll', posicionar, true);
  document.removeEventListener('keydown', teclas);
  atual = null;
}

function teclas(e) {
  if (!atual) return;
  if (e.key === 'Escape') fecharTutorial();
  if (e.key === 'ArrowRight' && atual.i < atual.t.passos.length - 1) mostrarPasso(atual.i + 1);
  if (e.key === 'ArrowLeft' && atual.i > 0) mostrarPasso(atual.i - 1);
}

async function aprendi() {
  const { t, opcoes } = atual;
  const botao = atual.balao.querySelector('[data-tut-aprendi]');
  if (botao) botao.disabled = true;
  let d = null;
  try {
    const corpo = new FormData(); corpo.append('rota', t.rota); corpo.append('_csrf', CSRF);
    const r = await fetch(`${BASE}/tutorial/aprendi`, { method: 'POST', body: corpo, headers: { Accept: 'application/json', 'X-CSRF-Token': CSRF } });
    d = await r.json();
  } catch { /* sem conexão: tenta de novo na próxima visita */ }
  t.aprendido = true;
  t.automatico = false;
  fecharTutorial();
  opcoes.aoAprender?.(d);
}

/** Começa o tutorial. t = { rota, titulo, passos: [{alvo, texto}], aprendido, automatico, feitas, total } */
export function iniciarTutorial(t, opcoes = {}) {
  if (!t || !t.passos?.length) return;
  fecharTutorial();
  const fundo = el('div', { class: 'tut-fundo', 'data-tutorial-aberto': '' });
  const luz = el('div', { class: 'tut-luz', 'aria-hidden': 'true' });
  const balao = el('div', { class: 'tut-balao', role: 'dialog', 'aria-modal': 'true', 'aria-label': 'Tutorial: ' + t.titulo, 'data-tut-balao': '' });
  document.body.append(fundo, luz, balao);
  atual = { t, i: 0, fundo, luz, balao, alvo: null, opcoes };
  balao.addEventListener('click', (ev) => {
    if (ev.target.closest('[data-tut-proximo]')) mostrarPasso(atual.i + 1);
    else if (ev.target.closest('[data-tut-voltar]')) mostrarPasso(atual.i - 1);
    else if (ev.target.closest('[data-tut-aprendi]')) aprendi();
    else if (ev.target.closest('[data-tut-pular]')) fecharTutorial();
  });
  addEventListener('resize', posicionar);
  addEventListener('scroll', posicionar, true);
  document.addEventListener('keydown', teclas);
  mostrarPasso(0);
}
