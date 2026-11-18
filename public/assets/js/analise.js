// =====================================================================
// Análise de clientes: gráficos (Chart.js) e filtros que aplicam sozinhos.
// =====================================================================
import { cor, corAlfa } from './cores.js';

// Filtros: trocar um campo já filtra (a UF nova limpa a cidade; o período novo limpa os meses marcados)
document.querySelectorAll('[data-filtros-analise] select').forEach((s) => s.addEventListener('change', () => {
  if (s.hasAttribute('data-limpa-cidade')) { const c = s.form.querySelector('[name=cidade]'); if (c) c.value = ''; }
  if (s.hasAttribute('data-limpa-meses')) s.form.querySelectorAll('[name="meses[]"]').forEach((m) => { m.checked = false; });
  s.form.submit();
}));
document.querySelectorAll('[data-meses-filtro] input').forEach((m) => m.addEventListener('change', () => m.form.submit()));

const dados = JSON.parse(document.getElementById('dados-analise')?.textContent || '{}');
const MESES = ['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'];
const nomeMes = (m) => MESES[Number(m.slice(5)) - 1] + '/' + m.slice(2, 4);
const real = (v) => v.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL', maximumFractionDigits: 0 });
const curto = (v) => (v >= 1000000 ? (v / 1000000).toLocaleString('pt-BR', { maximumFractionDigits: 1 }) + ' mi' : v >= 1000 ? Math.round(v / 1000).toLocaleString('pt-BR') + ' mil' : v);

if (window.Chart && dados.mensal) {
  Chart.defaults.font.family = getComputedStyle(document.body).fontFamily;
  Chart.defaults.color = cor('--texto-suave');
  const grade = { grid: { color: cor('--borda') }, border: { display: false } };
  const semGrade = { grid: { display: false }, border: { display: false } };
  const base = { responsive: true, maintainAspectRatio: false, animation: false };
  const lima = '#9bcf24';

  // Mês a mês: barras = faturamento; linhas = clientes que compraram e clientes novos
  new Chart(document.getElementById('grafico-mensal'), {
    data: {
      labels: dados.mensal.map((m) => nomeMes(m.mes)),
      datasets: [
        { type: 'bar', label: 'Faturamento', data: dados.mensal.map((m) => m.valor), yAxisID: 'y', backgroundColor: corAlfa('--primaria', 0.75), borderRadius: 8, maxBarThickness: 38, order: 3 },
        { type: 'line', label: 'Clientes que compraram', data: dados.mensal.map((m) => m.clientes), yAxisID: 'y2', borderColor: cor('--primaria-escura'), backgroundColor: cor('--primaria-escura'), tension: 0.3, pointRadius: 4, order: 1 },
        { type: 'line', label: 'Clientes novos', data: dados.mensal.map((m) => m.novos), yAxisID: 'y2', borderColor: lima, backgroundColor: lima, borderDash: [5, 4], tension: 0.3, pointRadius: 3, order: 2 },
      ],
    },
    options: {
      ...base, interaction: { mode: 'index', intersect: false },
      plugins: { legend: { position: 'bottom' }, tooltip: { callbacks: { label: (c) => `${c.dataset.label}: ${c.dataset.yAxisID === 'y' ? real(c.raw) : c.raw}` } } },
      scales: { x: semGrade, y: { ...grade, position: 'left', ticks: { callback: curto } }, y2: { ...semGrade, position: 'right', beginAtZero: true, ticks: { precision: 0 } } },
    },
  });

  // Frequência: quantos clientes compraram em 0, 1, 2... meses (os que compraram todos os meses em destaque)
  const ultimo = dados.frequencia.length - 1;
  new Chart(document.getElementById('grafico-frequencia'), {
    type: 'bar',
    data: {
      labels: dados.frequencia.map((x) => (x.meses === 0 ? 'nenhum' : x.meses === 1 ? '1 mês' : x.meses + ' meses')),
      datasets: [{ data: dados.frequencia.map((x) => x.clientes), borderRadius: 6, maxBarThickness: 30,
        backgroundColor: dados.frequencia.map((x, i) => (i === 0 ? cor('--borda') : i === 1 ? '#edbd78' : i >= ultimo - 1 ? lima : cor('--primaria'))) }],
    },
    options: { ...base, plugins: { legend: { display: false }, tooltip: { callbacks: { label: (c) => `${c.raw} cliente(s)` } } }, scales: { x: semGrade, y: { ...grade, beginAtZero: true, ticks: { precision: 0 } } } },
  });

  // Situação da carteira pela última compra
  new Chart(document.getElementById('grafico-situacao'), {
    type: 'doughnut',
    data: { labels: dados.situacao.map((s) => s[0]), datasets: [{ data: dados.situacao.map((s) => s[1]), borderWidth: 0,
      backgroundColor: [cor('--primaria-escura'), cor('--secundaria', '#5cd4b6'), '#edbd78', '#e07a5f', cor('--borda')] }] },
    options: { ...base, cutout: '60%', plugins: { legend: { position: window.innerWidth < 700 ? 'bottom' : 'right' }, tooltip: { callbacks: { label: (c) => `${c.label}: ${c.raw}` } } } },
  });

  // Quem mais compra: barras deitadas por grupo (UF, cidade, vendedor...)
  new Chart(document.getElementById('grafico-grupos'), {
    type: 'bar',
    data: { labels: dados.grupos.map((g) => g.nome), datasets: [{ data: dados.grupos.map((g) => g.valor), backgroundColor: cor('--secundaria', '#4f7c6c'), borderRadius: 6, maxBarThickness: 22 }] },
    options: {
      ...base, indexAxis: 'y',
      plugins: { legend: { display: false }, tooltip: { callbacks: { label: (c) => `${real(c.raw)} · recorrência ${dados.grupos[c.dataIndex].recorrencia}%` } } },
      scales: { x: { ...grade, ticks: { callback: curto } }, y: semGrade },
    },
  });
}
