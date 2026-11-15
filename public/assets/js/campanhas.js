// =====================================================================
// Comercial › Campanhas: linhas de faixa/produto (+ e ×), período pronto, público, cores e prévia ao vivo.
// =====================================================================
const form = document.querySelector('[data-cp-form]');
if (form) {
  const previa = document.querySelector('[data-previa]');

  // ---- Faixas e produtos: + linha / × tirar (renumera os índices dos nomes) ----
  const renumerar = (lista) => {
    lista.querySelectorAll('[data-linha]').forEach((linha, n) => {
      linha.querySelectorAll('[name]').forEach((campo) => { campo.name = campo.name.replace(/\[\d+\]/, `[${n}]`); });
    });
  };
  form.addEventListener('click', (ev) => {
    const mais = ev.target.closest('[data-mais]');
    if (mais) {
      const lista = form.querySelector(`[data-lista="${mais.dataset.mais}"]`);
      const nova = lista.querySelector('[data-linha]').cloneNode(true);
      nova.querySelectorAll('input, select').forEach((c) => {
        if (c.type === 'checkbox') c.checked = false;
        else if (c.tagName === 'SELECT') c.selectedIndex = 0;
        else c.value = '';
      });
      nova.querySelectorAll('img').forEach((i) => i.remove());
      lista.appendChild(nova);
      renumerar(lista);
      mostrarTipos();
      nova.querySelector('input, select').focus();
      return;
    }
    const tirar = ev.target.closest('[data-tirar]');
    if (tirar) {
      const lista = tirar.closest('[data-lista]');
      const linhas = lista.querySelectorAll('[data-linha]');
      if (linhas.length > 1) tirar.closest('[data-linha]').remove();
      else tirar.closest('[data-linha]').querySelectorAll('input, select').forEach((c) => { if (c.type === 'checkbox') c.checked = false; else if (c.type !== 'hidden') c.value = ''; });
      renumerar(lista);
      atualizarFaixas();
      return;
    }
    const periodo = ev.target.closest('[data-periodo]');
    if (periodo) {
      const [i, f] = periodo.dataset.periodo.split('|');
      form.inicio.value = i;
      form.fim.value = f;
    }
  });

  // ---- Faixa: mostra % ou brinde conforme o tipo ----
  const mostrarTipos = () => {
    form.querySelectorAll('.cp-faixa').forEach((f) => {
      const tipo = f.querySelector('[data-faixa-tipo]').value;
      f.querySelectorAll('[data-so]').forEach((c) => { c.hidden = c.dataset.so !== tipo; });
    });
  };
  mostrarTipos();

  // ---- Público: mostra as caixinhas do tipo escolhido ----
  const publico = form.querySelector('[data-publico]');
  publico?.addEventListener('change', () => {
    form.querySelectorAll('[data-publico-lista]').forEach((l) => {
      const ativo = l.dataset.publicoLista === publico.value;
      l.hidden = !ativo;
      l.querySelectorAll('input').forEach((i) => { i.disabled = !ativo; });
    });
  });

  // ---- Cores: combinações prontas, seletores e fundo claro/escuro ----
  const cores = form.querySelector('[data-cp-cores]');
  const corP = cores?.querySelector('[data-cor="p"]');
  const corD = cores?.querySelector('[data-cor="d"]');
  const campoP = cores?.querySelector('[data-cor-campo="p"]');
  const campoD = cores?.querySelector('[data-cor-campo="d"]');
  const pintar = () => {
    if (!previa || !cores) return;
    previa.style.setProperty('--p', corP.value);
    previa.style.setProperty('--d', corD.value);
    previa.classList.toggle('escuro', form.querySelector('[name="tema"]:checked')?.value === 'escuro');
  };
  cores?.addEventListener('click', (ev) => {
    const b = ev.target.closest('[data-pronta]');
    if (!b) return;
    corP.value = b.dataset.p || cores.dataset.padraoP;
    corD.value = b.dataset.d || cores.dataset.padraoD;
    campoP.value = b.dataset.p; // vazio = cor da empresa
    campoD.value = b.dataset.d;
    const tema = form.querySelector(`[name="tema"][value="${b.dataset.t}"]`);
    if (tema) tema.checked = true;
    pintar();
  });
  corP?.addEventListener('input', () => { campoP.value = corP.value; pintar(); });
  corD?.addEventListener('input', () => { campoD.value = corD.value; pintar(); });
  form.querySelectorAll('[name="tema"]').forEach((r) => r.addEventListener('change', pintar));

  // ---- Textos e banner na prévia ----
  form.querySelectorAll('[data-prev]').forEach((campo) => {
    campo.addEventListener('input', () => {
      const alvo = previa?.querySelector(`[data-prev-out="${campo.dataset.prev}"]`);
      if (!alvo) return;
      alvo.textContent = campo.value;
      if (campo.dataset.prev === 'selo') alvo.hidden = campo.value.trim() === '';
    });
  });
  form.querySelector('[data-prev-banner]')?.addEventListener('change', (ev) => {
    const arq = ev.target.files[0];
    const hero = previa?.querySelector('[data-previa-banner]');
    if (arq && hero) hero.style.setProperty('--banner', `url("${URL.createObjectURL(arq)}")`);
  });

  // ---- Faixas na prévia ----
  const brl = (n) => n.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
  const numero = (s) => { let t = String(s || '').trim().replace(/\s/g, ''); if (t.includes(',')) t = t.replace(/\./g, '').replace(',', '.'); const n = Number(t); return Number.isFinite(n) ? n : 0; };
  const atualizarFaixas = () => {
    const caixa = previa?.querySelector('[data-prev-faixas]');
    if (!caixa) return;
    const itens = [...form.querySelectorAll('.cp-faixa')].map((f) => {
      const valor = numero(f.querySelector('[data-faixa-valor]').value);
      const tipo = f.querySelector('[data-faixa-tipo]').value;
      const pct = f.querySelector('[name^="faixa_pct"]').value;
      const brinde = f.querySelector('[name^="faixa_brinde["]').value;
      const texto = tipo === 'desconto' ? `${pct || '?'}% de desconto` : tipo === 'brinde' ? `${brinde || 'brinde'}` : 'frete grátis';
      return valor > 0 ? { valor, texto } : null;
    }).filter(Boolean).sort((a, b) => a.valor - b.valor);
    caixa.replaceChildren(...itens.map((i) => {
      const s = document.createElement('span');
      const sm = document.createElement('small');
      sm.textContent = 'acima de ' + brl(i.valor);
      s.append(sm, i.texto);
      return s;
    }));
  };
  form.addEventListener('input', (ev) => { if (ev.target.closest('.cp-faixa')) { mostrarTipos(); atualizarFaixas(); } });
  form.addEventListener('change', (ev) => { if (ev.target.closest('.cp-faixa')) { mostrarTipos(); atualizarFaixas(); } });
}
