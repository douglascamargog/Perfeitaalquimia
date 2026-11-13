// =====================================================================
// Meu perfil › Aparência: prévia ao vivo das cores (antes de salvar).
// O cálculo dos tons é o mesmo de app/Services/Aparencia.php.
// =====================================================================
const caixa = document.querySelector('[data-aparencia]');
if (caixa) {
  const prim = caixa.querySelector('[name=cor_primaria]');
  const sec = caixa.querySelector('[name=cor_secundaria]');
  const rgb = (h) => [1, 3, 5].map((i) => parseInt(h.slice(i, i + 2), 16));
  const hex = (c) => '#' + c.map((v) => Math.round(v).toString(16).padStart(2, '0')).join('');
  const mistura = (a, b, q) => { const x = rgb(a), y = rgb(b); return hex(x.map((v, i) => v + (y[i] - v) * q)); };
  const lum = (h) => { const c = rgb(h).map((v) => { v /= 255; return v <= 0.03928 ? v / 12.92 : ((v + 0.055) / 1.055) ** 2.4; }); return 0.2126 * c[0] + 0.7152 * c[1] + 0.0722 * c[2]; };
  const contraste = (a, b) => { const x = lum(a), y = lum(b); return (Math.max(x, y) + 0.05) / (Math.min(x, y) + 0.05); };
  const texto = (f) => (contraste(f, '#ffffff') >= contraste(f, '#10201d') ? '#ffffff' : '#10201d');

  function variaveis(p, s) {
    let escura = mistura(p, '#000000', 0.18);
    for (let i = 0; i < 12 && contraste(escura, '#ffffff') < 4.5; i++) escura = mistura(escura, '#000000', 0.15);
    let secEscura = mistura(s, '#000000', 0.15);
    for (let i = 0; i < 12 && contraste(secEscura, '#ffffff') < 3; i++) secEscura = mistura(secEscura, '#000000', 0.15);
    return {
      '--primaria': p, '--primaria-texto': texto(p), '--primaria-escura': escura,
      '--primaria-clara': mistura(p, '#ffffff', 0.92), '--primaria-suave': mistura(p, '#ffffff', 0.82),
      '--secundaria': s, '--secundaria-texto': texto(s), '--secundaria-escura': secEscura,
      '--secundaria-clara': mistura(s, '#ffffff', 0.78), '--fundo': mistura(p, '#f6f7f6', 0.955),
    };
  }
  function aplicar() {
    const v = variaveis(prim.value.toLowerCase(), sec.value.toLowerCase());
    Object.entries(v).forEach(([k, val]) => document.documentElement.style.setProperty(k, val));
  }
  // Combinação pronta → preenche os dois seletores
  caixa.addEventListener('change', (e) => {
    if (e.target.matches('[name=combinacao]')) {
      prim.value = e.target.dataset.primaria;
      sec.value = e.target.dataset.secundaria;
    }
    if (e.target === prim || e.target === sec) {
      caixa.querySelector('[name=combinacao][value=personalizada]').checked = true;
    }
    aplicar();
  });
  caixa.addEventListener('input', (e) => { if (e.target === prim || e.target === sec) aplicar(); });
}
