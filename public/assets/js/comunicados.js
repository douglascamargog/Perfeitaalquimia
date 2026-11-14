// =====================================================================
// Comunicados: faixa em destaque no topo. Clicar abre a janela com o texto; "Li e entendi" marca como lido
// e a faixa passa para o próximo (sem recarregar). O resumo do sino (a cada 60 s) também atualiza a faixa.
// =====================================================================
const BASE = document.querySelector('meta[name="base"]')?.content || '';
const CSRF = document.querySelector('meta[name="csrf"]')?.content || '';
const lugar = document.querySelector('[data-com-lugar]');
const janela = document.querySelector('[data-com-janela]');

function abrir(id) {
  const modelo = document.querySelector(`template[data-com-texto="${id}"]`);
  if (!modelo || !janela) return;
  janela.querySelector('[data-com-conteudo]').innerHTML = modelo.innerHTML;
  janela.querySelector('[data-com-ok]').dataset.comLer = id;
  janela.hidden = false;
  janela.querySelector('[data-com-ok]').focus();
}

async function ler(id, botao) {
  if (botao) botao.disabled = true;
  const r = await fetch(`${BASE}/comunicados/${id}/ler`, { method: 'POST', headers: { 'X-CSRF-Token': CSRF, Accept: 'application/json' } }).catch(() => null);
  const d = r && r.ok ? await r.json().catch(() => null) : null;
  if (!d) { if (botao) botao.disabled = false; return; }
  if (lugar) lugar.innerHTML = d.faixa;
  if (janela) janela.hidden = true;
  const item = document.getElementById('c' + id); // histórico em /comunicados
  if (item) { item.classList.add('lido'); item.querySelector('.selo.laranja')?.remove(); botao?.remove(); }
}

document.addEventListener('click', (e) => {
  const abre = e.target.closest('[data-com-abrir]');
  if (abre) { abrir(abre.dataset.comAbrir); return; }
  const le = e.target.closest('[data-com-ler]');
  if (le) { ler(le.dataset.comLer, le); return; }
  if (e.target.closest('[data-com-fechar]') || e.target === janela) janela.hidden = true;
});
document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && janela && !janela.hidden) janela.hidden = true; });

// Usado pelo notificacoes.js quando o resumo traz a faixa atualizada
window.__oneFaixaComunicados = (html) => { if (lugar && typeof html === 'string' && (!janela || janela.hidden)) lugar.innerHTML = html; };

// ---------------------------------------------------------------------
// Letreiro do topo: "Novo pedido feito por Fulana" passa em movimento por 30 segundos
// (o comunicado também passa em movimento, mas fica até a pessoa clicar em "Li e entendi").
// ---------------------------------------------------------------------
const letreiro = document.querySelector('[data-letreiro]');
let fila = [];
let rodando = false;
const DURACAO = 30000;

function proximo() {
  if (!letreiro) return;
  const item = fila.shift();
  if (!item) { rodando = false; letreiro.hidden = true; letreiro.innerHTML = ''; return; }
  rodando = true;
  letreiro.hidden = false;
  letreiro.innerHTML = '';
  const trilho = document.createElement('span');
  trilho.className = 'letreiro-rolar';
  trilho.textContent = item.texto;
  trilho.dataset.letreiroPedido = item.id;
  letreiro.append(trilho);
  setTimeout(proximo, DURACAO);
}

window.__oneLetreiro = (itens) => {
  fila.push(...itens.filter((i) => !fila.some((f) => f.id === i.id)));
  if (!rodando) proximo();
};
