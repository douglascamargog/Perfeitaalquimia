// Teste automático (Playwright). Rodar todos: bash tests/rodar.sh  ·  só este: node tests/teste_gestora.js
// Gestor(a) de setor: coordena o Televendas e também vende (própria carteira + carteira da equipe do setor).
const { chromium } = require('playwright');
const { execSync } = require('child_process');
const BASE = 'http://127.0.0.1:8000';
let falhas = 0; const ok = (c, m) => { console.log((c ? 'OK   ' : 'FALHA') + ' ' + m); if (!c) falhas++; };
const sql = (q) => execSync(`mysql perfeita --default-character-set=utf8mb4 -N -e "${q.replace(/"/g, '\\"')}"`).toString().trim();
async function entrar(p, email) {
  await p.goto(BASE + '/entrar'); await p.fill('#email', email); await p.fill('#senha', 'Demo@2026'); await p.click('button[type=submit]'); await p.waitForLoadState();
  if (await p.locator('[data-novidades-ok]').count()) { await p.click('[data-novidades-ok]'); await p.waitForLoadState(); }
}

(async () => {
  const b = await chromium.launch();
  const erros = [];
  const nova = async () => { const p = await (await b.newContext({ viewport: { width: 1300, height: 900 } })).newPage(); p.on('pageerror', e => erros.push(e.message)); await p.route(/open-meteo|jsdelivr/, r => r.fulfill({ body: '{}' })); return p; };
  const tv = sql("select id from funcoes where slug='televendas'");
  const ext = sql("select id from funcoes where slug='externo'");
  const eu = sql("select id from usuarios where email='gestora.televendas@demo.local'");
  const tv1 = sql("select id from usuarios where email='televendas1@demo.local'");
  const tv2 = sql("select id from usuarios where email='televendas2@demo.local'");

  ok(sql(`select concat(perfil_id=(select id from perfis where slug='vendedor'),'|',gestor) from usuario_funcoes uf join usuarios u on u.id=uf.usuario_id where u.id=${eu}`) === '1|1', 'demonstração: gestora com perfil Vendedor e "gestor(a) do setor" no Televendas');
  const p = await nova();
  await entrar(p, 'gestora.televendas@demo.local');

  // ---------- 1. Menu: só o Televendas ----------
  const secoes = await p.$$eval('[data-menu-secao]', (l) => l.map((s) => s.dataset.menuSecao));
  ok(secoes.includes('setor:televendas') && !secoes.includes('setor:externo') && !secoes.includes('setor:terceirizacao'), 'gestora vê só o setor que gerencia');

  // ---------- 2. Carteira: própria + equipe do setor, nada de outro setor ----------
  await p.goto(BASE + '/carteira/lista?setor=televendas');
  const esperado = sql(`select count(*) from clientes where excluido_em is null and funcao_id=${tv}`);
  ok((await p.content()).includes(esperado), `Carteira do Televendas: ${esperado} clientes (dela e da equipe)`);
  const extCli = sql(`select id from clientes where funcao_id=${ext} and excluido_em is null limit 1`);
  const r = await p.goto(BASE + `/carteira/clientes/${extCli}`);
  ok(r.status() !== 200 || !(await p.content()).includes('ficha'), 'cliente do Externo não abre para a gestora do Televendas');
  const doTv1 = sql(`select id from clientes where responsavel_id=${tv1} and excluido_em is null limit 1`);
  ok((await p.goto(BASE + `/carteira/clientes/${doTv1}`)).status() === 200, 'cliente de alguém da equipe abre a página');
  // Filtro "Responsável" com a equipe do setor
  await p.goto(BASE + '/carteira/lista?setor=televendas');
  const opcoes = await p.$$eval('select[name=responsavel] option', (o) => o.map((x) => x.textContent));
  ok(opcoes.some((t) => t.includes('Televendas 1')) && !opcoes.some((t) => t.includes('Externo')), 'filtro de responsável mostra só a equipe do Televendas');

  // ---------- 3. Pedidos, Agenda e Recompra do setor ----------
  await p.goto(BASE + '/pedidos?setor=televendas');
  const ids = await p.$$eval('table tbody tr td:first-child a', (t) => t.map((x) => x.innerText.replace('#', '').trim()));
  ok(ids.length > 0 && sql(`select count(*) from pedidos p join clientes c on c.id=p.cliente_id where p.id in (${ids.join(',')}) and c.funcao_id<>${tv}`) === '0'
    && sql(`select count(distinct c.responsavel_id) from pedidos p join clientes c on c.id=p.cliente_id where p.id in (${ids.join(',')})`) !== '1', `Pedidos: ${ids.length} da equipe do Televendas (nenhum de outro setor)`);
  await p.goto(BASE + '/agenda?setor=televendas');
  ok(await p.locator('select[name=pessoa]').count() === 1, 'Agenda: escolhe a própria ou a de alguém da equipe');
  const agEquipe = await p.locator('table tbody tr').count();
  await p.goto(BASE + `/agenda?setor=televendas&pessoa=${eu}`);
  const agMinha = await p.locator('table tbody tr').count();
  ok(agEquipe > agMinha, `Agenda da equipe (${agEquipe}) e a própria (${agMinha})`);
  await p.goto(BASE + `/agenda?pessoa=${sql("select id from usuarios where email='externo1@demo.local'")}`);
  ok(await p.locator('table tbody tr').count() === agEquipe, 'pessoa de outro setor é ignorada (volta para a equipe)');
  await p.goto(BASE + '/recompra?setor=televendas');
  const rec = await p.locator('[data-recompra]').count();
  ok(rec > 0 && (await p.locator('.rc-item').first().innerText()).includes('Televendas'), `Recompra do setor com o nome de quem atende (${rec})`);

  // ---------- 4. Meu mês continua dela ----------
  await p.goto(BASE + '/meu-mes?setor=televendas');
  ok((await p.content()).includes('Gestora Televendas') || (await p.locator('h1').count()) > 0, 'Meu mês abre (números dela)');

  // ---------- 5. Transferir cliente dentro do setor ----------
  await p.goto(BASE + `/carteira/clientes/${doTv1}`);
  const csrf = await p.evaluate(() => document.querySelector('meta[name=csrf]').content);
  const atual = sql(`select atualizado_em from clientes where id=${doTv1}`);
  const t1 = await p.request.post(BASE + `/carteira/clientes/${doTv1}/ficha`, { form: { _csrf: csrf, responsavel_id: tv2, atualizado_em: atual }, headers: { Accept: 'application/json' } });
  ok(t1.status() === 200 && sql(`select concat(responsavel_id,'|',funcao_id) from clientes where id=${doTv1}`) === `${tv2}|${tv}`, 'gestora passa o cliente para outra pessoa do Televendas');
  const ext1 = sql("select id from usuarios where email='externo1@demo.local'");
  const t2 = await p.request.post(BASE + `/carteira/clientes/${doTv1}/ficha`, { form: { _csrf: csrf, responsavel_id: ext1, atualizado_em: sql(`select atualizado_em from clientes where id=${doTv1}`) }, headers: { Accept: 'application/json' } });
  ok(sql(`select responsavel_id from clientes where id=${doTv1}`) === tv2, 'não passa cliente para alguém de fora do setor');

  // ---------- 6. Vendedor comum continua só com a própria carteira ----------
  const v = await nova();
  await entrar(v, 'televendas1@demo.local');
  await v.goto(BASE + '/carteira/lista?setor=televendas');
  const meus = sql(`select count(*) from clientes where excluido_em is null and responsavel_id=${tv1}`);
  ok((await v.content()).includes(meus) && !(await v.content()).includes('>' + esperado + '<'), `vendedor comum vê só os ${meus} dele`);
  ok((await v.goto(BASE + `/carteira/clientes/${sql(`select id from clientes where responsavel_id=${eu} limit 1`)}`)).status() !== 200, 'vendedor comum não abre cliente da gestora');

  // ---------- 7. Usuários: marcar gestor(a) do setor ----------
  const adm = await nova();
  await entrar(adm, 'admin@demo.local');
  await adm.goto(BASE + `/usuarios/${tv2}/editar`);
  await adm.check(`input[name="gestor[]"][value="${tv}"]`);
  await Promise.all([adm.waitForNavigation(), adm.click('button:has-text("Salvar alterações")')]);
  ok(sql(`select gestor from usuario_funcoes where usuario_id=${tv2} and funcao_id=${tv}`) === '1', 'admin marca "gestor(a) do setor" em Usuários');
  ok(await adm.locator(`input[name="gestor[]"][value="${sql("select id from funcoes where slug='marketing'")}"]`).count() === 0, 'a opção só aparece nas funções que são setor de venda');

  ok(erros.length === 0, 'nenhum erro de JavaScript' + (erros.length ? ': ' + erros.join(' / ') : ''));
  await b.close();
  console.log(falhas ? `\n${falhas} FALHA(S)` : '\nTudo certo.');
  process.exit(falhas ? 1 : 0);
})();
