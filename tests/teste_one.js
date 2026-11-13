// Teste automático (Playwright). Rodar todos: bash tests/rodar.sh  ·  só este: node tests/teste_one.js
const path = require('path');
const RECURSOS = path.join(__dirname, 'recursos');
const { chromium } = require('playwright');
const BASE = 'http://127.0.0.1:8000', DIR = process.argv[2] || path.join(__dirname, 'saida');
let falhas = 0;
const ok = (c, m) => { console.log((c ? 'OK   ' : 'FALHA') + ' ' + m); if (!c) falhas++; };
async function entrar(p, email) { await p.goto(BASE + '/entrar'); await p.fill('#email', email); await p.fill('#senha', 'Demo@2026'); await p.click('button[type=submit]'); }
(async () => {
  const b = await chromium.launch(); const p = await (await b.newContext({ viewport: { width: 1440, height: 950 } })).newPage();
  const erros = []; p.on('pageerror', e => erros.push(e.message)); p.on('response', r => r.status() >= 400 && r.status() !== 403 && erros.push(r.status() + ' ' + r.url()));
  await p.goto(BASE + '/entrar');
  ok((await p.title()).includes('One'), 'título com o nome One: ' + await p.title());
  ok(await p.locator('.one-boas-vindas img').count() === 1, 'One dá boas-vindas no login');
  await p.screenshot({ path: DIR + '/one-login.png' });
  for (const email of ['televendas1@demo.local', 'gestor@demo.local', 'reativacao@demo.local', 'admin@demo.local', 'producao@demo.local']) {
    await entrar(p, email);
    const falas = await p.locator('.b-sugestoes li').allInnerTexts();
    ok(falas.length >= 1, `${email}: One mostra ${falas.length} dica(s) no Início → ${falas.map(f => f.split('\n')[0].slice(0, 60)).join(' | ')}`);
    if (email.startsWith('televendas1')) await p.screenshot({ path: DIR + '/one-inicio.png' });
    await p.goto(BASE + (email.startsWith('produ') ? '/' : '/meu-mes'));
    await p.click('[data-one-abrir]');
    await p.waitForSelector('.one-painel .one-falas', { timeout: 5000 });
    const primeira = await p.locator('.one-painel .one-fala:not(.one-saudacao)').first().innerText();
    ok(primeira.length > 10, `${email}: painel do One abre (1ª dica: ${primeira.split('\n')[0].slice(0, 70)})`);
    if (email.startsWith('gestor')) await p.waitForTimeout(500), await p.screenshot({ path: DIR + '/one-painel.png' });
    await p.keyboard.press('Escape');
    ok(await p.locator('#one-painel').isHidden(), 'Esc fecha o painel');
    await p.click('form[action$="/sair"] button');
  }
  await entrar(p, 'televendas1@demo.local');
  await p.goto(BASE + '/usuarios');
  ok(await p.locator('.card-erro img').count() === 1, 'One (ops) aparece na tela de acesso negado');
  ok(erros.length === 0, 'sem erros ' + erros.join(' | '));
  const cel = await (await b.newContext({ viewport: { width: 390, height: 844 } })).newPage();
  await entrar(cel, 'televendas1@demo.local'); await cel.click('[data-one-abrir]'); await cel.waitForSelector('.one-painel .one-falas'); await cel.waitForTimeout(500);
  await cel.screenshot({ path: DIR + '/one-celular.png' });
  await b.close(); console.log(falhas ? `\n${falhas} FALHA(S)` : '\nTudo certo'); process.exit(falhas ? 1 : 0);
})();
