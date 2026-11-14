// Teste automático (Playwright). Rodar todos: bash tests/rodar.sh  ·  só este: node tests/teste_ligacoes.js
// Ligue Talk (modo simulado) e Gestão › Acompanhamento: ligar, gravações, números por vendedora, discador e acessos.
const path = require('path');
const { chromium } = require('playwright');
const { execSync } = require('child_process');
const BASE = 'http://127.0.0.1:8000';
let falhas = 0; const ok = (c, m) => { console.log((c ? 'OK   ' : 'FALHA') + ' ' + m); if (!c) falhas++; };
const sql = (q) => execSync(`mysql perfeita --default-character-set=utf8mb4 -N -e "${q.replace(/"/g, '\\"')}"`).toString().trim();
async function entrar(p, email) {
  await p.goto(BASE + '/entrar'); await p.fill('#email', email); await p.fill('#senha', 'Demo@2026'); await p.click('button[type=submit]'); await p.waitForLoadState();
  if (await p.locator('[data-novidades-ok]').count()) { await p.click('[data-novidades-ok]'); await p.waitForLoadState(); }
}
const aviso = async (p) => (await p.locator('.aviso').allInnerTexts()).join(' | ');

(async () => {
  const b = await chromium.launch();
  const erros = [];
  const nova = async () => { const p = await (await b.newContext({ viewport: { width: 1300, height: 900 } })).newPage(); p.on('pageerror', e => erros.push(e.message)); await p.route(/open-meteo|jsdelivr/, r => r.fulfill({ body: '{}' })); p.on('dialog', d => d.accept()); return p; };
  const uid = (email) => sql(`select id from usuarios where email='${email}'`);
  const tv1 = uid('televendas1@demo.local'), tv2 = uid('televendas2@demo.local'), ext = uid('externo1@demo.local');

  ok(Number(sql('select count(*) from ligacoes')) > 200 && Number(sql('select count(*) from ligacoes where gravacao is not null')) > 100, 'demonstração: ligações e gravações de exemplo');
  ok(sql("select valor from configuracoes where chave='telefonia.modo'") === 'simulado', 'modo simulado na instalação de demonstração');

  // ---------- Ligar pela ficha (simulado) ----------
  const v = await nova();
  await entrar(v, 'televendas1@demo.local');
  const cli = sql(`select id from clientes where responsavel_id=${tv1} and telefone is not null and excluido_em is null limit 1`);
  const antes = Number(sql(`select count(*) from ligacoes where usuario_id=${tv1}`));
  await v.goto(BASE + `/carteira/clientes/${cli}`);
  await v.click('[data-ligar] button');
  await v.waitForFunction((n) => true, antes);
  await v.waitForTimeout(800);
  ok(Number(sql(`select count(*) from ligacoes where usuario_id=${tv1}`)) === antes + 1 && sql(`select cliente_id from ligacoes where usuario_id=${tv1} order by id desc limit 1`) === cli, '"Ligar" registra a ligação da vendedora com o cliente');
  ok(sql(`select count(*) from cliente_eventos where cliente_id=${cli} and tipo='ligacao' and descricao like 'Ligação pelo Ligue Talk%'`) !== '0', 'a ligação aparece na linha do tempo do cliente');

  // ---------- Acompanhamento (gestor) ----------
  const g = await nova();
  await entrar(g, 'gestor@demo.local');
  await g.goto(BASE + '/acompanhamento');
  const linha = g.locator(`[data-pessoa="${tv1}"]`);
  const ini = new Date(); const de = `${ini.getFullYear()}-${String(ini.getMonth() + 1).padStart(2, '0')}-01`;
  const esperadas = sql(`select count(*) from ligacoes where usuario_id=${tv1} and inicio >= '${de}'`);
  ok((await linha.locator('[data-ligacoes]').innerText()) === esperadas, `ligações do mês da Televendas 1 batem com o banco (${esperadas})`);
  const contatos = sql(`select count(*) from cliente_eventos where usuario_id=${tv1} and tipo in ('contato','ligacao','whatsapp','visita','email') and ocorrido_em >= '${de}'`);
  ok((await linha.locator('[data-contatos]').innerText()) === contatos, `contatos do mês batem (${contatos})`);
  ok(Number(await g.locator('[data-reativacoes]').evaluateAll((l) => l.reduce((t, x) => t + Number(x.innerText), 0))) > 0, 'demonstração tem reativações');
  await g.goto(BASE + '/acompanhamento?periodo=hoje');
  ok(Number(await g.locator(`[data-pessoa="${tv1}"] [data-ligacoes]`).innerText()) >= 1, 'filtro "Hoje"');

  // ---------- Detalhe e gravação ----------
  await g.goto(BASE + `/acompanhamento/pessoa/${tv1}`);
  ok(await g.locator('audio[data-gravacao]').count() > 0, 'detalhe com o player das gravações');
  const lig = sql(`select id from ligacoes where usuario_id=${tv1} and gravacao is not null limit 1`);
  const audio = await g.request.get(BASE + `/ligacoes/${lig}/gravacao`);
  ok(audio.status() === 200 && audio.headers()['content-type'] === 'audio/wav', 'gravação toca pelo sistema para o gestor');
  ok(sql(`select count(*) from auditoria where entidade='ligacao' and entidade_id=${lig} and acao='ouvir_gravacao'`) === '1', 'auditoria registra quem ouviu');
  ok((await v.request.get(BASE + `/ligacoes/${lig}/gravacao`)).status() === 200, 'a própria vendedora ouve a gravação dela');
  const outra = await nova();
  await entrar(outra, 'televendas2@demo.local');
  ok((await outra.request.get(BASE + `/ligacoes/${lig}/gravacao`)).status() === 404, 'outra vendedora não ouve');
  ok((await (await b.newContext()).request.get(BASE + `/ligacoes/${lig}/gravacao`, { maxRedirects: 0 })).status() === 302, 'sem login não ouve');
  ok((await outra.goto(BASE + '/acompanhamento')).status() === 403, 'vendedora comum não abre o Acompanhamento');

  // ---------- Gestora: só a equipe dela ----------
  const ge = await nova();
  await entrar(ge, 'gestora.televendas@demo.local');
  await ge.goto(BASE + '/acompanhamento');
  const pessoas = await ge.$$eval('[data-pessoa]', (l) => l.map((x) => x.dataset.pessoa));
  ok(pessoas.includes(tv1) && pessoas.includes(tv2) && !pessoas.includes(ext), 'gestora vê só a equipe do Televendas');
  await ge.goto(BASE + `/acompanhamento/pessoa/${ext}`);
  ok((await aviso(ge)).includes('fora da sua equipe'), 'gestora não abre pessoa de outro setor');
  const ligExt = sql(`select id from ligacoes where usuario_id=${ext} and gravacao is not null limit 1`);
  ok((await ge.request.get(BASE + `/ligacoes/${ligExt}/gravacao`)).status() === 404, 'gestora não ouve gravação de outro setor');

  // ---------- Discador (simulado) ----------
  const k = sql(`select id from carteiras where responsavel_id=${tv1} limit 1`);
  await ge.goto(BASE + `/carteiras/${k}`);
  await Promise.all([ge.waitForNavigation(), ge.click('button:has-text("Enviar para o discador")')]);
  ok((await aviso(ge)).includes('enviados para o discador'), 'carteira enviada para o discador do Ligue Talk');
  ok(sql("select count(*) from auditoria where entidade='telefonia' and acao='enviar_discador'") === '1', 'envio ao discador fica na auditoria');

  // ---------- Usuário no Ligue Talk e modo manual ----------
  const adm = await nova();
  await entrar(adm, 'admin@demo.local');
  await adm.goto(BASE + `/usuarios/${tv1}/editar`);
  await adm.fill('#ramal', '5644');
  await Promise.all([adm.waitForNavigation(), adm.click('button:has-text("Salvar alterações")')]);
  ok(sql(`select ramal from usuarios where id=${tv1}`) === '5644', 'admin cadastra o usuário da pessoa no Ligue Talk');
  sql("update configuracoes set valor='manual' where chave='telefonia.modo'");
  const r = await v.request.post(BASE + `/carteira/clientes/${cli}/ligar`, { form: { _csrf: await v.evaluate(() => document.querySelector('meta[name=csrf]').content) }, headers: { Accept: 'application/json' } });
  const j = await r.json();
  ok(j.ok && j.manual && /^\+55\d{10,11}$/.test(j.tel), 'modo manual: "Ligar" abre o telefone (tel:)');
  ok(execSync('php cron/sincronizar_ligacoes.php', { cwd: path.join(__dirname, '..') }).toString().includes('0 ligação'), 'sincronização não faz nada fora do modo "api"');

  ok(erros.length === 0, 'nenhum erro de JavaScript' + (erros.length ? ': ' + erros.join(' / ') : ''));
  await b.close();
  console.log(falhas ? `\n${falhas} FALHA(S)` : '\nTudo certo.');
  process.exit(falhas ? 1 : 0);
})();
