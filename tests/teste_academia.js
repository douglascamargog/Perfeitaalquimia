// Teste automático (Playwright). Rodar todos: bash tests/rodar.sh  ·  só este: node tests/teste_academia.js
// Biblioteca de produtos (antiga Academia): busca por tema, texto e categoria, todas as fichas e abas, celular.
const path = require('path');
const RECURSOS = path.join(__dirname, 'recursos');
const { chromium } = require('playwright');
const { execSync } = require('child_process');
const BASE = 'http://127.0.0.1:8000', DIR = process.argv[2] || path.join(__dirname, 'saida');
let falhas = 0;
const ok = (c, m) => { console.log((c ? 'OK   ' : 'FALHA') + ' ' + m); if (!c) falhas++; };
const sql = (q) => execSync(`mysql perfeita --default-character-set=utf8mb4 -N -e "${q}"`).toString().trim();
async function entrar(p, email) { await p.goto(BASE + '/entrar'); await p.fill('#email', email); await p.fill('#senha', 'Demo@2026'); await p.click('button[type=submit]'); }
(async () => {
  const b = await chromium.launch(); const c = await b.newContext({ viewport: { width: 1440, height: 950 }, permissions: ['clipboard-read', 'clipboard-write'] }); const p = await c.newPage();
  const erros = [], quebrados = [];
  p.on('pageerror', e => erros.push(e.message));
  p.on('response', r => r.status() >= 400 && !r.url().includes('/academia?') && quebrados.push(r.status() + ' ' + r.url()));
  // V13.0: a Academia virou Treinamento; aqui fica a Biblioteca de produtos (fichas para estudar)
  await p.goto(BASE + '/academia/produtos'); ok(p.url().endsWith('/entrar'), 'sem login vai para o login');
  const prod = await (await b.newContext()).newPage();
  await entrar(prod, 'producao@demo.local');
  await prod.goto(BASE + '/academia/produtos');
  ok(await prod.locator('.cartao-produto').count() > 0, 'a Biblioteca de produtos abre para todos os setores (ex.: Produção)');
  await entrar(p, 'televendas1@demo.local');
  // Produtos: busca por tema, por texto e por categoria
  await p.goto(BASE + '/academia/produtos?q=perder peso');
  const temaQtd = Number(sql("select count(*) from produtos where json_contains_path(ficha_json, 'one', '$.topics.emagrecimento')"));
  ok((await p.locator('.cartao-produto').count()) === Math.min(24, temaQtd) && (await p.content()).includes('Emagrecimento'), `"perder peso" vira o tema Emagrecimento (${temaQtd} produtos)`);
  await p.goto(BASE + '/academia/produtos?q=magnésio');
  ok(await p.locator('.cartao-produto').count() > 3, 'busca por "magnésio" encontra produtos');
  await p.goto(BASE + '/academia/produtos?categoria=Chás a granel');
  ok((await p.locator('.cartao-produto small').allInnerTexts()).every(t => /chás a granel/i.test(t)), 'filtro por apresentação');
  // Ficha: todas as abas
  for (const aba of ['', 'composicao', 'uso', 'atendimento']) {
    await p.goto(BASE + '/academia/produtos/magnesio' + (aba ? '?aba=' + aba : ''));
    ok((await p.locator('main').innerText()).length > 400, `ficha 8+ Magnésio, aba ${aba || 'conheça'}`);
  }
  const omega = sql("select slug from produtos where ficha_json like '%technicalTraining%'");
  await p.goto(BASE + '/academia/produtos/' + omega + '?aba=treinamento');
  ok(await p.locator('.capitulo').count() === 16, 'treinamento técnico do Ômega 3 com 16 capítulos');
  // Todas as fichas abrem e todas as fotos existem
  const slugs = sql('select slug from produtos where ativo = 1').split('\n');
  let fichasOk = 0;
  for (const s of slugs) { const r = await p.request.get(BASE + '/academia/produtos/' + s); if (r.ok() && (await r.text()).includes('Conheça o produto')) fichasOk++; }
  ok(fichasOk === slugs.length, `as ${slugs.length} fichas de produto abrem`);
  ok(quebrados.length === 0, 'nenhum arquivo quebrado ' + quebrados.slice(0, 3).join(' | '));
  ok(erros.length === 0, 'sem erros de JavaScript ' + erros.join('|'));
  // Celular
  const cel = await (await b.newContext({ viewport: { width: 390, height: 844 } })).newPage();
  await entrar(cel, 'televendas1@demo.local'); await cel.goto(BASE + '/academia/produtos/magnesio?aba=atendimento');
  await cel.screenshot({ path: DIR + '/academia-celular.png', fullPage: true });
  await b.close(); console.log(falhas ? `\n${falhas} FALHA(S)` : '\nTudo certo'); process.exit(falhas ? 1 : 0);
})();
