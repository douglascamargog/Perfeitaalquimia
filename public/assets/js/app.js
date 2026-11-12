// =====================================================================
// Comportamentos gerais do sistema interno (carregado em todas as telas).
// =====================================================================

// Abre/fecha o menu lateral no celular
const menu = document.querySelector('.menu-lateral');
document.querySelector('.abrir-menu')?.addEventListener('click', () => menu?.classList.toggle('aberto'));

// Fecha o menu ao clicar fora dele (celular)
document.addEventListener('click', (evento) => {
  if (menu?.classList.contains('aberto') && !menu.contains(evento.target) && !evento.target.closest('.abrir-menu')) {
    menu.classList.remove('aberto');
  }
});

// Botões/formulários com data-confirmar="mensagem" pedem confirmação antes de enviar
document.addEventListener('submit', (evento) => {
  const mensagem = evento.target.dataset.confirmar;
  if (mensagem && !window.confirm(mensagem)) {
    evento.preventDefault();
  }
});
