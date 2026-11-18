// V16.1 — Análise de clientes com gráficos: recorrência, ativos, quem comprou todos os meses ou em meses escolhidos,
// e o acesso dos vendedores (só a própria carteira). Rodar com o banco de TESTE e o servidor local no ar.
const { chromium } = require('playwright');
const { execSync } = require('child_process');
const BASE = 'http://127.0.0.1:8000';
let falhas = 0; const ok = (c, m) => { console.log((c ? 'OK   ' : 'FALHA') + ' ' + m); if (!c) falhas++; };
const sql = (q) => execSync(`mysql perfeita --default-character-set=utf8mb4 -N -e "${q.replace(/"/g, '\\"')}"`).toString().trim();
const id = (email) => sql(`select id from usuarios where email='${email}'`);

(async () => {
  const tv1 = id('televendas1@demo.local'), tv2 = id('televendas2@demo.local');
  const loja = (nome, dona) => {
    sql(`insert into clientes (tipo, razao_social, nome_fantasia, cidade, uf, responsavel_id, etapa_id, prioridade)
         values ('cliente', '${nome} Ltda', '${nome}', 'Vila Análise', 'RS', ${dona}, (select min(id) from funil_etapas), 'Média')`);
    return sql(`select max(id) from clientes where nome_fantasia='${nome}'`);
  };
  // pedido faturado no dia 15 do mês de "k meses atrás" (0 = mês atual)
  const ped = (cli, dona, total, k) => sql(`insert into pedidos (uuid, cliente_id, vendedor_id, tabela_preco_id, forma_pagamento_id, subtotal, total, situacao, faturado_em, criado_em, origem)
      values (UUID(), ${cli}, ${dona}, 1, 1, ${total}, ${total}, 'faturado', @d := LEAST(NOW(), DATE_FORMAT(CURDATE() - INTERVAL ${k} MONTH, '%Y-%m-15 10:00:00')), @d, 'sistema')`);
  const todo = loja('Todo Mês Naturais', tv1), duas = loja('Duas Vezes Empório', tv1), uma = loja('Uma Vez Farmácia', tv1), outra = loja('Outra Carteira Loja', tv2);
  const nunca = loja('Nunca Comprou Loja', tv1);
  for (let k = 1; k <= 11; k++) ped(todo, tv1, 500, k);            // todos os 11 meses completos
  ped(duas, tv1, 300, 1); ped(duas, tv1, 300, 3);                   // 2 meses
  ped(uma, tv1, 200, 1); ped(outra, tv2, 900, 1);                   // 1 mês
  sql(`update clientes c set ultima_compra_em = (select date(max(criado_em)) from pedidos where cliente_id = c.id) where c.id in (${todo},${duas},${uma},${outra})`);
  const mes = (k) => sql(`select date_format(curdate() - interval ${k} month, '%Y-%m')`);
  const filtro = BASE + '/analise?uf=RS&cidade=' + encodeURIComponent('Vila Análise');

  const nav = await chromium.launch(); const erros = [];
  const entrar = async (email) => {
    const ctx = await nav.newContext();
    await ctx.route('**/chart.js@4.4.1/**', (r) => r.fulfill({ path: 'tests/recursos/chart.umd.js', contentType: 'application/javascript' }));
    const p = await ctx.newPage(); p.on('pageerror', (e) => erros.push(email + ': ' + e.message));
    await p.goto(BASE + '/entrar'); await p.fill('#email', email); await p.fill('#senha', 'Demo@2026'); await p.click('button[type=submit]'); await p.waitForLoadState();
    return p;
  };
  const nomes = async (p) => p.locator('[data-analise-lista] tbody').innerText();
  try {
    const g = await entrar('gestor@demo.local');
    await g.goto(filtro);
    ok((await g.locator('[data-kpi="todos"]').innerText()).trim() === '1', 'cartão "Compraram todos os meses" = 1 loja');
    ok((await g.locator('[data-kpi="recorrencia"]').innerText()).includes('50%'), 'recorrência: 2 de 4 que compraram (50%)');
    ok((await g.locator('[data-kpi="ativos"]').innerText()).trim() === '4', 'clientes ativos: os 4 que compraram há pouco');
    ok((await g.locator('[data-kpi="clientes"]').innerText()).trim() === '5', 'lojas no filtro inclui quem nunca comprou');
    const graficos = await g.evaluate(() => ['grafico-mensal', 'grafico-frequencia', 'grafico-situacao', 'grafico-grupos'].map((i) => !!(window.Chart && Chart.getChart(document.getElementById(i)))));
    ok(graficos.every(Boolean), 'os 4 gráficos aparecem (mês a mês, frequência, situação, quem mais compra)');
    const freq = await g.evaluate(() => JSON.parse(document.getElementById('dados-analise').textContent).frequencia);
    ok(freq.find((x) => x.meses === 11)?.clientes === 1 && freq.find((x) => x.meses === 1)?.clientes === 2 && freq.find((x) => x.meses === 0)?.clientes === 1, 'gráfico de frequência: 1 loja em 11 meses, 2 em 1 mês, 1 nunca');
    const linha = (l) => g.locator(`[data-loja="${l}"] [data-meses-loja] i.sim`).count();
    ok((await linha(todo)) === 11 && (await linha(duas)) === 2 && (await linha(nunca)) === 0, 'faixa de meses na lista: 11, 2 e 0 quadradinhos verdes');

    await g.click('[data-kpi-link="todos"]'); await g.waitForLoadState();
    let t = await nomes(g);
    ok(t.includes('Todo Mês Naturais') && !t.includes('Duas Vezes') && !t.includes('Uma Vez'), 'clicar no cartão mostra só quem comprou todos os meses');
    await g.goto(filtro + '&frequencia=uma'); t = await nomes(g);
    ok(t.includes('Uma Vez Farmácia') && t.includes('Outra Carteira') && !t.includes('Todo Mês'), 'frequência "uma vez só"');
    await g.goto(filtro + '&frequencia=nenhum'); t = await nomes(g);
    ok(t.includes('Nunca Comprou') && !t.includes('Uma Vez'), 'frequência "nenhuma compra no período"');

    // Meses escolhidos: clicar nos chips aplica sozinho (comprou em TODOS os marcados)
    await g.goto(filtro);
    await Promise.all([g.waitForNavigation(), g.click(`.an-mes-chip:has(input[value="${mes(1)}"]) span`)]);
    await Promise.all([g.waitForNavigation(), g.click(`.an-mes-chip:has(input[value="${mes(3)}"]) span`)]);
    t = await nomes(g);
    ok(t.includes('Todo Mês') && t.includes('Duas Vezes') && !t.includes('Uma Vez') && !t.includes('Outra Carteira'), 'meses marcados: só quem comprou nos dois meses');
    ok(await g.locator(`input[name="meses[]"][value="${mes(3)}"]`).isChecked(), 'os meses marcados continuam marcados');

    await g.goto(filtro + '&ordem=meses');
    ok((await g.locator('[data-analise-lista] tbody tr').first().innerText()).includes('Todo Mês'), 'ordenar por "meses com compra"');
    const csv = await (await g.request.get(filtro.replace('/analise?', '/analise/exportar.csv?'))).text();
    ok(csv.includes('Meses com compra') && /Todo Mês Naturais.*;11;/.test(csv), 'CSV traz os meses com compra');

    // Vendedora: entra pelo menu Vendas e vê só a própria carteira, sem filtro de vendedor/carteira
    const v = await entrar('televendas1@demo.local');
    ok((await v.locator('a[href$="/analise"]').count()) > 0, 'vendedora tem a Análise de clientes no menu');
    await v.goto(filtro); t = await nomes(v);
    ok(t.includes('Todo Mês') && !t.includes('Outra Carteira'), 'vendedora vê só as lojas dela');
    ok((await v.locator('select[name=vendedor], select[name=carteira]').count()) === 0, 'vendedora não tem filtro de vendedor nem de carteira');
    ok((await v.locator('[data-kpi="clientes"]').innerText()).trim() === '4', 'números da vendedora só com a carteira dela');
    const csvV = await (await v.request.get(filtro.replace('/analise?', '/analise/exportar.csv?'))).text();
    ok(!csvV.includes('Outra Carteira'), 'CSV da vendedora só com a carteira dela');
    const v2 = await entrar('televendas2@demo.local');
    await v2.goto(filtro); t = await nomes(v2);
    ok(t.includes('Outra Carteira') && !t.includes('Todo Mês'), 'outra vendedora também vê só a dela');
    ok(erros.length === 0, 'sem erros de JavaScript' + (erros.length ? ': ' + erros.join(' | ') : ''));
  } catch (e) { console.log('FALHA', e.message); falhas++; }
  await nav.close();
  console.log(falhas ? `\n${falhas} falha(s)` : '\nTudo certo.');
  process.exit(falhas ? 1 : 0);
})();
