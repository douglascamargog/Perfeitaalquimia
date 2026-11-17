// Teste automático (Playwright). Rodar todos: bash tests/rodar.sh  ·  só este: node tests/teste_ecommerce_painel.js
// E-commerce completo (V14.0) com uma Tray SIMULADA (tests/recursos/tray_simulada.php):
//  - a cópia local lê os pedidos aos poucos (limite por rodada) e não duplica; pedido sem mudança não é lido de novo;
//  - canal (loja própria × Mercado Livre × digitado), situação (aguardando/pago/cancelado) e nomes sem HTML;
//  - faturamento conta só pago/enviado/entregue; nº do Mercado Livre no detalhe; nada de nome, CPF ou e-mail do comprador;
//  - Depósito: pedido cancelado na Tray depois de começar a separar sai da fila (com aviso); nomes limpos;
//  - lista com busca, planilha CSV, permissão (vendedora 403), celular sem rolagem lateral, sem erros de JavaScript.
const { chromium } = require('playwright');
const { execSync, spawn } = require('child_process');
const fs = require('fs');
const path = require('path');
const BASE = 'http://127.0.0.1:8000';
const SAIDA = process.argv[2] || 'tests/saida';
const RECURSOS = path.join(__dirname, 'recursos');
let falhas = 0; const ok = (c, m) => { console.log((c ? 'OK   ' : 'FALHA') + ' ' + m); if (!c) falhas++; };
const sql = (q) => execSync(`mysql perfeita --default-character-set=utf8mb4 -N -e "${q.replace(/"/g, '\\"')}"`).toString().trim();
const SEG = 'config/segredos.php';
const ESTADO = path.resolve(SAIDA, 'tray_estado.json');
const estado = () => (fs.existsSync(ESTADO) ? JSON.parse(fs.readFileSync(ESTADO, 'utf8')) : {});
const mudar = (pedidos) => fs.writeFileSync(ESTADO, JSON.stringify({ ...estado(), pedidos }));

const entrar = async (b, email, opcoes = {}) => {
  const p = await (await b.newContext({ viewport: { width: 1360, height: 900 }, ...opcoes })).newPage();
  p.erros = []; p.on('pageerror', (e) => p.erros.push(e.message));
  await p.route(/open-meteo/, (r) => r.fulfill({ body: '{}' }));
  await p.route('https://cdn.jsdelivr.net/npm/chart.js@4.4.1/**', (r) => r.fulfill({ path: RECURSOS + '/chart.umd.js', contentType: 'text/javascript' }));
  await p.goto(BASE + '/entrar'); await p.fill('#email', email); await p.fill('#senha', 'Demo@2026'); await p.click('button[type=submit]'); await p.waitForLoadState();
  if (await p.locator('[data-novidades-ok]').count()) { await p.click('[data-novidades-ok]'); await p.waitForLoadState(); }
  return p;
};

