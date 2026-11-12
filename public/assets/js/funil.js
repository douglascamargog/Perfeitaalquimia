// =====================================================================
// Funil (kanban): arraste um card para outra coluna para mudar a etapa.
// =====================================================================
// Mensagens usam a função de carteira.js (carregado antes deste arquivo)
const avisar = (texto, tipo) => window.Carteira?.avisar(texto, tipo);

const BASE = document.querySelector('meta[name="base"]')?.content || '';
const CSRF = document.querySelector('meta[name="csrf"]')?.content || '';
const funil = document.querySelector('[data-funil]');
let arrastando = null;

if (funil) {
  funil.addEventListener('dragstart', (e) => {
    arrastando = e.target.closest('.funil-card');
    if (!arrastando) return;
    arrastando.classList.add('arrastando');
    e.dataTransfer.effectAllowed = 'move';
    e.dataTransfer.setData('text/plain', arrastando.dataset.cliente);
  });
  funil.addEventListener('dragend', () => {
    arrastando?.classList.remove('arrastando');
    funil.querySelectorAll('.sobre').forEach((c) => c.classList.remove('sobre'));
  });
  funil.addEventListener('dragover', (e) => {
    const alvo = e.target.closest('[data-soltar]');
    if (!alvo || !arrastando) return;
    e.preventDefault();
    funil.querySelectorAll('.sobre').forEach((c) => c !== alvo && c.classList.remove('sobre'));
    alvo.classList.add('sobre');
  });
  funil.addEventListener('drop', async (e) => {
    const alvo = e.target.closest('[data-soltar]');
    if (!alvo || !arrastando) return;
    e.preventDefault();
    alvo.classList.remove('sobre');
    const card = arrastando;
    const origem = card.closest('.funil-coluna');
    const destino = alvo.closest('.funil-coluna');
    if (origem === destino) return;

    // Move na tela já; se o servidor recusar, volta
    alvo.prepend(card);
    ajustarContagem(origem, -1);
    ajustarContagem(destino, +1);
    const corpo = new FormData();
    corpo.append('etapa_id', destino.dataset.etapa);
    corpo.append('_csrf', CSRF);
    try {
      // O funil da Cobrança usa outro endereço: data-url-etapa="/cobranca/{id}/etapa"
      const modelo = funil.dataset.urlEtapa || '/carteira/clientes/{id}/etapa';
      const r = await fetch(BASE + modelo.replace('{id}', card.dataset.cliente), { method: 'POST', body: corpo, headers: { Accept: 'application/json' } });
      const dados = await r.json();
      if (!r.ok || !dados.ok) throw new Error(dados.mensagem || 'Não foi possível mudar a etapa.');
      avisar(`Movido para "${destino.querySelector('h3').textContent}".`);
    } catch (erro) {
      origem.querySelector('[data-soltar]').prepend(card);
      ajustarContagem(origem, +1);
      ajustarContagem(destino, -1);
      avisar(erro.message, 'erro');
    }
  });
}

function ajustarContagem(coluna, delta) {
  const el = coluna.querySelector('[data-contagem]');
  const atual = Number(el.textContent.replace(/\./g, '')) || 0;
  el.textContent = Math.max(0, atual + delta).toLocaleString('pt-BR');
}
