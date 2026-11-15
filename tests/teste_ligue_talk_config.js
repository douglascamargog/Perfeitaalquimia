// Teste automático (Playwright). Rodar todos: bash tests/rodar.sh  ·  só este: node tests/teste_ligue_talk_config.js
// Ligue Talk pela tela (V12.7): Administração guarda company_id e token em config/segredos.php (600, nunca mostra o token),
// "Testar conexão" contra um Ligue Talk simulado (token errado → explica; certo → lista usuários e quem já está ligado),
// modo "api" passa a valer, "Remover" volta ao manual, e só o admin consegue gravar.
const { chromium } = require('playwright');
const { execSync, spawn } = require('child_process');
const fs = require('fs');
const BASE = 'http://127.0.0.1:8000';
let falhas = 0; const ok = (c, m) => { console.log((c ? 'OK   ' : 'FALHA') + ' ' + m); if (!c) falhas++; };
const sql = (q) => execSync(`mysql perfeita --default-character-set=utf8mb4 -N -e "${q.replace(/"/g, '\\"')}"`).toString().trim();
const SEG = 'config/segredos.php';

(async () => {
  const simulado = spawn('php', ['-S', '127.0.0.1:8097', 'tests/recursos/liguetalk_simulado.php'], { stdio: 'ignore' });
  await new Promise((r) => setTimeout(r, 700));
  const antes = fs.existsSync(SEG) ? fs.readFileSync(SEG, 'utf8') : null;
  // endereço do Ligue Talk apontando para o simulado + uma chave da IA já guardada (não pode se perder)
  fs.writeFileSync(SEG, "<?php\nreturn ['integracoes' => ['anthropic' => ['chave' => 'sk-ant-preservada'], 'ligue_talk' => ['url' => 'http://127.0.0.1:8097/index.php/API_control']]];\n");
  sql("update usuarios set ramal='501' where email='televendas1@demo.local'");
  const b = await chromium.launch(); const erros = [];
  try {
    const a = await (await b.newContext()).newPage(); a.on('pageerror', (e) => erros.push(e.message)); a.on('dialog', (d) => d.accept());
    await a.goto(BASE + '/entrar'); await a.fill('#email', 'admin@demo.local'); await a.fill('#senha', 'Demo@2026'); await a.click('button[type=submit]'); await a.waitForLoadState();
    await a.goto(BASE + '/administracao#ligue-talk');
    ok(await a.locator('[data-ligue-talk]').isVisible() && (await a.locator('[data-ligue-talk]').innerText()).includes('nenhum cadastrado'), 'Administração mostra o cartão do Ligue Talk (sem token ainda)');

    const salvar = async (empresa, token, modo) => {
      await a.fill('[data-lt-empresa]', empresa); await a.fill('[data-lt-token]', token); await a.selectOption('[data-lt-modo]', modo);
      await Promise.all([a.waitForNavigation(), a.click('[data-ligue-talk] button:has-text("Guardar")')]);
    };
    const testar = async () => { await Promise.all([a.waitForNavigation(), a.click('[data-testar-ligue]')]); return a.locator('.aviso').first().innerText(); };

    await salvar('777', 'token-errado-999', 'api');
    ok(fs.readFileSync(SEG, 'utf8').includes('token-errado-999') && (fs.statSync(SEG).mode & 0o777) === 0o600, 'token guardado em config/segredos.php, só o dono lê (600)');
    ok(fs.readFileSync(SEG, 'utf8').includes('sk-ant-preservada') && fs.readFileSync(SEG, 'utf8').includes('127.0.0.1:8097'), 'guardar o Ligue Talk não apaga a chave da IA nem o endereço');
    const html = await a.content();
    ok(!html.includes('token-errado-999') && html.includes('…-999') && (await a.inputValue('[data-lt-token]')) === '', 'a tela nunca mostra o token de novo (só o final)');
    ok((await testar()).includes('recusou o token'), 'token errado: "Testar conexão" explica em português');

    await salvar('777', 'token-certo-123', 'api');
    ok((await testar()).includes('2 usuário(s)'), 'token certo: conectado, 2 usuários encontrados');
    const lista = await a.locator('[data-lt-usuarios]').innerText();
    ok(lista.includes('Vendedora Um') && lista.includes('ligado a Televendas 1') && lista.includes('Vendedora Dois') && lista.includes('ainda não ligado'), 'lista mostra quem já está ligado a uma pessoa do One (pelo "Usuário no Ligue Talk")');
    ok(sql("select valor from configuracoes where chave='telefonia.modo'") === 'api', 'modo "API do Ligue Talk" fica salvo');

    // Acompanhamento usa a API de verdade (botão "Testar Ligue Talk")
    await a.goto(BASE + '/acompanhamento');
    if (await a.locator('form[action$="/telefonia/testar"] button').count()) {
      await Promise.all([a.waitForNavigation(), a.click('form[action$="/telefonia/testar"] button')]);
      ok((await a.locator('.aviso').first().innerText()).includes('Conectado'), 'Acompanhamento › "Testar Ligue Talk" também conecta');
    }

    // Só quem administra grava
    const v = await (await b.newContext()).newPage();
    await v.goto(BASE + '/entrar'); await v.fill('#email', 'televendas1@demo.local'); await v.fill('#senha', 'Demo@2026'); await v.click('button[type=submit]'); await v.waitForLoadState();
    const r = await v.request.post(BASE + '/administracao/ligue-talk', { form: { company_id: '1', token: 'invasor-123456', _csrf: await v.locator('meta[name=csrf]').getAttribute('content') } });
    ok(r.status() === 403 && !fs.readFileSync(SEG, 'utf8').includes('invasor'), 'vendedora não consegue gravar (403)');

    await a.goto(BASE + '/administracao#ligue-talk');
    await Promise.all([a.waitForNavigation(), a.click('[data-ligue-talk] button:has-text("Remover")')]);
    const depois = fs.readFileSync(SEG, 'utf8');
    ok(!depois.includes('token-certo-123') && depois.includes('sk-ant-preservada') && sql("select valor from configuracoes where chave='telefonia.modo'") === 'manual', '"Remover" apaga token e código, mantém a IA e volta ao registro manual');
    ok(sql("select count(*) from auditoria where acao='ligue_talk'") >= '2' && sql("select count(*) from auditoria where acao='ligue_talk' and (depois_json like '%token-certo%' or depois_json like '%token-errado%')") === '0', 'auditoria registra as mudanças sem guardar o token');
    ok(erros.length === 0, 'sem erros de JavaScript' + (erros.length ? ': ' + erros[0] : ''));
  } finally {
    await b.close(); simulado.kill();
    if (antes === null) { try { fs.unlinkSync(SEG); } catch { /* ok */ } } else fs.writeFileSync(SEG, antes);
  }
  process.exit(falhas ? 1 : 0);
})();
