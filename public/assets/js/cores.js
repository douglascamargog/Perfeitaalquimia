// =====================================================================
// Cores do tema para os gráficos (Chart.js não lê variáveis CSS sozinho).
// Lê as variáveis da página (as cores escolhidas em Meu perfil › Aparência ou as da empresa).
// =====================================================================
export function cor(nome, reserva = '#025150') {
  const v = getComputedStyle(document.documentElement).getPropertyValue(nome).trim();
  return v || reserva;
}

/** Mesma cor com transparência (0 a 1) */
export function corAlfa(nome, alfa) {
  const h = cor(nome).replace('#', '');
  if (h.length !== 6) return cor(nome);
  const [r, g, b] = [0, 2, 4].map((i) => parseInt(h.slice(i, i + 2), 16));
  return `rgba(${r}, ${g}, ${b}, ${alfa})`;
}
