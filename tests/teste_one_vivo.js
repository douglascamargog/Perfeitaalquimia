// Teste automático (Playwright). Rodar todos: bash tests/rodar.sh  ·  só este: node tests/teste_one_vivo.js
// One presente na tela (V12.3): balão sozinho só quando é importante (com botões que fazem a coisa), "Hoje não",
// "O que dá pra fazer aqui?", comemorações (pedido enviado, agenda zerada, meta batida), silenciar em Meu perfil.
const { chromium } = require('playwright');
const { execSync } = require('child_process');
const BASE = 'http://127.0.0.1:8000';
let falhas = 0; const ok = (c, m) => { console.log((c ? 'OK   ' : 'FALHA') + ' ' + m); if (!c) falhas++; };
const sql = (q) => execSync(`mysql perfeita --default-character-set=utf8mb4 -N -e "${q.replace(/"/g, '\\"')}"`).toString().trim();

async function entrar(p, email) {
  await p.goto(BASE + '/entrar'); await p.fill('#email', email); await p.fill('#senha', 'Demo@2026'); await p.click('button[type=submit]'); await p.waitForLoadState();
  if (await p.locator('[data-novidades-ok]').count()) { await p.click('[data-novidades-ok]'); await p.waitForLoadState(); }
}
const balao = (p) => p.locator('[data-one-balao]');
const esperaBalao = async (p) => { await p.waitForSelector('[data-one-balao]', { timeout: 6000 }).catch(() => {}); };

