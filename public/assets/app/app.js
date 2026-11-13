// =====================================================================
// One Vendas — app do vendedor externo (funciona sem internet).
// Telas: Clientes · Novo pedido · Pedidos · Sincronizar.
// As regras do pedido aqui são uma prévia (mínimo, boleto, condição): o servidor confere tudo de novo.
// =====================================================================
import { lerPacote, guardarPacote, listarFila, salvarItem, removerItem, apagarTudo } from './banco.js';
import { enviar, atualizarSituacao, agendarEnvio, baixarPacote } from './fila.js';

const BASE = document.querySelector('meta[name="base"]')?.content || '';
const tela = document.querySelector('[data-tela]');
const avisoTopo = document.querySelector('[data-aviso]');
let pacote = null;
let aba = 'clientes';
let rascunho = null; // pedido sendo montado
let buscaClientes = '';
let buscaProdutos = '';

// ---------------- utilidades ----------------
const esc = (t) => String(t ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
const real = (v) => Number(v || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
const dataBr = (d) => (d ? new Date(d.length === 10 ? d + 'T12:00' : d).toLocaleDateString('pt-BR') : '—');
const novoUuid = () => (crypto.randomUUID ? crypto.randomUUID() : 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
  const r = (crypto.getRandomValues(new Uint8Array(1))[0] & 15); return (c === 'x' ? r : (r & 3) | 8).toString(16); }));
const nomeCliente = (c) => c.nome_fantasia || c.razao_social;
const sem = (t) => t.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();

function toast(texto, tipo = 'sucesso') {
  const el = document.querySelector('[data-aviso-flutuante]');
  el.textContent = texto;
  el.className = 'aviso-flutuante visivel ' + tipo;
  clearTimeout(toast.t);
  toast.t = setTimeout(() => el.classList.remove('visivel'), 2800);
}

function aviso(html) {
  avisoTopo.innerHTML = html || '';
  avisoTopo.hidden = !html;
}

function cliente(id) { return pacote?.clientes.find((c) => c.id === Number(id)); }
function precoDe(c, produtoId) { return pacote.precos?.[c.tabela_preco_id]?.[produtoId] ?? null; }

// ---------------- topo: rede e pendentes ----------------
async function atualizarTopo() {
  const rede = document.querySelector('[data-rede]');
  rede.textContent = navigator.onLine ? 'online' : 'offline';
  rede.className = 'ap-rede ' + (navigator.onLine ? 'on' : 'off');
  const fila = await listarFila();
  const pend = fila.filter((i) => i.estado === 'pendente').length;
  const b = document.querySelector('[data-pendentes]');
  b.textContent = pend; b.hidden = !pend;
  document.querySelector('[data-vendedor]').textContent = pacote ? pacote.vendedor.nome : '';
  if (pacote) {
    const dias = (Date.now() - new Date(pacote.gerado_em).getTime()) / 864e5;
    if (dias > (pacote.dias_cache || 7)) aviso(`Os preços guardados no celular têm ${Math.floor(dias)} dias. <button type="button" data-ir="sincronizar">Atualizar</button>`);
  }
}

