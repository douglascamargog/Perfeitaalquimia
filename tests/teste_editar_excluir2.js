// Teste automático (Playwright). Rodar todos: bash tests/rodar.sh  ·  só este: bash tests/rodar.sh editar_excluir2
// V15.8: corrigir pedido que espera aprovação (o novo substitui o antigo), editar pedido ao Marketing (quem pediu),
// corrigir comunicado enviado e editar ordem de produção planejada (mudar a quantidade tira a aprovação técnica).
const { chromium } = require('playwright');
const { execSync } = require('child_process');
const BASE = 'http://127.0.0.1:8000';
let falhas = 0; const ok = (c, m) => { console.log((c ? 'OK   ' : 'FALHA') + ' ' + m); if (!c) falhas++; };
const sql = (q) => execSync(`mysql perfeita --default-character-set=utf8mb4 -N -e "${q.replace(/"/g, '\\"')}"`).toString().trim();

(async () => {
  const tv1 = sql("select id from usuarios where email='televendas1@demo.local'");
  sql(`insert into clientes (tipo, razao_social, nome_fantasia, cidade, uf, responsavel_id, etapa_id) values ('cliente', 'Loja Pedido Corrigido Ltda', 'Loja Pedido Corrigido', 'Canoas', 'RS', ${tv1}, (select min(id) from funil_etapas))`);
  const cli = sql("select max(id) from clientes where nome_fantasia='Loja Pedido Corrigido'");
  sql("insert into produtos (slug, codigo, nome, ficha_json, ativo, visivel_catalogo) values ('produto-corrigir', 'PC01', 'Produto Corrigir', '{}', 1, 1)");
  const prod = sql("select id from produtos where slug='produto-corrigir'");
  sql(`insert into produto_precos (produto_id, tabela_preco_id, preco) select ${prod}, id, 100 from tabelas_preco`);
  sql(`insert into pedidos (uuid, cliente_id, vendedor_id, tabela_preco_id, forma_pagamento_id, subtotal, total, situacao) values (UUID(), ${cli}, ${tv1}, 1, 1, 1000, 1000, 'aguardando_aprovacao')`);
  const antigo = sql('select max(id) from pedidos');
  sql(`insert into pedido_itens (pedido_id, produto_id, nome, quantidade, preco_unitario, total) values (${antigo}, ${prod}, 'Produto Corrigir', 10, 100, 1000)`);

  const b = await chromium.launch(); const erros = [];
  const entrar = async (email) => {
    const p = await (await b.newContext()).newPage(); p.on('pageerror', (e) => erros.push(e.message)); p.on('dialog', (d) => d.accept());
    await p.goto(BASE + '/entrar'); await p.fill('#email', email); await p.fill('#senha', 'Demo@2026'); await p.click('button[type=submit]'); await p.waitForLoadState();
    return p;
  };
  try {
    // ---------------- Corrigir pedido
    const v = await entrar('televendas1@demo.local');
    await v.goto(BASE + '/pedidos/' + antigo);
    await Promise.all([v.waitForNavigation(), v.click('[data-corrigir-pedido]')]);
    ok(await v.locator('[data-corrigindo]').count() === 1 && await v.locator('input[name=corrigir_id]').inputValue() === antigo, 'Corrigir pedido abre a tela do pedido com o aviso e os itens do antigo');
    const r = await v.request.post(BASE + '/pedidos', { form: { _csrf: await v.locator('meta[name=csrf]').getAttribute('content'), cliente_id: cli, corrigir_id: antigo,
      uuid: await v.locator('input[name=uuid]').inputValue(), [`qtd[${prod}]`]: '15', pagamento: '1:1' } });
    ok(r.ok(), 'pedido corrigido enviado');
    const novo = sql(`select max(id) from pedidos where cliente_id=${cli}`);
    ok(novo !== antigo && sql(`select situacao from pedidos where id=${antigo}`) === 'cancelado' && sql(`select situacao from pedidos where id=${novo}`) === 'aguardando_aprovacao',
      'o pedido novo substitui o antigo (antigo cancelado, novo aguardando aprovação)');
    ok(sql(`select count(*) from pedido_historico where pedido_id=${antigo} and texto like '%substituído pelo pedido #${novo}%'`) === '1'
      && sql(`select sum(quantidade) from pedido_itens where pedido_id=${novo}`) === '15', 'histórico mostra a substituição; o novo tem a quantidade corrigida');

    // ---------------- Pedido ao Marketing: quem pediu edita
    const tipo = sql('select id from mkt_tipos where ativo = 1 order by id limit 1');
    await v.goto(BASE + '/marketing/pedir');
    const tok = await v.locator('meta[name=csrf]').getAttribute('content');
    await v.request.post(BASE + '/marketing/tarefas', { form: { _csrf: tok, pedido: '1', titulo: 'Arte para promoção', descricao: 'Detalhes', tipo_id: tipo } });
    const tar = sql("select max(id) from mkt_tarefas where titulo='Arte para promoção'");
    await v.goto(BASE + '/marketing/tarefas/' + tar);
    ok(await v.locator('[data-editar-tarefa-mkt]').count() === 1, 'quem pediu vê "Editar o meu pedido" enquanto está A fazer');
    await v.click('[data-editar-tarefa-mkt] summary');
    await v.fill('[data-editar-tarefa-mkt] input[name=titulo]', 'Arte para a promoção de dezembro');
    await Promise.all([v.waitForNavigation(), v.click('[data-editar-tarefa-mkt] button')]);
    ok(sql(`select titulo from mkt_tarefas where id=${tar}`) === 'Arte para a promoção de dezembro', 'pedido ao Marketing editado por quem pediu');
    sql(`update mkt_tarefas set situacao='fazendo' where id=${tar}`);
    await v.goto(BASE + '/marketing/tarefas/' + tar);
    ok(await v.locator('[data-editar-tarefa-mkt]').count() === 0, 'depois que o Marketing começa, quem pediu não edita mais (manda comentário)');

    // ---------------- Comunicado: corrigir
    const a = await entrar('admin@demo.local');
    await a.goto(BASE + '/comunicados');
    await a.request.post(BASE + '/comunicados', { form: { _csrf: await a.locator('meta[name=csrf]').getAttribute('content'), titulo: 'Reunião sexta', texto: 'Reunião às 9h', toda_empresa: '1' } });
    const com = sql("select max(id) from comunicados where titulo='Reunião sexta'");
    await a.goto(BASE + '/comunicados');
    await a.click(`[data-corrigir-comunicado="${com}"] summary`);
    await a.fill(`[data-corrigir-comunicado="${com}"] textarea`, 'Reunião às 10h (horário corrigido)');
    await Promise.all([a.waitForNavigation(), a.click(`[data-corrigir-comunicado="${com}"] button`)]);
    ok(sql(`select texto from comunicados where id=${com}`) === 'Reunião às 10h (horário corrigido)', 'comunicado corrigido depois de enviado');

    // ---------------- Ordem de produção planejada: editar
    sql(`insert into ordens_producao (produto_id, quantidade, situacao, aprovada_em) values (${prod}, 100, 'planejada', NOW())`);
    const op = sql('select max(id) from ordens_producao');
    await a.goto(BASE + '/producao/ordens/' + op);
    if (await a.locator('[data-editar-ordem]').count()) {
      await a.click('[data-editar-ordem] summary');
      await a.fill('[data-editar-ordem] input[name=quantidade]', '150');
      await Promise.all([a.waitForNavigation(), a.click('[data-editar-ordem] button')]);
    }
    ok(sql(`select concat(quantidade, '|', aprovada_em is null) from ordens_producao where id=${op}`) === '150|1', 'ordem planejada editada; mudar a quantidade tira a aprovação técnica');
    ok(erros.length === 0, 'sem erros de JavaScript' + (erros.length ? ': ' + erros[0] : ''));
  } finally {
    await b.close();
  }
  process.exit(falhas ? 1 : 0);
})();
