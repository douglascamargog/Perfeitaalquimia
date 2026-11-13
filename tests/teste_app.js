// Teste automático (Playwright). Rodar todos: bash tests/rodar.sh  ·  só este: node tests/teste_app.js
const path = require('path');
const RECURSOS = path.join(__dirname, 'recursos');
const { chromium } = require('playwright');
const { execSync } = require('child_process');
const BASE = 'http://127.0.0.1:8000', DIR = process.argv[2] || path.join(__dirname, 'saida');
let falhas = 0; const ok = (c, m) => { console.log((c ? 'OK   ' : 'FALHA') + ' ' + m); if (!c) falhas++; };
const sql = (q) => execSync(`mysql perfeita --default-character-set=utf8mb4 -N -e "${q}"`).toString().trim();
const espera = (ms) => new Promise((r) => setTimeout(r, ms));
async function esperarAte(fn, ms = 8000) { const fim = Date.now() + ms; while (Date.now() < fim) { if (await fn()) return true; await espera(250); } return false; }

(async () => {
  const b = await chromium.launch();
  const ctx = await b.newContext({ viewport: { width: 390, height: 844 } }); const p = await ctx.newPage();
  const erros = []; p.on('pageerror', e => erros.push(e.message));
  let resposta = ''; p.on('dialog', d => d.accept(resposta));
  const ext = sql("select id from usuarios where email='externo1@demo.local'");

  // ---------- Entrar e baixar dados ----------
  await p.goto(BASE + '/app');
  await p.fill('#email', 'externo1@demo.local'); await p.fill('#senha', 'Demo@2026'); await p.click('button[type=submit]'); await p.waitForLoadState();
  ok(p.url().endsWith('/app'), 'login volta para o app');
  await p.waitForSelector('.ap-cliente');
  const esperado = sql(`select count(*) from clientes where responsavel_id=${ext} and excluido_em is null`);
  ok((await p.locator('.ap-contagem').first().innerText()).startsWith(esperado + ' '), `só a carteira do vendedor (${esperado} clientes)`);
  ok(await esperarAte(() => p.evaluate(() => !!navigator.serviceWorker.controller), 10000) || (await p.reload(), await esperarAte(() => p.evaluate(() => !!navigator.serviceWorker.controller))), 'service worker controlando o app');
  const man = await p.request.get(BASE + '/app/manifest.webmanifest');
  ok(man.ok() && (await man.json()).icons.length === 2, 'manifest com ícones (instalável)');
  await p.reload(); await p.waitForSelector('.ap-cliente'); await espera(800);

  // ---------- Offline ----------
  await ctx.setOffline(true);
  await p.reload(); await p.waitForSelector('.ap-cliente', { timeout: 10000 });
  ok((await p.locator('[data-rede]').innerText()).toLowerCase() === 'offline', 'app abre sem internet (cópia guardada) e mostra "offline"');
  const antes = Number(sql("select count(*) from pedidos where origem='app'"));
  const antesExt = Number(sql(`select count(*) from pedidos where origem='app' and vendedor_id=${ext}`));
  const clientes = await p.locator('.ap-cliente').evaluateAll((els) => els.map((e) => e.querySelector('[data-novo-pedido]').dataset.novoPedido));
  const semBoleto = async () => {};
  for (const idx of [0, 1]) {
    await p.click('[data-aba=clientes]');
    await p.locator(`[data-novo-pedido="${clientes[idx]}"]`).click();
    await p.waitForSelector('.ap-produtos li');
    const caixa = p.locator('.ap-produtos li').first().locator('input');
    await caixa.fill('40'); await caixa.dispatchEvent('input');
    await p.locator('.ap-forma input[type=radio]:not([disabled])').first().check();
    await p.click('[data-salvar]');
    await p.waitForSelector('.ap-fila');
  }
  resposta = 'Visita teste: cliente pediu catálogo novo';
  await p.click('[data-aba=clientes]'); await p.locator(`[data-visita="${clientes[2]}"]`).click(); await espera(500);
  await p.click('[data-aba=pedidos]'); await espera(300);
  ok((await p.locator('.ap-item .selo').allInnerTexts()).filter((t) => t === 'Aguardando envio').length === 3, '2 pedidos e 1 visita "aguardando envio" (offline)');
  ok((await p.locator('[data-pendentes]').innerText()) === '3', 'topo mostra 3 pendentes');
  ok(Number(sql("select count(*) from pedidos where origem='app'")) === antes, 'nada chegou ao servidor enquanto offline');
  await p.screenshot({ path: DIR + '/app-offline.png' });

  // ---------- Volta a internet ----------
  await ctx.setOffline(false);
  await p.evaluate(() => window.dispatchEvent(new Event('online')));
  ok(await esperarAte(() => Number(sql("select count(*) from pedidos where origem='app'")) === antes + 2, 10000), 'internet voltou: 2 pedidos enviados sozinhos');
  ok(sql(`select count(*) from cliente_eventos where cliente_id=${clientes[2]} and canal='Visita presencial' and descricao like 'Visita teste%'`) === '1', 'visita registrada na linha do tempo do cliente');
  await p.click('[data-aba=pedidos]'); await espera(500);
  ok((await p.locator('.ap-item .selo').allInnerTexts()).some((t) => t.includes('aguardando aprovação')), 'app mostra "Enviado · aguardando aprovação"');
  ok(Number(sql(`select count(*) from pedidos where origem='app' and vendedor_id=${ext}`)) === antesExt + 2, 'pedidos com origem "app" e vendedor = externo');

  // ---------- Reenvio não duplica ----------
  const fila = await p.evaluate(async () => { const { listarFila } = await import(document.querySelector('meta[name=base]').content + '/assets/app/banco.js'); return (await listarFila()).filter((i) => i.tipo === 'pedido'); });
  const csrf = await p.evaluate(async () => { const { lerPacote } = await import(document.querySelector('meta[name=base]').content + '/assets/app/banco.js'); return (await lerPacote()).csrf; });
  const r2 = await p.request.post(BASE + '/app/api/sincronizar', { data: { itens: fila.map((i) => ({ ...i, estado: 'pendente' })) }, headers: { 'X-CSRF-Token': csrf, Accept: 'application/json' } });
  ok(r2.ok() && Number(sql("select count(*) from pedidos where origem='app'")) === antes + 2, 'a mesma fila enviada de novo não duplica');
  const r3 = await p.request.post(BASE + '/app/api/sincronizar', { data: { itens: [{ uuid: '11111111-2222-4333-8444-555555555555', tipo: 'visita', cliente_id: clientes[2], resumo: 'x' }, { uuid: '11111111-2222-4333-8444-555555555555', tipo: 'visita', cliente_id: clientes[2], resumo: 'x' }] }, headers: { 'X-CSRF-Token': csrf, Accept: 'application/json' } });
  ok(sql(`select count(*) from cliente_eventos where JSON_UNQUOTE(JSON_EXTRACT(dados_json,'$.uuid'))='11111111-2222-4333-8444-555555555555'`) === '1', 'visita repetida não duplica');

  // ---------- Cliente de outro vendedor ----------
  const outro = sql(`select id from clientes where responsavel_id<>${ext} limit 1`);
  const r4 = await (await p.request.post(BASE + '/app/api/sincronizar', { data: { itens: [{ uuid: '22222222-2222-4333-8444-555555555555', tipo: 'pedido', cliente_id: outro, itens: { 1: 50 }, pagamento: '1:1' }] }, headers: { 'X-CSRF-Token': csrf, Accept: 'application/json' } })).json();
  ok(r4.resultados['22222222-2222-4333-8444-555555555555'].ok === false, 'cliente de outro vendedor é recusado');

  // ---------- Pedido abaixo do mínimo forçado ----------
  await p.evaluate(async (cli) => { const { salvarItem } = await import(document.querySelector('meta[name=base]').content + '/assets/app/banco.js');
    await salvarItem({ uuid: crypto.randomUUID(), tipo: 'pedido', estado: 'pendente', cliente_id: Number(cli), cliente_nome: 'Forçado', itens: { 1: 1 }, pagamento: '1:1', total: 10, qtd: 1, criado_em: new Date().toISOString() }); }, clientes[0]);
  await p.click('[data-aba=pedidos]'); await espera(300); await p.click('[data-enviar]'); await espera(1500);
  ok((await p.locator('.ap-erro').first().innerText()).includes('mínimo'), 'servidor recusa abaixo do mínimo: item fica "com erro" com o motivo');
  ok(await p.locator('[data-corrigir]').count() === 1, 'botão "Corrigir e reenviar"');

  // ---------- Aprovação e faturamento aparecem no app ----------
  const idApp = sql(`select id from pedidos where origem='app' and vendedor_id=${ext} order by id desc limit 1`);
  const fin = await (await b.newContext()).newPage();
  await fin.goto(BASE + '/entrar'); await fin.fill('#email', 'financeiro@demo.local'); await fin.fill('#senha', 'Demo@2026'); await fin.click('button[type=submit]'); await fin.waitForLoadState();
  await fin.goto(BASE + '/aprovacao'); await Promise.all([fin.waitForNavigation(), fin.locator('.ped-fila-item', { hasText: '#' + idApp }).locator('form[action$="/aprovar"] button').click()]);
  await p.click('[data-enviar]').catch(() => {}); await p.click('[data-aba=sincronizar]'); await p.click('[data-aba=pedidos]');
  await p.evaluate(() => window.dispatchEvent(new Event('online'))); await espera(1500); await p.click('[data-aba=pedidos]'); await espera(300);
  ok((await p.locator(`.ap-item:has-text("#${idApp}") .selo`).innerText()) === 'Aprovado', 'app mostra "Aprovado" depois do Financeiro');
  await fin.goto(BASE + '/pedidos/' + idApp); await Promise.all([fin.waitForNavigation(), fin.click('form[action$="/simular-faturamento"] button')]);
  await p.evaluate(() => window.dispatchEvent(new Event('online'))); await espera(1500); await p.click('[data-aba=pedidos]'); await espera(300);
  ok((await p.locator(`.ap-item:has-text("#${idApp}")`).innerText()).includes('NF'), 'app mostra "Faturado" com o número da NF');

  // ---------- Sessão vencida ----------
  await ctx.clearCookies();
  await p.evaluate(async (cli) => { const { salvarItem } = await import(document.querySelector('meta[name=base]').content + '/assets/app/banco.js');
    await salvarItem({ uuid: '33333333-2222-4333-8444-555555555555', tipo: 'visita', estado: 'pendente', cliente_id: Number(cli), cliente_nome: 'Sessão', resumo: 'depois do login', data: new Date().toISOString().slice(0, 10), criado_em: new Date().toISOString() }); }, clientes[3]);
  await p.click('[data-aba=pedidos]'); await espera(200); await p.click('[data-enviar]'); await espera(1200);
  ok((await p.locator('[data-aviso]').innerText()).includes('sessão encerrou'), 'sessão vencida: avisa e mantém o item guardado');
  ok(sql("select count(*) from cliente_eventos where JSON_UNQUOTE(JSON_EXTRACT(dados_json,'$.uuid'))='33333333-2222-4333-8444-555555555555'") === '0', 'nada enviado sem login');
  await p.goto(BASE + '/app'); await p.fill('#email', 'externo1@demo.local'); await p.fill('#senha', 'Demo@2026'); await p.click('button[type=submit]'); await p.waitForLoadState();
  ok(await esperarAte(() => sql("select count(*) from cliente_eventos where JSON_UNQUOTE(JSON_EXTRACT(dados_json,'$.uuid'))='33333333-2222-4333-8444-555555555555'") === '1'), 'depois de entrar de novo, o item é enviado sem perder nada');
  await p.click('[data-aba=pedidos]'); await espera(400); await p.screenshot({ path: DIR + '/app-pedidos.png' });

  // ---------- Permissão ----------
  const prod = await (await b.newContext()).newPage();
  await prod.goto(BASE + '/entrar'); await prod.fill('#email', 'producao@demo.local'); await prod.fill('#senha', 'Demo@2026'); await prod.click('button[type=submit]'); await prod.waitForLoadState();
  ok((await prod.request.get(BASE + '/app/api/dados', { headers: { Accept: 'application/json' } })).status() === 403, 'Produção recebe 403 na API do app');
  ok(await p.evaluate(() => document.documentElement.scrollWidth) <= 392, 'sem rolagem horizontal no celular');
  ok(erros.length === 0, 'sem erros de JavaScript ' + erros.join(' | '));
  console.log(falhas ? `\n${falhas} FALHA(S)` : '\nTUDO OK'); await b.close();
})();
