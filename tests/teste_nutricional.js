// Teste automático (Playwright). Rodar todos: bash tests/rodar.sh  ·  só este: node tests/teste_nutricional.js
// Tabela nutricional (modelo Anvisa) na ficha técnica: salvar, %VD automático, prévia ao vivo, PNG, PDF; ficha técnica imprimível; acessos.
const { chromium } = require('playwright');
const { execSync } = require('child_process');
const BASE = 'http://127.0.0.1:8000';
let falhas = 0; const ok = (c, m) => { console.log((c ? 'OK   ' : 'FALHA') + ' ' + m); if (!c) falhas++; };
const sql = (q) => execSync(`mysql perfeita --default-character-set=utf8mb4 -N -e "${q.replace(/"/g, '\\"')}"`).toString().trim();

(async () => {
  const b = await chromium.launch();
  const erros = [];
  const como = async (email) => {
    const ctx = await b.newContext({ viewport: { width: 1400, height: 900 }, acceptDownloads: true }); const p = await ctx.newPage();
    p.on('pageerror', (e) => erros.push(e.message)); await p.route(/open-meteo/, (r) => r.fulfill({ body: '{}' }));
    await p.goto(BASE + '/entrar'); await p.fill('#email', email); await p.fill('#senha', 'Demo@2026'); await p.click('button[type=submit]'); await p.waitForLoadState();
    if (await p.locator('[data-novidades-ok]').count()) { await p.click('[data-novidades-ok]'); await p.waitForLoadState(); }
    return { p, ctx, csrf: await p.evaluate(() => document.querySelector('meta[name=csrf]').content) };
  };
  ok(Number(sql('select count(*) from produto_nutricional')) > 0, 'tabelas que já estavam nos produtos foram trazidas para a ficha técnica');
  const prod = sql('select produto_id from ficha_tecnica limit 1');
  const t = await como('tecnica@demo.local');
  await t.p.goto(BASE + `/producao/fichas/${prod}#tabela-nutricional`);
  await t.p.fill('#tn-porcoes', '30'); await t.p.fill('#tn-porcao', '1,1 g (2 cápsulas)'); await t.p.fill('#tn-coluna', '1,1 g');
  const linhas = t.p.locator('[data-tn-linha]');
  while (await linhas.count() > 1) await linhas.last().locator('[data-tn-tirar]').click();
  await linhas.first().locator('input[name="nutriente[]"]').fill('Magnésio'); await linhas.first().locator('input[name="unidade[]"]').fill('mg');
  await linhas.first().locator('input[name="quantidade[]"]').fill('111'); await linhas.first().locator('input[name="vd[]"]').fill('');
  await t.p.click('[data-tn-mais]');
  await linhas.nth(1).locator('input[name="nutriente[]"]').fill('L-Treonina'); await linhas.nth(1).locator('input[name="quantidade[]"]').fill('316');
  const previa = await t.p.locator('[data-tabela-nutricional]').innerText();
  ok(previa.includes('Magnésio (mg)') && /111\s*26/.test(previa.replace(/\n/g, ' ')), 'prévia ao vivo com %VD calculado (111 mg de magnésio = 26%)');
  ok(previa.includes('Porção: 1,1 g (2 cápsulas)') && previa.includes('INFORMAÇÃO NUTRICIONAL'), 'prévia no modelo da Anvisa');
  await Promise.all([t.p.waitForNavigation(), t.p.click('[data-form-nutricional] button[type=submit]')]);
  const salvo = JSON.parse(sql(`select linhas from produto_nutricional where produto_id=${prod}`));
  ok(salvo.length === 2 && salvo[0].nutriente === 'Magnésio' && salvo[1].nutriente === 'L-Treonina', 'tabela salva');
  // L-Treonina não tem VDR: %VD vazio
  ok((await t.p.locator('[data-tabela-nutricional] tbody tr').nth(1).innerText()).trim().endsWith('316'), 'nutriente sem valor de referência fica sem %VD');
  const [dl] = await Promise.all([t.p.waitForEvent('download'), t.p.click('[data-tn-png]')]);
  ok(/\.png$/.test(dl.suggestedFilename()), 'baixar imagem (PNG): ' + dl.suggestedFilename());
  const pdf = await t.p.request.get(BASE + `/producao/fichas/${prod}/nutricional/pdf`);
  const corpo = (await pdf.body()).toString('latin1');
  ok(pdf.status() === 200 && pdf.headers()['content-type'] === 'application/pdf' && corpo.startsWith('%PDF') && corpo.includes('Magn') && corpo.includes('(26)'), 'baixar PDF com a tabela (26% de VD)');
  // Ficha técnica para imprimir
  const imp = await t.ctx.newPage(); await imp.addInitScript(() => { window.print = () => {}; });
  await imp.goto(BASE + `/producao/fichas/${prod}/imprimir`);
  const txt = await imp.locator('.ft-folha').innerText();
  ok(txt.includes('FICHA TÉCNICA DO PRODUTO') && txt.includes('Composição') && txt.includes('INFORMAÇÃO NUTRICIONAL') && txt.includes('Responsável Técnica'), 'ficha técnica imprimível com composição, tabela nutricional e assinatura');
  // Acessos: Produção vê e baixa, mas não altera
  const pr = await como('producao@demo.local');
  await pr.p.goto(BASE + `/producao/fichas/${prod}`);
  ok(await pr.p.locator('[data-form-nutricional] button[type=submit]').count() === 0 && await pr.p.locator('[data-tn-pdf]').count() === 1, 'Produção vê e baixa a tabela, sem editar');
  ok((await pr.p.request.post(BASE + `/producao/fichas/${prod}/nutricional`, { form: { _csrf: pr.csrf, porcao: 'x' } })).status() === 403, 'Produção não altera a tabela');
  ok(erros.length === 0, 'nenhum erro de JavaScript' + (erros.length ? ': ' + erros.join(' / ') : ''));
  await b.close();
  console.log(falhas ? `\n${falhas} FALHA(S)` : '\nTudo certo.');
  process.exit(falhas ? 1 : 0);
})();
