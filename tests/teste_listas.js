// Teste automático (Playwright). Rodar todos: bash tests/rodar.sh  ·  só este: node tests/teste_listas.js
const path = require('path');
const RECURSOS = path.join(__dirname, 'recursos');
const { chromium } = require('playwright');
const { execSync } = require('child_process');
const BASE = 'http://127.0.0.1:8000', DIR = process.argv[2] || path.join(__dirname, 'saida');
let falhas = 0;
const ok = (c, m) => { console.log((c ? 'OK   ' : 'FALHA') + ' ' + m); if (!c) falhas++; };
const sql = (q) => execSync(`mysql perfeita --default-character-set=utf8mb4 -N -e "${q}"`).toString().trim();
(async () => {
  const b = await chromium.launch(); const p = await (await b.newContext({ viewport: { width: 1440, height: 950 } })).newPage();
  const erros = []; p.on('pageerror', e => erros.push(e.message));
  await p.goto(BASE + '/entrar'); await p.fill('#email', 'admin@demo.local'); await p.fill('#senha', 'Demo@2026'); await p.click('button[type=submit]');
  await p.goto(BASE + '/configuracoes/listas');
  ok(await p.locator('nav a.ativo').innerText() === 'Listas', 'só "Listas" fica destacado no menu');
  // nova etapa
  const sec = p.locator('#etapas form.nova-linha');
  await sec.locator('input[name=nome]').fill('Pós-venda');
  await sec.locator('input[name=ordem]').fill('8');
  await Promise.all([p.waitForNavigation(), sec.locator('button').click()]);
  ok(sql("select concat(ordem,'|',tipo,'|',ativa) from funil_etapas where nome='Pós-venda'") === '8|aberta|1', 'nova etapa criada');
  // desativar segmento Outros
  const id = sql("select id from segmentos where nome='Outros'");
  await p.uncheck(`input[form=segmentos-${id}][name=ativo]`);
  await Promise.all([p.waitForNavigation(), p.click(`#segmentos-${id} button`)]);
  ok(sql(`select ativo from segmentos where id=${id}`) === '0', 'segmento desativado');
  await p.goto(BASE + '/carteira/lista');
  ok(!(await p.locator('select[name=segmento]').innerText()).includes('Outros'), 'segmento desativado some do filtro');
  await p.goto(BASE + '/carteira/funil');
  ok(await p.locator('.funil-coluna h3', { hasText: 'Pós-venda' }).count() === 1, 'nova etapa aparece como coluna do funil');
  ok(sql("select count(*) from auditoria where entidade in ('etapas','segmentos')") === '2', 'mudanças registradas na auditoria');
  await p.goto(BASE + '/configuracoes/listas');
  await p.screenshot({ path: DIR + '/listas.png', fullPage: true });
  // gestor não acessa
  await p.click('form[action$="/sair"] button');
  await p.goto(BASE + '/entrar'); await p.fill('#email', 'gestor@demo.local'); await p.fill('#senha', 'Demo@2026'); await p.click('button[type=submit]');
  ok((await p.goto(BASE + '/configuracoes/listas')).status() === 403, 'gestor recebe 403 nas listas');
  ok(erros.length === 0, 'sem erros de JavaScript');
  await b.close(); console.log(falhas ? `\n${falhas} FALHA(S)` : '\nTudo certo'); process.exit(falhas ? 1 : 0);
})();
