// =====================================================================
// Produção: gráfico do painel, editor da ficha técnica e prévia da ordem de produção.
// =====================================================================
import { cor, corAlfa } from './cores.js';
const real = (v) => v.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL', minimumFractionDigits: 2, maximumFractionDigits: 4 });
const numero = (texto) => {
  let t = String(texto || '').trim().replace(/\s/g, '');
  if (t.includes(',')) t = t.replace(/\./g, '').replace(',', '.');
  const n = Number(t);
  return Number.isFinite(n) ? n : 0;
};
const MESES = ['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'];

// ---------- Painel: unidades produzidas por mês ----------
const grafico = document.getElementById('grafico-producao');
if (grafico && window.Chart) {
  const meses = JSON.parse(grafico.dataset.meses || '[]');
  Chart.defaults.font.family = getComputedStyle(document.body).fontFamily;
  Chart.defaults.color = cor('--texto-suave');
  new Chart(grafico, {
    type: 'bar',
    data: {
      labels: meses.map((m) => MESES[Number(m.mes.slice(5)) - 1] + '/' + m.mes.slice(2, 4)),
      datasets: [{ label: 'Unidades', data: meses.map((m) => m.unidades), backgroundColor: meses.map((_, i, l) => (i === l.length - 1 ? cor('--primaria-escura') : cor('--secundaria'))), borderRadius: 8, maxBarThickness: 40 }],
    },
    options: { responsive: true, maintainAspectRatio: false, animation: false, plugins: { legend: { display: false } },
      scales: { y: { grid: { color: cor('--borda') }, border: { display: false }, beginAtZero: true }, x: { grid: { display: false }, border: { display: false } } } },
  });
}

// ---------- Ficha técnica: acrescentar/tirar linhas e recalcular o custo ----------
const ficha = document.querySelector('[data-ficha]');
if (ficha) {
  const corpo = ficha.querySelector('[data-linhas]');
  const recalcular = () => {
    let total = 0;
    corpo.querySelectorAll('[data-linha]').forEach((tr) => {
      const opcao = tr.querySelector('[data-mp]')?.selectedOptions[0];
      const custo = Number(opcao?.dataset.custo || 0);
      const qtd = numero(tr.querySelector('[data-qtd]')?.value);
      tr.querySelector('[data-unidade]').textContent = opcao?.dataset.unidade || '';
      tr.querySelector('[data-custo-mp]').textContent = opcao?.value ? real(custo) : '';
      tr.querySelector('[data-custo-linha]').textContent = opcao?.value ? real(custo * qtd) : '';
      total += custo * qtd;
    });
    ficha.querySelector('[data-custo-total]').textContent = real(total);
  };
  ficha.querySelector('[data-acrescentar]')?.addEventListener('click', () => {
    corpo.append(ficha.querySelector('[data-modelo]').content.cloneNode(true));
    ficha.querySelector('[data-sem-linhas]')?.remove();
    corpo.lastElementChild.querySelector('select')?.focus();
  });
  ficha.addEventListener('click', (e) => {
    if (e.target.closest('[data-remover]')) {
      e.target.closest('[data-linha]').remove();
      recalcular();
    }
  });
  ficha.addEventListener('input', recalcular);
  ficha.addEventListener('change', recalcular);
}

// ---------- Nova ordem: prévia do consumo (busca no servidor) ----------
const form = document.querySelector('[data-form-ordem]');
if (form) {
  const produto = form.querySelector('[data-produto]');
  const quantidade = form.querySelector('[data-quantidade]');
  const caixa = document.querySelector('[data-previa]');
  const vazia = caixa.querySelector('[data-previa-vazia]');
  const conteudo = caixa.querySelector('[data-previa-conteudo]');
  const linhas = caixa.querySelector('[data-previa-linhas]');
  const resumo = caixa.querySelector('[data-previa-resumo]');
  let espera;
  const escapar = (t) => String(t).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

  const atualizar = async () => {
    const q = parseInt(quantidade.value, 10);
    if (!produto.value || !(q > 0)) {
      vazia.hidden = false;
      conteudo.hidden = true;
      return;
    }
    const r = await fetch(`${form.dataset.previaUrl}?produto=${encodeURIComponent(produto.value)}&quantidade=${q}`, { headers: { Accept: 'application/json' } });
    if (!r.ok) return;
    const p = await r.json();
    vazia.hidden = true;
    conteudo.hidden = false;
    linhas.innerHTML = p.linhas.map((l) => `<tr class="${l.falta > 0 ? 'pr-falta' : ''}"><td>${escapar(l.nome)}</td><td class="direita"><strong>${escapar(l.precisa_txt)}</strong></td>
      <td class="direita">${escapar(l.estoque_txt)}</td><td class="direita">${l.falta > 0 ? `<strong class="pn-vermelho">${escapar(l.falta_txt)}</strong>` : '<span class="pn-verde">ok</span>'}</td></tr>`).join('');
    resumo.innerHTML = p.sem_ficha ? 'Este produto não tem ficha técnica.'
      : `Custo de matéria-prima: <strong>${escapar(p.custo_txt)}</strong> (${escapar(p.custo_unidade_txt)} por unidade). `
        + (p.ok ? '<span class="pn-verde">Há estoque para produzir.</span>' : '<span class="pn-vermelho">Falta matéria-prima: a ordem pode ser planejada, mas só conclui depois da compra.</span>');
  };
  const agendar = () => { clearTimeout(espera); espera = setTimeout(atualizar, 250); };
  produto.addEventListener('change', agendar);
  quantidade.addEventListener('input', agendar);
  atualizar();
}
