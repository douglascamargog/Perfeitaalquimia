// Teste automático (Playwright). Rodar todos: bash tests/rodar.sh  ·  só este: node tests/teste_academia.js
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
  await p.goto(BASE + '/academia'); ok(p.url().endsWith('/entrar'), 'sem login vai para o login');
  await entrar(p, 'televendas1@demo.local');
  await p.goto(BASE + '/academia');
  ok((await p.locator('.progresso-estudo').innerText()).includes('0 de 19'), 'progresso começa em 0 de 19 (10 módulos + 9 aulas do One)');
  // Trilha: todos os módulos abrem
  const modulos = JSON.parse(sql("select conteudo_json from academia_conteudo where chave='modules'").replace(/\\\\/g, '\\'));
  for (const m of modulos) {
    const r = await p.goto(BASE + '/academia/trilha/' + m.id);
    ok(r.status() === 200 && (await p.locator('main').innerText()).length > 600, `módulo "${m.title}" abre com conteúdo`);
  }
  await p.goto(BASE + '/academia/trilha/empresa');
  ok((await p.content()).includes('Fabricação de produtos para outras marcas'), 'página da empresa com o texto original');
  await p.goto(BASE + '/academia/trilha/midias-marketing');
  ok(await p.locator('.figura-aula img').count() === 3, 'mídias do marketing com as 3 imagens');
  await p.locator('[data-copiar]').first().click();
  await p.waitForFunction(() => document.querySelector('[data-copiar]').textContent.includes('Copiado'), null, { timeout: 3000 }).catch(() => {});
  ok((await p.locator('[data-copiar]').first().innerText()).includes('Copiado'), 'botão copiar mensagem');
  ok((await p.evaluate(() => navigator.clipboard.readText())).includes('fliphtml5'), 'mensagem copiada contém o link do catálogo');
  // Marcar como estudado
  await Promise.all([p.waitForNavigation(), p.click('.marcar-estudo button')]);
  ok(sql("select count(*) from academia_progresso ap join usuarios u on u.id=ap.usuario_id where u.email='televendas1@demo.local' and item='modulo:midias-marketing'") === '1', 'progresso salvo no servidor');
  ok((await p.locator('.progresso-estudo').innerText()).includes('1 de 19'), 'progresso atualizado (1 de 19)');
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
  const slugs = sql('select slug from produtos').split('\n');
  let fichasOk = 0;
  for (const s of slugs) { const r = await p.request.get(BASE + '/academia/produtos/' + s); if (r.ok() && (await r.text()).includes('Conheça o produto')) fichasOk++; }
  ok(fichasOk === slugs.length, `as ${slugs.length} fichas de produto abrem`);
  // Atendimento e Como usar o One
  await p.goto(BASE + '/academia/atendimento');
  ok(await p.locator('.mensagem-copiavel').count() === 7, 'guia de atendimento com as 7 falas');
  await p.goto(BASE + '/academia/sistema/pedido');
  ok((await p.locator('h2').first().innerText()).includes('Fazer um pedido') && await p.locator('.pratica').count() === 1, 'aula "Como usar o One" com exercício');
  ok(quebrados.length === 0, 'nenhum arquivo quebrado ' + quebrados.slice(0, 3).join(' | '));
  ok(erros.length === 0, 'sem erros de JavaScript ' + erros.join('|'));
  // Celular
  const cel = await (await b.newContext({ viewport: { width: 390, height: 844 } })).newPage();
  await entrar(cel, 'televendas1@demo.local'); await cel.goto(BASE + '/academia/produtos/magnesio?aba=atendimento');
  await cel.screenshot({ path: DIR + '/academia-celular.png', fullPage: true });
  await b.close(); console.log(falhas ? `\n${falhas} FALHA(S)` : '\nTudo certo'); process.exit(falhas ? 1 : 0);
})();
