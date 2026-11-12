// =====================================================================
// Carteira: gaveta da ficha do cliente, formulários sem recarregar e filtros.
// Usado nas telas da Carteira, Agenda, Reativação e Cobrança.
// =====================================================================

const BASE = document.querySelector('meta[name="base"]')?.content || '';
const CSRF = document.querySelector('meta[name="csrf"]')?.content || '';

const gaveta = document.querySelector('[data-gaveta]');
const fundo = document.querySelector('[data-gaveta-fundo]');
const conteudo = document.querySelector('[data-gaveta-conteudo]');
const avisoFlutuante = document.querySelector('[data-aviso-flutuante]');
let clienteAberto = null;
let alterouAlgo = false; // se algo foi salvo, a página recarrega ao fechar a gaveta (para atualizar a lista)

/** Mensagem rápida no canto da tela */
function avisar(texto, tipo = 'sucesso') {
  if (!avisoFlutuante) return;
  avisoFlutuante.textContent = texto;
  avisoFlutuante.className = 'aviso-flutuante visivel ' + tipo;
  clearTimeout(avisar.timer);
  avisar.timer = setTimeout(() => avisoFlutuante.classList.remove('visivel'), 2800);
}

/** Abre a ficha do cliente na gaveta lateral */
async function abrirCliente(id) {
  if (!gaveta) return;
  clienteAberto = id;
  gaveta.hidden = false;
  fundo.hidden = false;
  document.body.classList.add('gaveta-aberta');
  conteudo.innerHTML = '<p class="gaveta-carregando">Carregando ficha…</p>';
  try {
    // A gaveta pode carregar outra coisa (ex.: caso de cobrança): data-url="/cobranca/{id}/caso"
    const modelo = gaveta.dataset.url || '/carteira/clientes/{id}/ficha';
    const resposta = await fetch(BASE + modelo.replace('{id}', encodeURIComponent(id)), { headers: { Accept: 'text/html' } });
    if (resposta.redirected && resposta.url.includes('/entrar')) { window.location.href = resposta.url; return; }
    if (!resposta.ok) {
      const erro = await resposta.json().catch(() => ({}));
      conteudo.innerHTML = `<div class="ficha-corpo"><p class="aviso erro">${escapar(erro.mensagem || 'Não foi possível abrir a ficha.')}</p><button class="botao contorno" data-fechar-gaveta>Fechar</button></div>`;
      return;
    }
    conteudo.innerHTML = await resposta.text();
    conteudo.querySelector('h2')?.focus?.();
  } catch (e) {
    conteudo.innerHTML = '<div class="ficha-corpo"><p class="aviso erro">Sem conexão com o servidor. Tente de novo.</p></div>';
  }
}

function fecharGaveta() {
  if (!gaveta) return;
  gaveta.hidden = true;
  fundo.hidden = true;
  document.body.classList.remove('gaveta-aberta');
  clienteAberto = null;
  if (alterouAlgo) window.location.reload();
}

function escapar(texto) {
  return String(texto ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}

// Cliques: abrir ficha / fechar
document.addEventListener('click', (evento) => {
  const abrir = evento.target.closest('[data-abrir-cliente]');
  if (abrir) { evento.preventDefault(); abrirCliente(abrir.dataset.abrirCliente); return; }
  if (evento.target.closest('[data-fechar-gaveta]') || evento.target === fundo) fecharGaveta();
});
document.addEventListener('keydown', (evento) => { if (evento.key === 'Escape' && clienteAberto) fecharGaveta(); });

// Formulários da ficha (data-ajax): enviam sem recarregar e atualizam a ficha
document.addEventListener('submit', async (evento) => {
  const form = evento.target.closest('form[data-ajax]');
  if (!form || evento.defaultPrevented) return; // defaultPrevented: a pessoa cancelou o "tem certeza?"
  evento.preventDefault();
  const botao = form.querySelector('button[type="submit"]');
  if (botao) botao.disabled = true;
  try {
    const resposta = await fetch(form.action, {
      method: 'POST',
      body: new FormData(form),
      headers: { 'X-CSRF-Token': CSRF, Accept: 'application/json' },
    });
    const dados = await resposta.json().catch(() => ({ ok: false, mensagem: 'Resposta inválida do servidor.' }));
    if (resposta.status === 409 && dados.conflito) {
      alert(dados.mensagem); // outra pessoa alterou: recarrega a ficha com os dados novos
    } else if (!dados.ok) {
      avisar(dados.mensagem || 'Não foi possível salvar.', 'erro');
      return;
    } else {
      avisar(dados.mensagem || 'Salvo.');
      alterouAlgo = true;
    }
    if (clienteAberto) await abrirCliente(clienteAberto);
  } catch (e) {
    avisar('Sem conexão: nada foi salvo. Tente de novo.', 'erro');
  } finally {
    if (botao) botao.disabled = false;
  }
});

// Filtros: ao mudar um campo, já aplica (a busca por texto aplica ao apertar Enter)
document.querySelectorAll('form[data-filtros]').forEach((form) => {
  form.addEventListener('change', (evento) => {
    if (evento.target.name === 'q') return;
    if (evento.target.name === 'uf') form.querySelector('[name="cidade"]').value = ''; // cidade depende da UF
    form.requestSubmit();
  });
});

// Disponível para os outros scripts (mapa.js, funil.js) sem carregar este arquivo duas vezes
/** Recarrega o que está aberto na gaveta (depois de salvar algo por outro script) */
function recarregar() {
  alterouAlgo = true;
  if (clienteAberto) abrirCliente(clienteAberto);
}

window.Carteira = { abrirCliente, avisar, recarregar };

// Abre a ficha automaticamente quando a URL tem ?abrir=ID (ex.: depois de cadastrar)
const abrirNaCarga = new URLSearchParams(window.location.search).get('abrir');
if (abrirNaCarga) abrirCliente(abrirNaCarga);
