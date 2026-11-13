// Teste automático (Playwright). Rodar todos: bash tests/rodar.sh  ·  só este: node tests/teste_meu_mes.js
const path = require('path');
const RECURSOS = path.join(__dirname, 'recursos');
const { chromium } = require('playwright');
const { execSync } = require('child_process');
const BASE = 'http://127.0.0.1:8000', DIR = process.argv[2] || path.join(__dirname, 'saida');
let falhas = 0;
const ok = (c, m) => { console.log((c ? 'OK   ' : 'FALHA') + ' ' + m); if (!c) falhas++; };
const sql = (q) => execSync(`mysql perfeita --default-character-set=utf8mb4 -N -e "${q}"`).toString().trim();
const brl = (v) => 'R$ ' + Number(v).toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
async function entrar(p, email) { await p.goto(BASE + '/entrar'); await p.fill('#email', email); await p.fill('#senha', 'Demo@2026'); await p.click('button[type=submit]'); }
// Dias úteis (seg-sex) do mês até a data e no total
function diasTrabalho(ate) {
  const hoje = new Date(); const ano = hoje.getFullYear(), mes = hoje.getMonth();
  const total = new Date(ano, mes + 1, 0).getDate(); let dec = 0, tot = 0;
  for (let d = 1; d <= total; d++) { const w = new Date(ano, mes, d).getDay(); if (w >= 1 && w <= 5) { tot++; if (d <= ate) dec++; } }
  return [dec, tot];
}
(async () => {
  const b = await chromium.launch(); const p = await (await b.newContext({ viewport: { width: 1440, height: 950 } })).newPage();
  const erros = []; p.on('pageerror', e => erros.push(e.message));

  // ---------- Gestor: lança o vendido da equipe ----------
  await entrar(p, 'gestor@demo.local');
  ok(await p.locator('nav a', { hasText: 'Meu mês' }).count() === 1, 'gestor vê "Meu mês" no menu');
  await p.goto(BASE + '/meu-mes');
  ok(await p.locator('.meta-equipe').count() === 0 && await p.locator('[data-atalho-equipe]').count() === 1, 'Meu mês não tem mais o bloco da equipe, só o atalho');
  await p.goto(BASE + '/equipe'); // a meta da equipe fica em Equipe de vendas
  ok((await p.locator('.meta-equipe h2').innerText()).includes('450.000,00'), 'meta da equipe aparece em Equipe de vendas (R$ 450.000,00)');
  await p.click('.form-equipe summary');
  await p.fill('#eq-vendido', '200.000,00');
  const hoje = new Date().getDate();
  await Promise.all([p.waitForNavigation(), p.click('.form-equipe button[type=submit]')]);
  ok((await p.locator('.aviso.sucesso').innerText()).includes('projeções'), 'gestor salva o vendido da equipe');
  const [dec, tot] = diasTrabalho(hoje);
  const ritmo = 200000 / dec, projecao = ritmo * tot;
  const numeros = await p.locator('.meta-equipe-numeros').innerText();
  ok(numeros.includes(brl(projecao)), `projeção do mês = 200.000 ÷ ${dec} × ${tot} = ${brl(projecao)}`);
  ok(numeros.includes(brl(ritmo)), `projeção do dia (ritmo) = ${brl(ritmo)}`);
  const restantes = tot - dec + ([1,2,3,4,5].includes(new Date().getDay()) ? 1 : 0);
  ok(numeros.includes(brl((450000 - 200000) / restantes)), `necessário por dia = 250.000 ÷ ${restantes} = ${brl(250000 / restantes)}`);
  ok(sql("select count(*) from auditoria where entidade='meta_equipe'") === '1', 'alteração da meta da equipe registrada na auditoria');
  await p.screenshot({ path: DIR + '/meu-mes-gestor.png', fullPage: true });
  await p.click('form[action$="/sair"] button');

  // ---------- Vendedor: vê a equipe sem poder alterar; usa a meta individual ----------
  await entrar(p, 'televendas1@demo.local');
  await p.goto(BASE + '/equipe');
  ok(await p.locator('.form-equipe').count() === 0, 'vendedor NÃO vê o formulário da meta da equipe');
  ok((await p.locator('.meta-equipe-numeros').innerText()).includes('200.000,00'), 'vendedor vê o vendido da equipe atualizado pelo gestor');
  await p.goto(BASE + '/meu-mes');
  const csrf = await p.locator('meta[name=csrf]').getAttribute('content');
  const r = await p.request.post(BASE + '/meu-mes/equipe', { form: { _csrf: csrf, mes: '2026-11', meta: '1', vendido: '1', 'dias_semana[]': '1' } });
  ok(r.status() === 403, 'vendedor recebe 403 ao tentar alterar a meta da equipe');

  const qtdAntes = Number(sql("select count(*) from vendas_registradas v join usuarios u on u.id=v.usuario_id where u.email='televendas1@demo.local' and v.data like concat(date_format(curdate(),'%Y-%m'),'%')"));
  await p.fill('#v-valor', '2.500,00');
  await p.fill('#v-desc', 'Empório Teste');
  await Promise.all([p.waitForNavigation(), p.click('.form-venda button')]);
  const kpis = await p.locator('.kpis-meu-mes').innerText();
  ok(kpis.includes(String(qtdAntes + 1)), `quantidade de vendas no mês: ${qtdAntes + 1}`);
  const [soma, qtd] = sql("select concat(sum(valor),'|',count(*)) from vendas_registradas v join usuarios u on u.id=v.usuario_id where u.email='televendas1@demo.local' and v.data like concat(date_format(curdate(),'%Y-%m'),'%')").split('|');
  ok(kpis.includes(brl(Number(soma) / Number(qtd))), `ticket médio = ${brl(Number(soma) / Number(qtd))}`);
  ok(kpis.includes('participação') || kpis.toLowerCase().includes('participação'), 'mostra a participação na equipe');
  await p.screenshot({ path: DIR + '/meu-mes-vendedor.png', fullPage: true });

  // Folga muda os dias de trabalho
  const futuro = new Date(); futuro.setDate(futuro.getDate() + 1);
  await p.goto(BASE + '/meu-mes?aba=calendario');
  const antesDias = await p.locator('.calendario-grade a:not(.folga)').count();
  await p.locator('.calendario-grade a:not(.folga)').last().click();
  await Promise.all([p.waitForNavigation(), p.click('form[action$="/meu-mes/folga"] button')]);
  const depoisDias = await p.locator('.calendario-grade a:not(.folga)').count();
  ok(depoisDias !== antesDias, `marcar/retirar folga altera os dias de trabalho (${antesDias} → ${depoisDias})`);
  await p.screenshot({ path: DIR + '/meu-mes-calendario.png' });

  // Tarefas
  await p.goto(BASE + '/meu-mes?aba=tarefas');
  await p.fill('#t-titulo', 'Ligar para clientes da Black Friday');
  await Promise.all([p.waitForNavigation(), p.click('form[action$="/meu-mes/tarefas"] button')]);
  ok((await p.content()).includes('Ligar para clientes da Black Friday'), 'tarefa criada');
  await Promise.all([p.waitForNavigation(), p.locator('li', { hasText: 'Ligar para clientes da Black Friday' }).locator('button', { hasText: 'Concluir' }).click()]);
  ok(sql("select concluida_em is not null from tarefas where titulo='Ligar para clientes da Black Friday'") === '1', 'tarefa concluída');

  // Financeiro não acessa
  await p.click('form[action$="/sair"] button');
  await entrar(p, 'financeiro@demo.local');
  ok((await p.goto(BASE + '/meu-mes')).status() === 403, 'financeiro recebe 403 no Meu mês');
  ok(erros.length === 0, 'sem erros de JavaScript ' + erros.join('|'));
  await b.close(); console.log(falhas ? `\n${falhas} FALHA(S)` : '\nTudo certo'); process.exit(falhas ? 1 : 0);
})();
