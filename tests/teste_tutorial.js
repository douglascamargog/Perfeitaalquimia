// Teste automático (Playwright). Rodar todos: bash tests/rodar.sh  ·  só este: node tests/teste_tutorial.js
// Tutorial do One em cada tela (V13.0): pessoa nova vê o passo a passo sozinho; "Pular" → volta na próxima visita;
// "Aprendi tudo desta tela" grava e não abre mais; parte que não está na tela → balão no meio; quem já usava não é
// interrompido, mas abre pelo One; o gestor reinicia; celular; criar pessoa nova liga o tutorial; sem erros de JS.
const { chromium } = require('playwright');
const { execSync } = require('child_process');
const BASE = 'http://127.0.0.1:8000';
const SAIDA = process.argv[2] || 'tests/saida';
let falhas = 0; const ok = (c, m) => { console.log((c ? 'OK   ' : 'FALHA') + ' ' + m); if (!c) falhas++; };
const sql = (q) => execSync(`mysql perfeita --default-character-set=utf8mb4 -N -e "${q.replace(/"/g, '\\"')}"`).toString().trim();

async function entrar(b, email, vp = { width: 1360, height: 860 }) {
  const p = await (await b.newContext({ viewport: vp })).newPage();
  p.erros = []; p.on('pageerror', (e) => p.erros.push(e.message));
  await p.route(/open-meteo/, (r) => r.fulfill({ body: '{}' }));
  await p.goto(BASE + '/entrar'); await p.fill('#email', email); await p.fill('#senha', 'Demo@2026'); await p.click('button[type=submit]'); await p.waitForLoadState();
  if (await p.locator('[data-novidades-ok]').count()) { await p.click('[data-novidades-ok]'); await p.waitForLoadState(); }
  return p;
}
const balao = (p) => p.locator('[data-tut-balao]');
const esperarBalao = async (p, ms = 6000) => { try { await balao(p).waitFor({ timeout: ms }); return true; } catch { return false; } };

