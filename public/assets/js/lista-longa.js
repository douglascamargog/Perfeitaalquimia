// =====================================================================
// Listas longas (tabelas e grades com muitas linhas): mostra só as primeiras N e um botão "Mostrar mais".
// Uso: <tbody data-lista-longa="25"> ou <div class="grade" data-lista-longa="24">. Carregado em todas as páginas.
// Sem JavaScript (ou na impressão) aparece tudo.
// =====================================================================
document.querySelectorAll('[data-lista-longa]').forEach((lista) => {
  const passo = Math.max(5, Number(lista.dataset.listaLonga) || 25);
  const itens = [...lista.children].filter((el) => !el.matches('[data-vazio], .vazio-linha'));
  if (itens.length <= passo + 5) return; // pouca coisa: mostra tudo
  let mostrando = passo;
  const aplicar = () => itens.forEach((el, i) => el.classList.toggle('ll-escondido', i >= mostrando));
  const barra = document.createElement('div');
  barra.className = 'll-barra';
  barra.innerHTML = '<span class="ll-info"></span><button type="button" class="botao contorno pequeno" data-mostrar-mais></button><button type="button" class="ll-tudo" data-mostrar-tudo>Mostrar tudo</button>';
  const info = barra.querySelector('.ll-info');
  const mais = barra.querySelector('[data-mostrar-mais]');
  const atualizar = () => {
    aplicar();
    const resta = itens.length - mostrando;
    info.textContent = `Mostrando ${Math.min(mostrando, itens.length)} de ${itens.length}`;
    mais.textContent = `Mostrar mais ${Math.min(passo, resta)}`;
    barra.hidden = resta <= 0;
  };
  mais.addEventListener('click', () => { mostrando += passo; atualizar(); });
  barra.querySelector('[data-mostrar-tudo]').addEventListener('click', () => { mostrando = itens.length; atualizar(); });
  // tabela: a barra vai depois da tabela (ou da caixa de rolagem); grade: depois da grade
  const ancora = lista.tagName === 'TBODY' ? (lista.closest('.tabela-rolagem') || lista.closest('table')) : lista;
  ancora.after(barra);
  // link para um item escondido (ex.: #produto-123 depois de salvar): mostra até ele
  const alvo = location.hash ? lista.querySelector(CSS.escape ? '#' + CSS.escape(location.hash.slice(1)) : location.hash) : null;
  if (alvo) {
    const i = itens.findIndex((el) => el === alvo || el.contains(alvo));
    if (i >= mostrando) mostrando = Math.ceil((i + 1) / passo) * passo;
  }
  atualizar();
  if (alvo) alvo.scrollIntoView({ block: 'center' });
});
