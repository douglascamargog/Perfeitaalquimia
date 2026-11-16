// Teste automático (Playwright). Rodar todos: bash tests/rodar.sh  ·  só este: node tests/teste_tray_config.js
// Tray pela tela (V13.10): Administração guarda endereço da API, consumer key/secret e code em config/segredos.php (600,
// nunca mostra de novo), mostra a URL de retorno da instalação, "Testar conexão" contra uma Tray simulada (chave errada →
// explica; certa → gera o token e lê os pedidos), o retorno da instalação grava code + api_address (pedindo login antes),
// só o admin grava, e "Remover" volta à demonstração sem apagar as outras chaves.
const { chromium } = require('playwright');
const { execSync, spawn } = require('child_process');
const fs = require('fs');
const BASE = 'http://127.0.0.1:8000';
let falhas = 0; const ok = (c, m) => { console.log((c ? 'OK   ' : 'FALHA') + ' ' + m); if (!c) falhas++; };
const sql = (q) => execSync(`mysql perfeita --default-character-set=utf8mb4 -N -e "${q.replace(/"/g, '\\"')}"`).toString().trim();
const SEG = 'config/segredos.php';
const API = 'http://127.0.0.1:8096/web_api';

const entrar = async (b, email) => {
  const p = await (await b.newContext()).newPage(); p.erros = []; p.on('pageerror', (e) => p.erros.push(e.message)); p.on('dialog', (d) => d.accept());
  await p.goto(BASE + '/entrar'); await p.fill('#email', email); await p.fill('#senha', 'Demo@2026'); await p.click('button[type=submit]'); await p.waitForLoadState();
  return p;
};

