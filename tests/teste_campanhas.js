// Teste automático (Playwright). Rodar todos: bash tests/rodar.sh  ·  só este: node tests/teste_campanhas.js
// Campanhas comerciais (V12.0): o gestor cria (faixas, brinde, frete, produto em promoção, público, cores), o catálogo novo
// mostra (visitante sem preço, cliente com preço promocional e "faltam R$ X"), o pedido grava o que a campanha deu,
// público por tabela, não somar com a vendedora, duplicar/desligar e o acesso (vendedora só vê).
const { chromium } = require('playwright');
const { execSync } = require('child_process');
const BASE = 'http://127.0.0.1:8000';
let falhas = 0; const ok = (c, m) => { console.log((c ? 'OK   ' : 'FALHA') + ' ' + m); if (!c) falhas++; };
const sql = (q) => execSync(`mysql perfeita --default-character-set=utf8mb4 -N -e "${q.replace(/"/g, '\\"')}"`).toString().trim();
const hoje = new Date(); const d = (x) => new Date(hoje.getTime() + x * 86400000 - hoje.getTimezoneOffset() * 60000).toISOString().slice(0, 10);

async function entrar(p, email) {
  await p.goto(BASE + '/entrar'); await p.fill('#email', email); await p.fill('#senha', 'Demo@2026'); await p.click('button[type=submit]'); await p.waitForLoadState();
  if (await p.locator('[data-novidades-ok]').count()) { await p.click('[data-novidades-ok]'); await p.waitForLoadState(); }
}
async function entrarCliente(p, cnpj) { await p.goto(BASE + '/catalogo/entrar'); await p.fill('#cnpj', cnpj); await p.fill('#senha', 'Demo@2026'); await p.click('form[action$="/catalogo/entrar"] button'); await p.waitForLoadState(); }

