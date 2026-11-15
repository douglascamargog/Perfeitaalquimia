// Teste automático (Playwright). Rodar todos: bash tests/rodar.sh  ·  só este: node tests/teste_pergunte_one.js
// Pergunte ao One (V12.4): sem IA (padrão) responde com as aulas, os números da pessoa e as campanhas; mensagem com HTML
// não vira código; limpar conversa; uso registrado SEM o texto (LGPD); com IA: contra a API simulada (tests/recursos).
const { chromium } = require('playwright');
const { execSync, spawn } = require('child_process');
const BASE = 'http://127.0.0.1:8000';
let falhas = 0; const ok = (c, m) => { console.log((c ? 'OK   ' : 'FALHA') + ' ' + m); if (!c) falhas++; };
const sql = (q) => execSync(`mysql perfeita --default-character-set=utf8mb4 -N -e "${q.replace(/"/g, '\\"')}"`).toString().trim();

(async () => {
  const b = await chromium.launch();
  const erros = [];
  const p = await (await b.newContext({ viewport: { width: 1360, height: 900 } })).newPage();
  p.on('pageerror', (e) => erros.push(e.message)); await p.route(/open-meteo/, (r) => r.fulfill({ body: '{}' }));
  let alerta = false; p.on('dialog', (d) => { alerta = true; d.dismiss(); });
  await p.goto(BASE + '/entrar'); await p.fill('#email', 'televendas1@demo.local'); await p.fill('#senha', 'Demo@2026'); await p.click('button[type=submit]'); await p.waitForLoadState();
  if (await p.locator('[data-novidades-ok]').count()) { await p.click('[data-novidades-ok]'); await p.waitForLoadState(); }
  await p.evaluate(() => localStorage.setItem('one-balao:/:' + new Date().toISOString().slice(0, 10), 'nao'));
  await p.click('[data-one-abrir]');
  ok((await p.locator('.one-pergunte-nota').innerText()).includes('aulas'), 'sem IA ligada (padrão): o One avisa que responde com as aulas e os dados do sistema');
  const respostas = () => p.locator('.one-msg.one:not(.pensando)');
  const perguntar = async (t) => { const n = await respostas().count(); await p.fill('[data-one-pergunte] input', t); await p.press('[data-one-pergunte] input', 'Enter'); await p.waitForFunction((x) => document.querySelectorAll('.one-msg.one:not(.pensando)').length > x, n); return respostas().last().innerText(); };

  await p.click('[data-one-exemplo]:has-text("orçamento")'); await p.waitForSelector('.one-msg.one:not(.pensando)');
  ok((await respostas().last().innerText()).includes('Orçamentos') && await respostas().last().locator('a[href$="/orcamentos"]').count() === 1, 'exemplo "Como faço um orçamento?": aponta a tela com link');
  ok((await perguntar('quanto falta para a minha meta?')).includes('Meta do mês'), 'meta: responde com os números da própria pessoa');
  ok((await perguntar('onde troco minha senha?')).includes('Meu perfil'), 'dúvida comum (senha): resposta direta');
  ok((await perguntar('quais campanhas estão no ar?')).match(/Campanhas no ar|Nenhuma campanha/), 'campanhas: lista o que está no ar');
  await perguntar('<img src=x onerror=alert(1)> teste');
  ok(!alerta && await p.locator('.one-conversa img[src="x"]').count() === 0 && (await p.locator('.one-msg.pessoa').last().innerText()).includes('<img'), 'pergunta com HTML aparece como texto (não vira código)');
  ok(sql("select count(*) from one_perguntas where provedor='local'") >= '4', 'cada pergunta registra o uso');
  ok(sql("select count(*) from information_schema.columns where table_schema='perfeita' and table_name='one_perguntas' and column_name in ('pergunta','resposta','texto')") === '0', 'o texto das perguntas NÃO é guardado (LGPD)');
  await p.click('[data-one-limpar]');
  ok(await p.locator('.one-msg').count() === 0 && await p.locator('[data-one-sugestoes]').isVisible(), '"limpar conversa" recomeça');

  // Administração: resumo de uso
  const a = await (await b.newContext()).newPage();
  await a.goto(BASE + '/entrar'); await a.fill('#email', 'admin@demo.local'); await a.fill('#senha', 'Demo@2026'); await a.click('button[type=submit]'); await a.waitForLoadState();
  await a.goto(BASE + '/administracao');
  ok((await a.locator('[data-uso-one]').innerText()).includes('pergunta(s) este mês'), 'Administração mostra o uso do Pergunte ao One');

  // Com IA: contra a API simulada
  const sim = spawn('php', ['-S', '127.0.0.1:8099', 'tests/recursos/anthropic_simulada.php'], { stdio: 'ignore' });
  await new Promise((r) => setTimeout(r, 800));
  const saida = execSync('php tests/recursos/teste_one_ia.php').toString();
  sim.kill();
  saida.trim().split('\n').forEach((l) => ok(l.startsWith('OK'), l.replace(/^(OK|FALHA)\s+/, '')));

  ok(erros.length === 0, 'sem erros de JavaScript' + (erros.length ? ': ' + erros.join(' | ') : ''));
  await b.close();
  console.log(falhas ? `\n${falhas} FALHA(S)` : '\nTudo certo.');
  process.exit(falhas ? 1 : 0);
})();
