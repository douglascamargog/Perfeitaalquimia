// Teste automático (Playwright). Rodar todos: bash tests/rodar.sh  ·  só este: node tests/teste_deposito.js
// Depósito: pedido faturado entra na fila, urgente primeiro, separar com leitor de código de barras, lote obrigatório
// (sugerido pela ordem de produção), falta (aguardar / despachar sem), despacho com rastreio (vendedor, pedido, catálogo),
// rastrear lote e acessos.
const { chromium } = require('playwright');
const { execSync } = require('child_process');
const BASE = 'http://127.0.0.1:8000';
let falhas = 0; const ok = (c, m) => { console.log((c ? 'OK   ' : 'FALHA') + ' ' + m); if (!c) falhas++; };
const sql = (q) => execSync(`mysql perfeita --default-character-set=utf8mb4 -N -e "${q.replace(/"/g, '\\"')}"`).toString().trim();

(async () => {
  const b = await chromium.launch();
  const erros = [];
  const como = async (email, viewport = { width: 1300, height: 900 }) => {
    const p = await (await b.newContext({ viewport })).newPage();
    p.on('pageerror', (e) => erros.push(e.message)); await p.route(/open-meteo|jsdelivr/, (r) => r.fulfill({ body: '{}' }));
    await p.goto(BASE + '/entrar'); await p.fill('#email', email); await p.fill('#senha', 'Demo@2026'); await p.click('button[type=submit]'); await p.waitForLoadState();
    if (await p.locator('[data-novidades-ok]').count()) { await p.click('[data-novidades-ok]'); await p.waitForLoadState(); }
    const csrf = await p.evaluate(() => document.querySelector('meta[name=csrf]').content);
    return { p, csrf };
  };
  const post = async (s, caminho, form) => {
    const html = await (await s.p.request.post(BASE + caminho, { form: { _csrf: s.csrf, ...form } })).text();
    return [...html.matchAll(/class="aviso[- ][a-z ]*"[^>]*>([^<]*)</g)].map((m) => m[1]).join(' | ');
  };
  const exp = (id) => sql(`select coalesce(expedicao,'') from pedidos where id=${id}`);

  // ---------- Faturado agora entra na fila; histórico antigo não ----------
  const fin = await como('financeiro@demo.local');
  const pid = sql("select p.id from pedidos p where p.situacao='aprovado' and exists (select 1 from cliente_acessos a where a.cliente_id=p.cliente_id) order by p.id limit 1")
    || sql("select id from pedidos where situacao='aprovado' order by id limit 1");
  ok(!!pid && exp(pid) === '', 'pedido aprovado ainda fora da fila');
  await post(fin, `/pedidos/${pid}/simular-faturamento`, {});
  ok(sql(`select situacao from pedidos where id=${pid}`) === 'faturado' && exp(pid) === 'aguardando', 'nota emitida: o pedido entra na fila do depósito');
  ok(Number(sql("select count(*) from pedidos where situacao='faturado' and expedicao is null")) > 100, 'pedidos faturados antigos ficam fora da fila');

  // ---------- Fila: urgente primeiro ----------
  const d = await como('deposito@demo.local');
  await post(d, `/deposito/${pid}/urgente`, { urgente: '1' });
  await d.p.goto(BASE + '/deposito');
  const ordem = await d.p.locator('.dep-cartao').evaluateAll((els) => els.map((e) => [e.dataset.pedido, e.classList.contains('urgente')]));
  const primeiroNormal = ordem.findIndex(([, u]) => !u);
  ok(ordem.some(([id]) => id === pid) && ordem.slice(primeiroNormal).every(([, u]) => !u), 'fila: urgentes primeiro');
  ok(await d.p.locator('[data-resumo] b').first().innerText() !== '', 'painel do dia no topo da fila');

  // ---------- Separar: leitor de código de barras ----------
  const itens = sql(`select concat(id,'|',coalesce(produto_id,0),'|',quantidade) from pedido_itens where pedido_id=${pid} order by id`).split('\n').map((l) => l.split('|'));
  const [i1, prod1, q1] = itens[0];
  sql(`insert into produto_tecnico (produto_id, ean) values (${prod1}, '7890000012345') on duplicate key update ean='7890000012345'`);
  sql(`insert into ordens_producao (produto_id, quantidade, situacao, lote, validade, concluida_em) values (${prod1}, 100, 'concluida', 'LT-DEP-1', date_add(curdate(), interval 300 day), now())`);
  await post(d, `/deposito/${pid}/comecar`, {});
  ok(exp(pid) === 'separando', 'começar a separar');
  await d.p.goto(BASE + `/deposito/${pid}`);
  ok((await d.p.locator(`tr[data-item="${i1}"]`).innerText()).includes('LT-DEP-1'), 'lote sugerido vem da ordem de produção');
  await d.p.fill('[data-leitor]', '7890000012345'); await d.p.press('[data-leitor]', 'Enter');
  ok(await d.p.inputValue(`tr[data-item="${i1}"] [data-qtd]`) === '1' && (await d.p.locator('[data-leitor-msg]').innerText()).includes('1 de'), 'leitor de código de barras soma 1 no item');
  await d.p.fill('[data-leitor]', '1111111111111'); await d.p.press('[data-leitor]', 'Enter');
  ok((await d.p.locator('[data-leitor-msg]').innerText()).includes('não é de nenhum item'), 'código de outro produto avisa');

  // ---------- Lote obrigatório ----------
  const tudo = (comLote, menos = 0) => {
    const f = {};
    itens.forEach(([id, , q], n) => {
      const qtd = n === 0 ? Number(q) - menos : Number(q);
      f[`item[${id}][qtd]`] = String(qtd);
      if (comLote) { f[`item[${id}][lotes][0][lote]`] = n === 0 ? 'LT-DEP-1' : 'LT-OUTRO'; f[`item[${id}][lotes][0][validade]`] = '2027-12-31'; f[`item[${id}][lotes][0][qtd]`] = String(qtd); }
    });
    return f;
  };
  let msg = await post(d, `/deposito/${pid}/separar`, { ...tudo(false), concluir: '1' });
  ok(msg.includes('Anote o lote') && exp(pid) === 'separando', 'sem lote não conclui (lote obrigatório)');

  // ---------- Falta: aguardar o produto ----------
  const vend = sql(`select coalesce(vendedor_id,0) from pedidos where id=${pid}`);
  const antesNotif = Number(sql("select count(*) from notificacoes where tipo='pedido_falta'"));
  msg = await post(d, `/deposito/${pid}/separar`, { ...tudo(true, 1), concluir: '1', decisao: 'aguardar' });
  ok(sql(`select expedicao_aguarda_produto from pedidos where id=${pid}`) === '1' && exp(pid) === 'separando', 'falta: fica na fila aguardando o produto');
  ok(Number(sql("select count(*) from notificacoes where tipo='pedido_falta'")) > antesNotif
    && sql(`select count(*) from notificacoes where tipo='pedido_falta' and usuario_id=(select id from usuarios where email='producao@demo.local')`) !== '0', 'falta avisa o vendedor e a Produção');

  // ---------- Concluir completo e despachar ----------
  msg = await post(d, `/deposito/${pid}/separar`, { ...tudo(true), concluir: '1' });
  ok(exp(pid) === 'separado' && sql(`select count(*) from pedido_item_lotes where pedido_id=${pid}`) === String(itens.length), 'separação concluída com o lote de cada item');
  msg = await post(d, `/deposito/${pid}/despachar`, { transportadora: '', volumes: '1' });
  ok(msg.includes('transportadora') && exp(pid) === 'separado', 'despacho pede a transportadora');
  await post(d, `/deposito/${pid}/despachar`, { transportadora: 'Correios', volumes: '2', peso: '3,5', rastreio: 'QA987654321BR' });
  ok(exp(pid) === 'despachado' && sql(`select rastreio from pedidos where id=${pid}`) === 'QA987654321BR', 'despachado com transportadora e rastreio');
  if (vend !== '0') ok(sql(`select count(*) from notificacoes where tipo='pedido_despachado' and usuario_id=${vend}`) !== '0', 'vendedor avisado do despacho');
  ok(sql(`select count(*) from cliente_eventos where cliente_id=(select cliente_id from pedidos where id=${pid}) and descricao like '%despachado%'`) !== '0', 'despacho na linha do tempo do cliente');
  await fin.p.goto(BASE + `/pedidos/${pid}`);
  ok((await fin.p.locator('[data-rastreio]').innerText()).includes('QA987654321BR'), 'rastreio aparece na página do pedido');
  // Catálogo do cliente
  const cnpj = sql(`select c.cnpj from pedidos p join clientes c on c.id=p.cliente_id join cliente_acessos a on a.cliente_id=c.id where p.id=${pid}`);
  if (cnpj) {
    const c = await (await b.newContext()).newPage();
    await c.goto(BASE + '/catalogo/entrar'); await c.fill('#cnpj', cnpj); await c.fill('#senha', 'Demo@2026'); await c.click('form[action$="/catalogo/entrar"] button'); await c.waitForLoadState();
    await c.goto(BASE + '/catalogo/pedidos');
    const cartao = c.locator('details.cat-pedido', { hasText: `Pedido #${pid}` });
    ok((await cartao.locator('summary').innerText()).includes('Enviado') && (await cartao.textContent()).includes('QA987654321BR'), 'cliente vê "Enviado" com o rastreio no catálogo');
  }

  // ---------- Despachar sem o que falta ----------
  const p2 = sql("select id from pedidos where expedicao='aguardando' and id<>" + pid + ' order by id limit 1');
  const itens2 = sql(`select concat(id,'|',quantidade) from pedido_itens where pedido_id=${p2} order by id`).split('\n').map((l) => l.split('|'));
  const f2 = {};
  itens2.forEach(([id, q], n) => { const qtd = n === 0 ? 0 : Number(q); f2[`item[${id}][qtd]`] = String(qtd); f2[`item[${id}][lotes][0][lote]`] = 'LT-X'; f2[`item[${id}][lotes][0][qtd]`] = String(qtd); });
  msg = await post(d, `/deposito/${p2}/separar`, { ...f2, concluir: '1', decisao: 'despachar_sem', observacao: '' });
  ok(msg.includes('observação') && exp(p2) === 'separando', 'despachar sem o que falta exige observação');
  const antesFin = Number(sql("select count(*) from notificacoes where tipo='pedido_falta' and usuario_id=(select id from usuarios where email='financeiro@demo.local')"));
  await post(d, `/deposito/${p2}/separar`, { ...f2, concluir: '1', decisao: 'despachar_sem', observacao: 'Combinado com o vendedor' });
  ok(exp(p2) === 'separado' && sql(`select falta from pedidos where id=${p2}`) === '1', 'separado sem o que falta (marcado)');
  ok(Number(sql("select count(*) from notificacoes where tipo='pedido_falta' and usuario_id=(select id from usuarios where email='financeiro@demo.local')")) > antesFin, 'Financeiro avisado (nota pode precisar de ajuste)');

  // ---------- Rastrear lote ----------
  await d.p.goto(BASE + '/deposito/lotes?q=LT-DEP-1');
  const cliente = sql(`select coalesce(nullif(c.nome_fantasia,''), c.razao_social) from pedidos p join clientes c on c.id=p.cliente_id where p.id=${pid}`);
  ok((await d.p.locator('[data-rastreio]').innerText()).includes(cliente), 'rastrear lote mostra para qual cliente foi');

  // ---------- Acessos e celular ----------
  const v = await como('televendas1@demo.local');
  await v.p.goto(BASE + '/deposito');
  ok(await v.p.locator('[data-fila]').count() === 0, 'vendedora não vê a fila do depósito');
  msg = await post(v, `/deposito/${p2}/despachar`, { transportadora: 'X' });
  ok(exp(p2) === 'separado', 'vendedora não despacha');
  const cel = await como('deposito@demo.local', { width: 390, height: 844 });
  for (const url of ['/deposito', `/deposito/${p2}`]) {
    await cel.p.goto(BASE + url);
    ok(await cel.p.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), `celular sem rolagem lateral: ${url}`);
  }

  ok(erros.length === 0, 'nenhum erro de JavaScript' + (erros.length ? ': ' + erros.join(' / ') : ''));
  await b.close();
  console.log(falhas ? `\n${falhas} FALHA(S)` : '\nTudo certo.');
  process.exit(falhas ? 1 : 0);
})();
