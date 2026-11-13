// Teste automático (Playwright). Rodar todos: bash tests/rodar.sh  ·  só este: node tests/teste_setores.js
// Setores de venda (Televendas, Externo, Terceirização, Prospecção e Reativação) e o menu com submenus.
const { chromium } = require('playwright');
const { execSync } = require('child_process');
const BASE = 'http://127.0.0.1:8000';
let falhas = 0; const ok = (c, m) => { console.log((c ? 'OK   ' : 'FALHA') + ' ' + m); if (!c) falhas++; };
const sql = (q) => execSync(`mysql perfeita --default-character-set=utf8mb4 -N -e "${q.replace(/"/g, '\\"')}"`).toString().trim();
async function entrar(p, email) {
  await p.goto(BASE + '/entrar'); await p.fill('#email', email); await p.fill('#senha', 'Demo@2026'); await p.click('button[type=submit]'); await p.waitForLoadState();
  if (await p.locator('[data-novidades-ok]').count()) { await p.click('[data-novidades-ok]'); await p.waitForLoadState(); }
}
const secoes = (p) => p.$$eval('[data-menu-secao]', (l) => l.map((s) => s.dataset.menuSecao));

(async () => {
  const b = await chromium.launch();
  const erros = [];
  const nova = async (w = 1300) => { const p = await (await b.newContext({ viewport: { width: w, height: 900 } })).newPage(); p.on('pageerror', e => erros.push(e.message)); await p.route(/open-meteo|jsdelivr/, r => r.fulfill({ body: '{}' })); return p; };
  const fn = (slug) => sql(`select id from funcoes where slug='${slug}'`);

  // ---------- 1. Quem vê quais setores ----------
  ok(sql('select group_concat(slug order by slug) from funcoes where setor_venda=1') === 'externo,prospeccao_reativacao,televendas,terceirizacao', 'instalação: 4 setores de venda');
  const tv = await nova();
  await entrar(tv, 'televendas1@demo.local');
  let s = await secoes(tv);
  ok(s.includes('setor:televendas') && !s.includes('setor:externo') && !s.includes('setor:terceirizacao') && !s.includes('setor:prospeccao_reativacao'), 'Televendas vê só o setor Televendas');
  ok(s.includes('Financeiro') && s.includes('Produtos') && s.includes('Administração'), 'demais grupos continuam no menu (com cadeado quando não tem acesso)');
  ok(await tv.locator('[data-menu-secao].aberta').count() === 0, 'no Início, os grupos começam fechados');
  const ges = await nova();
  await entrar(ges, 'gestor@demo.local');
  s = await secoes(ges);
  ok(['televendas', 'externo', 'terceirizacao', 'prospeccao_reativacao'].every((x) => s.includes('setor:' + x)), 'Gestor vê os quatro setores');
  const ext = await nova();
  await entrar(ext, 'externo1@demo.local');
  s = await secoes(ext);
  ok(s.includes('setor:externo') && !s.includes('setor:televendas'), 'Externo vê só o setor Externo');
  ok(await ext.locator('[data-menu-secao="setor:externo"] a[data-url="/app"]').count() === 1, 'App do externo fica dentro do setor Externo');

  // ---------- 2. Abrir e fechar ----------
  await ges.click('[data-menu-secao="setor:externo"] .menu-secao-botao');
  ok(await ges.locator('[data-menu-secao="setor:externo"] .menu-secao-botao').getAttribute('aria-expanded') === 'true' && await ges.locator('[data-menu-secao="setor:externo"].aberta').count() === 1, 'clique abre o setor');
  await ges.click('[data-menu-secao="setor:externo"] a[data-url="/carteira"]');
  await ges.waitForLoadState();
  ok(await ges.locator('[data-menu-secao="setor:externo"].aberta a.ativo[data-url="/carteira"]').count() === 1, 'a página atual deixa o setor aberto e a aba marcada');
  ok((await ges.locator('[data-selo-setor]').innerText()).includes('Externo'), 'selo do setor ao lado do título');
  await ges.click('[data-menu-secao="setor:externo"] .menu-secao-botao');
  ok(await ges.locator('[data-menu-secao="setor:externo"].aberta').count() === 0, 'clique de novo fecha');

  // ---------- 3. Cada setor, a sua carteira ----------
  for (const setor of ['televendas', 'externo']) {
    await ges.goto(BASE + `/carteira/lista?setor=${setor}`);
    const n = sql(`select count(*) from clientes where excluido_em is null and funcao_id=${fn(setor)}`);
    ok((await ges.content()).includes(n), `Carteira de ${setor}: ${n} clientes`);
    const responsaveis = await ges.$$eval('table tbody tr td:nth-child(6)', (tds) => [...new Set(tds.map((t) => t.innerText.trim()))]);
    ok(responsaveis.length >= 1 && responsaveis.every((r) => r.toLowerCase().includes(setor === 'externo' ? 'externo' : 'televendas')), `Carteira de ${setor}: só clientes do setor (${responsaveis.join(', ')})`);
  }
  // Pedidos do setor
  await ges.goto(BASE + '/pedidos?setor=terceirizacao');
  const pedTerc = Number(sql(`select count(*) from pedidos p join clientes c on c.id=p.cliente_id where c.funcao_id=${fn('terceirizacao')}`));
  const linhas = await ges.locator('table tbody tr').count();
  ok(linhas === Math.min(pedTerc, 30), `Pedidos de Terceirização: só os do setor (${linhas})`);
  // Recompra e Agenda do setor
  await ges.goto(BASE + '/recompra?setor=externo');
  const rec = await ges.$$eval('[data-recompra]', (l) => l.length);
  await ges.goto(BASE + '/recompra?setor=');
  const recTodos = await ges.$$eval('[data-recompra]', (l) => l.length);
  ok(rec < recTodos, `Recompra do Externo (${rec}) menor que a de todos (${recTodos})`);
  ok(await ges.locator('[data-selo-setor]').count() === 0, '"✕" no selo volta a mostrar todos os setores');
  await ges.goto(BASE + '/agenda?setor=televendas');
  const agTv = await ges.locator('table tbody tr').count();
  const agEsperado = Number(sql(`select count(*) from tarefas t left join clientes c on c.id=t.cliente_id and c.excluido_em is null where t.concluida_em is null and (t.cliente_id is null or c.funcao_id=${fn('televendas')})`));
  ok(agTv === Math.min(agEsperado, 500), `Agenda de Televendas: ${agTv} tarefas`);

  // ---------- 4. Setor que a pessoa não vê é ignorado ----------
  await tv.goto(BASE + '/carteira/lista?setor=externo');
  ok(await tv.locator('[data-selo-setor]').count() === 0 || (await tv.locator('[data-selo-setor]').innerText()).includes('Televendas'), 'Televendas não entra no setor Externo pelo endereço');
  const meus = sql(`select count(*) from clientes where excluido_em is null and responsavel_id=(select id from usuarios where email='televendas1@demo.local')`);
  ok((await tv.content()).includes(meus), 'vendedor continua vendo a própria carteira');

  // ---------- 5. Funções: desligar um setor ----------
  sql(`update funcoes set setor_venda=0 where slug='prospeccao_reativacao'`);
  await ges.goto(BASE + '/');
  s = await secoes(ges);
  ok(!s.includes('setor:prospeccao_reativacao') && await ges.locator('[data-menu-secao="Comercial"] a[data-url="/prospeccao"]').count() === 1, 'setor desligado em Funções: as abas dele vão para o grupo Comercial');
  const pro = await nova();
  await entrar(pro, 'producao@demo.local');
  s = await secoes(pro);
  ok(!s.some((x) => x.startsWith('setor:')) && s.includes('Comercial'), 'Produção não vê setores de venda (a Terceirização fica em Comercial)');
  sql(`update funcoes set setor_venda=1 where slug='prospeccao_reativacao'`);

  // ---------- 6. Celular ----------
  const cel = await nova(390);
  await entrar(cel, 'gestor@demo.local');
  await cel.click('.abrir-menu');
  await cel.click('[data-menu-secao="setor:televendas"] .menu-secao-botao');
  ok(await cel.locator('[data-menu-secao="setor:televendas"] a[data-url="/carteira"]').isVisible(), 'celular: o setor abre dentro da gaveta do menu');
  ok(await cel.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth + 1), 'celular: nada passa da largura');

  ok(erros.length === 0, 'nenhum erro de JavaScript' + (erros.length ? ': ' + erros.join(' / ') : ''));
  await b.close();
  console.log(falhas ? `\n${falhas} FALHA(S)` : '\nTudo certo.');
  process.exit(falhas ? 1 : 0);
})();
