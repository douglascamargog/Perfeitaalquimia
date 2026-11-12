// =====================================================================
// Fila de envio: pedidos e visitas anotados no celular vão para o servidor
// quando há internet. Cada item tem um uuid: reenviar nunca duplica.
// =====================================================================
import { listarFila, salvarItem, lerPacote, guardarPacote } from './banco.js';

const BASE = document.querySelector('meta[name="base"]')?.content || '';
const FINAIS = ['faturado', 'recusado', 'cancelado'];

async function chamar(caminho, opcoes = {}) {
  const pacote = await lerPacote();
  const r = await fetch(BASE + caminho, {
    credentials: 'same-origin', ...opcoes,
    headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-Token': pacote?.csrf || '', ...(opcoes.headers || {}) },
  });
  if (r.status === 401 || r.status === 419) return { login: true };
  if (!r.ok) throw new Error('O servidor respondeu ' + r.status);
  return r.json();
}

/** Envia o que está pendente. Devolve { enviados, erros, login } */
export async function enviar() {
  const pendentes = (await listarFila()).filter((i) => i.estado === 'pendente');
  if (!pendentes.length || !navigator.onLine) return { enviados: 0, erros: 0, login: false };
  let resposta = await chamar('/app/api/sincronizar', { method: 'POST', body: JSON.stringify({ itens: pendentes }) });
  if (resposta.login) {
    // O token pode ter mudado (novo login): busca o pacote atualizado e tenta uma vez de novo
    const novo = await baixarPacote().catch(() => null);
    if (!novo || novo.login) return { enviados: 0, erros: 0, login: true };
    await guardarPacote(novo);
    resposta = await chamar('/app/api/sincronizar', { method: 'POST', body: JSON.stringify({ itens: pendentes }) });
    if (resposta.login) return { enviados: 0, erros: 0, login: true };
  }
  let enviados = 0, erros = 0;
  for (const item of pendentes) {
    const r = resposta.resultados?.[item.uuid];
    if (!r) continue;
    if (r.ok) {
      Object.assign(item, { estado: 'enviado', pedido_id: r.pedido_id || null, situacao: r.situacao || null, mensagem: r.mensagem, enviado_em: new Date().toISOString() });
      enviados++;
    } else {
      Object.assign(item, { estado: 'erro', mensagem: r.mensagem });
      erros++;
    }
    await salvarItem(item);
  }
  return { enviados, erros, login: false };
}

/** Busca a situação dos pedidos já enviados (aprovado, faturado, recusado) */
export async function atualizarSituacao() {
  const enviados = (await listarFila()).filter((i) => i.tipo === 'pedido' && i.estado === 'enviado' && !FINAIS.includes(i.situacao));
  if (!enviados.length || !navigator.onLine) return 0;
  const resposta = await chamar('/app/api/situacao?uuids=' + enviados.map((i) => i.uuid).join(','), { method: 'GET' });
  if (resposta.login) return 0;
  let mudou = 0;
  for (const item of enviados) {
    const s = resposta.pedidos?.[item.uuid];
    if (s && (s.situacao !== item.situacao || s.numero_nf !== item.numero_nf)) {
      Object.assign(item, { situacao: s.situacao, numero_nf: s.numero_nf, motivo: s.motivo, pedido_id: s.pedido_id });
      await salvarItem(item);
      mudou++;
    }
  }
  return mudou;
}

/** Pede ao navegador para enviar sozinho quando a internet voltar (Android/Chrome) */
export async function agendarEnvio() {
  try {
    const reg = await navigator.serviceWorker?.ready;
    await reg?.sync?.register('enviar-fila');
  } catch { /* navegador sem Background Sync: envia ao abrir o app e no evento "online" */ }
}

export async function baixarPacote() {
  const r = await fetch(BASE + '/app/api/dados', { credentials: 'same-origin', headers: { Accept: 'application/json' } });
  if (r.status === 401) return { login: true };
  if (!r.ok) throw new Error('O servidor respondeu ' + r.status);
  return r.json();
}
