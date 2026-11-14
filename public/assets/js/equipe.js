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

// Evolução da meta da equipe: um ponto por dia de trabalho (o último valor lançado no dia), linha do ritmo necessário e a meta
const evolucao = JSON.parse(document.getElementById('dados-evolucao')?.textContent || 'null');
const telaEvolucao = document.getElementById('grafico-evolucao');
if (evolucao && telaEvolucao && window.Chart) {
  const dias = evolucao.dias;
  // Até o último lançamento: cada dia mostra o vendido acumulado mais recente lançado até ele; depois, sem ponto
  const datas = Object.keys(evolucao.vendido).sort();
  const ultima = datas[datas.length - 1];
  const real2 = dias.map((d) => {
    if (d > ultima) return null;
    const ate = datas.filter((x) => x <= d);
    return ate.length ? evolucao.vendido[ate[ate.length - 1]] : 0;
  });
  const moeda = (v) => v.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
  new Chart(telaEvolucao, {
    type: 'line',
    data: {
      labels: dias.map((d) => d.slice(8, 10) + '/' + d.slice(5, 7)),
      datasets: [
        { label: 'Vendido', data: real2, borderColor: '#caff4d', backgroundColor: '#caff4d', borderWidth: 3, pointRadius: 3, spanGaps: true, tension: 0.2 },
        { label: 'Ritmo necessário', data: dias.map((_, i) => (evolucao.meta * (i + 1)) / dias.length), borderColor: '#ffffff60', borderDash: [4, 5], borderWidth: 2, pointRadius: 0 },
        { label: 'Meta', data: dias.map(() => evolucao.meta), borderColor: '#efc07a', borderDash: [7, 6], borderWidth: 1.5, pointRadius: 0 },
      ],
    },
    options: {
      maintainAspectRatio: false, animation: false,
      plugins: { legend: { display: false }, tooltip: { callbacks: { label: (c) => `${c.dataset.label}: ${moeda(c.parsed.y)}` } } },
      scales: {
        y: { beginAtZero: true, grid: { color: '#ffffff14' }, ticks: { color: '#ffffffb0', callback: (v) => (v >= 1000 ? (v / 1000).toLocaleString('pt-BR') + ' mil' : v) } },
        x: { grid: { display: false }, ticks: { color: '#ffffffb0', maxRotation: 0, autoSkip: true, maxTicksLimit: 12 } },
      },
    },
  });
}
