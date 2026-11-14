// Teste automático (Playwright). Rodar todos: bash tests/rodar.sh  ·  só este: node tests/teste_compras.js
// Orçamentos e compras da Qualidade: sugestão (matéria-prima abaixo do mínimo), cotações com anexo, envio para a pessoa
// escolhida, só ela decide, pedir outra cotação, aprovar, comprar, receber no Depósito (entra no estoque), item avulso e acessos.
const { chromium } = require('playwright');
const { execSync } = require('child_process');
const BASE = 'http://127.0.0.1:8000';
let falhas = 0; const ok = (c, m) => { console.log((c ? 'OK   ' : 'FALHA') + ' ' + m); if (!c) falhas++; };
const sql = (q) => execSync(`mysql perfeita --default-character-set=utf8mb4 -N -e "${q.replace(/"/g, '\\"')}"`).toString().trim();
const PDF = Buffer.from('%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n');

(async () => {
  const b = await chromium.launch();
  const erros = [];
  const como = async (email) => {
    const p = await (await b.newContext({ viewport: { width: 1300, height: 900 } })).newPage();
    p.on('pageerror', (e) => erros.push(e.message)); await p.route(/open-meteo|jsdelivr/, (r) => r.fulfill({ body: '{}' }));
    await p.goto(BASE + '/entrar'); await p.fill('#email', email); await p.fill('#senha', 'Demo@2026'); await p.click('button[type=submit]'); await p.waitForLoadState();
    if (await p.locator('[data-novidades-ok]').count()) { await p.click('[data-novidades-ok]'); await p.waitForLoadState(); }
    const csrf = await p.evaluate(() => document.querySelector('meta[name=csrf]').content);
    return { p, csrf };
  };
  const post = async (s, caminho, form = {}, multipart = null) => {
    const r = multipart ? await s.p.request.post(BASE + caminho, { multipart: { _csrf: s.csrf, ...form, ...multipart } })
      : await s.p.request.post(BASE + caminho, { form: { _csrf: s.csrf, ...form } });
    const html = await r.text();
    return [...html.matchAll(/class="aviso[- ][a-z ]*"[^>]*>([^<]*)</g)].map((m) => m[1]).join(' | ');
  };
  const sit = (id) => sql(`select situacao from compras where id=${id}`);
  const uid = (email) => sql(`select id from usuarios where email='${email}'`);

  // ---------- Sugestão: matéria-prima abaixo do mínimo ----------
  const mp = sql("select id from materias_primas where tipo='materia_prima' and ativo=1 and id not in (select coalesce(materia_prima_id,0) from compras) order by id limit 1");
  sql(`update materias_primas set estoque=2, estoque_minimo=50, custo_unitario=1 where id=${mp}`);
  const q = await como('qualidade@demo.local');
  await q.p.goto(BASE + '/compras');
  ok(await q.p.locator('.menu-lateral, nav').first().count() >= 0 && await q.p.locator(`[data-sugestao-mp="${mp}"]`).count() === 1, 'sugestão: matéria-prima abaixo do mínimo');
  await q.p.click(`[data-sugestao-mp="${mp}"]`);
  await q.p.waitForLoadState();
  ok(await q.p.inputValue('select[name=materia_prima_id]') === mp, 'formulário já vem com a matéria-prima');
  await Promise.all([q.p.waitForNavigation(), q.p.click('.cp-form button[type=submit]')]);
  const id = sql(`select max(id) from compras where materia_prima_id=${mp}`);
  ok(!!id && sit(id) === 'rascunho', 'orçamento criado');

  // ---------- Cotações ----------
  let msg = await post(q, `/compras/${id}/cotacao`, { fornecedor: 'Fornecedor A', valor_unitario: '2,00', frete: '10', prazo_dias: '5' }, { arquivo: { name: 'orc.pdf', mimeType: 'application/pdf', buffer: PDF } });
  await post(q, `/compras/${id}/cotacao`, { fornecedor: 'Fornecedor B', valor_unitario: '1,50', frete: '0', prazo_dias: '9' });
  const [cotA, cotB] = sql(`select id from compra_cotacoes where compra_id=${id} order by fornecedor`).split('\n');
  ok(sql(`select count(*) from compra_cotacoes where compra_id=${id}`) === '2' && sql(`select arquivo is not null from compra_cotacoes where id=${cotA}`) === '1', 'duas cotações, uma com anexo');

  // ---------- Enviar para a pessoa escolhida ----------
  const gestor = uid('gestor@demo.local');
  msg = await post(q, `/compras/${id}/enviar`, { cotacao: cotA, aprovador: gestor, motivo_escolha: '' });
  ok(msg.includes('mais barata') && sit(id) === 'rascunho', 'escolher a mais cara exige o motivo');
  await post(q, `/compras/${id}/enviar`, { cotacao: cotB, aprovador: gestor });
  ok(sit(id) === 'aprovacao' && sql(`select aprovador_id from compras where id=${id}`) === gestor, 'enviado para a pessoa escolhida');
  ok(sql(`select count(*) from notificacoes where tipo='compra_aprovar' and usuario_id=${gestor} and link='/compras/${id}'`) === '1', 'quem aprova recebe aviso no sino');

  // ---------- Só a pessoa escolhida decide ----------
  const f = await como('financeiro@demo.local');
  msg = await post(f, `/compras/${id}/decidir`, { decisao: 'aprovado' });
  ok(msg.includes('foi enviado para') && sit(id) === 'aprovacao', 'outra pessoa não decide');
  const g = await como('gestor@demo.local');
  await g.p.goto(BASE + `/compras/${id}`);
  ok(await g.p.locator('[data-decidir]').count() === 1, 'a pessoa escolhida vê os botões de decisão');
  msg = await post(g, `/compras/${id}/decidir`, { decisao: 'refazer', comentario: '' });
  ok(msg.includes('motivo') && sit(id) === 'aprovacao', 'pedir outra cotação exige comentário');
  await post(g, `/compras/${id}/decidir`, { decisao: 'refazer', comentario: 'Cote também com o fornecedor C' });
  ok(sit(id) === 'refazer' && sql(`select count(*) from notificacoes where tipo='compra_decidida' and usuario_id=${uid('qualidade@demo.local')}`) !== '0', '"pedir outra cotação" volta para a Qualidade (avisada)');
  await post(q, `/compras/${id}/enviar`, { cotacao: cotB, aprovador: gestor });
  await post(g, `/compras/${id}/decidir`, { decisao: 'aprovado' });
  ok(sit(id) === 'aprovado', 'aprovado');

  // ---------- Comprar e receber ----------
  await post(q, `/compras/${id}/comprar`, { previsto: '2030-01-10' });
  ok(sit(id) === 'comprado', '"Comprei" registra a compra');
  const d = await como('deposito@demo.local');
  await d.p.goto(BASE + '/deposito/recebimento');
  ok(await d.p.locator(`tr[data-compra="${id}"]`).count() === 1, 'aparece no Recebimento do Depósito');
  const antes = Number(sql(`select estoque from materias_primas where id=${mp}`));
  await post(d, `/deposito/recebimento/${id}`, { quantidade: sql(`select quantidade from compras where id=${id}`).replace('.', ','), observacao: 'NF 123' });
  const qtd = Number(sql(`select quantidade from compras where id=${id}`));
  ok(sit(id) === 'recebido' && Math.abs(Number(sql(`select estoque from materias_primas where id=${mp}`)) - (antes + qtd)) < 0.001, 'chegou: entrou no estoque da matéria-prima');
  ok(sql(`select custo_unitario from materias_primas where id=${mp}`) === '1.5000', 'custo da matéria-prima atualizado com a cotação');

  // ---------- Item avulso ----------
  await post(q, '/compras', { tipo: 'avulso', titulo: 'Fita adesiva', quantidade: '20', unidade: 'rolo' });
  const av = sql("select max(id) from compras where titulo='Fita adesiva'");
  await post(q, `/compras/${av}/cotacao`, { fornecedor: 'Papelaria', valor_unitario: '5' });
  const cotAv = sql(`select id from compra_cotacoes where compra_id=${av}`);
  await post(q, `/compras/${av}/enviar`, { cotacao: cotAv, aprovador: uid('financeiro@demo.local') });
  await post(f, `/compras/${av}/decidir`, { decisao: 'aprovado' });
  await post(q, `/compras/${av}/comprar`, {});
  const movAntes = sql('select count(*) from mp_movimentos');
  await post(d, `/deposito/recebimento/${av}`, { quantidade: '20' });
  ok(sit(av) === 'recebido' && sql('select count(*) from mp_movimentos') === movAntes, 'item avulso chega sem mexer no estoque');

  // ---------- Acessos ----------
  const anexo = await q.p.request.get(BASE + `/compras/anexo/${cotA}`, { maxRedirects: 0 });
  ok(anexo.status() === 200 && anexo.headers()['content-type'] === 'application/pdf', 'Qualidade abre o anexo da cotação');
  const v = await como('televendas1@demo.local');
  ok((await v.p.request.get(BASE + `/compras/anexo/${cotA}`, { maxRedirects: 0 })).status() !== 200, 'vendedora não abre o anexo');
  await post(v, '/compras', { tipo: 'avulso', titulo: 'Teste proibido', quantidade: '1' });
  ok(sql("select count(*) from compras where titulo='Teste proibido'") === '0', 'vendedora não cria orçamento');
  await v.p.goto(BASE + '/compras');
  ok(await v.p.locator('[data-compras]').count() === 1 && await v.p.locator('tr[data-compra]').count() === 0, 'vendedora só vê os orçamentos para ela aprovar (nenhum)');

  ok(erros.length === 0, 'nenhum erro de JavaScript' + (erros.length ? ': ' + erros.join(' / ') : ''));
  await b.close();
  console.log(falhas ? `\n${falhas} FALHA(S)` : '\nTudo certo.');
  process.exit(falhas ? 1 : 0);
})();
