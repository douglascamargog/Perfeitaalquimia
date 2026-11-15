// Teste automático (Playwright). Rodar todos: bash tests/rodar.sh  ·  só este: node tests/teste_diretoria.js
// Perfil Diretoria: vê todas as áreas, não altera nada (só aprova), não entra em Configurações/Usuários/Atualização;
// diretor que também vende trabalha só os clientes dele; avisos da Diretoria (produção, estoque, desenvolvimento, meta, problemas).
const { chromium } = require('playwright');
const { execSync } = require('child_process');
const BASE = 'http://127.0.0.1:8000';
let falhas = 0; const ok = (c, m) => { console.log((c ? 'OK   ' : 'FALHA') + ' ' + m); if (!c) falhas++; };
const sql = (q) => execSync(`mysql perfeita --default-character-set=utf8mb4 -N -e "${q.replace(/"/g, '\\"')}"`).toString().trim();

(async () => {
  const b = await chromium.launch();
  const erros = [];
  const como = async (email) => {
    const p = await (await b.newContext({ viewport: { width: 1360, height: 900 } })).newPage();
    p.on('pageerror', (e) => erros.push(e.message + ' @ ' + p.url())); await p.route(/open-meteo|jsdelivr/, (r) => r.fulfill({ body: '{}' }));
    await p.goto(BASE + '/entrar'); await p.fill('#email', email); await p.fill('#senha', 'Demo@2026'); await p.click('button[type=submit]'); await p.waitForLoadState();
    if (await p.locator('[data-novidades-ok]').count()) { await p.click('[data-novidades-ok]'); await p.waitForLoadState(); }
    return { p, csrf: await p.evaluate(() => document.querySelector('meta[name=csrf]').content) };
  };
  const post = async (s, caminho, form = {}) => {
    const r = await s.p.request.post(BASE + caminho, { form: { _csrf: s.csrf, ...form } });
    return { status: r.status(), html: await r.text() };
  };
  const uid = (email) => sql(`select id from usuarios where email='${email}'`);
  const dir = uid('diretoria@demo.local'), vende = uid('diretoria.vende@demo.local'), adm = uid('admin@demo.local'), gestor = uid('gestor@demo.local');
  const avisos = (tipo, quem = dir) => Number(sql(`select count(*) from notificacoes where usuario_id=${quem} and tipo='${tipo}'`));

  // ---------- Vê tudo ----------
  const d = await como('diretoria@demo.local');
  for (const u of ['/carteira/lista', '/pedidos', '/aprovacao', '/producao', '/producao/ordens', '/marketing', '/marketing/inicio', '/deposito', '/compras', '/painel', '/desenvolvimento', '/cobranca', '/auditoria']) {
    const r = await d.p.goto(BASE + u);
    ok(r.status() === 200, `Diretoria abre ${u}`);
  }
  for (const u of ['/configuracoes', '/usuarios', '/sistema/atualizacao']) {
    const r = await d.p.goto(BASE + u);
    ok(r.status() === 403, `Diretoria não entra em ${u}`);
  }
  await d.p.goto(BASE + '/');
  ok(await d.p.locator('.menu-leitura').count() >= 5, 'menu mostra o olho (só leitura) nas áreas');

  // ---------- Não altera ----------
  const cli = sql('select id from clientes where responsavel_id is not null order by id limit 1');
  const r1 = await post(d, `/carteira/clientes/${cli}/contato`, { canal: 'Ligação', resumo: 'teste' });
  ok(r1.status === 403 && r1.html.includes('Diretoria acompanha e aprova'), 'não registra contato em cliente');
  ok((await post(d, '/producao/ordens', { produto_id: '1', quantidade: '10' })).status === 403, 'não cria ordem de produção');
  ok((await post(d, '/marketing/posts', { titulo: 'x' })).status === 403, 'não cria post');
  ok((await post(d, '/pedidos', { cliente_id: cli })).status === 403, 'não cria pedido');
  ok((await post(d, '/configuracoes', {})).status === 403, 'não salva configurações');

  // ---------- Aprova ----------
  const ped = sql("select id from pedidos where situacao='aguardando_aprovacao' order by id limit 1");
  await post(d, `/aprovacao/${ped}/aprovar`);
  ok(sql(`select situacao from pedidos where id=${ped}`) === 'aprovado', 'aprova pedido em Aprovação');
  const postAprov = sql("select p.id from mkt_posts p join mkt_post_etapas e on e.id=p.etapa_id where e.tipo='aprovacao' order by p.id limit 1");
  const rp = await d.p.request.post(BASE + `/marketing/posts/${postAprov}/aprovar`, { headers: { 'X-CSRF-Token': d.csrf } });
  ok(rp.status() === 200 && sql(`select aprovado_por from mkt_posts where id=${postAprov}`) === dir, 'aprova post do Marketing');
  // compra enviada para a Diretoria decidir
  const compra = sql("select id from compras where situacao='aprovacao' order by id limit 1");
  sql(`update compras set aprovador_id=${dir} where id=${compra}`);
  await post(d, `/compras/${compra}/decidir`, { decisao: 'aprovado' });
  ok(sql(`select situacao from compras where id=${compra}`) === 'aprovado', 'decide orçamento de compra enviado a ela');
  ok(sql(`select count(*) from usuarios u join perfis p on p.id=u.perfil_id where p.slug='diretoria'`) === '2', 'dois diretores na demonstração');

  // ---------- Diretor que também vende ----------
  const v = await como('diretoria.vende@demo.local');
  const meu = sql(`select id from clientes where responsavel_id=${vende} order by id limit 1`);
  const outro = sql(`select id from clientes where responsavel_id<>${vende} order by id limit 1`);
  const rc = await post(v, `/carteira/clientes/${meu}/contato`, { canal: 'Ligação', resumo: 'Ligação do diretor' });
  ok(rc.status !== 403 && sql(`select count(*) from cliente_eventos where cliente_id=${meu} and descricao like '%Ligação do diretor%'`) === '1', 'diretor que vende registra contato no cliente DELE');
  ok((await post(v, `/carteira/clientes/${outro}/contato`, { canal: 'Ligação', resumo: 'Não pode' })).status === 403, '...mas não no cliente de outra pessoa');
  const p1 = sql('select p.id from produtos p join produto_precos pp on pp.produto_id=p.id and pp.tabela_preco_id=1 where p.ativo=1 and p.visivel_catalogo=1 order by p.id limit 1');
  ok((await post(v, '/orcamentos', { cliente_id: outro, [`qtd[${p1}]`]: '1' })).status === 403, 'não faz orçamento para cliente de outra pessoa');
  await post(v, '/orcamentos', { cliente_id: meu, [`qtd[${p1}]`]: '2' });
  ok(sql(`select count(*) from orcamentos where cliente_id=${meu} and vendedor_id=${vende}`) === '1', 'faz orçamento para o cliente dele');
  ok((await post(v, '/producao/ordens', { produto_id: p1, quantidade: '5' })).status === 403, 'continua sem mexer na Produção');

  // ---------- Avisos da Diretoria ----------
  const prod = await como('producao@demo.local');
  // ordem criada
  const prodComFicha = sql('select produto_id from ficha_tecnica group by produto_id order by produto_id limit 1');
  await post(prod, '/producao/ordens', { produto_id: prodComFicha, quantidade: '5' });
  const op = sql(`select max(id) from ordens_producao where produto_id=${prodComFicha}`);
  ok(avisos('diretoria_op_criada') === 1 && avisos('diretoria_op_criada', adm) === 1, 'ordem criada: Diretoria e Admin avisados');
  ok(avisos('diretoria_op_criada', gestor) === 0, '...o Gestor (comercial) não recebe');
  // estoque cruzando o mínimo na conclusão da ordem (matéria-prima da ficha)
  const mp = sql(`select materia_prima_id from ficha_tecnica where produto_id=${prodComFicha} order by materia_prima_id limit 1`);
  const porUn = Number(sql(`select quantidade from ficha_tecnica where produto_id=${prodComFicha} and materia_prima_id=${mp}`));
  sql(`update materias_primas set estoque=${porUn * 5 * 3}, estoque_minimo=${porUn * 5 * 2.5} where id=${mp}`);
  sql(`update materias_primas set estoque=estoque+100000 where id in (select materia_prima_id from ficha_tecnica where produto_id=${prodComFicha}) and id<>${mp}`);
  await post(prod, `/producao/ordens/${op}/concluir`, { quantidade: '5' });
  ok(sql(`select situacao from ordens_producao where id=${op}`) === 'concluida', 'ordem concluída');
  ok(avisos('diretoria_op_concluida') === 1, 'ordem concluída: Diretoria avisada');
  ok(avisos('diretoria_estoque') === 1 && sql(`select texto from notificacoes where usuario_id=${dir} and tipo='diretoria_estoque'`).includes('mínimo'), 'matéria-prima abaixo do mínimo: 1 aviso');
  // desligar o evento corta o aviso
  sql("update configuracoes set valor='0' where chave='diretoria.aviso_op_criada'");
  await post(prod, '/producao/ordens', { produto_id: prodComFicha, quantidade: '3' });
  ok(avisos('diretoria_op_criada') === 1, 'evento desligado em Configurações › Diretoria: sem aviso');
  // problema: pedido recusado
  const f = await como('financeiro@demo.local');
  const ped2 = sql("select id from pedidos where situacao='aguardando_aprovacao' order by id limit 1");
  await post(f, `/aprovacao/${ped2}/recusar`, { motivo: 'Crédito estourado' });
  ok(avisos('diretoria_problemas') === 1, 'pedido recusado: Diretoria avisada (problema)');
  // meta batida
  const t = await como('televendas1@demo.local');
  const mes = new Date().toLocaleDateString('sv').slice(0, 7);
  const t1 = uid('televendas1@demo.local');
  sql(`insert into meu_mes (usuario_id, mes, meta, dias_semana, folgas) values (${t1}, '${mes}', 1000, '1,2,3,4,5', '[]') on duplicate key update meta=1000`);
  sql(`delete from vendas_registradas where usuario_id=${t1} and data like '${mes}-%'`);
  const hoje = new Date().toLocaleDateString('sv');
  await post(t, '/meu-mes/vendas', { data: hoje, valor: '600,00' });
  ok(avisos('diretoria_meta') === 0, 'abaixo da meta: sem aviso');
  await post(t, '/meu-mes/vendas', { data: hoje, valor: '500,00' });
  ok(avisos('diretoria_meta') === 1, 'bateu a meta: Diretoria avisada');
  await post(t, '/meu-mes/vendas', { data: hoje, valor: '50,00' });
  ok(avisos('diretoria_meta') === 1, '...uma vez só');
  // sem enxurrada: pedido novo para aprovar não vai para a Diretoria
  ok(avisos('pedido_aprovar') === 0, 'pedido novo para aprovar NÃO avisa a Diretoria');

  ok(erros.length === 0, 'nenhum erro de JavaScript' + (erros.length ? ': ' + erros.join(' / ') : ''));
  await b.close();
  console.log(falhas ? `\n${falhas} FALHA(S)` : '\nTudo certo.');
  process.exit(falhas ? 1 : 0);
})();
