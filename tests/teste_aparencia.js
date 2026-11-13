// Teste automático (Playwright). Rodar todos: bash tests/rodar.sh  ·  só este: node tests/teste_aparencia.js
// Aparência: cores da empresa (Perfeita Alquimia), cores escolhidas por pessoa, contraste e catálogo com a cor da empresa.
const { chromium } = require('playwright');
const { execSync } = require('child_process');
const BASE = 'http://127.0.0.1:8000';
let falhas = 0; const ok = (c, m) => { console.log((c ? 'OK   ' : 'FALHA') + ' ' + m); if (!c) falhas++; };
const sql = (q) => execSync(`mysql perfeita --default-character-set=utf8mb4 -N -e "${q.replace(/"/g, '\\"')}"`).toString().trim();
async function entrar(p, email) { await p.goto(BASE + '/entrar'); await p.fill('#email', email); await p.fill('#senha', 'Demo@2026'); await p.click('button[type=submit]'); await p.waitForLoadState(); }
const variavel = (p, nome) => p.evaluate((n) => getComputedStyle(document.documentElement).getPropertyValue(n).trim(), nome);

(async () => {
  const b = await chromium.launch();
  const erros = [];
  const nova = async () => { const p = await (await b.newContext({ viewport: { width: 1280, height: 860 } })).newPage(); p.on('pageerror', e => erros.push(e.message)); await p.route(/open-meteo|jsdelivr/, r => r.fulfill({ body: '{}' })); return p; };

  // 1. Padrão da empresa: verde-petróleo + menta, fonte Montserrat
  ok(sql("select valor from configuracoes where chave='visual.cor_primaria'") === '#025150', 'cor primária da empresa = verde-petróleo #025150');
  ok(sql("select valor from configuracoes where chave='visual.cor_secundaria'") === '#5cd4b6', 'cor secundária da empresa = menta #5cd4b6');
  const ven = await nova();
  await entrar(ven, 'televendas1@demo.local');
  ok(await variavel(ven, '--primaria') === '#025150' && await variavel(ven, '--secundaria') === '#5cd4b6', 'sem escolha, a pessoa vê as cores da empresa');
  ok((await ven.evaluate(() => getComputedStyle(document.body).fontFamily)).includes('Montserrat'), 'fonte da marca (Montserrat)');
  ok(await ven.evaluate(() => document.fonts.check('16px Montserrat')), 'fonte Montserrat carregada do próprio sistema');

  // 2. Escolher uma combinação pronta: prévia ao vivo e gravação
  await ven.goto(BASE + '/meu-perfil');
  await ven.check('input[name=combinacao][value=oceano]');
  ok(await variavel(ven, '--primaria') === '#0b3c5d', 'prévia ao vivo antes de salvar');
  await Promise.all([ven.waitForNavigation(), ven.click('button:has-text("Salvar cores")')]);
  ok(sql("select concat(cor_primaria,' ',cor_secundaria) from usuarios where email='televendas1@demo.local'") === '#0b3c5d #5bc0eb', 'combinação "Oceano" gravada');
  await ven.goto(BASE + '/carteira');
  ok(await variavel(ven, '--primaria') === '#0b3c5d', 'a cor escolhida vale em todas as telas');
  const menu = await ven.evaluate(() => getComputedStyle(document.querySelector('.menu-lateral')).backgroundColor);
  ok(menu === 'rgb(11, 60, 93)', 'menu lateral na cor primária escolhida (' + menu + ')');
  ok(sql("select count(*) from auditoria where acao='aparencia'") !== '0', 'troca de cores registrada na auditoria');

  // 3. Cor personalizada clara: o texto por cima fica escuro (contraste)
  await ven.goto(BASE + '/meu-perfil');
  await ven.locator('input[name=cor_primaria]').evaluate((i) => { i.value = '#f5e663'; i.dispatchEvent(new Event('input', { bubbles: true })); i.dispatchEvent(new Event('change', { bubbles: true })); });
  ok(await ven.locator('input[name=combinacao][value=personalizada]').isChecked(), 'mexer na cor marca "Personalizada"');
  await Promise.all([ven.waitForNavigation(), ven.click('button:has-text("Salvar cores")')]);
  ok(await variavel(ven, '--primaria-texto') === '#10201d', 'primária clara → texto escuro por cima');
  const escura = await variavel(ven, '--primaria-escura');
  ok(escura !== '#f5e663', 'títulos usam um tom escurecido da cor clara (' + escura + ')');

  // 4. Valor inválido é recusado
  const tok = await ven.locator('meta[name=csrf]').getAttribute('content');
  await ven.request.post(BASE + '/meu-perfil/aparencia', { form: { cor_primaria: 'red;}body{display:none', cor_secundaria: '#5cd4b6' }, headers: { 'X-CSRF-Token': tok } });
  ok(sql("select cor_primaria from usuarios where email='televendas1@demo.local'") === '#f5e663', 'cor inválida (tentativa de injetar CSS) é recusada');

  // 5. Catálogo do cliente sempre com a cor da empresa
  const cat = await nova();
  await cat.goto(BASE + '/catalogo');
  ok(await variavel(cat, '--primaria') === '#025150', 'catálogo do cliente usa a cor da empresa');

  // 6. Voltar ao padrão
  await ven.goto(BASE + '/meu-perfil');
  await Promise.all([ven.waitForNavigation(), ven.click('button:has-text("Voltar ao padrão da empresa")')]);
  ok(sql("select ifnull(cor_primaria,'NULL') from usuarios where email='televendas1@demo.local'") === 'NULL' && await variavel(ven, '--primaria') === '#025150', '"Voltar ao padrão da empresa"');

  // 7. Admin muda o padrão da empresa em Configurações
  const adm = await nova();
  await entrar(adm, 'admin@demo.local');
  if (await adm.locator('[data-novidades-ok]').count()) await adm.click('[data-novidades-ok]');
  await adm.goto(BASE + '/configuracoes');
  const campo = adm.locator('input[type=color][name="config[visual.cor_primaria]"]');
  ok(await campo.count() === 1, 'Configurações › Aparência com seletor de cor');
  await campo.evaluate((i) => { i.value = '#15a454'; });
  await Promise.all([adm.waitForNavigation(), adm.locator('form:has(input[name="config[visual.cor_primaria]"]) button[type=submit]').first().click()]);
  await ven.goto(BASE + '/');
  ok(await variavel(ven, '--primaria') === '#15a454', 'padrão da empresa vale para quem não escolheu cor');
  sql("update configuracoes set valor='#025150' where chave='visual.cor_primaria'");

  ok(erros.length === 0, 'sem erros de JavaScript ' + erros.join(' / '));
  await b.close();
  console.log(falhas ? `\n${falhas} FALHA(S)` : '\nTudo certo');
  process.exit(falhas ? 1 : 0);
})();
