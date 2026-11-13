// Teste automático (Playwright). Rodar todos: bash tests/rodar.sh  ·  só este: node tests/teste_equipe.js
// Equipe de vendas (3 frentes, ranking) e Meu mês do vendedor (Meus clientes).
const path = require('path');
const { chromium } = require('playwright');
const { execSync } = require('child_process');
const BASE = 'http://127.0.0.1:8000';
const RECURSOS = path.join(__dirname, 'recursos');
let falhas = 0; const ok = (c, m) => { console.log((c ? 'OK   ' : 'FALHA') + ' ' + m); if (!c) falhas++; };
const sql = (q) => execSync(`mysql perfeita --default-character-set=utf8mb4 -N -e "${q.replace(/"/g, '\\"')}"`).toString().trim();
const brl = (v) => v.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
async function entrar(p, email) { await p.goto(BASE + '/entrar'); await p.fill('#email', email); await p.fill('#senha', 'Demo@2026'); await p.click('button[type=submit]'); await p.waitForLoadState(); }

(async () => {
  const b = await chromium.launch();
  const erros = [];
  const nova = async () => { const p = await (await b.newContext({ viewport: { width: 1440, height: 900 } })).newPage(); p.on('pageerror', e => erros.push(e.message));
    await p.route(/open-meteo/, r => r.fulfill({ body: '{}' }));
    await p.route('https://cdn.jsdelivr.net/npm/chart.js@4.4.1/**', r => r.fulfill({ path: RECURSOS + '/chart.umd.js', contentType: 'text/javascript' })); return p; };
  const mes = sql("select date_format(curdate(), '%Y-%m')");

  // 1. Vendedor vê a Equipe de vendas (com nomes no ranking)
  const ven = await nova();
  await entrar(ven, 'televendas1@demo.local');
  await ven.goto(BASE + '/equipe');
  ok(await ven.locator('.eq-frente').count() === 3, 'três frentes: Externos, Televendas, Terceirização');
  // Cada frente bate com a soma dos pedidos faturados das carteiras das funções daquela frente
  for (const [frente, nome] of [['externo', 'Externos'], ['televendas', 'Televendas'], ['terceirizacao', 'Terceirização']]) {
    const valor = Number(sql(`select coalesce(sum(p.total),0) from pedidos p join clientes c on c.id=p.cliente_id join funcoes f on f.id=c.funcao_id where p.situacao='faturado' and date_format(p.faturado_em,'%Y-%m')='${mes}' and f.frente='${frente}'`));
    const card = await ven.locator(`.eq-frente[data-frente=${frente}] .eq-valor`).innerText();
    ok(card.includes(brl(valor)), `${nome}: faturado do mês = R$ ${brl(valor)}`);
  }
  const ranking = ven.locator('[data-ranking] tbody tr');
  ok(await ranking.count() >= 3, 'ranking com os vendedores das frentes');
  ok((await ranking.first().innerText()).includes('(demo)'), 'ranking mostra os nomes (todos veem)');
  await ven.click('.eq-abas a:has-text("Televendas")');
  await ven.waitForLoadState();
  const soTele = await ven.locator('[data-ranking] tbody tr').allInnerTexts();
  ok(soTele.length >= 1 && soTele.every((t) => t.includes('Televendas')), 'filtro do ranking por frente');
  ok(await ven.locator('.form-equipe').count() === 0, 'vendedor não lança o vendido da equipe');
  ok(await ven.evaluate(() => !!document.querySelector('#grafico-frentes') && !!window.Chart), 'gráfico do faturado por frente');

  // 2. Meu mês do vendedor: atalho da equipe e "Meus clientes" só da carteira dele
  await ven.goto(BASE + '/meu-mes');
  ok(await ven.locator('[data-atalho-equipe]').count() === 1 && await ven.locator('.meta-equipe').count() === 0, 'Meu mês: só o atalho para a equipe');
  ok(await ven.locator('[data-top-mes]').count() === 1, 'Visão do mês: melhores clientes do mês');
  await ven.goto(BASE + '/meu-mes?aba=clientes');
  const vend = sql("select id from usuarios where email='televendas1@demo.local'");
  const nomes = await ven.locator('[data-melhores] tbody tr .link-cliente').allInnerTexts();
  const meus = sql(`select count(*) from pedidos p where p.vendedor_id=${vend} and p.situacao='faturado' and p.faturado_em >= curdate() - interval 12 month`);
  ok(nomes.length > 0 && nomes.length <= 10, `melhores clientes (${nomes.length}) de ${meus} pedido(s) faturado(s)`);
  const parados = await ven.locator('[data-parados] tbody tr .link-cliente').count();
  const esperado = Math.min(60, Number(sql(`select count(*) from clientes where responsavel_id=${vend} and tipo='cliente' and excluido_em is null and ultima_compra_em < curdate() - interval 30 day`)));
  ok(parados === esperado, `sem comprar há 30+ dias, só da carteira dele (${parados})`);
  await ven.locator('[data-parados] .link-cliente').first().click();
  await ven.waitForSelector('.gaveta.aberta, .gaveta[open], [data-gaveta]:not([hidden])', { timeout: 5000 }).catch(() => null);
  ok(await ven.locator('.gaveta').count() >= 1, 'clicar no cliente abre a ficha');

  // 3. Gestor lança o vendido da equipe e volta para a Equipe de vendas
  const ges = await nova();
  await entrar(ges, 'gestor@demo.local');
  await ges.goto(BASE + '/equipe');
  await ges.click('.form-equipe summary');
  await ges.fill('#eq-vendido', '123.456,00');
  await Promise.all([ges.waitForNavigation(), ges.click('.form-equipe button[type=submit]')]);
  ok(new URL(ges.url()).pathname.endsWith('/equipe') && (await ges.locator('.meta-equipe-numeros').innerText()).includes('123.456,00'), 'gestor salva o vendido da equipe e continua em Equipe de vendas');

  // 4. Quem não tem acesso: cadeado
  const prod = await nova();
  await entrar(prod, 'producao@demo.local');
  ok(await prod.locator('.menu-lateral a[href$="/equipe"][data-bloqueado]').count() === 1, 'Produção: Equipe de vendas com cadeado');

  ok(erros.length === 0, 'sem erros de JavaScript ' + erros.join(' / '));
  await b.close();
  console.log(falhas ? `\n${falhas} FALHA(S)` : '\nTudo certo');
  process.exit(falhas ? 1 : 0);
})();
