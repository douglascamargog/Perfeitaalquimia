// Teste automático (Playwright). Rodar todos: bash tests/rodar.sh  ·  só este: node tests/teste_desenvolvimento.js
// Desenvolvimento de Produto: cada etapa pela sua função, checagens, aprovação da prova (Marketing + Qualidade),
// entrada dos rótulos no estoque, lote piloto, lançamento no catálogo e projeto de Terceirização.
// V8.1: o "jogo" — tipo de embalagem, palco 3D, balões por etapa, gaveta da etapa e reserva sem 3D.
const { chromium } = require('playwright');
const { execSync } = require('child_process');
const BASE = 'http://127.0.0.1:8000';
let falhas = 0; const ok = (c, m) => { console.log((c ? 'OK   ' : 'FALHA') + ' ' + m); if (!c) falhas++; };
const sql = (q) => execSync(`mysql perfeita --default-character-set=utf8mb4 -N -e "${q.replace(/"/g, '\\"')}"`).toString().trim();
const PNG = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==', 'base64');
const PDF = Buffer.from('%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n');

(async () => {
  const b = await chromium.launch();
  const erros = [];
  const sessoes = {};
  const como = async (email) => {
    if (sessoes[email]) return sessoes[email];
    const p = await (await b.newContext({ viewport: { width: 1300, height: 900 } })).newPage();
    p.on('pageerror', e => erros.push(e.message)); await p.route(/open-meteo|jsdelivr/, r => r.fulfill({ body: '{}' }));
    await p.goto(BASE + '/entrar'); await p.fill('#email', email); await p.fill('#senha', 'Demo@2026'); await p.click('button[type=submit]'); await p.waitForLoadState();
    if (await p.locator('[data-novidades-ok]').count()) { await p.click('[data-novidades-ok]'); await p.waitForLoadState(); }
    const csrf = await p.evaluate(() => document.querySelector('meta[name=csrf]').content);
    return (sessoes[email] = { p, csrf });
  };
  const post = async (email, caminho, form = {}, multipart = null) => {
    const s = await como(email);
    const r = multipart ? await s.p.request.post(BASE + caminho, { multipart: { _csrf: s.csrf, ...multipart } })
      : await s.p.request.post(BASE + caminho, { form: { _csrf: s.csrf, ...form } });
    // O aviso aparece na página para onde o POST redireciona (e só uma vez)
    const html = await r.text();
    return [...html.matchAll(/class="aviso [a-z]+"[^>]*>([^<]*)</g)].map((m) => m[1]).join(' | ');
  };
  const etapa = (id) => sql(`select e.checagem from dp_projetos p join dp_etapas e on e.id=p.etapa_id where p.id=${id}`);

  // ---------- Criar ----------
  const g = await como('gestor@demo.local');
  await g.p.goto(BASE + '/desenvolvimento');
  ok(await g.p.locator('.dp-coluna').count() === 11, 'quadro com as 11 etapas');
  await g.p.click('[data-abrir="#novo-projeto"]');
  ok(await g.p.locator('#novo-projeto .dp-tipo').count() === 3, 'novo projeto: escolher entre pote, frasco e caixinha');
  await g.p.fill('#novo-projeto input[name=nome]', 'Produto Teste Alfa');
  await g.p.click('#novo-projeto .dp-tipo:has(input[value=frasco])');
  await Promise.all([g.p.waitForNavigation(), g.p.click('#novo-projeto button[type=submit]')]);
  const id = sql("select id from dp_projetos where nome='Produto Teste Alfa'");
  ok(sql(`select tipo from dp_projetos where id=${id}`) === 'frasco', 'o tipo escolhido (frasco) fica gravado');

  // ---------- O palco (jogo) ----------
  const dadosJogo = async (pg) => pg.evaluate(() => JSON.parse(document.getElementById('dp-dados').textContent));
  ok(await g.p.locator('.dp-balao').count() === 11, 'um balão por etapa em volta da embalagem');
  ok(await g.p.locator('.dp-balao.andamento [data-abrir-etapa]').count() === 1, 'o balão da etapa atual é clicável');
  ok(await g.p.locator('#dp-etapa').isHidden(), 'o cartão da etapa fica guardado na gaveta');
  await g.p.click('[data-abrir-etapa]');
  ok(await g.p.locator('dialog.dp-gaveta[open] #dp-etapa .dp-concluir button').isVisible(), 'clicar no balão abre a gaveta com o botão Concluir');
  await g.p.click('.dp-gaveta-fechar');
  let jogo = await dadosJogo(g.p);
  ok(jogo.tipo === 'frasco' && jogo.progresso === 0 && jogo.etapas.length === 11, 'dados do jogo: tipo, progresso 0% e as etapas');
  const prod = sql(`select produto_id from dp_projetos where id=${id}`);
  const slug = sql(`select slug from produtos where id=${prod}`);
  ok(sql(`select concat(ativo,visivel_catalogo) from produtos where id=${prod}`) === '00', 'o projeto cria o produto oculto (fora do catálogo)');
  ok(Number(sql(`select count(*) from produtos where ativo=1 and id=${prod}`)) === 0, 'produto em desenvolvimento não aparece em Produtos');
  await post('gestor@demo.local', `/desenvolvimento/${id}/concluir`);
  ok(etapa(id) === 'ficha', 'briefing concluído pela gestão → Formulação');

  // ---------- Formulação (Técnica) ----------
  let msg = await post('tecnica@demo.local', `/desenvolvimento/${id}/concluir`);
  ok(msg.includes('Ainda falta') && etapa(id) === 'ficha', 'sem ficha técnica a etapa não conclui');
  msg = await post('qualidade@demo.local', `/desenvolvimento/${id}/concluir`);
  ok(msg.includes('feita por') && etapa(id) === 'ficha', 'quem não é da Técnica não conclui a Formulação');
  const mp = sql("select id from materias_primas where tipo='materia_prima' and estoque > 50 order by estoque desc limit 1");
  const pote = sql("select id from materias_primas where tipo='embalagem' and estoque > 50 order by estoque desc limit 1");
  const t = await como('tecnica@demo.local');
  await t.p.request.post(BASE + `/produtos/${slug}/ficha`, { form: { _csrf: t.csrf, voltar: 'ficha', 'mp[]': mp, 'qtd[]': '0,01' } });
  await t.p.request.post(BASE + `/produtos/${slug}/ficha`, { form: { _csrf: t.csrf, voltar: 'embalagens', 'mp[]': pote, 'qtd[]': '1' } });
  await post('tecnica@demo.local', `/desenvolvimento/${id}/concluir`);
  ok(etapa(id) === 'composicao', 'Técnica conclui a Formulação');
  await g.p.goto(BASE + `/desenvolvimento/${id}`);
  jogo = await dadosJogo(g.p);
  ok(jogo.etapas.find((e) => e.checagem === 'ficha').situacao === 'concluida' && jogo.progresso === 18, 'Formulação concluída: a peça "conteúdo" entra no jogo (18% formado)');
  ok(await g.p.locator('.dp-cena.tem-ficha').count() === 1 && await g.p.locator('.dp-balao.concluida').count() === 2, 'palco marca a peça e o balão fica concluído');
  ok(sql(`select count(*) from notificacoes where tipo='desenvolvimento' and usuario_id=(select id from usuarios where email='tecnica@demo.local')`) !== '0', 'aviso no sino para a função da etapa');
  // Rotulagem
  await t.p.request.post(BASE + `/produtos/${slug}/composicao`, { form: { _csrf: t.csrf, ingredientes: 'Ingrediente teste', porcao: '10 g', 'nutriente[]': 'Proteína', 'quantidade[]': '5 g', 'vd[]': '10%' } });
  await post('tecnica@demo.local', `/desenvolvimento/${id}/concluir`);
  ok(etapa(id) === 'codigo', 'Técnica conclui a Rotulagem → Código de barras');

  // ---------- Qualidade: código ----------
  msg = await post('qualidade@demo.local', `/desenvolvimento/${id}/codigo`, { ean: '123', ncm: '21069030' });
  ok(msg.includes('EAN'), 'EAN inválido é recusado');
  await post('qualidade@demo.local', `/desenvolvimento/${id}/codigo`, { ean: '7891234567895', ncm: '21069030' });
  await post('qualidade@demo.local', `/desenvolvimento/${id}/concluir`);
  ok(etapa(id) === 'arte' && sql(`select ean from produto_tecnico where produto_id=${prod}`) === '7891234567895', 'Qualidade cadastra EAN/NCM e conclui');

  // ---------- Marketing: arte ----------
  await post('marketing@demo.local', `/desenvolvimento/${id}/arte`, {}, { arquivo: { name: 'arte.pdf', mimeType: 'application/pdf', buffer: PDF } });
  await post('marketing@demo.local', `/desenvolvimento/${id}/arte`, {}, { arquivo: { name: 'arte.png', mimeType: 'image/png', buffer: PNG } });
  await post('marketing@demo.local', `/desenvolvimento/${id}/concluir`);
  ok(etapa(id) === 'compras' && sql(`select count(*) from produto_documentos where produto_id=${prod} and tipo='rotulo'`) === '2', 'Marketing envia a arte e conclui');
  // A arte em imagem vira a textura do rótulo no 3D (servida só pelo sistema, para quem acompanha)
  await g.p.goto(BASE + `/desenvolvimento/${id}`);
  jogo = await dadosJogo(g.p);
  ok(/\/produtos\/documentos\/\d+$/.test(jogo.arte || '') && jogo.ean === '7891234567895', 'dados do jogo: arte (imagem) e EAN para o rótulo 3D');
  const fin = sql("select id from usuarios where email='financeiro@demo.local'");
  sql(`insert into usuario_permissoes (usuario_id, permissao, concedida) values (${fin}, 'produtos.ver', 0)`);
  const f = await como('financeiro@demo.local');
  const rArte = await f.p.request.get(BASE + jogo.arte, { maxRedirects: 0 });
  ok(rArte.status() === 200 && rArte.headers()['content-type'] === 'image/png', 'quem só acompanha o desenvolvimento vê a arte do projeto');
  const outro = sql("select id from produto_documentos where produto_id not in (select produto_id from dp_projetos) and interno=0 and arquivado_em is null limit 1");
  ok((await f.p.request.get(BASE + '/produtos/documentos/' + outro, { maxRedirects: 0 })).status() !== 200, '...mas não os documentos de outros produtos');
  sql(`delete from usuario_permissoes where usuario_id=${fin} and permissao='produtos.ver'`);

  // ---------- Compras e gráfica ----------
  msg = await post('qualidade@demo.local', `/desenvolvimento/${id}/concluir`);
  ok(msg.includes('sem compra'), 'compras: precisa marcar os itens');
  for (const x of [mp, pote]) await post('qualidade@demo.local', `/desenvolvimento/${id}/compra`, { mp: x, comprado: '1' });
  await post('qualidade@demo.local', `/desenvolvimento/${id}/concluir`);
  await post('qualidade@demo.local', `/desenvolvimento/${id}/grafica`, { grafica: 'Gráfica Teste', data: new Date().toISOString().slice(0, 10) });
  await post('qualidade@demo.local', `/desenvolvimento/${id}/concluir`);
  ok(etapa(id) === 'aprovacao', 'compras e envio à gráfica concluídos');

  // ---------- Aprovação da prova ----------
  await post('qualidade@demo.local', `/desenvolvimento/${id}/prova`, {}, { arquivo: { name: 'prova.pdf', mimeType: 'application/pdf', buffer: PDF } });
  msg = await post('marketing@demo.local', `/desenvolvimento/${id}/aprovar`, { decisao: 'aprovado' });
  ok(etapa(id) === 'aprovacao' && msg.includes('Falta a outra'), 'só o Marketing aprovando não avança');
  msg = await post('marketing@demo.local', `/desenvolvimento/${id}/aprovar`, { decisao: 'aprovado' });
  ok(msg.includes('já respondeu'), 'a mesma pessoa não aprova duas vezes');
  msg = await post('qualidade@demo.local', `/desenvolvimento/${id}/aprovar`, { decisao: 'ajustar', comentario: 'Cor do logo errada' });
  ok(etapa(id) === 'arte' && msg.includes('voltou'), '"Pedir ajuste" volta para a Criação do rótulo');
  await post('marketing@demo.local', `/desenvolvimento/${id}/concluir`);
  ok(etapa(id) === 'compras' || etapa(id) === 'grafica' || etapa(id) === 'aprovacao', 'Marketing reenvia e o fluxo segue');
  while (etapa(id) !== 'aprovacao') { await post('qualidade@demo.local', `/desenvolvimento/${id}/concluir`); }
  await post('marketing@demo.local', `/desenvolvimento/${id}/aprovar`, { decisao: 'aprovado' });
  ok(etapa(id) === 'aprovacao', 'nova rodada: aprovação anterior não vale');
  await post('qualidade@demo.local', `/desenvolvimento/${id}/aprovar`, { decisao: 'aprovado' });
  ok(etapa(id) === 'recebimento', 'Marketing + Qualidade aprovam → Recebimento');

  // ---------- Recebimento ----------
  await post('qualidade@demo.local', `/desenvolvimento/${id}/rotulos`, { quantidade: '500' });
  const rot = sql("select concat(id,'|',estoque,'|',tipo) from materias_primas where nome='Rótulo Produto Teste Alfa'").split('|');
  ok(rot[1] === '500.000' && rot[2] === 'embalagem' && sql(`select count(*) from ficha_tecnica where produto_id=${prod} and materia_prima_id=${rot[0]}`) === '1', 'rótulos entram no estoque e na ficha técnica');
  await post('qualidade@demo.local', `/desenvolvimento/${id}/concluir`);

  // ---------- Lote piloto ----------
  await post('producao@demo.local', `/desenvolvimento/${id}/piloto`, { quantidade: '5' });
  const op = sql(`select ordem_id from dp_projetos where id=${id}`);
  ok(op && sql(`select situacao from ordens_producao where id=${op}`) === 'planejada', 'Produção cria a ordem do lote piloto');
  msg = await post('producao@demo.local', `/desenvolvimento/${id}/concluir`);
  ok(msg.includes('Concluir a ordem'), 'piloto: precisa concluir a ordem (ou explicar)');
  await post('producao@demo.local', `/desenvolvimento/${id}/concluir`, { observacao: 'Piloto feito e aprovado na bancada', manual: '1' });
  ok(etapa(id) === 'lancamento', 'Produção conclui o lote piloto');

  // ---------- Trocar o tipo e ver o 3D (navegador com WebGL) ----------
  msg = await post('televendas1@demo.local', `/desenvolvimento/${id}/tipo`, { tipo: 'caixinha' });
  ok(msg.includes('Só quem gerencia') && sql(`select tipo from dp_projetos where id=${id}`) === 'frasco', 'vendedora não troca o tipo');
  await post('gestor@demo.local', `/desenvolvimento/${id}/tipo`, { tipo: 'pote' });
  ok(sql(`select tipo from dp_projetos where id=${id}`) === 'pote', 'quem gerencia troca o tipo de embalagem');
  const b3 = await chromium.launch({ args: ['--use-gl=angle', '--use-angle=swiftshader', '--enable-unsafe-swiftshader'] });
  const c3 = await b3.newContext({ viewport: { width: 1300, height: 900 } });
  await c3.addCookies((await g.p.context().cookies()));
  const p3d = await c3.newPage();
  const erros3d = [];
  p3d.on('pageerror', (e) => erros3d.push(e.message));
  await p3d.goto(BASE + `/desenvolvimento/${id}`);
  await p3d.waitForSelector('.dp-cena.com-3d canvas', { timeout: 8000 }).catch(() => null);
  ok(await p3d.locator('.dp-cena.com-3d canvas').count() === 1 && await p3d.locator('.dp-palco.circulo').count() === 1, '3D: a embalagem aparece no centro com os balões em círculo');
  ok(erros3d.length === 0, '3D sem erros de JavaScript' + (erros3d.length ? ': ' + erros3d.join(' / ') : ''));
  // Sem o 3D (biblioteca não carrega): fica a ilustração e a gaveta continua funcionando
  const pSem = await c3.newPage();
  await pSem.route(/vendor\/three/, (r) => r.abort());
  await pSem.goto(BASE + `/desenvolvimento/${id}`);
  await pSem.waitForTimeout(800);
  ok(await pSem.locator('.dp-cena .dp-ilustra.reserva').isVisible() && await pSem.locator('.dp-cena.com-3d').count() === 0, 'sem 3D: aparece a ilustração da embalagem');
  await pSem.click('[data-abrir-etapa]');
  ok(await pSem.locator('dialog.dp-gaveta[open] #dp-etapa').isVisible(), 'sem 3D: a gaveta da etapa funciona');
  // Celular: balões em faixa embaixo da embalagem
  const cel = await b3.newContext({ viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true });
  await cel.addCookies((await g.p.context().cookies()));
  const pc = await cel.newPage();
  await pc.goto(BASE + `/desenvolvimento/${id}`);
  ok(await pc.locator('.dp-palco.circulo').count() === 0 && await pc.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), 'celular: balões em faixa, sem rolagem lateral da página');
  await b3.close();

  // ---------- Lançamento ----------
  await post('gestor@demo.local', `/desenvolvimento/${id}/concluir`);
  ok(sql(`select situacao from dp_projetos where id=${id}`) === 'lancado' && sql(`select concat(ativo,visivel_catalogo) from produtos where id=${prod}`) === '11', 'lançado: produto ativo e no catálogo');
  ok((await (await b.newContext()).request.get(BASE + `/catalogo/produto/${slug}`)).status() === 200, 'aparece no catálogo público');

  // ---------- Terceirização ----------
  const p3 = sql("select id from dp_projetos where cliente_id is not null limit 1");
  const cli = sql(`select cliente_id from dp_projetos where id=${p3}`);
  const prod3 = sql(`select produto_id from dp_projetos where id=${p3}`);
  sql(`update dp_projetos set rotulos_recebidos=300 where id=${p3}`);
  sql(`update dp_projetos set etapa_id=(select id from dp_etapas where checagem='lancamento') where id=${p3}`);
  await post('gestor@demo.local', `/desenvolvimento/${p3}/concluir`);
  ok(sql(`select concat(ativo,visivel_catalogo,'|',cliente_exclusivo_id) from produtos where id=${prod3}`) === `10|${cli}`, 'Terceirização: produto exclusivo do cliente, fora do catálogo');
  ok(sql(`select rotulos_estoque from terceirizacao_produtos where produto_id=${prod3} and cliente_id=${cli}`) === '300', 'rótulos recebidos vão para os rótulos do cliente');

  // ---------- Minhas tarefas e acessos ----------
  const q = await como('qualidade@demo.local');
  await q.p.goto(BASE + '/desenvolvimento/minhas');
  ok(await q.p.locator('.dp-cartao').count() >= 1, 'Minhas tarefas da Qualidade (prova esperando aprovação)');
  await g.p.goto(BASE + '/desenvolvimento?situacao=lancado');
  ok(await g.p.locator(`.dp-cartao[data-projeto="${id}"] .dp-barra i[style*="100%"]`).count() === 1, 'quadro: projeto lançado aparece 100% formado');
  const v = await como('televendas1@demo.local');
  ok((await v.p.goto(BASE + '/desenvolvimento')).status() === 200 && await v.p.locator('[data-abrir="#novo-projeto"]').count() === 0, 'equipe vê os projetos, sem criar');
  msg = await post('televendas1@demo.local', `/desenvolvimento/${p3}/situacao`, { situacao: 'pausado' });
  ok(msg.includes('Só quem gerencia'), 'vendedora não pausa projeto');

  ok(erros.length === 0, 'nenhum erro de JavaScript' + (erros.length ? ': ' + erros.join(' / ') : ''));
  await b.close();
  console.log(falhas ? `\n${falhas} FALHA(S)` : '\nTudo certo.');
  process.exit(falhas ? 1 : 0);
})();
