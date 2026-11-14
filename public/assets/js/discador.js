// =====================================================================
// Discador do topo (Televendas e Cobrança): digita qualquer número e liga pelo Ligue Talk.
// Se o número for de um cliente, mostra o atalho para a ficha (a ligação já vai para a linha do tempo dele).
// Os últimos números ficam só neste aparelho (localStorage).
// =====================================================================
const BASE = document.querySelector('meta[name="base"]')?.content || '';
const CSRF = document.querySelector('meta[name="csrf"]')?.content || '';
const caixa = document.querySelector('[data-discador]');
const CHAVE = 'one:discador';

const lerRecentes = () => { try { return JSON.parse(localStorage.getItem(CHAVE) || '[]'); } catch { return []; } };
const guardar = (n) => { try { localStorage.setItem(CHAVE, JSON.stringify([n, ...lerRecentes().filter((x) => x !== n)].slice(0, 5))); } catch { /* sem armazenamento */ } };

function mascara(v) {
  const d = v.replace(/\D/g, '').slice(0, 11);
  if (d.length <= 2) return d ? `(${d}` : '';
  if (d.length <= 6) return `(${d.slice(0, 2)}) ${d.slice(2)}`;
  if (d.length <= 10) return `(${d.slice(0, 2)}) ${d.slice(2, 6)}-${d.slice(6)}`;
  return `(${d.slice(0, 2)}) ${d.slice(2, 7)}-${d.slice(7)}`;
}

if (caixa) {
  const botao = caixa.querySelector('[data-discador-botao]');
  const painel = caixa.querySelector('[data-discador-painel]');
  const campo = caixa.querySelector('[data-discador-numero]');
  const msg = caixa.querySelector('[data-discador-msg]');
  const recentes = caixa.querySelector('[data-discador-recentes]');

  const mostrarRecentes = () => {
    const lista = lerRecentes();
    recentes.innerHTML = lista.length ? '<small>Últimos números</small>' + lista.map((n) => `<button type="button" data-recente="${n}">${mascara(n)}</button>`).join('') : '';
  };
  const abrir = (sim) => {
    painel.hidden = !sim;
    botao.setAttribute('aria-expanded', sim);
    if (sim) { mostrarRecentes(); campo.focus(); }
  };

  botao.addEventListener('click', (e) => { e.stopPropagation(); abrir(painel.hidden); });
  document.addEventListener('click', (e) => { if (!painel.hidden && !caixa.contains(e.target)) abrir(false); });
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && !painel.hidden) abrir(false); });
  campo.addEventListener('input', () => { campo.value = mascara(campo.value); });
  caixa.addEventListener('click', (e) => {
    const tecla = e.target.closest('[data-tecla]');
    if (tecla) {
      const d = campo.value.replace(/\D/g, '');
      campo.value = mascara(tecla.dataset.tecla === '⌫' ? d.slice(0, -1) : d + tecla.dataset.tecla);
      return;
    }
    const r = e.target.closest('[data-recente]');
    if (r) campo.value = mascara(r.dataset.recente);
  });

  caixa.querySelector('[data-discador-form]').addEventListener('submit', async (e) => {
    e.preventDefault();
    const numero = campo.value.replace(/\D/g, '');
    const ligar = caixa.querySelector('[data-discador-ligar]');
    ligar.disabled = true;
    msg.className = 'discador-msg pequeno';
    msg.textContent = 'Ligando…';
    try {
      const corpo = new FormData();
      corpo.append('_csrf', CSRF);
      corpo.append('numero', numero);
      const r = await fetch(BASE + '/telefonia/ligar', { method: 'POST', body: corpo, headers: { Accept: 'application/json', 'X-CSRF-Token': CSRF } });
      const d = await r.json().catch(() => ({ ok: false, mensagem: 'Resposta inválida.' }));
      msg.classList.add(d.ok ? 'ok' : 'erro');
      msg.textContent = d.mensagem || '';
      if (d.ok) {
        guardar(numero);
        if (d.cliente) {
          const a = document.createElement('a');
          a.href = `${BASE}/carteira/clientes/${d.cliente.id}`;
          a.textContent = `É do cliente ${d.cliente.nome}: abrir a ficha →`;
          a.dataset.discadorCliente = d.cliente.id;
          msg.append(document.createElement('br'), a);
        }
        if (d.tel) window.location.href = 'tel:' + d.tel; // sem discagem pela API: abre o softphone/telefone
      }
    } catch {
      msg.classList.add('erro');
      msg.textContent = 'Sem conexão. Tente de novo.';
    } finally {
      ligar.disabled = false;
    }
  });
}
