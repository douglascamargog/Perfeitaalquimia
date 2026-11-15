// Teste automático (Playwright). Rodar todos: bash tests/rodar.sh  ·  só este: node tests/teste_marketing_tarefas.js
// Marketing por funções: "Pedir ao Marketing" (qualquer setor) cai na função certa pelo tipo, avisos, começar → aprovação →
// aprovar/ajuste → pronto (quem pediu é avisado), posts e rótulos na lista, anexo protegido, menu e imagem sem corte.
const { chromium } = require('playwright');
const { execSync } = require('child_process');
const BASE = 'http://127.0.0.1:8000';
let falhas = 0; const ok = (c, m) => { console.log((c ? 'OK   ' : 'FALHA') + ' ' + m); if (!c) falhas++; };
const sql = (q) => execSync(`mysql perfeita --default-character-set=utf8mb4 -N -e "${q.replace(/"/g, '\\"')}"`).toString().trim();
const PNG = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==', 'base64');

(async () => {
  const b = await chromium.launch();
  const erros = [];
  const como = async (email) => {
    const p = await (await b.newContext({ viewport: { width: 1440, height: 900 } })).newPage();
    p.on('pageerror', (e) => erros.push(e.message + ' @ ' + p.url())); await p.route(/open-meteo|jsdelivr/, (r) => r.fulfill({ body: '{}' }));
    await p.goto(BASE + '/entrar'); await p.fill('#email', email); await p.fill('#senha', 'Demo@2026'); await p.click('button[type=submit]'); await p.waitForLoadState();
    if (await p.locator('[data-novidades-ok]').count()) { await p.click('[data-novidades-ok]'); await p.waitForLoadState(); }
    return { p, csrf: await p.evaluate(() => document.querySelector('meta[name=csrf]').content) };
  };
  const post = async (s, caminho, form = {}) => {
    const r = await s.p.request.post(BASE + caminho, { form: { _csrf: s.csrf, ...form } });
    return [...(await r.text()).matchAll(/class="aviso[- ][a-z ]*"[^>]*>([^<]*)</g)].map((m) => m[1]).join(' | ');
  };
  const uid = (email) => sql(`select id from usuarios where email='${email}'`);
  const sit = (id) => sql(`select situacao from mkt_tarefas where id=${id}`);
  const tipo = (inicio) => sql(`select id from mkt_tipos where nome like '${inicio}%' limit 1`);
  const social = uid('social@demo.local'), ecom = uid('ecommerce@demo.local'), gestor = uid('marketing@demo.local'), tele = uid('televendas1@demo.local');

  // ---------- Funções e menu ----------
  ok(sql("select count(*) from funcoes where slug in ('social_media','gestor_marketing','ecommerce')") === '3', 'funções Social Media, Gestor de Marketing e E-commerce criadas');
  ok(sql("select funcao_slug from dp_etapas where nome='Criação do rótulo'") === 'gestor_marketing', 'rótulo do Desenvolvimento passa para o Gestor de Marketing');
  const v = await como('televendas1@demo.local');
  await v.p.goto(BASE + '/');
  ok(await v.p.locator('[data-pedir-marketing]').count() === 1, 'Início de todo mundo tem "Pedir ao Marketing"');
  const menu = await v.p.locator('nav').first().innerText();
  ok(!menu.includes('Planejamento') && !menu.includes('Leads do site'), 'Planejamento e Leads do site fora do menu');

  // ---------- Pedido de arte (Televendas) → Social Media ----------
  await v.p.goto(BASE + '/marketing/pedir');
  await v.p.selectOption('select[name=tipo_id]', tipo('Arte'));
  await v.p.fill('input[name=titulo]', 'Arte do Dia do Cliente');
  await v.p.fill('textarea[name=descricao]', 'Formato stories, com o logo');
  await v.p.setInputFiles('input[name=anexo]', { name: 'referencia.png', mimeType: 'image/png', buffer: PNG });
  await Promise.all([v.p.waitForNavigation(), v.p.click('[data-pedir] button[type=submit]')]);
  const t1 = sql("select id from mkt_tarefas where titulo='Arte do Dia do Cliente'");
  ok(!!t1 && sql(`select concat(funcao_slug,'/',situacao,'/',setor) from mkt_tarefas where id=${t1}`) === 'social_media/a_fazer/Televendas', 'arte vai para Social Media, "A fazer", pedido da Televendas');
  ok(sql(`select count(*) from notificacoes where tipo='mkt_tarefa' and usuario_id=${social} and link='/marketing/tarefas/${t1}'`) === '1', 'Social Media avisada no sino');
  ok(sql(`select count(*) from notificacoes where tipo='mkt_tarefa' and usuario_id=${ecom} and link='/marketing/tarefas/${t1}'`) === '0', 'E-commerce não recebe o pedido de arte');
  ok(await v.p.locator(`tr[data-pedido="${t1}"]`).count() === 1, 'aparece em "Meus pedidos"');
  // Cadastro de produto → E-commerce
  await post(v, '/marketing/tarefas', { pedido: '1', tipo_id: tipo('Cadastrar produto'), titulo: 'Cadastrar o Ozen X na Shopee' });
  const t2 = sql("select id from mkt_tarefas where titulo='Cadastrar o Ozen X na Shopee'");
  ok(sql(`select funcao_slug from mkt_tarefas where id=${t2}`) === 'ecommerce', 'cadastro de produto vai para E-commerce e Marketplaces');
  // Assuntos que saíram da lista (V11.6)
  await v.p.goto(BASE + '/marketing/pedir');
  const assuntos = (await v.p.locator('select[name=tipo_id] option').allInnerTexts()).join(' | ');
  ok(!/Rótulo ou embalagem|oferta relâmpago|Envio FULL/.test(assuntos) && assuntos.includes('Arte ou post'), 'sem "Rótulo ou embalagem", "Campanha de desconto" e "Envio FULL" na lista de assuntos');
  ok((await post(v, '/marketing/tarefas', { pedido: '1', tipo_id: tipo('Arte'), titulo: 'Ontem', prazo: '2000-01-01' })).includes('já passou'), 'prazo no passado não passa');

  // ---------- Anexo protegido ----------
  const s = await como('social@demo.local');
  const anexo = await s.p.request.get(BASE + `/marketing/tarefas/${t1}/anexo`);
  ok(anexo.status() === 200 && anexo.headers()['content-type'] === 'image/png', 'Social Media abre o anexo');
  const outra = await como('televendas2@demo.local');
  ok((await outra.p.request.get(BASE + `/marketing/tarefas/${t1}/anexo`)).status() === 404 && (await outra.p.request.get(BASE + `/marketing/tarefas/${t1}`)).status() === 404, 'outra vendedora não vê o pedido nem o anexo');
  ok((await v.p.request.get(BASE + `/marketing/tarefas/${t1}`)).status() === 200, 'quem pediu acompanha o pedido');

  // ---------- Quadro do Social Media ----------
  await s.p.goto(BASE + '/marketing/inicio');
  ok(await s.p.locator(`[data-coluna="a_fazer"] [data-cartao="tarefa-${t1}"]`).count() === 1, 'Social Media vê o pedido em "A fazer"');
  ok(await s.p.locator(`[data-cartao="tarefa-${t2}"]`).count() === 0, '...e não vê o FULL (é do E-commerce)');
  await s.p.click('[data-ver-todas]'); await s.p.waitForLoadState();
  ok(await s.p.locator(`[data-cartao="tarefa-${t2}"]`).count() === 1, '"Todas" mostra as tarefas de todo o Marketing');
  await s.p.goto(BASE + '/marketing/inicio');
  await Promise.all([s.p.waitForNavigation(), s.p.click(`[data-cartao="tarefa-${t1}"] [data-acao="comecar"]`)]);
  ok(sit(t1) === 'fazendo' && sql(`select responsavel_id from mkt_tarefas where id=${t1}`) === social, '"Começar": fica com quem começou');
  ok(await s.p.locator(`[data-coluna="fazendo"] [data-cartao="tarefa-${t1}"]`).count() === 1, 'vai para "Fazendo agora"');
  ok((await post(s, `/marketing/tarefas/${t1}/mudar`, { acao: 'aprovar' })).includes('Só quem aprova') && sit(t1) === 'fazendo', 'Social Media não aprova a própria tarefa');
  await post(s, `/marketing/tarefas/${t1}/mudar`, { acao: 'aprovacao' });
  ok(sit(t1) === 'aprovacao' && sql(`select count(*) from notificacoes where tipo='mkt_aprovar' and usuario_id=${gestor} and link='/marketing/tarefas/${t1}'`) === '1', 'enviada para aprovação: Gestor de Marketing avisado');

  // ---------- Gestor de Marketing ----------
  const g = await como('marketing@demo.local');
  await g.p.goto(BASE + '/marketing/inicio');
  ok(await g.p.locator(`[data-coluna="aprovacao"] [data-cartao="tarefa-${t1}"]`).count() === 1, 'gestor vê em "Esperando aprovação"');
  const postsAprovar = Number(sql("select count(*) from mkt_posts p join mkt_post_etapas e on e.id=p.etapa_id where e.tipo='aprovacao'"));
  ok(await g.p.locator('[data-coluna="aprovacao"] [data-cartao^="post-"]').count() === Math.min(postsAprovar, 30), 'posts para aprovar também viram tarefa do gestor');
  ok(await g.p.locator('[data-coluna="a_fazer"] [data-cartao^="projeto-"]').count() >= 1, 'rótulo do Desenvolvimento aparece para o Gestor de Marketing');
  ok(await g.p.locator('.mt-cal-dia.tem').count() >= 1, 'mini calendário marca os dias com posts e prazos');
  ok((await post(g, `/marketing/tarefas/${t1}/mudar`, { acao: 'devolver', comentario: '' })).includes('ajustar'), 'pedir ajuste exige comentário');
  await post(g, `/marketing/tarefas/${t1}/mudar`, { acao: 'devolver', comentario: 'Aumentar o logo' });
  ok(sit(t1) === 'fazendo' && sql(`select count(*) from notificacoes where tipo='mkt_ajuste' and usuario_id=${social}`) === '1', 'ajuste volta para Social Media (avisada)');
  await post(s, `/marketing/tarefas/${t1}/mudar`, { acao: 'aprovacao' });
  await post(g, `/marketing/tarefas/${t1}/mudar`, { acao: 'aprovar' });
  ok(sit(t1) === 'feito' && sql(`select count(*) from notificacoes where tipo='mkt_pronto' and usuario_id=${tele} and link='/marketing/tarefas/${t1}'`) === '1', 'aprovada: feita e quem pediu é avisado');
  // comentário do Marketing avisa quem pediu
  await post(g, `/marketing/tarefas/${t2}/comentar`, { texto: 'Precisamos da lista de produtos' });
  ok(sql(`select count(*) from notificacoes where tipo='mkt_comentario' and usuario_id=${tele}`) === '1', 'comentário do Marketing avisa quem pediu');
  // encaminhar
  await post(g, `/marketing/tarefas/${t2}/encaminhar`, { funcao_slug: 'ecommerce', responsavel_id: ecom, prazo: '2099-01-10' });
  ok(sql(`select concat(responsavel_id,'/',prazo) from mkt_tarefas where id=${t2}`) === `${ecom}/2099-01-10`, 'encaminhar define quem faz e o prazo');
  ok(sql(`select count(*) from notificacoes where usuario_id=${ecom} and link='/marketing/tarefas/${t2}' and tipo='mkt_tarefa'`) === '2', 'quem recebe a tarefa é avisado');
  // vendedora não mexe
  ok((await post(v, `/marketing/tarefas/${t2}/mudar`, { acao: 'concluir' })).includes('não pode') && sit(t2) === 'a_fazer', 'vendedora não conclui tarefa do Marketing');
  await post(v, `/marketing/tarefas/${t2}/mudar`, { acao: 'cancelar' });
  ok(sit(t2) === 'cancelado', 'quem pediu pode cancelar enquanto está "A fazer"');

  // ---------- Listas: tipo → função configurável ----------
  const adm = await como('admin@demo.local');
  const tipoArte = tipo('Arte');
  await post(adm, `/configuracoes/listas/mkt_tipos/${tipoArte}`, { nome: sql(`select nome from mkt_tipos where id=${tipoArte}`), ordem: '10', ativo: '1', funcao_slug: 'gestor_marketing' });
  ok(sql(`select funcao_slug from mkt_tipos where id=${tipoArte}`) === 'gestor_marketing', 'admin muda a função que recebe cada tipo (Listas)');

  // ---------- Imagem da aprovação sem corte ----------
  const postId = sql('select id from mkt_posts where midia_id is not null order by id limit 1');
  if (postId) {
    await g.p.goto(BASE + `/marketing?post=${postId}`);
    await g.p.waitForSelector('.mk-previa img', { timeout: 5000 }).catch(() => null);
    const fit = await g.p.evaluate(() => { const i = document.querySelector('.mk-previa img'); return i ? getComputedStyle(i).objectFit : null; });
    ok(fit === 'contain', 'imagem da aprovação aparece inteira (sem corte): ' + fit);
  } else ok(true, 'sem post com imagem na demonstração (pulado)');

  ok(erros.length === 0, 'nenhum erro de JavaScript' + (erros.length ? ': ' + erros.join(' / ') : ''));
  await b.close();
  console.log(falhas ? `\n${falhas} FALHA(S)` : '\nTudo certo.');
  process.exit(falhas ? 1 : 0);
})();
