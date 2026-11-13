// Teste automático (Playwright). Rodar todos: bash tests/rodar.sh  ·  só este: node tests/teste_ajustes.js
// Ajustes depois da Fase 0: catálogo sem preço, código do produto, busca rápida, ficha do funcionário,
// aniversariantes, Início do admin, Academia "Como usar o One", mensagens num lugar só e backup automático.
const path = require('path');
const fs = require('fs');
const RECURSOS = path.join(__dirname, 'recursos');
const { chromium } = require('playwright');
const { execSync } = require('child_process');
const BASE = 'http://127.0.0.1:8000', DIR = process.argv[2] || path.join(__dirname, 'saida');
const RAIZ = path.join(__dirname, '..');
let falhas = 0; const ok = (c, m) => { console.log((c ? 'OK   ' : 'FALHA') + ' ' + m); if (!c) falhas++; };
const sql = (q) => execSync(`mysql perfeita --default-character-set=utf8mb4 -N -e "${q.replace(/"/g, '\\"')}"`).toString().trim();
const aviso = async (p) => (await p.locator('.aviso').allInnerTexts()).join(' | ');
async function entrar(p, email) { await p.goto(BASE + '/entrar'); await p.fill('#email', email); await p.fill('#senha', 'Demo@2026'); await p.click('button[type=submit]'); await p.waitForLoadState(); }
async function sair(p) { await p.goto(BASE + '/'); await p.click('form[action$="/sair"] button'); await p.waitForLoadState(); }

