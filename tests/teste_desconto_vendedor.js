// Teste automático (Playwright). Rodar todos: bash tests/rodar.sh  ·  só este: node tests/teste_desconto_vendedor.js
// Desconto da vendedora no pedido: por produto e no pedido inteiro, cálculo ao vivo, gravação (preço de tabela e %),
// limite configurável (acima: marcado para aprovação e gestores avisados) e o cliente no catálogo não consegue dar desconto.
const { chromium } = require('playwright');
const { execSync } = require('child_process');
const BASE = 'http://127.0.0.1:8000';
let falhas = 0; const ok = (c, m) => { console.log((c ? 'OK   ' : 'FALHA') + ' ' + m); if (!c) falhas++; };
const sql = (q) => execSync(`mysql perfeita --default-character-set=utf8mb4 -N -e "${q.replace(/"/g, '\\"')}"`).toString().trim();

(async () => {
  const b = await chromium.launch();
  const erros = [];
  const p = await (await b.newContext({ viewport: { width: 1440, height: 950 } })).newPage();
  p.on('pageerror', (e) => erros.push(e.message)); await p.route(/open-meteo/, (r) => r.fulfill({ body: '{}' }));
  await p.goto(BASE + '/entrar'); await p.fill('#email', 'televendas1@demo.local'); await p.fill('#senha', 'Demo@2026'); await p.click('button[type=submit]'); await p.waitForLoadState();
  if (await p.locator('[data-novidades-ok]').count()) { await p.click('[data-novidades-ok]'); await p.waitForLoadState(); }
  const cli = sql("select c.id from clientes c join usuarios u on u.id=c.responsavel_id where u.email='televendas1@demo.local' and c.tipo='cliente' and c.credito_situacao<>'atraso' order by c.id limit 1");
  await p.goto(BASE + '/pedidos/novo?cliente=' + cli);
  const linhas = p.locator('[data-linha]');
  const preco = Number(await linhas.first().getAttribute('data-preco'));
  const prod = (await linhas.first().locator('.ped-qtd').getAttribute('name')).match(/\[(.+)\]/)[1];
  const qtd = Math.ceil(4000 / preco); // passa do pedido mínimo
  await linhas.first().locator('.ped-qtd').fill(String(qtd));
  await linhas.first().locator('.ped-desc').fill('5');
  await p.fill('[data-desconto-pedido]', '3');
  const resumo = await p.locator('[data-desconto-resumo]').innerText();
  const unit = Math.round(preco * 0.95 * 100) / 100;
  const sub = unit * qtd;
  ok(resumo.includes('Desconto total') && !(await p.locator('[data-desconto-resumo].acima').count()), 'desconto calculado na hora, dentro do limite (' + resumo + ')');
  await p.check('label.opcao:has-text("Pix") input');
  await Promise.all([p.waitForNavigation(), p.click('[data-enviar]')]);
  const id = p.url().match(/pedidos\/(\d+)/)?.[1];
  ok(!!id, 'pedido com desconto enviado');
  ok(sql(`select concat(preco_tabela,'|',desconto_pct,'|',preco_unitario) from pedido_itens where pedido_id=${id}`) === `${preco.toFixed(2)}|5.00|${unit.toFixed(2)}`, 'item grava o preço de tabela, o % e o preço com desconto');
  const [subG, descG, totG] = sql(`select concat(subtotal,'|',desconto,'|',total) from pedidos where id=${id}`).split('|').map(Number);
  ok(Math.abs(subG - sub) < 0.02 && descG >= Math.round(sub * 3) / 100 - 0.01 && Math.abs(totG - (subG - descG)) < 0.01, `subtotal com o desconto do produto (${sub.toFixed(2)}) e o desconto do pedido somado aos descontos da condição de pagamento`);
  ok(sql(`select concat(desconto_pedido_pct,'|',desconto_acima_limite) from pedidos where id=${id}`) === '3.00|0', 'desconto do pedido gravado, dentro do limite');
  ok(await p.locator('[data-desconto-vendedor]').count() === 1, 'página do pedido mostra o desconto da vendedora');

  // Acima do limite (padrão 10%): marcado e gestores avisados
  await p.goto(BASE + '/pedidos/novo?cliente=' + cli);
  const token = await p.locator('meta[name=csrf]').getAttribute('content');
  const forma = await p.locator('label.opcao', { hasText: 'Pix' }).locator('input').getAttribute('value');
  const form = { cliente_id: cli, uuid: 'dddddddd-bbbb-4ccc-8ddd-eeeeeeeeeeee', pagamento: forma, _csrf: token, desconto_pedido: '15' };
  form[`qtd[${prod}]`] = String(qtd + 2);
  await p.request.post(BASE + '/pedidos', { form });
  const id2 = sql("select id from pedidos where uuid='dddddddd-bbbb-4ccc-8ddd-eeeeeeeeeeee'");
  ok(sql(`select desconto_acima_limite from pedidos where id=${id2}`) === '1', 'desconto de 15% passa do limite: marcado para aprovação');
  ok(Number(sql(`select count(*) from notificacoes where tipo='pedido_desconto' and link='/pedidos/${id2}'`)) >= 1, 'gestores avisados do desconto acima do limite');
  const f = await (await b.newContext()).newPage();
  await f.goto(BASE + '/entrar'); await f.fill('#email', 'financeiro@demo.local'); await f.fill('#senha', 'Demo@2026'); await f.click('button[type=submit]'); await f.waitForLoadState();
  await f.goto(BASE + '/aprovacao');
  ok(await f.locator('[data-desconto-acima]').count() >= 1, 'Aprovação mostra "desconto acima do limite"');

  // Pedido do catálogo (cliente) ignora desconto
  ok(sql("select count(*) from pedidos where origem='catalogo' and desconto_vendedor > 0") === '0', 'pedido feito pelo cliente nunca tem desconto de vendedora');
  ok(erros.length === 0, 'nenhum erro de JavaScript' + (erros.length ? ': ' + erros.join(' / ') : ''));
  await b.close();
  console.log(falhas ? `\n${falhas} FALHA(S)` : '\nTudo certo.');
  process.exit(falhas ? 1 : 0);
})();
