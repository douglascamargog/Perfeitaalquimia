// =====================================================================
// E-commerce: gráficos (Chart.js, carregado antes pelo layout).
// =====================================================================
import { cor, corAlfa } from './cores.js';
const dados = JSON.parse(document.getElementById('dados-ecommerce')?.textContent || '{"diario":[],"meses":[]}');
const real = (v) => v.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL', maximumFractionDigits: 0 });
const MESES = ['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'];

if (window.Chart) {
  Chart.defaults.font.family = getComputedStyle(document.body).fontFamily;
  Chart.defaults.color = cor('--texto-suave');
  const eixoValor = { grid: { color: cor('--borda') }, border: { display: false }, ticks: { callback: (v) => (v >= 1000 ? (v / 1000).toLocaleString('pt-BR') + ' mil' : v) } };

  const diario = document.getElementById('grafico-diario');
  if (diario) {
    new Chart(diario, {
      type: 'bar',
      data: {
        labels: dados.diario.map((d) => d.dia),
        datasets: [{ label: 'Faturamento', data: dados.diario.map((d) => d.valor), backgroundColor: dados.diario.map((_, i, l) => (i === l.length - 1 ? cor('--primaria-escura') : cor('--secundaria'))), borderRadius: 6, maxBarThickness: 22 }],
      },
      options: {
        responsive: true, maintainAspectRatio: false, animation: false,
        plugins: { legend: { display: false }, tooltip: { callbacks: { title: (i) => `Dia ${i[0].label}`, label: (c) => `${real(c.raw)} · ${dados.diario[c.dataIndex].pedidos} pedidos` } } },
        scales: { x: { grid: { display: false }, border: { display: false } }, y: eixoValor },
      },
    });
  }

  const meses = document.getElementById('grafico-meses');
  if (meses) {
    new Chart(meses, {
      type: 'line',
      data: {
        labels: dados.meses.map((m) => MESES[Number(m.mes.slice(5)) - 1]),
        datasets: [{ label: 'Faturamento', data: dados.meses.map((m) => m.valor), borderColor: cor('--primaria-escura'),
          // O mês atual ainda não terminou: o último trecho fica tracejado
          segment: { borderDash: (c) => (c.p1DataIndex === dados.meses.length - 1 && dados.mesAtualParcial ? [5, 5] : undefined) }, backgroundColor: corAlfa('--secundaria', .35), fill: true, tension: 0.35, pointRadius: 3, pointBackgroundColor: cor('--primaria-escura') }],
      },
      options: {
        responsive: true, maintainAspectRatio: false, animation: false,
        plugins: { legend: { display: false }, tooltip: { callbacks: { label: (c) => real(c.raw) + (c.dataIndex === dados.meses.length - 1 && dados.mesAtualParcial ? ' (mês em andamento)' : '') } } },
        scales: { x: { grid: { display: false }, border: { display: false } }, y: eixoValor },
      },
    });
  }
}
