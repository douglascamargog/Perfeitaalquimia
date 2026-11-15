// =====================================================================
// Tabela nutricional (ficha técnica): prévia ao vivo, %VD automático e "Baixar imagem (PNG)".
// O %VD segue a mesma regra do servidor (TabelaNutricionalService::vd).
// =====================================================================
const editor = document.querySelector('[data-editor-nutricional]');
if (editor) {
  const form = editor.querySelector('[data-form-nutricional]');
  const previa = editor.querySelector('[data-tabela-nutricional]');
  const VDR = JSON.parse(editor.dataset.vdr || '{}');
  const semAcento = (s) => s.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().trim();
  const numero = (s) => { let t = String(s || '').trim().replace(/\s/g, ''); if (t.includes(',')) t = t.replace(/\./g, '').replace(',', '.'); const n = Number(t); return Number.isFinite(n) ? n : 0; };
  const FATOR = { g: 1e6, mg: 1e3, 'µg': 1, kcal: 1 };

  const vd = (l) => {
    if (l.vd.trim() !== '') return l.vd.trim().replace(/%$/, '');
    const ref = VDR[semAcento(l.nutriente)];
    const q = numero(l.quantidade);
    if (!ref || q <= 0) return '';
    const u = l.unidade.toLowerCase().replace('mcg', 'µg').replace(/^ug$/, 'µg').trim();
    if (!(u in FATOR) || (u === 'kcal') !== (ref[1] === 'kcal')) return '';
    return String(Math.round((q * FATOR[u]) / (ref[0] * FATOR[ref[1]]) * 100));
  };

  const dados = () => {
    const linhas = [...form.querySelectorAll('[data-tn-linha]')].map((d) => {
      const [n, u, q, v] = d.querySelectorAll('input');
      return { nutriente: n.value.trim(), unidade: u.value.trim(), quantidade: q.value.trim(), vd: v.value };
    }).filter((l) => l.nutriente);
    const porcao = form.porcao.value.trim();
    const coluna = form.porcao_coluna.value.trim() || ((porcao.match(/^\s*([\d.,]+\s*(g|ml|mg))/i) || [])[1]) || 'Porção';
    return { porcoes: form.porcoes_embalagem.value.trim(), porcao, coluna, nota: form.nota.value.trim(), linhas: linhas.map((l) => ({ ...l, vdFinal: vd(l) })) };
  };

  const escapar = (s) => s.replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
  const atualizar = () => {
    const d = dados();
    previa.querySelector('[data-tn="porcoes"]').textContent = d.porcoes;
    previa.querySelector('[data-tn="porcao"]').textContent = d.porcao;
    previa.querySelector('[data-tn="coluna"]').textContent = d.coluna;
    previa.querySelector('[data-tn="nota"]').textContent = d.nota;
    previa.querySelector('[data-tn="linhas"]').innerHTML = d.linhas.map((l) =>
      `<tr><td>${escapar(l.nutriente)}${l.unidade ? ' (' + escapar(l.unidade) + ')' : ''}</td><td>${escapar(l.quantidade)}</td><td>${escapar(l.vdFinal)}</td></tr>`).join('');
    // mostra no campo %VD o valor calculado (como dica)
    form.querySelectorAll('[data-tn-linha]').forEach((linha) => {
      const [n, u, q, v] = linha.querySelectorAll('input');
      v.placeholder = vd({ nutriente: n.value, unidade: u.value, quantidade: q.value, vd: '' }) || '—';
    });
  };
  form.addEventListener('input', atualizar);

  // + nutriente / tirar
  editor.querySelector('[data-tn-mais]')?.addEventListener('click', () => {
    const lista = form.querySelector('[data-tn-linhas]');
    const nova = lista.querySelector('[data-tn-linha]').cloneNode(true);
    nova.querySelectorAll('input').forEach((i) => { i.value = i.name === 'unidade[]' ? 'mg' : ''; });
    lista.appendChild(nova);
    nova.querySelector('input').focus();
    atualizar();
  });
  form.addEventListener('click', (ev) => {
    const b = ev.target.closest('[data-tn-tirar]');
    if (!b) return;
    const linhas = form.querySelectorAll('[data-tn-linha]');
    if (linhas.length > 1) b.closest('[data-tn-linha]').remove(); else b.closest('[data-tn-linha]').querySelectorAll('input').forEach((i) => { i.value = ''; });
    atualizar();
  });

  // Baixar imagem (PNG em alta resolução): desenha a tabela no canvas
  editor.querySelector('[data-tn-png]')?.addEventListener('click', () => {
    const d = dados();
    const K = 4; // escala (imagem nítida para a gráfica)
    const W = 420, m = 8, fonte = 'Arial, Helvetica, sans-serif';
    const c = document.createElement('canvas');
    const ctx = c.getContext('2d');
    const quebrar = (texto, tam, max) => {
      ctx.font = `${tam}px ${fonte}`;
      const linhas = []; let atual = '';
      texto.split(/\s+/).forEach((p) => { const t = atual ? atual + ' ' + p : p; if (ctx.measureText(t).width > max && atual) { linhas.push(atual); atual = p; } else atual = t; });
      if (atual) linhas.push(atual);
      return linhas;
    };
    const nota = quebrar(d.nota, 13.5, W - 2 * m);
    const rodape = quebrar('*Percentual de valores diários fornecidos pela porção', 13.5, W - 2 * m);
    const H = 34 + 50 + 26 + d.linhas.length * 26 + nota.length * 17 + 10 + rodape.length * 17 + 12;
    c.width = (W + 6) * K; c.height = (H + 6) * K;
    ctx.scale(K, K); ctx.translate(3, 3);
    ctx.fillStyle = '#fff'; ctx.fillRect(-3, -3, W + 6, H + 6);
    ctx.fillStyle = '#000'; ctx.strokeStyle = '#000';
    const linha = (x1, y1, x2, y2, e) => { ctx.lineWidth = e; ctx.beginPath(); ctx.moveTo(x1, y1); ctx.lineTo(x2, y2); ctx.stroke(); };
    const texto = (s, x, y, tam, negrito, alinha = 'left') => { ctx.font = `${negrito ? '900 ' : ''}${tam}px ${fonte}`; ctx.textAlign = alinha; ctx.fillText(s, x, y); };
    const c1 = W * 0.6, c2 = W * 0.8;
    let y = 0;
    texto('INFORMAÇÃO NUTRICIONAL', m, 27, 25, true); y = 34; linha(0, y, W, y, 2);
    texto('Porções por embalagem: ' + d.porcoes, m, y + 20, 16.5); texto('Porção: ' + d.porcao, m, y + 41, 16.5);
    y += 50; linha(0, y, W, y, 4);
    texto(d.coluna, c2 - 8, y + 19, 16.5, true, 'right'); texto('%VD*', W - 8, y + 19, 16.5, true, 'right');
    linha(c1, y, c1, y + 26, 1.5); linha(c2, y, c2, y + 26, 1.5);
    y += 26; linha(0, y, W, y, 1.5);
    d.linhas.forEach((l) => {
      texto(l.nutriente + (l.unidade ? ' (' + l.unidade + ')' : ''), m, y + 19, 16.5);
      texto(l.quantidade, c2 - 8, y + 19, 16.5, false, 'right'); texto(l.vdFinal, W - 8, y + 19, 16.5, false, 'right');
      linha(c1, y, c1, y + 26, 1.5); linha(c2, y, c2, y + 26, 1.5);
      y += 26; linha(0, y, W, y, 1.5);
    });
    nota.forEach((t) => { y += 17; texto(t, m, y, 13.5); });
    y += 10; linha(0, y, W, y, 1.5);
    rodape.forEach((t) => { y += 17; texto(t, m, y, 13.5); });
    ctx.lineWidth = 2.5; ctx.strokeRect(0, 0, W, H);
    const a = document.createElement('a');
    a.download = 'tabela-nutricional-' + semAcento(editor.dataset.nome || 'produto').replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '') + '.png';
    a.href = c.toDataURL('image/png');
    a.click();
  });

  atualizar();
}
