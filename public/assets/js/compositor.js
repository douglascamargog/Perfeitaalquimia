// =====================================================================
// Caixa rápida da linha do tempo (gaveta e página do cliente):
//  - abas Anotação / Agendar / Contato;
//  - Ctrl+V cola o print de tela no campo de anexos (com miniatura), até 4 arquivos.
// O envio é do carteira.js (formulários data-ajax), que manda os arquivos junto.
// A gaveta é recarregada sem recarregar a página, por isso tudo usa delegação de eventos.
// =====================================================================
const MAXIMO = 4;

function caixaDe(el) { return el.closest('[data-compositor]'); }

// Junta arquivos no <input type=file> (colados + escolhidos)
function adicionar(caixa, arquivos) {
  const campo = caixa.querySelector('[data-cx-arquivos]');
  const dt = new DataTransfer();
  [...campo.files, ...arquivos].slice(0, MAXIMO).forEach((f) => dt.items.add(f));
  campo.files = dt.files;
  mostrarPrevias(caixa);
}

function mostrarPrevias(caixa) {
  const campo = caixa.querySelector('[data-cx-arquivos]');
  const lugar = caixa.querySelector('[data-cx-previas]');
  lugar.innerHTML = '';
  [...campo.files].forEach((f, i) => {
    const item = document.createElement('div');
    item.className = 'cx-previa';
    if (f.type.startsWith('image/')) {
      const img = document.createElement('img');
      img.src = URL.createObjectURL(f);
      img.alt = f.name;
      item.appendChild(img);
    } else {
      item.textContent = '📄 ' + f.name;
    }
    const tirar = document.createElement('button');
    tirar.type = 'button'; tirar.textContent = '×'; tirar.title = 'Tirar'; tirar.dataset.cxTirar = i;
    item.appendChild(tirar);
    lugar.appendChild(item);
  });
  const dica = caixa.querySelector('[data-cx-dica]');
  if (dica) dica.textContent = campo.files.length ? `${campo.files.length} arquivo(s) para salvar` : 'Dica: tire o print (Win+Shift+S) e cole aqui.';
}

document.addEventListener('click', (e) => {
  const aba = e.target.closest('[data-cx-aba]');
  if (aba) {
    const caixa = caixaDe(aba);
    caixa.querySelectorAll('[data-cx-aba]').forEach((b) => { b.classList.toggle('ativo', b === aba); b.setAttribute('aria-selected', b === aba); });
    caixa.querySelectorAll('[data-cx-painel]').forEach((p) => { p.hidden = p.dataset.cxPainel !== aba.dataset.cxAba; });
    caixa.querySelector(`[data-cx-painel="${aba.dataset.cxAba}"] textarea, [data-cx-painel="${aba.dataset.cxAba}"] input:not([type=hidden])`)?.focus();
    return;
  }
  const tirar = e.target.closest('[data-cx-tirar]');
  if (tirar) {
    const caixa = caixaDe(tirar);
    const campo = caixa.querySelector('[data-cx-arquivos]');
    const dt = new DataTransfer();
    [...campo.files].forEach((f, i) => { if (i !== Number(tirar.dataset.cxTirar)) dt.items.add(f); });
    campo.files = dt.files;
    mostrarPrevias(caixa);
  }
});

document.addEventListener('change', (e) => {
  if (e.target.matches('[data-cx-arquivos]')) mostrarPrevias(caixaDe(e.target));
});

// Colar print (Ctrl+V) no texto da anotação ou em qualquer lugar da caixa
document.addEventListener('paste', (e) => {
  const caixa = e.target.closest?.('[data-compositor]');
  if (!caixa) return;
  const arquivos = [...(e.clipboardData?.files || [])].filter((f) => f.type.startsWith('image/') || f.type === 'application/pdf');
  if (!arquivos.length) return;
  e.preventDefault();
  const agora = new Date();
  const nome = `print-${agora.toISOString().slice(0, 16).replace(/[:T]/g, '-')}`;
  adicionar(caixa, arquivos.map((f, i) => new File([f], f.name && f.name !== 'image.png' ? f.name : `${nome}${i ? '-' + i : ''}.png`, { type: f.type })));
  caixa.querySelector('[data-cx-aba="nota"]').click();
});
