// Teste automático (Playwright). Rodar todos: bash tests/rodar.sh  ·  só este: node tests/teste_especialistas.js
// Especialistas do One (V15.0): cada pessoa vê o One geral + o especialista do seu setor (admin vê todos), o chat abre no
// especialista do setor, troca os exemplos ao trocar, registra o uso por especialista, não deixa usar especialista de outro setor;
// Administração edita instruções e desliga; com IA (simulada) cada especialista só recebe as suas consultas.
const { chromium } = require('playwright');
const { execSync, spawn } = require('child_process');
const BASE = 'http://127.0.0.1:8000';
let falhas = 0; const ok = (c, m) => { console.log((c ? 'OK   ' : 'FALHA') + ' ' + m); if (!c) falhas++; };
const sql = (q) => execSync(`mysql perfeita --default-character-set=utf8mb4 -N -e "${q.replace(/"/g, '\\"')}"`).toString().trim();

const entrar = async (b, email) => {
  const p = await (await b.newContext({ viewport: { width: 1360, height: 900 } })).newPage(); p.erros = []; p.on('pageerror', (e) => p.erros.push(e.message));
  await p.route(/open-meteo/, (r) => r.fulfill({ body: '{}' }));
  await p.goto(BASE + '/entrar'); await p.fill('#email', email); await p.fill('#senha', 'Demo@2026'); await p.click('button[type=submit]'); await p.waitForLoadState();
  if (await p.locator('[data-novidades-ok]').count()) { await p.click('[data-novidades-ok]'); await p.waitForLoadState(); }
  await p.evaluate(() => localStorage.setItem('one-balao:/:' + new Date().toISOString().slice(0, 10), 'nao'));
  return p;
};
const abrirChat = async (p) => { await p.click('[data-one-abrir]'); await p.click('[data-one-aba="perguntar"]'); };
const chips = (p) => p.locator('[data-one-especialista]').evaluateAll((l) => l.map((b) => b.dataset.oneEspecialista));
const selecionado = (p) => p.locator('[data-one-especialista][aria-selected="true"]').getAttribute('data-one-especialista');

(async () => {
  const b = await chromium.launch(); const paginas = [];
  try {
    ok(sql('select count(*) from one_especialistas where ativo=1') === '7', '7 especialistas cadastrados (One geral + Vendas, Prospecção, Marketing, Formulação, Cobrança, Terceirização)');
    // Vendedora
    const v = await entrar(b, 'televendas1@demo.local'); paginas.push(v);
    await abrirChat(v);
    ok(JSON.stringify(await chips(v)) === JSON.stringify(['geral', 'vendas']) && await selecionado(v) === 'vendas', 'vendedora: vê o One e o especialista de Vendas, e o chat abre em Vendas');
    ok((await v.locator('[data-one-exemplos]').innerText()).includes('Para quem eu ligo hoje?'), 'exemplos de pergunta do especialista de Vendas');
    await v.click('[data-one-especialista="geral"]');
    ok(await selecionado(v) === 'geral' && (await v.locator('[data-one-exemplos]').innerText()).includes('orçamento'), 'trocar para o One geral troca os exemplos');
    await v.click('[data-one-especialista="vendas"]');
    await v.click('[data-one-exemplo]:has-text("meta")'); await v.waitForSelector('.one-msg.one:not(.pensando)');
    ok(sql("select especialista from one_perguntas order by id desc limit 1") === 'vendas', 'a pergunta fica registrada como do especialista de Vendas (sem o texto)');
    const csrf = await v.locator('meta[name=csrf]').getAttribute('content');
    const r = await (await v.request.post(BASE + '/one/perguntar', { form: { pergunta: 'quanto custa a fórmula?', especialista: 'formulacao', _csrf: csrf }, headers: { Accept: 'application/json' } })).json();
    ok(r.especialista === 'vendas' && sql("select especialista from one_perguntas order by id desc limit 1") === 'vendas', 'vendedora não consegue usar o especialista de Formulação (cai no dela)');
    await v.reload(); await abrirChat(v);
    ok(await selecionado(v) === 'vendas', 'o chat lembra o especialista escolhido');

    // Responsável Técnica e Admin
    const t = await entrar(b, 'tecnica@demo.local'); paginas.push(t);
    await abrirChat(t);
    ok(await selecionado(t) === 'formulacao' && (await t.locator('[data-one-exemplos]').innerText()).includes('cápsulas'), 'Responsável Técnica: abre no especialista de Formulação');
    const a = await entrar(b, 'admin@demo.local'); paginas.push(a);
    await abrirChat(a);
    ok((await chips(a)).length === 7, 'admin vê os 7 especialistas');

    // Administração
    await a.goto(BASE + '/administracao/especialistas');
    ok((await a.locator('[data-especialista]').count()) === 7, 'Administração › Especialistas do One lista todos');
    const cartao = a.locator('[data-especialista="cobranca"]');
    await cartao.locator('[data-instrucoes]').fill('Seja muito gentil. Priorize promessas vencidas.');
    await cartao.locator('input[name=ativo]').uncheck();
    await Promise.all([a.waitForNavigation(), cartao.locator('button:has-text("Salvar")').click()]);
    ok(sql("select concat(ativo,'|',instrucoes,'|',editado_em is not null) from one_especialistas where slug='cobranca'") === '0|Seja muito gentil. Priorize promessas vencidas.|1', 'admin edita as instruções e desliga um especialista');
    ok(sql("select count(*) from auditoria where acao='one_especialista'") === '1', 'a mudança fica na auditoria');
    const f = await entrar(b, 'financeiro@demo.local'); paginas.push(f);
    await abrirChat(f);
    ok((await f.locator('[data-one-especialistas]').count()) === 0, 'especialista desligado some do chat de quem é da Cobrança');
    const vend403 = await v.request.post(BASE + '/administracao/especialistas/1', { form: { instrucoes: 'invasão', _csrf: csrf } });
    ok(vend403.status() === 403, 'vendedora não edita especialistas (403)');

    // Com IA (simulada)
    const sim = spawn('php', ['-S', '127.0.0.1:8099', 'tests/recursos/anthropic_simulada.php'], { stdio: 'ignore' });
    await new Promise((res) => setTimeout(res, 800));
    const saida = execSync('php tests/recursos/teste_especialistas_ia.php').toString();
    sim.kill();
    saida.trim().split('\n').forEach((l) => ok(l.startsWith('OK'), l.replace(/^(OK|FALHA)\s+/, '')));

    // Celular
    const cel = await (await b.newContext({ viewport: { width: 390, height: 800 } })).newPage(); paginas.push(cel); cel.erros = [];
    await cel.route(/open-meteo/, (x) => x.fulfill({ body: '{}' }));
    await cel.goto(BASE + '/entrar'); await cel.fill('#email', 'admin@demo.local'); await cel.fill('#senha', 'Demo@2026'); await cel.click('button[type=submit]'); await cel.waitForLoadState();
    if (await cel.locator('[data-novidades-ok]').count()) { await cel.click('[data-novidades-ok]'); await cel.waitForLoadState(); }
    await abrirChat(cel);
    ok(await cel.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth + 1), 'celular: a fileira de especialistas rola de lado sem quebrar a tela');
    const erros = paginas.flatMap((p) => p.erros || []);
    ok(erros.length === 0, 'sem erros de JavaScript' + (erros.length ? ': ' + erros[0] : ''));
  } finally {
    await b.close();
  }
  process.exit(falhas ? 1 : 0);
})();
