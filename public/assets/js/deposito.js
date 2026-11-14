// Depósito › separar o pedido: leitor de código de barras (o leitor USB/bluetooth "digita" o EAN e aperta Enter),
// botão "tudo", lote único acompanhando a quantidade, "+ outro lote" e o quadro "se faltar produto".
const form = document.querySelector('[data-separar]');
if (form) {
  const linhas = [...form.querySelectorAll('tr[data-item]')];
  const leitor = form.querySelector('[data-leitor]');
  const msg = form.querySelector('[data-leitor-msg]');
  const falta = form.querySelector('[data-falta-caixa]');

  const atualizar = (tr) => {
    const qtd = tr.querySelector('[data-qtd]');
    const lotes = tr.querySelectorAll('[data-lote]');
    if (lotes.length === 1) lotes[0].querySelector('[data-lote-qtd]').value = qtd.value || 0; // um lote só: acompanha a quantidade
    tr.classList.toggle('completo', Number(qtd.value) >= Number(tr.dataset.pedidoQtd));
  };
  const verFalta = () => {
    const tem = linhas.some((tr) => Number(tr.querySelector('[data-qtd]').value) < Number(tr.dataset.pedidoQtd));
    falta.hidden = !tem;
  };
  linhas.forEach((tr) => {
    const qtd = tr.querySelector('[data-qtd]');
    qtd.addEventListener('input', () => { atualizar(tr); verFalta(); });
    tr.querySelector('[data-tudo]').addEventListener('click', () => { qtd.value = tr.dataset.pedidoQtd; atualizar(tr); verFalta(); });
    // Lote escolhido da lista sugerida preenche a validade
    tr.querySelectorAll('[data-lote-nome]').forEach((campo) => campo.addEventListener('change', () => preencherValidade(tr, campo)));
    tr.querySelector('[data-outro-lote]').addEventListener('click', () => {
      const caixa = tr.querySelector('[data-lotes]');
      const ultimo = caixa.lastElementChild;
      const novo = ultimo.cloneNode(true);
      const n = caixa.children.length;
      novo.querySelectorAll('input').forEach((i) => { i.name = i.name.replace(/\[lotes\]\[\d+\]/, `[lotes][${n}]`); i.value = ''; });
      novo.querySelector('[data-lote-nome]').addEventListener('change', (e) => preencherValidade(tr, e.target));
      caixa.append(novo);
      novo.querySelector('input').focus();
    });
    atualizar(tr);
  });
  verFalta();

  function preencherValidade(tr, campo) {
    const opcao = [...tr.querySelectorAll('datalist option')].find((o) => o.value === campo.value);
    const validade = campo.closest('[data-lote]').querySelector('[data-lote-validade]');
    if (opcao && opcao.dataset.validade && !validade.value) validade.value = opcao.dataset.validade;
  }

  // Leitor: Enter com o EAN soma 1 no item; não envia o formulário
  leitor?.addEventListener('keydown', (e) => {
    if (e.key !== 'Enter') return;
    e.preventDefault();
    const codigo = leitor.value.replace(/\D/g, '');
    leitor.value = '';
    if (!codigo) return;
    const tr = linhas.find((l) => l.dataset.ean && l.dataset.ean.replace(/\D/g, '') === codigo);
    if (!tr) {
      msg.textContent = `Código ${codigo} não é de nenhum item deste pedido.`;
      msg.className = 'dep-leitor-msg erro';
      return;
    }
    const qtd = tr.querySelector('[data-qtd]');
    const max = Number(tr.dataset.pedidoQtd);
    if (Number(qtd.value) >= max) {
      msg.textContent = `${tr.querySelector('strong').textContent}: já está completo (${max}).`;
      msg.className = 'dep-leitor-msg erro';
    } else {
      qtd.value = Number(qtd.value) + 1;
      msg.textContent = `${tr.querySelector('strong').textContent}: ${qtd.value} de ${max}.`;
      msg.className = 'dep-leitor-msg ok';
    }
    atualizar(tr);
    verFalta();
    tr.classList.remove('lido');
    void tr.offsetWidth;
    tr.classList.add('lido');
  });
}