// ---------------- telas ----------------
function telaClientes() {
  const termo = sem(buscaClientes);
  const lista = pacote.clientes.filter((c) => !termo || sem(`${nomeCliente(c)} ${c.razao_social} ${c.cidade || ''} ${c.cnpj || ''}`).includes(termo));
  tela.innerHTML = `
    <div class="ap-busca"><input type="search" placeholder="Buscar cliente, cidade ou CNPJ…" value="${esc(buscaClientes)}" data-busca-clientes aria-label="Buscar cliente"></div>
    <p class="ap-contagem">${lista.length} cliente(s) na sua carteira</p>
    <ul class="ap-clientes">
      ${lista.slice(0, 300).map((c) => {
        const tel = c.whatsapp || (String(c.telefone || '').length === 11 ? c.telefone : '');
        const end = [c.endereco, c.bairro, c.cidade, c.uf].filter(Boolean).join(', ');
        return `<li class="ap-cliente">
          <div class="ap-cliente-topo"><strong>${esc(nomeCliente(c))}</strong>
            <span class="ap-selos">${c.recompra_atrasada ? '<span class="selo laranja">recompra</span>' : ''}${c.boleto_bloqueado ? '<span class="selo vermelho">sem boleto</span>' : ''}</span></div>
          <small>${esc(c.cidade || '')}/${esc(c.uf || '')}${c.ultima_compra_em ? ' · última compra ' + dataBr(c.ultima_compra_em) : ' · ainda não comprou'}</small>
          <div class="ap-acoes">
            <button type="button" class="botao lima pequeno" data-novo-pedido="${c.id}">Novo pedido</button>
            <button type="button" class="botao contorno pequeno" data-visita="${c.id}">Registrar visita</button>
            ${c.telefone ? `<a class="botao contorno pequeno" href="tel:+55${esc(c.telefone)}">Ligar</a>` : ''}
            ${tel ? `<a class="botao contorno pequeno" href="https://wa.me/55${esc(tel)}" target="_blank" rel="noopener">WhatsApp</a>` : ''}
            ${end ? `<a class="botao contorno pequeno" href="https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(end)}" target="_blank" rel="noopener">Mapa</a>` : ''}
          </div>
        </li>`;
      }).join('')}
    </ul>
    ${lista.length > 300 ? '<p class="ap-contagem">Mostrando 300. Use a busca.</p>' : ''}`;
}

function totais() {
  const c = cliente(rascunho.cliente_id);
  let subtotal = 0, qtd = 0;
  for (const [id, q] of Object.entries(rascunho.itens)) {
    const preco = precoDe(c, id);
    if (preco !== null && q > 0) { subtotal += preco * q; qtd += q; }
  }
  const minimo = pacote.minimos?.[c.uf] || 0;
  return { c, subtotal, qtd, minimo, falta: Math.max(0, minimo - subtotal) };
}

function telaPedido() {
  const { c, subtotal, qtd, minimo, falta } = totais();
  const termo = sem(buscaProdutos);
  const produtos = pacote.produtos.filter((p) => precoDe(c, p.id) !== null && (!termo || sem((p.codigo || '') + ' ' + p.nome + ' ' + (p.categoria || '')).includes(termo)));
  const tabela = pacote.tabelas.find((t) => Number(t.id) === c.tabela_preco_id)?.nome || '';
  tela.innerHTML = `
    <div class="ap-pedido-topo">
      <button type="button" class="ap-voltar" data-ir="clientes">← Clientes</button>
      <h1>${esc(nomeCliente(c))}</h1>
      <small>${esc(c.cidade || '')}/${esc(c.uf || '')} · tabela ${esc(tabela)}${rascunho.corrigindo ? ' · <b>corrigindo pedido com erro</b>' : ''}</small>
    </div>
    <div class="ap-busca"><input type="search" placeholder="Buscar produto ou código…" value="${esc(buscaProdutos)}" data-busca-produtos aria-label="Buscar produto"></div>
    <ul class="ap-produtos">
      ${produtos.map((p) => { const q = rascunho.itens[p.id] || 0; return `
        <li class="${q ? 'escolhido' : ''}">
          ${p.imagem ? `<img src="${esc(p.imagem)}" alt="" loading="lazy" width="52" height="52">` : '<span class="ap-sem-foto"></span>'}
          <div><strong>${p.codigo ? `<span class="cod-produto">${esc(p.codigo)}</span> ` : ''}${esc(p.nome)}</strong><small>${esc(p.embalagem || '')}</small><b>${real(precoDe(c, p.id))}</b></div>
          <div class="ap-qtd" data-produto="${p.id}"><button type="button" data-menos aria-label="Menos">−</button><input type="number" inputmode="numeric" min="0" value="${q || ''}" placeholder="0" aria-label="Quantidade de ${esc(p.nome)}"><button type="button" data-mais aria-label="Mais">+</button></div>
        </li>`; }).join('')}
    </ul>
    <section class="ap-resumo" data-resumo>${resumoHtml({ c, subtotal, qtd, minimo, falta })}</section>`;
}

