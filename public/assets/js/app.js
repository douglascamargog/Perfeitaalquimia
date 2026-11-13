import './manter-rolagem.js';
// =====================================================================
// Comportamentos gerais do sistema interno (carregado em todas as telas).
// =====================================================================

// Abre/fecha o menu lateral no celular
const menu = document.querySelector('.menu-lateral');
document.querySelector('.abrir-menu')?.addEventListener('click', () => menu?.classList.toggle('aberto'));

// Grupos do menu (setores e áreas): abrem e fecham com um clique. O grupo da página atual abre sozinho;
// os que a pessoa abriu ficam lembrados neste navegador.
const lerAbertos = () => { try { return JSON.parse(localStorage.getItem('menu-abertos') || '[]'); } catch { return []; } };
const gravarAbertos = (lista) => { try { localStorage.setItem('menu-abertos', JSON.stringify(lista)); } catch { /* sem armazenamento: tudo bem */ } };
document.querySelectorAll('[data-menu-secao]').forEach((secao) => {
  const botao = secao.querySelector('.menu-secao-botao');
  const marcar = (aberta) => { secao.classList.toggle('aberta', aberta); botao.setAttribute('aria-expanded', String(aberta)); };
  if (!secao.hasAttribute('data-atual') && lerAbertos().includes(secao.dataset.menuSecao)) marcar(true);
  botao.addEventListener('click', () => {
    const aberta = !secao.classList.contains('aberta');
    marcar(aberta);
    const lista = lerAbertos().filter((c) => c !== secao.dataset.menuSecao);
    if (aberta) lista.push(secao.dataset.menuSecao);
    gravarAbertos(lista);
  });
});

// Fecha o menu ao clicar fora dele (celular)
document.addEventListener('click', (evento) => {
  if (menu?.classList.contains('aberto') && !menu.contains(evento.target) && !evento.target.closest('.abrir-menu')) {
    menu.classList.remove('aberto');
  }
});

// Botões/formulários com data-confirmar="mensagem" pedem confirmação antes de enviar
document.addEventListener('submit', (evento) => {
  const mensagem = evento.target.dataset.confirmar;
  if (mensagem && !window.confirm(mensagem)) {
    evento.preventDefault();
  }
});

// ---------------------------------------------------------------------
// One, o ajudante: abre o painel e carrega as falas (só na primeira vez)
// ---------------------------------------------------------------------
const oneBotao = document.querySelector('[data-one-abrir]');
const onePainel = document.getElementById('one-painel');
let oneCarregado = false;

async function abrirOne() {
  onePainel.hidden = false;
  oneBotao.setAttribute('aria-expanded', 'true');
  if (oneCarregado) return;
  const base = document.querySelector('meta[name="base"]')?.content || '';
  try {
    const resposta = await fetch(`${base}/one/falas?pagina=${encodeURIComponent(location.pathname.slice(base.length) || '/')}`);
    if (!resposta.ok) throw new Error();
    document.querySelector('[data-one-corpo]').innerHTML = await resposta.text();
    oneCarregado = true;
  } catch {
    document.querySelector('[data-one-corpo]').innerHTML = '<p class="one-carregando">Ops! Não consegui buscar suas informações agora. Tente de novo em instantes.</p>';
  }
}
function fecharOne() {
  onePainel.hidden = true;
  oneBotao.setAttribute('aria-expanded', 'false');
}
oneBotao?.addEventListener('click', () => (onePainel.hidden ? abrirOne() : fecharOne()));
document.querySelector('[data-one-fechar]')?.addEventListener('click', fecharOne);
document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && onePainel && !onePainel.hidden) fecharOne(); });
