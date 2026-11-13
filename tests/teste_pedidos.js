// Teste automático (Playwright). Rodar todos: bash tests/rodar.sh  ·  só este: node tests/teste_pedidos.js
const path = require('path');
const RECURSOS = path.join(__dirname, 'recursos');
const { chromium } = require('playwright');
const { execSync } = require('child_process');
const BASE = 'http://127.0.0.1:8000', DIR = process.argv[2] || path.join(__dirname, 'saida');
let falhas = 0; const ok = (c, m) => { console.log((c ? 'OK   ' : 'FALHA') + ' ' + m); if (!c) falhas++; };
const sql = (q) => execSync(`mysql perfeita --default-character-set=utf8mb4 -N -e "${q}"`).toString().trim();
const brl = (v) => 'R$ ' + Number(v).toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
async function entrarEquipe(p, email) { await p.goto(BASE + '/entrar'); await p.fill('#email', email); await p.fill('#senha', 'Demo@2026'); await p.click('button[type=submit]'); await p.waitForLoadState(); }
async function sairEquipe(p) { await p.goto(BASE + '/'); await p.click('form[action$="/sair"] button'); await p.waitForLoadState(); }
async function entrarCliente(p, cnpj, senha = 'Demo@2026') { await p.goto(BASE + '/catalogo/entrar'); await p.fill('#cnpj', cnpj); await p.fill('#senha', senha); await p.click('form[action$="/catalogo/entrar"] button'); await p.waitForLoadState(); }
async function sairCliente(p) { await p.goto(BASE + '/catalogo'); const b = p.locator('form[action$="/catalogo/sair"] button'); if (await b.count()) { await b.click(); await p.waitForLoadState(); } }
async function qtd(p, produtoId, n) { await p.request.post(BASE + '/catalogo/carrinho/quantidade', { form: { produto_id: produtoId, quantidade: n, _csrf: await p.locator('meta[name=csrf]').getAttribute('content') } }); }

