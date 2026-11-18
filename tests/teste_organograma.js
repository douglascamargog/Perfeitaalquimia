// V17.8/V17.9 — Organograma (só o desenvolvedor do sistema): interligações, o que falta ligar e a organização;
// seletor "ver o One como" do desenvolvedor e menu lateral em sanfona.
// Rodar com o banco de TESTE e o servidor local no ar.
const { chromium, devices } = require('playwright');
const { execSync } = require('child_process');
const BASE = 'http://127.0.0.1:8000';
let falhas = 0; const ok = (c, m) => { console.log((c ? 'OK   ' : 'FALHA') + ' ' + m); if (!c) falhas++; };
const sql = (q) => execSync(`mysql perfeita --default-character-set=utf8mb4 -N -e "${q.replace(/"/g, '\\"')}"`).toString().trim();
const entrar = async (p, email) => { await p.goto(BASE + '/entrar'); await p.fill('#email', email); await p.fill('#senha', 'Demo@2026'); await p.click('button[type=submit]'); await p.waitForLoadState(); };

(async () => {
  const nav = await chromium.launch(); const erros = [];
  const nova = async (opts = {}) => { const p = await (await nav.newContext(opts)).newPage(); p.on('pageerror', (e) => erros.push(e.message)); await p.route(/open-meteo/, (r) => r.fulfill({ body: '{}' })); return p; };
  try {
    // 0) ninguém é desenvolvedor: nem o Admin vê as telas de desenvolvimento; ele se marca na Administração
    sql("update usuarios set desenvolvedor = 0, ver_como_funcao_id = null");
    const a = await nova({ viewport: { width: 1440, height: 1000 } });
    await entrar(a, 'admin@demo.local');
    ok((await a.request.get(BASE + '/organograma')).status() === 403 && (await a.locator('nav a[href$="/sistema/atualizacao"]').count()) === 0, 'sem desenvolvedor: nem o Admin abre o Organograma (e a Atualização some do menu)');
    await a.goto(BASE + '/administracao');
    await Promise.all([a.waitForNavigation(), a.click('[data-sou-desenvolvedor]')]);
    ok(sql("select desenvolvedor from usuarios where email='admin@demo.local'") === '1', '"Eu sou o desenvolvedor do sistema" marca quem clicou');
    ok((await a.locator('[data-sou-desenvolvedor]').count()) === 0, 'depois de marcado, o botão some (só um desenvolvedor marca outro)');

    // 1) desenvolvedor: diagrama completo, com números vivos
    await a.goto(BASE + '/organograma');
    ok((await a.locator('nav a[href$="/organograma"]').count()) >= 1, 'Organograma aparece no menu');
    ok((await a.locator('[data-org-desenho] svg [data-setor]').count()) === 18, 'diagrama com os 18 setores e sistemas');
    const ligacoes = await a.locator('[data-org-desenho] svg .org-lig').count();
    ok(ligacoes === 31, `diagrama com as ligações (${ligacoes})`);
    ok((await a.locator('[data-setor="cobranca"] .org-no-num').count()) === 1, 'gestão vê o número vivo de cada setor (casos abertos na Cobrança)');

    // 2) clicar num setor: destaca as ligações e mostra o que recebe e entrega
    await a.click('[data-setor="cobranca"]');
    ok((await a.locator('.org-lig.ativa').count()) === 3, 'clicar na Cobrança destaca as 3 ligações dela');
    const det = (await a.locator('[data-org-detalhe-setor]').innerText()).toLowerCase();
    ok(det.includes('recebe de') && det.includes('entrega para') && det.includes('omie'), 'quadro do setor embaixo do diagrama: recebe de / entrega para');
    ok((await a.locator('[data-org-detalhe-setor] a.botao').getAttribute('href')).endsWith('/cobranca'), 'botão leva para a tela do setor');
    await a.click('[data-org-limpar]');
    ok(await a.locator('[data-org-detalhe]').isHidden(), '"Fechar" volta ao diagrama inteiro');

    // 3) filtro: só o que falta ligar
    await a.click('[data-org-filtro="falta"]');
    ok(await a.locator('.org-lig.ok').first().isHidden() && await a.locator('.org-lig.ilha').first().isVisible(), '"Só o que falta ligar" esconde as integradas');

    // 3b) o que falta ligar: aba própria
    await a.click('[data-org-aba="falta"]');
    ok((await a.locator('[data-org-falta]').count()) === 5 && !(await a.locator('[data-org-desenho]').isVisible()), 'aba "O que falta ligar" com as 5 pendências');

    // 4) organização: áreas, funções e pessoas
    await a.click('[data-org-aba="organizacao"]');
    ok(await a.locator('[data-org-arvore]').isVisible() && !(await a.locator('[data-org-desenho]').isVisible()), 'aba Organização troca a visão');
    ok((await a.locator('[data-org-area="comercial"] [data-org-funcao="televendas"] .org-pessoa').count()) >= 1, 'Comercial › Televendas com as pessoas da função');
    ok((await a.locator('[data-org-area="diretoria"]').count()) === 1, 'Diretoria no topo');

    // 5) a área se muda em Funções
    const tecnica = sql("select id from funcoes where slug='tecnica'");
    await a.goto(BASE + '/funcoes');
    await a.selectOption(`select[name=area][form="funcao-${tecnica}"]`, 'diretoria');
    await Promise.all([a.waitForNavigation(), a.locator(`#funcao-${tecnica} button`).click()]);
    ok(sql(`select area from funcoes where id=${tecnica}`) === 'diretoria', 'Funções: área no organograma gravada');
    await a.goto(BASE + '/organograma#organizacao');
    ok((await a.locator('[data-org-area="diretoria"] [data-org-funcao="tecnica"]').count()) === 1, 'a função aparece na área escolhida');
    sql(`update funcoes set area='operacao' where id=${tecnica}`);

    // 6) vendedora: não vê o Organograma nem as telas de desenvolvimento
    const v = await nova({ viewport: { width: 1280, height: 900 } });
    await entrar(v, 'televendas1@demo.local');
    ok((await v.request.get(BASE + '/organograma')).status() === 403 && (await v.locator('nav a[href$="/organograma"]').count()) === 0 && (await v.locator('[data-dev-modo]').count()) === 0,
      'vendedora: sem Organograma no menu, sem acesso e sem o seletor');

    // 6b) seletor "ver o One como": Gestor de Marketing
    const gm = sql("select id from funcoes where slug='gestor_marketing'");
    await a.goto(BASE + '/');
    await a.click('[data-dev-modo] summary');
    await Promise.all([a.waitForNavigation(), a.click(`[data-dev-opcao="gestor_marketing"]`)]);
    ok(sql("select ver_como_funcao_id from usuarios where email='admin@demo.local'") === gm, 'seletor grava o modo "Gestor de Marketing"');
    ok(await a.locator('[data-dev-faixa]').isVisible() && (await a.locator('.usuario-nome span').innerText()).includes('Gestor de Marketing'), 'faixa avisa o modo e o topo mostra só essa função');
    ok((await a.request.get(BASE + '/organograma')).status() === 403 && (await a.request.get(BASE + '/usuarios')).status() === 403, 'no modo função: sem Organograma e sem o que o perfil não pode (Usuários)');
    ok((await a.request.get(BASE + '/marketing')).status() === 200, 'no modo função: abre o que o Gestor de Marketing usa');
    await Promise.all([a.waitForNavigation(), a.click('[data-dev-faixa] button')]);
    ok(sql("select coalesce(ver_como_funcao_id, 0) from usuarios where email='admin@demo.local'") === '0' && (await a.request.get(BASE + '/organograma')).status() === 200, '"Voltar ao modo desenvolvedor" devolve tudo');

    // 6c) menu em sanfona: abrir um grupo fecha os outros
    const grupos = a.locator('[data-menu-secao] .menu-secao-botao');
    await grupos.nth(0).click(); await grupos.nth(1).click();
    ok((await a.locator('[data-menu-secao].aberta').count()) === 1, 'menu: abrir um submenu fecha o outro');

    // 7) celular: a página não rola de lado (o diagrama rola dentro do quadro)
    const c = await nova({ ...devices['iPhone 13'] });
    await entrar(c, 'admin@demo.local');
    await c.goto(BASE + '/organograma');
    ok(await c.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth + 1), 'celular: sem rolagem lateral da página');
    await c.goto(BASE + '/organograma#organizacao');
    ok(await c.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth + 1), 'celular: organização cabe na tela');

    ok(erros.length === 0, 'sem erros de JavaScript' + (erros.length ? ': ' + erros.join(' | ') : ''));
  } catch (e) { console.log('FALHA', e.message); falhas++; }
  await nav.close();
  console.log(falhas ? `\n${falhas} falha(s)` : '\nTudo certo.');
  process.exit(falhas ? 1 : 0);
})();
