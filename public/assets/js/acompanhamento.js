// Acompanhamento: gráfico de ligações por dia (atendidas × não atendidas)
import { cor } from './cores.js';

const tela = document.getElementById('grafico-ligacoes');
if (tela && window.Chart) {
  const dados = JSON.parse(tela.dataset.dados || '[]');
  new Chart(tela, {
    type: 'bar',
    data: {
      labels: dados.map((d) => d.dia.split('-').reverse().slice(0, 2).join('/')),
      datasets: [
        { label: 'Atendidas', data: dados.map((d) => Number(d.atendidas)), backgroundColor: cor('--primaria'), stack: 'l' },
        { label: 'Não atendidas', data: dados.map((d) => Number(d.total) - Number(d.atendidas)), backgroundColor: cor('--secundaria'), stack: 'l' },
      ],
    },
    options: { responsive: true, maintainAspectRatio: false, animation: false,
      scales: { y: { beginAtZero: true, stacked: true, ticks: { precision: 0 } }, x: { stacked: true, grid: { display: false } } } },
  });
}

// Cobrança: recuperado por dia (R$)
const telaRec = document.getElementById('grafico-recuperado');
if (telaRec && window.Chart) {
  const dados = JSON.parse(telaRec.dataset.dados || '[]');
  new Chart(telaRec, {
    type: 'bar',
    data: { labels: dados.map((d) => d.dia.split('-').reverse().slice(0, 2).join('/')),
      datasets: [{ label: 'Recuperado', data: dados.map((d) => Number(d.valor)), backgroundColor: cor('--primaria'), borderRadius: 6 }] },
    options: { responsive: true, maintainAspectRatio: false, animation: false, plugins: { legend: { display: false },
      tooltip: { callbacks: { label: (c) => c.parsed.y.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' }) } } },
      scales: { y: { beginAtZero: true }, x: { grid: { display: false } } } },
  });
}