(async () => {
  const b = await chromium.launch(); const erros = [];
  const cel = await b.newContext({ viewport: { width: 390, height: 844 } }); const c = await cel.newPage();
  c.on('pageerror', e => erros.push('cliente: ' + e.message)); c.on('dialog', d => d.accept());
  const acessos = sql("select concat(c.id,'|',c.cnpj,'|',coalesce(c.tabela_preco_id,0),'|',c.uf) from cliente_acessos a join clientes c on c.id=a.cliente_id order by a.id").split('\n').map(l => l.split('|'));
  const [cliId, cnpj, , uf] = acessos.find(([id]) => sql(`select count(*) from cobrancas where cliente_id=${id} and situacao='aberta'`) === '0' && sql(`select credito_situacao from clientes where id=${id}`) === 'ok');
  const emCobranca = acessos.find(([id]) => sql(`select count(*) from cobrancas where cliente_id=${id} and situacao='aberta'`) !== '0');

  // ---------- Visitante ----------
  await c.goto(BASE + '/catalogo');
  ok(await c.locator('.cat-card').count() > 100, 'vitrine pública com produtos (' + await c.locator('.cat-card').count() + ')');
  ok(await c.locator('.cat-preco').count() === 0 && !(await c.locator('.cat-grade').innerText()).includes('R$'), 'visitante NÃO vê preço nem estoque');
  await c.goto(BASE + '/catalogo/produto/magnesio');
  ok((await c.locator('.cat-produto').innerText()).includes('Entrar com o CNPJ'), 'ficha do produto pede login para ver preço');
  ok((await c.content()).includes('350 mg'), 'ficha usa o conteúdo da Academia');

  // ---------- Login e preço da tabela ----------
  await entrarCliente(c, cnpj);
  ok(c.url().endsWith('/catalogo'), 'cliente entra com CNPJ + senha');
  const tabela = sql(`select t.nome from clientes c join tabelas_preco t on t.id=coalesce(c.tabela_preco_id,(select min(id) from tabelas_preco)) where c.id=${cliId}`);
  ok((await c.locator('.cat-faixa').innerText()).includes(tabela), `mostra a tabela do cliente (${tabela})`);
  const prod = sql(`select concat(p.id,'|',pp.preco) from produtos p join produto_precos pp on pp.produto_id=p.id and pp.tabela_preco_id=coalesce((select tabela_preco_id from clientes where id=${cliId}),(select min(id) from tabelas_preco)) where p.slug='magnesio'`).split('|');
  await c.goto(BASE + '/catalogo/produto/magnesio');
  ok((await c.locator('.cat-preco').innerText()).includes(brl(prod[1])), `preço da tabela certa (${brl(prod[1])})`);

  // ---------- Carrinho abaixo do mínimo ----------
  await c.locator('.cat-qtd [data-mais]').click(); await c.waitForTimeout(700);
  await c.goto(BASE + '/catalogo/carrinho');
  const minimo = Number(sql(`select coalesce(ru.pedido_minimo, r.pedido_minimo) from regiao_ufs ru join regioes r on r.id=ru.regiao_id where ru.uf='${uf}'`));
  ok((await c.locator('[data-minimo]').innerText()).includes('Faltam ' + brl(minimo - Number(prod[1]))), 'carrinho mostra quanto falta para o mínimo');
  ok(await c.locator('.cat-finalizar button[type=submit]').isDisabled(), 'botão bloqueado abaixo do mínimo');
  await c.check('input[name=pagamento] >> nth=0');
  await c.evaluate(() => { const b = document.querySelector('.cat-finalizar button[type=submit]'); b.disabled = false; b.click(); });
  await c.waitForLoadState();
  ok((await c.locator('.aviso.erro').innerText()).includes('pedido mínimo'), 'servidor recusa pedido abaixo do mínimo mesmo forçando');
  ok(await c.locator('.cat-forma.bloqueada').count() === 0, 'cliente sem pendências pode usar boleto');

  // ---------- Fecha o pedido com Pix (3% de desconto) ----------
  await qtd(c, prod[0], Math.ceil(minimo / Number(prod[1])) + 1);
  await c.goto(BASE + '/catalogo/carrinho');
  await c.locator('label.opcao', { hasText: 'Pix' }).locator('input').check();
  await c.fill('#obs', 'Teste automático <b>sem HTML</b>');
  await c.click('.cat-finalizar button[type=submit]'); await c.waitForLoadState();
  ok(c.url().endsWith('/catalogo/pedidos') && (await c.locator('.aviso.sucesso').innerText()).includes('enviado'), 'pedido enviado pelo catálogo');
  const ped = sql(`select concat(id,'|',origem,'|',subtotal,'|',desconto,'|',total,'|',coalesce(omie_codigo,'')) from pedidos where cliente_id=${cliId} order by id desc limit 1`).split('|');
  ok(ped[1] === 'catalogo' && Math.abs(Number(ped[3]) - Number(ped[2]) * 0.03) < 0.02, `origem catálogo e 3% de desconto do Pix (${ped[3]})`);
  ok(ped[5].startsWith('FAKE-'), 'pedido foi ao ERP como orçamento (' + ped[5] + ')');
  ok((await c.locator('.cat-pedido').first().innerText()).includes('Aguardando aprovação'), '"Meus pedidos" mostra a situação');
  await c.screenshot({ path: DIR + '/cat-meus-pedidos.png' });

  // repetir
  await c.click('.cat-repetir button'); await c.waitForLoadState();
  ok(c.url().endsWith('/catalogo/carrinho') && await c.locator('.cat-itens li').count() >= 1, '"Repetir último pedido" coloca os itens no carrinho');
  await sairCliente(c);

  // ---------- Cliente em cobrança: boleto bloqueado ----------
  if (emCobranca) {
    await entrarCliente(c, emCobranca[1]);
    await c.goto(BASE + '/catalogo/carrinho');
    await qtd(c, prod[0], 30); await c.goto(BASE + '/catalogo/carrinho');
    ok((await c.locator('.cat-forma.bloqueada').innerText()).includes('Boleto'), 'boleto bloqueado para cliente em cobrança');
    await sairCliente(c);
  } else ok(false, 'sem cliente em cobrança na demonstração');

  // ---------- Link do vendedor ----------
  const [vid, vcod] = sql("select concat(id,'|',codigo_vendedor) from usuarios where email='televendas2@demo.local'").split('|');
  await c.goto(BASE + '/catalogo/v/' + vcod);
  await entrarCliente(c, cnpj);
  ok((await c.locator('.cat-faixa').innerText()).includes('Atendimento'), 'faixa mostra o vendedor do link');
  await qtd(c, prod[0], Math.ceil(minimo / Number(prod[1])) + 1);
  await c.goto(BASE + '/catalogo/carrinho'); await c.locator('label.opcao', { hasText: 'Cartão' }).first().locator('input').check();
  await c.click('.cat-finalizar button[type=submit]'); await c.waitForLoadState();
  ok(sql(`select vendedor_id from pedidos where cliente_id=${cliId} order by id desc limit 1`) === vid, 'pedido pelo link vai para o vendedor do link');
  await sairCliente(c);

  // ---------- Senha errada 5× bloqueia ----------
  const outro = acessos.find(([id]) => id !== cliId);
  for (let i = 0; i < 5; i++) await entrarCliente(c, outro[1], 'errada123');
  await entrarCliente(c, outro[1]);
  ok((await c.locator('.aviso.erro').innerText()).includes('Muitas tentativas'), 'senha errada 5× bloqueia (mesmo com a senha certa depois)');
  sql(`update cliente_acessos set bloqueado_ate=null, tentativas=0 where cliente_id=${outro[0]}`);

  // ---------- Primeiro acesso por código ----------
  const novoCli = sql("select concat(id,'|',cnpj) from clientes where cnpj is not null and tipo='cliente' and id not in (select cliente_id from cliente_acessos) limit 1").split('|');
  await c.goto(BASE + '/catalogo/entrar?modo=codigo');
  await c.fill('#cnpj1', novoCli[1]); await c.click('form[action$="/catalogo/codigo"] button'); await c.waitForLoadState();
  const codigo = await c.locator('[data-codigo-demo]').innerText();
  ok(/^\d{6}$/.test(codigo), 'código de 6 dígitos gerado (modo demonstração mostra na tela)');
  await c.fill('#cnpj2', novoCli[1]); await c.fill('#codigo', '000000'); await c.fill('#nova', 'SenhaNova123');
  await c.click('form[action$="/catalogo/senha"] button'); await c.waitForLoadState();
  ok((await c.locator('.aviso.erro').innerText()).includes('Código inválido'), 'código errado é recusado');
  await c.fill('#cnpj2', novoCli[1]); await c.fill('#codigo', codigo); await c.fill('#nova', 'SenhaNova123');
  await c.click('form[action$="/catalogo/senha"] button'); await c.waitForLoadState();
  ok((await c.locator('.aviso.sucesso').innerText()).includes('Senha criada'), 'código certo: cria a senha e entra');
  await sairCliente(c);
  await entrarCliente(c, novoCli[1], 'SenhaNova123');
  ok(c.url().endsWith('/catalogo') && await c.locator('form[action$="/catalogo/sair"]').count() === 1, 'entra depois com a senha criada');
  await sairCliente(c);
  sql(`update cliente_acessos set codigo_hash='$(php -r "echo password_hash('111111', PASSWORD_DEFAULT);")', codigo_expira_em=now() - interval 1 minute where cliente_id=${novoCli[0]}`);
  ok(true, '(código vencido preparado)');
  ok(sql(`select count(*) from clientes where id=${novoCli[0]}`) === '1', 'cliente existe');
  ok(c.url().indexOf(novoCli[1]) === -1 && c.url().indexOf(cnpj) === -1, 'CNPJ nunca aparece na URL');

  // ---------- Equipe ----------
  const p = await (await b.newContext({ viewport: { width: 1440, height: 1000 } })).newPage();
  p.on('pageerror', e => erros.push('equipe: ' + e.message)); p.on('dialog', d => d.accept());
  await entrarEquipe(p, 'televendas1@demo.local');
  const meuCli = sql("select c.id from clientes c join usuarios u on u.id=c.responsavel_id where u.email='televendas1@demo.local' and c.tipo='cliente' and c.credito_situacao='ok' and c.id not in (select cliente_id from cobrancas where situacao='aberta') limit 1");
  await p.goto(BASE + '/carteira/lista?abrir=' + meuCli);
  await p.waitForSelector('.ficha-pedidos, .ficha-secao');
  ok(await p.locator('.ficha-secao a', { hasText: 'Novo pedido' }).count() === 1, 'ficha do cliente tem "Novo pedido"');
  ok(await p.locator('.ficha-pedidos li').count() >= 1, 'ficha mostra os pedidos do cliente');
  await p.locator('form[action$="/acesso-catalogo"] button').click(); await p.waitForTimeout(1200);
  ok(/^\d{6}$/.test(await p.locator('[data-codigo-catalogo]').innerText()), 'vendedor gera código de acesso ao catálogo na ficha');
  await p.goto(BASE + '/pedidos/novo?cliente=' + meuCli);
  const linhas = p.locator('[data-linha]');
  for (let i = 0; i < 4; i++) await linhas.nth(i * 3).locator('.ped-qtd').fill('25');
  await p.locator('label.opcao', { hasText: 'Pix' }).locator('input').check();
  const totalTela = (await p.locator('[data-subtotal]').innerText()).replace(/\s/g, ' ');
  await p.screenshot({ path: DIR + '/ped-novo.png' });
  await Promise.all([p.waitForNavigation(), p.click('[data-enviar]')]);
  const idEquipe = p.url().split('/').pop();
  ok(sql(`select count(*) from pedido_itens where pedido_id=${idEquipe} and (codigo is null or codigo='')`) === '0', 'itens do pedido gravam o código do produto');
  ok(/^\d+$/.test(idEquipe) && (await p.locator('.aviso.sucesso').innerText()).includes('criado'), `vendedor cria pedido pela ficha (#${idEquipe}, ${totalTela})`);
  ok(brl(sql(`select subtotal from pedidos where id=${idEquipe}`)) === totalTela, 'subtotal da tela = subtotal gravado');
  await p.goto(BASE + '/pedidos');
  const visiveis = Number((await p.locator('.paginacao-barra span').first().innerText()).split(' ')[0].replace('.', ''));
  const esperado = Number(sql("select count(*) from pedidos p join clientes c on c.id=p.cliente_id, usuarios u where u.email='televendas1@demo.local' and (p.vendedor_id=u.id or c.responsavel_id=u.id)"));
  ok(visiveis === esperado, `vendedor vê só os pedidos dele (${visiveis})`);
  // uuid repetido
  await p.goto(BASE + '/pedidos/novo?cliente=' + meuCli);
  const token = await p.locator('meta[name=csrf]').getAttribute('content');
  const forma = await p.locator('label.opcao', { hasText: 'Pix' }).locator('input').getAttribute('value');
  const prodEq = await linhas.first().locator('.ped-qtd').getAttribute('name');
  const form = { cliente_id: meuCli, uuid: 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee', pagamento: forma, _csrf: token }; form[prodEq] = '300';
  const antes = sql('select count(*) from pedidos');
  await p.request.post(BASE + '/pedidos', { form }); await p.request.post(BASE + '/pedidos', { form });
  ok(Number(sql('select count(*) from pedidos')) === Number(antes) + 1, 'o mesmo pedido (uuid) enviado 2× entra uma vez só');
  ok((await p.request.post(BASE + `/aprovacao/${idEquipe}/aprovar`, { form: { _csrf: token } })).status() === 403, 'vendedor não aprova (403)');
  await sairEquipe(p);

  // ---------- Financeiro ----------
  await entrarEquipe(p, 'financeiro@demo.local');
  await p.goto(BASE + '/aprovacao');
  ok(await p.locator('.ped-fila-item').count() >= 4, 'fila de aprovação (' + await p.locator('.ped-fila-item').count() + ')');
  ok(await p.locator('.ped-credito').count() >= 4, 'cada pedido mostra o crédito do cliente');
  await p.screenshot({ path: DIR + '/ped-aprovacao.png', fullPage: true });
  const item = p.locator('.ped-fila-item', { hasText: '#' + idEquipe });
  await Promise.all([p.waitForNavigation(), item.locator('form[action$="/aprovar"] button').click()]);
  ok(sql(`select situacao from pedidos where id=${idEquipe}`) === 'aprovado', 'Financeiro aprova');
  const recusar = p.locator('.ped-fila-item', { hasText: '#' + ped[0] });
  await recusar.locator('summary', { hasText: 'Recusar' }).click();
  await recusar.locator('textarea[name=motivo]').fill('Cliente pediu para refazer com outra quantidade');
  await Promise.all([p.waitForNavigation(), recusar.locator('form[action$="/recusar"] button').click()]);
  ok(sql(`select situacao from pedidos where id=${ped[0]}`) === 'recusado', 'Financeiro recusa com motivo');
  // faturamento simulado
  await p.goto(BASE + '/pedidos/' + idEquipe);
  await Promise.all([p.waitForNavigation(), p.click('form[action$="/simular-faturamento"] button')]);
  const fat = sql(`select concat(situacao,'|',coalesce(numero_nf,'')) from pedidos where id=${idEquipe}`).split('|');
  ok(fat[0] === 'faturado' && fat[1] !== '', `faturamento volta do ERP (NF ${fat[1]})`);
  ok(sql(`select ultima_compra_em from clientes where id=${meuCli}`) === new Date().toISOString().slice(0, 10) || sql(`select ultima_compra_em = curdate() from clientes where id=${meuCli}`) === '1', 'ficha do cliente: última compra = hoje');
  ok(sql(`select count(*) from cliente_eventos where cliente_id=${meuCli} and descricao like 'Pedido #${idEquipe} faturado%'`) === '1', 'evento "Pedido faturado" na linha do tempo');
  ok(Number(sql(`select count(*) from auditoria where entidade='pedido' and entidade_id=${idEquipe}`)) >= 2, 'auditoria do pedido (criar e aprovar)');
  await p.screenshot({ path: DIR + '/ped-ver.png', fullPage: true });
  await sairEquipe(p);

  // cliente vê o motivo da recusa
  await entrarCliente(c, cnpj); await c.goto(BASE + '/catalogo/pedidos');
  await c.locator('.cat-pedido', { hasText: '#' + ped[0] }).locator('summary').click();
  ok((await c.locator('.cat-pedido', { hasText: '#' + ped[0] }).innerText()).includes('outra quantidade'), 'cliente vê o motivo da recusa em "Meus pedidos"');
  await sairCliente(c);

  // ---------- Produtos (Marketing) e permissões ----------
  await entrarEquipe(p, 'marketing@demo.local');
  await p.goto(BASE + '/produtos');
  ok(await p.locator('tbody tr').count() >= 150, 'Produtos lista os itens com preços das 3 tabelas');
  const linhaMg = p.locator('tr', { hasText: '8+ Magnésio' }).first();
  await linhaMg.locator('input[name=visivel_catalogo]').uncheck();
  await Promise.all([p.waitForNavigation(), linhaMg.locator('form button').click()]);
  const r404 = await c.goto(BASE + '/catalogo/produto/magnesio');
  ok(r404.status() === 404, 'produto desligado some do catálogo');
  sql("update produtos set visivel_catalogo=1 where slug='magnesio'");
  await sairEquipe(p);
  await entrarEquipe(p, 'producao@demo.local');
  for (const u of ['/pedidos', '/aprovacao', '/produtos']) ok((await p.goto(BASE + u)).status() === 403, `Produção recebe 403 em ${u}`);
  await sairEquipe(p);

  ok(erros.length === 0, 'sem erros de JavaScript ' + erros.join(' | '));
  console.log(falhas ? `\n${falhas} FALHA(S)` : '\nTUDO OK'); await b.close();
})();
