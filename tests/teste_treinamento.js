// Teste automático (Playwright). Rodar todos: bash tests/rodar.sh  ·  só este: node tests/teste_treinamento.js
// Treinamento por setor (V13.0): cada pessoa vê Integração + a trilha do setor + áreas vizinhas; prova com nota
// mínima (abaixo não conclui, acima conclui; a resposta certa não vai para a tela antes); trilha completa → selo e
// comemoração; Equipe só para gestor; Marketing edita módulo (a atualização não sobrescreve); endereços antigos da
// Academia levam ao Treinamento; nenhum "Leads2b" na interface.
const { chromium } = require('playwright');
const { execSync } = require('child_process');
const BASE = 'http://127.0.0.1:8000';
const SAIDA = process.argv[2] || 'tests/saida';
let falhas = 0; const ok = (c, m) => { console.log((c ? 'OK   ' : 'FALHA') + ' ' + m); if (!c) falhas++; };
const sql = (q) => execSync(`mysql perfeita --default-character-set=utf8mb4 -N -e "${q.replace(/"/g, '\\"')}"`).toString().trim();

async function entrar(b, email, vp = { width: 1360, height: 900 }) {
  const p = await (await b.newContext({ viewport: vp })).newPage();
  p.erros = []; p.on('pageerror', (e) => p.erros.push(e.message));
  await p.route(/open-meteo/, (r) => r.fulfill({ body: '{}' }));
  await p.goto(BASE + '/entrar'); await p.fill('#email', email); await p.fill('#senha', 'Demo@2026'); await p.click('button[type=submit]'); await p.waitForLoadState();
  if (await p.locator('[data-novidades-ok]').count()) { await p.click('[data-novidades-ok]'); await p.waitForLoadState(); }
  return p;
}
const blocos = (p) => p.locator('[data-tr-bloco]').evaluateAll((l) => l.map((e) => e.dataset.trBloco));

