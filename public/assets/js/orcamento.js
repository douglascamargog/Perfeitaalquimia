// =====================================================================
// Página do orçamento (equipe): copiar o link, enviar pelo WhatsApp e baixar a IMAGEM do orçamento.
// A imagem é desenhada aqui no navegador (canvas), sem biblioteca: logo, cliente, produtos, total e validade.
// Cada envio fica registrado no histórico e na linha do tempo do cliente.
// =====================================================================
const caixa = document.querySelector('[data-orcamento-enviar]');
const CSRF = document.querySelector('meta[name="csrf"]')?.content || '';

function registrar(como) {
  const corpo = new FormData();
  corpo.append('_csrf', CSRF);
  corpo.append('como', como);
  return fetch(caixa.dataset.enviadoUrl, { method: 'POST', body: corpo, headers: { Accept: 'application/json', 'X-CSRF-Token': CSRF } }).catch(() => null);
}

function carregarImagem(src) {
  return new Promise((ok) => { const img = new Image(); img.onload = () => ok(img); img.onerror = () => ok(null); img.src = src; });
}

// Quebra o texto em linhas que cabem na largura
function linhas(ctx, texto, largura) {
  const palavras = String(texto).split(' ');
  const saida = [];
  let atual = '';
  palavras.forEach((p) => {
    const teste = atual ? atual + ' ' + p : p;
    if (ctx.measureText(teste).width > largura && atual) { saida.push(atual); atual = p; } else atual = teste;
  });
  if (atual) saida.push(atual);
  return saida;
}