(async () => {
  const b = await chromium.launch(); const ctx = await b.newContext({ viewport: { width: 1440, height: 1000 } }); const p = await ctx.newPage();
  const erros = []; p.on('pageerror', e => erros.push(e.message));
  await p.route(/open-meteo|cdn\.jsdelivr/, r => r.fulfill({ body: '{}' }));

  // ---------- Código do produto ----------
  ok(sql("select count(*) from produtos where codigo like 'PA-%'") === sql('select count(*) from produtos where cliente_exclusivo_id is null') && sql("select count(*) from produtos where cliente_exclusivo_id is not null and codigo not like 'EX-%'") === '0', 'demonstração: todos os produtos com código (PA- do catálogo, EX- das fórmulas exclusivas)');
  await entrar(p, 'marketing@demo.local');
  const prod = sql("select id from produtos where codigo='PA-1001'");
  await p.goto(BASE + '/produtos/catalogo?q=PA-1001');
  ok(await p.locator('tbody tr').count() === 1, 'tela Produtos busca pelo código');
  await p.fill(`#produto-${prod} input[name=codigo]`, 'pa-1002');
  await Promise.all([p.waitForNavigation(), p.click(`#produto-${prod} form button`)]);
  ok((await aviso(p)).includes('já é de outro produto') && sql(`select codigo from produtos where id=${prod}`) === 'PA-1001', 'código repetido é recusado');
  await p.fill(`#produto-${prod} input[name=codigo]`, 'abc-777');
  await Promise.all([p.waitForNavigation(), p.click(`#produto-${prod} form button`)]);
  ok(sql(`select codigo from produtos where id=${prod}`) === 'ABC-777', 'código editado (em maiúsculas)');
  // Importação por planilha (CSV com Código e Produto)
  const dois = sql("select concat(id,'|',nome) from produtos where codigo in ('PA-1003','PA-1004') order by codigo").split('\n').map(l => l.split('|'));
  const csv = path.join(DIR, 'codigos.csv');
  fs.mkdirSync(DIR, { recursive: true });
  fs.writeFileSync(csv, `Código;Produto\nNOVO-01;${dois[0][1]}\nNOVO-02;${dois[1][1].toUpperCase()}\nNOVO-03;Produto Que Não Existe\n;Sem código\n`);
  await p.setInputFiles('#planilha-codigos', csv);
  await Promise.all([p.waitForNavigation(), p.click('.prod-importar button')]);
  ok((await p.locator('[data-previa-codigos]').innerText()).includes('2 produto(s) vão receber código'), 'prévia: 2 ligados pelo nome (maiúsculas não importam), o resto avisado');
  ok(sql(`select codigo from produtos where id=${dois[0][0]}`) === 'PA-1003', 'nada gravado antes de confirmar');
  await Promise.all([p.waitForNavigation(), p.click('[data-previa-codigos] button[value=aplicar]')]);
  ok(sql(`select concat(a.codigo,'|',b.codigo) from produtos a, produtos b where a.id=${dois[0][0]} and b.id=${dois[1][0]}`) === 'NOVO-01|NOVO-02', 'códigos importados');
  await sair(p);
  // Catálogo e pedido com o código
  await p.goto(BASE + '/catalogo?q=NOVO-01');
  ok(await p.locator('.cat-card').count() === 1 && (await p.locator('.cat-card .cod-produto').innerText()) === 'NOVO-01', 'catálogo busca e mostra o código');

  // ---------- Catálogo: tabela sem preço avisa o cliente ----------
  await p.goto(BASE + '/catalogo/entrar');
  const cnpj = sql("select c.cnpj from cliente_acessos a join clientes c on c.id=a.cliente_id order by a.id limit 1");
  const tabela = sql(`select tabela_preco_id from clientes where cnpj='${cnpj}'`);
  sql(`create table if not exists _precos_teste as select * from produto_precos where tabela_preco_id=${tabela}`);
  sql(`delete from produto_precos where tabela_preco_id=${tabela}`);
  await p.fill('#cnpj', cnpj); await p.fill('#senha', 'Demo@2026');
  await Promise.all([p.waitForNavigation(), p.click('form button[type=submit]')]);
  ok(await p.locator('.cat-aviso-preco').count() === 1, 'cliente com a tabela sem preço vê o aviso (não uma página vazia)');
  sql(`insert into produto_precos select * from _precos_teste`); sql('drop table _precos_teste');
  await p.goto(BASE + '/catalogo');
  ok(await p.locator('.cat-aviso-preco').count() === 0 && await p.locator('.cat-qtd').count() > 10, 'com preço, os produtos voltam com quantidade');
  await ctx.clearCookies();

  // ---------- Busca rápida ----------
  await entrar(p, 'televendas1@demo.local');
  const vend = sql("select id from usuarios where email='televendas1@demo.local'");
  const meu = sql(`select concat(id,'|',razao_social) from clientes where responsavel_id=${vend} and excluido_em is null order by id limit 1`).split('|');
  const deOutro = sql(`select razao_social from clientes where responsavel_id<>${vend} and excluido_em is null and razao_social not in (select razao_social from clientes where responsavel_id=${vend}) order by id limit 1`);
  let r = await (await p.request.get(BASE + '/busca?q=' + encodeURIComponent(meu[1].slice(0, 12)), { headers: { Accept: 'application/json' } })).json();
  ok(r.clientes.some(c => String(c.id) === meu[0]), 'busca rápida acha cliente da própria carteira');
  r = await (await p.request.get(BASE + '/busca?q=' + encodeURIComponent(deOutro), { headers: { Accept: 'application/json' } })).json();
  ok(!r.clientes.some(c => c.nome === deOutro), 'busca rápida não mostra cliente de outro vendedor');
  r = await (await p.request.get(BASE + '/busca?q=NOVO-02', { headers: { Accept: 'application/json' } })).json();
  ok(r.produtos.length === 1 && r.produtos[0].codigo === 'NOVO-02', 'busca rápida acha produto pelo código');
  await p.goto(BASE + '/');
  await p.keyboard.press('/');
  await p.keyboard.type(meu[1].slice(0, 12), { delay: 20 });
  await p.waitForSelector('[data-busca-resultados] a');
  await Promise.all([p.waitForNavigation(), p.keyboard.press('Enter')]);
  ok(p.url().includes('/carteira/lista?abrir='), 'atalho "/" + Enter abre a ficha do cliente');

  // ---------- Recompra usa as Mensagens prontas ----------
  sql("update mensagens_prontas set texto = 'TESTE {nome} faz {dias_sem_compra} dias' where categoria='Recompra' and titulo='Lembrete de recompra'");
  await p.goto(BASE + '/recompra');
  const linkWhats = await p.locator('.rc-item a[href*="wa.me"]').first().getAttribute('href').catch(() => '');
  ok(decodeURIComponent(linkWhats || '').includes('TESTE ') && decodeURIComponent(linkWhats).includes(' dias'), 'Recompra usa o modelo "Lembrete de recompra" das Mensagens prontas');
  ok(sql("select count(*) from mensagens_prontas where categoria='Cobrança' and titulo in ('Lembrete gentil','Segunda via','Proposta de acordo')") === '3', 'textos da Cobrança viraram Mensagens prontas');

  // ---------- Meu perfil e aniversariantes ----------
  await p.goto(BASE + '/meu-perfil');
  await p.setInputFiles('#foto', RECURSOS + '/foto_teste.jpg');
  await p.fill('#apelido', 'Tati');
  const nomeAntes = sql(`select nome from usuarios where id=${vend}`);
  await p.fill('#nome', nomeAntes + ' Silva');
  const hoje = sql("select date_format(curdate(), '%m-%d')");
  await p.fill('#data_nascimento', '1990-' + hoje);
  await Promise.all([p.waitForNavigation(), p.click('form[action$="/meu-perfil"] button[type=submit]')]);
  const foto = sql(`select foto from usuarios where id=${vend}`);
  ok(foto.startsWith('media/equipe/') && fs.existsSync(path.join(RAIZ, 'public', foto)), 'foto salva em media/equipe');
  ok(sql(`select nome from usuarios where id=${vend}`) === nomeAntes + ' Silva', 'Meu perfil: a pessoa muda o próprio nome');
  ok(await p.locator('input[type=email][disabled]').count() === 1, 'Meu perfil: e-mail de acesso só para consulta');
  const dims = execSync(`python3 -c "from PIL import Image; im=Image.open('${path.join(RAIZ, 'public', foto)}'); print(im.size, im.format)"`).toString().trim();
  ok(dims.includes('(400, 400) WEBP'), 'foto reduzida para 400×400 WebP (' + dims + ')');
  await p.setInputFiles('#foto', RECURSOS + '/falsa.jpg');
  await Promise.all([p.waitForNavigation(), p.click('form[action$="/meu-perfil"] button[type=submit]')]);
  ok((await aviso(p)).includes('JPG, PNG ou WebP') && sql(`select foto from usuarios where id=${vend}`) === foto, 'arquivo que não é imagem é recusado');
  await p.goto(BASE + '/');
  const card = p.locator('[data-card=aniversariantes]');
  ok(await card.locator('li.hoje', { hasText: nomeAntes + ' Silva' }).count() === 1, 'card de aniversariantes mostra quem faz aniversário hoje (pelo nome, não pelo apelido)');
  ok(!(await card.innerText()).includes('1990'), 'o ano de nascimento não aparece');
  ok(await p.locator('.topo img.avatar').count() === 1, 'foto no topo');
  await sair(p);

  // ---------- Início do admin ----------
  await entrar(p, 'admin@demo.local');
  const cards = await p.$$eval('[data-card]', els => els.map(e => e.dataset.card));
  ok(cards.includes('aprovacao') && cards.includes('aniversariantes'), 'admin vê pedidos para aprovar e aniversariantes');
  ok(await p.locator('.b-topo span', { hasText: /^Minha meta$|^Meta da equipe$/ }).count() === 0 && await p.locator('.b-hero .b-atalhos a', { hasText: 'Registrar venda' }).count() === 0, 'admin não vê meta individual nem atalhos de vendedor');
  // ficha do funcionário pelo admin
  await p.goto(BASE + `/usuarios/${vend}/editar`);
  await p.fill('#data_admissao', '2024-02-01'); await p.fill('#observacoes', 'Teste de observação');
  await Promise.all([p.waitForNavigation(), p.click('form[action$="/usuarios/' + vend + '"] button[type=submit]')]);
  ok(sql(`select concat(data_admissao,'|',observacoes,'|',apelido) from usuarios where id=${vend}`) === '2024-02-01|Teste de observação|Tati', 'admin edita a ficha do funcionário sem perder o que a pessoa preencheu');
  await sair(p);

  // ---------- Academia ----------
  await entrar(p, 'televendas1@demo.local');
  await p.goto(BASE + '/academia/sistema');
  ok(await p.locator('.crm-indice a').count() === 10 && !(await p.content()).toLowerCase().includes('leads2b'), 'Academia: 10 aulas "Como usar o One", sem citar o Leads2b');
  const r2 = await p.request.get(BASE + '/academia/crm', { maxRedirects: 0 });
  ok(r2.status() === 302 && (r2.headers()['location'] || '').endsWith('/academia/sistema'), 'endereço antigo das aulas do Leads2b leva ao treinamento do One');
  await p.goto(BASE + '/academia/trilha/crm');
  ok((await p.content()).includes('Rotina no One'), 'trilha: módulo "Rotina no One"');
  await sair(p);

  // ---------- Depois de salvar, a página continua onde estava ----------
  await entrar(p, 'producao@demo.local');
  await p.goto(BASE + '/producao/materias');
  const ultima = p.locator('tr[data-mp]').last();
  await ultima.scrollIntoViewIfNeeded();
  const yAntes = await p.evaluate(() => window.scrollY);
  await ultima.locator('input[name=quantidade]').fill('1');
  await Promise.all([p.waitForNavigation(), ultima.locator('button').click()]);
  await p.waitForTimeout(300);
  const yDepois = await p.evaluate(() => window.scrollY);
  ok(yAntes > 300 && Math.abs(yDepois - yAntes) < 60, `depois de salvar, continua no mesmo lugar da página (${yAntes} → ${yDepois})`);
  ok(await p.locator('.avisos-flutuantes .aviso-flut.sucesso').count() === 1, 'aviso de "salvo" aparece flutuando no canto');
  await p.goto(BASE + '/producao/ordens');
  ok(await p.evaluate(() => window.scrollY) === 0, 'abrir outra página começa do topo');
  await sair(p);

  // ---------- Backup automático ----------
  execSync('php cron/backup.php', { cwd: RAIZ });
  const pasta = path.join(RAIZ, 'storage/backups');
  const velho = path.join(pasta, 'one-backup-2000-01-01-000000.sql.gz');
  fs.writeFileSync(velho, 'x'); fs.utimesSync(velho, new Date('2000-01-01'), new Date('2000-01-01'));
  execSync('php cron/backup.php', { cwd: RAIZ });
  ok(!fs.existsSync(velho) && fs.readdirSync(pasta).filter(f => f.endsWith('.sql.gz')).length >= 1, 'backup automático gerado e os antigos apagados');
  await entrar(p, 'admin@demo.local');
  await p.goto(BASE + '/sistema/atualizacao');
  ok((await p.locator('[data-ultimo-backup]').innerText()).includes('o último foi em'), 'tela Atualização mostra o último backup');
  fs.rmSync(pasta, { recursive: true, force: true });

  ok(erros.length === 0, 'sem erros de JavaScript ' + erros.join(' / '));
  await b.close();
  console.log(falhas ? `\n${falhas} FALHA(S)` : '\nTudo certo');
  process.exit(falhas ? 1 : 0);
})();
