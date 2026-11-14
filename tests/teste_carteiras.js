// Teste automático (Playwright). Rodar todos: bash tests/rodar.sh  ·  só este: node tests/teste_carteiras.js
// Gestão › Carteiras: carteiras por vendedor(a), sem vendedor(a), passar, mover, juntar, criar, importar CSV/XML.
const fs = require('fs');
const path = require('path');
const { chromium } = require('playwright');
const { execSync } = require('child_process');
const BASE = 'http://127.0.0.1:8000';
const DIR = path.join(process.argv[2] || path.join(__dirname, 'saida'), 'carteiras');
let falhas = 0; const ok = (c, m) => { console.log((c ? 'OK   ' : 'FALHA') + ' ' + m); if (!c) falhas++; };
const sql = (q) => execSync(`mysql perfeita --default-character-set=utf8mb4 -N -e "${q.replace(/"/g, '\\"')}"`).toString().trim();
async function entrar(p, email) {
  await p.goto(BASE + '/entrar'); await p.fill('#email', email); await p.fill('#senha', 'Demo@2026'); await p.click('button[type=submit]'); await p.waitForLoadState();
  if (await p.locator('[data-novidades-ok]').count()) { await p.click('[data-novidades-ok]'); await p.waitForLoadState(); }
}
const aviso = async (p) => (await p.locator('.aviso').allInnerTexts()).join(' | ');