/** Desenha o orçamento e devolve o canvas (1080 px de largura: fica nítido no WhatsApp) */
export async function desenhar(d, canvas) {
  const cores = getComputedStyle(document.documentElement);
  const verde = cores.getPropertyValue('--primaria-escura').trim() || '#0e3b2f';
  const lima = cores.getPropertyValue('--secundaria').trim() || '#caff4d';
  const L = 1080, M = 64;
  const ctx = canvas.getContext('2d');
  ctx.font = '600 30px Montserrat, Arial, sans-serif';
  // Altura: cabeçalho + uma ou duas linhas por produto + rodapé
  const alturas = d.itens.map((i) => Math.max(1, linhas(ctx, i.nome, 470).length) * 38 + (i.codigo ? 56 : 30));
  canvas.width = L;
  canvas.height = 420 + alturas.reduce((a, b) => a + b, 0) + 260;
  ctx.fillStyle = '#ffffff'; ctx.fillRect(0, 0, L, canvas.height);

  // Cabeçalho verde com o logo
  ctx.fillStyle = verde; ctx.fillRect(0, 0, L, 260);
  const logo = await carregarImagem(d.logo);
  if (logo) ctx.drawImage(logo, M, 50, 110, 110);
  ctx.fillStyle = '#ffffff';
  ctx.font = '800 46px Montserrat, Arial, sans-serif'; ctx.fillText(d.empresa, M + 140, 100);
  ctx.font = '600 28px Montserrat, Arial, sans-serif'; ctx.fillText(`Orçamento #${d.numero} · ${d.data}`, M + 140, 145);
  ctx.fillStyle = lima; ctx.font = '800 26px Montserrat, Arial, sans-serif';
  ctx.fillText(`VÁLIDO ATÉ ${d.validade}`, M + 140, 190);

  // Cliente
  let y = 320;
  ctx.fillStyle = '#1d2b26'; ctx.font = '800 38px Montserrat, Arial, sans-serif'; ctx.fillText(d.cliente, M, y);
  ctx.fillStyle = '#5d6b66'; ctx.font = '500 26px Montserrat, Arial, sans-serif';
  ctx.fillText(d.cidade + (d.vendedor ? ` · atendimento: ${d.vendedor}` : ''), M, y + 40);

  // Cabeçalho da tabela
  y += 90;
  ctx.fillStyle = '#eef5f1'; ctx.fillRect(M - 16, y - 34, L - 2 * M + 32, 50);
  ctx.fillStyle = verde; ctx.font = '800 22px Montserrat, Arial, sans-serif';
  ctx.fillText('PRODUTO', M, y); ctx.textAlign = 'right';
  ctx.fillText('QTD.', 650, y); ctx.fillText('UNIT.', 820, y); ctx.fillText('TOTAL', L - M, y); ctx.textAlign = 'left';
  y += 30;

  // Produtos
  d.itens.forEach((i, n) => {
    ctx.font = '600 30px Montserrat, Arial, sans-serif';
    const ls = linhas(ctx, i.nome, 470);
    const h = alturas[n];
    if (n % 2) { ctx.fillStyle = '#f8faf9'; ctx.fillRect(M - 16, y, L - 2 * M + 32, h); }
    ctx.fillStyle = '#1d2b26';
    ls.forEach((t, k) => ctx.fillText(t, M, y + 42 + k * 38));
    if (i.codigo) { ctx.fillStyle = '#8a9692'; ctx.font = '500 20px Montserrat, Arial, sans-serif'; ctx.fillText(i.codigo, M, y + 42 + ls.length * 38 - 6); }
    ctx.fillStyle = '#1d2b26'; ctx.font = '700 28px Montserrat, Arial, sans-serif'; ctx.textAlign = 'right';
    ctx.fillText(String(i.qtd), 650, y + 42); ctx.font = '500 24px Montserrat, Arial, sans-serif'; ctx.fillText(i.preco, 820, y + 42);
    ctx.font = '700 28px Montserrat, Arial, sans-serif'; ctx.fillText(i.total, L - M, y + 42); ctx.textAlign = 'left';
    y += h;
  });

  // Total
  y += 30;
  ctx.fillStyle = lima; ctx.fillRect(M - 16, y, L - 2 * M + 32, 110);
  ctx.fillStyle = verde; ctx.font = '700 30px Montserrat, Arial, sans-serif'; ctx.fillText('TOTAL', M + 10, y + 68);
  ctx.font = '800 52px Montserrat, Arial, sans-serif'; ctx.textAlign = 'right'; ctx.fillText(d.total, L - M - 10, y + 74); ctx.textAlign = 'left';
  y += 160;
  ctx.fillStyle = '#5d6b66'; ctx.font = '500 24px Montserrat, Arial, sans-serif';
  if (d.pagamento) { ctx.fillText(`Pagamento sugerido: ${d.pagamento}`, M, y); y += 36; }
  ctx.fillText('Preços da sua tabela. Sujeito à confirmação de estoque e à aprovação do pedido.', M, y);
  return canvas;
}

if (caixa) {
  const dados = JSON.parse(caixa.dataset.imagem);
  caixa.querySelector('[data-copiar]')?.addEventListener('click', async (e) => {
    const campo = caixa.querySelector('[data-link]');
    try { await navigator.clipboard.writeText(campo.value); } catch { campo.select(); document.execCommand('copy'); }
    e.target.textContent = 'Copiado ✓';
    registrar('copiar');
  });
  caixa.querySelector('[data-whatsapp]')?.addEventListener('click', () => registrar('whatsapp'));
  caixa.querySelectorAll('[data-baixar-imagem]').forEach((b) => b.addEventListener('click', async () => {
    b.disabled = true;
    const canvas = await desenhar(dados, document.querySelector('[data-canvas-orcamento]'));
    canvas.toBlob((blob) => {
      const a = document.createElement('a');
      a.href = URL.createObjectURL(blob);
      a.download = `orcamento-${dados.numero}.png`;
      document.body.appendChild(a); a.click(); a.remove();
      setTimeout(() => URL.revokeObjectURL(a.href), 2000);
      b.disabled = false;
    }, 'image/png');
    registrar('imagem');
  }));
}