(async () => {
  const b = await chromium.launch();
  const erros = [];
  const nova = async (w = 1300) => { const p = await (await b.newContext({ viewport: { width: w, height: 950 } })).newPage(); p.on('pageerror', (e) => erros.push(e.message)); await p.route(/open-meteo/, (r) => r.fulfill({ body: '{}' })); return p; };
  sql('update campanhas set ativa = 0'); // começa sem as campanhas de exemplo

  // Cliente do catálogo (acesso, crédito ok) e um produto com preço na tabela dele
  const [cliId, cnpj, tabela] = sql("select concat(c.id,'|',c.cnpj,'|',coalesce(c.tabela_preco_id,(select id from tabelas_preco where ativa=1 order by id limit 1))) from cliente_acessos a join clientes c on c.id=a.cliente_id where c.credito_situacao='ok' and not exists (select 1 from cobrancas cb where cb.cliente_id=c.id and cb.situacao='aberta') order by a.id limit 1").split('|');
  const [prodId, prodNome, prodPreco, prodSlug] = sql(`select concat(p.id,'|',p.nome,'|',pp.preco,'|',p.slug) from produtos p join produto_precos pp on pp.produto_id=p.id and pp.tabela_preco_id=${tabela} where p.ativo=1 and p.visivel_catalogo=1 and p.imagem_id is not null order by p.ordem, p.nome limit 1`).split('|');
  const preco = Number(prodPreco); const promo = Math.round(preco * 0.75 * 100) / 100;
  const minimo = Number(sql(`select coalesce(ru.pedido_minimo, r.pedido_minimo) from clientes c join regiao_ufs ru on ru.uf=c.uf join regioes r on r.id=ru.regiao_id where c.id=${cliId}`) || 0);
  const f1 = Math.max(minimo, 500) + 200; const f2 = f1 * 2; // faixas: desconto e brinde

  // ---- Gestor cria a campanha ----
  const g = await nova();
  await entrar(g, 'gestor@demo.local');
  await g.goto(BASE + '/campanhas');
  ok(await g.locator('.cabecalho-secao h1:has-text("Campanhas")').count() === 1, 'Comercial › Campanhas abre para o gestor');
  await g.goto(BASE + '/campanhas/nova');
  await g.fill('#c-nome', 'Teste semana');
  await g.click('[data-periodo]:has-text("Esta semana")');
  await g.locator('[name="faixa_valor[0]"]').fill(String(f1).replace('.', ','));
  await g.locator('[name="faixa_tipo[0]"]').selectOption('desconto'); await g.locator('[name="faixa_pct[0]"]').fill('5');
  await g.click('[data-mais="faixas"]');
  await g.locator('[name="faixa_valor[1]"]').fill(String(f2).replace('.', ','));
  await g.locator('[name="faixa_tipo[1]"]').selectOption('brinde');
  ok(await g.locator('[name="faixa_pct[1]"]').isHidden() && await g.locator('[name="faixa_brinde[1]"]').isVisible(), 'faixa de brinde mostra o campo do brinde (e esconde o %)');
  await g.locator('[name="faixa_brinde[1]"]').fill('Caneca térmica');
  await g.click('[data-mais="faixas"]');
  await g.locator('[name="faixa_valor[2]"]').fill(String(f2 * 3).replace('.', ','));
  await g.locator('[name="faixa_tipo[2]"]').selectOption('frete');
  await g.locator('[name="promo_produto[0]"]').selectOption(prodId); await g.locator('[name="promo_pct[0]"]').fill('25'); await g.check('[name="promo_destaque[0]"]');
  await g.fill('#c-titulo', 'Semana teste'); await g.fill('#c-selo', '25% OFF');
  ok((await g.locator('[data-previa] [data-prev-out="titulo"]').innerText()) === 'Semana teste', 'prévia do catálogo atualiza o título ao digitar');
  ok((await g.locator('[data-prev-faixas] span').count()) === 3, 'prévia mostra as 3 faixas');
  await g.click('[data-pronta][title="Verde noite"]');
  ok(await g.locator('[data-previa].escuro').count() === 1, 'combinação pronta "Verde noite" deixa a prévia com fundo escuro');
  await Promise.all([g.waitForNavigation(), g.click('button:has-text("Salvar campanha")')]);
  const campId = g.url().match(/campanhas\/(\d+)/)?.[1];
  ok(!!campId && (await g.locator('.aviso.sucesso').innerText()).includes('valendo'), 'campanha salva e no ar');
  ok(sql(`select concat(count(*),'|',sum(tipo='brinde'),'|',sum(tipo='frete')) from campanha_faixas where campanha_id=${campId}`) === '3|1|1', 'faixas gravadas (desconto, brinde, frete)');
  ok(sql(`select concat(desconto_pct,'|',destaque) from campanha_produtos where campanha_id=${campId}`) === '25.00|1', 'produto em promoção gravado (25%, destaque)');
  ok(sql(`select concat(tema,'|',cor_primaria) from campanhas where id=${campId}`) === 'escuro|#0b2a1f', 'cores e fundo escuro da campanha gravados');

  // Erro de validação mantém o que foi digitado
  await g.goto(BASE + '/campanhas/nova');
  await g.fill('#c-nome', 'Sem faixas');
  await Promise.all([g.waitForNavigation(), g.click('button:has-text("Salvar campanha")')]);
  ok((await g.locator('.aviso.erro').innerText()).includes('pelo menos uma faixa') && (await g.inputValue('#c-nome')) === 'Sem faixas', 'campanha sem faixa nem produto é recusada, mantendo o nome digitado');

  // ---- Visitante: catálogo novo, sem preço ----
  const v = await nova();
  await v.goto(BASE + '/catalogo');
  ok((await v.locator('.vt-titulo').innerText()).replace(/\s+/g, ' ').includes('Semana teste'), 'catálogo mostra o título da campanha no banner');
  ok(await v.locator('body.cat-escuro').count() === 1, 'catálogo com o fundo escuro da campanha');
  ok(await v.locator('.vt-faixa').count() === 3 && (await v.locator('.vt-faixa.brinde').innerText()).includes('Caneca'), 'faixas da campanha no catálogo (com o brinde)');
  ok(await v.locator('.vt-destaque').count() >= 1 && await v.locator('.vt-off').count() >= 1, 'produto em destaque e selo de desconto na lista');
  ok(!(await v.content()).includes(String(promo.toFixed(2)).replace('.', ',')) && await v.locator('.vt-preco-off').count() > 0, 'visitante não vê preço (só "Entre para ver o preço")');

  // ---- Cliente: preço promocional, "faltam", pedido ----
  const c = await nova();
  await entrarCliente(c, cnpj);
  await c.goto(BASE + '/catalogo/produto/' + prodSlug);
  ok((await c.locator('.vt-precos s').innerText()).includes(preco.toFixed(2).replace('.', ',')) && (await c.locator('.vt-precos strong').innerText()).includes(promo.toFixed(2).replace('.', ',')), `página do produto: de ${preco} por ${promo}`);
  await c.goto(BASE + '/catalogo');
  const qtd = Math.ceil(f1 / promo) + 1; // passa da 1ª faixa, não chega na 2ª (se der)
  const caixa = c.locator(`.cat-card [data-produto="${prodId}"]`);
  await caixa.locator('input').fill(String(qtd)); await caixa.locator('input').dispatchEvent('change');
  await c.waitForResponse((r) => r.url().includes('/carrinho/quantidade')); await c.waitForTimeout(200);
  const msg = await c.locator('.vt-progresso [data-camp-msg]').innerText();
  const ganhou = await c.locator('.vt-progresso [data-camp-ganhou]').innerText();
  ok(/Faltam R\$/.test(msg) && ganhou.includes('5% de desconto'), `barra da campanha ao vivo: "${msg}" · "${ganhou}"`);
  ok((await c.locator('.cat-barra-carrinho [data-camp-msg]').innerText()).startsWith('Faltam'), 'barra do carrinho mostra quanto falta para a próxima faixa');
  await c.goto(BASE + '/catalogo/carrinho');
  ok((await c.locator('.cat-campanha').innerText()).includes('5% de desconto') && (await c.locator('.cat-totais').innerText()).includes('Desconto da campanha'), 'carrinho: o que ganhou e o desconto da campanha');
  await c.check('label.opcao:has-text("Pix") input');
  await Promise.all([c.waitForNavigation(), c.click('button:has-text("Enviar pedido")')]);
  const pedId = sql(`select max(id) from pedidos where cliente_id=${cliId}`);
  const sub = Math.round(qtd * promo * 100) / 100;
  const [pSub, pPct, pDesc, pCamp] = sql(`select concat(subtotal,'|',campanha_desconto_pct,'|',campanha_desconto,'|',coalesce(campanhas,'')) from pedidos where id=${pedId}`).split('|');
  ok(Math.abs(Number(pSub) - sub) < 0.02 && pPct === '5.00' && Math.abs(Number(pDesc) - Math.round(sub * 5) / 100) < 0.02 && pCamp.includes('Semana teste'), `pedido grava preço promocional, 5% da campanha (${pDesc}) e o nome da campanha`);
  ok(sql(`select concat(promo_pct,'|',preco_unitario) from pedido_itens where pedido_id=${pedId}`) === `25.00|${promo.toFixed(2)}`, 'item grava o % promocional e o preço com promoção');
  ok(sql(`select count(*) from pedido_historico where pedido_id=${pedId} and texto like 'Campanha Semana teste%'`) === '1', 'histórico do pedido registra o que a campanha deu');

  // Brinde: pedido acima da 2ª faixa
  await c.goto(BASE + '/catalogo');
  const qtd2 = Math.ceil(f2 / promo) + 1;
  await c.locator(`.cat-card [data-produto="${prodId}"] input`).fill(String(qtd2)); await c.locator(`.cat-card [data-produto="${prodId}"] input`).dispatchEvent('change');
  await c.waitForResponse((r) => r.url().includes('/carrinho/quantidade'));
  await c.goto(BASE + '/catalogo/carrinho');
  ok((await c.locator('.cat-campanha').innerText()).includes('Caneca térmica'), 'carrinho acima da 2ª faixa mostra o brinde conquistado');
  await c.check('label.opcao:has-text("Pix") input');
  await Promise.all([c.waitForNavigation(), c.click('button:has-text("Enviar pedido")')]);
  const ped2 = sql(`select max(id) from pedidos where cliente_id=${cliId}`);
  ok(sql(`select concat(campanha_brindes,'|',frete_gratis) from pedidos where id=${ped2}`) === 'Caneca térmica|0', 'pedido grava o brinde');
  await g.goto(BASE + '/pedidos/' + ped2);
  ok((await g.locator('[data-pedido-campanha]').innerText()).includes('Caneca térmica'), 'página do pedido mostra a campanha e o brinde');
  const fin = await nova();
  await entrar(fin, 'financeiro@demo.local');
  await fin.goto(BASE + '/aprovacao');
  ok((await fin.locator('[data-campanha-brinde]').allInnerTexts()).some((x) => x.includes('Caneca')), 'aprovação marca o pedido com o brinde');

  // ---- Público por tabela: campanha só de outra tabela não aparece para este cliente ----
  const outra = sql(`select id from tabelas_preco where ativa=1 and id<>${tabela} order by id limit 1`);
  sql(`update campanhas set publico='tabela', publico_valores='${outra}' where id=${campId}`);
  await c.goto(BASE + '/catalogo');
  ok(await c.locator('.vt-faixa.brinde').count() === 0 && !(await c.locator('.vt-titulo').innerText()).includes('Semana'), 'campanha de outra tabela de preço não vale para este cliente');
  sql(`update campanhas set publico='todos', publico_valores=null where id=${campId}`);

  // ---- Pedido da equipe: desconto da vendedora com campanha que não soma ----
  sql(`update campanhas set soma_desconto_vendedor=0 where id=${campId}`);
  const t = await nova();
  await entrar(t, 'televendas1@demo.local');
  const cliT = sql("select c.id from clientes c join usuarios u on u.id=c.responsavel_id where u.email='televendas1@demo.local' and c.tipo='cliente' and c.credito_situacao<>'atraso' and not exists (select 1 from cobrancas cb where cb.cliente_id=c.id and cb.situacao='aberta') order by c.id limit 1");
  await t.goto(BASE + '/pedidos/novo?cliente=' + cliT);
  const linha = t.locator('[data-linha]:has(.ped-promo)').first();
  ok(await linha.count() === 1 && await t.locator('[data-campanha]').count() === 1, 'pedido da equipe: produto em promoção e caixa da campanha no resumo');
  const precoT = Number(await linha.getAttribute('data-preco'));
  await linha.locator('.ped-qtd').fill(String(Math.ceil(f1 / precoT) + 1));
  ok((await t.locator('[data-campanha-ganhou]').innerText()).includes('5% de desconto') && !(await t.locator('[data-desconto-campanha]').isHidden()), 'resumo ao vivo: ganhou 5% e mostra o desconto da campanha');
  await t.fill('[data-desconto-pedido]', '2');
  ok(await t.locator('[data-desconto-campanha]').isHidden(), 'com desconto da vendedora, a campanha que não soma tira o desconto da faixa (na tela)');
  await t.check('label.opcao:has-text("Pix") input');
  await Promise.all([t.waitForNavigation(), t.click('[data-enviar]')]);
  const pedT = t.url().match(/pedidos\/(\d+)/)?.[1];
  ok(!!pedT && sql(`select concat(campanha_desconto_pct,'|',desconto_pedido_pct) from pedidos where id=${pedT}`) === '0.00|2.00', 'servidor confere: sem desconto da faixa, com o desconto da vendedora');

  // ---- Acesso: vendedora só vê ----
  const r = await t.goto(BASE + '/campanhas/nova');
  ok(r.status() === 403, 'vendedora não cria campanha (403)');
  await t.goto(BASE + '/campanhas/' + campId);
  ok(await t.locator('button:has-text("Salvar campanha")').count() === 0 && await t.locator('#c-nome[disabled]').count() === 1, 'vendedora vê a campanha em modo leitura');

  // ---- Duplicar e desligar ----
  await g.goto(BASE + '/campanhas');
  await Promise.all([g.waitForNavigation(), g.locator(`form[action$="/campanhas/${campId}/duplicar"] button`).click()]);
  const copia = g.url().match(/campanhas\/(\d+)/)?.[1];
  ok(!!copia && sql(`select concat(ativa,'|',(select count(*) from campanha_faixas where campanha_id=${copia}),'|',inicio > (select fim from campanhas where id=${campId})) from campanhas where id=${copia}`) === '0|3|1', 'duplicar cria cópia desligada, com as faixas, no período seguinte');
  await g.goto(BASE + '/campanhas');
  await Promise.all([g.waitForNavigation(), g.locator(`form[action$="/campanhas/${campId}/alternar"] button`).click()]);
  await v.goto(BASE + '/catalogo');
  ok(await v.locator('.vt-faixa.brinde').count() === 0 && await v.locator('body.cat-claro').count() === 1, 'campanha desligada sai do catálogo (volta o visual padrão)');

  // ---- Visual padrão do catálogo ----
  await g.goto(BASE + '/campanhas?aba=visual');
  await g.fill('#v-titulo', 'Catálogo de teste');
  await Promise.all([g.waitForNavigation(), g.click('button:has-text("Salvar visual")')]);
  await v.goto(BASE + '/catalogo');
  ok((await v.locator('.vt-titulo').innerText()).replace(/\s+/g, ' ').includes('Catálogo de teste'), 'visual padrão: título do catálogo mudado pela tela de Campanhas');

  // ---- Celular ----
  const m = await nova(390);
  await m.goto(BASE + '/catalogo');
  ok(await m.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth + 1), 'catálogo cabe no celular (sem rolagem lateral)');

  ok(erros.length === 0, 'sem erros de JavaScript' + (erros.length ? ': ' + erros.join(' | ') : ''));
  await b.close();
  console.log(falhas ? `\n${falhas} FALHA(S)` : '\nTudo certo.');
  process.exit(falhas ? 1 : 0);
})();
