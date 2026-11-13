// =====================================================================
// Aniversário: chuva de confete ao entrar no sistema (uma vez por sessão), só para o aniversariante.
// Desenhado num <canvas>, sem biblioteca. Quem pediu "reduzir movimento" no aparelho não vê a animação.
// =====================================================================
const tela = document.querySelector('[data-confete]');
const reduzir = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
let jaViu = false;
try { jaViu = sessionStorage.getItem('one-confete') === '1'; sessionStorage.setItem('one-confete', '1'); } catch { /* sem armazenamento */ }

if (tela && !reduzir && !jaViu) {
  const ctx = tela.getContext('2d');
  const cores = ['#5cd4b6', '#025150', '#f472b6', '#facc15', '#60a5fa', '#fb923c'];
  const ajustar = () => { tela.width = innerWidth; tela.height = innerHeight; };
  ajustar();
  addEventListener('resize', ajustar);
  const pedacos = Array.from({ length: 160 }, () => ({
    x: Math.random() * innerWidth, y: -20 - Math.random() * innerHeight, l: 6 + Math.random() * 6, a: 4 + Math.random() * 4,
    vy: 2 + Math.random() * 3, vx: -1 + Math.random() * 2, g: Math.random() * Math.PI, vg: -0.1 + Math.random() * 0.2,
    cor: cores[Math.floor(Math.random() * cores.length)],
  }));
  const inicio = performance.now();
  const quadro = (t) => {
    ctx.clearRect(0, 0, tela.width, tela.height);
    const acabando = t - inicio > 4500;
    let vivos = 0;
    for (const p of pedacos) {
      p.y += p.vy; p.x += p.vx + Math.sin(p.g) * 0.6; p.g += p.vg;
      if (p.y > tela.height + 20) { if (acabando) continue; p.y = -20; }
      vivos++;
      ctx.save(); ctx.translate(p.x, p.y); ctx.rotate(p.g); ctx.fillStyle = p.cor; ctx.fillRect(-p.l / 2, -p.a / 2, p.l, p.a); ctx.restore();
    }
    if (vivos) requestAnimationFrame(quadro); else tela.remove();
  };
  requestAnimationFrame(quadro);
} else if (tela) {
  tela.remove();
}
