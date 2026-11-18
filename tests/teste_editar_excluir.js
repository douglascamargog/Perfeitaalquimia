// Teste automático (Playwright). Rodar todos: bash tests/rodar.sh  ·  só este: bash tests/rodar.sh editar_excluir
// V15.7: excluir/restaurar lojas e juntar repetidas (Gestor), corrigir/apagar registro da linha do tempo (24 h),
// editar/excluir tarefa da Agenda, excluir mensagem pronta e post do Marketing, cancelar roteiro.
const { chromium } = require('playwright');
const { execSync } = require('child_process');
const BASE = 'http://127.0.0.1:8000';
let falhas = 0; const ok = (c, m) => { console.log((c ? 'OK   ' : 'FALHA') + ' ' + m); if (!c) falhas++; };
const sql = (q) => execSync(`mysql perfeita --default-character-set=utf8mb4 -N -e "${q.replace(/"/g, '\\"')}"`).toString().trim();

(async () => {
  const tv1 = sql("select id from usuarios where email='televendas1@demo.local'");
  const loja = (nome, extra = {}) => {
    const cols = Object.keys(extra), vals = Object.values(extra).map((v) => `'${v}'`);
    sql(`insert into clientes (tipo, razao_social, nome_fantasia, cidade, uf, responsavel_id, etapa_id${cols.map((c) => ', ' + c).join('')})
         values ('cliente', '${nome} Ltda', '${nome}', 'Canoas', 'RS', ${tv1}, (select min(id) from funil_etapas)${vals.map((v) => ', ' + v).join('')})`);
    return sql(`select max(id) from clientes where nome_fantasia='${nome}'`);
  };
  const fica = loja('Loja Que Fica', { telefone: '51999990000' });
  const repetida = loja('Loja Repetida', { cnpj: '11222333000181', email: 'repetida@loja.com', telefone: '51888880000' });
  const apagar = loja('Loja Fechou');
  const comPedido = loja('Loja Com Pedido');
  sql(`insert into pedidos (uuid, cliente_id, vendedor_id, tabela_preco_id, forma_pagamento_id, subtotal, total, situacao) values (UUID(), ${comPedido}, ${tv1}, 1, 1, 500, 500, 'aguardando_aprovacao')`);
  sql(`insert into pedidos (uuid, cliente_id, vendedor_id, tabela_preco_id, forma_pagamento_id, subtotal, total, situacao, faturado_em) values (UUID(), ${repetida}, ${tv1}, 1, 1, 700, 700, 'faturado', NOW() - INTERVAL 5 DAY)`);
  sql(`insert into cliente_eventos (cliente_id, usuario_id, tipo, canal, descricao, ocorrido_em) values (${repetida}, ${tv1}, 'nota', null, 'Anotação da loja repetida', NOW())`);

  const b = await chromium.launch(); const erros = [];
  const entrar = async (email) => {
    const p = await (await b.newContext()).newPage(); p.on('pageerror', (e) => erros.push(e.message)); p.on('dialog', (d) => d.accept());
    await p.goto(BASE + '/entrar'); await p.fill('#email', email); await p.fill('#senha', 'Demo@2026'); await p.click('button[type=submit]'); await p.waitForLoadState();
    return p;
  };
  const aviso = (p) => p.locator('.aviso').first().innerText();
  try {
    // ---------------- Lojas: excluir, restaurar, juntar
    const v = await entrar('televendas1@demo.local');
    await v.goto(BASE + `/carteira/clientes/${apagar}/editar`);
    ok(await v.locator('[data-cuidado]').count() === 0, 'vendedora não vê "Juntar ou excluir"');
    const r403 = await v.request.post(BASE + `/carteira/clientes/${apagar}/excluir`, { form: { motivo: 'x', _csrf: await v.locator('meta[name=csrf]').getAttribute('content') } });
    ok(r403.status() === 403 && sql(`select excluido_em is null from clientes where id=${apagar}`) === '1', 'vendedora não consegue excluir loja (403)');

    const g = await entrar('gestor@demo.local');
    await g.goto(BASE + `/carteira/clientes/${comPedido}/editar`);
    await g.fill('[data-form-excluir] input[name=motivo]', 'teste');
    await Promise.all([g.waitForNavigation(), g.click('[data-form-excluir] button')]);
    ok((await aviso(g)).includes('pedido(s) em andamento') && sql(`select excluido_em is null from clientes where id=${comPedido}`) === '1', 'loja com pedido em andamento não é excluída (explica por quê)');

    await g.goto(BASE + `/carteira/clientes/${apagar}/editar`);
    await g.fill('[data-form-excluir] input[name=motivo]', 'Loja fechou as portas');
    await Promise.all([g.waitForNavigation(), g.click('[data-form-excluir] button')]);
    ok(sql(`select excluido_em is not null from clientes where id=${apagar}`) === '1', 'gestor exclui a loja (com motivo)');
    await g.goto(BASE + '/carteira/lista?q=Loja+Fechou');
    ok(!(await g.locator('main').innerText()).includes('Loja Fechou'), 'loja excluída sai da Carteira');
    await g.goto(BASE + '/carteira/excluidas');
    ok((await g.locator(`[data-excluida="${apagar}"]`).innerText()).includes('Loja fechou as portas'), 'aparece em Lojas excluídas com o motivo');
    await Promise.all([g.waitForNavigation(), g.click(`[data-excluida="${apagar}"] button`)]);
    ok(sql(`select excluido_em is null from clientes where id=${apagar}`) === '1', '"Restaurar" traz a loja de volta');

    await g.goto(BASE + `/carteira/clientes/${fica}/editar`);
    await g.fill('[data-form-juntar] input[name=origem_numero]', repetida);
    await Promise.all([g.waitForNavigation(), g.click('[data-form-juntar] button')]);
    ok(g.url().endsWith(`/carteira/clientes/${fica}`) && (await aviso(g)).includes('Lojas juntadas'), 'juntar: a loja repetida vem para a que fica');
    ok(sql(`select count(*) from pedidos where cliente_id=${fica}`) === '1' && sql(`select count(*) from cliente_eventos where cliente_id=${fica} and descricao='Anotação da loja repetida'`) === '1',
      'pedidos e linha do tempo da repetida passam para a que fica');
    ok(sql(`select concat(cnpj, '|', email, '|', telefone) from clientes where id=${fica}`) === '11222333000181|repetida@loja.com|51999990000',
      'campos vazios completados com os da repetida (o que já existia continua)');
    ok(sql(`select excluido_em is not null from clientes where id=${repetida}`) === '1' && sql(`select ultima_compra_em is not null from clientes where id=${fica}`) === '1',
      'a repetida sai da Carteira; a última compra é recalculada');

    // ---------------- Linha do tempo: corrigir e apagar (24 h)
    sql(`insert into cliente_eventos (cliente_id, usuario_id, tipo, canal, descricao, ocorrido_em, registrado_em) values (${fica}, ${tv1}, 'nota', null, 'Texto com erro de digitaçao', NOW(), NOW())`);
    const evNovo = sql('select max(id) from cliente_eventos');
    sql(`insert into cliente_eventos (cliente_id, usuario_id, tipo, canal, descricao, ocorrido_em, registrado_em) values (${fica}, ${tv1}, 'nota', null, 'Anotação de anteontem', NOW() - INTERVAL 2 DAY, NOW() - INTERVAL 2 DAY)`);
    const evVelho = sql('select max(id) from cliente_eventos');
    await v.goto(BASE + `/carteira/clientes/${fica}`);
    ok(await v.locator(`[data-corrigir-evento="${evNovo}"]`).count() === 1 && await v.locator(`[data-corrigir-evento="${evVelho}"]`).count() === 0,
      'quem registrou pode corrigir o registro de hoje; o de 2 dias atrás não');
    await v.locator(`[data-corrigir-evento="${evNovo}"] summary`).click();
    await v.fill(`[data-corrigir-evento="${evNovo}"] textarea`, 'Texto corrigido');
    await Promise.all([v.waitForNavigation(), v.locator(`[data-corrigir-evento="${evNovo}"] form`).first().locator('button').click()]);
    ok(sql(`select concat(descricao, '|', editado_em is not null) from cliente_eventos where id=${evNovo}`) === 'Texto corrigido|1' && (await v.locator('main').innerText()).includes('corrigido'),
      'registro corrigido (aparece "corrigido" na linha do tempo)');
    await v.locator(`[data-corrigir-evento="${evNovo}"] summary`).click();
    await Promise.all([v.waitForNavigation(), v.locator(`[data-corrigir-evento="${evNovo}"] form`).nth(1).locator('button').click()]);
    ok(sql(`select count(*) from cliente_eventos where id=${evNovo}`) === '0' && sql(`select count(*) from auditoria where entidade='cliente_evento' and acao='apagar' and antes_json like '%Texto corrigido%'`) === '1',
      'registro apagado; o texto fica guardado na auditoria');
    const tok = await v.locator('meta[name=csrf]').getAttribute('content');
    await v.request.post(BASE + `/carteira/clientes/${fica}/eventos/${evVelho}/apagar`, { form: { _csrf: tok } });
    ok(sql(`select count(*) from cliente_eventos where id=${evVelho}`) === '1', 'registro de mais de 24 h não é apagado pela vendedora');

    // ---------------- Agenda: editar e excluir
    sql(`insert into tarefas (cliente_id, usuario_id, titulo, tipo, vence_em) values (${fica}, ${tv1}, 'Ligar pra loja', 'retorno', CURDATE())`);
    const tar = sql('select max(id) from tarefas');
    await v.goto(BASE + '/agenda');
    const cartao = v.locator(`[data-tarefa="${tar}"]`).first();
    await cartao.locator('[data-editar-tarefa] summary').click();
    await cartao.locator('[data-editar-tarefa] input[name=titulo]').fill('Ligar para a loja sobre o pedido');
    await Promise.all([v.waitForNavigation(), cartao.locator('[data-editar-tarefa] form').first().locator('button').click()]);
    ok(sql(`select titulo from tarefas where id=${tar}`) === 'Ligar para a loja sobre o pedido', 'Agenda: editar o assunto da tarefa');
    const cartao2 = v.locator(`[data-tarefa="${tar}"]`).first();
    await cartao2.locator('[data-editar-tarefa] summary').click();
    await Promise.all([v.waitForNavigation(), cartao2.locator('[data-editar-tarefa] form').nth(1).locator('button').click()]);
    ok(sql(`select count(*) from tarefas where id=${tar}`) === '0', 'Agenda: excluir a tarefa criada por engano');

    // ---------------- Mensagem pronta e post
    const a = await entrar('admin@demo.local');
    sql("insert into mensagens_prontas (titulo, categoria, texto, ativo) values ('Mensagem para excluir', 'Prospecção', 'Olá {nome}', 1)");
    const msg = sql("select max(id) from mensagens_prontas");
    await a.goto(BASE + '/mensagens/' + msg);
    await Promise.all([a.waitForNavigation(), a.click('[data-excluir-mensagem]')]);
    ok(sql(`select count(*) from mensagens_prontas where id=${msg}`) === '0', 'mensagem pronta excluída');
    const etapa = sql("select id from mkt_post_etapas where tipo='rascunho' limit 1");
    sql(`insert into mkt_posts (titulo, formato, data_publicacao, etapa_id, responsavel_id) values ('Post de teste para excluir', 'feed', CURDATE(), ${etapa}, ${tv1})`);
    const post = sql('select max(id) from mkt_posts');
    const r = await a.request.post(BASE + `/marketing/posts/${post}/excluir`, { form: { _csrf: await a.locator('meta[name=csrf]').getAttribute('content') } });
    ok(r.ok() && sql(`select count(*) from mkt_posts where id=${post}`) === '0', 'post do Marketing (não publicado) excluído');

    // ---------------- Roteiro: cancelar
    const ext = sql("select id from usuarios where email='externo1@demo.local'");
    sql(`insert into roteiros (usuario_id, nome, data_inicio, data_fim) values (${ext}, 'Roteiro para cancelar', CURDATE(), CURDATE())`);
    const rot = sql('select max(id) from roteiros');
    const e = await entrar('externo1@demo.local');
    await e.goto(BASE + '/roteiros/' + rot);
    await Promise.all([e.waitForNavigation(), e.click('[data-cancelar-roteiro]')]);
    ok(sql(`select situacao from roteiros where id=${rot}`) === 'cancelado', 'roteiro cancelado pelo botão');
    ok(erros.length === 0, 'sem erros de JavaScript' + (erros.length ? ': ' + erros[0] : ''));
  } finally {
    await b.close();
  }
  process.exit(falhas ? 1 : 0);
})();
