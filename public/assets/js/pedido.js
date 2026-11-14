// =====================================================================
// Novo pedido (equipe): soma ao vivo, barra do pedido mínimo, filtro de produtos
// e condição de pagamento que depende do valor. O servidor confere tudo de novo ao enviar.
// Também usado no LINK DO ORÇAMENTO (cliente) e com os MODELOS DE PEDIDO (somar quantidades prontas).
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
  enviar.dataset.texto ??= enviar.textContent;
  enviar.disabled = falta > 0 || itens === 0;
  enviar.textContent = itens === 0 ? 'Escolha os produtos' : falta > 0 ? 'Complete o pedido mínimo' : enviar.dataset.texto;
  const orc = form.querySelector('[data-gerar-orcamento]');
  if (orc) orc.disabled = itens === 0;
}

function filtrar() {
  const campo = form.querySelector('[data-filtro-produtos]');
  if (!campo) return;
  const termo = campo.value.trim().toLowerCase();
  const so = form.querySelector('[data-so-escolhidos]')?.checked;
  // No link do orçamento a busca vale só para a lista "Adicionar mais produtos"
  (campo.closest('[data-area-filtro]') || form).querySelectorAll('[data-linha]').forEach((linha) => {
    const visivel = (!termo || linha.dataset.nome.includes(termo)) && (!so || linha.classList.contains('escolhido'));
    linha.hidden = !visivel;
  });
}

// Modelos de pedido: soma as quantidades do modelo escolhido às que já estão na tela
function aplicarModelo() {
  const caixa = form.querySelector('[data-modelos]');
  const id = Number(caixa.querySelector('[data-modelo-escolha]').value);
  const msg = caixa.querySelector('[data-modelo-msg]');
  const modelo = JSON.parse(caixa.dataset.modelos).find((m) => m.id === id);
  if (!modelo) { msg.textContent = 'Escolha um modelo na lista.'; return; }
  let somados = 0, faltam = 0;
  Object.entries(modelo.itens).forEach(([produto, qtd]) => {
    const campo = form.querySelector(`.ped-qtd[name="qtd[${produto}]"]`);
    if (!campo) { faltam++; return; } // produto sem preço na tabela deste cliente
    campo.value = (Number(campo.value) || 0) + Number(qtd);
    const linha = campo.closest('[data-linha]');
    linha.classList.remove('ped-do-modelo'); void linha.offsetWidth; linha.classList.add('ped-do-modelo');
    somados++;
  });
  msg.textContent = `${somados} produto(s) somado(s) ao pedido` + (faltam ? ` · ${faltam} sem preço na tabela deste cliente (ficaram de fora)` : '') + '. Ajuste as quantidades se quiser.';
  const so = form.querySelector('[data-so-escolhidos]');
  if (so) so.checked = true;
  recalcular(); filtrar();
}

if (form) {
  form.querySelector('[data-modelo-aplicar]')?.addEventListener('click', aplicarModelo);
  form.addEventListener('input', (e) => {
    if (e.target.matches('.ped-qtd')) recalcular();
    if (e.target.matches('[data-filtro-produtos]')) filtrar();
  });
  form.addEventListener('change', (e) => { if (e.target.matches('[data-so-escolhidos]')) filtrar(); });
  // Botões − e + ao lado da quantidade (link do orçamento, no celular)
  form.addEventListener('click', (e) => {
    const b = e.target.closest('[data-orc-mais], [data-orc-menos]');
    if (!b) return;
    const campo = b.parentElement.querySelector('.ped-qtd');
    campo.value = Math.max(0, (Number(campo.value) || 0) + (b.hasAttribute('data-orc-mais') ? 1 : -1)) || '';
    recalcular();
  });
  // Enter no campo de quantidade não envia o pedido sem querer
  form.addEventListener('keydown', (e) => { if (e.key === 'Enter' && e.target.matches('input:not([type=submit])')) e.preventDefault(); });
  form.addEventListener('submit', () => { form.querySelector('[data-enviar]').disabled = true; });
  recalcular();
}
