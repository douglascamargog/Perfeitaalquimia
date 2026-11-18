// Teste automático (Playwright). Rodar todos: bash tests/rodar.sh  ·  só este: bash tests/rodar.sh lote_desfazer
// V15.9: filtros lembrados na Carteira, ações com as lojas marcadas (etapa, prioridade, carteira) e "↶ Desfazer"
// (concluir tarefa, excluir tarefa, apagar registro da linha do tempo).
const { chromium } = require('playwright');
const { execSync } = require('child_process');
const BASE = 'http://127.0.0.1:8000';
let falhas = 0; const ok = (c, m) => { console.log((c ? 'OK   ' : 'FALHA') + ' ' + m); if (!c) falhas++; };
const sql = (q) => execSync(`mysql perfeita --default-character-set=utf8mb4 -N -e "${q.replace(/"/g, '\\"')}"`).toString().trim();

(async () => {
  const tv1 = sql("select id from usuarios where email='televendas1@demo.local'");
  const tv2 = sql("select id from usuarios where email='televendas2@demo.local'");
  const loja = (nome, dono) => {
    sql(`insert into clientes (tipo, razao_social, nome_fantasia, cidade, uf, responsavel_id, prioridade, etapa_id) values ('cliente', '${nome} Ltda', '${nome}', 'Canoas', 'RS', ${dono}, 'Baixa', (select min(id) from funil_etapas))`);
    return sql(`select max(id) from clientes where nome_fantasia='${nome}'`);
  };
  const l1 = loja('Lote Um', tv1), l2 = loja('Lote Dois', tv1), l3 = loja('Lote Três', tv1), outra = loja('Lote Da Outra', tv2);

  const b = await chromium.launch(); const erros = [];
  const entrar = async (email) => {
    const p = await (await b.newContext()).newPage(); p.on('pageerror', (e) => erros.push(e.message)); p.on('dialog', (d) => d.accept());
    await p.goto(BASE + '/entrar'); await p.fill('#email', email); await p.fill('#senha', 'Demo@2026'); await p.click('button[type=submit]'); await p.waitForLoadState();
    return p;
  };
  try {
    // ---------------- Filtros lembrados
    const v = await entrar('televendas1@demo.local');
    await v.goto(BASE + '/carteira/lista?q=Lote&prioridade=Baixa');
    await v.goto(BASE + '/agenda');
    await v.goto(BASE + '/carteira/lista');
    ok(v.url().includes('q=Lote') && v.url().includes('prioridade=Baixa') && await v.locator('[data-limpar-filtros]').count() === 1, 'voltar para a Carteira reabre com os últimos filtros');
    await v.goto(BASE + '/carteira');
    ok(v.url().includes('q=Lote'), 'a Visão geral da Carteira também usa os filtros lembrados');
    await v.goto(BASE + '/carteira/lista');
    await Promise.all([v.waitForNavigation(), v.click('[data-limpar-filtros]')]);
    await v.goto(BASE + '/carteira/lista');
    ok(!v.url().includes('?'), '"Limpar filtros" esquece os filtros');

    // ---------------- Ações com as lojas marcadas
    await v.goto(BASE + '/carteira/lista?q=Lote');
    await v.check(`[data-lote-item][value="${l1}"]`); await v.check(`[data-lote-item][value="${l2}"]`);
    ok((await v.locator('[data-lote-qtd]').innerText()) === '(2)', 'contador mostra quantas lojas estão marcadas');
    await v.selectOption('[data-lote] select[name=prioridade]', 'Alta');
    await Promise.all([v.waitForNavigation(), v.click('[data-lote] button[value=prioridade]')]);
    ok(sql(`select group_concat(prioridade order by id) from clientes where id in (${l1},${l2},${l3})`) === 'Alta,Alta,Baixa', 'prioridade mudada só nas marcadas');
    ok(sql(`select count(*) from cliente_eventos where cliente_id in (${l1},${l2}) and descricao like 'Prioridade: Baixa → Alta'`) === '2', 'cada loja registra a mudança na linha do tempo');
    const etapa = sql("select id from funil_etapas where nome like 'Em negocia%' limit 1");
    await v.goto(BASE + '/carteira/lista?q=Lote');
    await v.check('[data-lote-todas]');
    await v.selectOption('[data-lote] select[name=etapa_id]', etapa);
    await Promise.all([v.waitForNavigation(), v.click('[data-lote] button[value=etapa]')]);
    ok(sql(`select count(*) from clientes where id in (${l1},${l2},${l3}) and etapa_id=${etapa}`) === '3', '"marcar todas" + mudar etapa');
    const tok = await v.locator('meta[name=csrf]').getAttribute('content');
    await v.request.post(BASE + '/carteira/lote', { form: { _csrf: tok, acao: 'prioridade', prioridade: 'Alta', 'ids[]': outra } });
    ok(sql(`select prioridade from clientes where id=${outra}`) === 'Baixa', 'loja de outra carteira não muda (a vendedora só mexe nas dela)');
    const g = await entrar('gestor@demo.local');
    const cart = sql(`select id from carteiras where responsavel_id=${tv2} and ativa=1 limit 1`);
    if (cart) {
      await g.goto(BASE + '/carteira/lista?q=Lote+Um');
      await g.check(`[data-lote-item][value="${l1}"]`);
      await g.selectOption('[data-lote] select[name=carteira_id]', cart);
      await Promise.all([g.waitForNavigation(), g.click('[data-lote] button[value=carteira]')]);
      ok(sql(`select responsavel_id from clientes where id=${l1}`) === tv2, 'gestão move as marcadas para outra carteira');
    }

    // ---------------- Desfazer
    sql(`insert into tarefas (cliente_id, usuario_id, titulo, tipo, vence_em) values (${l2}, ${tv1}, 'Retorno lote', 'retorno', CURDATE())`);
    const t1 = sql('select max(id) from tarefas');
    await v.goto(BASE + '/agenda');
    await Promise.all([v.waitForNavigation(), v.locator(`[data-tarefa="${t1}"] [data-concluir]`).first().click()]);
    ok(await v.locator('[data-desfazer]').count() === 1, 'depois de concluir aparece "↶ Desfazer"');
    await Promise.all([v.waitForNavigation(), v.click('[data-desfazer] button')]);
    ok(sql(`select concluida_em is null from tarefas where id=${t1}`) === '1' && sql(`select count(*) from cliente_eventos where cliente_id=${l2} and descricao='Concluída: Retorno lote'`) === '0',
      'Desfazer: a tarefa volta para a Agenda (e sai da linha do tempo)');
    await Promise.all([v.waitForNavigation(), v.locator(`[data-tarefa="${t1}"] [data-concluir]`).first().click()]);
    await v.goto(BASE + '/agenda');
    ok(await v.locator('[data-desfazer]').count() === 0, 'o botão aparece só na tela logo depois (não desfaz algo antigo sem querer)');

    sql(`insert into tarefas (cliente_id, usuario_id, titulo, tipo, vence_em) values (${l2}, ${tv1}, 'Tarefa apagada sem querer', 'retorno', CURDATE())`);
    const t2 = sql('select max(id) from tarefas');
    await v.goto(BASE + '/agenda');
    const c2 = v.locator(`[data-tarefa="${t2}"]`).first();
    await c2.locator('[data-editar-tarefa] summary').click();
    await Promise.all([v.waitForNavigation(), c2.locator('[data-editar-tarefa] form').nth(1).locator('button').click()]);
    await Promise.all([v.waitForNavigation(), v.click('[data-desfazer] button')]);
    ok(sql(`select titulo from tarefas where id=${t2}`) === 'Tarefa apagada sem querer', 'Desfazer: tarefa excluída volta (com o mesmo número)');

    sql(`insert into cliente_eventos (cliente_id, usuario_id, tipo, descricao, ocorrido_em, registrado_em) values (${l2}, ${tv1}, 'nota', 'Nota que volta', NOW(), NOW())`);
    const ev = sql('select max(id) from cliente_eventos');
    await v.goto(BASE + `/carteira/clientes/${l2}`);
    await v.locator(`[data-corrigir-evento="${ev}"] summary`).click();
    await Promise.all([v.waitForNavigation(), v.locator(`[data-corrigir-evento="${ev}"] form`).nth(1).locator('button').click()]);
    await Promise.all([v.waitForNavigation(), v.click('[data-desfazer] button')]);
    ok(sql(`select descricao from cliente_eventos where id=${ev}`) === 'Nota que volta', 'Desfazer: registro apagado volta para a linha do tempo');
    ok(erros.length === 0, 'sem erros de JavaScript' + (erros.length ? ': ' + erros[0] : ''));
  } finally {
    await b.close();
  }
  process.exit(falhas ? 1 : 0);
})();
