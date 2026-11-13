// Teste automático (Playwright). Rodar todos: bash tests/rodar.sh  ·  só este: node tests/teste_comunicados.js
// Comunicados em destaque no topo (setores, leitura, validade, encerrar) e o apelido só para a própria pessoa.
const { chromium } = require('playwright');
const { execSync } = require('child_process');
const BASE = 'http://127.0.0.1:8000';
let falhas = 0; const ok = (c, m) => { console.log((c ? 'OK   ' : 'FALHA') + ' ' + m); if (!c) falhas++; };
const sql = (q) => execSync(`mysql perfeita --default-character-set=utf8mb4 -N -e "${q.replace(/"/g, '\\"')}"`).toString().trim();
async function entrar(p, email) { await p.goto(BASE + '/entrar'); await p.fill('#email', email); await p.fill('#senha', 'Demo@2026'); await p.click('button[type=submit]'); await p.waitForLoadState(); }

(async () => {
  const b = await chromium.launch();
  const erros = [];
  const nova = async () => { const p = await (await b.newContext({ viewport: { width: 1440, height: 900 } })).newPage(); p.on('pageerror', e => erros.push(e.message)); await p.route(/open-meteo|jsdelivr/, r => r.fulfill({ body: '{}' })); return p; };
  sql('insert into comunicado_leituras (comunicado_id, usuario_id) select c.id, u.id from comunicados c, usuarios u'); // começa sem os da demonstração

  // 1. Admin emite um comunicado importante só para Televendas (com HTML no texto, que deve ser escapado)
  const adm = await nova();
  await entrar(adm, 'admin@demo.local');
  if (await adm.locator('[data-novidades-ok]').count()) await adm.click('[data-novidades-ok]');
  await adm.goto(BASE + '/comunicados');
  await adm.fill('#com-titulo', 'Reunião de Televendas sexta 9h');
  await adm.fill('#com-texto', 'Pauta: metas do mês.\n<script>alert(1)</script><b>negrito</b>');
  await adm.check(`[data-form-comunicado] input[name="funcoes[]"][value="${sql("select id from funcoes where slug='televendas'")}"]`);
  await adm.selectOption('#com-prioridade', 'importante');
  await Promise.all([adm.waitForNavigation(), adm.click('[data-form-comunicado] button[type=submit]')]);
  const id = sql("select id from comunicados where titulo='Reunião de Televendas sexta 9h'");
  ok(/^\d+$/.test(id), 'comunicado emitido');
  ok(sql(`select count(*) from auditoria where entidade='comunicado' and entidade_id=${id} and acao='emitir'`) === '1', 'registrado na auditoria');
  const tele = Number(sql("select count(distinct uf.usuario_id) from usuario_funcoes uf join funcoes f on f.id=uf.funcao_id join usuarios u on u.id=uf.usuario_id where f.slug='televendas' and u.ativo=1"));
  ok(sql(`select count(*) from notificacoes where tipo='comunicado' and titulo like '%Reunião de Televendas%'`) === String(tele), `aviso no sino de quem é de Televendas (${tele})`);

  // 2. Televendas vê a faixa em destaque, abre e marca como lido
  const ven = await nova();
  await entrar(ven, 'televendas1@demo.local');
  const faixa = ven.locator('[data-com-lugar] .com-faixa');
  ok(await faixa.count() === 1 && (await faixa.getAttribute('class')).includes('importante'), 'faixa em destaque (importante) no topo');
  ok((await faixa.innerText()).includes('Reunião de Televendas'), 'título na faixa');
  await faixa.click();
  ok(await ven.locator('[data-com-janela]').isVisible(), 'clicar abre a janela com o texto');
  const html = await ven.locator('[data-com-conteudo] .com-texto').innerHTML();
  ok(!html.includes('<script') && !html.includes('<b>') && html.includes('&lt;b&gt;'), 'texto escapado (HTML não roda)');
  await ven.click('[data-com-ok]');
  await ven.waitForTimeout(500);
  ok(await ven.locator('[data-com-lugar] .com-faixa').count() === 0, '"Li e entendi" tira da faixa');
  ok(sql(`select count(*) from comunicado_leituras where comunicado_id=${id}`) === '1', 'leitura gravada');
  await ven.reload();
  ok(await ven.locator('[data-com-lugar] .com-faixa').count() === 0, '... e não volta ao recarregar');

  // 3. Produção não recebe
  const prod = await nova();
  await entrar(prod, 'producao@demo.local');
  ok(await prod.locator('[data-com-lugar] .com-faixa').count() === 0, 'Produção não vê o comunicado de Televendas');

  // 4. Toda a empresa: aparece para todos, inclusive Produção (sem recarregar: pelo resumo do sino)
  await adm.goto(BASE + '/comunicados');
  await adm.fill('#com-titulo', 'Feriado na segunda');
  await adm.fill('#com-texto', 'Não haverá expediente.');
  await adm.check('[data-toda-empresa]');
  await Promise.all([adm.waitForNavigation(), adm.click('[data-form-comunicado] button[type=submit]')]);
  await prod.evaluate(() => window.__oneAtualizarNotificacoes && window.__oneAtualizarNotificacoes());
  await prod.waitForTimeout(800);
  ok((await prod.locator('[data-com-lugar]').innerText()).includes('Feriado na segunda'), '"Toda a empresa" aparece para a Produção sem recarregar');

  // 5. Leitura: X de Y; encerrar tira de todos
  await adm.goto(BASE + '/comunicados');
  const linha = adm.locator(`[data-comunicado="${id}"]`);
  ok((await linha.locator('[data-leitura]').innerText()) === `1 de ${tele} leram`, `"1 de ${tele} leram"`);
  const fer = sql("select id from comunicados where titulo='Feriado na segunda'");
  adm.once('dialog', (d) => d.accept());
  await Promise.all([adm.waitForNavigation(), adm.locator(`[data-comunicado="${fer}"] form button`).click()]);
  await prod.reload();
  ok(!(await prod.locator('[data-com-lugar]').innerText()).includes('Feriado'), 'encerrado: sai do topo de todos');

  // 6. Validade: com fim no passado não aparece
  sql("insert into comunicados (titulo, texto, toda_empresa, inicio, fim) values ('Antigo', 'x', 1, now() - interval 10 day, now() - interval 1 day)");
  await prod.reload();
  ok(!(await prod.locator('[data-com-lugar]').innerText()).includes('Antigo'), 'vencido não aparece');

  // 7. Sem permissão não emite
  const tok = await ven.locator('meta[name=csrf]').getAttribute('content');
  const r = await ven.request.post(BASE + '/comunicados', { form: { titulo: 'x', texto: 'y', toda_empresa: '1' }, headers: { 'X-CSRF-Token': tok } });
  ok(r.status() === 403 && sql("select count(*) from comunicados where titulo='x'") === '0', 'vendedor não emite (403)');

  // 8. Apelido: só para a própria pessoa; para os colegas, o nome do funcionário
  sql("update usuarios set apelido='Tatá', data_nascimento=concat('1990-', date_format(curdate(), '%m-%d')) where email='televendas1@demo.local'");
  await ven.goto(BASE + '/');
  ok((await ven.locator('.topo .usuario-nome strong').innerText()) === 'Tatá', 'no topo, a pessoa vê o próprio apelido');
  await prod.goto(BASE + '/');
  const aniver = await prod.locator('[data-card=aniversariantes]').innerText();
  ok(aniver.includes('Televendas 1 (demo)') && !aniver.includes('Tatá'), 'nos aniversariantes, os colegas veem o nome do funcionário');

  ok(erros.length === 0, 'sem erros de JavaScript ' + erros.join(' / '));
  await b.close();
  console.log(falhas ? `\n${falhas} FALHA(S)` : '\nTudo certo');
  process.exit(falhas ? 1 : 0);
})();
