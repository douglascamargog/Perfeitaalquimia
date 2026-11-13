// =====================================================================
// Mensagens prontas: botões de variáveis e prévia ao vivo (mesma regra do servidor: variável sem valor some).
// =====================================================================
const form = document.querySelector('[data-form-mensagem]');
if (form) {
  const texto = form.querySelector('[data-texto]');
  const previa = document.querySelector('[data-previa]');
  const valores = JSON.parse(document.getElementById('valores-variaveis')?.textContent || '{}');
  const aplicar = (t) => t.replace(/\{[a-z_]+\}/g, (v) => valores[v] ?? '').replace(/[ \t]{2,}/g, ' ').replace(/\s+([,.!?)])/g, '$1').trim();
  const atualizar = () => { previa.textContent = aplicar(texto.value); };
  form.querySelectorAll('[data-variavel]').forEach((b) => b.addEventListener('click', () => {
    const v = b.dataset.variavel;
    const ini = texto.selectionStart ?? texto.value.length, fim = texto.selectionEnd ?? ini;
    texto.value = texto.value.slice(0, ini) + v + texto.value.slice(fim);
    texto.focus();
    texto.selectionStart = texto.selectionEnd = ini + v.length;
    atualizar();
  }));
  texto.addEventListener('input', atualizar);
  atualizar();
}
