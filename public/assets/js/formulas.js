// =====================================================================
// Fórmulas (V15.0): linhas de ingrediente (mais/tirar) e as contas na hora enquanto edita
// (massa da porção, % de cada ingrediente, ocupação da cápsula, custo por porção, embalagem e lote).
// O valor oficial é o calculado no servidor ao salvar (FormulaService::calcular).
// =====================================================================
const dados = JSON.parse(document.getElementById('dados-formula')?.textContent || '{"mps":{}}');
const form = document.querySelector('[data-form-formula]');
const corpo = document.querySelector('[data-itens-corpo]');
const real = (v) => v.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL', minimumFractionDigits: 2, maximumFractionDigits: v < 1 ? 4 : 2 });
const num = (t) => { const v = parseFloat(String(t || '').replace(/\./g, '').replace(',', '.')); return Number.isFinite(v) ? v : 0; };
const fmt = (v, c = 1) => v.toLocaleString('pt-BR', { maximumFractionDigits: c });
const PESO_KG = { mcg: 1e-9, mg: 1e-6, g: 1e-3, kg: 1 };
const VOL_L = { ml: 1e-3, l: 1 };
function fator(de, para) {
  de = de.toLowerCase(); para = para.toLowerCase();
  if (PESO_KG[de] && PESO_KG[para]) return PESO_KG[de] / PESO_KG[para];
  if (VOL_L[de] && VOL_L[para]) return VOL_L[de] / VOL_L[para];
  return de === para ? 1 : null;
}

let proximo = 1000;
document.querySelector('[data-item-mais]')?.addEventListener('click', () => {
  const modelo = document.querySelector('[data-item-modelo]');
  const html = modelo.innerHTML.replace(/itens\[999\]/g, `itens[${proximo++}]`);
  corpo.insertAdjacentHTML('beforeend', html);
  corpo.lastElementChild.querySelector('[data-item-mp]')?.focus();
  calcular();
});
corpo?.addEventListener('click', (ev) => {
  if (ev.target.closest('[data-item-tirar]')) { ev.target.closest('[data-item]').remove(); calcular(); }
});
form?.addEventListener('input', calcular);
form?.addEventListener('change', calcular);

function calcular() {
  if (!form) return;
  const porcoes = Math.max(1, num(form.querySelector('[data-porcoes]')?.value));
  const lote = Math.max(1, num(form.querySelector('[data-lote]')?.value));
  const porcao = num(form.querySelector('[data-porcao]')?.value);
  const unidPorcao = form.querySelector('[data-porcao-unidade]')?.value || '';
  const capacidade = num(form.querySelector('[data-capacidade]')?.value);
  const forma = form.querySelector('[data-forma]')?.value;
  const linhas = [...corpo.querySelectorAll('[data-item]')].map((tr) => {
    const qtd = num(tr.querySelector('[data-item-qtd]').value);
    const unidade = tr.querySelector('[data-item-unidade]').value;
    const base = tr.querySelector('[data-item-base]').value;
    const funcao = tr.querySelector('[data-item-funcao]').value;
    const mp = dados.mps[tr.querySelector('[data-item-mp]').value];
    const conta = base === 'porcao' && !['capsula', 'embalagem'].includes(funcao) && PESO_KG[unidade];
    return { tr, qtd, unidade, base, mp, mg: conta ? qtd * PESO_KG[unidade] / 1e-6 : null };
  });
  const massa = linhas.reduce((s, l) => s + (l.mg || 0), 0);
  let custoEmb = 0;
  linhas.forEach((l) => {
    l.tr.querySelector('[data-item-pct]').textContent = l.mg !== null && massa > 0 ? fmt(l.mg / massa * 100) + '%' : '—';
    let custo = null;
    if (l.mp) {
      const f = fator(l.unidade, l.mp.unidade);
      if (f !== null) { custo = l.qtd * f * (l.base === 'porcao' ? porcoes : 1) * l.mp.custo; custoEmb += custo; }
    }
    l.tr.querySelector('[data-item-custo]').textContent = custo !== null ? real(custo) : '—';
  });
  const r = (sel, txt) => { const e = document.querySelector(sel); if (e) e.textContent = txt; };
  r('[data-r-massa]', fmt(massa) + ' mg');
  const doses = ['cápsulas', 'comprimidos', 'sachês', 'unidades'].includes(unidPorcao) ? Math.max(0.001, porcao) : null;
  r('[data-r-dose]', doses ? fmt(massa / doses) + ' mg' : '—');
  const ocup = forma === 'capsula' && capacidade > 0 && doses ? massa / doses / capacidade * 100 : null;
  const oc = document.querySelector('[data-r-ocupacao]');
  if (oc) { oc.textContent = ocup !== null ? fmt(ocup, 0) + '%' + (ocup > 100 ? ' — não cabe' : '') : '—'; oc.classList.toggle('fm-ruim', ocup !== null && ocup > 100); }
  r('[data-r-custo-porcao]', real(custoEmb / porcoes));
  r('[data-r-custo-emb]', real(custoEmb));
  r('[data-r-custo-lote]', real(custoEmb * lote));
}

// "Perguntar ao especialista": abre o chat do One no especialista de Formulação (ele já sabe qual fórmula está aberta)
document.querySelector('[data-perguntar-especialista]')?.addEventListener('click', () => {
  document.querySelector('[data-one-abrir]')?.click();
  setTimeout(() => {
    document.querySelector('[data-one-aba="perguntar"]')?.click();
    document.querySelector('[data-one-especialista="formulacao"]')?.click();
    document.querySelector('[data-one-pergunte] input')?.focus();
  }, 120);
});
