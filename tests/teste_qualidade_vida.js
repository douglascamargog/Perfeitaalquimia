// Teste automático (Playwright). Rodar todos: bash tests/rodar.sh  ·  só este: bash tests/rodar.sh qualidade_vida
// V15.6 (qualidade de vida): a conversa com o One não se perde ao trocar de tela e reabre depois de clicar num link dela;
// "Abrir página completa da loja" na gaveta da Cobrança; nome da loja com link em Pedidos, Aprovação e Meu mês.
const { chromium } = require('playwright');
const { execSync } = require('child_process');
const BASE = 'http://127.0.0.1:8000';
let falhas = 0; const ok = (c, m) => { console.log((c ? 'OK   ' : 'FALHA') + ' ' + m); if (!c) falhas++; };
const sql = (q) => execSync(`mysql perfeita --default-character-set=utf8mb4 -N -e "${q.replace(/"/g, '\\"')}"`).toString().trim();

(async () => {
  const b = await chromium.launch(); const erros = [];
  const entrar = async (email) => {
    const p = await (await b.newContext()).newPage(); p.on('pageerror', (e) => erros.push(e.message)); p.on('dialog', (d) => d.accept());
    await p.goto(BASE + '/entrar'); await p.fill('#email', email); await p.fill('#senha', 'Demo@2026'); await p.click('button[type=submit]'); await p.waitForLoadState();
    return p;
  };
  const abrirConversa = async (p) => {
    if (await p.locator('#one-painel').isHidden()) await p.click('[data-one-abrir]');
    await p.click('[data-one-aba="perguntar"]');
  };
  try {
    // ---------------- 1. Conversa com o One
    const a = await entrar('admin@demo.local');
    await a.goto(BASE + '/carteira');
    await abrirConversa(a);
    await a.fill('[data-one-pergunte] [name=pergunta]', 'Como cadastro um cliente novo?');
    await a.click('[data-one-pergunte] button');
    await a.waitForFunction(() => document.querySelectorAll('[data-one-conversa] .one-msg.one:not(.pensando)').length >= 1, null, { timeout: 30000 });
    const antes = await a.locator('[data-one-conversa] .one-msg').count();
    await a.goto(BASE + '/agenda');
    await abrirConversa(a);
    ok(await a.locator('[data-one-conversa] .one-msg').count() === antes && (await a.locator('[data-one-conversa]').innerText()).includes('Como cadastro um cliente novo?'),
      'trocar de tela não apaga a conversa com o One');

    // Link dentro da resposta: na tela nova o painel reabre na conversa
    await a.evaluate(() => {
      const k = Object.keys(sessionStorage).find((x) => x.startsWith('one-conversa:'));
      const l = JSON.parse(sessionStorage.getItem(k)); l.push({ quem: 'one', texto: 'Veja a [Recompra](/recompra).' }); sessionStorage.setItem(k, JSON.stringify(l));
    });
    await a.reload();
    await abrirConversa(a);
    await Promise.all([a.waitForNavigation(), a.click('[data-one-conversa] a[href$="/recompra"]')]);
    await a.waitForTimeout(400);
    ok(a.url().endsWith('/recompra') && await a.locator('#one-painel').isVisible() && await a.locator('[data-one-aba-painel="perguntar"]').isVisible()
      && (await a.locator('[data-one-conversa]').innerText()).includes('Como cadastro um cliente novo?'), 'clicar num link da conversa abre a tela e o One reabre na mesma conversa');
    await a.click('[data-one-limpar]');
    await a.goto(BASE + '/carteira');
    await abrirConversa(a);
    ok(await a.locator('[data-one-conversa] .one-msg').count() === 0, '"nova conversa" apaga a conversa guardada');

    // ---------------- 2. Gaveta da Cobrança e links da loja
    const caso = sql("select co.id from cobrancas co order by co.id limit 1");
    const cliCaso = sql(`select cliente_id from cobrancas where id=${caso}`);
    const f = await entrar('financeiro@demo.local');
    await f.goto(BASE + '/cobranca/lista');
    await f.locator(`[data-abrir-cliente="${caso}"]`).first().click();
    await f.waitForSelector('[data-pagina-loja]');
    ok((await f.locator('[data-pagina-loja]').getAttribute('href')).endsWith('/carteira/clientes/' + cliCaso), 'gaveta da Cobrança tem "Abrir página completa da loja"');
    ok((await f.goto(BASE + '/carteira/clientes/' + cliCaso)).status() === 200, 'quem cuida da Cobrança abre a página completa da loja do caso');
    const semCaso = sql('select c.id from clientes c where not exists (select 1 from cobrancas co where co.cliente_id = c.id) order by id limit 1');
    sql("update perfil_permissoes set permissao = 'pedidos.aprovar_x' where permissao = 'pedidos.aprovar' and perfil_id = (select id from perfis where slug = 'financeiro')");
    const f2 = await entrar('financeiro@demo.local');
    ok((await f2.goto(BASE + '/carteira/clientes/' + semCaso)).status() === 403, '...mas não abre loja sem caso de cobrança (sem outra permissão)');
    sql("update perfil_permissoes set permissao = 'pedidos.aprovar' where permissao = 'pedidos.aprovar_x'");

    // Pedido aguardando aprovação: nome da loja com link em Pedidos, no pedido e na Aprovação
    const tv1 = sql("select id from usuarios where email='televendas1@demo.local'");
    const cli = sql(`select id from clientes where responsavel_id=${tv1} order by id limit 1`);
    sql(`insert into pedidos (uuid, cliente_id, vendedor_id, tabela_preco_id, forma_pagamento_id, subtotal, total, situacao) values (UUID(), ${cli}, ${tv1}, 1, 1, 900, 900, 'aguardando_aprovacao')`);
    const ped = sql('select max(id) from pedidos');
    const v = await entrar('televendas1@demo.local');
    await v.goto(BASE + '/pedidos');
    ok(await v.locator(`a[data-link-loja][href$="/carteira/clientes/${cli}"]`).count() > 0, 'lista de Pedidos: nome da loja leva à página da loja');
    await v.goto(BASE + '/pedidos/' + ped);
    ok(await v.locator(`a[data-link-loja][href$="/carteira/clientes/${cli}"]`).count() === 1, 'pedido aberto: nome da loja com link');
    await f.goto(BASE + '/aprovacao');
    ok(await f.locator(`a[data-link-loja][href$="/carteira/clientes/${cli}"]`).count() > 0 && (await f.goto(BASE + '/carteira/clientes/' + cli)).status() === 200,
      'Aprovação: o Financeiro abre a loja do pedido para conferir antes de aprovar');
    ok(erros.length === 0, 'sem erros de JavaScript' + (erros.length ? ': ' + erros[0] : ''));
  } finally {
    await b.close();
  }
  process.exit(falhas ? 1 : 0);
})();
