// Página do cliente: "Ver mais" das listas e filtros da linha do tempo.

// "Ver mais": mostra os itens escondidos (data-a-mais) da lista indicada
document.querySelectorAll('[data-ver-mais]').forEach((botao) => {
  botao.addEventListener('click', () => {
    document.querySelectorAll(botao.dataset.verMais + ' [data-a-mais]').forEach((li) => { li.hidden = false; });
    botao.remove();
  });
});

// Filtros da linha do tempo: Tudo, Contatos, Anotações, Pedidos...
const filtros = document.querySelector('[data-filtro-tempo]');
if (filtros) {
  const itens = () => document.querySelectorAll('.cl-linha-tempo > li');
  filtros.addEventListener('click', (ev) => {
    const botao = ev.target.closest('button[data-grupo]');
    if (!botao) return;
    filtros.querySelectorAll('button').forEach((b) => b.classList.toggle('ativo', b === botao));
    const grupo = botao.dataset.grupo;
    itens().forEach((li) => { li.hidden = grupo !== '' && li.dataset.grupo !== grupo; });
  });
}
