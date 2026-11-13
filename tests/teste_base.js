// Teste automático (Playwright). Rodar todos: bash tests/rodar.sh  ·  só este: node tests/teste_base.js
const path = require('path');
const RECURSOS = path.join(__dirname, 'recursos');
const { chromium } = require('playwright');
const BASE = 'http://127.0.0.1:8000';
const SHOTS = process.argv[2];
let falhas = 0;
const ok = (cond, msg) => { console.log((cond ? 'OK   ' : 'FALHA') + ' ' + msg); if (!cond) falhas++; };

async function entrar(page, email, senha = 'Demo@2026') {
  await page.goto(BASE + '/entrar');
  await page.fill('#email', email);
  await page.fill('#senha', senha);
  await page.click('button[type=submit]');
}
async function sair(page) { await page.click('form[action$="/sair"] button'); }

(async () => {
  const browser = await chromium.launch();
  const page = await (await browser.newContext({ viewport: { width: 1360, height: 900 } })).newPage();
  const erros = [];
  page.on('pageerror', e => erros.push(e.message));

  // Senha errada
  await entrar(page, 'admin@demo.local', 'errada123');
  ok(await page.locator('.aviso.erro').count() === 1, 'senha errada mostra erro');

  // Admin
  await entrar(page, 'admin@demo.local');
  ok(page.url() === BASE + '/', 'admin entra na tela inicial');
  ok(await page.locator('.faixa-demo').count() === 1, 'faixa de demonstração visível');
  ok(await page.locator('nav a', { hasText: 'Usuários' }).count() === 1, 'admin vê Usuários no menu');
  await page.screenshot({ path: SHOTS + '/inicio-admin.png' });

  // Configuração → auditoria
  await page.goto(BASE + '/configuracoes');
  await page.fill('input[name="config[carteira.dias_inatividade]"]', '75');
  await page.click('form[action$="/configuracoes"] button[type=submit]');
  ok(await page.locator('.aviso.sucesso').count() === 1, 'configuração salva');
  await page.goto(BASE + '/auditoria?entidade=configuracao');
  ok((await page.content()).includes('75'), 'alteração de configuração aparece na auditoria');

  // Criar usuário
  await page.goto(BASE + '/usuarios/novo');
  await page.fill('#nome', 'Televendas 3 (demo)');
  await page.fill('#email', 'televendas3@demo.local');
  await page.selectOption('#perfil_id', { index: 3 });
  await page.fill('#codigo_vendedor', 'tv-03');
  await page.check('input[name="funcoes[]"] >> nth=0');
  await page.fill('#senha', 'Provisoria1');
  await page.fill('#senha_confirmacao', 'Provisoria1');
  await page.click('form button[type=submit]:has-text("Criar")');
  ok((await page.content()).includes('televendas3@demo.local') && (await page.content()).includes('TV03'), 'usuário criado com código normalizado');
  await page.screenshot({ path: SHOTS + '/usuarios.png' });

  // E-mail duplicado
  await page.goto(BASE + '/usuarios/novo');
  await page.fill('#nome', 'X'); await page.fill('#email', 'admin@demo.local');
  await page.selectOption('#perfil_id', { index: 1 });
  await page.fill('#senha', 'Provisoria1'); await page.fill('#senha_confirmacao', 'Provisoria1');
  await page.click('form button[type=submit]:has-text("Criar")');
  ok(await page.locator('.aviso.erro', { hasText: 'e-mail' }).count() === 1, 'e-mail duplicado recusado');
  ok(await page.inputValue('#nome') === 'X', 'formulário mantém o que foi digitado');
  await sair(page);

  // Novo usuário é obrigado a trocar a senha
  await entrar(page, 'televendas3@demo.local', 'Provisoria1');
  ok(page.url().endsWith('/minha-senha'), 'senha provisória obriga troca');
  await page.fill('#senha_atual', 'Provisoria1'); await page.fill('#senha', 'NovaSenha9'); await page.fill('#senha_confirmacao', 'NovaSenha9');
  await page.click('form[action$="/minha-senha"] button');
  ok(page.url() === BASE + '/', 'troca de senha leva ao início');
  await sair(page);

  // Vendedor: menu restrito, 403 em área de admin
  await entrar(page, 'televendas1@demo.local');
  ok(await page.locator('nav a:not([data-bloqueado])', { hasText: 'Usuários' }).count() === 0 && await page.locator('nav a[data-bloqueado]', { hasText: 'Usuários' }).count() === 1, 'vendedor: Usuários aparece com cadeado');
  ok(await page.locator('nav a', { hasText: 'Carteira' }).count() === 1, 'vendedor vê Carteira');
  ok((await page.locator('.topo').innerText()).includes('Televendas'), 'topo mostra a função');
  const r = await page.goto(BASE + '/usuarios');
  ok(r.status() === 403, 'vendedor recebe 403 em /usuarios');
  await page.goto(BASE + '/');
  await sair(page);

  // Financeiro e Produção
  await entrar(page, 'financeiro@demo.local');
  ok(await page.locator('nav a', { hasText: 'Aprovação' }).count() === 1, 'financeiro vê Aprovação');
  ok(await page.locator('nav a:not([data-bloqueado])', { hasText: 'Produção' }).count() === 0, 'financeiro: Produção com cadeado');
  await sair(page);
  await entrar(page, 'producao@demo.local');
  ok(await page.locator('nav a[href$="/producao"]').count() === 1, 'produção vê Produção');
  await sair(page);

  // Celular
  const cel = await (await browser.newContext({ viewport: { width: 390, height: 844 }, deviceScaleFactor: 1 })).newPage();
  await cel.goto(BASE + '/entrar');
  await cel.screenshot({ path: SHOTS + '/login-celular.png', fullPage: true });

  // Força bruta
  for (let i = 0; i < 6; i++) await entrar(page, 'gestor@demo.local', 'errada' + i);
  ok((await page.content()).includes('Muitas tentativas'), 'bloqueio após várias senhas erradas');

  ok(erros.length === 0, 'sem erros de JavaScript ' + erros.join(' | '));
  await browser.close();
  console.log(falhas ? `\n${falhas} FALHA(S)` : '\nTudo certo');
  process.exit(falhas ? 1 : 0);
})();
