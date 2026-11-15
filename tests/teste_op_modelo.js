// Teste automático (Playwright). Rodar todos: bash tests/rodar.sh  ·  só este: node tests/teste_op_modelo.js
// OP no modelo PLA08.1: lotes de MP sugeridos (vence primeiro) e escolha manual, aprovação só pela Responsável Técnica,
// dados do modelo no cadastro da OP e impressão numa folha.
const { chromium } = require('playwright');
const { execSync } = require('child_process');
const BASE = 'http://127.0.0.1:8000';
let falhas = 0; const ok = (c, m) => { console.log((c ? 'OK   ' : 'FALHA') + ' ' + m); if (!c) falhas++; };
const sql = (q) => execSync(`mysql perfeita --default-character-set=utf8mb4 -N -e "${q.replace(/"/g, '\\"')}"`).toString().trim();

(async () => {
  const b = await chromium.launch();
  const erros = [];
  const como = async (email) => {
    const p = await (await b.newContext({ viewport: { width: 1300, height: 900 } })).newPage();
    p.on('pageerror', (e) => erros.push(e.message)); await p.route(/open-meteo/, (r) => r.fulfill({ body: '{}' }));
    await p.goto(BASE + '/entrar'); await p.fill('#email', email); await p.fill('#senha', 'Demo@2026'); await p.click('button[type=submit]'); await p.waitForLoadState();
    if (await p.locator('[data-novidades-ok]').count()) { await p.click('[data-novidades-ok]'); await p.waitForLoadState(); }
    return { p, csrf: await p.evaluate(() => document.querySelector('meta[name=csrf]').content) };
  };
  const post = async (s, caminho, form = {}) => (await s.p.request.post(BASE + caminho, { form: { _csrf: s.csrf, ...form } })).status();

  // OP planejada com matéria-prima que tem 2 lotes
  const [op, mp] = sql(`select concat(o.id,'|',f.materia_prima_id) from ordens_producao o join ficha_tecnica f on f.produto_id=o.produto_id
    join materias_primas m on m.id=f.materia_prima_id and m.tipo='materia_prima'
    where o.situacao='planejada' and (select count(*) from mp_lotes l where l.materia_prima_id=f.materia_prima_id and l.saldo>0) >= 2 order by o.id limit 1`).split('|');
  const prod = await como('producao@demo.local');
  await prod.p.goto(BASE + `/producao/ordens/${op}`);
  const fefo = sql(`select id from mp_lotes where materia_prima_id=${mp} and saldo>0 order by validade is null, validade limit 1`);
  ok(await prod.p.locator(`[data-lotes-mp] select[name="lote[${mp}]"]`).inputValue() === fefo, 'sugere o lote que vence primeiro');
  const outro = sql(`select id from mp_lotes where materia_prima_id=${mp} and saldo>0 and id<>${fefo} limit 1`);
  await post(prod, `/producao/ordens/${op}/lotes`, { [`lote[${mp}]`]: outro });
  await prod.p.goto(BASE + `/producao/ordens/${op}`);
  ok(await prod.p.locator(`[data-lotes-mp] select[name="lote[${mp}]"]`).inputValue() === outro, 'escolher outro lote fica salvo');

  // Aprovação: só a Técnica
  ok(await post(prod, `/producao/ordens/${op}/aprovar`) === 403 && sql(`select aprovada_em is null from ordens_producao where id=${op}`) === '1', 'Produção não aprova a OP');
  const tec = await como('tecnica@demo.local');
  await tec.p.goto(BASE + `/producao/ordens/${op}`);
  await Promise.all([tec.p.waitForNavigation(), tec.p.click('[data-aprovar-op]')]);
  ok(sql(`select aprovada_por=(select id from usuarios where email='tecnica@demo.local') from ordens_producao where id=${op}`) === '1', 'Responsável Técnica revisa e aprova');

  // Impressão: aprovação e lote escolhido saem preenchidos, numa folha só
  const imp = await (await b.newContext()).newPage(); await imp.addInitScript(() => { window.print = () => {}; });
  await imp.goto(BASE + '/entrar'); await imp.fill('#email', 'producao@demo.local'); await imp.fill('#senha', 'Demo@2026'); await imp.click('button[type=submit]'); await imp.waitForLoadState();
  await imp.goto(BASE + `/producao/ordens/${op}/imprimir`);
  const assin = await imp.locator('.op-assin').innerText();
  ok(assin.includes('Técnica'), 'impressão mostra quem revisou e aprovou');
  ok((await imp.locator('.op-folha').innerText()).includes(sql(`select lote from mp_lotes where id=${outro}`)), 'impressão traz o lote escolhido');
  await imp.emulateMedia({ media: 'print' });
  const pdf = await imp.pdf({ format: 'A4', landscape: true, printBackground: true });
  ok((pdf.toString('latin1').match(/\/Type\s*\/Page[^s]/g) || []).length === 1, 'cabe numa folha A4 deitada');

  // Cadastro da OP com os dados do modelo
  const prodId = sql(`select produto_id from ordens_producao where id=${op}`);
  await post(prod, '/producao/ordens', { produto_id: prodId, quantidade: '30', lote: 'LT-MOD-1', forma: 'capsula', unidades_por_frasco: '60', amostra: '1', fornecedor_mp: 'cliente', entrada_producao: '2026-12-01' });
  ok(sql("select concat(forma,'|',unidades_por_frasco,'|',amostra,'|',fornecedor_mp,'|',entrada_producao) from ordens_producao where lote='LT-MOD-1'") === 'capsula|60|1|cliente|2026-12-01', 'OP guarda forma, cápsulas por frasco, amostra, fornecedor e entrada na produção');

  ok(erros.length === 0, 'nenhum erro de JavaScript' + (erros.length ? ': ' + erros.join(' / ') : ''));
  await b.close();
  console.log(falhas ? `\n${falhas} FALHA(S)` : '\nTudo certo.');
  process.exit(falhas ? 1 : 0);
})();
