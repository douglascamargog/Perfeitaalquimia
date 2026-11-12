// =====================================================================
// Catálogo do cliente: + / − nas quantidades (salva sem recarregar),
// barra do pedido mínimo e confirmação antes de cancelar.
// =====================================================================
const BASE = document.querySelector('meta[name="base"]')?.content || '';
const CSRF = document.querySelector('meta[name="csrf"]')?.content || '';
const avisoFlutuante = document.querySelector('[data-aviso-flutuante]');

function avisar(texto, tipo = 'sucesso') {
  if (!avisoFlutuante) return;
  avisoFlutuante.textContent = texto;
  avisoFlutuante.className = 'aviso-flutuante visivel ' + tipo;
  clearTimeout(avisar.timer);
  avisar.timer = setTimeout(() => avisoFlutuante.classList.remove('visivel'), 2600);
}

async function salvar(caixa, quantidade) {
  const corpo = new FormData();
  corpo.append('produto_id', caixa.dataset.produto);
  corpo.append('quantidade', quantidade);
  corpo.append('_csrf', CSRF);
  const r = await fetch(`${BASE}/catalogo/carrinho/quantidade`, { method: 'POST', body: corpo, headers: { Accept: 'application/json' } });
  const dados = await r.json().catch(() => ({ ok: false, mensagem: 'Sem conexão. Tente de novo.' }));
  if (!dados.ok) throw new Error(dados.mensagem);
  return dados;
}

function atualizarResumo(d) {
  document.querySelectorAll('[data-qtd-carrinho], [data-qtd-barra]').forEach((el) => (el.textContent = d.quantidade));
  const barra = document.querySelector('[data-barra-carrinho]');
  if (barra) {
    barra.hidden = d.quantidade === 0;
    barra.querySelector('[data-subtotal]').textContent = d.subtotal;
    barra.querySelector('[data-falta]').textContent = d.falta > 0 ? d.falta_texto : '';
  }
}

let espera = null;
document.addEventListener('click', (evento) => {
  const botao = evento.target.closest('[data-mais], [data-menos]');
  if (!botao) return;
  const caixa = botao.closest('[data-produto]');
  const campo = caixa.querySelector('input');
  campo.value = Math.max(0, (Number(campo.value) || 0) + (botao.hasAttribute('data-mais') ? 1 : -1));
  campo.dispatchEvent(new Event('change', { bubbles: true }));
});

document.addEventListener('change', (evento) => {
  const caixa = evento.target.closest('[data-produto]');
  if (!caixa) return;
  clearTimeout(espera);
  // Espera um instante: vários cliques seguidos viram um envio só
  espera = setTimeout(async () => {
    const quantidade = Math.max(0, Math.min(9999, Number(evento.target.value) || 0));
    try {
      const d = await salvar(caixa, quantidade);
      atualizarResumo(d);
      if (caixa.hasAttribute('data-recarregar')) window.location.reload(); // no carrinho, recalcula totais e pagamento
      else avisar(quantidade ? 'Carrinho atualizado' : 'Removido do carrinho');
    } catch (erro) {
      avisar(erro.message, 'erro');
    }
  }, 350);
});

// Primeira carga: mostra a barra com o subtotal certo
const barra = document.querySelector('[data-barra-carrinho]');
if (barra && !barra.hidden) {
  const primeiro = document.querySelector('[data-produto]');
  if (primeiro) salvar(primeiro, primeiro.querySelector('input').value).then(atualizarResumo).catch(() => {});
}

// "Tem certeza?" (cancelar pedido)
document.addEventListener('submit', (evento) => {
  const mensagem = evento.target.dataset.confirmar;
  if (mensagem && !window.confirm(mensagem)) evento.preventDefault();
});
