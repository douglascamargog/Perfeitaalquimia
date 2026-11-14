// Teste automático (Playwright). Rodar todos: bash tests/rodar.sh  ·  só este: node tests/teste_acompanhamento.js
// Acompanhamento completo: meta, WhatsApp, retornos, abas da vendedora, visão Cobrança e quem vê o quê.
const { chromium } = require('playwright');
const { execSync } = require('child_process');
const BASE = 'http://127.0.0.1:8000';
let falhas = 0; const ok = (c, m) => { console.log((c ? 'OK   ' : 'FALHA') + ' ' + m); if (!c) falhas++; };
const sql = (q) => execSync(`mysql perfeita --default-character-set=utf8mb4 -N -e "${q.replace(/"/g, '\\"')}"`).toString().trim();
async function entrar(p, email) {
  await p.goto(BASE + '/entrar'); await p.fill('#email', email); await p.fill('#senha', 'Demo@2026'); await p.click('button[type=submit]'); await p.waitForLoadState();
  if (await p.locator('[data-novidades-ok]').count()) { await p.click('[data-novidades-ok]'); await p.waitForLoadState(); }
}

(async () => {
  const b = await chromium.launch();
  const erros = [];
  const nova = async () => { const p = await (await b.newContext({ viewport: { width: 1300, height: 900 } })).newPage(); p.on('pageerror', e => erros.push(e.message)); await p.route(/open-meteo|jsdelivr/, r => r.fulfill({ body: '{}' })); return p; };
  const uid = (email) => sql(`select id from usuarios where email='${email}'`);
  const tv1 = uid('televendas1@demo.local'), fin = uid('financeiro@demo.local');
  const hoje = new Date(); const de = `${hoje.getFullYear()}-${String(hoje.getMonth() + 1).padStart(2, '0')}-01`; const mes = de.slice(0, 7);
  // Dados conhecidos para conferir as contas
  sql(`insert into metas (mes, tipo, alvo_id, valor) values ('${mes}', 'vendedor', ${tv1}, 10000) on duplicate key update valor = 10000`);
  const cli = sql(`select id from clientes where responsavel_id=${tv1} limit 1`);
  sql(`insert into cliente_eventos (cliente_id, usuario_id, tipo, descricao, ocorrido_em) values (${cli}, ${tv1}, 'whatsapp', 'Mensagem pronta: Teste A', NOW()), (${cli}, ${tv1}, 'whatsapp', 'Conversa', NOW())`);
  sql(`insert into tarefas (cliente_id, usuario_id, titulo, vence_em, concluida_em) values (${cli}, ${tv1}, 'no prazo', CURDATE(), NOW()), (${cli}, ${tv1}, 'atrasada', CURDATE() - INTERVAL 5 DAY, NULL)`);

  const g = await nova();
  await entrar(g, 'gestor@demo.local');
  await g.goto(BASE + '/acompanhamento');
  const linha = g.locator(`[data-pessoa="${tv1}"]`);
  const fat = Number(sql(`select coalesce(sum(total),0) from pedidos where vendedor_id=${tv1} and situacao='faturado' and faturado_em >= '${de}'`));
  ok((await linha.locator('[data-meta]').innerText()).includes(Math.round(fat / 10000 * 100) + '%'), `meta do mês: ${Math.round(fat / 100)}% de R$ 10.000 (faturado ÷ meta)`);
  const wpp = sql(`select count(*) from cliente_eventos where usuario_id=${tv1} and tipo='whatsapp' and ocorrido_em >= '${de}'`);
  ok((await linha.locator('[data-whatsapp]').innerText()).startsWith(wpp), `WhatsApp bate com o banco (${wpp})`);
  const atrasadas = sql(`select count(*) from tarefas where usuario_id=${tv1} and concluida_em is null and vence_em < curdate()`);
  ok((await linha.locator('[data-tarefas]').innerText()).includes(`${atrasadas} atrasado`), `retornos atrasados (${atrasadas})`);
  ok(await g.locator('.ac-delta').count() > 0, 'comparação com o período anterior (▲ ▼)');

  // Abas da vendedora
  for (const aba of ['resumo', 'ligacoes', 'carteira', 'agenda', 'contatos', 'pedidos']) {
    const r = await g.goto(BASE + `/acompanhamento/pessoa/${tv1}?aba=${aba}`);
    ok(r.status() === 200 && await g.locator('.abas a.ativa').count() === 1, `aba ${aba} da vendedora`);
  }
  await g.goto(BASE + `/acompanhamento/pessoa/${tv1}?aba=carteira`);
  ok((await g.locator('[data-carteira-total]').innerText()) === sql(`select count(*) from clientes where responsavel_id=${tv1} and excluido_em is null`), 'carteira: total bate com o banco');
  await g.goto(BASE + `/acompanhamento/pessoa/${tv1}?aba=contatos`);
  ok((await g.content()).includes('Teste A'), 'mensagens prontas usadas aparecem');

  // Visão Cobrança
  sql(`update cobranca_titulos t join cobrancas c on c.id=t.cobranca_id set t.pago_em = curdate() where c.responsavel_id=${fin} and t.pago_em is null order by t.id limit 1`);
  await g.goto(BASE + '/acompanhamento?visao=cobranca');
  const rec = Number(sql(`select coalesce(sum(t.valor),0) from cobranca_titulos t join cobrancas c on c.id=t.cobranca_id where c.responsavel_id=${fin} and t.pago_em >= '${de}'`));
  const txt = await g.locator(`[data-pessoa="${fin}"] [data-recuperado]`).innerText();
  ok(txt.includes(rec.toLocaleString('pt-BR', { minimumFractionDigits: 2 })), `recuperado no mês bate com o banco (R$ ${rec})`);
  const tent = sql(`select count(*) from cliente_eventos where usuario_id=${fin} and tipo='cobranca' and descricao like 'Cobrança — %' and ocorrido_em >= '${de}'`);
  ok((await g.locator(`[data-pessoa="${fin}"] [data-tentativas]`).innerText()).startsWith(tent), `tentativas (${tent})`);
  ok((await g.locator(`[data-pessoa="${fin}"] [data-casos]`).innerText()) === sql(`select count(*) from cobrancas where responsavel_id=${fin} and situacao='aberta'`), 'casos abertos');
  ok((await g.goto(BASE + `/acompanhamento/cobranca/${fin}`)).status() === 200 && await g.locator('.ac-funil li').count() > 0, 'detalhe da Cobrança com casos por etapa');

  // Financeiro: só a Cobrança
  const f = await nova();
  await entrar(f, 'financeiro@demo.local');
  await f.goto(BASE + '/acompanhamento');
  ok((await f.locator('h1').allInnerTexts()).some((t) => t.includes('Cobrança')) && await f.locator('.ac-visoes').count() === 0, 'Financeiro abre direto a Cobrança (sem a aba Vendas)');
  ok((await f.goto(BASE + `/acompanhamento/pessoa/${tv1}`)).status() === 403, 'Financeiro não abre o detalhe das vendedoras');
  // Gestora: só vendas da equipe
  const ge = await nova();
  await entrar(ge, 'gestora.televendas@demo.local');
  ok((await ge.goto(BASE + `/acompanhamento/cobranca/${fin}`)).status() === 403, 'gestora do setor não abre a Cobrança');

  ok(erros.length === 0, 'nenhum erro de JavaScript' + (erros.length ? ': ' + erros.join(' / ') : ''));
  await b.close();
  console.log(falhas ? `\n${falhas} FALHA(S)` : '\nTudo certo.');
  process.exit(falhas ? 1 : 0);
})();
