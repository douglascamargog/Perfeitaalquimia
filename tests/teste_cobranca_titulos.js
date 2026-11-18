// V17.4 — Cobrança integrada aos títulos da ficha do cliente (titulos_receber):
// título vencido entra sozinho num caso, pago sai sozinho, a baixa feita na Cobrança volta para a ficha, e o ERP grava na ficha.
// Rodar com o banco de TESTE e o servidor local no ar.
const { chromium } = require('playwright');
const { execSync } = require('child_process');
const BASE = 'http://127.0.0.1:8000';
let falhas = 0; const ok = (c, m) => { console.log((c ? 'OK   ' : 'FALHA') + ' ' + m); if (!c) falhas++; };
const sql = (q) => execSync(`mysql perfeita --default-character-set=utf8mb4 -N -e "${q.replace(/"/g, '\\"')}"`).toString().trim();

(async () => {
  const tv1 = sql("select id from usuarios where email='televendas1@demo.local'");
  sql(`insert into clientes (tipo, razao_social, nome_fantasia, cnpj, cidade, uf, responsavel_id, etapa_id, prioridade)
       values ('cliente', 'Loja Titulos Ltda', 'Loja dos Titulos', '12345678000199', 'Porto Alegre', 'RS', ${tv1}, (select min(id) from funil_etapas), 'Média')`);
  const cli = sql("select id from clientes where nome_fantasia = 'Loja dos Titulos'");
  // na ficha: um vencido há 20 dias, um vencido ontem (dentro da tolerância de 5 dias) e um a vencer
  sql(`insert into titulos_receber (cliente_id, documento, parcela, vencimento, valor, situacao, origem) values
       (${cli}, 'NF 100', '1/2', CURDATE() - INTERVAL 20 DAY, 300, 'aberto', 'omie'),
       (${cli}, 'NF 100', '2/2', CURDATE() - INTERVAL 1 DAY, 300, 'aberto', 'omie'),
       (${cli}, 'NF 101', '1/1', CURDATE() + INTERVAL 10 DAY, 500, 'aberto', 'omie')`);
  const t1 = sql(`select id from titulos_receber where cliente_id = ${cli} and parcela = '1/2'`);

  const nav = await chromium.launch(); const erros = [];
  const p = await (await nav.newContext()).newPage(); p.on('pageerror', (e) => erros.push(e.message)); p.on('dialog', (d) => d.accept());
  try {
    await p.goto(BASE + '/entrar'); await p.fill('#email', 'admin@demo.local'); await p.fill('#senha', 'Demo@2026'); await p.click('button[type=submit]'); await p.waitForLoadState();

    // 1) abrir a Cobrança já traz o caso, com o título vencido da ficha
    await p.goto(BASE + '/cobranca');
    ok((await p.locator('body').innerText()).includes('Loja dos Titulos'), 'abrir a Cobrança já traz o cliente com título vencido na ficha');
    const caso = sql(`select id from cobrancas where cliente_id = ${cli} and situacao = 'aberta'`);
    ok(sql(`select group_concat(parcela order by parcela) from cobranca_titulos where cobranca_id = ${caso}`) === '1/2', 'entra só o vencido há mais dias que a tolerância (não o de ontem nem o a vencer)');
    ok(sql(`select titulo_id from cobranca_titulos where cobranca_id = ${caso}`) === t1, 'o item da cobrança é o mesmo título da ficha');
    await p.goto(BASE + '/cobranca/lista');
    ok(sql(`select count(*) from cobranca_titulos where titulo_id = ${t1}`) === '1', 'abrir de novo não repete o título');

    // 2) a ficha mostra que o título está na Cobrança
    await p.goto(BASE + '/carteira/clientes/' + cli);
    ok((await p.locator('[data-titulo-na-cobranca]').count()) === 1, 'a ficha marca o título "na Cobrança" (com link para o caso)');

    // 3) baixa feita na Cobrança volta para a ficha
    const item = sql(`select id from cobranca_titulos where titulo_id = ${t1}`);
    const r = await p.request.post(BASE + `/cobranca/titulos/${item}/pago`, { form: { _csrf: await p.evaluate(() => document.querySelector('meta[name=csrf]').content) },
      headers: { Accept: 'application/json' } });
    ok(r.ok(), 'baixa do título na Cobrança');
    ok(sql(`select situacao from titulos_receber where id = ${t1}`) === 'pago', 'a baixa na Cobrança deixa o título pago na ficha do cliente');

    // 4) pago na ficha (ex.: veio do ERP/Nota10) → baixa sozinho na Cobrança
    sql(`update titulos_receber set vencimento = CURDATE() - INTERVAL 10 DAY where cliente_id = ${cli} and parcela = '2/2'`);
    await p.goto(BASE + '/cobranca');
    const t2 = sql(`select id from titulos_receber where cliente_id = ${cli} and parcela = '2/2'`);
    ok(sql(`select count(*) from cobranca_titulos where titulo_id = ${t2} and pago_em is null`) === '1', 'título que venceu de vez entra no caso aberto do cliente');
    sql(`update titulos_receber set situacao = 'pago', pago_em = CURDATE() where id = ${t2}`);
    execSync('php cron/cobranca.php');
    ok(sql(`select pago_em is not null from cobranca_titulos where titulo_id = ${t2}`) === '1', 'pago na ficha → baixa sozinho na Cobrança (cron)');
    ok(sql(`select situacao from cobrancas where id = ${caso}`) !== 'aberta', 'sem nada em aberto, o caso encerra');

    // 5) "Ler títulos vencidos do ERP": os títulos do ERP passam pela ficha
    // (o cron do passo 4 já leu o ERP uma vez; ler de novo não pode duplicar a ficha)
    const antes = Number(sql("select count(*) from titulos_receber where origem = 'omie' and omie_codigo is not null"));
    execSync(`php -r 'require "app/bootstrap.php"; App\\Services\\CobrancaService::importarDoErp(null);'`);
    const depois = Number(sql("select count(*) from titulos_receber where origem = 'omie' and omie_codigo is not null"));
    ok(depois > 0, `títulos do ERP gravados na ficha dos clientes (${depois})`);
    ok(depois === antes, 'ler o ERP de novo não duplica os títulos da ficha');
    ok(sql('select count(*) from cobranca_titulos ct join titulos_receber t on t.id = ct.titulo_id where t.omie_codigo is not null') !== '0', 'e a Cobrança usa esses títulos da ficha');
    const casos = sql('select count(*) from cobranca_titulos');
    execSync(`php -r 'require "app/bootstrap.php"; App\\Services\\CobrancaService::importarDoErp(null);'`);
    ok(sql('select count(*) from cobranca_titulos') === casos, 'ler o ERP de novo não repete nada');

    ok(erros.length === 0, 'sem erros de JavaScript' + (erros.length ? ': ' + erros.join(' | ') : ''));
  } catch (e) { console.log('FALHA', e.message); falhas++; }
  await nav.close();
  console.log(falhas ? `\n${falhas} falha(s)` : '\nTudo certo.');
  process.exit(falhas ? 1 : 0);
})();