function resumoHtml({ c, subtotal, qtd, minimo, falta }) {
  const opcoes = pacote.formas.map((f) => {
    const bloqueada = f.bloqueia_com_atraso && c.boleto_bloqueado;
    return `<fieldset class="ap-forma ${bloqueada ? 'bloqueada' : ''}"><legend>${esc(f.nome)}</legend>
      ${bloqueada ? '<small>Indisponível: cliente com título em aberto.</small>' : f.condicoes.map((cd) => {
        const off = subtotal < Number(cd.valor_minimo);
        const v = `${f.id}:${cd.id}`;
        return `<label class="opcao ${off ? 'desligada' : ''}"><input type="radio" name="pagamento" value="${v}" ${rascunho.pagamento === v && !off ? 'checked' : ''} ${off ? 'disabled' : ''}>
          ${esc(cd.nome)}${Number(cd.desconto_pct) > 0 ? ` · ${String(cd.desconto_pct).replace('.', ',')}% desc.` : ''}${off ? ` <small>(a partir de ${real(cd.valor_minimo)})</small>` : ''}</label>`;
      }).join('')}</fieldset>`;
  }).join('');
  return `
    <div class="ap-total"><span>${qtd} item(ns)</span><strong>${real(subtotal)}</strong></div>
    <div class="ap-minimo ${falta > 0 ? 'falta' : 'ok'}">${falta > 0 ? `Faltam ${real(falta)} para o mínimo (${real(minimo)})` : 'Pedido mínimo atingido ✓'}</div>
    ${opcoes}
    <textarea rows="2" placeholder="Observação (opcional)" data-obs aria-label="Observação">${esc(rascunho.observacoes || '')}</textarea>
    <button type="button" class="botao largo" data-salvar ${falta > 0 || !qtd ? 'disabled' : ''}>${!qtd ? 'Escolha os produtos' : falta > 0 ? 'Complete o pedido mínimo' : 'Salvar pedido'}</button>
    <small class="ap-nota">Funciona sem internet: o pedido fica no celular e é enviado sozinho quando a rede voltar.</small>`;
}

const SITUACAO = {
  pendente: ['Aguardando envio', 'laranja'], erro: ['Com erro', 'vermelho'],
  aguardando_aprovacao: ['Enviado · aguardando aprovação', 'cinza'], aprovado: ['Aprovado', 'lima'], faturado: ['Faturado', 'verde'],
  recusado: ['Recusado', 'vermelho'], cancelado: ['Cancelado', 'cinza'],
};

