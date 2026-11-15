// Teste automático (Playwright). Rodar todos: bash tests/rodar.sh  ·  só este: node tests/teste_ecommerce_deposito.js
// Pedidos do e-commerce (Tray simulada) no Depósito: importa os pagos, só entra na fila com nota emitida,
// horário limite por canal, filtro Lojistas/E-commerce/Terceirização, ordem pela urgência, não duplica,
// compradores fora da carteira e do painel de vendas, separação e despacho normais.
const { chromium } = require('playwright');
const { execSync } = require('child_process');
const BASE = 'http://127.0.0.1:8000';
let falhas = 0; const ok = (c, m) => { console.log((c ? 'OK   ' : 'FALHA') + ' ' + m); if (!c) falhas++; };
const sql = (q) => execSync(`mysql perfeita --default-character-set=utf8mb4 -N -e "${q.replace(/"/g, '\\"')}"`).toString().trim();

(async () => {
  const b = await chromium.launch();
  const erros = [];
  const como = async (email) => {
    const p = await (await b.newContext({ viewport: { width: 1440, height: 900 } })).newPage();
    p.on('pageerror', (e) => erros.push(e.message + ' @ ' + p.url())); await p.route(/open-meteo/, (r) => r.fulfill({ body: '{}' }));
    await p.goto(BASE + '/entrar'); await p.fill('#email', email); await p.fill('#senha', 'Demo@2026'); await p.click('button[type=submit]'); await p.waitForLoadState();
    if (await p.locator('[data-novidades-ok]').count()) { await p.click('[data-novidades-ok]'); await p.waitForLoadState(); }
    return { p, csrf: await p.evaluate(() => document.querySelector('meta[name=csrf]').content) };
  };
  const post = async (s, caminho, form = {}) => { const r = await s.p.request.post(BASE + caminho, { form: { _csrf: s.csrf, ...form } }); return r.status(); };
  const n = (q) => Number(sql(q));

  // ---------- Importação (demonstração já trouxe os pedidos de hoje) ----------
  const total = n("select count(*) from pedidos where tipo_venda='ecommerce'");
  const semNota = n("select count(*) from pedidos where tipo_venda='ecommerce' and numero_nf is null");
  ok(total >= 1, `pedidos pagos da Tray importados (${total})`);
  ok(n("select count(*) from pedidos where tipo_venda='ecommerce' and numero_nf is not null and expedicao='aguardando'") === total - semNota, 'com nota emitida: entraram na fila de separação');
  ok(n("select count(*) from pedidos where tipo_venda='ecommerce' and numero_nf is null and expedicao is not null") === 0, 'sem nota: ainda fora da fila');

  const d = await como('deposito@demo.local');
  await post(d, '/deposito/tray');
  ok(n("select count(*) from pedidos where tipo_venda='ecommerce'") === total, 'buscar de novo na Tray não duplica');

  // ---------- Horário limite por canal ----------
  const horas = sql("select group_concat(distinct concat(canal,'=',date_format(expedicao_limite,'%H:%i')) order by canal) from pedidos where tipo_venda='ecommerce' and expedicao_limite is not null");
  ok(/mercado_livre=13:00/.test(horas) || !horas.includes('mercado_livre'), 'Mercado Livre sai até 13:00 (' + horas + ')');
  ok(/shopee=11:00/.test(horas) || !horas.includes('shopee'), 'Shopee sai até 11:00');
  ok(n("select count(*) from pedidos where tipo_venda='ecommerce' and expedicao_limite is not null and weekday(expedicao_limite) >= 5") === 0, 'horário limite nunca cai em sábado ou domingo');
  ok(n("select count(*) from pedidos where tipo_venda='ecommerce' and expedicao_limite is not null and expedicao_limite < faturado_em") === 0, 'nota emitida depois do horário: limite vai para o próximo dia útil');

  // ---------- Fila: filtro e ordem ----------
  const eco = sql("select id from pedidos where tipo_venda='ecommerce' and expedicao='aguardando' order by id limit 1");
  sql(`update pedidos set expedicao_limite = now() - interval 15 minute where id=${eco}`);
  await d.p.goto(BASE + '/deposito');
  ok(await d.p.locator('[data-tipos] [data-tipo="ecommerce"]').count() === 1 && await d.p.locator('[data-tipos] [data-tipo="lojista"]').count() === 1
    && await d.p.locator('[data-tipos] [data-tipo="terceirizacao"]').count() === 1, 'filtro Lojistas · E-commerce · Terceirização');
  const primeiroSemUrgencia = await d.p.locator('[data-pedido]:not(.urgente)').first().getAttribute('data-pedido');
  ok(primeiroSemUrgencia === eco, 'pedido do e-commerce que passou do horário vai para o topo (depois dos urgentes)');
  ok(await d.p.locator(`[data-pedido="${eco}"] [data-prazo].atrasado`).count() === 1, 'passou do horário: aviso em vermelho');
  await d.p.goto(BASE + '/deposito?tipo=ecommerce');
  const tipos = await d.p.locator('[data-tipo-pedido]').evaluateAll((els) => [...new Set(els.map((e) => e.dataset.tipoPedido))]);
  ok(tipos.length === 1 && tipos[0] === 'ecommerce', 'filtro E-commerce mostra só pedidos do e-commerce');
  await d.p.goto(BASE + '/deposito?tipo=lojista');
  ok(await d.p.locator('[data-tipo-pedido="ecommerce"]').count() === 0 && await d.p.locator('[data-tipo-pedido="lojista"]').count() >= 1, 'filtro Lojistas sem os do e-commerce');

  // ---------- Página do pedido e separação normal ----------
  await d.p.goto(BASE + `/deposito/${eco}`);
  const cab = await d.p.locator('.cabecalho-secao').innerText();
  ok(/Mercado Livre|Shopee|Amazon|Loja própria/.test(cab) && cab.includes('comprador') && await d.p.locator('[data-prazo]').count() === 1, 'pedido mostra canal, número do marketplace, comprador e horário');
  const itens = sql(`select concat(id,'|',quantidade) from pedido_itens where pedido_id=${eco}`).split('\n').map((l) => l.split('|'));
  const caixa = sql("select id from materias_primas where lower(nome) like '%caixa de papel%' limit 1");
  const f = { concluir: '1', 'embalagem[0][item]': caixa, 'embalagem[0][qtd]': '1' };
  itens.forEach(([id, q]) => { f[`item[${id}][qtd]`] = q; f[`item[${id}][lotes][0][lote]`] = 'LT-ECO'; f[`item[${id}][lotes][0][qtd]`] = q; });
  await post(d, `/deposito/${eco}/comecar`);
  await post(d, `/deposito/${eco}/separar`, f);
  ok(sql(`select expedicao from pedidos where id=${eco}`) === 'separado', 'separação do pedido do e-commerce igual às outras (lote + caixa)');
  await post(d, `/deposito/${eco}/despachar`, { transportadora: 'Mercado Envios', volumes: '1', rastreio: 'ME123' });
  ok(sql(`select expedicao from pedidos where id=${eco}`) === 'despachado', 'despachado');

  // ---------- Fora da carteira e do painel da equipe ----------
  const g = await como('gestor@demo.local');
  await g.p.goto(BASE + '/carteira/lista?q=consumidores');
  ok(!(await g.p.content()).includes('(consumidores)'), 'compradores do e-commerce não aparecem na carteira');
  ok(n("select count(*) from clientes where tipo='consumidor' and responsavel_id is not null") === 0, 'cliente técnico do canal sem vendedor responsável');
  ok(n("select count(*) from pedidos where tipo_venda='ecommerce' and vendedor_id is not null") === 0, 'pedido do e-commerce não conta para meta de vendedor');

  // ---------- Acesso ----------
  const v = await como('televendas1@demo.local');
  ok(await post(v, '/deposito/tray') === 403, 'vendedora não busca pedidos da Tray');

  ok(erros.length === 0, 'nenhum erro de JavaScript' + (erros.length ? ': ' + erros.join(' / ') : ''));
  await b.close();
  console.log(falhas ? `\n${falhas} FALHA(S)` : '\nTudo certo.');
  process.exit(falhas ? 1 : 0);
})();
