// =====================================================================
// Início do Marketing: mini calendário (tocar no dia mostra posts e prazos)
// e janela "Nova tarefa".
// =====================================================================
const calendario = document.querySelector('[data-calendario]');
if (calendario) {
  calendario.addEventListener('click', (e) => {
    const dia = e.target.closest('[data-dia]');
    if (!dia) return;
    calendario.querySelectorAll('.mt-cal-dia.escolhido').forEach((d) => d.classList.remove('escolhido'));
    dia.classList.add('escolhido');
    let achou = false;
    calendario.querySelectorAll('[data-lista-dia]').forEach((l) => { l.hidden = l.dataset.listaDia !== dia.dataset.dia; achou ||= !l.hidden; });
    const dica = calendario.querySelector('[data-cal-dica]');
    dica.hidden = achou;
    if (!achou) { dica.hidden = false; dica.textContent = 'Nada neste dia.'; }
  });
}

const dialogo = document.querySelector('[data-dialogo-tarefa]');
document.querySelector('[data-nova-tarefa]')?.addEventListener('click', () => dialogo?.showModal());
dialogo?.querySelector('[data-fechar]')?.addEventListener('click', () => dialogo.close());
