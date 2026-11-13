// Teste automático (Playwright). Rodar todos: bash tests/rodar.sh  ·  só este: node tests/teste_presenca.js
// Quem está online: fotinhas no topo, nome da aba (nunca o cliente), some depois de 3 minutos sem sinal.
const { chromium } = require('playwright');
const { execSync } = require('child_process');
const BASE = 'http://127.0.0.1:8000';
let falhas = 0; const ok = (c, m) => { console.log((c ? 'OK   ' : 'FALHA') + ' ' + m); if (!c) falhas++; };
const sql = (q) => execSync(`mysql perfeita --default-character-set=utf8mb4 -N -e "${q.replace(/"/g, '\\"')}"`).toString().trim();
async function entrar(p, email) { await p.goto(BASE + '/entrar'); await p.fill('#email', email); await p.fill('#senha', 'Demo@2026'); await p.click('button[type=submit]'); await p.waitForLoadState(); }

(async () => {
  sql('update usuarios set visto_em=null, visto_onde=null');
  const b = await chromium.launch();
  const erros = [];
  const nova = async () => { const p = await (await b.newContext({ viewport: { width: 1280, height: 860 } })).newPage(); p.on('pageerror', e => erros.push(e.message)); await p.route(/open-meteo|jsdelivr/, r => r.fulfill({ body: '{}' })); return p; };
  const ven = await nova(), ges = await nova();

  // 1. Vendedor abre a ficha de um cliente na Carteira
  await entrar(ven, 'televendas1@demo.local');
  const vend = sql("select id from usuarios where email='televendas1@demo.local'");
  const cli = sql(`select id from clientes where responsavel_id=${vend} and excluido_em is null limit 1`);
  const loja = sql(`select coalesce(nullif(nome_fantasia,''), razao_social) from clientes where id=${cli}`);
  await ven.goto(BASE + `/carteira/lista?cliente=${cli}`);
  ok(sql("select visto_onde from usuarios where email='televendas1@demo.local'") === 'Carteira', 'grava só o nome da aba ("Carteira")');

  // 2. Gestor vê a fotinha do vendedor e onde ele está
  await entrar(ges, 'gestor@demo.local');
  const pilha = ges.locator('[data-online] .online-foto');
  ok(await pilha.count() >= 1, 'fotinha de quem está online no topo');
  ok(await ges.locator('[data-online] .online-foto').first().getAttribute('title') !== null, 'passar o mouse mostra nome e aba');
  await ges.click('[data-online] summary');
  const item = ges.locator('.online-item', { hasText: 'Televendas 1' });
  ok(await item.count() === 1 && (await item.locator('.online-onde b').innerText()) === 'Carteira', 'lista: "Televendas 1 · Carteira"');
  ok(await ges.locator('.online-item', { hasText: 'Gestor' }).count() === 0, 'a própria pessoa não aparece');

  // 3. Privacidade: a resposta automática não tem cliente nem endereço da página
  const r = await (await ges.request.get(BASE + '/notificacoes/resumo?onde=' + encodeURIComponent(`/carteira/clientes/${cli}/ficha`), { headers: { Accept: 'application/json' } })).json();
  ok(!r.online_html.includes(loja) && !r.online_html.includes(`/clientes/${cli}`), 'nunca mostra o cliente aberto (LGPD)');
  ok(sql("select visto_onde from usuarios where email='gestor@demo.local'") === 'Carteira', 'endereço com cliente vira só "Carteira"');

  // 4. Parado numa tela: o resumo do sino (a cada 60 s) mantém online e atualiza a aba
  sql(`update usuarios set visto_em = now() - interval 2 minute where id=${vend}`);
  const rv = await ven.request.get(BASE + '/notificacoes/resumo?onde=' + encodeURIComponent('/agenda'), { headers: { Accept: 'application/json' } });
  ok(rv.ok() && sql(`select visto_onde from usuarios where id=${vend}`) === 'Agenda', 'o resumo do sino atualiza a aba (Agenda)');
  ok(Number(sql(`select timestampdiff(second, visto_em, now()) from usuarios where id=${vend}`)) < 30, '... e renova o "online"');

  // 5. Sem sinal há mais de 3 minutos: some
  sql(`update usuarios set visto_em = now() - interval 5 minute where id=${vend}`);
  const r2 = await (await ges.request.get(BASE + '/notificacoes/resumo?onde=%2F', { headers: { Accept: 'application/json' } })).json();
  ok(!r2.online_html.includes('Televendas 1'), 'sem sinal há mais de 3 minutos sai da lista');

  // 6. App do externo aparece como "App do externo"
  const ext = await nova();
  await entrar(ext, 'externo1@demo.local');
  await ext.goto(BASE + '/app');
  ok(sql("select visto_onde from usuarios where email='externo1@demo.local'") === 'App do externo', 'app do externo registra "App do externo"');

  ok(erros.length === 0, 'sem erros de JavaScript ' + erros.join(' / '));
  await b.close();
  console.log(falhas ? `\n${falhas} FALHA(S)` : '\nTudo certo');
  process.exit(falhas ? 1 : 0);
})();
