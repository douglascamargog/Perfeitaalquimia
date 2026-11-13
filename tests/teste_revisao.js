// Teste automático (Playwright). Rodar todos: bash tests/rodar.sh  ·  só este: node tests/teste_revisao.js
const path = require('path');
const RECURSOS = path.join(__dirname, 'recursos');
const { chromium } = require('playwright');
const BASE = 'http://127.0.0.1:8000', DIR = process.argv[2] || path.join(__dirname, 'saida');
const perfis = (process.argv[3] || 'admin@demo.local,gestor@demo.local,televendas1@demo.local,externo1@demo.local,financeiro@demo.local,marketing@demo.local,producao@demo.local').split(',');
let falhas = 0; const ok = (c, m) => { if (!c) { console.log('FALHA ' + m); falhas++; } };
(async () => {
  const b = await chromium.launch();
  let paginas = 0;
  for (const email of perfis) {
    for (const [nome, vp] of [['pc', { width: 1366, height: 900 }], ['cel', { width: 390, height: 844 }]]) {
      const p = await (await b.newContext({ viewport: vp })).newPage();
      const erros = []; p.on('pageerror', e => erros.push(e.message));
      await p.route('https://cdn.jsdelivr.net/npm/chart.js@4.4.1/**', r => r.fulfill({ path: RECURSOS + '/chart.umd.js', contentType: 'text/javascript' }));
      await p.route(/maplibre|open-meteo|tiles|openfreemap|cartocdn/, r => r.fulfill({ body: '' }));
      await p.goto(BASE + '/entrar'); await p.fill('#email', email); await p.fill('#senha', 'Demo@2026'); await p.click('button[type=submit]'); await p.waitForLoadState();
      const links = await p.$$eval('.menu-lateral nav a', as => as.map(a => a.getAttribute('href')));
      for (const href of ['/', ...links.map(h => h.replace(/^https?:\/\/[^/]+/, ''))]) {
        if (href === '/app') continue; // app do externo tem teste próprio (PWA)
        const r = await p.goto(BASE + href); await p.waitForTimeout(200);
        const html = await p.content();
        paginas++;
        ok(r.status() === 200, `${email} ${nome} ${href}: HTTP ${r.status()}`);
        ok(!/<b>(Warning|Notice|Deprecated|Fatal error)<\/b>/.test(html), `${email} ${nome} ${href}: aviso do PHP`);
        ok(!html.includes('Black Friday 2026'), `${email} ${nome} ${href}: ainda fala em Black Friday 2026`);
        if (nome === 'cel') ok(await p.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth + 2), `${email} ${nome} ${href}: rolagem lateral (${await p.evaluate(() => document.documentElement.scrollWidth)}px)`);
        if (erros.length) { ok(false, `${email} ${nome} ${href}: JS ${erros.join(' / ')}`); erros.length = 0; }
      }
      await p.context().close();
    }
  }
  await b.close();
  console.log(`${falhas ? 'FALHA' : 'OK   '} ${paginas} páginas visitadas · ${falhas ? falhas + ' falha(s)' : 'tudo certo'}`);
  process.exit(falhas ? 1 : 0);
})();
