// =====================================================================
// Notificações: sino do topo, contadores do menu e título da aba, atualizados a cada 60 s
// (só com a aba aberta e visível). Quando chega algo novo, mostra um aviso flutuante.
// Opcional: "Avisar no computador" (notificação do sistema enquanto o One está aberto; precisa de HTTPS).
// =====================================================================
const sino = document.querySelector('[data-sino]');
if (sino) {
  const BASE = document.querySelector('meta[name="base"]')?.content || '';
  const CSRF = document.querySelector('meta[name="csrf"]')?.content || '';
  const botao = sino.querySelector('[data-sino-botao]');
  const painel = sino.querySelector('[data-sino-painel]');
  const lista = sino.querySelector('[data-sino-lista]');
  const contador = sino.querySelector('[data-sino-contador]');
  const tituloBase = document.title.replace(/^\(\d+\)\s*/, '');
  const esc = (t) => String(t ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  let ultima = null;

  const pedir = async (extra = '') => {
    const onde = encodeURIComponent(location.pathname.slice(BASE.length) || '/');
    const r = await fetch(`${BASE}/notificacoes/resumo?depois=${ultima ?? 0}&onde=${onde}${extra}`, { headers: { Accept: 'application/json' } }).catch(() => null);
    // Sessão expirada ou senha provisória: o servidor manda uma página, não os dados → ignora até a próxima vez
    if (!r || !r.ok || !(r.headers.get('content-type') || '').includes('json')) return null;
    return r.json().catch(() => null);
  };

  const aplicar = (d) => {
    // Comunicado novo aparece na faixa do topo sem recarregar
    if (window.__oneFaixaComunicados) window.__oneFaixaComunicados(d.comunicados);
    // Quem está online (fotinhas do topo)
    const online = document.querySelector('[data-online]');
    if (online && typeof d.online_html === 'string') {
      online.hidden = !d.online_qtd;
      online.innerHTML = d.online_html;
    }
    contador.hidden = !d.nao_lidas;
    contador.textContent = d.nao_lidas > 99 ? '99+' : d.nao_lidas;
    let pendente = 0;
    document.querySelectorAll('[data-pendencia]').forEach((b) => {
      const n = d.pendencias[b.dataset.pendencia] || 0;
      b.hidden = !n; b.textContent = n; pendente += n;
    });
    const ponto = document.querySelector('[data-menu-ponto]');
    if (ponto) ponto.hidden = !pendente;
    const total = pendente + d.nao_lidas;
    document.title = (total ? `(${total}) ` : '') + tituloBase;
  };

  const desenharLista = (itens) => {
    lista.innerHTML = itens.length ? itens.map((n) => `<a href="${esc(n.link)}" class="${n.lida ? '' : 'nao-lida'}">
        <strong>${esc(n.titulo)}</strong>${n.texto ? `<span>${esc(n.texto)}</span>` : ''}<small>${esc(n.quando)}</small></a>`).join('')
      : '<p class="suave pequeno">Nenhuma notificação por enquanto.</p>';
  };

  // Aviso flutuante quando chega algo novo (e, se a pessoa ativou, notificação do computador)
  const avisarNovas = (novas) => {
    novas.slice(0, 3).forEach((n) => {
      let caixa = document.querySelector('.avisos-flutuantes');
      if (!caixa) { caixa = document.createElement('div'); caixa.className = 'avisos-flutuantes'; caixa.setAttribute('role', 'status'); document.body.append(caixa); }
      const a = document.createElement('a');
      a.className = 'aviso-flut atencao notif-nova'; a.href = n.link;
      a.innerHTML = `<strong>${esc(n.titulo)}</strong>${n.texto ? `<br><small>${esc(n.texto)}</small>` : ''}`;
      caixa.append(a);
      setTimeout(() => a.classList.add('saindo'), 8000);
      setTimeout(() => a.remove(), 8600);
      if ('Notification' in window && Notification.permission === 'granted' && document.hidden) {
        try { new Notification(n.titulo, { body: n.texto || '', tag: 'one-' + n.id }); } catch (e) { /* sem suporte */ }
      }
    });
  };

  const atualizar = async () => {
    if (document.hidden) return;
    const d = await pedir();
    if (!d) return;
    if (ultima !== null && d.novas.length) avisarNovas(d.novas.reverse());
    ultima = d.ultima;
    aplicar(d);
  };

  // Abrir/fechar o painel
  botao.addEventListener('click', async () => {
    const abrir = painel.hidden;
    painel.hidden = !abrir;
    botao.setAttribute('aria-expanded', String(abrir));
    if (abrir) {
      const d = await pedir('&lista=1');
      if (d) { desenharLista(d.lista); aplicar(d); }
    }
  });
  document.addEventListener('click', (e) => { if (!sino.contains(e.target)) { painel.hidden = true; botao.setAttribute('aria-expanded', 'false'); } });
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape') painel.hidden = true; });

  // "Marcar todas como lidas" sem sair da página
  sino.querySelector('[data-sino-marcar]')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    await fetch(e.target.action, { method: 'POST', headers: { 'X-CSRF-Token': CSRF, Accept: 'application/json' } }).catch(() => null);
    const d = await pedir('&lista=1&forcar=1');
    if (d) { desenharLista(d.lista); aplicar(d); }
  });

  // Avisar no computador (opcional, a pessoa escolhe)
  const ativar = sino.querySelector('[data-ativar-avisos]');
  if (ativar && 'Notification' in window && Notification.permission === 'default' && window.isSecureContext) {
    ativar.hidden = false;
    ativar.addEventListener('click', async () => { await Notification.requestPermission(); ativar.hidden = true; });
  }

  atualizar();
  setInterval(atualizar, 60000);
  document.addEventListener('visibilitychange', () => { if (!document.hidden) atualizar(); });
  window.__oneAtualizarNotificacoes = atualizar; // usado pelos testes automáticos
}
