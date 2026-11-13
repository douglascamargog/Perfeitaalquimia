// Teste automático (Playwright). Rodar todos: bash tests/rodar.sh  ·  só este: node tests/teste_marketing.js
const path = require('path');
const RECURSOS = path.join(__dirname, 'recursos');
const { chromium } = require('playwright');
const { execSync } = require('child_process');
const BASE = 'http://127.0.0.1:8000', DIR = process.argv[2] || path.join(__dirname, 'saida');
let falhas = 0; const ok = (c, m) => { console.log((c ? 'OK   ' : 'FALHA') + ' ' + m); if (!c) falhas++; };
const sql = (q) => execSync(`mysql perfeita --default-character-set=utf8mb4 -N -e "${q}"`).toString().trim();
async function entrar(p, email) { await p.goto(BASE + '/entrar'); await p.fill('#email', email); await p.fill('#senha', 'Demo@2026'); await p.click('button[type=submit]'); await p.waitForLoadState(); }
async function sair(p) { await p.goto(BASE + '/'); await p.click('form[action$="/sair"] button'); await p.waitForLoadState(); }
async function limpar(p) { await p.evaluate(() => { const a = document.querySelector('.aviso-flutuante'); a.classList.remove('visivel'); a.textContent = ''; }); }
const aviso = async (p) => { await p.waitForSelector('.aviso-flutuante.visivel'); const t = await p.locator('.aviso-flutuante').innerText(); await p.waitForTimeout(400); return t; };
const etapaDo = (id) => sql(`select e.tipo from mkt_posts p join mkt_post_etapas e on e.id=p.etapa_id where p.id=${id}`);

