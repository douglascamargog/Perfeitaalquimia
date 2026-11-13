// =====================================================================
// Equipe de vendas: gráfico do faturado por frente (Chart.js), com as cores do tema.
// =====================================================================
import { cor, corAlfa } from './cores.js';

const dados = JSON.parse(document.getElementById('dados-equipe')?.textContent || '{"meses":[],"series":[]}');
const MESES = ['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'];
const canvas = document.getElementById('grafico-frentes');
if (canvas && window.Chart) {
  Chart.defaults.font.family = getComputedStyle(document.body).fontFamily;
  Chart.defaults.color = cor('--texto-suave');
  const cores = [cor('--primaria-escura'), cor('--secundaria'), corAlfa('--primaria', 0.45)];
  new Chart(canvas, {
    type: 'bar',
    data: {
      labels: dados.meses.map((m) => MESES[Number(m.slice(5)) - 1]),
      datasets: dados.series.map((s, i) => ({ label: s.nome, data: s.valores, backgroundColor: cores[i % cores.length], borderRadius: 6, maxBarThickness: 26 })),
    },
    options: {
      maintainAspectRatio: false,
      plugins: { legend: { position: 'bottom' }, tooltip: { callbacks: { label: (c) => `${c.dataset.label}: ${c.parsed.y.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })}` } } },
      scales: { y: { grid: { color: cor('--borda') }, border: { display: false }, ticks: { callback: (v) => (v >= 1000 ? (v / 1000).toLocaleString('pt-BR') + ' mil' : v) } }, x: { grid: { display: false } } },
    },
  });
}
