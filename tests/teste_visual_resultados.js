// Teste automático (Playwright). Rodar todos: bash tests/rodar.sh  ·  só este: node tests/teste_visual_resultados.js
// Visual de Resultados, Meu mês e Agenda: anel da meta, selo de ritmo, barras por dia, "Para fazer hoje",
// Agenda em semana com cartões, Reagendar (vai para a linha do tempo), Concluir e nada cortado no computador e no celular.
const { chromium } = require('playwright');
const { execSync } = require('child_process');
const BASE = 'http://127.0.0.1:8000';
let falhas = 0; const ok = (c, m) => { console.log((c ? 'OK   ' : 'FALHA') + ' ' + m); if (!c) falhas++; };
const sql = (q) => execSync(`mysql perfeita --default-character-set=utf8mb4 -N -e "${q.replace(/"/g, '\\"')}"`).toString().trim();

(async () => {
  const b = await chromium.launch();
  const erros = [];
  const como = async (email, opcoes = {}) => {
    const p = await (await b.newContext({ viewport: { width: 1440, height: 900 }, ...opcoes })).newPage();
    p.on('pageerror', (e) => erros.push(e.message + ' @ ' + p.url())); await p.route(/open-meteo|jsdelivr/, (r) => r.fulfill({ body: '{}' }));
    await p.goto(BASE + '/entrar'); await p.fill('#email', email); await p.fill('#senha', 'Demo@2026'); await p.click('button[type=submit]'); await p.waitForLoadState();
    if (await p.locator('[data-novidades-ok]').count()) { await p.click('[data-novidades-ok]'); await p.waitForLoadState(); }
    return { p, csrf: await p.evaluate(() => document.querySelector('meta[name=csrf]').content) };
  };
  const largura = (p) => p.evaluate(() => document.documentElement.scrollWidth);
  const t1 = sql("select id from usuarios where email='televendas1@demo.local'");
  const mes = sql("select date_format(curdate(), '%Y-%m')");

  // ---------- Meu mês ----------
  const v = await como('televendas1@demo.local');
  await v.p.goto(BASE + '/meu-mes');
  const meta = Number(sql(`select coalesce(meta,0) from meu_mes where usuario_id=${t1} and mes='${mes}'`) || 0);
  const vendido = Number(sql(`select coalesce(sum(valor),0) from vendas_registradas where usuario_id=${t1} and data like '${mes}-%' and data <= curdate()`));
  const anel = Number(await v.p.getAttribute('[data-mm-hero] [data-anel]', 'data-anel'));
  ok(meta > 0 && anel === Math.round(vendido / meta * 100), `anel da meta mostra ${Math.round(vendido / meta * 100)}%`);
  const ritmo = await v.p.getAttribute('[data-mm-hero] [data-ritmo]', 'data-ritmo');
  ok(['no_ritmo', 'atras', 'batida', 'inicio'].includes(ritmo), 'selo de ritmo da meta (' + ritmo + ')');
  const diasTrabalho = await v.p.locator('[data-grafico-barras] [data-coluna]').count();
  ok(diasTrabalho >= 15 && diasTrabalho <= 27, `vendas por dia: uma barra por dia de trabalho (${diasTrabalho})`);
  ok(await v.p.locator('[data-para-hoje]').count() === 1, '"Para fazer hoje" na Visão do mês');
  ok(await largura(v.p) <= 1440, 'Meu mês sem rolar para o lado (1440 px)');

  // ---------- Agenda: semana, reagendar, concluir ----------
  const cli = sql(`select id from clientes where responsavel_id=${t1} order by id limit 1`);
  const amanha = sql('select curdate() + interval 1 day');
  sql(`insert into tarefas (cliente_id, usuario_id, titulo, tipo, vence_em, hora) values (${cli}, ${t1}, 'Teste visual agenda', 'compromisso', curdate() - interval 2 day, '10:00')`);
  const tar = sql("select max(id) from tarefas where titulo='Teste visual agenda'");
  await v.p.goto(BASE + '/agenda');
  ok(await v.p.locator('.ag-semana [data-dia-coluna]').count() === 7, 'Agenda em semana: 7 colunas');
  ok(await v.p.locator(`[data-atrasadas] [data-tarefa="${tar}"]`).count() === 1, 'tarefa atrasada aparece na faixa de atrasados');
  ok(await v.p.locator(`[data-tarefa="${tar}"].ag-compromisso`).count() === 1, 'cartão com a cor do tipo (compromisso)');
  await v.p.click(`[data-tarefa="${tar}"] .ag-reagendar summary`);
  await Promise.all([v.p.waitForNavigation(), v.p.click(`[data-tarefa="${tar}"] button[value="amanha"]`)]);
  ok(sql(`select vence_em from tarefas where id=${tar}`) === amanha, 'Reagendar para amanhã muda o dia');
  ok(sql(`select count(*) from cliente_eventos where cliente_id=${cli} and descricao like 'Reagendada para%Teste visual agenda'`) === '1', 'reagendamento na linha do tempo do cliente');
  const naSemana = sql(`select yearweek(curdate(), 1) = yearweek(curdate() + interval 1 day, 1)`) === '1';
  if (naSemana) ok(await v.p.locator(`[data-dia-coluna="${amanha}"] [data-tarefa="${tar}"]`).count() === 1, 'na semana, aparece na coluna de amanhã');
  else ok(true, 'amanhã é na próxima semana (coluna não conferida)');
  await v.p.goto(BASE + '/agenda?ver=lista');
  await Promise.all([v.p.waitForNavigation(), v.p.click(`[data-tarefa="${tar}"] [data-concluir]`)]);
  ok(sql(`select concluida_em is not null from tarefas where id=${tar}`) === '1' && v.p.url().includes('ver=lista'), 'Concluir fecha a tarefa e volta para a mesma visão');
  ok((await (await v.p.request.post(BASE + `/agenda/${tar}/reagendar`, { form: { _csrf: v.csrf, para: 'data', data: '2000-01-01' } })).text()).length > 0, 'reagendar para o passado não quebra');

  // ---------- Resultados (gestão) ----------
  const g = await como('gestor@demo.local');
  await g.p.goto(BASE + '/acompanhamento');
  ok(await g.p.locator('[data-equipe] [data-pessoa]').count() >= 3, 'equipe em cartões, com a barra da meta');
  ok(await largura(g.p) <= 1440, 'Acompanhamento sem cortar à direita (1440 px)');
  await g.p.goto(BASE + `/acompanhamento/pessoa/${t1}`);
  ok(await g.p.locator('[data-resumo-meta] [data-anel]').count() === 1 && await g.p.locator('[data-resumo-meta] [data-ritmo]').count() === 1, 'Resumo da vendedora: anel da meta e ritmo');
  ok(await g.p.locator('[data-grafico-barras] [data-coluna]').count() >= 1, 'ligações por dia sem depender de biblioteca da internet');
  ok(await g.p.locator('[data-atencao], .ac-tudo-certo').count() >= 1, 'pontos de atenção');

  // ---------- Celular ----------
  const cel = await como('televendas1@demo.local', { viewport: { width: 390, height: 844 }, isMobile: true });
  for (const u of ['/meu-mes', '/agenda']) {
    await cel.p.goto(BASE + u);
    ok(await largura(cel.p) <= 392, `${u} cabe no celular (${await largura(cel.p)} px)`);
  }
  const gc = await como('gestor@demo.local', { viewport: { width: 390, height: 844 }, isMobile: true });
  for (const u of ['/acompanhamento', `/acompanhamento/pessoa/${t1}`]) {
    await gc.p.goto(BASE + u);
    ok(await largura(gc.p) <= 392, `${u} cabe no celular (${await largura(gc.p)} px)`);
  }

  ok(erros.length === 0, 'nenhum erro de JavaScript' + (erros.length ? ': ' + erros.join(' / ') : ''));
  await b.close();
  console.log(falhas ? `\n${falhas} FALHA(S)` : '\nTudo certo.');
  process.exit(falhas ? 1 : 0);
})();
