// Teste automático (Playwright). Rodar todos: bash tests/rodar.sh  ·  só este: node tests/teste_perfil_social.js
// Perfil das pessoas (V11.0): status do dia, personalizar (frase, cor, capa, interesses, ordem/esconder blocos),
// recados (quem pode remover), elogios (aviso no sino, um por tipo por dia, não para si mesmo), conquistas,
// Diretoria (só acompanha) pode deixar recado, texto escapado e celular.
const { chromium } = require('playwright');
const { execSync } = require('child_process');
const BASE = 'http://127.0.0.1:8000';
let falhas = 0; const ok = (c, m) => { console.log((c ? 'OK   ' : 'FALHA') + ' ' + m); if (!c) falhas++; };
const sql = (q) => execSync(`mysql perfeita --default-character-set=utf8mb4 -N -e "${q.replace(/"/g, '\\"')}"`).toString().trim();

(async () => {
  const b = await chromium.launch();
  const erros = [];
  const como = async (email, opcoes = {}) => {
    const p = await (await b.newContext({ viewport: { width: 1440, height: 900 }, ...opcoes })).newPage();
    p.on('pageerror', (e) => erros.push(e.message + ' @ ' + p.url())); await p.route(/open-meteo/, (r) => r.fulfill({ body: '{}' }));
    await p.goto(BASE + '/entrar'); await p.fill('#email', email); await p.fill('#senha', 'Demo@2026'); await p.click('button[type=submit]'); await p.waitForLoadState();
    if (await p.locator('[data-novidades-ok]').count()) { await p.click('[data-novidades-ok]'); await p.waitForLoadState(); }
    return { p, csrf: await p.evaluate(() => document.querySelector('meta[name=csrf]').content) };
  };
  const post = async (s, caminho, form = {}) => { const r = await s.p.request.post(BASE + caminho, { form: { _csrf: s.csrf, ...form } }); return r.status(); };
  const uid = (email) => sql(`select id from usuarios where email='${email}'`);
  const t1 = uid('televendas1@demo.local');

  // ---------- Status do dia (no próprio perfil) ----------
  const eu = await como('televendas1@demo.local');
  await eu.p.goto(BASE + `/pessoas/${t1}`);
  ok(await eu.p.locator('[data-form-status]').count() === 1, 'no meu perfil aparece "Status do dia"');
  await eu.p.check('[data-form-status] input[value="🏖️"]', { force: true });
  await eu.p.fill('[data-form-status] input[name=status_texto]', 'de folga amanhã');
  await Promise.all([eu.p.waitForNavigation(), eu.p.click('[data-form-status] button[type=submit]')]);
  ok((await eu.p.textContent('[data-status]')).includes('de folga amanhã'), 'status publicado aparece no perfil');
  await eu.p.goto(BASE + '/pessoas');
  ok((await eu.p.locator(`[data-pessoa="${t1}"] [data-status]`).innerText()).includes('🏖️'), 'status aparece no cartão da Equipe');
  sql(`update usuario_perfil set status_ate = now() - interval 1 minute where usuario_id=${t1}`);
  await eu.p.goto(BASE + `/pessoas/${t1}`);
  ok(await eu.p.locator('[data-status]').count() === 0, 'status some sozinho depois do prazo');

  // ---------- Personalizar ----------
  await eu.p.goto(BASE + '/meu-perfil#personalizar');
  await eu.p.fill('#pp-frase', 'Bora vender!');
  await eu.p.check('[data-personalizar] input[name=capa_tema][value=oceano]', { force: true });
  await eu.p.check('[data-personalizar] input[name=cor_perfil][value="#7b5ea7"]', { force: true });
  await eu.p.fill('#pp-interesses', 'café, corrida, café');
  await eu.p.fill('#pp-pergunte', 'Excel');
  await eu.p.uncheck('[data-personalizar] input[name="mostrar[familia]"]');
  // sobe "Recados" até o topo com o botão ↑
  for (let i = 0; i < 6; i++) await eu.p.click('[data-ordem-blocos] li:has-text("Recados") [data-mover=cima]');
  await Promise.all([eu.p.waitForNavigation(), eu.p.click('[data-personalizar] button[type=submit]')]);
  ok(sql(`select concat(frase,'|',capa_tema,'|',cor_perfil,'|',interesses) from usuario_perfil where usuario_id=${t1}`) === 'Bora vender!|oceano|#7b5ea7|café, corrida', 'personalização salva (frase, capa, cor, interesses sem repetir)');
  await eu.p.goto(BASE + `/pessoas/${t1}`);
  ok((await eu.p.textContent('[data-frase]')).includes('Bora vender!'), 'frase aparece no perfil');
  ok(await eu.p.locator('.pe-blocos > [data-bloco]').first().getAttribute('data-bloco') === 'recados', 'Recados foi para o primeiro lugar');
  ok(await eu.p.locator('[data-bloco="familia"]').count() === 0, 'bloco escondido (Família) não aparece');
  ok(await eu.p.evaluate(() => getComputedStyle(document.querySelector('.pe-perfil')).getPropertyValue('--pe-cor').trim()) === '#7b5ea7', 'cor do perfil aplicada');
  ok(await eu.p.locator('.pe-tags span', { hasText: '#corrida' }).count() === 1, 'interesses viram etiquetas');

  // ---------- Recados ----------
  const g = await como('gestor@demo.local');
  await g.p.goto(BASE + `/pessoas/${t1}`);
  await g.p.fill('[data-form-recado] textarea', 'Parabéns pela semana! <script>alert(1)</script>');
  await Promise.all([g.p.waitForNavigation(), g.p.click('[data-form-recado] button[type=submit]')]);
  const recado = sql(`select id from perfil_recados where perfil_id=${t1} and texto like 'Parabéns pela semana!%'`);
  ok(!!recado && (await g.p.locator(`[data-recado="${recado}"]`).innerText()).includes('<script>'), 'recado aparece (com o texto escapado, sem rodar script)');
  ok(sql(`select count(*) from notificacoes where usuario_id=${t1} and tipo='perfil_recado'`) !== '0', 'dono do perfil é avisado do recado no sino');
  const t2 = await como('televendas2@demo.local');
  ok(await post(t2, `/pessoas/recados/${recado}/apagar`) === 403 || sql(`select oculto_em is null from perfil_recados where id=${recado}`) === '1', 'outra pessoa não remove o recado');
  ok(sql(`select oculto_em is null from perfil_recados where id=${recado}`) === '1', '...o recado continua');
  await post(eu, `/pessoas/recados/${recado}/apagar`);
  ok(sql(`select oculto_em is not null from perfil_recados where id=${recado}`) === '1', 'o dono do perfil remove o recado');

  // ---------- Elogios ----------
  const antes = Number(sql(`select count(*) from perfil_elogios where para_id=${t1} and oculto_em is null`));
  await g.p.goto(BASE + `/pessoas/${t1}`);
  await g.p.click('.pe-hero-acoes [data-abrir-elogio]');
  ok(await g.p.locator('[data-painel-elogio]').isVisible(), 'botão "Elogiar" abre o painel com os tipos');
  await g.p.check('[data-form-elogio] input[value=ideia_brilhante]', { force: true });
  await g.p.fill('#el-texto', 'Sugeriu a campanha de recompra');
  await Promise.all([g.p.waitForNavigation(), g.p.click('[data-form-elogio] button[type=submit]')]);
  ok(Number(sql(`select count(*) from perfil_elogios where para_id=${t1} and oculto_em is null`)) === antes + 1, 'elogio registrado');
  ok(sql(`select count(*) from notificacoes where usuario_id=${t1} and tipo='perfil_elogio'`) !== '0', 'elogiado(a) recebe aviso no sino');
  ok(Number(await g.p.textContent('[data-total-elogios]')) === antes + 1, 'contador de elogios atualizado no topo do perfil');
  await post(g, `/pessoas/${t1}/elogios`, { tipo: 'ideia_brilhante' });
  ok(Number(sql(`select count(*) from perfil_elogios where para_id=${t1} and oculto_em is null`)) === antes + 1, 'o mesmo elogio só uma vez por dia');
  await post(eu, `/pessoas/${t1}/elogios`, { tipo: 'ajudou_muito' });
  ok(sql(`select count(*) from perfil_elogios where para_id=${t1} and de_id=${t1}`) === '0', 'ninguém se elogia');
  await post(g, `/pessoas/${t1}/elogios`, { tipo: 'nao_existe' });
  ok(Number(sql(`select count(*) from perfil_elogios where para_id=${t1} and oculto_em is null`)) === antes + 1, 'tipo de elogio inventado é recusado');

  // ---------- Conquistas (só selos, sem valores) ----------
  await g.p.goto(BASE + `/pessoas/${t1}`);
  const selos = await g.p.locator('[data-bloco="conquistas"]').innerText();
  ok(/anos? de casa|Chegou agora/.test(selos), 'conquista de tempo de casa');
  ok(!selos.includes('R$'), 'conquistas não mostram valores em reais');

  // ---------- Diretoria (só acompanha) pode deixar recado ----------
  const d = await como('diretoria@demo.local');
  await post(d, `/pessoas/${t1}/recados`, { texto: 'Recado da diretoria' });
  ok(sql(`select count(*) from perfil_recados where perfil_id=${t1} and texto='Recado da diretoria'`) === '1', 'Diretoria (modo leitura) pode deixar recado');

  // ---------- Celular ----------
  const cel = await como('gestor@demo.local', { viewport: { width: 390, height: 844 }, isMobile: true });
  for (const u of [`/pessoas/${t1}`, '/pessoas', '/meu-perfil']) {
    await cel.p.goto(BASE + u);
    ok(await cel.p.evaluate(() => document.documentElement.scrollWidth) <= 392, `${u} cabe no celular`);
  }

  ok(erros.length === 0, 'nenhum erro de JavaScript' + (erros.length ? ': ' + erros.join(' / ') : ''));
  await b.close();
  console.log(falhas ? `\n${falhas} FALHA(S)` : '\nTudo certo.');
  process.exit(falhas ? 1 : 0);
})();
