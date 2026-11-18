// =====================================================================
// Importar Nota10: lê o backup em partes (chama o servidor até terminar) e desenha o gráfico por ano.
// =====================================================================
import { cor, corAlfa } from './cores.js';

const CSRF = document.querySelector('meta[name="csrf"]')?.content || '';
const caixa = document.querySelector('[data-n10-leitura]');

if (caixa) {
  const botao = caixa.querySelector('[data-n10-ler]');
  const barra = caixa.querySelector('[data-n10-pct]');
  const status = caixa.querySelector('[data-n10-status]');
  let lendo = false;

  const parte = async () => {
    const corpo = new URLSearchParams({ _csrf: CSRF });
    const r = await fetch(caixa.dataset.url, { method: 'POST', body: corpo, headers: { Accept: 'application/json', 'X-CSRF-Token': CSRF } });
    return r.json().catch(() => ({ erro: 'O servidor não respondeu direito (código ' + r.status + ').' }));
  };

  botao.addEventListener('click', async () => {
    if (lendo) return;
    lendo = true; botao.disabled = true;
    window.onbeforeunload = () => 'A leitura ainda não terminou.';
    let falhas = 0;
    for (;;) {
      let r;
      try { r = await parte(); } catch { r = { erro: 'Sem conexão com o servidor.' }; }
      if (r.pct !== undefined) {
        barra.style.width = r.pct + '%';
        barra.parentElement.setAttribute('aria-valuenow', Math.round(r.pct));
        status.textContent = `Lendo… ${r.pct.toLocaleString('pt-BR')}% · ${r.comandos.toLocaleString('pt-BR')} comando(s). Deixe esta página aberta.`;
      }
      if (r.situacao === 'lido') { status.textContent = 'Pronto! Abrindo a conferência…'; window.onbeforeunload = null; location.reload(); return; }
      if (r.erro || r.situacao === 'erro') {
        // falha de rede/tempo: tenta de novo algumas vezes antes de desistir (continua de onde parou)
        if (!r.situacao && ++falhas <= 3) { await new Promise((ok) => setTimeout(ok, 3000)); continue; }
        status.textContent = 'Parou: ' + (r.erro || 'erro desconhecido');
        window.onbeforeunload = null; lendo = false; botao.disabled = false; botao.textContent = 'Tentar continuar';
        return;
      }
      falhas = 0;
    }
  });
}

// Gráfico: valor dos pedidos por ano
const anos = JSON.parse(document.getElementById('n10-anos')?.textContent || '[]');
const tela = document.getElementById('n10-grafico-anos');
if (window.Chart && tela && anos.length) {
  Chart.defaults.font.family = getComputedStyle(document.body).fontFamily;
  Chart.defaults.color = cor('--texto-suave');
  const real = (v) => v.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL', maximumFractionDigits: 0 });
  new Chart(tela, {
    type: 'bar',
    data: { labels: anos.map((a) => a.ano), datasets: [{ data: anos.map((a) => a.valor), backgroundColor: corAlfa('--primaria', 0.8), borderRadius: 8, maxBarThickness: 46 }] },
    options: {
      responsive: true, maintainAspectRatio: false, animation: false,
      plugins: { legend: { display: false }, tooltip: { callbacks: { label: (c) => `${real(c.raw)} · ${anos[c.dataIndex].qtd.toLocaleString('pt-BR')} pedido(s)` } } },
      scales: { x: { grid: { display: false } }, y: { grid: { color: cor('--borda') }, ticks: { callback: (v) => (v >= 1e6 ? (v / 1e6).toLocaleString('pt-BR') + ' mi' : v >= 1e3 ? (v / 1e3).toLocaleString('pt-BR', { maximumFractionDigits: 1 }) + ' mil' : v) } } },
    },
  });
}