(async () => {
  const b = await chromium.launch(); const p = await (await b.newContext({ viewport: { width: 1440, height: 1700 } })).newPage();
  const erros = []; p.on('pageerror', e => erros.push(e.message)); p.on('dialog', d => d.accept());
  await p.route('https://cdn.jsdelivr.net/npm/chart.js@4.4.1/**', r => r.fulfill({ path: RECURSOS + '/chart.umd.js', contentType: 'text/javascript' }));
  await p.route('https://api.open-meteo.com/**', r => r.fulfill({ json: {} }));

  // ---------- Marketing cria post ----------
  await entrar(p, 'marketing@demo.local');
  ok(await p.locator('nav a', { hasText: 'Mural de posts' }).count() === 1, 'Marketing vê "Mural de posts" no menu');
  await p.goto(BASE + '/marketing');
  ok(await p.locator('.mk-post').count() >= 20, 'mural mostra os posts do mês (' + await p.locator('.mk-post').count() + ')');
  const futuro = new Date(Date.now() + 3 * 864e5).toISOString().slice(0, 10);
  await p.click('.cabecalho-secao [data-novo-post]');
  await p.waitForSelector('#pt-titulo');
  await p.fill('#pt-titulo', 'Teste automático — Reels Magnésio');
  await p.selectOption('#pt-formato', 'reels');
  await p.fill('#pt-data', futuro);
  await p.setInputFiles('#pt-imagem', RECURSOS + '/falsa.png');
  await limpar(p); await p.click('.ficha-secao button[type=submit]');
  ok((await aviso(p)).includes('JPG, PNG'), 'arquivo que não é imagem é recusado');
  await p.setInputFiles('#pt-imagem', RECURSOS + '/post_teste.png');
  await p.fill('#pt-legenda', 'Legenda de teste #magnesio');
  ok((await p.locator('[data-contador]').innerText()).includes('/ 2.200'), 'contador de caracteres da legenda');
  await limpar(p); await p.click('.ficha-secao button[type=submit]');
  ok((await aviso(p)).includes('Post criado'), 'post criado com imagem');
  const id = sql("select id from mkt_posts where titulo like 'Teste autom%'");
  ok(sql(`select m.arquivo from mkt_posts p join midias m on m.id=p.midia_id where p.id=${id}`).startsWith('media/marketing/'), 'imagem guardada em public/media/marketing');
  await p.waitForSelector('.mk-fluxo');

  // pular para Publicado sem aprovação = bloqueado
  await limpar(p); await p.locator('.mk-publicar summary').click(); await p.fill('.mk-publicar input[name=link]', 'https://www.instagram.com/p/teste/');
  await p.click('.mk-publicar button[type=submit]');
  ok((await aviso(p)).includes('não foi aprovado'), 'sem aprovação não dá para marcar como publicado');
  ok(etapaDo(id) === 'rascunho', 'post continua em Ideia');

  // arrastar para outro dia (fecha a gaveta e recarrega o mural)
  await p.keyboard.press('Escape'); await p.waitForLoadState('networkidle'); await p.goto(BASE + '/marketing');
  const outroDia = new Date(Date.now() + 4 * 864e5).toISOString().slice(0, 10);
  if (outroDia.slice(0, 7) === futuro.slice(0, 7)) {
    const cartao = p.locator(`.mk-post[data-post="${id}"]`), alvo = p.locator(`.mk-dia[data-dia="${outroDia}"]`);
    const a = await cartao.boundingBox(), d = await alvo.boundingBox();
    await p.mouse.move(a.x + 20, a.y + 10); await p.mouse.down();
    for (let k = 1; k <= 12; k++) await p.mouse.move(a.x + 20 + (d.x + 40 - a.x - 20) * k / 12, a.y + 10 + (d.y + 30 - a.y - 10) * k / 12);
    await p.mouse.up(); await p.waitForTimeout(800);
    ok(sql(`select data_publicacao from mkt_posts where id=${id}`) === outroDia, 'arrastar no calendário muda a data');
  } else ok(true, '(arrastar pulado: virada de mês)');

  // enviar para aprovação
  await p.evaluate((i) => window.Carteira.abrirCliente(i), id); await p.waitForSelector('.mk-fluxo');
  await limpar(p); await p.click('form[action$="/enviar"] button'); await aviso(p);
  ok(etapaDo(id) === 'aprovacao', 'enviado para aprovação');
  ok(await p.locator('form[action$="/aprovar"]').count() === 0, 'Marketing não vê o botão Aprovar');
  const tokenMk = await p.locator('meta[name=csrf]').getAttribute('content');
  ok((await p.request.post(BASE + `/marketing/posts/${id}/aprovar`, { headers: { 'X-CSRF-Token': tokenMk } })).status() === 403, 'Marketing não consegue aprovar pela URL (403)');
  await p.screenshot({ path: DIR + '/mk-post-aprovacao.png' });
  await sair(p);

  // ---------- Admin pede ajuste, depois aprova ----------
  await entrar(p, 'admin@demo.local');
  await p.goto(BASE + '/marketing');
  await p.evaluate((i) => window.Carteira.abrirCliente(i), id); await p.waitForSelector('.mk-fluxo');
  await p.locator('.mk-pedir-ajuste summary').click();
  await p.fill('.mk-pedir-ajuste textarea', 'Usar foto com fundo claro');
  await limpar(p); await p.click('.mk-pedir-ajuste button[type=submit]'); await aviso(p);
  ok(etapaDo(id) === 'ajuste', 'admin pede ajuste');
  await p.waitForSelector('.mk-ajuste');
  ok((await p.locator('.mk-ajuste').innerText()).includes('fundo claro'), 'motivo do ajuste aparece no post');
  await limpar(p); await p.click('form[action$="/aprovar"] button'); await aviso(p);
  ok(etapaDo(id) === 'aprovado' && sql(`select aprovado_por is not null from mkt_posts where id=${id}`) === '1', 'admin aprova (fica registrado quem aprovou)');
  await sair(p);

  // ---------- Marketing: editar legenda tira a aprovação; publicar ----------
  await entrar(p, 'marketing@demo.local');
  await p.goto(BASE + '/marketing');
  await p.evaluate((i) => window.Carteira.abrirCliente(i), id); await p.waitForSelector('#pt-legenda');
  await p.fill('#pt-legenda', 'Legenda mudada depois da aprovação');
  await limpar(p); await p.click('.ficha-secao button[type=submit]');
  ok((await aviso(p)).includes('voltou para aprovação'), 'mudar a legenda depois de aprovado devolve para aprovação');
  ok(etapaDo(id) === 'aprovacao', 'post voltou para "Aguardando aprovação"');
  sql(`update mkt_posts set etapa_id=(select id from mkt_post_etapas where tipo='aprovado'), aprovado_em=now(), aprovado_por=1 where id=${id}`); // aprovado de novo (atalho do teste)
  await p.evaluate((i) => window.Carteira.abrirCliente(i), id); await p.waitForSelector('.mk-publicar');
  await p.locator('.mk-publicar summary').click(); await p.fill('.mk-publicar input[name=link]', 'https://www.instagram.com/p/teste123/');
  await limpar(p); await p.click('.mk-publicar button[type=submit]'); await aviso(p);
  ok(etapaDo(id) === 'publicado' && sql(`select link_publicado from mkt_posts where id=${id}`).includes('teste123'), 'marcado como publicado com o link');
  ok(Number(sql(`select count(*) from auditoria where entidade='post' and entidade_id=${id}`)) >= 5, 'auditoria registra criação, data, etapas, ajuste, aprovação e publicação');
  ok(Number(sql(`select count(*) from mkt_post_comentarios where post_id=${id}`)) >= 5, 'histórico do post na gaveta');

  // feed, planejamento, campanha
  await p.goto(BASE + '/marketing/feed');
  ok(await p.locator('.mk-feed-item').count() >= 10, 'prévia do feed em grade');
  await p.goto(BASE + '/marketing/planejamento');
  ok(await p.locator('.mk-gantt-barra').count() >= 2, 'planejamento mostra as campanhas na linha do tempo');
  ok(await p.locator('.mk-gantt-data').count() >= 3, 'datas comemorativas no planejamento');
  await p.fill('#cp-nome', 'Campanha de teste'); await p.fill('#cp-ini', new Date().toISOString().slice(0, 10)); await p.fill('#cp-fim', futuro);
  await Promise.all([p.waitForNavigation(), p.click('#form-campanha button[type=submit]')]);
  ok(sql("select count(*) from mkt_campanhas where nome='Campanha de teste'") === '1', 'campanha criada');

  // e-commerce
  await p.goto(BASE + '/marketing/ecommerce'); await p.waitForTimeout(500);
  const fat = sql("select sum(faturamento) from ecommerce_resumo_diario where data between date_format(curdate(),'%Y-%m-01') and curdate()");
  const brl = 'R$ ' + Number(fat).toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
  ok((await p.locator('.b-escuro .b-numero-grande').innerText()) === brl, `faturamento do mês bate com o banco (${brl})`);
  ok(await p.evaluate(() => !!Chart.getChart('grafico-diario') && !!Chart.getChart('grafico-meses')), 'gráficos desenhados');
  ok(await p.locator('.mk-ranking li').count() === 10, 'top 10 produtos');
  const dias = sql('select count(*) from ecommerce_resumo_diario');
  await Promise.all([p.waitForNavigation(), p.click('form[action$="/ecommerce/atualizar"] button')]);
  ok(sql('select count(*) from ecommerce_resumo_diario') === dias, '"Atualizar agora" não duplica dias');
  await p.goto(BASE + '/');
  ok((await p.locator('.bento').innerText()).includes('Posts desta semana'), 'Início do Marketing mostra posts da semana');
  ok((await p.locator('.bento').innerText()).includes('E-commerce no mês'), 'Início do Marketing mostra o e-commerce');
  await p.screenshot({ path: DIR + '/inicio-marketing.png', fullPage: true });
  await sair(p);

  // ---------- Permissões ----------
  await entrar(p, 'televendas1@demo.local');
  ok((await p.goto(BASE + '/marketing')).status() === 403, 'vendedor recebe 403 no Marketing');
  ok((await p.goto(BASE + '/marketing/ecommerce')).status() === 403, 'vendedor recebe 403 no E-commerce');
  await sair(p);
  await entrar(p, 'financeiro@demo.local');
  ok((await p.goto(BASE + '/marketing/ecommerce')).status() === 200, 'Financeiro vê o E-commerce');
  ok((await p.goto(BASE + '/marketing')).status() === 403, 'Financeiro não vê o mural (403)');
  await sair(p);

  // celular
  const cel = await b.newContext({ viewport: { width: 390, height: 844 } }); const pc = await cel.newPage();
  await pc.route('https://cdn.jsdelivr.net/npm/chart.js@4.4.1/**', r => r.fulfill({ path: RECURSOS + '/chart.umd.js', contentType: 'text/javascript' }));
  await entrar(pc, 'marketing@demo.local');
  for (const [u, n] of [['/marketing', 'mk-celular-mural'], ['/marketing/ecommerce', 'mk-celular-ecommerce']]) {
    await pc.goto(BASE + u); await pc.waitForTimeout(300);
    ok(await pc.evaluate(() => document.documentElement.scrollWidth) <= 392, `${u} sem rolagem horizontal no celular`);
    await pc.screenshot({ path: `${DIR}/${n}.png`, fullPage: true });
  }
  ok(erros.length === 0, 'sem erros de JavaScript ' + erros.join(' | '));
  console.log(falhas ? `\n${falhas} FALHA(S)` : '\nTUDO OK'); await b.close();
})();
