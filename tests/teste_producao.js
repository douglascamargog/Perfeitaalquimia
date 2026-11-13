// Teste automático (Playwright). Rodar todos: bash tests/rodar.sh  ·  só este: node tests/teste_producao.js
const path = require('path');
const RECURSOS = path.join(__dirname, 'recursos');
const { chromium } = require('playwright');
const { execSync } = require('child_process');
const BASE = 'http://127.0.0.1:8000', DIR = process.argv[2] || path.join(__dirname, 'saida');
let falhas = 0; const ok = (c, m) => { console.log((c ? 'OK   ' : 'FALHA') + ' ' + m); if (!c) falhas++; };
const sql = (q) => execSync(`mysql perfeita --default-character-set=utf8mb4 -N -e "${q}"`).toString().trim();
async function entrar(p, email) { await p.goto(BASE + '/entrar'); await p.fill('#email', email); await p.fill('#senha', 'Demo@2026'); await p.click('button[type=submit]'); await p.waitForLoadState(); }
async function sair(p) { await p.goto(BASE + '/'); await p.click('form[action$="/sair"] button'); await p.waitForLoadState(); }
const aviso = async (p) => (await p.locator('.aviso').allInnerTexts()).join(' | ');
(async () => {
  const b = await chromium.launch(); const ctx = await b.newContext({ viewport: { width: 1440, height: 1000 } }); const p = await ctx.newPage();
  const erros = []; p.on('pageerror', e => erros.push(e.message));
  await p.route('https://cdn.jsdelivr.net/npm/chart.js@4.4.1/**', r => r.fulfill({ path: RECURSOS + '/chart.umd.js', contentType: 'text/javascript' }));
  await p.route('https://api.open-meteo.com/**', r => r.fulfill({ json: {} }));

  await entrar(p, 'producao@demo.local');
  // ---------- Painel ----------
  await p.goto(BASE + '/producao');
  ok(await p.locator('.cabecalho-secao h1', { hasText: 'Produção' }).count() === 1, 'painel abre para o perfil Produção');
  ok(Number(await p.locator('[data-mp-alerta]').innerText()) >= 3, 'painel mostra matérias-primas em alerta (demo)');
  ok(Number(await p.locator('[data-reabastecer-qtd]').innerText()) >= 1, 'painel mostra produtos para reabastecer (demo)');
  ok(await p.evaluate(() => !!Chart.getChart('grafico-producao')), 'gráfico de produção desenhado');
  ok(await p.locator('.menu-lateral a[href$="/producao/materias"]').count() === 1, 'menu tem Matérias-primas');

  // ---------- 1. cadastra matéria-prima ----------
  await p.goto(BASE + '/producao/materias/nova');
  await p.fill('#nome', 'Teste Pó A'); await p.selectOption('#unidade', 'kg'); await p.fill('#estoque_minimo', '1,5'); await p.fill('#custo_unitario', '10,00');
  await p.fill('#fornecedor', 'Fornecedor Teste'); await p.fill('#estoque_inicial', '2');
  await Promise.all([p.waitForNavigation(), p.click('button[type=submit]:has-text("Cadastrar")')]);
  const a = sql("select id from materias_primas where nome='Teste Pó A'");
  ok(a && sql(`select estoque from materias_primas where id=${a}`) === '2.000', 'cadastra matéria-prima com estoque inicial');
  ok(sql(`select count(*) from mp_movimentos where materia_prima_id=${a} and tipo='ajuste'`) === '1', 'estoque inicial vira movimento de ajuste');
  // nome repetido
  await p.goto(BASE + '/producao/materias/nova');
  await p.fill('#nome', 'Teste Pó A'); await Promise.all([p.waitForNavigation(), p.click('button[type=submit]:has-text("Cadastrar")')]);
  ok((await aviso(p)).includes('Já existe'), 'não deixa nome repetido');

  // ---------- 2. entrada de compra ----------
  await p.goto(BASE + `/producao/materias/${a}`);
  await p.fill('#e-qtd', '3,5'); await p.fill('#e-custo', '12,40'); await p.fill('#e-nota', '12345');
  await Promise.all([p.waitForNavigation(), p.click('[data-form-entrada] button')]);
  ok(sql(`select estoque from materias_primas where id=${a}`) === '5.500', 'entrada soma ao estoque (2 + 3,5)');
  ok(sql(`select concat(tipo,'|',quantidade,'|',saldo,'|',referencia) from mp_movimentos where materia_prima_id=${a} order by id desc limit 1`) === 'entrada|3.500|5.500|NF 12345', 'movimento de entrada com saldo e nota');
  ok(sql(`select custo_unitario from materias_primas where id=${a}`) === '12.4000', 'entrada atualiza o último custo');
  // entrada rápida pela lista
  await p.goto(BASE + '/producao/materias?q=Teste');
  const linhaA = p.locator(`tr[data-mp="${a}"]`);
  await linhaA.locator('input[name=quantidade]').fill('0,5');
  await Promise.all([p.waitForNavigation(), linhaA.locator('button').click()]);
  ok(sql(`select estoque from materias_primas where id=${a}`) === '6.000', 'entrada rápida pela lista');

  // ---------- 3. ajuste exige motivo ----------
  await p.goto(BASE + `/producao/materias/${a}`);
  await p.fill('#a-contagem', '5'); await Promise.all([p.waitForNavigation(), p.click('[data-form-ajuste] button')]);
  ok((await aviso(p)).includes('motivo') && sql(`select estoque from materias_primas where id=${a}`) === '6.000', 'ajuste sem motivo é recusado');
  await p.fill('#a-contagem', '5,8'); await p.fill('#a-motivo', 'Contagem de inventário');
  await Promise.all([p.waitForNavigation(), p.click('[data-form-ajuste] button')]);
  ok(sql(`select estoque from materias_primas where id=${a}`) === '5.800' && sql(`select quantidade from mp_movimentos where materia_prima_id=${a} order by id desc limit 1`) === '-0.200', 'ajuste grava só a diferença (-0,2)');

  // mais duas matérias-primas (pela API do service seria mais rápido, mas usamos a tela)
  const novaMp = async (nome, un, custo, est) => {
    await p.goto(BASE + '/producao/materias/nova');
    await p.fill('#nome', nome); await p.selectOption('#unidade', un); await p.fill('#custo_unitario', custo); await p.fill('#estoque_inicial', est);
    await Promise.all([p.waitForNavigation(), p.click('button[type=submit]:has-text("Cadastrar")')]);
    return sql(`select id from materias_primas where nome='${nome}'`);
  };
  const bId = await novaMp('Teste Cápsula B', 'un', '0,02', '3000');
  const cId = await novaMp('Teste Pote C', 'un', '1,10', '30');

  // ---------- 4. ficha técnica com 3 matérias-primas ----------
  const prod = sql("select id from produtos p where ativo=1 and not exists (select 1 from ficha_tecnica f where f.produto_id=p.id) order by id limit 1");
  // A Produção só LÊ a ficha técnica: quem edita é a Responsável Técnica
  await p.goto(BASE + `/producao/fichas/${prod}`);
  ok(await p.locator('[data-acrescentar]').count() === 0, 'Produção vê a ficha técnica sem poder editar');
  await sair(p);
  await entrar(p, 'tecnica@demo.local');
  if (await p.locator('[data-novidades-ok]').count()) await p.click('[data-novidades-ok]');
  await p.goto(BASE + `/producao/fichas/${prod}`);
  for (const [mp, q] of [[a, '0,03'], [bId, '60'], [cId, '1']]) {
    await p.click('[data-acrescentar]');
    const tr = p.locator('[data-linhas] [data-linha]').last();
    await tr.locator('select').selectOption(String(mp)); await tr.locator('[data-qtd]').fill(q);
  }
  const custoEsperado = 0.03 * 12.4 + 60 * 0.02 + 1 * 1.10; // 2,672
  ok((await p.locator('[data-custo-total]').innerText()).includes('2,672'), 'custo por unidade calculado na tela (R$ 2,672)');
  await Promise.all([p.waitForNavigation(), p.click('[data-ficha] button[type=submit]')]);
  ok(sql(`select count(*) from ficha_tecnica where produto_id=${prod}`) === '3', 'ficha salva com 3 matérias-primas');
  ok(Math.abs(Number(sql(`select sum(ft.quantidade*m.custo_unitario) from ficha_tecnica ft join materias_primas m on m.id=ft.materia_prima_id where produto_id=${prod}`)) - custoEsperado) < 0.0001, 'custo por unidade correto no banco');
  ok(Number(sql(`select count(*) from auditoria where entidade='ficha_tecnica' and entidade_id=${prod}`)) >= 1, 'auditoria da ficha');
  await sair(p);
  await entrar(p, 'producao@demo.local');

  // ---------- 5. ordem com estoque insuficiente ----------
  await p.goto(BASE + `/producao/ordens/nova?produto=${prod}`);
  await p.fill('[data-quantidade]', '40'); // pote C tem 30
  await p.waitForSelector('[data-previa-linhas] tr');
  ok(await p.locator('[data-previa-linhas] tr.pr-falta').count() === 1, 'prévia mostra a matéria-prima que falta');
  await p.fill('#lote', 'LTESTE1');
  await Promise.all([p.waitForNavigation(), p.click('[data-form-ordem] button[type=submit]')]);
  const op1 = sql("select id from ordens_producao where lote='LTESTE1'");
  ok(op1 && sql(`select situacao from ordens_producao where id=${op1}`) === 'planejada', 'ordem planejada criada');
  ok(await p.locator('[data-falta]').count() === 1, 'página da ordem avisa que falta matéria-prima');
  p.once('dialog', d => d.accept());
  await Promise.all([p.waitForNavigation(), p.click('[data-form-concluir] button')]);
  ok((await aviso(p)).includes('Estoque insuficiente') && (await aviso(p)).includes('Teste Pote C'), 'não conclui e mostra o que falta');
  ok(sql(`select estoque from materias_primas where id=${a}`) === '5.800' && sql(`select situacao from ordens_producao where id=${op1}`) === 'planejada', 'nada foi baixado');

  // ---------- 6. conclui com estoque (quantidade real 25) ----------
  const acabAntes = Number(sql(`select coalesce((select quantidade from estoque_acabado where produto_id=${prod}),0)`));
  await p.fill('#quantidade_real', '25');
  p.once('dialog', d => d.accept());
  await Promise.all([p.waitForNavigation(), p.click('[data-form-concluir] button')]);
  ok(sql(`select situacao from ordens_producao where id=${op1}`) === 'concluida' && sql(`select quantidade from ordens_producao where id=${op1}`) === '25', 'ordem concluída com 25 unidades');
  ok(sql(`select estoque from materias_primas where id=${a}`) === '5.050', 'pó A: 5,8 − 25×0,03 = 5,05');
  ok(sql(`select estoque from materias_primas where id=${bId}`) === '1500.000', 'cápsula B: 3000 − 25×60 = 1500');
  ok(sql(`select estoque from materias_primas where id=${cId}`) === '5.000', 'pote C: 30 − 25 = 5');
  ok(sql(`select count(*) from mp_movimentos where ordem_id=${op1} and tipo='consumo'`) === '3', '3 movimentos de consumo com a referência da ordem');
  ok(Number(sql(`select quantidade from estoque_acabado where produto_id=${prod}`)) === acabAntes + 25, 'produto acabado somou 25');
  ok(Math.abs(Number(sql(`select custo_total from ordens_producao where id=${op1}`)) - 25 * custoEsperado) < 0.01, 'custo da ordem = 25 × custo da ficha');
  ok(sql(`select count(*) from auditoria where entidade='ordem_producao' and entidade_id=${op1} and acao='concluir'`) === '1', 'auditoria registra a conclusão');
  ok(await p.locator('[data-consumo] tbody tr').count() === 3, 'página mostra o consumo real');
  // concluir de novo não baixa duas vezes
  const r2 = await p.request.post(BASE + `/producao/ordens/${op1}/concluir`, { form: { _csrf: await p.locator('input[name=_csrf]').first().inputValue() } }).catch(() => null);
  ok(sql(`select estoque from materias_primas where id=${a}`) === '5.050', 'concluir de novo não baixa outra vez');
  // impressão
  const imp = await ctx.newPage(); await imp.addInitScript(() => { window.print = () => {}; });
  await imp.goto(BASE + `/producao/ordens/${op1}/imprimir`);
  ok((await imp.locator('.pr-imp-numero').innerText()).includes('OP ' + op1) && await imp.locator('.pr-imp-tabela tbody tr').count() === 3, 'ordem para imprimir (A4)');
  await imp.close();

  // ---------- 7. cancelar não mexe no estoque ----------
  await p.goto(BASE + `/producao/ordens/nova?produto=${prod}`);
  await p.fill('[data-quantidade]', '2'); await p.fill('#lote', 'LTESTE2');
  await Promise.all([p.waitForNavigation(), p.click('[data-form-ordem] button[type=submit]')]);
  const op2 = sql("select id from ordens_producao where lote='LTESTE2'");
  const estA = sql(`select estoque from materias_primas where id=${a}`), estP = sql(`select quantidade from estoque_acabado where produto_id=${prod}`);
  await p.fill('[data-form-cancelar] input[name=motivo]', 'teste');
  p.once('dialog', d => d.accept());
  await Promise.all([p.waitForNavigation(), p.click('[data-form-cancelar] button')]);
  ok(sql(`select situacao from ordens_producao where id=${op2}`) === 'cancelada', 'ordem cancelada');
  ok(sql(`select estoque from materias_primas where id=${a}`) === estA && sql(`select quantidade from estoque_acabado where produto_id=${prod}`) === estP, 'cancelar não mexe em estoque');
  // produto sem ficha não produz
  const semFicha = sql("select id from produtos p where ativo=1 and not exists (select 1 from ficha_tecnica f where f.produto_id=p.id) order by id limit 1");
  const csrf = await p.locator('input[name=_csrf]').first().inputValue();
  await p.request.post(BASE + '/producao/ordens', { form: { _csrf: csrf, produto_id: semFicha, quantidade: '10' } });
  ok(sql(`select count(*) from ordens_producao where produto_id=${semFicha}`) === '0', 'produto sem ficha técnica não vira ordem');

  // ---------- 8. previsão bate com o consumo médio ----------
  const mpAlvo = sql("select materia_prima_id from mp_movimentos where tipo='consumo' and criado_em >= now() - interval 30 day group by materia_prima_id order by sum(quantidade) limit 1");
  const consumo = Number(sql(`select -sum(quantidade) from mp_movimentos where materia_prima_id=${mpAlvo} and tipo='consumo' and criado_em >= now() - interval 30 day`));
  const est = Number(sql(`select estoque from materias_primas where id=${mpAlvo}`));
  const dias = Math.floor(est / (consumo / 30));
  await p.goto(BASE + `/producao/materias/${mpAlvo}`);
  ok((await p.locator('[data-previsao]').innerText()).includes('~' + dias + ' dias'), `previsão "acaba em ~${dias} dias" bate com o consumo médio`);

  // ---------- 9. reabastecimento ----------
  await p.goto(BASE + '/producao');
  const item = p.locator('[data-reabastecer]').filter({ has: p.locator('button') }).first();
  const pidR = await item.getAttribute('data-reabastecer');
  const texto = await item.locator('button').innerText();
  const sugerido = Number(texto.match(/de ([\d.]+) un/)[1].replace('.', ''));
  await Promise.all([p.waitForNavigation(), item.locator('button').click()]);
  const opR = sql(`select id from ordens_producao where produto_id=${pidR} and origem='reabastecimento' order by id desc limit 1`);
  ok(opR && Number(sql(`select quantidade from ordens_producao where id=${opR}`)) === sugerido, `"Gerar ordem de reabastecimento" cria a ordem sugerida (${sugerido} un)`);
  ok(p.url().endsWith('/producao/ordens/' + opR), 'abre a ordem criada');
  await p.goto(BASE + '/producao');
  ok(await p.locator(`[data-reabastecer="${pidR}"] button`).count() === 0, 'produto com ordem planejada não sugere de novo');

  // ---------- estoque acabado: mínimo e ajuste ----------
  await p.goto(BASE + `/producao/acabados/${prod}`);
  await p.fill('input[name=minimo]', '7'); await Promise.all([p.waitForNavigation(), p.click('.pr-linha-form button')]);
  ok(sql(`select minimo from estoque_acabado where produto_id=${prod}`) === '7', 'define o estoque mínimo');
  await p.fill('#contagem', '20'); await Promise.all([p.waitForNavigation(), p.click('button:has-text("Ajustar estoque")')]);
  ok((await aviso(p)).includes('motivo'), 'ajuste do acabado exige motivo');
  await p.fill('#contagem', '20'); await p.fill('#motivo', 'Contagem'); await Promise.all([p.waitForNavigation(), p.click('button:has-text("Ajustar estoque")')]);
  ok(sql(`select quantidade from estoque_acabado where produto_id=${prod}`) === '20', 'ajuste do acabado');
  await sair(p);

  // ---------- 10. pedido faturado baixa o estoque acabado ----------
  await entrar(p, 'financeiro@demo.local');
  const ped = sql(`select id from pedidos where situacao='aguardando_aprovacao' order by id limit 1`);
  // a fábrica passa a controlar os produtos deste pedido (estoque de 50)
  sql(`insert ignore into estoque_acabado (produto_id, quantidade, minimo) select distinct produto_id, 50, 0 from pedido_itens where pedido_id=${ped} and produto_id is not null`);
  if (ped) {
    const itens = sql(`select concat(i.produto_id,':',sum(i.quantidade)) from pedido_itens i join estoque_acabado e on e.produto_id=i.produto_id where i.pedido_id=${ped} group by i.produto_id`).split('\n');
    const antes = Object.fromEntries(itens.map(x => { const [pid] = x.split(':'); return [pid, Number(sql(`select quantidade from estoque_acabado where produto_id=${pid}`))]; }));
    await p.goto(BASE + '/aprovacao');
    const card = p.locator('.ped-fila-item', { hasText: '#' + ped });
    await Promise.all([p.waitForNavigation(), card.locator('form[action$="/aprovar"] button').click()]);
    await p.goto(BASE + `/pedidos/${ped}`);
    await Promise.all([p.waitForNavigation(), p.click('form[action$="/simular-faturamento"] button')]);
    ok(sql(`select situacao from pedidos where id=${ped}`) === 'faturado', 'pedido faturado (simulado)');
    ok(itens.every(x => { const [pid, q] = x.split(':'); return Number(sql(`select quantidade from estoque_acabado where produto_id=${pid}`)) === antes[pid] - Number(q); }), 'faturamento baixou o estoque acabado');
    ok(sql(`select count(*) from acabado_movimentos where pedido_id=${ped} and tipo='venda'`) === String(itens.length), 'movimento de venda com o nº do pedido');
  } else ok(false, 'sem pedido aguardando com produto controlado para testar');
  await sair(p);

  // ---------- 11. permissões ----------
  await entrar(p, 'gestor@demo.local');
  await p.goto(BASE + '/producao/materias');
  ok(await p.locator('.cabecalho-secao h1', { hasText: 'Matérias-primas' }).count() === 1 && await p.locator('.pr-entrada-rapida').count() === 0, 'gestor vê sem botões de alterar');
  const csrfG = await p.locator('input[name=_csrf]').first().inputValue().catch(() => '');
  const tok = csrfG || await p.evaluate(() => document.querySelector('meta[name=csrf]')?.content || '');
  const rg = await p.request.post(BASE + `/producao/materias/${a}/entrada`, { form: { _csrf: tok, quantidade: '1' }, maxRedirects: 0 });
  ok(rg.status() === 403 && sql(`select estoque from materias_primas where id=${a}`) === '5.050', `gestor não altera (POST ${rg.status()})`);
  await sair(p);
  await entrar(p, 'televendas1@demo.local');
  const rv = await p.goto(BASE + '/producao');
  ok(rv.status() === 403, `vendedor recebe 403 (${rv.status()})`);
  ok(await p.locator('.menu-lateral a[href$="/producao"][data-bloqueado]').count() === 1, 'vendedor: Produção com cadeado no menu');
  await sair(p);

  // celular
  await entrar(p, 'producao@demo.local');
  await p.setViewportSize({ width: 390, height: 844 });
  await p.goto(BASE + '/producao');
  ok(await p.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth + 1), 'painel sem rolagem lateral no celular');
  ok(erros.length === 0, 'sem erros de JavaScript ' + erros.join(' / '));
  await b.close();
  console.log(falhas ? `\n${falhas} FALHA(S)` : '\nTudo certo');
  process.exit(falhas ? 1 : 0);
})();
