// =====================================================================
// E-commerce (V14.0): gráficos (Chart.js) e mapa de calor.
// Os dados vêm do bloco <script id="dados-ecommerce"> da página.
// =====================================================================
import { cor, corAlfa } from './cores.js';
const dados = JSON.parse(document.getElementById('dados-ecommerce')?.textContent || '{}');
const real = (v) => v.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL', maximumFractionDigits: 0 });
const MESES = ['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'];
const DIAS = ['seg', 'ter', 'qua', 'qui', 'sex', 'sáb', 'dom'];
// Cor de cada canal (a mesma dos selos)
const CORES_CANAL = { loja: '#2f9e5b', mercado_livre: '#f5d000', shopee: '#ee6a2c', amazon: '#232f3e', magalu: '#0b6be0', americanas: '#d92b2b', shein: '#6b7280', tiktok: '#111827', manual: '#94a3b8', outro: '#a3a3a3' };

if (window.Chart && dados.diario) {
  Chart.defaults.font.family = getComputedStyle(document.body).fontFamily;
  Chart.defaults.color = cor('--texto-suave');
  const eixoValor = { grid: { color: cor('--borda') }, border: { display: false }, ticks: { callback: (v) => (v >= 1000 ? (v / 1000).toLocaleString('pt-BR') + ' mil' : v) } };

  // Faturamento por dia, empilhado por canal
  const diario = document.getElementById('grafico-diario');
  if (diario) {
    const dias = [];
    for (let d = new Date(dados.de + 'T12:00:00'); d <= new Date(dados.ate + 'T12:00:00'); d.setDate(d.getDate() + 1)) dias.push(d.toISOString().slice(0, 10));
    const porDia = Object.fromEntries(dias.map((d) => [d, {}]));
    dados.diario.forEach((l) => { if (porDia[l.dia]) porDia[l.dia][l.canal] = l; });
    const canais = dados.canais.filter((c) => dados.diario.some((l) => l.canal === c.chave));
    const rotulo = (d) => (dias.length > 62 ? d.slice(8, 10) + '/' + d.slice(5, 7) : String(Number(d.slice(8, 10))));
    new Chart(diario, {
      type: 'bar',
      data: {
        labels: dias.map(rotulo),
        datasets: canais.map((c) => ({ label: c.nome, data: dias.map((d) => porDia[d][c.chave]?.valor || 0), backgroundColor: CORES_CANAL[c.chave] || '#a3a3a3', borderRadius: 3, maxBarThickness: 22, stack: 'v' })),
      },
      options: {
        responsive: true, maintainAspectRatio: false, animation: false,
        plugins: {
          legend: { position: 'bottom', labels: { boxWidth: 10, boxHeight: 10 } },
          tooltip: { mode: 'index', callbacks: { title: (i) => dias[i[0].dataIndex].split('-').reverse().join('/'), label: (c) => (c.raw ? `${c.dataset.label}: ${real(c.raw)}` : null),
            footer: (i) => 'Total: ' + real(i.reduce((s, x) => s + x.raw, 0)) } },
        },
        scales: { x: { stacked: true, grid: { display: false }, border: { display: false } }, y: { ...eixoValor, stacked: true } },
      },
    });
  }

  // 12 meses
  const meses = document.getElementById('grafico-meses');
  if (meses) {
    new Chart(meses, {
      type: 'line',
      data: {
        labels: dados.meses.map((m) => MESES[Number(m.mes.slice(5)) - 1]),
        datasets: [{ label: 'Faturamento', data: dados.meses.map((m) => m.valor), borderColor: cor('--primaria-escura'),
          segment: { borderDash: (c) => (c.p1DataIndex === dados.meses.length - 1 && dados.mesAtualParcial ? [5, 5] : undefined) },
          backgroundColor: corAlfa('--secundaria', 0.35), fill: true, tension: 0.35, pointRadius: 3, pointBackgroundColor: cor('--primaria-escura') }],
      },
      options: {
        responsive: true, maintainAspectRatio: false, animation: false,
        plugins: { legend: { display: false }, tooltip: { callbacks: { label: (c) => `${real(c.raw)} · ${dados.meses[c.dataIndex].pedidos} pedidos` + (c.dataIndex === dados.meses.length - 1 && dados.mesAtualParcial ? ' (mês em andamento)' : '') } } },
        scales: { x: { grid: { display: false }, border: { display: false } }, y: eixoValor },
      },
    });
  }

  // Formas de pagamento
  const pag = document.getElementById('grafico-pagamentos');
  if (pag && dados.pagamentos.length) {
    new Chart(pag, {
      type: 'doughnut',
      data: { labels: dados.pagamentos.map((p) => p.nome), datasets: [{ data: dados.pagamentos.map((p) => p.valor), backgroundColor: ['#2f9e5b', '#caff4d', '#f59e0b', '#94a3b8', '#3b82f6'], borderWidth: 0 }] },
      options: { responsive: true, maintainAspectRatio: false, animation: false, cutout: '62%',
        plugins: { legend: { position: 'right', labels: { boxWidth: 10, boxHeight: 10 } }, tooltip: { callbacks: { label: (c) => `${c.label}: ${real(c.raw)}` } } } },
    });
  }
}

// Mapa de calor: dia da semana × hora
const calor = document.getElementById('eco-calor');
if (calor && dados.calor) {
  const m = Array.from({ length: 7 }, () => Array(24).fill(0));
  dados.calor.forEach(([d, h, n]) => { m[d][h] = n; });
  const max = Math.max(1, ...m.flat());
  const partes = ['<span></span>', ...Array.from({ length: 24 }, (_, h) => `<span>${h % 3 === 0 ? h + 'h' : ''}</span>`)];
  m.forEach((linha, d) => {
    partes.push(`<b>${DIAS[d]}</b>`);
    linha.forEach((n, h) => {
      const forca = Math.round((n / max) * 100);
      partes.push(`<i style="--forca:${forca}%" ${forca > 60 ? 'data-forte' : ''} title="${DIAS[d]} ${h}h: ${n} pedido(s)"></i>`);
    });
  });
  calor.innerHTML = partes.join('');
}
