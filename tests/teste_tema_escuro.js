// Teste automático (Playwright). Rodar todos: bash tests/rodar.sh  ·  só este: node tests/teste_tema_escuro.js
// Modo escuro (V13.9): botão sol/lua troca na hora e fica salvo; Meu perfil › Aparência (claro/escuro/automático);
// automático segue o aparelho; telas sem blocos claros sobrando; impressão continua clara; catálogo do cliente não muda.
const { chromium } = require('playwright');
const { execSync } = require('child_process');
const BASE = 'http://127.0.0.1:8000';
const SAIDA = process.argv[2] || 'tests/saida';
let falhas = 0; const ok = (c, m) => { console.log((c ? 'OK   ' : 'FALHA') + ' ' + m); if (!c) falhas++; };
const sql = (q) => execSync(`mysql perfeita --default-character-set=utf8mb4 -N -e "${q.replace(/"/g, '\\"')}"`).toString().trim();

async function entrar(b, email, opcoes = {}) {
  const p = await (await b.newContext({ viewport: { width: 1360, height: 860 }, ...opcoes })).newPage();
  p.erros = []; p.on('pageerror', (e) => p.erros.push(e.message));
  await p.route(/open-meteo/, (r) => r.fulfill({ body: '{}' }));
  await p.goto(BASE + '/entrar'); await p.fill('#email', email); await p.fill('#senha', 'Demo@2026'); await p.click('button[type=submit]'); await p.waitForLoadState();
  if (await p.locator('[data-novidades-ok]').count()) { await p.click('[data-novidades-ok]'); await p.waitForLoadState(); }
  return p;
}
// cor do cartão no tema atual (a variável --card aplicada num elemento de teste)
const luzCartao = (p) => p.evaluate(() => {
  const d = document.createElement('div'); d.style.background = 'var(--card)'; document.body.append(d);
  const c = getComputedStyle(d).backgroundColor.match(/\d+/g).map(Number); d.remove();
  return (0.299 * c[0] + 0.587 * c[1] + 0.114 * c[2]) / 255;
});
const escuro = (p) => p.evaluate(() => document.documentElement.classList.contains('escuro'));
const luzDe = (p, sel) => p.evaluate((s) => {
  const c = getComputedStyle(document.querySelector(s)).backgroundColor.match(/\d+/g).map(Number);
  return (0.299 * c[0] + 0.587 * c[1] + 0.114 * c[2]) / 255;
}, sel);
// blocos grandes com fundo claro (o que ficou sem versão escura)
const claros = (p) => p.evaluate(() => {
  const lum = (c) => { const m = c.match(/[\d.]+/g); if (!m) return 0; const [r, g, b, a = 1] = m.map(Number); return a < 0.5 ? 0 : (0.299 * r + 0.587 * g + 0.114 * b) / 255; };
  const achados = [];
  document.querySelectorAll('main *').forEach((e) => {
    const r = e.getBoundingClientRect();
    if (r.width < 60 || r.height < 24 || e.tagName === 'IMG' || e.closest('.tr-print, .empresa-logo, [class*="logo"], .cartao-produto .foto, figure')) return;
    if (lum(getComputedStyle(e).backgroundColor) > 0.85) achados.push(e.tagName.toLowerCase() + '.' + String(e.className).split(' ')[0]);
  });
  return [...new Set(achados)];
});