(async () => {
  const b = await chromium.launch();
  ok(sql("select count(*) from tutorial_telas where ativo=1") >= '50', 'tutoriais das telas carregados (' + sql('select count(*) from tutorial_telas') + ' telas)');
  ok(sql("select count(*) from usuarios where tutorial_ativo=1") === '0', 'quem já existia não recebe o tutorial automático');

  // Pessoa nova (tutorial ligado)
  sql("update usuarios set tutorial_ativo=1 where email='televendas1@demo.local'");
  const p = await entrar(b, 'televendas1@demo.local');
  ok(await esperarBalao(p), 'pessoa nova: o tutorial do Início abre sozinho');
  ok((await balao(p).innerText()).includes('Passo 1 de'), 'mostra "Passo 1 de N"');
  ok(await p.locator('[data-tut-aprendi]').count() === 0, '"Aprendi" só aparece no último passo');
  await p.click('[data-tut-proximo]');
  ok(await p.locator('.tut-luz:not([hidden])').count() === 1, 'passo com parte da tela: ela fica destacada');
  const caixaLuz = await p.locator('.tut-luz').boundingBox(); const caixaBalao = await balao(p).boundingBox();
  ok(caixaBalao.x >= caixaLuz.x + caixaLuz.width - 2, 'menu destacado: o balão fica ao lado (não cobre o menu)');
  await p.click('[data-tut-pular]');
  ok(await balao(p).count() === 0, '"Pular por agora" fecha');
  await p.goto(BASE + '/');
  ok(await esperarBalao(p), 'voltou ao Início: o tutorial abre de novo (ainda não aprendeu)');
  ok(sql("select vezes from tutorial_progresso p join usuarios u on u.id=p.usuario_id where u.email='televendas1@demo.local' and rota='/'") >= '2', 'conta quantas vezes abriu');
  // até o fim
  while (await p.locator('[data-tut-proximo]').count()) await p.click('[data-tut-proximo]');
  await p.screenshot({ path: SAIDA + '/tutorial-ultimo-passo.png' });
  await p.click('[data-tut-aprendi]');
  await p.waitForSelector('[data-one-balao]');
  ok((await p.locator('[data-one-balao]').innerText()).match(/aprendeu 1 de \d+ telas/), 'depois de "Aprendi": o One diz quantas telas faltam');
  ok(sql("select count(*) from tutorial_progresso p join usuarios u on u.id=p.usuario_id where u.email='televendas1@demo.local' and rota='/' and aprendido_em is not null") === '1', '"Aprendi" grava no servidor');
  await p.goto(BASE + '/');
  ok(!(await esperarBalao(p, 2500)), 'tela aprendida: não abre mais sozinho');
  // ficha do cliente: tutorial próprio (prefixo mais longo)
  await p.goto(BASE + '/agenda');
  ok(await esperarBalao(p) && (await balao(p).innerText()).includes('Agenda'), 'cada tela tem o seu tutorial (Agenda)');
  await p.click('[data-tut-pular]');
  // parte que não existe na tela → balão no meio
  sql("update tutorial_telas set passos_json='[{\"alvo\":\"#nao-existe\",\"texto\":\"Passo sem parte na tela\"}]' where rota='/recompra'");
  await p.goto(BASE + '/recompra');
  ok(await esperarBalao(p) && await balao(p).evaluate((e) => e.classList.contains('meio')), 'parte que não está na tela: balão no meio');
  await p.click('[data-tut-aprendi]');
  ok(p.erros.length === 0, 'sem erros de JavaScript' + (p.erros[0] ? ': ' + p.erros[0] : ''));

  // Quem já usava: não é interrompido, mas abre pelo One
  const v = await entrar(b, 'televendas2@demo.local');
  await v.goto(BASE + '/carteira');
  ok(!(await esperarBalao(v, 2500)), 'quem já usava o sistema não é interrompido');
  await v.click('[data-one-abrir]');
  await v.waitForSelector('#one-painel [data-one-ajuda]');
  await v.click('#one-painel [data-one-ajuda]');
  ok(await esperarBalao(v) && (await balao(v).innerText()).includes('Carteira'), 'pelo One ("O que dá pra fazer aqui?") abre o passo a passo da tela');
  ok(await v.locator('#one-painel').isHidden(), 'o painel do One fecha para a tela aparecer');
  await v.keyboard.press('Escape');
  ok(await balao(v).count() === 0, 'Esc fecha o tutorial');

  // Celular
  const c = await entrar(b, 'televendas1@demo.local', { width: 390, height: 800 });
  await c.goto(BASE + '/carteira');
  ok(await esperarBalao(c), 'celular: o tutorial abre');
  const bc = await balao(c).boundingBox();
  ok(bc.x >= 0 && bc.x + bc.width <= 390 && await c.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), 'celular: o balão cabe na tela, sem rolagem para o lado');
  await c.screenshot({ path: SAIDA + '/tutorial-celular.png' });

  // Gestor reinicia o tutorial de alguém
  sql("update usuarios set tutorial_ativo=0 where email='televendas1@demo.local'");
  const g = await entrar(b, 'gestor@demo.local');
  await g.goto(BASE + '/treinamento/equipe');
  const linha = g.locator('tr', { hasText: 'Televendas 1' });
  ok((await linha.innerText()).match(/\t[1-9]\d*\t/) !== null || (await linha.innerText()).includes('2'), 'Equipe mostra quantas telas a pessoa aprendeu');
  g.on('dialog', (d) => d.accept());
  await Promise.all([g.waitForNavigation(), linha.locator('button:has-text("Reiniciar tutorial")').click()]);
  ok(sql("select tutorial_ativo from usuarios where email='televendas1@demo.local'") === '1' && sql("select count(*) from tutorial_progresso p join usuarios u on u.id=p.usuario_id where u.email='televendas1@demo.local' and aprendido_em is not null") === '0', 'gestor reinicia: tutorial ligado de novo e telas voltam a "não aprendidas"');
  const vendedora = await entrar(b, 'televendas2@demo.local');
  const r = await vendedora.request.post(BASE + '/treinamento/equipe/1/reiniciar', { form: { _csrf: await vendedora.locator('meta[name=csrf]').getAttribute('content') } });
  ok(r.status() === 403, 'vendedora não reinicia o tutorial de ninguém (403)');

  // Pessoa criada pela tela já começa com o tutorial
  const a = await entrar(b, 'admin@demo.local');
  await a.goto(BASE + '/usuarios/novo');
  await a.fill('#nome', 'Pessoa Nova Teste'); await a.fill('#email', 'nova.teste@demo.local'); await a.selectOption('#perfil_id', { index: 1 });
  await a.fill('#senha', 'Nova@Senha2026'); await a.fill('#senha_confirmacao', 'Nova@Senha2026');
  await Promise.all([a.waitForNavigation(), a.click('form:has(#nome) button[type=submit]')]);
  ok(sql("select tutorial_ativo from usuarios where email='nova.teste@demo.local'") === '1', 'pessoa criada em Usuários começa com o tutorial ligado');

  await b.close();
  process.exit(falhas ? 1 : 0);
})();
