// Gestão › Carteiras: janela "Nova carteira" e marcar clientes para mover
document.querySelectorAll('[data-abrir]').forEach((b) => b.addEventListener('click', () => document.querySelector(b.dataset.abrir)?.showModal()));
document.querySelectorAll('dialog [data-fechar]').forEach((b) => b.addEventListener('click', () => b.closest('dialog').close()));

const form = document.querySelector('[data-mover]');
if (form) {
  const caixas = () => [...form.querySelectorAll('input[name="cliente[]"]')];
  const atualizar = () => {
    const n = caixas().filter((c) => c.checked).length;
    const qtd = form.querySelector('[data-qtd-marcados]');
    if (qtd) qtd.textContent = n;
    const botao = form.querySelector('[data-botao-mover]');
    if (botao) botao.disabled = n === 0;
  };
  form.querySelector('[data-todos]')?.addEventListener('change', (e) => { caixas().forEach((c) => { c.checked = e.target.checked; }); atualizar(); });
  form.addEventListener('change', atualizar);
}
