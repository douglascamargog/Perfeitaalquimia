import './manter-rolagem.js';
// =====================================================================
// Comportamentos gerais do sistema interno (carregado em todas as telas).
// =====================================================================

// Abre/fecha o menu lateral no celular
const menu = document.querySelector('.menu-lateral');
document.querySelector('.abrir-menu')?.addEventListener('click', () => menu?.classList.toggle('aberto'));

// Grupos do menu (setores e áreas): sanfona — abrir um grupo fecha os outros (V17.9, para o menu não ficar poluído).
// O grupo da página atual abre sozinho; sem ele, abre o último que a pessoa abriu neste navegador.
const lerAbertos = () => { try { return JSON.parse(localStorage.getItem('menu-abertos') || '[]'); } catch { return []; } };
const gravarAbertos = (lista) => { try { localStorage.setItem('menu-abertos', JSON.stringify(lista)); } catch { /* sem armazenamento: tudo bem */ } };
const secoesMenu = [...document.querySelectorAll('[data-menu-secao]')];
const marcarSecao = (secao, aberta) => { secao.classList.toggle('aberta', aberta); secao.querySelector('.menu-secao-botao')?.setAttribute('aria-expanded', String(aberta)); };
const secaoAtual = secoesMenu.find((s) => s.hasAttribute('data-atual'));
const lembrada = secoesMenu.find((s) => s.dataset.menuSecao === lerAbertos().slice(-1)[0]);
secoesMenu.forEach((s) => marcarSecao(s, s === (secaoAtual || lembrada)));
secoesMenu.forEach((secao) => {
  secao.querySelector('.menu-secao-botao')?.addEventListener('click', () => {
    const aberta = !secao.classList.contains('aberta');
    secoesMenu.forEach((s) => marcarSecao(s, aberta && s === secao));
    gravarAbertos(aberta ? [secao.dataset.menuSecao] : []);
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

// Menu › Comercial: escolher o setor leva para a mesma aba no setor escolhido
document.querySelector('[data-setor-escolha]')?.addEventListener('change', (e) => { window.location.href = e.target.value; });

// ---------------------------------------------------------------------
// Tema claro / escuro (V13.9): o botão 🌙/☀️ do topo troca na hora e guarda a escolha no login da pessoa.
// "auto" segue o aparelho; o clique sempre vai para o oposto do que está aparecendo agora.
// ---------------------------------------------------------------------
(() => {
  const html = document.documentElement;
  const base = document.querySelector('meta[name="base"]')?.content || '';
  const csrf = document.querySelector('meta[name="csrf"]')?.content || '';
  const escuroAgora = () => html.classList.contains('escuro');
  const salvar = (tema) => {
    html.dataset.tema = tema;
    window.oneAplicarTema?.();
    const corpo = new FormData(); corpo.append('tema', tema); corpo.append('_csrf', csrf);
    fetch(`${base}/meu-perfil/tema`, { method: 'POST', body: corpo, headers: { Accept: 'application/json', 'X-CSRF-Token': csrf } }).catch(() => {});
    document.querySelectorAll('[data-mp-tema] input[name="tema"]').forEach((r) => { r.checked = r.value === tema; });
  };
  document.addEventListener('click', (ev) => {
    if (ev.target.closest('[data-tema-alternar]')) salvar(escuroAgora() ? 'claro' : 'escuro');
  });
  // Meu perfil › Aparência: escolher o tema já mostra e salva
  document.addEventListener('change', (ev) => {
    const r = ev.target.closest('[data-mp-tema] input[name="tema"]');
    if (r) salvar(r.value);
  });
})();
