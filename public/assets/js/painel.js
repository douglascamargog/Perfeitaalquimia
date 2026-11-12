// =====================================================================
// Painel de vendas: gráficos (Chart.js) e campos de data do período "Escolher datas".
// =====================================================================
const dados = JSON.parse(document.getElementById('dados-painel')?.textContent || '{"meses":[],"ufs":[],"origens":[]}');
const real = (v) => v.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL', maximumFractionDigits: 0 });
const MESES = ['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'];

// Período "Escolher datas" mostra os campos de/até
const periodo = document.querySelector('[data-periodo]');
periodo?.addEventListener('change', () => {
  const livre = periodo.value === 'livre';
  document.querySelectorAll('[data-livre]').forEach((c) => (c.hidden = !livre));
});

if (window.Chart) {
  Chart.defaults.font.family = getComputedStyle(document.body).fontFamily;
  Chart.defaults.color = '#758078';
  const eixo = { grid: { color: '#eef2ec' }, border: { display: false }, ticks: { callback: (v) => (v >= 1000 ? (v / 1000).toLocaleString('pt-BR') + ' mil' : v) } };
  const semGrade = { grid: { display: false }, border: { display: false } };
  const base = { responsive: true, maintainAspectRatio: false, animation: false };

  const meses = document.getElementById('grafico-meses');
  if (meses) {
    new Chart(meses, {
      data: {
        labels: dados.meses.map((m) => MESES[Number(m.mes.slice(5)) - 1] + '/' + m.mes.slice(2, 4)),
        datasets: [
          { type: 'bar', label: 'Faturado', data: dados.meses.map((m) => m.valor), backgroundColor: dados.meses.map((_, i, l) => (i === l.length - 1 ? '#14251b' : '#b6e45a')), borderRadius: 8, maxBarThickness: 36 },
          { type: 'line', label: 'Meta da equipe', data: dados.meses.map((m) => m.meta), borderColor: '#c2410c', borderDash: [6, 4], pointRadius: 3, spanGaps: false },
        ],
      },
      options: { ...base, plugins: { legend: { position: 'bottom' }, tooltip: { callbacks: { label: (c) => `${c.dataset.label}: ${c.raw === null ? '—' : real(c.raw)}` } } }, scales: { x: semGrade, y: eixo } },
    });
  }
  const ufs = document.getElementById('grafico-ufs');
  if (ufs) {
    new Chart(ufs, {
      type: 'bar',
      data: { labels: dados.ufs.map((u) => u.nome), datasets: [{ data: dados.ufs.map((u) => u.valor), backgroundColor: '#429b77', borderRadius: 6, maxBarThickness: 30 }] },
      options: { ...base, plugins: { legend: { display: false }, tooltip: { callbacks: { label: (c) => real(c.raw) } } }, scales: { x: semGrade, y: eixo } },
    });
  }
  const origens = document.getElementById('grafico-origens');
  if (origens) {
    new Chart(origens, {
      type: 'doughnut',
      data: { labels: dados.origens.map((o) => o.nome), datasets: [{ data: dados.origens.map((o) => o.valor), backgroundColor: ['#14251b', '#b6e45a', '#429b77', '#edbd78'], borderWidth: 0 }] },
      options: { ...base, cutout: '62%', plugins: { legend: { position: 'bottom' }, tooltip: { callbacks: { label: (c) => `${c.label}: ${real(c.raw)}` } } } },
    });
  }
}
