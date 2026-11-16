// Teste automático (Playwright). Rodar todos: bash tests/rodar.sh  ·  só este: node tests/teste_one_reacoes.js
// Reações do One (V13.6): depois do horário de descanso ele dorme (botão, saudação do Início) e avisa UMA vez por dia;
// no aniversário da pessoa o Início mostra o One com balões; com retornos atrasados, preocupado; imagens animadas existem.
const { chromium } = require('playwright');
const { execSync } = require('child_process');
const BASE = 'http://127.0.0.1:8000';
let falhas = 0; const ok = (c, m) => { console.log((c ? 'OK   ' : 'FALHA') + ' ' + m); if (!c) falhas++; };
const sql = (q) => execSync(`mysql perfeita --default-character-set=utf8mb4 -N -e "${q.replace(/"/g, '\\"')}"`).toString().trim();

async function entrar(b, email) {
  const p = await (await b.newContext({ viewport: { width: 1360, height: 860 } })).newPage();
  p.erros = []; p.on('pageerror', (e) => p.erros.push(e.message));
  await p.route(/open-meteo/, (r) => r.fulfill({ body: '{}' }));
  await p.goto(BASE + '/entrar'); await p.fill('#email', email); await p.fill('#senha', 'Demo@2026'); await p.click('button[type=submit]'); await p.waitForLoadState();
  if (await p.locator('[data-novidades-ok]').count()) { await p.click('[data-novidades-ok]'); await p.waitForLoadState(); }
  return p;
}
const heroi = (p) => p.locator('[data-hero-one]').getAttribute('data-hero-one');

(async () => {
  const b = await chromium.launch();
  for (const r of ['ola', 'piscando', 'feliz', 'festa', 'pensando', 'ideia', 'ops', 'bravo', 'furioso', 'correndo', 'dormindo']) {
    const resp = await (await b.newContext()).request.get(BASE + `/assets/img/one/one-${r}-padrao.webp`);
    if (!resp.ok()) ok(false, 'imagem do One: ' + r);
  }
  ok(true, 'as 11 reações animadas do One existem');

  // Hora de descansar (configuração vale já)
  sql("update configuracoes set valor='00:00' where chave='one.hora_descanso'");
  sql("update usuarios set data_nascimento='1990-01-01' where email='televendas2@demo.local'");
  const p = await entrar(b, 'televendas2@demo.local');
  ok((await p.locator('[data-one-abrir] img').getAttribute('src')).includes('one-dormindo'), 'depois do horário de descanso: o One do canto está dormindo');
  ok(await heroi(p) === 'dormindo' && (await p.locator('[data-saudacao-frase]').innerText()).includes('hora de descansar'), 'Início: One dormindo e "hora de descansar" na saudação');
  await p.waitForSelector('[data-one-balao]', { timeout: 6000 });
  ok((await p.locator('[data-one-balao]').innerText()).includes('Hora de descansar'), 'o One avisa num balão que é hora de descansar');
  await p.click('[data-one-balao-fechar]'); await p.waitForTimeout(300);
  await p.goto(BASE + '/agenda'); await p.waitForTimeout(2200);
  ok(!(await p.locator('[data-one-balao]').innerText().catch(() => '')).includes('Hora de descansar'), 'o aviso de descanso aparece uma vez só por dia');

  // Aniversário: balões (vale até depois do horário)
  const hoje = new Date(Date.now() - 3 * 3600 * 1000).toISOString().slice(5, 10);
  sql(`update usuarios set data_nascimento='1990-${hoje}' where email='televendas2@demo.local'`);
  await p.goto(BASE + '/');
  ok(await heroi(p) === 'festa' && (await p.locator('[data-saudacao-frase]').innerText()).includes('Feliz aniversário'), 'no aniversário da pessoa: Início com o One de balões e "Feliz aniversário"');
  sql("update usuarios set data_nascimento='1990-01-01' where email='televendas2@demo.local'");

  // Fora do descanso: retornos atrasados → preocupado
  sql("update configuracoes set valor='' where chave='one.hora_descanso'");
  const t = await entrar(b, 'televendas1@demo.local');
  const atrasadas = Number(sql("select count(*) from tarefas t join usuarios u on u.id=t.usuario_id where u.email='televendas1@demo.local' and t.concluida_em is null and t.vence_em < curdate()"));
  ok(atrasadas > 0 ? await heroi(t) === 'ops' && (await t.locator('[data-saudacao-frase]').innerText()).includes('atrasado') : true, 'com retornos atrasados: One preocupado e o aviso na saudação');
  ok(!(await t.locator('[data-one-abrir] img').getAttribute('src')).includes('dormindo'), 'horário de descanso vazio: o One não dorme');
  sql("update configuracoes set valor='17:45' where chave='one.hora_descanso'");

  const erros = [p, t].flatMap((x) => x.erros);
  ok(erros.length === 0, 'sem erros de JavaScript' + (erros[0] ? ': ' + erros[0] : ''));
  await b.close();
  process.exit(falhas ? 1 : 0);
})();
