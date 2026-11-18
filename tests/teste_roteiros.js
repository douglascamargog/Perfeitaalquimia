// Teste automático (Playwright). Rodar todos: bash tests/rodar.sh  ·  só este: bash tests/rodar.sh roteiros
// V15.2: média mensal na ficha da loja; Gestão › Análise de clientes (todas as carteiras, filtros, agrupar, CSV);
// Planejador de visitas do externo: roteiro com cidades → lojas no caminho (todas as carteiras) → paradas por dia,
// aviso à dona, acesso à ficha só das lojas do roteiro, pedido em loja de outra carteira vai para a DONA
// (o externo fica como "tirado por" e vê no Meu mês, sem contar para a meta dele).
const { chromium } = require('playwright');
const { execSync } = require('child_process');
const fs = require('fs');
const BASE = 'http://127.0.0.1:8000';
let falhas = 0; const ok = (c, m) => { console.log((c ? 'OK   ' : 'FALHA') + ' ' + m); if (!c) falhas++; };
const sql = (q) => execSync(`mysql perfeita --default-character-set=utf8mb4 -N -e "${q.replace(/"/g, '\\"')}"`).toString().trim();
const id = (email) => sql(`select id from usuarios where email='${email}'`);

(async () => {
  const tv1 = id('televendas1@demo.local'), tv2 = id('televendas2@demo.local'), ext = id('externo1@demo.local');
  // Cidades do caminho (com o centro conhecido) e uma longe dele
  const cidade = (nome, lat, lng) => {
    sql(`insert into cidades (nome, uf, lat, lng) select '${nome}', 'RS', ${lat}, ${lng} from dual where not exists (select 1 from cidades where nome='${nome}' and uf='RS')`);
    sql(`update cidades set lat=${lat}, lng=${lng} where nome='${nome}' and uf='RS'`);
    return sql(`select id from cidades where nome='${nome}' and uf='RS' limit 1`);
  };
  const poa = cidade('Porto Alegre', -30.0346, -51.2177), cxs = cidade('Caxias do Sul', -29.1678, -51.1794), bento = cidade('Bento Gonçalves', -29.1714, -51.5192);
  const pel = cidade('Pelotas', -31.7654, -52.3376);
  // Lojas de teste (carteiras das televendas), com ponto exato
  const loja = (nome, cid, cidNome, lat, lng, dona, extra = '') => {
    sql(`insert into clientes (tipo, razao_social, nome_fantasia, cidade, uf, cidade_id, lat, lng, responsavel_id, etapa_id, prioridade ${extra ? ', ' + extra.split('=')[0] : ''})
         values ('cliente', '${nome} Ltda', '${nome}', '${cidNome}', 'RS', ${cid}, ${lat}, ${lng}, ${dona}, (select min(id) from funil_etapas), 'Média' ${extra ? ', ' + extra.split('=')[1] : ''})`);
    return sql(`select max(id) from clientes where nome_fantasia='${nome}'`);
  };
  const a = loja('Farmácia Rota Um', cxs, 'Caxias do Sul', -29.1690, -51.1800, tv1);
  const b = loja('Empório Rota Dois', cxs, 'Caxias do Sul', -29.1600, -51.1700, tv1);
  const c = loja('Naturais Rota Três', bento, 'Bento Gonçalves', -29.1700, -51.5100, tv2);
  const longe = loja('Loja Longe Pelotas', pel, 'Pelotas', -31.7650, -52.3300, tv1);
  sql(`update clientes set ultima_compra_em = CURDATE() - INTERVAL 100 DAY, intervalo_medio_dias = 30 where id = ${b}`); // recompra atrasada e 90+ dias
  // Pedidos faturados da loja A: R$ 4.074,63 em 12 meses (2 pedidos) + um de 14 meses atrás (fora)
  const ped = (cli, total, dias) => sql(`insert into pedidos (uuid, cliente_id, vendedor_id, tabela_preco_id, forma_pagamento_id, subtotal, total, situacao, faturado_em, origem)
      values (UUID(), ${cli}, ${tv1}, 1, 1, ${total}, ${total}, 'faturado', NOW() - INTERVAL ${dias} DAY, 'sistema')`);
  ped(a, 2037.31, 40); ped(a, 2037.32, 80); ped(a, 1500, 420);
  sql(`update clientes set ultima_compra_em = CURDATE() - INTERVAL 40 DAY where id = ${a}`);
  // Produto com preço nas 3 tabelas (para o pedido do externo)
  sql("insert into produtos (slug, codigo, nome, ficha_json, ativo, visivel_catalogo) values ('produto-roteiro', 'RT01', 'Produto do Roteiro', '{}', 1, 1)");
  const prod = sql("select id from produtos where slug='produto-roteiro'");
  sql(`insert into produto_precos (produto_id, tabela_preco_id, preco) select ${prod}, id, 100 from tabelas_preco`);

  const b2 = await chromium.launch(); const erros = [];
  await b2.newContext().then((ctx) => ctx.close());
  const entrar = async (email) => {
    const ctx = await b2.newContext();
    await ctx.route('**/maplibre-gl@*/dist/maplibre-gl.js', (r) => r.fulfill({ path: 'tests/recursos/maplibre-gl.js' }));
    await ctx.route('**/maplibre-gl@*/dist/maplibre-gl.css', (r) => r.fulfill({ path: 'tests/recursos/maplibre-gl.css' }));
    await ctx.route('https://tiles.openfreemap.org/**', (r) => r.fulfill({ status: 200, contentType: 'application/json', body: '{"version":8,"sources":{},"layers":[]}' }));
    const p = await ctx.newPage(); p.on('pageerror', (e) => erros.push(email + ': ' + e.message)); p.on('dialog', (d) => d.accept());
    await p.goto(BASE + '/entrar'); await p.fill('#email', email); await p.fill('#senha', 'Demo@2026'); await p.click('button[type=submit]'); await p.waitForLoadState();
    return p;
  };
  try {
    // ---------------- 1. Média por mês na ficha
    const g = await entrar('gestor@demo.local');
    await g.goto(BASE + '/carteira/clientes/' + a);
    ok((await g.locator('[data-media-mensal]').innerText()).includes('339,55'), 'ficha: "Média por mês" = comprou em 12 meses ÷ 12 (R$ 4.074,63 → R$ 339,55)');

    // ---------------- 2. Análise de clientes
    await g.goto(BASE + '/analise?uf=RS&cidade=Caxias+do+Sul');
    ok(await g.locator('[data-kpi=faturamento]').innerText().then((t) => t.includes('4.074,63')), 'análise: faturamento do filtro (UF + cidade) só com os pedidos faturados dos 12 meses');
    ok((await g.locator('[data-kpi=media]').innerText()).includes('339,55'), 'análise: média mensal por cliente que comprou');
    ok((await g.locator('[data-kpi=ticket]').innerText()).includes('2.037,32'), 'análise: ticket médio por pedido');
    ok((await g.locator('[data-analise-lista] [data-loja="' + a + '"] [data-media]').innerText()).includes('339,55'), 'análise: lista de lojas com a média mensal de cada uma');
    await g.goto(BASE + '/analise?uf=RS&grupo=cidade');
    ok((await g.locator('[data-analise-grupos]').innerText()).includes('Caxias do Sul / RS'), 'análise: agrupado por cidade (todas as carteiras)');
    await g.goto(BASE + '/analise?grupo=vendedor&compra=sem90');
    ok((await g.locator('[data-analise-lista]').innerText()).includes('Empório Rota Dois'), 'análise: filtro "sem compra há 90+ dias"');
    await g.goto(BASE + '/analise?uf=RS&media_min=300');
    const lista = await g.locator('[data-analise-lista]').innerText();
    ok(lista.includes('Farmácia Rota Um') && !lista.includes('Empório Rota Dois'), 'análise: filtro por faixa de média mensal');
    const csv = await g.request.get(BASE + '/analise/exportar.csv?uf=RS&cidade=Caxias+do+Sul');
    const textoCsv = await csv.text();
    ok(csv.status() === 200 && textoCsv.includes('Média mensal (÷ 12)') && textoCsv.includes('339,55'), 'análise: exporta CSV com a média mensal');
    const tv = await entrar('televendas1@demo.local');
    ok((await tv.goto(BASE + '/analise')).status() === 403, 'vendedora não abre a Análise de clientes (só gestão)');

    // ---------------- 3. Planejador: quem usa
    ok((await tv.goto(BASE + '/roteiros/novo')).status() === 403, 'televendas não monta roteiro (só a função Externo)');
    const e = await entrar('externo1@demo.local');
    await e.goto(BASE + '/inicio');
    ok(await e.locator('a[href$="/roteiros"]').count() > 0, 'externo vê o Planejador de visitas no menu');
    ok((await e.goto(BASE + '/carteira/clientes/' + a)).status() === 403, 'antes do roteiro, o externo não abre a ficha de loja de outra carteira');

    // ---------------- 4. Criar roteiro
    await e.goto(BASE + '/roteiros/novo');
    const amanha = sql('select CURDATE() + INTERVAL 1 DAY'), depois = sql('select CURDATE() + INTERVAL 3 DAY');
    await e.fill('input[name=nome]', 'Serra gaúcha'); await e.fill('input[name=data_inicio]', amanha); await e.fill('input[name=data_fim]', depois);
    await e.fill('input[name=raio_km]', '30'); await e.fill('input[name=visitas_dia]', '2');
    const cid = e.locator('input[name="cidades[]"]');
    await cid.nth(0).fill('Porto Alegre/RS'); await cid.nth(1).fill('Caxias do Sul'); await cid.nth(2).fill('Bento Gonçalves/RS');
    await Promise.all([e.waitForNavigation(), e.click('[data-form-roteiro] button[type=submit]')]);
    const rid = sql("select max(id) from roteiros where nome='Serra gaúcha'");
    ok(rid !== 'NULL' && e.url().includes('/roteiros/' + rid) && e.url().includes('aba=caminho'), 'roteiro criado; abre direto "Lojas no caminho"');
    ok(sql(`select cidades_json from roteiros where id=${rid}`) === JSON.stringify([+poa, +cxs, +bento]), 'cidades do caminho guardadas na ordem');

    // ---------------- 5. Assistente: lojas no caminho
    await e.goto(BASE + `/roteiros/${rid}?aba=caminho&so=todas`);
    const caminho = await e.locator('[data-caminho]').innerText();
    ok(caminho.includes('Farmácia Rota Um') && caminho.includes('Empório Rota Dois') && caminho.includes('Naturais Rota Três'), 'assistente acha as lojas perto da rota, de todas as carteiras');
    ok(!caminho.includes('Loja Longe Pelotas'), 'loja longe do caminho não aparece');
    ok(caminho.includes('recompra atrasada') && caminho.includes('compra R$ 339,55/mês'), 'cada loja mostra por que visitar (recompra atrasada, média mensal...)');
    const ordem = await e.locator('[data-loja-caminho]').evaluateAll((l) => l.map((x) => x.dataset.lojaCaminho));
    ok(ordem.indexOf(b) < ordem.indexOf(a) && ordem.indexOf(a) < ordem.indexOf(c), 'mais importantes primeiro (recompra atrasada e 90 dias sem compra > quem compra todo mês > sem histórico)');
    await e.locator('[data-marcar-todas]').check(); await e.locator('[data-marcar-todas]').uncheck(); // desmarca as sugeridas
    await e.locator(`[data-loja-caminho="${a}"] input`).check(); await e.locator(`[data-loja-caminho="${b}"] input`).check(); await e.locator(`[data-loja-caminho="${c}"] input`).check();
    await Promise.all([e.waitForNavigation(), e.click('[data-incluir-marcadas]')]);
    ok(sql(`select count(*) from roteiro_paradas where roteiro_id=${rid}`) === '3', 'lojas marcadas entram no roteiro');
    ok(sql(`select count(*) from notificacoes where usuario_id=${tv1} and tipo='roteiro_visita'`) === '1' && sql(`select count(*) from notificacoes where usuario_id=${tv2} and tipo='roteiro_visita'`) === '1',
      'cada dona recebe UM aviso no sino com as lojas dela que serão visitadas');
    const porDia = sql(`select max(n) from (select count(*) n from roteiro_paradas where roteiro_id=${rid} group by dia) x`);
    ok(+porDia <= 2 && sql(`select count(*) from roteiro_paradas where roteiro_id=${rid} and dia is null`) === '0', 'paradas divididas pelos dias (no máximo 2 visitas por dia)');
    const ordemDias = sql(`select group_concat(cliente_id order by dia, ordem) from roteiro_paradas where roteiro_id=${rid}`).split(',');
    ok(ordemDias.indexOf(c) === 2, 'paradas na ordem do caminho (Bento Gonçalves, a última cidade, fica por último)');
    await e.goto(BASE + `/roteiros/${rid}`);
    ok(await e.locator('[data-google-maps]').count() > 0 && (await e.locator('[data-google-maps]').first().getAttribute('href')).startsWith('https://www.google.com/maps/dir/'), 'botão "Rota no Google Maps" com as paradas');
    // A rota do Google vai pelo ENDEREÇO da loja (não pela coordenada, que pode ser aproximada)
    sql(`update clientes set endereco='Rua Sinimbu, 1500', bairro='Centro', cep='95020002' where id=${c}`);
    await e.goto(BASE + `/roteiros/${rid}`);
    const rota = decodeURIComponent((await e.locator('[data-google-maps]').first().getAttribute('href')).replace(/\+/g, ' '));
    ok(rota.includes('Rua Sinimbu, 1500, Centro, Bento Gonçalves - RS, 95020-002, Brasil') && !rota.includes('-29.17'), 'Google Maps: loja com endereço vai pelo endereço (rua, número, bairro, cidade, CEP)');

    // ---------------- 6. Acesso e pedido em loja de outra carteira
    ok((await e.goto(BASE + '/carteira/clientes/' + a)).status() === 200, 'com a loja no roteiro, o externo abre a ficha dela');
    ok((await e.goto(BASE + '/carteira/clientes/' + longe)).status() === 403, 'loja fora do roteiro continua fechada para o externo');
    const pacote = await (await e.request.get(BASE + '/app/api/dados', { headers: { Accept: 'application/json' } })).json();
    ok(pacote.clientes.some((x) => String(x.id) === a) && !pacote.clientes.some((x) => String(x.id) === longe), 'app do externo leva as lojas do roteiro (para vender offline)');
    await e.goto(BASE + '/inicio');
    const csrf = await e.locator('meta[name=csrf]').getAttribute('content');
    const r = await (await e.request.post(BASE + '/app/api/sincronizar', { data: { itens: [{ uuid: 'abcdef12-3456-4789-8abc-def012345678', tipo: 'pedido', cliente_id: +a, itens: { [prod]: 10 }, pagamento: '1:1' }] },
      headers: { 'X-CSRF-Token': csrf, Accept: 'application/json' } })).json();
    const pid = r.resultados['abcdef12-3456-4789-8abc-def012345678']?.pedido_id;
    ok(!!pid, 'externo fecha pedido na loja do roteiro (pelo app)' + (pid ? '' : ': ' + JSON.stringify(r)));
    ok(sql(`select concat(vendedor_id, '/', tirado_por_id) from pedidos where id=${pid}`) === `${tv1}/${ext}`, 'pedido vai para a DONA da carteira; o externo fica como "tirado por"');
    ok(sql(`select concat(situacao, '/', pedido_id) from roteiro_paradas where roteiro_id=${rid} and cliente_id=${a}`) === `visitado/${pid}`, 'a parada fica como visitada, com o pedido');
    ok(sql(`select count(*) from notificacoes where usuario_id=${tv1} and tipo='roteiro_pedido' and pedido_id=${pid}`) === '1', 'a dona recebe aviso: o externo fechou pedido na loja dela (a venda é dela)');
    ok((await e.goto(BASE + '/pedidos/' + pid)).status() === 200, 'o externo vê o pedido que tirou');
    sql(`update pedidos set situacao='faturado', faturado_em=NOW() where id=${pid}`);
    const mes = sql("select date_format(curdate(), '%Y-%m')");
    const metas = execSync(`php -r 'require "app/bootstrap.php"; echo App\\Services\\MetaService::totalMes(${tv1}, "${mes}"), "|", App\\Services\\MetaService::totalMes(${ext}, "${mes}");'`).toString();
    const totalPed = Number(sql(`select total from pedidos where id=${pid}`));
    ok(Number(metas.split('|')[0]) >= totalPed && Number(metas.split('|')[1]) === 0, 'venda conta para a meta da dona e NÃO para a do externo');
    await e.goto(BASE + '/meu-mes?aba=vendas');
    ok(await e.locator('[data-vendas-visitas]').count() === 1 && (await e.locator('[data-total-visitas]').innerText()).includes(totalPed.toLocaleString('pt-BR', { minimumFractionDigits: 2 })), 'Meu mês do externo: "Vendas em visitas de outras carteiras" com o total');

    // ---------------- 7. Registrar visita
    await e.goto(BASE + `/roteiros/${rid}`);
    const parB = sql(`select id from roteiro_paradas where roteiro_id=${rid} and cliente_id=${b}`);
    await e.locator(`[data-parada="${parB}"] summary`).click();
    await e.locator(`[data-parada="${parB}"] select[name=situacao]`).selectOption('nao_atendeu');
    await e.locator(`[data-parada="${parB}"] input[name=observacao]`).fill('Loja fechada para balanço');
    await Promise.all([e.waitForNavigation(), e.locator(`[data-parada="${parB}"] form`).first().locator('button').click()]);
    ok(sql(`select situacao from roteiro_paradas where id=${parB}`) === 'nao_atendeu' && sql(`select count(*) from cliente_eventos where cliente_id=${b} and descricao like '%Loja fechada para balanço%'`) === '1',
      'visita registrada vai para a linha do tempo da loja');

    // ---------------- 8. A dona vê só as lojas dela no roteiro
    ok((await tv.goto(BASE + `/roteiros/${rid}`)).status() === 200, 'a dona abre o roteiro pelo aviso');
    const daDona = await tv.locator('main').innerText();
    ok(daDona.includes('Farmácia Rota Um') && !daDona.includes('Naturais Rota Três'), '...e vê só as lojas da carteira dela');
    const t2 = await entrar('televendas2@demo.local');
    ok((await t2.goto(BASE + '/roteiros/' + rid)).status() === 200 && !(await t2.locator('main').innerText()).includes('Farmácia Rota Um'), 'outra dona também só vê as dela');
    ok((await entrar('reativacao@demo.local').then((p) => p.goto(BASE + '/roteiros/' + rid))).status() === 403, 'quem não tem loja no roteiro não abre');

    // ---------------- 9. Gestor vê os roteiros de todos; concluir tira o acesso
    await g.goto(BASE + '/roteiros');
    ok(await g.locator(`[data-roteiro="${rid}"]`).count() === 1, 'gestor vê os roteiros de todos');
    await e.goto(BASE + `/roteiros/${rid}`);
    await Promise.all([e.waitForNavigation(), e.click('form[action$="/situacao"] button:has-text("Concluir")')]);
    ok(sql(`select situacao from roteiros where id=${rid}`) === 'concluido' && (await e.goto(BASE + '/carteira/clientes/' + b)).status() === 403, 'roteiro concluído: o externo não abre mais as lojas de outras carteiras');
    ok(erros.length === 0, 'sem erros de JavaScript' + (erros.length ? ': ' + erros[0] : ''));
  } finally {
    await b2.close();
  }
  process.exit(falhas ? 1 : 0);
})();