(async () => {
  const simulada = spawn('php', ['-S', '127.0.0.1:8096', 'tests/recursos/tray_simulada.php'], { stdio: 'ignore' });
  await new Promise((r) => setTimeout(r, 700));
  const antes = fs.existsSync(SEG) ? fs.readFileSync(SEG, 'utf8') : null;
  fs.writeFileSync(SEG, "<?php\nreturn ['integracoes' => ['anthropic' => ['chave' => 'sk-ant-preservada']]];\n");
  execSync('rm -f storage/cache/tray_token_*.json');
  const b = await chromium.launch(); const paginas = [];
  try {
    // 1. Retorno da instalação sem login: pede para entrar e depois grava
    const anon = await (await b.newContext()).newPage(); paginas.push(anon); anon.erros = []; anon.on('pageerror', (e) => anon.erros.push(e.message));
    await anon.goto(BASE + '/tray/retorno?code=code-certo-789&api_address=' + encodeURIComponent(API) + '&store=391250&adm_user=loja');
    ok(anon.url().includes('/entrar') && (await anon.content()).includes('administrador'), 'retorno da instalação sem login: pede para entrar como administrador');
    await anon.fill('#email', 'admin@demo.local'); await anon.fill('#senha', 'Demo@2026'); await anon.click('button[type=submit]'); await anon.waitForLoadState();
    ok(anon.url().includes('/administracao') && (await anon.locator('.aviso').first().innerText()).includes('Loja Tray conectada'), 'depois do login: volta, grava e avisa "Loja Tray conectada"');
    let seg = fs.readFileSync(SEG, 'utf8');
    ok(seg.includes('code-certo-789') && seg.includes(API) && seg.includes('sk-ant-preservada') && (fs.statSync(SEG).mode & 0o777) === 0o600, 'code e endereço da API guardados em config/segredos.php (600), sem apagar a chave da IA');
    ok(!anon.url().includes('code-certo'), 'o code não fica no endereço da página depois de gravar');

    // 2. Cartão da Administração
    const a = await entrar(b, 'admin@demo.local'); paginas.push(a);
    await a.goto(BASE + '/administracao#tray');
    const cartao = a.locator('[data-tray]');
    ok(await cartao.isVisible() && (await a.locator('[data-tray-retorno]').innerText()).endsWith('/tray/retorno'), 'Administração mostra o cartão da Tray com a URL de retorno para cadastrar');
    ok((await a.inputValue('[data-tray-api]')) === API && (await a.inputValue('[data-tray-code]')) === '' && !(await a.content()).includes('code-certo-789'), 'a tela mostra o endereço, mas nunca o code (só o final)');

    const salvar = async (campos, modo) => {
      for (const [sel, v] of Object.entries(campos)) await a.fill(sel, v);
      if (modo) await a.selectOption('[data-tray-modo]', modo);
      await Promise.all([a.waitForNavigation(), a.click('[data-tray] button:has-text("Guardar")')]);
      return a.locator('.aviso').first().innerText();
    };
    const testar = async () => { await Promise.all([a.waitForNavigation(), a.click('[data-testar-tray]')]); return a.locator('.aviso').first().innerText(); };

    ok((await testar()).includes('Faltam dados'), 'sem consumer key/secret: "Testar conexão" diz o que falta');
    ok((await salvar({ '[data-tray-api]': 'javascript:alert(1)' })).includes('não parece certo'), 'endereço da API estranho é recusado');
    await salvar({ '[data-tray-api]': API, '[data-tray-key]': 'chave-errada-000', '[data-tray-secret]': 'segredo-certo-456' }, 'tray');
    ok((await testar()).includes('recusou as chaves'), 'chave errada: explica em português que a Tray recusou');
    await salvar({ '[data-tray-key]': 'chave-certa-123' }, 'tray');
    const msg = await testar();
    ok(msg.includes('Conectado à loja') && msg.includes('1'), 'chaves certas: gera o Access Token e lê os pedidos da loja');
    const tokens = execSync('ls storage/cache/').toString().split('\n').filter((f) => f.startsWith('tray_token_'));
    ok(tokens.length >= 1 && (fs.statSync('storage/cache/' + tokens.at(-1)).mode & 0o777) === 0o600, 'token da Tray guardado em arquivo protegido (600)');
    ok(sql("select valor from configuracoes where chave='ecommerce.provedor'") === 'tray', 'E-commerce passa a usar a loja Tray');
    const html = await a.content();
    ok(!html.includes('chave-certa-123') && !html.includes('segredo-certo-456') && html.includes('…-123'), 'a tela nunca mostra o consumer key nem o secret de novo');

    // "Conectar à loja": leva para auth.php da loja com o consumer key e a URL de retorno
    await a.goto(BASE + '/administracao#tray');
    ok(await a.locator('[data-tray-conectar]').isEnabled(), 'com as chaves guardadas, o botão "Conectar à loja Tray" fica liberado');
    const resp = await a.request.post(BASE + '/administracao/tray/conectar', { maxRedirects: 0,
      form: { loja: 'https://loja-teste.commercesuite.com.br/admin', _csrf: await a.locator('meta[name=csrf]').getAttribute('content') } });
    const u = new URL(resp.headers().location);
    ok(u.origin + u.pathname === 'https://loja-teste.commercesuite.com.br/auth.php' && u.searchParams.get('response_type') === 'code' && u.searchParams.get('consumer_key') === 'chave-certa-123' && u.searchParams.get('callback').endsWith('/tray/retorno'),
      '"Conectar à loja Tray" abre a autorização da própria loja (auth.php) com o consumer key e a URL de retorno');

    // Etapa "Autorizar": logo depois de instalar, a Tray manda só o endereço da loja
    await a.goto(BASE + '/tray/retorno?url=' + encodeURIComponent('https://loja-teste.commercesuite.com.br') + '&adm_user=loja&store=391250');
    const link = await a.getAttribute('[data-tray-autorizar-botao]', 'href');
    ok(link.startsWith('https://loja-teste.commercesuite.com.br/auth.php?response_type=code&consumer_key=chave-certa-123&callback=') && decodeURIComponent(link).endsWith('/tray/retorno'),
      'instalação: mostra "Autorizar o One na loja" levando para auth.php da loja com o consumer key e a URL de retorno');

    // Painel do E-commerce lê a loja (Tray simulada)
    await a.goto(BASE + '/marketing/ecommerce');
    ok(!(await a.content()).includes('Fatal error'), 'painel do E-commerce abre ligado à Tray');

    // 3. Só quem administra grava
    const v = await entrar(b, 'televendas1@demo.local'); paginas.push(v);
    const r = await v.request.post(BASE + '/administracao/tray', { form: { consumer_key: 'invasor-123456', _csrf: await v.locator('meta[name=csrf]').getAttribute('content') } });
    ok(r.status() === 403 && !fs.readFileSync(SEG, 'utf8').includes('invasor'), 'vendedora não consegue gravar (403)');
    await v.goto(BASE + '/tray/retorno?code=invasor-code-1&api_address=' + encodeURIComponent('https://outra.com.br/web_api'));
    ok(!fs.readFileSync(SEG, 'utf8').includes('invasor-code') && (await v.content()).includes('Só quem administra'), 'retorno da instalação com login de vendedora não grava nada');

    // 4. Remover
    await a.goto(BASE + '/administracao#tray');
    await Promise.all([a.waitForNavigation(), a.click('[data-tray] button:has-text("Remover")')]);
    seg = fs.readFileSync(SEG, 'utf8');
    ok(!seg.includes('chave-certa-123') && !seg.includes('code-certo-789') && seg.includes('sk-ant-preservada') && sql("select valor from configuracoes where chave='ecommerce.provedor'") === 'fake', '"Remover" apaga as chaves da Tray, mantém a IA e volta à demonstração');
    ok(sql("select count(*) from auditoria where acao like 'tray%'") >= '3' && sql("select count(*) from auditoria where acao like 'tray%' and (depois_json like '%chave-certa%' or depois_json like '%segredo-certo%' or depois_json like '%code-certo%')") === '0', 'auditoria registra as mudanças sem guardar as chaves');
    const erros = paginas.flatMap((p) => p.erros);
    ok(erros.length === 0, 'sem erros de JavaScript' + (erros.length ? ': ' + erros[0] : ''));
  } finally {
    await b.close(); simulada.kill();
    execSync('rm -f storage/cache/tray_token_*.json');
    if (antes === null) { try { fs.unlinkSync(SEG); } catch { /* ok */ } } else fs.writeFileSync(SEG, antes);
  }
  process.exit(falhas ? 1 : 0);
})();