(async () => {
  fs.mkdirSync(SAIDA, { recursive: true });
  if (fs.existsSync(ESTADO)) fs.unlinkSync(ESTADO);
  const simulada = spawn('php', ['-S', '127.0.0.1:8096', 'tests/recursos/tray_simulada.php'], { stdio: 'ignore', env: { ...process.env, TRAY_ESTADO: ESTADO } });
  await new Promise((r) => setTimeout(r, 700));
  const antes = fs.existsSync(SEG) ? fs.readFileSync(SEG, 'utf8') : null;
  fs.writeFileSync(SEG, "<?php\nreturn ['integracoes' => ['tray' => ['api' => 'http://127.0.0.1:8096/web_api', 'consumer_key' => 'chave-certa-123', 'consumer_secret' => 'segredo-certo-456', 'code' => 'code-certo-789']]];\n");
  execSync('rm -f storage/cache/tray_token_*.json');
  sql("update configuracoes set valor='tray' where chave='ecommerce.provedor'");
  sql("update configuracoes set valor='10' where chave='ecommerce.detalhes_por_rodada'");
  sql("update configuracoes set valor='' where chave='ecommerce.sinc_ate'");
  sql("delete from ecommerce_pedidos");
  const b = await chromium.launch(); const paginas = [];
  try {
    const a = await entrar(b, 'admin@demo.local'); paginas.push(a);

    // 1. Primeira leitura: 16 pedidos, só 10 completos nesta rodada
    await a.goto(BASE + '/marketing/ecommerce?periodo=90d');
    await Promise.all([a.waitForNavigation(), a.click('[data-eco-atualizar]')]);
    ok((await a.locator('.aviso').first().innerText()).includes('16 pedido(s) novo(s)'), '"Atualizar agora" copia os 16 pedidos da loja');
    ok(sql("select count(*) from ecommerce_pedidos where origem='tray' and detalhado=1") === '10' && sql("select count(*) from ecommerce_pedidos where detalhado=0") === '6',
      'limite por rodada: 10 pedidos completos agora, 6 ficam para a próxima');
    ok(await a.locator('[data-eco-importando]').isVisible() && (await a.locator('[data-eco-importando]').innerText()).includes('10 de 16'), 'a tela mostra "Importando o histórico: 10 de 16"');
    await Promise.all([a.waitForNavigation(), a.click('[data-eco-atualizar]')]);
    ok(sql("select count(*) from ecommerce_pedidos where detalhado=0") === '0' && sql('select count(*) from ecommerce_pedidos') === '16', 'segunda rodada completa o histórico, sem duplicar');
    const leiturasAntes = Object.values(estado().contagem || {}).reduce((s, n) => s + n, 0);
    await Promise.all([a.waitForNavigation(), a.click('[data-eco-atualizar]')]);
    const leiturasDepois = Object.values(estado().contagem || {}).reduce((s, n) => s + n, 0);
    ok(leiturasDepois === leiturasAntes, 'pedido sem mudança não é lido de novo (economiza o limite da Tray)');

    // 2. Tradução dos dados
    ok(sql("select concat(canal,'|',status_tipo) from ecommerce_pedidos where tray_id='2029'") === 'mercado_livre|pago'
      && sql("select concat(canal,'|',status_tipo) from ecommerce_pedidos where tray_id='2023'") === 'manual|aguardando'
      && sql("select status_tipo from ecommerce_pedidos where tray_id='2019'") === 'aguardando', 'canal e situação: Mercado Livre pago, "Pendente" e "Aguardando Vindi" = aguardando pagamento');
    ok(sql("select concat(nome,'|',variacao) from ecommerce_pedido_itens i join ecommerce_pedidos p on p.id=i.pedido_id where p.tray_id='1281'") === 'Roupa Cirurgica Gato Confort Verde (teste Tray)|Tamanho 02',
      'nome do produto sem HTML e sem "(Disponibilidade...)", com a variação separada');
    ok(sql("select count(*) from ecommerce_pedido_itens where nome like '%<%'") === '0', 'nenhum nome com HTML');
    ok(sql("select concat(cidade,'/',uf,'|',length(cliente_hash)) from ecommerce_pedidos where tray_id='2029'") === 'Campinas/SP|16', 'guarda só cidade/UF e um código anônimo do cliente');

    // 3. Painel
    await a.goto(BASE + '/marketing/ecommerce?periodo=90d');
    const fat = Number(sql("select sum(total) from ecommerce_pedidos where status_tipo in ('pago','enviado','entregue')"));
    ok(Math.abs(fat - (899 + 21.85 + 1200)) < 0.01, 'faturamento conta só pago/enviado/entregue (sem os aguardando pagamento)');
    ok((await a.locator('[data-eco-faturamento]').innerText()).includes('2.120,85'), 'o painel mostra R$ 2.120,85 de faturamento');
    const canais = await a.locator('[data-eco-canais]').innerText();
    ok(/loja própria/i.test(canais) && /mercado livre/i.test(canais) && /digitado no painel/i.test(canais), 'vendas por canal: loja própria, Mercado Livre e digitado no painel');
    ok(await a.locator('#grafico-diario').isVisible() && (await a.locator('#eco-calor i').count()) === 168, 'gráfico por dia e canal + mapa de calor (7 dias × 24 horas)');
    ok((await a.locator('[data-eco-produtos]').innerText()).includes('Roupa Cirurgica Gato Confort Verde') && !(await a.content()).includes('&lt;strong'), 'mais vendidos com nome limpo');
    ok((await a.locator('[data-eco-mapa] .eco-uf:not(.sem)').count()) === 2, 'mapa do Brasil marca os estados com venda');
    await a.screenshot({ path: SAIDA + '/ecommerce-painel.png', fullPage: true });

    // Filtro por canal
    await a.goto(BASE + '/marketing/ecommerce?periodo=90d&canal=mercado_livre');
    ok((await a.locator('[data-eco-faturamento]').innerText()).includes('21,85'), 'filtro "Mercado Livre": só os pedidos do marketplace');

    // 4. Lista, busca e detalhe
    await a.goto(BASE + '/marketing/ecommerce/pedidos?periodo=90d&busca=2000009988776655');
    ok((await a.locator('[data-eco-pedido]').count()) === 1 && (await a.locator('[data-eco-pedido="2029"]').count()) === 1, 'busca pelo número do pedido no Mercado Livre');
    await a.click('[data-eco-pedido="2029"] a');
    const detalhe = await a.locator('[data-eco-detalhe]').innerText();
    ok(detalhe.includes('2000009988776655') && detalhe.includes('me2') && detalhe.includes('MOTO G32'), 'detalhe: nº no marketplace, tipo de envio e produtos');
    const html = await a.content();
    ok(!html.includes('Jhonatan') && !html.includes('92976518017') && !html.includes('@exemplo.com.br') && !html.includes('Rua Secreta'), 'LGPD: nenhum nome, CPF, e-mail ou endereço do comprador na tela');
    const csv = await a.request.get(BASE + '/marketing/ecommerce/pedidos.csv?periodo=90d');
    const txt = await csv.text();
    ok(csv.status() === 200 && txt.includes('2000009988776655') && txt.includes('Mercado Livre') && !txt.includes('92976518017') && !txt.includes('Jhonatan'), 'planilha CSV com os pedidos, sem dados pessoais');

    // 5. Depósito: entra com nota e sai quando é cancelado na Tray (mesmo já separando)
    await a.goto(BASE + '/deposito?tipo=ecommerce');
    await Promise.all([a.waitForNavigation(), a.click('form[action$="/deposito/tray"] button')]);
    const idDep = sql("select id from pedidos where tray_codigo='1281'");
    ok(idDep !== '' && sql(`select expedicao from pedidos where id=${idDep}`) === 'aguardando', 'Depósito: pedido pago com nota entra na fila de separação');
    ok(sql(`select nome from pedido_itens where pedido_id=${idDep}`).startsWith('Roupa Cirurgica Gato Confort Verde (teste Tray)') && sql(`select count(*) from pedido_itens where pedido_id=${idDep} and nome like '%<%'`) === '0', 'Depósito: nome do produto limpo');
    ok(sql("select comprador_nome from pedidos where tray_codigo='1281'") === 'Sdc O.', 'Depósito: só o nome curto do comprador para conferir a etiqueta');
    sql(`update pedidos set expedicao='separando' where id=${idDep}`);
    mudar({ 1281: { status: 'CANCELADO', modified: sql('select curdate()') + ' 08:00:00' } });
    await a.goto(BASE + '/deposito?tipo=ecommerce');
    await Promise.all([a.waitForNavigation(), a.click('form[action$="/deposito/tray"] button')]);
    ok((await a.locator('.aviso').first().innerText()).includes('1 cancelado'), '"Buscar na Tray" avisa 1 cancelado');
    ok(sql(`select concat(situacao,'|',coalesce(expedicao,'-')) from pedidos where id=${idDep}`) === 'cancelado|-', 'cancelado na Tray depois de começar a separar: sai da fila');
    ok(sql(`select count(*) from notificacoes where tipo='pedido_cancelado'`) !== '0', 'o Depósito recebe aviso no sino');
    await a.goto(BASE + '/deposito/' + idDep);
    ok(await a.locator('[data-dep-cancelado]').isVisible() && (await a.locator('form[action$="/comecar"]').count()) === 0, 'a tela do pedido mostra "Cancelado na Tray: não separar"');
    ok(sql("select status_tipo from ecommerce_pedidos where tray_id='1281'") === 'cancelado', 'o painel também passa a contar o pedido como cancelado');

    // 6. Permissão e celular
    const v = await entrar(b, 'televendas1@demo.local'); paginas.push(v);
    const r = await v.goto(BASE + '/marketing/ecommerce');
    ok(r.status() === 403, 'vendedora sem acesso ao E-commerce (403)');
    const cel = await entrar(b, 'admin@demo.local', { viewport: { width: 390, height: 800 } }); paginas.push(cel);
    await cel.goto(BASE + '/marketing/ecommerce?periodo=90d');
    ok(await cel.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth + 1), 'celular: o painel cabe na tela (sem rolagem lateral)');
    await cel.screenshot({ path: SAIDA + '/ecommerce-celular.png', fullPage: true });

    const erros = paginas.flatMap((p) => p.erros);
    ok(erros.length === 0, 'sem erros de JavaScript' + (erros.length ? ': ' + erros[0] : ''));
  } finally {
    await b.close(); simulada.kill();
    execSync('rm -f storage/cache/tray_token_*.json');
    sql("update configuracoes set valor='fake' where chave='ecommerce.provedor'");
    if (antes === null) { try { fs.unlinkSync(SEG); } catch { /* ok */ } } else fs.writeFileSync(SEG, antes);
  }
  process.exit(falhas ? 1 : 0);
})();