(async () => {
  fs.mkdirSync(DIR, { recursive: true });
  const b = await chromium.launch();
  const erros = [];
  const nova = async () => { const p = await (await b.newContext({ viewport: { width: 1300, height: 900 } })).newPage(); p.on('pageerror', e => erros.push(e.message)); await p.route(/open-meteo|jsdelivr/, r => r.fulfill({ body: '{}' })); p.on('dialog', d => d.accept()); return p; };
  const tv = sql("select id from funcoes where slug='televendas'");
  const uid = (email) => sql(`select id from usuarios where email='${email}'`);
  const tv1 = uid('televendas1@demo.local'), tv2 = uid('televendas2@demo.local'), gestora = uid('gestora.televendas@demo.local');

  ok(sql('select count(*) from clientes where carteira_id is null') === '0', 'instalação: todo cliente numa carteira');
  ok(sql('select count(*) from clientes c join carteiras k on k.id=c.carteira_id where not (k.responsavel_id <=> c.responsavel_id)') === '0', 'carteira e responsável batem');

  const g = await nova();
  await entrar(g, 'gestor@demo.local');
  await g.goto(BASE + '/carteiras');
  ok(await g.locator('[data-sem-vendedor]').count() === 1 && await g.locator('.ct-cartao.sem').count() >= 1, 'lista destaca a carteira sem vendedor(a)');
  const antiga = sql("select id from carteiras where nome like '%Carteira antiga%'");
  const qtdAntiga = Number(sql(`select count(*) from clientes where carteira_id=${antiga}`));

  // ---------- Passar a carteira sem vendedora para Televendas 2 ----------
  const eventosAntes = Number(sql("select count(*) from cliente_eventos where tipo='responsavel'"));
  const card = g.locator(`[data-carteira="${antiga}"]`);
  await card.locator('select[name=vendedor_id]').selectOption(tv2);
  await Promise.all([g.waitForNavigation(), card.locator('button:has-text("Passar")').click()]);
  ok(sql(`select count(*) from clientes where carteira_id=${antiga} and responsavel_id=${tv2} and funcao_id=${tv}`) === String(qtdAntiga), `passar a carteira: os ${qtdAntiga} clientes vão para Televendas 2`);
  ok(Number(sql("select count(*) from cliente_eventos where tipo='responsavel'")) - eventosAntes === qtdAntiga, 'cada cliente ganha o evento na linha do tempo');
  ok(sql(`select count(*) from notificacoes where usuario_id=${tv2} and tipo='cliente_recebido'`) !== '0', 'quem recebe é avisado (um aviso só)');

  // ---------- Mover selecionados ----------
  const k1 = sql(`select id from carteiras where responsavel_id=${tv1} and ativa=1 limit 1`);
  await g.goto(BASE + `/carteiras/${antiga}`);
  const marcar = g.locator('input[name="cliente[]"]');
  await marcar.nth(0).check(); await marcar.nth(1).check(); await marcar.nth(2).check();
  ok((await g.locator('[data-qtd-marcados]').innerText()) === '3', 'contador de marcados');
  await g.selectOption('[data-mover] select[name=destino_id]', k1);
  await Promise.all([g.waitForNavigation(), g.click('[data-botao-mover]')]);
  ok(sql(`select count(*) from clientes where carteira_id=${k1} and responsavel_id=${tv1}`) === '33', 'mover 3 selecionados para a carteira do Televendas 1');

  // ---------- Nova carteira + importar CSV ----------
  await g.goto(BASE + '/carteiras');
  await g.click('[data-abrir="#nova-carteira"]');
  await g.fill('#nova-carteira input[name=nome]', 'Televendas · Serra (teste)');
  await g.selectOption('#nova-carteira select[name=funcao_id]', tv);
  await Promise.all([g.waitForNavigation(), g.click('#nova-carteira button[type=submit]')]);
  const nova1 = sql("select id from carteiras where nome='Televendas · Serra (teste)'");
  ok(nova1 && sql(`select responsavel_id is null from carteiras where id=${nova1}`) === '1', 'nova carteira (sem vendedor(a) por enquanto)');
  const existente = sql(`select concat(cnpj,'|',razao_social,'|',cidade,'|',uf) from clientes where carteira_id=${k1} and cnpj is not null limit 1`).split('|');
  const csv = path.join(DIR, 'carteira.csv');
  fs.writeFileSync(csv, `Razão social;CNPJ;Cidade;UF;Telefone\n${existente[1]};${existente[0]};${existente[2]};${existente[3]};\nLoja Nova Serra Teste;11.222.333/0001-81;Caxias do Sul;RS;54999990000\nSem cidade;;;RS;\n`);
  await g.setInputFiles('input[name=arquivo]', csv);
  await Promise.all([g.waitForNavigation(), g.click('button:has-text("Ver prévia")')]);
  ok((await g.content()).includes('mudam para a carteira'), 'prévia: já cadastrados mudam para a carteira');
  await Promise.all([g.waitForNavigation(), g.click('button:has-text("Confirmar importação")')]);
  ok(sql(`select count(*) from clientes where carteira_id=${nova1}`) === '2' && sql(`select carteira_id from clientes where cnpj='${existente[0]}'`) === nova1, 'CSV: o CNPJ existente mudou de carteira e o novo entrou nela');
  ok(sql(`select count(*) from clientes where carteira_id=${nova1} and responsavel_id is not null`) === '0', 'carteira sem vendedor(a): clientes ficam sem responsável');

  // ---------- XML simples, NF-e e XML perigoso ----------
  const xml = path.join(DIR, 'clientes.xml');
  fs.writeFileSync(xml, '<?xml version="1.0"?><clientes><cliente><razao_social>Empório XML Teste</razao_social><cnpj>22.333.444/0001-90</cnpj><cidade>Pelotas</cidade><uf>RS</uf></cliente><cliente><razao_social>Farmácia XML Dois</razao_social><cidade>Rio Grande</cidade><uf>RS</uf></cliente></clientes>');
  await g.goto(BASE + `/carteiras/${nova1}`);
  await g.setInputFiles('input[name=arquivo]', xml);
  await Promise.all([g.waitForNavigation(), g.click('button:has-text("Ver prévia")')]);
  await Promise.all([g.waitForNavigation(), g.click('button:has-text("Confirmar importação")')]);
  ok(sql(`select count(*) from clientes where carteira_id=${nova1} and razao_social like '%XML%'`) === '2', 'XML simples importado para a carteira');
  const nfe = path.join(DIR, 'nfe.xml');
  fs.writeFileSync(nfe, '<?xml version="1.0"?><nfeProc xmlns="http://www.portalfiscal.inf.br/nfe"><NFe><infNFe><dest><CNPJ>11444777000161</CNPJ><xNome>Natural NFe Teste Ltda</xNome><enderDest><xLgr>Rua A</xLgr><nro>10</nro><xBairro>Centro</xBairro><xMun>Santa Maria</xMun><UF>RS</UF><CEP>97000000</CEP><fone>5532220000</fone></enderDest></dest></infNFe></NFe></nfeProc>');
  await g.goto(BASE + `/carteiras/${nova1}`);
  await g.setInputFiles('input[name=arquivo]', nfe);
  await Promise.all([g.waitForNavigation(), g.click('button:has-text("Ver prévia")')]);
  await Promise.all([g.waitForNavigation(), g.click('button:has-text("Confirmar importação")')]);
  ok(sql("select concat(cidade,'|',uf,'|',carteira_id) from clientes where cnpj='11444777000161'") === `Santa Maria|RS|${nova1}`, 'XML de NF-e: o destinatário vira cliente na carteira');
  const mau = path.join(DIR, 'mau.xml');
  fs.writeFileSync(mau, '<?xml version="1.0"?><!DOCTYPE x [<!ENTITY e SYSTEM "file:///etc/passwd">]><clientes><cliente><razao_social>&e;</razao_social><cidade>X</cidade><uf>RS</uf></cliente></clientes>');
  await g.goto(BASE + `/carteiras/${nova1}`);
  await g.setInputFiles('input[name=arquivo]', mau);
  await Promise.all([g.waitForNavigation(), g.click('button:has-text("Ver prévia")')]);
  ok((await aviso(g)).includes('DOCTYPE') && sql("select count(*) from importacoes where arquivo='mau.xml'") === '0', 'XML com DOCTYPE/ENTITY é recusado');

  // ---------- Juntar ----------
  await g.goto(BASE + `/carteiras/${nova1}`);
  await g.selectOption('form[action$="/juntar"] select', k1);
  await Promise.all([g.waitForNavigation(), g.click('form[action$="/juntar"] button')]);
  ok(sql(`select ativa from carteiras where id=${nova1}`) === '0' && sql(`select count(*) from clientes where carteira_id=${nova1}`) === '0' && sql(`select count(*) from clientes where carteira_id=${k1}`) === '37', 'juntar: tudo vai para a outra carteira e a vazia fica inativa');

  // ---------- Gestora: só o setor dela; vendedor: sem acesso ----------
  const ge = await nova();
  await entrar(ge, 'gestora.televendas@demo.local');
  await ge.goto(BASE + '/carteiras');
  const setoresVistos = await ge.$$eval('.ct-setor', (h) => h.map((x) => x.innerText));
  ok(setoresVistos.length === 1 && setoresVistos[0].startsWith('Televendas'), 'gestora vê só as carteiras do Televendas');
  const ext = sql("select k.id from carteiras k join funcoes f on f.id=k.funcao_id where f.slug='externo' limit 1");
  await ge.goto(BASE + `/carteiras/${ext}`);
  ok(g.url !== undefined && (await aviso(ge)).includes('não encontrada'), 'gestora não abre carteira do Externo');
  const csrf = await ge.evaluate(() => document.querySelector('meta[name=csrf]').content);
  await ge.request.post(BASE + `/carteiras/${ext}/passar`, { form: { _csrf: csrf, vendedor_id: gestora } });
  ok(sql(`select responsavel_id from carteiras where id=${ext}`) !== gestora, 'gestora não passa carteira de outro setor');
  const v = await nova();
  await entrar(v, 'televendas1@demo.local');
  ok((await v.goto(BASE + '/carteiras')).status() === 403, 'vendedor(a) comum não entra em Carteiras');

  // ---------- Desativar usuário: carteira fica sem vendedor(a) ----------
  const adm = await nova();
  await entrar(adm, 'admin@demo.local');
  await adm.goto(BASE + `/usuarios/${tv1}/editar`);
  await adm.uncheck('input[name=ativo]');
  await Promise.all([adm.waitForNavigation(), adm.click('button:has-text("Salvar alterações")')]);
  ok(sql(`select responsavel_id is null from carteiras where id=${k1}`) === '1' && sql(`select count(*) from clientes where carteira_id=${k1} and responsavel_id is not null`) === '0', 'pessoa desativada: a carteira dela fica sem vendedor(a)');
  ok((await aviso(adm)).includes('sem vendedor'), 'admin é avisado para passar a carteira');
  const n = sql('select count(*) from clientes where responsavel_id is null');
  execSync('php cron/fila_reativacao.php', { cwd: path.join(__dirname, '..') });
  ok(sql('select count(*) from clientes where responsavel_id is null') === n, 'fila de reativação não mexe em clientes de carteira sem vendedor(a)');

  ok(erros.length === 0, 'nenhum erro de JavaScript' + (erros.length ? ': ' + erros.join(' / ') : ''));
  await b.close();
  console.log(falhas ? `\n${falhas} FALHA(S)` : '\nTudo certo.');
  process.exit(falhas ? 1 : 0);
})();