(async () => {
  const b = await chromium.launch();
  ok(sql('select count(*) from trein_trilhas') >= '15' && sql('select count(*) from trein_modulos') >= '55', 'trilhas e módulos carregados (' + sql('select count(*) from trein_trilhas') + ' trilhas, ' + sql('select count(*) from trein_modulos') + ' módulos)');
  ok(sql('select count(*) from trein_modulos m where not exists (select 1 from trein_perguntas p where p.modulo_id=m.id)') === '0', 'todo módulo tem prova');
  ok(sql("select count(*) from funcoes f where ativa=1 and not exists (select 1 from trein_trilhas t where t.funcao_slug=f.slug)") === '0', 'toda função ativa tem uma trilha');

  // Televendas
  const tv = await entrar(b, 'televendas1@demo.local');
  await tv.goto(BASE + '/treinamento');
  const bt = await blocos(tv);
  ok(bt.join(',') === 'integracao,televendas,produtos,vizinhos', 'televendas vê: Integração → Televendas → Conhecimento de produto → Áreas com que tem contato');
  const viz = await tv.locator('[data-tr-bloco="vizinhos"]').innerText();
  ok(viz.includes('Como trabalha o Financeiro') && viz.includes('Como trabalha o Depósito') && !viz.includes('Como trabalha o Televendas'), 'áreas vizinhas: Financeiro, Depósito... (e não o próprio setor)');
  await tv.screenshot({ path: SAIDA + '/treinamento-meu.png', fullPage: true });

  // V13.2: Conhecimento de produto, prints, regras atuais e casos práticos
  ok((await blocos(tv)).includes('produtos'), 'televendas tem a trilha "Conhecimento de produto"');
  const modPed = sql("select id from trein_modulos where chave='tv-pedido'");
  await tv.goto(BASE + '/treinamento/modulo/' + modPed);
  const imgs = await tv.locator('[data-tr-conteudo] figure.tr-print img').evaluateAll((l) => l.map((i) => i.getAttribute('src')));
  let imgsOk = imgs.length >= 4;
  for (const src of imgs) { const r = await tv.request.get(BASE.replace(/\/$/, '') + src.replace(/^https?:\/\/[^/]+/, '')); if (!r.ok()) imgsOk = false; }
  ok(imgsOk, 'módulo com prints das telas (' + imgs.length + ' imagens, todas abrem)');
  const conteudo = await tv.locator('[data-tr-conteudo]').innerText();
  ok(!conteudo.includes('{{') && conteudo.includes(sql("select replace(format(pedido_minimo,2,'de_DE'),'','') from regioes order by pedido_minimo limit 1")) && conteudo.includes(sql("select valor from configuracoes where chave='pedidos.desconto_max_pct'") + '%'), 'regras com os valores atuais (pedido mínimo por região e limite de desconto)');
  sql("update configuracoes set valor='7' where chave='pedidos.desconto_max_pct'");
  await tv.reload();
  ok((await tv.locator('[data-tr-conteudo]').innerText()).includes('7%'), 'mudou a configuração: o treinamento mostra o valor novo');
  sql("update configuracoes set valor='10' where chave='pedidos.desconto_max_pct'");
  ok(await tv.locator('.tr-caso').count() >= 2 && !(await tv.locator('.tr-caso p').first().isVisible()), 'casos práticos começam fechados');
  await tv.locator('.tr-caso summary').first().click();
  ok(await tv.locator('.tr-caso p').first().isVisible(), 'clicou no caso: aparece o que fazer');
  const modCap = sql("select id from trein_modulos where chave='pd-capsulas'");
  await tv.goto(BASE + '/treinamento/modulo/' + modCap);
  ok(await tv.locator('.tr-produto').count() >= 10 && (await tv.locator('.tr-produto').first().getAttribute('href')).includes('/academia/produtos/'), 'linha de cápsulas: vitrine com os produtos atuais e link para a ficha');

  // Produção não vê a trilha do Televendas
  const pr = await entrar(b, 'producao@demo.local');
  await pr.goto(BASE + '/treinamento');
  const bp = await blocos(pr);
  ok(bp.includes('producao') && !bp.includes('televendas') && !bp.includes('produtos'), 'Produção vê a trilha dela (não a do Televendas nem a de produto)');
  ok((await pr.locator('[data-tr-bloco="vizinhos"]').innerText()).includes('Como trabalha a Qualidade'), 'Produção tem a Qualidade nas áreas vizinhas');
  // Financeiro (perfil) tem a trilha de aprovação, além da Cobrança (função)
  const fi = await entrar(b, 'financeiro@demo.local');
  await fi.goto(BASE + '/treinamento');
  const bf = await blocos(fi);
  ok(bf.includes('cobranca') && bf.includes('financeiro'), 'perfil Financeiro: trilha do Financeiro + Cobrança');

  // V13.8: trilhas por perfil (Gestão e Administração) e a Fábrica com prints
  const ge = await entrar(b, 'gestor@demo.local');
  await ge.goto(BASE + '/treinamento');
  ok((await blocos(ge)).includes('gestor'), 'gestor tem a trilha "Gestão comercial"');
  const ad = await entrar(b, 'admin@demo.local');
  await ad.goto(BASE + '/treinamento');
  ok((await blocos(ad)).includes('admin'), 'admin tem a trilha "Administração do One"');
  await pr.goto(BASE + '/treinamento/modulo/' + sql("select id from trein_modulos where chave='pd-ordens'"));
  ok(await pr.locator('[data-tr-conteudo] figure.tr-print').count() >= 3 && await pr.locator('.tr-caso').count() >= 2, 'Produção: ordens de produção com prints e casos práticos');

  // Módulo e prova (a resposta certa não vai para a tela)
  const mod = sql("select id from trein_modulos where chave='tv-pedido'");
  await tv.goto(BASE + '/treinamento/modulo/' + mod);
  ok((await tv.locator('[data-tr-conteudo] h3').count()) >= 2 && (await tv.locator('[data-tr-conteudo] a[href$="/orcamentos"]').count()) >= 1, 'módulo: títulos e links para as telas do sistema');
  ok(!(await tv.content()).includes('"correta"') && await tv.locator('.tr-correta').count() === 0, 'a resposta certa não aparece antes de enviar');
  const perguntas = JSON.parse(sql(`select json_arrayagg(json_object('id', id, 'c', correta)) from trein_perguntas where modulo_id=${mod}`));
  // errando tudo
  for (const q of perguntas) await tv.check(`input[name="resposta[${q.id}]"][value="${(q.c + 1) % 2}"]`);
  await Promise.all([tv.waitForNavigation(), tv.click('[data-tr-enviar]')]);
  ok((await tv.locator('[data-tr-nota]').getAttribute('data-tr-nota')) === '0' && await tv.locator('.tr-pergunta.errada').count() === perguntas.length, 'errou tudo: nota 0, mostra o que errou');
  ok(sql(`select count(*) from trein_progresso p join usuarios u on u.id=p.usuario_id where u.email='televendas1@demo.local' and modulo_id=${mod} and aprovado_em is not null`) === '0', 'abaixo da nota mínima: não conclui');
  // acertando
  for (const q of perguntas) await tv.check(`input[name="resposta[${q.id}]"][value="${q.c}"]`);
  await Promise.all([tv.waitForNavigation(), tv.click('[data-tr-enviar]')]);
  ok((await tv.locator('[data-tr-nota]').getAttribute('data-tr-nota')) === '100' && (await tv.locator('.tr-nota').innerText()).includes('concluído'), 'acertou: 100%, módulo concluído');
  ok(sql(`select concat(nota,'/',tentativas) from trein_progresso p join usuarios u on u.id=p.usuario_id where u.email='televendas1@demo.local' and modulo_id=${mod}`) === '100/2', 'guarda a melhor nota e as tentativas');
  await tv.screenshot({ path: SAIDA + '/treinamento-prova.png', fullPage: true });

  // Concluir a trilha inteira do Televendas → selo + comemoração
  const outros = sql("select id from trein_modulos where trilha_id=(select id from trein_trilhas where chave='televendas') and tipo='setor' and chave<>'tv-pedido' order by ordem").split('\n');
  for (const id of outros) {
    await tv.goto(BASE + '/treinamento/modulo/' + id);
    const qs = JSON.parse(sql(`select json_arrayagg(json_object('id', id, 'c', correta)) from trein_perguntas where modulo_id=${id}`));
    for (const q of qs) await tv.check(`input[name="resposta[${q.id}]"][value="${q.c}"]`);
    await Promise.all([tv.waitForNavigation(), tv.click('[data-tr-enviar]')]);
  }
  ok((await tv.locator('.aviso').first().innerText()).includes('concluiu a trilha "Televendas"'), 'trilha do setor completa: parabéns');
  ok(await tv.locator('#one-festa').count() === 1, 'o One comemora (confete)');
  await tv.goto(BASE + '/treinamento');
  ok((await tv.locator('.tr-selos').innerText()).includes('Televendas'), 'o selo aparece no Meu treinamento');
  const idTv = sql("select id from usuarios where email='televendas1@demo.local'");
  await tv.goto(BASE + '/pessoas/' + idTv);
  ok((await tv.locator('main').innerText()).includes('Treinamento: Televendas'), 'o selo aparece no perfil da pessoa');

  // Todas as trilhas: consulta de outro setor
  await tv.goto(BASE + '/treinamento/trilhas');
  ok(await tv.locator('[data-tr-trilha]').count() >= 15, 'Todas as trilhas: todos os setores para consulta');
  const modProd = sql("select id from trein_modulos where chave='pd-ordens'");
  const r1 = await tv.goto(BASE + '/treinamento/modulo/' + modProd);
  ok(r1.status() === 200 && (await tv.locator('.cabecalho-secao h1').innerText()).includes('Ordens de produção'), 'a vendedora consegue ler um módulo da Produção (consulta)');

  // Equipe e edição: acesso
  const r403 = await tv.goto(BASE + '/treinamento/equipe');
  ok(r403.status() === 403, 'vendedora não vê a Equipe (403)');
  const r403b = await tv.goto(BASE + '/treinamento/editar');
  ok(r403b.status() === 403, 'vendedora não edita o treinamento (403)');
  const g = await entrar(b, 'gestora.televendas@demo.local');
  await g.goto(BASE + '/treinamento/equipe?setor=televendas');
  const linha = await g.locator('tr', { hasText: 'Televendas 1' }).innerText();
  ok(linha.includes('100%') || linha.match(/\d+ de \d+/), 'gestora do setor vê o progresso da equipe (módulos e média)');

  // Marketing edita um módulo
  const mk = await entrar(b, 'marketing@demo.local');
  const modInt = sql("select id from trein_modulos where chave='int-lgpd'");
  await mk.goto(BASE + '/treinamento/editar/modulo/' + modInt);
  await mk.fill('input[name=titulo]', 'Segurança e LGPD (editado)');
  await mk.fill('textarea[name=conteudo]', '## Texto novo\n- item com <script>alert(1)</script>\n> destaque');
  await mk.fill('input[name="perguntas[0][pergunta]"]', 'Pergunta editada?');
  await Promise.all([mk.waitForNavigation(), mk.click('button:has-text("Salvar módulo")')]);
  ok(sql(`select titulo from trein_modulos where id=${modInt}`) === 'Segurança e LGPD (editado)' && sql(`select pergunta from trein_perguntas where modulo_id=${modInt} order by ordem limit 1`) === 'Pergunta editada?', 'Marketing edita o texto e a prova');
  let alerta = false; mk.on('dialog', (d) => { alerta = true; d.dismiss(); });
  await mk.goto(BASE + '/treinamento/modulo/' + modInt);
  ok(!alerta && await mk.locator('[data-tr-conteudo] script').count() === 0 && (await mk.locator('[data-tr-conteudo]').innerText()).includes('<script>'), 'texto editado com HTML aparece como texto (seguro)');
  execSync('php database/migrar.php', { stdio: 'ignore' }); // rodar a atualização de novo
  ok(sql(`select titulo from trein_modulos where id=${modInt}`) === 'Segurança e LGPD (editado)', 'a atualização do sistema não sobrescreve o que foi editado');
  // tutorial editado pela tela
  const tId = sql("select id from tutorial_telas where rota='/agenda'");
  await mk.goto(BASE + '/treinamento/editar/tutorial/' + tId);
  await mk.fill('textarea[name=passos]', '.cabecalho-secao | Passo editado da Agenda\nSegundo passo no meio');
  await Promise.all([mk.waitForNavigation(), mk.click('button:has-text("Salvar")')]);
  ok(sql(`select json_length(passos_json) from tutorial_telas where id=${tId}`) === '2' && sql(`select json_unquote(json_extract(passos_json,'$[0].alvo')) from tutorial_telas where id=${tId}`) === '.cabecalho-secao', 'Marketing edita o passo a passo de uma tela');

  // Endereços antigos e Leads2b
  for (const u of ['/academia', '/academia/trilha', '/academia/sistema', '/academia/crm', '/academia/crm/rotina', '/academia/atendimento']) {
    await tv.goto(BASE + u);
    if (!tv.url().endsWith('/treinamento')) { ok(false, u + ' leva para o Treinamento'); }
  }
  ok(true, 'endereços antigos da Academia (inclusive as aulas do Leads2b) levam ao Treinamento');
  await tv.goto(BASE + '/academia/produtos');
  ok(await tv.locator('.cartao-produto').count() > 0, 'a Biblioteca de produtos continua (fichas para estudar)');
  let comLeads = [];
  for (const u of ['/', '/treinamento', '/treinamento/trilhas', '/academia/produtos', '/carteira', '/novidades']) {
    await tv.goto(BASE + u);
    if ((await tv.locator('body').innerText()).toLowerCase().includes('leads2b')) comLeads.push(u);
  }
  ok(comLeads.length === 0, 'nenhum "Leads2b" na interface' + (comLeads.length ? ': ' + comLeads.join(', ') : ''));

  // Pergunte ao One (sem IA) acha os módulos novos
  await tv.goto(BASE + '/');
  const resp = await tv.evaluate(async () => {
    const f = new FormData(); f.append('pergunta', 'como funciona o pedido mínimo e o orçamento por link'); f.append('_csrf', document.querySelector('meta[name=csrf]').content);
    const r = await fetch(document.querySelector('meta[name=base]').content + '/one/perguntar', { method: 'POST', body: f, headers: { Accept: 'application/json' } });
    return (await r.json()).texto;
  });
  ok(/treinamento\/modulo|Orçamentos|orcamentos/i.test(resp), 'o Pergunte ao One encontra o conteúdo do treinamento novo');

  // Celular
  const cel = await entrar(b, 'televendas2@demo.local', { width: 390, height: 800 });
  await cel.goto(BASE + '/treinamento');
  ok(await cel.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), 'celular: Meu treinamento sem rolagem para o lado');
  await cel.goto(BASE + '/treinamento/modulo/' + mod);
  ok(await cel.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), 'celular: módulo e prova sem rolagem para o lado');

  const erros = [tv, pr, fi, g, mk, cel, ge, ad].flatMap((p) => p.erros);
  ok(erros.length === 0, 'sem erros de JavaScript' + (erros[0] ? ': ' + erros[0] : ''));
  await b.close();
  process.exit(falhas ? 1 : 0);
})();
