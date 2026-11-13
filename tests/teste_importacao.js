// Teste automático (Playwright). Rodar todos: bash tests/rodar.sh  ·  só este: node tests/teste_importacao.js
const path = require('path');
const RECURSOS = path.join(__dirname, 'recursos');
const { chromium } = require('playwright');
const { execSync } = require('child_process');
const BASE = 'http://127.0.0.1:8000', DIR = process.argv[2] || path.join(__dirname, 'saida');
let falhas = 0;
const ok = (c, m) => { console.log((c ? 'OK   ' : 'FALHA') + ' ' + m); if (!c) falhas++; };
const sql = (q) => execSync(`mysql perfeita --default-character-set=utf8mb4 -N -e "${q}"`).toString().trim();
const contagens = async (p) => (await p.locator('.agenda-resumo strong').allInnerTexts()).join(',');
(async () => {
  const b = await chromium.launch(); const p = await (await b.newContext({ viewport: { width: 1440, height: 950 } })).newPage();
  const erros = []; p.on('pageerror', e => erros.push(e.message));
  await p.goto(BASE + '/entrar'); await p.fill('#email', 'gestor@demo.local'); await p.fill('#senha', 'Demo@2026'); await p.click('button[type=submit]');

  // A planilha de exemplo tem um cliente que "já existe" (CNPJ fictício 11.222.333/0001-81): cria antes de importar
  sql("insert ignore into clientes (razao_social, cnpj, tipo, uf, cidade, responsavel_id) select 'Cliente Já Existente (teste)', '11222333000181', 'cliente', 'SP', 'São Paulo', id from usuarios where email='televendas1@demo.local'");

  // CSV (Windows-1252, separador ;)
  await p.goto(BASE + '/carteira/importar');
  await p.setInputFiles('#arquivo', RECURSOS + '/carteira_vendedor.csv');
  await p.click('form[enctype] button[type=submit]');
  await p.waitForURL(/importar\/\d+/);
  ok(await contagens(p) === '2,1,2,1', 'CSV: 2 novos, 1 completar, 2 duplicados, 1 erro → ' + await contagens(p));
  ok((await p.content()).includes('Empório Teste Importação'), 'acentos do CSV Windows-1252 preservados');
  await p.screenshot({ path: DIR + '/importacao-previa.png' });
  const antes = Number(sql('select count(*) from clientes'));
  p.once('dialog', d => d.accept());
  await Promise.all([p.waitForNavigation(), p.click('form[action$="/confirmar"] button')]);
  ok(Number(sql('select count(*) from clientes')) === antes + 2, 'confirmar grava só os 2 novos');
  ok(sql("select concat(u.codigo_vendedor,'|',s.nome) from clientes c join usuarios u on u.id=c.responsavel_id join segmentos s on s.id=c.segmento_id where c.razao_social='Empório Teste Importação'") === 'TV01|Produtos naturais', 'vendedor pelo código TV01 e segmento normalizado');
  ok(sql("select u.email from clientes c join usuarios u on u.id=c.responsavel_id where c.razao_social='Drogaria Nova Era'") === 'externo1@demo.local', 'vendedor pelo e-mail');

  // XLSX
  await p.goto(BASE + '/carteira/importar');
  await p.setInputFiles('#arquivo', RECURSOS + '/planilha.xlsx');
  await p.click('form[enctype] button[type=submit]');
  await p.waitForURL(/importar\/\d+/);
  ok(await contagens(p) === '2,0,0,0', 'XLSX: 2 novos → ' + await contagens(p));
  // cancelar não grava nada
  const antesX = sql('select count(*) from clientes');
  await Promise.all([p.waitForNavigation(), p.click('form[action$="/cancelar"] button')]);
  ok(sql('select count(*) from clientes') === antesX, 'cancelar não grava nada');

  // Backup JSON da V4
  await p.goto(BASE + '/carteira/importar');
  await p.setInputFiles('#arquivo', RECURSOS + '/backup_v4.json');
  await p.click('form[enctype] button[type=submit]');
  await p.waitForURL(/importar\/\d+/);
  const c = await contagens(p);
  const [nv, at, du] = c.split(',').map(Number);
  ok(nv === 1 && at + du === 499 && at >= 1, 'Backup V4: 1 loja nova; as 499 existentes viram atualização ou duplicado → ' + c);
  p.once('dialog', d => d.accept());
  await Promise.all([p.waitForNavigation(), p.click('form[action$="/confirmar"] button')]);
  ok(sql("select concat(e.nome,'|',(select count(*) from cliente_eventos ev where ev.cliente_id=c.id and ev.descricao like '%pediu tabela%')) from clientes c join funil_etapas e on e.id=c.etapa_id where c.razao_social='Loja Criada na V4'") === 'Contato realizado|1', 'loja da V4 entra com etapa e histórico');
  ok(sql("select concat(e.nome,'|',u.email) from clientes c join funil_etapas e on e.id=c.etapa_id join usuarios u on u.id=c.responsavel_id where c.observacoes like '%Fulano%'") === 'Em negociação|reativacao@demo.local', 'trabalho da V4 aplicado (etapa avança); nome do responsável antigo vai só para observação, não vira regra');

  // Vendedor não acessa a importação
  await p.click('form[action$="/sair"] button');
  await p.goto(BASE + '/entrar'); await p.fill('#email', 'televendas1@demo.local'); await p.fill('#senha', 'Demo@2026'); await p.click('button[type=submit]');
  ok((await p.goto(BASE + '/carteira/importar')).status() === 403, 'vendedor recebe 403 na importação');
  ok(erros.length === 0, 'sem erros de JavaScript ' + erros.join('|'));
  await b.close(); console.log(falhas ? `\n${falhas} FALHA(S)` : '\nTudo certo'); process.exit(falhas ? 1 : 0);
})();
