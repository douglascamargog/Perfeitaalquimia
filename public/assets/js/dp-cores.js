// Desenvolvimento de Produto: escolha do tipo e das cores do mockup (pote/tampa) com o desenho mudando na hora.
document.querySelectorAll('[data-cores]').forEach((caixa) => {
  const form = caixa.closest('form');
  const corTampa = caixa.querySelector('input[name=cor_tampa]');
  const alvo = form.closest('[data-cores-alvo]') || form;
  const pintar = () => {
    const pote = caixa.querySelector('input[name=cor_embalagem]:checked');
    if (pote) alvo.style.setProperty('--cor-pote', pote.dataset.hex);
    alvo.style.setProperty('--cor-tampa', corTampa.value);
    caixa.querySelectorAll('.dp-amostra').forEach((b) => b.classList.toggle('ativa', b.dataset.tampa === corTampa.value.toLowerCase()));
  };
  const enviar = () => { if (form.hasAttribute('data-enviar-ao-mudar-form')) form.requestSubmit(); };
  caixa.querySelectorAll('.dp-amostra').forEach((b) => b.addEventListener('click', () => { corTampa.value = b.dataset.tampa; pintar(); enviar(); }));
  corTampa.addEventListener('change', () => { pintar(); enviar(); });
  caixa.querySelectorAll('input[name=cor_embalagem]').forEach((r) => r.addEventListener('change', () => { pintar(); enviar(); }));
  // Âmbar só existe no frasco
  form.querySelectorAll('[name=tipo]').forEach((t) => t.addEventListener('change', () => {
    const tipo = form.querySelector('[name=tipo]:checked, select[name=tipo]')?.value;
    caixa.querySelectorAll('[data-so-frasco="1"]').forEach((l) => {
      l.hidden = tipo !== 'frasco';
      if (l.hidden && l.querySelector('input').checked) caixa.querySelector('input[name=cor_embalagem][value=branco]').checked = true;
    });
    pintar();
  }));
  pintar();
});

// "Embalagem e cores": fecha ao clicar fora ou apertar Esc
document.querySelectorAll('.dp-personalizar').forEach((d) => {
  document.addEventListener('click', (ev) => { if (d.open && !d.contains(ev.target)) d.open = false; });
  document.addEventListener('keydown', (ev) => { if (ev.key === 'Escape') d.open = false; });
});