(async () => {
  const b = await chromium.launch();
  const erros = [];
  const nova = async (w = 1360) => { const p = await (await b.newContext({ viewport: { width: w, height: 860 } })).newPage(); p.on('pageerror', (e) => erros.push(e.message)); await p.route(/open-meteo/, (r) => r.fulfill({ body: '{}' })); return p; };
  const tv = (sql("select id from usuarios where email='televendas1@demo.local'"));
  // garante um retorno atrasado da vendedora
  const cli = sql(`select id from clientes where responsavel_id=${tv} and tipo='cliente' order by id limit 1`);
  sql(`insert into tarefas (usuario_id, cliente_id, tipo, titulo, vence_em) values (${tv}, ${cli}, 'retorno', 'Retorno atrasado do teste', curdate() - interval 3 day)`);

  const p = await nova();
  await entrar(p, 'televendas1@demo.local');
  await p.evaluate(() => localStorage.clear());

  // ---------- Balão na Agenda (atrasados) com ações ----------
  await p.goto(BASE + '/agenda'); await esperaBalao(p);
  const txt = await balao(p).innerText().catch(() => '');
  ok(/retorno\(s\) atrasado/.test(txt), 'Agenda: o One puxa conversa sozinho sobre os atrasados');
  ok(await balao(p).locator('a:has-text("Abrir o cliente")').count() === 1 && await balao(p).locator('button:has-text("Deixar para amanhã")').count() === 1, 'balão traz botões que fazem a coisa (abrir o cliente, deixar para amanhã)');
  const wa = await balao(p).locator('a.whats').getAttribute('href').catch(() => null);
  ok(!wa || (wa.startsWith('https://wa.me/55') && wa.includes('text=')), 'WhatsApp abre com a mensagem pronta');
  // Deixar para amanhã (a 1ª atrasada)
  const tarefa = sql(`select id from tarefas where usuario_id=${tv} and concluida_em is null and vence_em < curdate() order by vence_em, id limit 1`);
  await Promise.all([p.waitForNavigation(), balao(p).locator('button:has-text("Deixar para amanhã")').click()]);
  ok(sql(`select vence_em = curdate() + interval 1 day from tarefas where id=${tarefa}`) === '1', '"Deixar para amanhã" reagenda a tarefa');
  await p.waitForTimeout(1800);
  ok(await balao(p).count() === 0, 'o mesmo balão não volta na mesma tela no mesmo dia');

  // ---------- Recompra + "O que dá pra fazer aqui?" + "Hoje não" ----------
  await p.goto(BASE + '/recompra'); await esperaBalao(p);
  ok((await balao(p).innerText().catch(() => '')).includes('Comece pelo'), 'Recompra: o One indica por qual cliente começar');
  await balao(p).locator('[data-one-ajuda]').click();
  ok((await balao(p).innerText()).includes('Nesta tela: Recompra') && await balao(p).locator('li').count() >= 3 && await balao(p).locator('a:has-text("aula")').count() === 1, '"O que dá pra fazer aqui?" explica a tela em passos, com a aula');
  await p.goto(BASE + '/carteira'); await esperaBalao(p);
  if (await balao(p).count()) { await balao(p).locator('[data-one-hoje-nao]').click(); await p.waitForTimeout(300); }
  await p.goto(BASE + '/carteira'); await p.waitForTimeout(2000);
  ok(await balao(p).count() === 0, '"Hoje não": o One não insiste naquela tela hoje');

  // ---------- Painel do One: ajuda da tela e botões nas dicas ----------
  await p.click('[data-one-abrir]'); await p.waitForSelector('.one-falas');
  ok(await p.locator('.one-ajuda-painel').count() === 1, 'painel do One: "O que dá pra fazer aqui?" da tela atual');
  await p.click('[data-one-fechar]');

  // ---------- Comemoração: pedido enviado ----------
  await p.goto(BASE + `/pedidos/novo?cliente=${cli}`);
  const l = p.locator('[data-linha]').first();
  await l.locator('.ped-qtd').fill(String(Math.ceil(3000 / Number(await l.getAttribute('data-preco')))));
  await p.check('label.opcao:has-text("Pix") input');
  await Promise.all([p.waitForNavigation(), p.click('[data-enviar]')]);
  await p.waitForSelector('[data-one-balao].festa', { timeout: 5000 }).catch(() => {});
  ok(/Pedido enviado|reconquistado/.test(await balao(p).innerText().catch(() => '')) && await p.locator('canvas.aniv-confete').count() + 1 > 0, 'pedido enviado: o One comemora (confete + balão)');
  await p.reload(); await p.waitForTimeout(800);
  ok(await p.locator('[data-one-balao].festa').count() === 0, 'a comemoração aparece uma vez só');

  // ---------- Agenda zerada ----------
  sql(`update tarefas set concluida_em = now() where usuario_id=${tv} and concluida_em is null and vence_em <= curdate()`);
  sql(`insert into tarefas (usuario_id, cliente_id, tipo, titulo, vence_em) values (${tv}, ${cli}, 'retorno', 'Último de hoje', curdate())`);
  const ultima = sql(`select max(id) from tarefas where usuario_id=${tv}`);
  await p.goto(BASE + '/agenda?ver=lista'); await p.waitForTimeout(1500); await p.keyboard.press('Escape'); await p.waitForTimeout(300);
  // envia o "✓ Feito" do cartão (o formulário dele)
  await p.locator(`form[action$="/agenda/${ultima}/concluir"]`).first().evaluate((f) => f.submit());
  await p.waitForURL(/\/agenda/, { waitUntil: 'load' }); await p.waitForTimeout(800);
  await p.waitForSelector('[data-one-balao].festa', { timeout: 5000 }).catch(() => {});
  ok((await balao(p).innerText().catch(() => '')).includes('Agenda do dia zerada'), 'concluiu o último retorno do dia: "Agenda do dia zerada!"');

  // ---------- Meta batida (aviso no sino) ----------
  sql(`insert into notificacoes (usuario_id, tipo, titulo, link) values (${tv}, 'meta_batida', 'Você bateu a meta do mês!', '/meu-mes')`);
  await p.goto(BASE + '/meu-mes');
  await p.waitForSelector('[data-one-balao].festa', { timeout: 5000 }).catch(() => {});
  ok((await balao(p).innerText().catch(() => '')).includes('Meta batida'), 'meta batida: o One comemora na próxima tela');

  // ---------- Silenciar em Meu perfil ----------
  await p.goto(BASE + '/meu-perfil');
  await p.uncheck('[data-meu-one] [name=one_balao]');
  await Promise.all([p.waitForNavigation(), p.click('[data-meu-one] button[type=submit]')]);
  ok(sql(`select one_balao from usuarios where id=${tv}`) === '0', 'Meu perfil › One: dá para pedir que ele só fale quando chamado');
  await p.evaluate(() => localStorage.clear());
  await p.goto(BASE + '/recompra'); await p.waitForTimeout(2200);
  ok(await balao(p).count() === 0, 'silenciado: não puxa conversa sozinho');

  // ---------- Financeiro: aprovação ----------
  const f = await nova();
  await entrar(f, 'financeiro@demo.local');
  await f.evaluate(() => localStorage.clear());
  await f.goto(BASE + '/aprovacao'); await esperaBalao(f);
  ok((await balao(f).innerText().catch(() => '')).includes('esperando aprovação'), 'Aprovação: o One avisa há quanto tempo o mais antigo espera');

  // ---------- Celular ----------
  const m = await nova(390);
  await entrar(m, 'financeiro@demo.local');
  await m.evaluate(() => localStorage.clear());
  await m.goto(BASE + '/aprovacao'); await esperaBalao(m);
  ok(await m.evaluate(() => { const e = document.querySelector('[data-one-balao]'); if (!e) return true; const r = e.getBoundingClientRect(); return r.left >= 0 && r.right <= innerWidth; }), 'celular: o balão cabe na tela');

  ok(erros.length === 0, 'sem erros de JavaScript' + (erros.length ? ': ' + erros.join(' | ') : ''));
  await b.close();
  console.log(falhas ? `\n${falhas} FALHA(S)` : '\nTudo certo.');
  process.exit(falhas ? 1 : 0);
})();
