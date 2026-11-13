// =====================================================================
// Prospecção › Encontrar empresas: marcar empresas e mostrar a barra "Enviar para o funil".
// =====================================================================
const form = document.querySelector('[data-form-envio]');
if (form) {
  const barra = form.querySelector('[data-barra-envio]');
  const qtd = form.querySelector('[data-qtd-marcadas]');
  const todas = form.querySelector('[data-marcar-todas]');
  const caixas = () => [...form.querySelectorAll('[data-marcar]')];
  const atualizar = () => {
    const marcadas = caixas().filter((c) => c.checked).length;
    qtd.textContent = marcadas;
    barra.hidden = marcadas === 0;
    if (todas) todas.checked = marcadas > 0 && marcadas === caixas().length;
  };
  todas?.addEventListener('change', () => { caixas().forEach((c) => (c.checked = todas.checked)); atualizar(); });
  form.addEventListener('change', (e) => { if (e.target.matches('[data-marcar]')) atualizar(); });
  form.addEventListener('submit', () => form.querySelector('button[type=submit]').disabled = true);
  atualizar();
}