async function telaPedidos() {
  const fila = (await listarFila()).sort((a, b) => (b.criado_em || '').localeCompare(a.criado_em || ''));
  const pend = fila.filter((i) => i.estado === 'pendente').length;
  tela.innerHTML = `
    <div class="ap-cabeca"><h1>Pedidos e visitas</h1>
      <button type="button" class="botao pequeno" data-enviar ${pend && navigator.onLine ? '' : 'disabled'}>Enviar agora${pend ? ` (${pend})` : ''}</button></div>
    ${!fila.length ? '<p class="ap-vazio">Nada anotado ainda. Escolha um cliente e faça um pedido.</p>' : ''}
    <ul class="ap-fila">
      ${fila.map((i) => {
        const chave = i.estado === 'enviado' ? (i.situacao || 'aguardando_aprovacao') : i.estado;
        const [rotulo, cor] = SITUACAO[chave] || [chave, 'cinza'];
        return `<li class="ap-item" data-item="${esc(i.uuid)}">
          <div class="ap-item-topo"><strong>${i.tipo === 'visita' ? 'Visita' : 'Pedido'}${i.pedido_id ? ' #' + i.pedido_id : ''} · ${esc(i.cliente_nome)}</strong><span class="selo ${cor}">${esc(i.tipo === 'visita' && i.estado === 'enviado' ? 'Registrada' : rotulo)}</span></div>
          <small>${new Date(i.criado_em).toLocaleString('pt-BR')}${i.tipo === 'pedido' ? ' · ' + real(i.total) + ' · ' + i.qtd + ' item(ns)' : ''}${i.numero_nf ? ' · NF ' + esc(i.numero_nf) : ''}</small>
          ${i.estado === 'erro' ? `<p class="ap-erro">${esc(i.mensagem)}</p><div class="ap-acoes"><button type="button" class="botao contorno pequeno" data-corrigir="${esc(i.uuid)}">Corrigir e reenviar</button><button type="button" class="botao contorno pequeno" data-descartar="${esc(i.uuid)}">Descartar</button></div>` : ''}
          ${i.situacao === 'recusado' && i.motivo ? `<p class="ap-erro">Motivo: ${esc(i.motivo)}</p>` : ''}
        </li>`;
      }).join('')}
    </ul>`;
}

async function telaSincronizar() {
  const fila = await listarFila();
  tela.innerHTML = `
    <div class="ap-cabeca"><h1>Sincronizar</h1></div>
    <section class="ap-cartao">
      <p>Dados no celular: <strong>${pacote ? new Date(pacote.gerado_em).toLocaleString('pt-BR') : 'nenhum'}</strong></p>
      <p>${pacote ? `${pacote.clientes.length} clientes · ${pacote.produtos.length} produtos` : ''}</p>
      <p>Fila: ${fila.filter((i) => i.estado === 'pendente').length} aguardando envio · ${fila.filter((i) => i.estado === 'erro').length} com erro</p>
      <button type="button" class="botao largo" data-baixar ${navigator.onLine ? '' : 'disabled'}>Baixar dados para usar offline</button>
      <small class="ap-nota">Baixa a carteira, os preços e as fotos dos produtos. Faça com Wi-Fi antes de sair para a rua.</small>
    </section>
    <section class="ap-cartao">
      <p><strong>Instalar no celular</strong></p>
      <button type="button" class="botao contorno largo" data-instalar ${window.__instalar ? '' : 'hidden'}>Instalar o app</button>
      <small class="ap-nota">No iPhone: botão Compartilhar › "Adicionar à Tela de Início". No Android: menu ⋮ › "Instalar app".</small>
    </section>
    <section class="ap-cartao">
      <a class="botao contorno largo" href="${BASE}/">Abrir o sistema completo</a>
      <button type="button" class="botao contorno largo" data-sair>Sair e apagar dados deste celular</button>
    </section>`;
}

async function mostrar(nova) {
  if (nova) aba = nova;
  document.querySelectorAll('[data-aba]').forEach((b) => b.classList.toggle('ativa', b.dataset.aba === aba || (aba === 'pedido' && b.dataset.aba === 'clientes')));
  if (!pacote && aba !== 'sincronizar') {
    tela.innerHTML = `<div class="ap-vazio"><p>${navigator.onLine ? 'Baixando seus dados…' : 'Abra o app uma vez com internet para baixar a sua carteira.'}</p></div>`;
    return;
  }
  if (aba === 'clientes') telaClientes();
  else if (aba === 'pedido') telaPedido();
  else if (aba === 'pedidos') await telaPedidos();
  else await telaSincronizar();
  atualizarTopo();
}