(async () => {
  const b = await chromium.launch();
  ok(sql("select count(*) from usuarios where tema <> 'claro'") === '0', 'todo mundo começa no tema claro');

  const p = await entrar(b, 'televendas1@demo.local');
  ok(!(await escuro(p)) && await luzCartao(p) > 0.85, 'tema claro: cartões brancos');
  await p.click('[data-tema-alternar]');
  ok(await escuro(p) && await luzCartao(p) < 0.25, 'botão 🌙 do topo: fica escuro na hora, sem recarregar');
  await p.waitForTimeout(500);
  ok(sql("select tema from usuarios where email='televendas1@demo.local'") === 'escuro', 'a escolha fica salva no login da pessoa');
  await p.reload();
  ok(await escuro(p), 'recarregou: continua escuro');
  const texto = await p.evaluate(() => { const c = getComputedStyle(document.body).color.match(/\d+/g).map(Number); return (0.299 * c[0] + 0.587 * c[1] + 0.114 * c[2]) / 255; });
  ok(texto > 0.75, 'no escuro, o texto fica claro');
  await p.screenshot({ path: SAIDA + '/tema-escuro-inicio.png' });

  // Telas principais sem blocos claros sobrando
  const telas = ['/', '/carteira', '/carteira/lista', '/agenda', '/meu-mes', '/pedidos', '/pedidos/novo', '/orcamentos', '/recompra', '/treinamento', '/academia/produtos', '/pessoas', '/meu-perfil'];
  const sobras = {};
  for (const t of telas) { await p.goto(BASE + t); await p.waitForTimeout(300); const c = await claros(p); if (c.length) sobras[t] = c; }
  ok(Object.keys(sobras).length === 0, 'telas da vendedora sem blocos claros no escuro' + (Object.keys(sobras).length ? ': ' + JSON.stringify(sobras).slice(0, 300) : ''));

  // Impressão continua clara
  await p.goto(BASE + '/carteira');
  await p.emulateMedia({ media: 'print' });
  ok(await luzCartao(p) > 0.85, 'na impressão o tema volta a ser claro');
  await p.emulateMedia({ media: 'screen' });

  // Meu perfil: Claro / Escuro / Automático
  await p.goto(BASE + '/meu-perfil');
  await p.check('[data-mp-tema] input[value="claro"]');
  await p.waitForTimeout(400);
  ok(!(await escuro(p)) && sql("select tema from usuarios where email='televendas1@demo.local'") === 'claro', 'Meu perfil › Aparência: "Claro" muda na hora e salva');

  // Automático segue o aparelho
  sql("update usuarios set tema='auto' where email='televendas2@demo.local'");
  const noite = await entrar(b, 'televendas2@demo.local', { colorScheme: 'dark' });
  ok(await escuro(noite), 'automático com o aparelho no modo escuro: escuro');
  const dia = await entrar(b, 'televendas2@demo.local', { colorScheme: 'light' });
  ok(!(await escuro(dia)), 'automático com o aparelho no modo claro: claro');
  await noite.click('[data-tema-alternar]');
  ok(!(await escuro(noite)), 'no automático, o botão vai para o oposto do que está aparecendo');

  // Admin: outras áreas
  sql("update usuarios set tema='escuro' where email='admin@demo.local'");
  const a = await entrar(b, 'admin@demo.local');
  const sobrasAdm = {};
  for (const t of ['/producao', '/producao/ordens', '/deposito', '/compras', '/financeiro/compras', '/aprovacao', '/cobranca', '/marketing/inicio', '/marketing', '/campanhas', '/painel', '/equipe', '/configuracoes', '/usuarios', '/administracao', '/treinamento/editar']) {
    await a.goto(BASE + t); await a.waitForTimeout(300); const c = await claros(a); if (c.length) sobrasAdm[t] = c;
  }
  ok(Object.keys(sobrasAdm).length === 0, 'Fábrica, Financeiro, Marketing e Administração sem blocos claros no escuro' + (Object.keys(sobrasAdm).length ? ': ' + JSON.stringify(sobrasAdm).slice(0, 400) : ''));

  // Catálogo do cliente não muda
  await a.goto(BASE + '/catalogo');
  ok(!(await escuro(a)), 'o catálogo do cliente continua com o visual dele (sem tema escuro)');

  // Celular
  const cel = await entrar(b, 'admin@demo.local', { viewport: { width: 390, height: 800 } });
  ok(await escuro(cel) && await cel.locator('[data-tema-alternar]').isVisible(), 'celular: escuro e o botão sol/lua aparece');

  const r = await p.request.post(BASE + '/meu-perfil/tema', { form: { tema: 'roxo', _csrf: await p.locator('meta[name=csrf]').getAttribute('content') }, headers: { Accept: 'application/json' } });
  ok(r.status() === 422, 'tema inválido é recusado');
  const erros = [p, noite, dia, a, cel].flatMap((x) => x.erros);
  ok(erros.length === 0, 'sem erros de JavaScript' + (erros[0] ? ': ' + erros[0] : ''));
  await b.close();
  process.exit(falhas ? 1 : 0);
})();
