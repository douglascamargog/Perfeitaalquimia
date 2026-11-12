// =====================================================================
// Novo pedido (equipe): soma ao vivo, barra do pedido mínimo, filtro de produtos
// e condição de pagamento que depende do valor. O servidor confere tudo de novo ao enviar.
// =====================================================================
const form = document.querySelector('[data-pedido]');
const real = (v) => v.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });

function recalcular() {
  const minimo = Number(form.dataset.minimo) || 0;
  let subtotal = 0, itens = 0;
  form.querySelectorAll('[data-linha]').forEach((linha) => {
    const q = Math.max(0, Number(linha.querySelector('.ped-qtd').value) || 0);
    const total = q * Number(linha.dataset.preco);
    linha.querySelector('[data-total-linha]').textContent = q ? real(total) : '';
    linha.classList.toggle('escolhido', q > 0);
    subtotal += total; itens += q;
  });
  form.querySelector('[data-itens]').textContent = itens;
  form.querySelector('[data-subtotal]').textContent = real(subtotal);
  const falta = Math.max(0, minimo - subtotal);
  const caixa = form.querySelector('[data-caixa-minimo]');
  caixa.className = 'cat-minimo ' + (falta > 0 ? 'falta' : 'ok');
  form.querySelector('[data-texto-minimo]').textContent = falta > 0 ? `Faltam ${real(falta)} para o mínimo (${real(minimo)})` : 'Pedido mínimo atingido ✓';
  form.querySelector('[data-barra-minimo]').style.width = (minimo ? Math.min(100, subtotal / minimo * 100) : 100) + '%';
  // Condições com valor mínimo (ex.: boleto 28/56 a partir de R$ 1.500)
  form.querySelectorAll('[data-condicao-minimo]').forEach((op) => {
    const bloqueada = subtotal < Number(op.dataset.condicaoMinimo);
    const radio = op.querySelector('input');
    radio.disabled = bloqueada;
    if (bloqueada) radio.checked = false;
    op.classList.toggle('desligada', bloqueada);
  });
  const enviar = form.querySelector('[data-enviar]');
  enviar.disabled = falta > 0 || itens === 0;
  enviar.textContent = itens === 0 ? 'Escolha os produtos' : falta > 0 ? 'Complete o pedido mínimo' : 'Enviar pedido';
}

function filtrar() {
  const termo = form.querySelector('[data-filtro-produtos]').value.trim().toLowerCase();
  const so = form.querySelector('[data-so-escolhidos]').checked;
  form.querySelectorAll('[data-linha]').forEach((linha) => {
    const visivel = (!termo || linha.dataset.nome.includes(termo)) && (!so || linha.classList.contains('escolhido'));
    linha.hidden = !visivel;
  });
}

if (form) {
  form.addEventListener('input', (e) => {
    if (e.target.matches('.ped-qtd')) recalcular();
    if (e.target.matches('[data-filtro-produtos]')) filtrar();
  });
  form.addEventListener('change', (e) => { if (e.target.matches('[data-so-escolhidos]')) filtrar(); });
  // Enter no campo de quantidade não envia o pedido sem querer
  form.addEventListener('keydown', (e) => { if (e.key === 'Enter' && e.target.matches('input:not([type=submit])')) e.preventDefault(); });
  form.addEventListener('submit', () => { form.querySelector('[data-enviar]').disabled = true; });
  recalcular();
}
