// Teste automático (Playwright). Rodar todos: bash tests/rodar.sh  ·  só este: node tests/teste_painel.js
const path = require('path');
const RECURSOS = path.join(__dirname, 'recursos');
const { chromium } = require('playwright');
const { execSync } = require('child_process');
const BASE = 'http://127.0.0.1:8000', DIR = process.argv[2] || path.join(__dirname, 'saida');
let falhas = 0; const ok = (c, m) => { console.log((c ? 'OK   ' : 'FALHA') + ' ' + m); if (!c) falhas++; };
const sql = (q) => execSync(`mysql perfeita --default-character-set=utf8mb4 -N -e "${q}"`).toString().trim();
const brl = (v) => 'R$ ' + Number(v).toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
const norm = (t) => t.replace(/\s/g, ' ');
async function entrar(p, email) { await p.goto(BASE + '/entrar'); await p.fill('#email', email); await p.fill('#senha', 'Demo@2026'); await p.click('button[type=submit]'); await p.waitForLoadState(); }
async function sair(p) { await p.goto(BASE + '/'); await p.click('form[action$="/sair"] button'); await p.waitForLoadState(); }
(async () => {
  const b = await chromium.launch(); const p = await (await b.newContext({ viewport: { width: 1440, height: 1000 } })).newPage();
  const erros = []; p.on('pageerror', e => erros.push(e.message));
  await p.route('https://cdn.jsdelivr.net/npm/chart.js@4.4.1/**', r => r.fulfill({ path: RECURSOS + '/chart.umd.js', contentType: 'text/javascript' }));
  await p.route('https://api.open-meteo.com/**', r => r.fulfill({ json: {} }));
  const hoje = sql('select curdate()'), ini = sql("select date_format(curdate(),'%Y-%m-01')");

  await entrar(p, 'gestor@demo.local');
  // mês atual
  await p.goto(BASE + '/painel');
  const fatMes = sql(`select coalesce(sum(total),0) from pedidos where situacao='faturado' and faturado_em >= '${ini}' and faturado_em < '${hoje}' + interval 1 day`);
  ok(norm(await p.locator('[data-faturamento]').innerText()) === brl(fatMes), `faturado do mês bate com o banco (${brl(fatMes)})`);
  // 12 meses filtrado por vendedor e UF
  const vend = sql("select id from usuarios where email='televendas1@demo.local'");
  const de12 = sql("select date_format(curdate() - interval 11 month,'%Y-%m-01')");
  const uf = sql(`select c.uf from pedidos p join clientes c on c.id=p.cliente_id where p.vendedor_id=${vend} and p.situacao='faturado' group by c.uf order by count(*) desc limit 1`);
  await p.goto(BASE + `/painel?periodo=12m&vendedor=${vend}&uf=${uf}`);
  const fatF = sql(`select coalesce(sum(p.total),0) from pedidos p join clientes c on c.id=p.cliente_id where p.situacao='faturado' and p.vendedor_id=${vend} and c.uf='${uf}' and p.faturado_em >= '${de12}' and p.faturado_em < '${hoje}' + interval 1 day`);
  ok(norm(await p.locator('[data-faturamento]').innerText()) === brl(fatF), `filtro 12 meses + vendedor + UF ${uf} bate (${brl(fatF)})`);
  const ped = sql(`select count(*) from pedidos p join clientes c on c.id=p.cliente_id where p.situacao='faturado' and p.vendedor_id=${vend} and c.uf='${uf}' and p.faturado_em >= '${de12}'`);
  ok((await p.locator('[data-pedidos]').innerText()).replace('.', '') === ped, `pedidos no filtro (${ped})`);
  // recorrência
  const cli = Number(sql(`select count(distinct p.cliente_id) from pedidos p join clientes c on c.id=p.cliente_id where p.situacao='faturado' and p.vendedor_id=${vend} and c.uf='${uf}' and p.faturado_em >= '${de12}'`));
  const rec = Number(sql(`select count(*) from (select p.cliente_id from pedidos p join clientes c on c.id=p.cliente_id where p.situacao='faturado' and p.vendedor_id=${vend} and c.uf='${uf}' and p.faturado_em >= '${de12}' group by p.cliente_id having count(*)>=2) x`));
  ok((await p.locator('[data-recorrencia]').innerText()) === (cli ? (rec / cli * 100).toFixed(1).replace('.', ',') : '0,0') + '%', `recorrência = ${rec}/${cli}`);
  // mês anterior e variação
  await p.goto(BASE + '/painel?periodo=mes');
  const antIni = sql("select date_format(curdate() - interval 1 month,'%Y-%m-01')"), antFim = sql('select curdate() - interval 1 month');
  const fatAnt = Number(sql(`select coalesce(sum(total),0) from pedidos where situacao='faturado' and faturado_em >= '${antIni}' and faturado_em < '${antFim}' + interval 1 day`));
  const v = (Number(fatMes) - fatAnt) / fatAnt * 100;
  ok((await p.locator('[data-card=faturamento] .mk-var').innerText()).includes(Math.abs(v).toFixed(1).replace('.', ',') + '%'), `variação vs mesmo período do mês anterior (${v.toFixed(1)}%)`);
  ok(await p.evaluate(() => !!Chart.getChart('grafico-meses') && !!Chart.getChart('grafico-ufs') && !!Chart.getChart('grafico-origens')), 'gráficos desenhados');
  const csv = await p.request.get(BASE + '/painel/exportar.csv?periodo=12m');
  ok(csv.ok() && (await csv.text()).includes('Vendedor;Faturado'), 'exporta CSV');
  // sem compra → link para a carteira
  const sem90 = sql("select count(*) from clientes where excluido_em is null and tipo='cliente' and ultima_compra_em is not null and ultima_compra_em < curdate() - interval 90 day");
  ok((await p.locator('.pn-sem-compra a').last().innerText()).startsWith(sem90), `sem compra 90+ dias (${sem90})`);

  // ---------- Metas ----------
  await p.goto(BASE + '/metas');
  const campo = p.locator(`input[name="metas[vendedor][${vend}]"]`);
  await campo.fill('12.345,67');
  const fn = sql("select id from funcoes where slug='prospeccao_reativacao'");
  await p.locator(`input[name="metas[funcao][${fn}]"]`).fill('8.000,00');
  await Promise.all([p.waitForNavigation(), p.click('.pn-salvar button')]);
  const mes = hoje.slice(0, 7);
  ok(sql(`select valor from metas where mes='${mes}' and tipo='vendedor' and alvo_id=${vend}`) === '12345.67', 'gestor salva meta de vendedor');
  ok(sql(`select valor from metas where mes='${mes}' and tipo='funcao' and alvo_id=${fn}`) === '8000.00', 'gestor salva meta de função');
  ok(Number(sql(`select count(*) from auditoria where entidade='meta'`)) >= 2, 'auditoria registra as metas');
  const vendido = Number(sql(`select coalesce(sum(total),0) from pedidos where situacao='faturado' and vendedor_id=${vend} and faturado_em >= '${ini}'`));
  const linha = p.locator('tr', { has: p.locator(`input[name="metas[vendedor][${vend}]"]`) });
  ok((await linha.innerText()).includes(Math.round(vendido / 12345.67 * 100) + '%'), `% da meta (${Math.round(vendido / 12345.67 * 100)}%)`);
  // copiar do mês anterior
  const prox = sql("select date_format(curdate() + interval 1 month,'%Y-%m')");
  await p.goto(BASE + '/metas?mes=' + prox);
  await Promise.all([p.waitForNavigation(), p.click('form[action$="/metas/copiar"] button')]);
  ok(sql(`select valor from metas where mes='${prox}' and tipo='vendedor' and alvo_id=${vend}`) === '12345.67', 'copiar metas do mês anterior');
  await p.screenshot({ path: DIR + '/pn-metas.png', fullPage: true });

  // ---------- Recompra (gestor vê todos) ----------
  await p.goto(BASE + '/recompra');
  const tol = sql("select valor from configuracoes where chave='recompra.tolerancia_dias'"), inat = sql("select valor from configuracoes where chave='carteira.dias_inatividade'");
  const regra = (extra) => sql(`select count(*) from clientes where excluido_em is null and tipo='cliente' and intervalo_medio_dias>0 and ultima_compra_em + interval (intervalo_medio_dias + ${tol}) day < curdate() and ultima_compra_em >= curdate() - interval ${inat} day ${extra}`);
  ok(String(await p.locator('.rc-item').count()) === regra(''), `gestor vê todos os clientes da regra (${regra('')})`);
  await sair(p);

  // ---------- Vendedor ----------
  await entrar(p, 'televendas1@demo.local');
  await p.goto(BASE + '/recompra');
  ok(String(await p.locator('.rc-item').count()) === regra(`and responsavel_id=${vend}`), `vendedor vê só a carteira dele (${regra(`and responsavel_id=${vend}`)})`);
  const primeiro = await p.locator('.rc-item').first().getAttribute('data-recompra');
  await Promise.all([p.waitForNavigation(), p.locator('.rc-item').first().locator('.rc-agendar button').click()]);
  ok(sql(`select count(*) from tarefas where cliente_id=${primeiro} and concluida_em is null and titulo like 'Recompra%'`) === '1', 'agendar retorno cria tarefa na Agenda');
  ok(await p.locator('.rc-item').first().locator('a', { hasText: 'Novo pedido' }).count() === 1, 'atalho "Novo pedido (repetir)"');
  ok((await p.goto(BASE + '/painel')).status() === 403, 'vendedor recebe 403 no Painel');
  ok((await p.goto(BASE + '/metas')).status() === 403, 'vendedor recebe 403 em Metas');
  await p.goto(BASE + '/meu-mes');
  ok((await p.content()).includes('Faturado no sistema'), 'Meu mês mostra "Faturado no sistema"');
  await p.goto(BASE + '/');
  ok((await p.locator('.bento').innerText()).includes('Recompra'), 'Início do vendedor mostra Recompra');
  await sair(p);

  // ---------- Financeiro e Produção ----------
  await entrar(p, 'financeiro@demo.local');
  ok((await p.goto(BASE + '/painel')).status() === 200, 'Financeiro vê o Painel');
  ok((await p.goto(BASE + '/metas')).status() === 403, 'Financeiro não edita metas (403)');
  await p.goto(BASE + '/'); ok((await p.locator('.bento').innerText()).includes('Faturado no mês'), 'Início do Financeiro mostra "Faturado no mês"');
  await sair(p);
  await entrar(p, 'producao@demo.local');
  for (const u of ['/painel', '/metas', '/recompra']) ok((await p.goto(BASE + u)).status() === 403, `Produção recebe 403 em ${u}`);
  await sair(p);

  // celular
  const cel = await b.newContext({ viewport: { width: 390, height: 844 } }); const pc = await cel.newPage();
  await pc.route('https://cdn.jsdelivr.net/npm/chart.js@4.4.1/**', r => r.fulfill({ path: RECURSOS + '/chart.umd.js', contentType: 'text/javascript' }));
  await entrar(pc, 'gestor@demo.local');
  for (const u of ['/painel', '/recompra']) { await pc.goto(BASE + u); ok(await pc.evaluate(() => document.documentElement.scrollWidth) <= 392, `${u} sem rolagem horizontal no celular`); }
  await pc.goto(BASE + '/painel'); await pc.screenshot({ path: DIR + '/pn-celular.png' });
  ok(erros.length === 0, 'sem erros de JavaScript ' + erros.join(' | '));
  console.log(falhas ? `\n${falhas} FALHA(S)` : '\nTUDO OK'); await b.close();
})();
