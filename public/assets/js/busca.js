// =====================================================================
// Busca rápida do topo: digite nome, CNPJ, telefone ou código do produto (atalho: tecla "/").
// Setas para escolher, Enter abre, Esc fecha.
// =====================================================================
const caixa = document.querySelector('[data-busca-rapida]');
if (caixa) {
  const BASE = document.querySelector('meta[name="base"]')?.content || '';
  const campo = caixa.querySelector('[data-busca-campo]');
  const lista = caixa.querySelector('[data-busca-resultados]');
  const esc = (t) => String(t ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  let espera, ultima = '', marcado = -1;

  const fechar = () => { lista.hidden = true; marcado = -1; };
  const itens = () => [...lista.querySelectorAll('a')];
  const marcar = (i) => {
    const todos = itens();
    if (!todos.length) return;
    marcado = (i + todos.length) % todos.length;
    todos.forEach((a, k) => a.classList.toggle('marcado', k === marcado));
    todos[marcado].scrollIntoView({ block: 'nearest' });
  };

  const buscar = async () => {
    const q = campo.value.trim();
    if (q.length < 2) { fechar(); return; }
    if (q === ultima && !lista.hidden) return;
    ultima = q;
    const r = await fetch(`${BASE}/busca?q=${encodeURIComponent(q)}`, { headers: { Accept: 'application/json' } }).catch(() => null);
    if (!r || !r.ok || campo.value.trim() !== q) return;
    const d = await r.json();
    const partes = [];
    if (d.clientes.length) {
      partes.push('<small class="busca-grupo">Clientes</small>');
      d.clientes.forEach((c) => partes.push(`<a href="${esc(c.url)}" role="option"><strong>${esc(c.nome)}</strong><span>${esc(c.local)}${c.etapa ? ' · ' + esc(c.etapa) : ''}${c.tipo === 'cliente' ? ' · cliente' : ' · lead'}</span></a>`));
    }
    if (d.produtos.length) {
      partes.push('<small class="busca-grupo">Produtos</small>');
      d.produtos.forEach((p) => partes.push(`<a href="${esc(p.url)}" role="option"><strong>${p.codigo ? `<span class="cod-produto">${esc(p.codigo)}</span> ` : ''}${esc(p.nome)}</strong><span>${esc(p.embalagem || '')}</span></a>`));
    }
    lista.innerHTML = partes.length ? partes.join('') : '<p class="busca-vazio">Nada encontrado.</p>';
    lista.hidden = false;
    marcado = -1;
  };

  campo.addEventListener('input', () => { clearTimeout(espera); espera = setTimeout(buscar, 220); });
  campo.addEventListener('focus', () => { if (campo.value.trim().length >= 2) buscar(); });
  campo.addEventListener('keydown', (e) => {
    if (e.key === 'ArrowDown') { e.preventDefault(); marcar(marcado + 1); }
    else if (e.key === 'ArrowUp') { e.preventDefault(); marcar(marcado - 1); }
    else if (e.key === 'Enter') { e.preventDefault(); const alvo = itens()[marcado >= 0 ? marcado : 0]; if (alvo) window.location.href = alvo.href; }
    else if (e.key === 'Escape') { fechar(); campo.blur(); }
  });
  document.addEventListener('click', (e) => { if (!caixa.contains(e.target)) fechar(); });
  // Atalho "/" em qualquer tela (menos quando já está digitando em outro campo)
  document.addEventListener('keydown', (e) => {
    if (e.key === '/' && !['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement?.tagName) && !document.activeElement?.isContentEditable) {
      e.preventDefault(); campo.focus();
    }
  });
}
