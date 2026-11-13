// Teste automático (Playwright). Rodar todos: bash tests/rodar.sh  ·  só este: node tests/teste_pessoas.js
// Equipe: perfil "tipo rede social" e aniversário (decoração só do aniversariante e mural de parabéns).
const path = require('path');
const { chromium } = require('playwright');
const { execSync } = require('child_process');
const BASE = 'http://127.0.0.1:8000';
const RECURSOS = path.join(__dirname, 'recursos');
let falhas = 0; const ok = (c, m) => { console.log((c ? 'OK   ' : 'FALHA') + ' ' + m); if (!c) falhas++; };
const sql = (q) => execSync(`mysql perfeita --default-character-set=utf8mb4 -N -e "${q.replace(/"/g, '\\"')}"`).toString().trim();
async function entrar(p, email) { await p.goto(BASE + '/entrar'); await p.fill('#email', email); await p.fill('#senha', 'Demo@2026'); await p.click('button[type=submit]'); await p.waitForLoadState(); }

(async () => {
  const b = await chromium.launch();
  const erros = [];
  const nova = async () => { const p = await (await b.newContext({ viewport: { width: 1440, height: 900 } })).newPage(); p.on('pageerror', e => erros.push(e.message)); await p.route(/open-meteo|jsdelivr/, r => r.fulfill({ body: '{}' })); return p; };
  // O aniversariante de hoje é a pessoa de Produção (ano 1990 nunca deve aparecer)
  sql("update usuarios set data_nascimento = null where date_format(data_nascimento,'%m-%d') = date_format(curdate(),'%m-%d')");
  sql("update usuarios set data_nascimento = concat('1990-', date_format(curdate(), '%m-%d')) where email='producao@demo.local'");
  const aniv = sql("select id from usuarios where email='producao@demo.local'");

  // 1. Perfil público preenchido em Meu perfil e visto por um colega
  const ven = await nova();
  await entrar(ven, 'televendas2@demo.local');
  await ven.goto(BASE + '/meu-perfil');
  await ven.fill('#pp-sobre', 'Sou do televendas e amo café <b>forte</b>');
  await ven.fill('#pp-comida', 'Feijoada');
  await ven.fill('#pp-time', 'Grêmio');
  await ven.fill('#pp-pets', 'O gato Frajola');
  await ven.fill('#pp-insta', '@perfilteste');
  await ven.setInputFiles('#pp-capa', path.join(RECURSOS, 'foto_teste.jpg'));
  await Promise.all([ven.waitForNavigation(), ven.click('[data-perfil-publico] button[type=submit]')]);
  const v2 = sql("select id from usuarios where email='televendas2@demo.local'");
  ok(sql(`select comida from usuario_perfil where usuario_id=${v2}`) === 'Feijoada', 'perfil público salvo');
  ok(sql(`select instagram from usuario_perfil where usuario_id=${v2}`) === 'https://www.instagram.com/perfilteste/', 'Instagram vira link seguro');
  ok(sql(`select capa from usuario_perfil where usuario_id=${v2}`).startsWith('media/equipe/'), 'capa salva');
  const col = await nova();
  await entrar(col, 'externo1@demo.local');
  await col.goto(BASE + '/pessoas');
  ok(await col.locator('[data-pessoa]').count() >= 8, 'diretório da equipe');
  await Promise.all([col.waitForNavigation(), col.click(`[data-pessoa="${v2}"]`)]);
  const perfil = await col.content();
  ok(perfil.includes('Feijoada') && perfil.includes('Grêmio') && perfil.includes('O gato Frajola'), 'colega vê os gostos, o time e o pet');
  ok(!perfil.includes('<b>forte</b>') && perfil.includes('&lt;b&gt;forte'), 'texto escapado');
  await col.goto(BASE + `/pessoas/${aniv}`);
  ok(!(await col.locator('.pe-perfil').innerText()).includes('1990'), 'o ano do aniversário nunca aparece');

  // 2. Decoração: só no sistema do aniversariante
  const prod = await nova();
  await entrar(prod, 'producao@demo.local');
  ok(await prod.evaluate(() => document.body.classList.contains('aniversario')) && await prod.locator('.aniv-faixa').count() === 1, 'aniversariante: sistema decorado e faixa de parabéns');
  ok(await col.evaluate(() => document.body.classList.contains('aniversario')) === false, 'colega: sem decoração');

  // 3. Mural: colega deixa os parabéns no Início; o aniversariante vê e é avisado
  await col.goto(BASE + '/');
  const mural = col.locator(`[data-mural="${aniv}"]`);
  ok(await mural.count() === 1, 'mural "Hoje é aniversário de..." no Início do colega');
  await mural.locator('textarea').fill('Parabéns! <script>x</script> Muitas felicidades');
  await Promise.all([col.waitForNavigation(), mural.locator('button[type=submit]').click()]);
  ok(sql(`select count(*) from aniversario_mensagens where aniversariante_id=${aniv}`) === '1', 'mensagem gravada');
  ok(sql(`select count(*) from notificacoes where usuario_id=${aniv} and tipo='aniversario'`) === '1', 'aniversariante recebe o aviso no sino');
  await prod.goto(BASE + '/');
  const muralDele = prod.locator(`[data-mural="${aniv}"]`);
  ok((await muralDele.innerText()).includes('Muitas felicidades') && await muralDele.locator('textarea').count() === 0, 'aniversariante vê as mensagens (sem escrever para si mesmo)');
  ok(!(await muralDele.innerHTML()).includes('<script>x'), 'mensagem escapada');

  // 4. Só o autor apaga; fora do dia não se escreve
  const msg = sql(`select id from aniversario_mensagens where aniversariante_id=${aniv} limit 1`);
  const tokP = await prod.locator('meta[name=csrf]').getAttribute('content');
  await prod.request.post(BASE + `/aniversario/mensagens/${msg}/apagar`, { form: {}, headers: { 'X-CSRF-Token': tokP } });
  ok(sql(`select count(*) from aniversario_mensagens where id=${msg}`) === '1', 'outra pessoa não apaga a mensagem');
  await col.goto(BASE + '/');
  await Promise.all([col.waitForNavigation(), col.locator(`[data-mensagem="${msg}"] form button`).click()]);
  ok(sql(`select count(*) from aniversario_mensagens where id=${msg}`) === '0', 'o autor apaga a própria mensagem');
  const tokC = await col.locator('meta[name=csrf]').getAttribute('content');
  await col.request.post(BASE + `/aniversario/${v2}/mensagens`, { form: { texto: 'fora do dia' }, headers: { 'X-CSRF-Token': tokC } });
  ok(sql(`select count(*) from aniversario_mensagens where aniversariante_id=${v2}`) === '0', 'fora do dia do aniversário não se escreve no mural');

  // 5. Clicar no aniversariante (card de aniversariantes) abre o perfil
  await col.goto(BASE + '/');
  await Promise.all([col.waitForNavigation(), col.locator(`[data-card=aniversariantes] a[href$="/pessoas/${aniv}"]`).first().click()]);
  ok(new URL(col.url()).pathname.endsWith(`/pessoas/${aniv}`) && await col.locator('#mural-aniversario').count() === 1, 'aniversariantes abrem o perfil (com o mural no dia)');

  ok(erros.length === 0, 'sem erros de JavaScript ' + erros.join(' / '));
  await b.close();
  console.log(falhas ? `\n${falhas} FALHA(S)` : '\nTudo certo');
  process.exit(falhas ? 1 : 0);
})();
