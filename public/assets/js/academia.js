// =====================================================================
// Academia: botão "Copiar texto" das falas e mensagens prontas.
// =====================================================================
document.addEventListener('click', async (evento) => {
  const botao = evento.target.closest('[data-copiar]');
  if (!botao) return;
  const texto = botao.closest('.mensagem-copiavel')?.querySelector('[data-texto]')?.textContent?.trim();
  if (!texto) return;
  let copiou = false;
  try {
    await navigator.clipboard.writeText(texto);
    copiou = true;
  } catch {
    // Navegadores antigos ou sem permissão: seleciona o texto para a pessoa copiar com Ctrl+C
    const area = document.createElement('textarea');
    area.value = texto;
    area.style.cssText = 'position:fixed;left:-9999px';
    document.body.append(area);
    area.select();
    copiou = document.execCommand('copy');
    area.remove();
  }
  const original = botao.textContent;
  botao.textContent = copiou ? 'Copiado ✓' : 'Selecione e copie';
  setTimeout(() => (botao.textContent = original), 1800);
});
