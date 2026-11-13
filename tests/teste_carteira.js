// Teste automático (Playwright). Rodar todos: bash tests/rodar.sh  ·  só este: node tests/teste_carteira.js
const path = require('path');
const RECURSOS = path.join(__dirname, 'recursos');
const { chromium } = require('playwright');
const { execSync } = require('child_process');
const BASE = 'http://127.0.0.1:8000';
const SHOTS = process.argv[2];
let falhas = 0;
const ok = (cond, msg) => { console.log((cond ? 'OK   ' : 'FALHA') + ' ' + msg); if (!cond) falhas++; };
const sql = (q) => execSync(`mysql perfeita --default-character-set=utf8mb4 -N -e "${q}"`).toString().trim();

async function entrar(page, email) {
  await page.goto(BASE + '/entrar');
  await page.fill('#email', email);
  await page.fill('#senha', 'Demo@2026');
  await page.click('button[type=submit]');
}
async function sair(page) { await page.click('form[action$="/sair"] button'); }

(async () => {
  const browser = await chromium.launch();
  const ctx = await browser.newContext({ viewport: { width: 1440, height: 950 } });
  // O ambiente de teste não acessa a internet: o MapLibre vem do pacote npm e o fundo do mapa é ignorado
  const rotas = async (c) => {
    await c.route('https://cdn.jsdelivr.net/npm/maplibre-gl@4.7.1/dist/**', (rota) => {
      const arquivo = rota.request().url().endsWith('.css') ? 'maplibre-gl.css' : 'maplibre-gl.js';
      rota.fulfill({ path: '/tmp/claude-0/npm/package/dist/' + arquivo });
    });
    await c.route('https://tiles.openfreemap.org/**', (rota) => rota.abort());
  };
  await rotas(ctx);
  const page = await ctx.newPage();
  const erros = [];
  page.on('pageerror', e => erros.push(e.message));
  page.on('console', m => { if (m.type() === 'error' && !/tiles|openfreemap|Failed to load resource|Mapa:|AJAXError|style/i.test(m.text())) erros.push(m.text()); });

  // ---------- Gestor ----------
  await entrar(page, 'gestor@demo.local');
  await page.goto(BASE + '/carteira');
  ok((await page.locator('.v4-metrica.destaque .v4-valor').innerText()).replace('.', '') === sql('select count(*) from clientes'), 'visão geral: total de estabelecimentos bate com o banco');
  await page.waitForSelector('.pino-grupo', { timeout: 15000 }).catch(() => {});
  ok(await page.locator('.pino-grupo').count() > 0, 'mapa mostra agrupamentos por estado');
  await page.waitForTimeout(1500);
  await page.screenshot({ path: SHOTS + '/carteira-visao.png', fullPage: true });

  // Lista + filtros
  await page.goto(BASE + '/carteira/lista');
  const totalRS = sql("select count(*) from clientes where uf='RS'");
  await page.selectOption('select[name=uf]', 'RS');
  await page.waitForURL(/uf=RS/);
  ok((await page.locator('.paginacao-barra > span').innerText()).includes(Number(totalRS).toLocaleString('pt-BR')), `filtro UF=RS mostra ${totalRS}`);
  const segFarm = sql("select id from segmentos where nome like 'Farm%'");
  await page.selectOption('select[name=segmento]', segFarm);
  await page.waitForURL(/segmento=/);
  const totalFarmRS = sql(`select count(*) from clientes where uf='RS' and segmento_id=${segFarm}`);
  ok((await page.locator('.paginacao-barra > span').innerText()).includes(totalFarmRS), `filtro RS + Farmácia mostra ${totalFarmRS}`);
  await page.fill('input[name=q]', 'Gaia');
  await page.press('input[name=q]', 'Enter');
  await page.waitForURL(/q=Gaia/);
  ok(await page.locator('.nome-cliente').count() === Number(sql("select count(*) from clientes where uf='RS' and segmento_id=" + segFarm + " and (razao_social like '%Gaia%' or nome_fantasia like '%Gaia%')")), 'busca por texto');
  await page.goto(BASE + '/carteira/lista?q=Empório Gaia');
  await page.screenshot({ path: SHOTS + '/carteira-lista.png' });

  // Ficha: registrar contato → etapa avança e aparece na linha do tempo
  const lead = sql("select c.id from clientes c join funil_etapas e on e.id=c.etapa_id where e.nome='Validar cadastro' order by c.id limit 1");
  await page.goto(BASE + '/carteira/lista?abrir=' + lead);
  await page.waitForSelector('.ficha-topo h2');
  await page.screenshot({ path: SHOTS + '/carteira-ficha.png' });
  await page.selectOption('#c-canal', 'Ligação');
  await page.fill('#c-resumo', 'Teste automático: conversei com o comprador.');
  await page.click('form[action$="/contato"] button[type=submit]');
  await page.waitForFunction(() => document.querySelector('.linha-tempo')?.innerText.includes('Teste automático'));
  ok(sql(`select e.nome from clientes c join funil_etapas e on e.id=c.etapa_id where c.id=${lead}`) === 'Contato realizado', 'contato avança a etapa para "Contato realizado"');
  ok((await page.locator('.ficha-selos').innerText()).includes('Contato realizado'), 'ficha recarregada mostra a etapa nova');

  // Salvar gestão comercial com próximo contato
  await page.fill('#f-proximo', '2026-12-01');
  await page.selectOption('#f-prioridade', 'Alta');
  await page.click('form[action$="/ficha"] button[type=submit]');
  await page.waitForTimeout(800);
  ok(sql(`select vence_em from tarefas where cliente_id=${lead} and concluida_em is null`) === '2026-12-01', 'próximo contato vira tarefa na agenda');
  ok(sql(`select prioridade from clientes where id=${lead}`) === 'Alta', 'prioridade salva');

  // Conflito: outra pessoa altera enquanto a ficha está aberta
  sql(`update clientes set observacoes='alterado por outra pessoa', atualizado_em = NOW() + INTERVAL 1 SECOND where id=${lead}`);
  let alerta = '';
  page.once('dialog', async d => { alerta = d.message(); await d.accept(); });
  await page.fill('#f-obs', 'minha versão');
  await page.click('form[action$="/ficha"] button[type=submit]');
  await page.waitForTimeout(1000);
  ok(alerta.includes('alterado por outra pessoa') && sql(`select observacoes from clientes where id=${lead}`) === 'alterado por outra pessoa', 'conflito de edição detectado, nada sobrescrito');

  // Ligação 1 clique
  await page.click('form[action$="/ligacao"] button:has-text("Não atendeu")');
  await page.waitForFunction(() => document.querySelector('.linha-tempo')?.innerText.includes('não atendeu'));
  ok(true, 'ligação com 1 clique registrada na linha do tempo');
  await page.click('[data-fechar-gaveta]');

  // Funil: arrastar card
  await page.setViewportSize({ width: 2700, height: 950 }); // todas as colunas visíveis, sem rolagem
  await page.goto(BASE + '/carteira/funil');
  page.on('request', r => r.method() === 'POST' && r.url().includes('/etapa') && console.log('     (POST ' + r.url().split('/clientes/')[1] + ')'));
  const colunaDestino = page.locator('.funil-coluna', { has: page.locator('h3', { hasText: 'Em negociação' }) });
  const card = page.locator('.funil-coluna', { has: page.locator('h3', { hasText: 'Não abordado' }) }).locator('.funil-card').first();
  const idCard = await card.getAttribute('data-cliente');
  // Arraste "humano": aperta, mexe um pouco sobre o próprio card e só então vai até a coluna de destino
  const origemBox = await card.boundingBox();
  const destinoBox = await colunaDestino.locator('[data-soltar]').boundingBox();
  await page.mouse.move(origemBox.x + origemBox.width / 2, origemBox.y + 20);
  await page.mouse.down();
  await page.mouse.move(origemBox.x + origemBox.width / 2 + 8, origemBox.y + 24, { steps: 4 });
  await page.mouse.move(destinoBox.x + destinoBox.width / 2, destinoBox.y + 40, { steps: 12 });
  await page.mouse.up();
  await page.waitForTimeout(1000);
  const etapaCard = sql(`select e.nome from clientes c join funil_etapas e on e.id=c.etapa_id where c.id=${idCard}`);
  ok(etapaCard === 'Em negociação', 'arrastar no funil muda a etapa (cliente ' + idCard + ': ' + etapaCard + ')');
  await page.setViewportSize({ width: 1440, height: 950 });
  await page.goto(BASE + '/carteira/funil');
  await page.screenshot({ path: SHOTS + '/carteira-funil.png' });

  // Mapa: aproxima até pontos
  await page.goto(BASE + '/carteira/mapa?uf=RS');
  await page.waitForSelector('.pino-grupo', { timeout: 15000 });
  if (await page.locator('.pino-grupo.estado').count()) await page.locator('.pino-grupo.estado').first().click();
  await page.waitForSelector('.pino-grupo[title^="Porto Alegre"]', { timeout: 15000 });
  await page.locator('.pino-grupo[title^="Porto Alegre"]').click({ force: true });
  await page.waitForSelector('.pino-loja', { timeout: 15000 }).catch(() => {});
  ok(await page.locator('.pino-loja').count() > 0, 'mapa: aproximar mostra lojas individuais');
  await page.waitForTimeout(1500);
  await page.screenshot({ path: SHOTS + '/carteira-mapa.png' });

  // Reativação
  const antes = Number(sql("select count(*) from clientes c join funcoes f on f.id=c.funcao_id where f.slug='prospeccao_reativacao' and c.tipo='cliente'"));
  const aguardando = Number(sql("select count(*) from clientes c where ultima_compra_em < curdate() - interval 90 day and funcao_id <> (select id from funcoes where slug='prospeccao_reativacao')"));
  await page.goto(BASE + '/reativacao');
  await Promise.all([page.waitForNavigation(), page.click('form[action$="/reativacao/processar"] button')]);
  const depois = Number(sql("select count(*) from clientes c join funcoes f on f.id=c.funcao_id where f.slug='prospeccao_reativacao' and c.tipo='cliente'"));
  ok(aguardando > 0 && depois === antes + aguardando, `processar agora: ${aguardando} clientes foram para a fila`);
  await page.screenshot({ path: SHOTS + '/reativacao.png' });
  const primeiro = page.locator('form.devolver').first();
  const idDevolver = (await primeiro.getAttribute('action')).match(/reativacao\/(\d+)/)[1];
  page.once('dialog', d => d.accept());
  await primeiro.locator('select').selectOption({ label: 'Televendas 1 (demo)' });
  await primeiro.locator('button').click();
  await page.waitForLoadState();
  ok(sql(`select u.email from clientes c join usuarios u on u.id=c.responsavel_id where c.id=${idDevolver}`) === 'televendas1@demo.local', 'devolver para vendedor');
  ok(sql(`select f.slug from clientes c join funcoes f on f.id=c.funcao_id where c.id=${idDevolver}`) === 'televendas', 'cliente devolvido sai da fila (função Televendas)');

  // Agenda
  await page.goto(BASE + '/agenda');
  await page.screenshot({ path: SHOTS + '/agenda.png' });
  ok(await page.locator('.agenda-resumo .card').count() === 3, 'agenda com atrasados / hoje / próximos');

  // Novo cadastro com CNPJ inválido e depois válido
  await page.goto(BASE + '/carteira/clientes/novo');
  await page.fill('#razao_social', 'Loja Teste Automático');
  await page.fill('#cnpj', '11.111.111/1111-11');
  await page.fill('#cidade', 'Canoas'); await page.selectOption('#uf', 'RS');
  await page.click('form.form-cliente button[type=submit]');
  ok(await page.locator('.aviso.erro', { hasText: 'CNPJ inválido' }).count() === 1, 'CNPJ inválido recusado');
  await page.fill('#cnpj', '11.222.333/0001-81');
  await page.click('form.form-cliente button[type=submit]');
  await page.waitForSelector('.ficha-topo h2');
  const novo = sql("select concat(u.email,'|',f.slug) from clientes c join usuarios u on u.id=c.responsavel_id join funcoes f on f.id=c.funcao_id where c.cnpj='11222333000181'");
  ok(novo === 'reativacao@demo.local|prospeccao_reativacao', 'cadastro pelo gestor vai para a função de novos leads: ' + novo);
  await page.keyboard.press('Escape');
  await sair(page);

  // ---------- Vendedor: só a própria carteira ----------
  await entrar(page, 'televendas2@demo.local');
  await page.goto(BASE + '/carteira/lista');
  const meus = sql("select count(*) from clientes c join usuarios u on u.id=c.responsavel_id where u.email='televendas2@demo.local' and c.excluido_em is null");
  ok((await page.locator('.paginacao-barra > span').innerText()).includes(' de ' + meus), `vendedor vê só os próprios ${meus} clientes`);
  ok(await page.locator('select[name=responsavel]').count() === 0, 'vendedor não tem filtro de responsável');
  ok(await page.locator('a', { hasText: 'Exportar CSV' }).count() === 0, 'vendedor não exporta CSV');
  const deOutro = sql("select c.id from clientes c join usuarios u on u.id=c.responsavel_id where u.email='televendas1@demo.local' limit 1");
  const r = await page.request.get(BASE + `/carteira/clientes/${deOutro}/ficha`);
  ok(r.status() === 403, 'vendedor recebe 403 ao abrir ficha de outro vendedor');
  const r2 = await page.request.get(BASE + '/carteira/exportar.csv');
  ok(r2.status() === 403, 'vendedor recebe 403 na exportação');
  const r3 = await page.request.post(BASE + `/carteira/clientes/${deOutro}/etapa`, { form: { etapa_id: '1' } });
  ok(r3.status() === 419, 'POST sem CSRF é recusado');
  const mapaJson = await (await page.request.get(BASE + '/carteira/mapa.json?nivel=uf')).json();
  ok(mapaJson.itens.reduce((s, i) => s + Number(i.total), 0) === Number(meus), 'mapa do vendedor só conta os próprios clientes');

  // Celular
  const ctxCel = await browser.newContext({ viewport: { width: 390, height: 844 } });
  await rotas(ctxCel);
  const cel = await ctxCel.newPage();
  await cel.goto(BASE + '/entrar');
  await cel.fill('#email', 'televendas2@demo.local'); await cel.fill('#senha', 'Demo@2026'); await cel.click('button[type=submit]');
  await cel.goto(BASE + '/carteira');
  await cel.waitForTimeout(2000);
  await cel.screenshot({ path: SHOTS + '/carteira-celular.png', fullPage: true });
  await cel.locator('.v4-metrica.destaque').click();
  await cel.locator('.nome-cliente').first().click();
  await cel.waitForSelector('.ficha-topo h2');
  await cel.screenshot({ path: SHOTS + '/ficha-celular.png' });

  ok(erros.length === 0, 'sem erros de JavaScript ' + erros.join(' | '));
  await browser.close();
  console.log(falhas ? `\n${falhas} FALHA(S)` : '\nTudo certo');
  process.exit(falhas ? 1 : 0);
})();