// ---------------- dados e sincronização ----------------
async function baixar(avisar = false) {
  try {
    const r = await baixarPacote();
    if (r.login) { pedirLogin(); return false; }
    pacote = r;
    await guardarPacote(r);
    aviso('');
    if (avisar) toast('Dados atualizados.');
    baixarFotos();
    return true;
  } catch {
    if (avisar) toast('Sem conexão com o servidor.', 'erro');
    return false;
  }
}

/** Busca as fotos dos produtos (o service worker guarda para usar offline), poucas por vez */
async function baixarFotos() {
  const fotos = (pacote?.produtos || []).map((p) => p.imagem).filter(Boolean);
  for (let i = 0; i < fotos.length; i += 6) {
    await Promise.all(fotos.slice(i, i + 6).map((u) => fetch(u, { credentials: 'same-origin' }).catch(() => {})));
  }
}

function pedirLogin() {
  aviso(`Sua sessão encerrou. Os pedidos continuam guardados no celular. <a href="${BASE}/app">Entrar de novo</a>`);
}

async function sincronizar(avisar = false) {
  if (!navigator.onLine) return;
  try {
    const r = await enviar();
    if (r.login) { pedirLogin(); return; }
    if (r.enviados) toast(`${r.enviados} item(ns) enviado(s).`);
    if (r.erros) toast(`${r.erros} item(ns) com erro. Veja em Pedidos.`, 'erro');
    await atualizarSituacao();
    if (avisar && !r.enviados && !r.erros) toast('Tudo em dia.');
  } catch {
    if (avisar) toast('Sem conexão com o servidor. Tente de novo.', 'erro');
  }
  if (aba === 'pedidos') await telaPedidos();
  atualizarTopo();
}

async function guardarNaFila(item) {
  await salvarItem(item);
  await agendarEnvio();
  if (navigator.onLine) await sincronizar();
  atualizarTopo();
}

// ---------------- eventos ----------------
document.addEventListener('click', async (e) => {
  const alvo = e.target.closest('button, a');
  if (!alvo) return;
  if (alvo.dataset.aba) { rascunho = null; mostrar(alvo.dataset.aba); return; }
  if (alvo.dataset.ir) { mostrar(alvo.dataset.ir); return; }
  if (alvo.dataset.novoPedido) {
    rascunho = { uuid: novoUuid(), cliente_id: Number(alvo.dataset.novoPedido), itens: {}, pagamento: '', observacoes: '' };
    buscaProdutos = ''; mostrar('pedido'); window.scrollTo(0, 0); return;
  }
  if (alvo.dataset.visita) {
    const c = cliente(alvo.dataset.visita);
    const resumo = window.prompt(`Visita em ${nomeCliente(c)}: o que foi conversado?`, '');
    if (resumo === null) return;
    await guardarNaFila({ uuid: novoUuid(), tipo: 'visita', estado: 'pendente', cliente_id: c.id, cliente_nome: nomeCliente(c), resumo, data: new Date().toISOString().slice(0, 10), criado_em: new Date().toISOString() });
    toast(navigator.onLine ? 'Visita registrada.' : 'Visita guardada. Será enviada quando houver internet.');
    return;
  }
  if (alvo.matches('[data-mais], [data-menos]')) {
    const caixa = alvo.closest('[data-produto]');
    const input = caixa.querySelector('input');
    input.value = Math.max(0, (Number(input.value) || 0) + (alvo.matches('[data-mais]') ? 1 : -1)) || '';
    input.dispatchEvent(new Event('input', { bubbles: true }));
    return;
  }
  if (alvo.matches('[data-salvar]')) {
    const t = totais();
    if (!rascunho.pagamento) { toast('Escolha a forma de pagamento.', 'erro'); return; }
    const itens = Object.fromEntries(Object.entries(rascunho.itens).filter(([, q]) => q > 0));
    await guardarNaFila({ uuid: rascunho.uuid, tipo: 'pedido', estado: 'pendente', cliente_id: t.c.id, cliente_nome: nomeCliente(t.c), itens, pagamento: rascunho.pagamento,
      observacoes: rascunho.observacoes || '', total: t.subtotal, qtd: t.qtd, criado_em: rascunho.criado_em || new Date().toISOString(), mensagem: null });
    toast(navigator.onLine ? 'Pedido salvo e enviado.' : 'Pedido salvo no celular. Será enviado quando houver internet.');
    rascunho = null; mostrar('pedidos'); return;
  }
  if (alvo.matches('[data-enviar]')) { await sincronizar(true); return; }
  if (alvo.dataset.corrigir) {
    const item = (await listarFila()).find((i) => i.uuid === alvo.dataset.corrigir);
    rascunho = { uuid: item.uuid, cliente_id: item.cliente_id, itens: { ...item.itens }, pagamento: item.pagamento, observacoes: item.observacoes, criado_em: item.criado_em, corrigindo: true };
    mostrar('pedido'); return;
  }
  if (alvo.dataset.descartar) {
    if (window.confirm('Descartar este pedido com erro?')) { await removerItem(alvo.dataset.descartar); await telaPedidos(); atualizarTopo(); }
    return;
  }
  if (alvo.matches('[data-baixar]')) { alvo.disabled = true; alvo.textContent = 'Baixando…'; await sincronizar(); await baixar(true); mostrar(); return; }
  if (alvo.matches('[data-instalar]')) { window.__instalar?.prompt(); return; }
  if (alvo.matches('[data-sair]')) {
    const pend = (await listarFila()).filter((i) => i.estado !== 'enviado').length;
    if (!window.confirm(pend ? `Há ${pend} item(ns) ainda não enviado(s) que serão perdidos. Sair mesmo assim?` : 'Sair e apagar os dados deste celular?')) return;
    await apagarTudo(); pacote = null;
    window.location.href = BASE + '/';
  }
});

