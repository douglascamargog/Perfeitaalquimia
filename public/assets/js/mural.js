// =====================================================================
// Mural de posts (Marketing): arrastar post para outro dia, "+ novo post",
// contador de caracteres da legenda e copiar legenda.
// A gaveta do post usa o carteira.js (abrirCliente / avisar).
// =====================================================================
const BASE = document.querySelector('meta[name="base"]')?.content || '';
const CSRF = document.querySelector('meta[name="csrf"]')?.content || '';
const avisar = (texto, tipo) => window.Carteira?.avisar(texto, tipo);
const mural = document.querySelector('[data-mural]');
let arrastando = null;

// Novo post (botão do topo ou "+" de um dia)
document.addEventListener('click', async (evento) => {
  const novo = evento.target.closest('[data-novo-post]');
  if (novo) {
    window.Carteira?.abrirCliente('novo', `/marketing/posts/novo?data=${encodeURIComponent(novo.dataset.novoPost)}`);
    return;
  }
  const copiar = evento.target.closest('[data-copiar-texto]');
  if (copiar) {
    try { await navigator.clipboard.writeText(copiar.dataset.copiarTexto); avisar('Legenda copiada. Agora é só colar no Instagram.'); }
    catch { window.prompt('Copie a legenda:', copiar.dataset.copiarTexto); }
  }
});

// Contador de caracteres da legenda (limite do Instagram)
document.addEventListener('input', (evento) => {
  const campo = evento.target;
  const contador = document.querySelector(`[data-contador="${campo.id}"]`);
  if (contador) atualizarContador(contador, campo);
});
function atualizarContador(contador, campo) {
  const limite = Number(contador.dataset.limite);
  const texto = `${campo.value.length.toLocaleString('pt-BR')} / ${limite.toLocaleString('pt-BR')}`;
  if (contador.textContent !== texto) contador.textContent = texto; // só muda se for diferente (evita repetição infinita)
  contador.style.color = campo.value.length > limite * 0.95 ? 'var(--perigo)' : '';
}
// A gaveta é carregada depois: quando o conteúdo DELA é trocado, atualiza o contador
const conteudoGaveta = document.querySelector('[data-gaveta-conteudo]');
if (conteudoGaveta) {
  new MutationObserver(() => {
    conteudoGaveta.querySelectorAll('[data-contador]').forEach((c) => {
      const campo = document.getElementById(c.dataset.contador);
      if (campo) atualizarContador(c, campo);
    });
  }).observe(conteudoGaveta, { childList: true }); // só os filhos diretos: a troca do conteúdo inteiro
}

// Arrastar e soltar entre dias
if (mural) {
  mural.addEventListener('dragstart', (e) => {
    arrastando = e.target.closest('.mk-post');
    if (!arrastando) return;
    arrastando.classList.add('arrastando');
    e.dataTransfer.effectAllowed = 'move';
    e.dataTransfer.setData('text/plain', arrastando.dataset.post);
  });
  mural.addEventListener('dragend', () => {
    arrastando?.classList.remove('arrastando');
    mural.querySelectorAll('.sobre').forEach((d) => d.classList.remove('sobre'));
  });
  mural.addEventListener('dragover', (e) => {
    const dia = e.target.closest('.mk-dia');
    if (!dia || !arrastando) return;
    e.preventDefault();
    mural.querySelectorAll('.sobre').forEach((d) => d !== dia && d.classList.remove('sobre'));
    dia.classList.add('sobre');
  });
  mural.addEventListener('drop', async (e) => {
    const dia = e.target.closest('.mk-dia');
    if (!dia || !arrastando) return;
    e.preventDefault();
    dia.classList.remove('sobre');
    const post = arrastando;
    const origem = post.closest('.mk-dia');
    if (origem === dia) return;
    dia.append(post); // move na tela já; se o servidor recusar, volta
    const corpo = new FormData();
    corpo.append('data', dia.dataset.dia);
    corpo.append('_csrf', CSRF);
    try {
      const r = await fetch(`${BASE}/marketing/posts/${post.dataset.post}/data`, { method: 'POST', body: corpo, headers: { Accept: 'application/json' } });
      const dados = await r.json();
      if (!r.ok || !dados.ok) throw new Error(dados.mensagem || 'Não foi possível mudar a data.');
      avisar(dados.mensagem);
    } catch (erro) {
      origem.append(post);
      avisar(erro.message, 'erro');
    }
  });
}
