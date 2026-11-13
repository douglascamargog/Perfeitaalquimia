// Produtos › Composição: acrescentar e tirar linhas da tabela nutricional
const corpo = document.querySelector('[data-nutri-linhas]');
if (corpo) {
  document.querySelector('[data-nutri-mais]')?.addEventListener('click', () => {
    const modelo = corpo.querySelector('tr');
    const nova = modelo.cloneNode(true);
    nova.querySelectorAll('input').forEach((i) => { i.value = ''; });
    corpo.append(nova);
    nova.querySelector('input')?.focus();
  });
  corpo.addEventListener('click', (e) => {
    const botao = e.target.closest('[data-nutri-tirar]');
    if (!botao) return;
    const linha = botao.closest('tr');
    // Sempre fica ao menos uma linha (vazia) para poder acrescentar de novo
    if (corpo.rows.length > 1) linha.remove();
    else linha.querySelectorAll('input').forEach((i) => { i.value = ''; });
  });
}
