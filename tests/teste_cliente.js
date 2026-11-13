// Teste automático (Playwright). Rodar todos: bash tests/rodar.sh  ·  só este: node tests/teste_cliente.js
// Página do cliente: abre pela gaveta, mostra tudo da empresa, salva pela ficha e respeita a carteira de cada um.
const { chromium } = require('playwright');
const { execSync } = require('child_process');
const BASE = 'http://127.0.0.1:8000';
let falhas = 0; const ok = (c, m) => { console.log((c ? 'OK   ' : 'FALHA') + ' ' + m); if (!c) falhas++; };
const sql = (q) => execSync(`mysql perfeita --default-character-set=utf8mb4 -N -e "${q.replace(/"/g, '\\"')}"`).toString().trim();
async function entrar(p, email) { await p.goto(BASE + '/entrar'); await p.fill('#email', email); await p.fill('#senha', 'Demo@2026'); await p.click('button[type=submit]'); await p.waitForLoadState(); }

(async () => {
  const b = await chromium.launch();
  const erros = [];
  const nova = async () => { const p = await (await b.newContext({ viewport: { width: 1440, height: 900 } })).newPage(); p.on('pageerror', e => erros.push(e.message)); await p.route(/open-meteo|jsdelivr/, r => r.fulfill({ body: '{}' })); return p; };
  const vend = sql("select id from usuarios where email='televendas1@demo.local'");
  const cli = sql(`select c.id from clientes c where c.responsavel_id=${vend} and c.tipo='cliente' and c.excluido_em is null and exists (select 1 from pedidos p where p.cliente_id=c.id and p.situacao='faturado') limit 1`);
  const nome = sql(`select coalesce(nullif(nome_fantasia,''), razao_social) from clientes where id=${cli}`);

  // 1. Da gaveta (acesso rápido) para a página completa
  const ven = await nova();
  await entrar(ven, 'televendas1@demo.local');
  await ven.goto(BASE + '/carteira/lista?q=' + encodeURIComponent(nome.slice(0, 15)));
  await ven.locator(`[data-abrir-cliente="${cli}"]`).first().click();
  await ven.waitForSelector('[data-abrir-pagina]');
  ok(await ven.locator('.gaveta a:has-text("Abrir página completa")').count() === 1, 'gaveta com o botão "Abrir página completa"');
  await Promise.all([ven.waitForNavigation(), ven.click('[data-abrir-pagina]')]);
  ok(new URL(ven.url()).pathname.endsWith(`/carteira/clientes/${cli}`), 'abre a página do cliente');

  // 2. Tudo da empresa
  ok((await ven.locator('.cl-topo h1').innerText()).includes(nome), 'nome do cliente no topo');
  ok(await ven.locator('.cl-kpi').count() === 4, 'números: comprou em 12 meses, ticket, última compra, próxima compra');
  const pedidos = Number(sql(`select least(count(*), 50) from pedidos where cliente_id=${cli}`));
  ok(await ven.locator('[data-pedidos-cliente] tbody tr a').count() === pedidos, `todos os pedidos (${pedidos})`);
  ok(await ven.locator('h2:has-text("O que mais compra")').count() === 1, 'produtos que mais compra');
  ok(await ven.locator('.cl-linha-tempo li').count() >= 1, 'linha do tempo completa');
  ok(await ven.locator('[data-pagina-cliente] .ficha-topo').count() === 0 && await ven.locator('[data-pagina-cliente] h3:has-text("Gestão comercial"), [data-pagina-cliente] h3').count() > 0, 'ficha da gaveta ao lado (sem o cabeçalho repetido)');

  // 3. Salvar pela ficha na página: registra o contato e a página mostra na linha do tempo
  await ven.fill('[data-pagina-cliente] #c-resumo', 'Contato pela página do cliente');
  await Promise.all([ven.waitForNavigation(), ven.locator('[data-pagina-cliente] form[action$="/contato"] button[type=submit]').click()]);
  ok(await ven.locator('.cl-linha-tempo li', { hasText: 'Contato pela página do cliente' }).count() === 1, 'contato registrado na página aparece na linha do tempo');

  // 4. Cliente de outra carteira: sem acesso
  const outro = sql(`select id from clientes where responsavel_id <> ${vend} and excluido_em is null limit 1`);
  const r = await ven.goto(BASE + `/carteira/clientes/${outro}`);
  ok(r.status() === 403, 'vendedor não abre a página de cliente de outra carteira (403)');

  // 5. Cliente de terceirização mostra os produtos com a marca dele
  const ges = await nova();
  await entrar(ges, 'gestor@demo.local');
  const terc = sql('select cliente_id from terceirizacao_produtos limit 1');
  await ges.goto(BASE + `/carteira/clientes/${terc}`);
  ok(await ges.locator('h2:has-text("Produtos com a marca do cliente")').count() === 1, 'cliente de terceirização: produtos com a marca dele');

  ok(erros.length === 0, 'sem erros de JavaScript ' + erros.join(' / '));
  await b.close();
  console.log(falhas ? `\n${falhas} FALHA(S)` : '\nTudo certo');
  process.exit(falhas ? 1 : 0);
})();
