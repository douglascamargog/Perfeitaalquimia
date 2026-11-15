// =====================================================================
// Financeiro › Compras: lançar compra com várias linhas (preço sugerido pelo último preço do item, total ao vivo).
// =====================================================================
const form = document.querySelector('[data-lancar-compra]');
const real = (v) => v.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
const numero = (t) => Number(String(t || '').replace(/\./g, '').replace(',', '.')) || 0;

function recalcular() {
  let total = 0;
  form.querySelectorAll('[data-linha]').forEach((l) => {
    const v = numero(l.querySelector('[name="quantidade[]"]').value) * numero(l.querySelector('[name="valor[]"]').value);
    l.querySelector('[data-total-linha]').textContent = v ? real(v) : '—';
    total += v;
  });
  form.querySelector('[data-total-nota]').textContent = real(total);
}

if (form) {
  const corpo = form.querySelector('[data-linhas] tbody');
  const modelo = corpo.querySelector('[data-linha]').cloneNode(true);
  form.addEventListener('input', recalcular);
  form.addEventListener('change', (e) => {
    if (e.target.matches('[name="item[]"]')) {
      const op = e.target.selectedOptions[0];
      const valor = e.target.closest('[data-linha]').querySelector('[name="valor[]"]');
      if (op?.dataset.preco && !valor.value) valor.value = String(Number(op.dataset.preco)).replace('.', ',');
      recalcular();
    }
  });
  form.addEventListener('click', (e) => {
    if (e.target.closest('[data-mais-linha]')) {
      const nova = modelo.cloneNode(true);
      nova.querySelectorAll('input').forEach((i) => { i.value = ''; });
      nova.querySelector('select').value = '';
      corpo.append(nova);
      recalcular();
    }
    const tirar = e.target.closest('[data-tirar-linha]');
    if (tirar && corpo.querySelectorAll('[data-linha]').length > 1) { tirar.closest('[data-linha]').remove(); recalcular(); }
  });
}
