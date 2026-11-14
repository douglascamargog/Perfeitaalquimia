// Teste automático (Playwright). Rodar todos: bash tests/rodar.sh  ·  só este: node tests/teste_perda.js
// Produção: máximo de unidades que dá para produzir e desperdício medido ao concluir a ordem (vale para as próximas ordens).
const { chromium } = require('playwright');
const { execSync } = require('child_process');
const BASE = 'http://127.0.0.1:8000';
let falhas = 0; const ok = (c, m) => { console.log((c ? 'OK   ' : 'FALHA') + ' ' + m); if (!c) falhas++; };
const sql = (q) => execSync(`mysql perfeita --default-character-set=utf8mb4 -N -e "${q.replace(/"/g, '\\"')}"`).toString().trim();

(async () => {
  const b = await chromium.launch();
  const p = await (await b.newContext()).newPage();
  const erros = []; p.on('pageerror', (e) => erros.push(e.message)); await p.route(/open-meteo|jsdelivr/, (r) => r.fulfill({ body: '{}' }));
  await p.goto(BASE + '/entrar'); await p.fill('#email', 'producao@demo.local'); await p.fill('#senha', 'Demo@2026'); await p.click('button[type=submit]'); await p.waitForLoadState();
  if (await p.locator('[data-novidades-ok]').count()) { await p.click('[data-novidades-ok]'); await p.waitForLoadState(); }
  const csrf = await p.evaluate(() => document.querySelector('meta[name=csrf]').content);

  // Matéria-prima usada em pelo menos dois produtos ativos
  const [mp, prodA, prodB] = sql("select ft.materia_prima_id, min(ft.produto_id), max(ft.produto_id) from ficha_tecnica ft join produtos p on p.id=ft.produto_id join materias_primas m on m.id=ft.materia_prima_id where p.ativo=1 and m.tipo='materia_prima' group by ft.materia_prima_id having count(*)>=2 limit 1").split('\t');
  // Estoque conhecido para todos os itens do produto A
  sql(`update materias_primas m join ficha_tecnica ft on ft.materia_prima_id=m.id set m.estoque=10000 where ft.produto_id in (${prodA},${prodB})`);
  const porUn = Number(sql(`select quantidade from ficha_tecnica where produto_id=${prodA} and materia_prima_id=${mp}`));
  const estoqueMp = Math.round(porUn * 30 * 1000) / 1000; // dá para 30 unidades
  sql(`update materias_primas set estoque=${estoqueMp} where id=${mp}`);
  const previa = async (prod, q) => (await p.request.get(BASE + `/producao/ordens/previa?produto=${prod}&quantidade=${q}`)).json();

  let r = await previa(prodA, 10);
  ok(r.maximo === 30 && r.limita, `prévia mostra o máximo que dá para produzir (${r.maximo}, limita: ${r.limita})`);

  // Ordem pequena, concluída com o consumo real 10% acima do previsto
  await (await p.request.post(BASE + '/producao/ordens', { form: { _csrf: csrf, produto_id: prodA, quantidade: '10', lote: 'PERDA-1' } })).text();
  const op = sql(`select max(id) from ordens_producao where lote='PERDA-1'`);
  const teorico = porUn * 10;
  const usado = Math.round(teorico * 1.1 * 1000) / 1000;
  const antes = Number(sql(`select estoque from materias_primas where id=${mp}`));
  await p.goto(BASE + `/producao/ordens/${op}`);
  ok(await p.locator(`input[name="usado[${mp}]"]`).count() === 1 && await p.locator('[data-maximo]').count() === 1, 'página da ordem: campo "Usado de verdade" e o máximo');
  await p.request.post(BASE + `/producao/ordens/${op}/concluir`, { form: { _csrf: csrf, quantidade_real: '10', [`usado[${mp}]`]: String(usado).replace('.', ',') } });
  ok(sql(`select situacao from ordens_producao where id=${op}`) === 'concluida', 'ordem concluída');
  ok(Math.abs(antes - Number(sql(`select estoque from materias_primas where id=${mp}`)) - usado) < 0.001, 'baixou o que foi usado de verdade');
  ok(Math.abs(Number(sql(`select perda_pct from producao_perdas where ordem_id=${op} and materia_prima_id=${mp}`)) - 10) < 0.1, 'desperdício medido: 10%');
  ok(Math.abs(Number(sql(`select perda_pct from ficha_tecnica where produto_id=${prodA} and materia_prima_id=${mp}`)) - 10) < 0.1, 'perda gravada na ficha do produto');
  ok(sql(`select count(*) from producao_perdas where ordem_id=${op}`) === '1', 'itens sem consumo informado não viram medição');

  // Próximas ordens já somam a perda
  r = await previa(prodA, 10);
  const linha = r.linhas.find((l) => String(l.materia_prima_id) === mp);
  ok(Math.abs(linha.precisa - teorico * 1.1) < 0.002 && linha.perda_txt.includes('10'), 'prévia soma o desperdício medido no produto');
  const r2 = await previa(prodB, 10);
  const l2 = r2.linhas.find((l) => String(l.materia_prima_id) === mp);
  ok(l2.perda_origem === 'materia' && l2.perda_pct > 9.9, 'outro produto com a mesma matéria-prima usa a média medida');
  await p.goto(BASE + `/producao/ordens/${op}`);
  ok((await p.locator('[data-perdas]').innerText()).includes('10,0%'), 'ordem concluída mostra o desperdício medido');

  ok(erros.length === 0, 'nenhum erro de JavaScript' + (erros.length ? ': ' + erros.join(' / ') : ''));
  await b.close();
  console.log(falhas ? `\n${falhas} FALHA(S)` : '\nTudo certo.');
  process.exit(falhas ? 1 : 0);
})();
