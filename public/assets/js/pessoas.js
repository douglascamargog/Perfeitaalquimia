// Perfil das pessoas: abrir o painel de elogio, ir para os recados e ordenar os blocos (Meu perfil › Personalizar).

const painel = document.querySelector('[data-painel-elogio]');
const abrirElogio = (ev) => {
  if (!painel) return;
  ev.preventDefault();
  painel.hidden = false;
  painel.scrollIntoView({ behavior: 'smooth', block: 'center' });
  const primeiro = painel.querySelector('input[type=radio]');
  if (primeiro) primeiro.focus();
};
document.querySelectorAll('[data-abrir-elogio]').forEach((a) => a.addEventListener('click', abrirElogio));
document.querySelector('[data-fechar-elogio]')?.addEventListener('click', () => { painel.hidden = true; });
if (location.hash === '#elogiar' && painel) painel.hidden = false;

document.querySelector('[data-ir-recado]')?.addEventListener('click', (ev) => {
  const campo = document.querySelector('[data-form-recado] textarea');
  if (!campo) return;
  ev.preventDefault();
  campo.scrollIntoView({ behavior: 'smooth', block: 'center' });
  setTimeout(() => campo.focus(), 300);
});

// Personalizar: botões ↑ ↓ trocam a ordem e renumeram os campos "ordem"
const lista = document.querySelector('[data-ordem-blocos]');
if (lista) {
  const renumerar = () => lista.querySelectorAll('li').forEach((li, i) => { li.querySelector('input[type=number]').value = i + 1; });
  lista.addEventListener('click', (ev) => {
    const botao = ev.target.closest('[data-mover]');
    if (!botao) return;
    const li = botao.closest('li');
    if (botao.dataset.mover === 'cima' && li.previousElementSibling) li.parentNode.insertBefore(li, li.previousElementSibling);
    if (botao.dataset.mover === 'baixo' && li.nextElementSibling) li.parentNode.insertBefore(li.nextElementSibling, li);
    renumerar();
    botao.focus();
  });
}

// Prévia da cor e da capa no formulário Personalizar
const form = document.querySelector('[data-personalizar]');
if (form) {
  const previa = form.querySelector('[data-previa-capa]');
  form.addEventListener('change', (ev) => {
    if (ev.target.name === 'capa_tema' && previa) previa.style.background = ev.target.dataset.fundo;
    if (ev.target.name === 'cor_perfil' || ev.target.name === 'cor_livre') {
      const cor = ev.target.value;
      if (ev.target.name === 'cor_livre') { const r = form.querySelector('input[name=cor_perfil][value=livre]'); if (r) { r.checked = true; r.value = cor; } }
      form.style.setProperty('--pe-cor', cor);
    }
  });
}