document.addEventListener('input', (e) => {
  if (e.target.matches('[data-busca-clientes]')) { buscaClientes = e.target.value; const pos = e.target.selectionStart; telaClientes(); const n = tela.querySelector('[data-busca-clientes]'); n.focus(); n.setSelectionRange(pos, pos); return; }
  if (e.target.matches('[data-busca-produtos]')) { buscaProdutos = e.target.value; const pos = e.target.selectionStart; telaPedido(); const n = tela.querySelector('[data-busca-produtos]'); n.focus(); n.setSelectionRange(pos, pos); return; }
  const caixa = e.target.closest('[data-produto]');
  if (caixa && rascunho) {
    rascunho.itens[caixa.dataset.produto] = Math.max(0, Math.min(9999, Number(e.target.value) || 0));
    caixa.closest('li').classList.toggle('escolhido', rascunho.itens[caixa.dataset.produto] > 0);
    tela.querySelector('[data-resumo]').innerHTML = resumoHtml(totais());
    return;
  }
  if (e.target.matches('[data-obs]') && rascunho) rascunho.observacoes = e.target.value;
});
document.addEventListener('change', (e) => { if (e.target.name === 'pagamento' && rascunho) rascunho.pagamento = e.target.value; });

window.addEventListener('online', () => { atualizarTopo(); sincronizar(); });
window.addEventListener('offline', atualizarTopo);
window.addEventListener('beforeinstallprompt', (e) => { e.preventDefault(); window.__instalar = e; });
navigator.serviceWorker?.addEventListener('message', (e) => { if (e.data === 'enviar-fila') sincronizar(); });

// ---------------- início ----------------
(async function iniciar() {
  if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register(BASE + '/app/sw.js', { scope: BASE + '/app' }).catch(() => {});
  }
  pacote = await lerPacote();
  await mostrar('clientes');
  if (navigator.onLine) {
    await baixar();               // atualiza carteira, preços e o token de segurança da sessão atual
    await sincronizar();          // depois envia o que ficou pendente no celular
    await mostrar();
  }
})();
