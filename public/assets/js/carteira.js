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

/** Abre a ficha do cliente na gaveta lateral (enderecoExato: opcional, para abrir outro endereço, ex.: formulário novo) */
async function abrirCliente(id, enderecoExato = null) {
  if (!gaveta) return;
  clienteAberto = id;
  gaveta.hidden = false;
  fundo.hidden = false;
  document.body.classList.add('gaveta-aberta');
  conteudo.innerHTML = '<p class="gaveta-carregando">Carregando ficha…</p>';
  try {
    // A gaveta pode carregar outra coisa (ex.: caso de cobrança): data-url="/cobranca/{id}/caso"
    const modelo = gaveta.dataset.url || '/carteira/clientes/{id}/ficha';
    const resposta = await fetch(BASE + (enderecoExato || modelo.replace('{id}', encodeURIComponent(id))), { headers: { Accept: 'text/html' } });
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
      if (dados.abrir) clienteAberto = dados.abrir; // item recém-criado: a gaveta passa a mostrar ele
    }
    if (clienteAberto) await abrirCliente(clienteAberto);
  } catch (e) {
    avisar('Sem conexão: nada foi salvo. Tente de novo.', 'erro');
  } finally {
    if (botao) botao.disabled = false;
  }
});

// Mensagem pronta na ficha: escolher o modelo preenche o texto; "Abrir no WhatsApp" registra o uso e abre o wa.me
document.addEventListener('change', (evento) => {
  const modelo = evento.target.closest('[data-msg-modelo]');
  if (!modelo) return;
  const caixa = modelo.closest('[data-msg-pronta]');
  caixa.querySelector('[data-msg-texto]').value = modelo.selectedOptions[0]?.dataset.texto || '';
  caixa.querySelectorAll('[data-msg-enviar]').forEach((b) => (b.disabled = !modelo.value));
});
document.addEventListener('click', async (evento) => {
  const botao = evento.target.closest('[data-msg-enviar]');
  if (!botao) return;
  const caixa = botao.closest('[data-msg-pronta]');
  const modelo = caixa.querySelector('[data-msg-modelo]').value;
  const texto = caixa.querySelector('[data-msg-texto]').value.trim();
  if (!modelo || !texto) return;
  // Abre a janela já no clique (o navegador bloqueia janelas abertas depois de uma espera)
  const janela = botao.dataset.whats ? window.open(`https://wa.me/55${botao.dataset.whats}?text=${encodeURIComponent(texto)}`, '_blank', 'noopener') : null;
  if (!botao.dataset.whats) {
    try { await navigator.clipboard.writeText(texto); } catch (e) { /* sem permissão: a pessoa copia da caixa */ }
  }
  const corpo = new FormData();
  corpo.append('cliente_id', caixa.dataset.cliente);
  const resposta = await fetch(caixa.dataset.usarUrl.replace('{id}', modelo), { method: 'POST', body: corpo, headers: { 'X-CSRF-Token': CSRF, Accept: 'application/json' } }).catch(() => null);
  const dados = resposta ? await resposta.json().catch(() => ({})) : {};
  avisar(dados.ok ? (botao.dataset.whats ? 'Mensagem registrada na linha do tempo.' : 'Texto copiado e registrado na linha do tempo.') : (dados.mensagem || 'Não foi possível registrar.'), dados.ok ? undefined : 'erro');
  if (dados.ok) { alterouAlgo = true; if (clienteAberto) await abrirCliente(clienteAberto); }
  void janela;
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
