// =====================================================================
// Continua no mesmo lugar da página depois de salvar.
//
// Quase toda ação (salvar, concluir, aprovar, filtrar) envia o formulário e o servidor devolve a MESMA página
// recarregada — e o navegador voltaria para o topo. Aqui guardamos a posição ao sair da página e,
// se a página que abriu é a mesma (mesmo endereço) e foi há poucos segundos, voltamos para onde a pessoa estava.
// Como o aviso de "salvo"/erro aparece no topo, ele é mostrado flutuando no canto da tela.
// =====================================================================
const CHAVE = 'one_rolagem';
const endereco = () => location.pathname + location.search;

// Ao sair da página (envio de formulário, clique, recarregar), guarda onde estava
window.addEventListener('pagehide', () => {
  try {
    sessionStorage.setItem(CHAVE, JSON.stringify({ endereco: endereco(), y: window.scrollY, quando: Date.now() }));
  } catch (e) { /* navegador sem armazenamento: segue normal */ }
});

function voltarParaOndeEstava() {
  let salvo = null;
  try { salvo = JSON.parse(sessionStorage.getItem(CHAVE) || 'null'); sessionStorage.removeItem(CHAVE); } catch (e) { return; }
  const navegacao = performance.getEntriesByType('navigation')[0]?.type;
  // Voltar/avançar do navegador já cuida da posição sozinho
  if (!salvo || navegacao === 'back_forward' || salvo.endereco !== endereco() || Date.now() - salvo.quando > 20000 || salvo.y < 80) return;
  window.scrollTo({ top: salvo.y, behavior: 'instant' });
  mostrarAvisosFlutuando();
}

// Os avisos do topo (sucesso/erro) aparecem também no canto, porque a pessoa não está mais no topo
function mostrarAvisosFlutuando() {
  const avisos = [...document.querySelectorAll('main > .aviso, .conteudo > .aviso, .cat-conteudo > .aviso')];
  if (!avisos.length) return;
  const caixa = document.createElement('div');
  caixa.className = 'avisos-flutuantes';
  caixa.setAttribute('role', 'status');
  avisos.forEach((a) => {
    // Cópia com classe própria (o aviso original continua no topo, como sempre)
    const copia = document.createElement('div');
    copia.className = 'aviso-flut ' + (a.classList.contains('erro') ? 'erro' : a.classList.contains('sucesso') ? 'sucesso' : 'atencao');
    copia.textContent = a.textContent.trim();
    const fechar = document.createElement('button');
    fechar.type = 'button'; fechar.className = 'avisos-fechar'; fechar.setAttribute('aria-label', 'Fechar aviso'); fechar.textContent = '✕';
    fechar.addEventListener('click', () => copia.remove());
    copia.append(fechar);
    caixa.append(copia);
    // sucesso some sozinho; erro fica até a pessoa fechar
    if (!a.classList.contains('erro')) setTimeout(() => copia.classList.add('saindo'), 4500);
    if (!a.classList.contains('erro')) setTimeout(() => copia.remove(), 5000);
  });
  document.body.append(caixa);
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', voltarParaOndeEstava);
} else {
  voltarParaOndeEstava();
}
