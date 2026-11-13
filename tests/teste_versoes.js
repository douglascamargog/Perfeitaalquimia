// Teste automático (Playwright). Rodar todos: bash tests/rodar.sh  ·  só este: node tests/teste_versoes.js
// Novidades da versão: aviso no sino depois da atualização, janela uma vez por pessoa, histórico e nome do pacote.
const path = require('path');
const { chromium } = require('playwright');
const { execSync } = require('child_process');
const BASE = 'http://127.0.0.1:8000';
const RAIZ = path.join(__dirname, '..');
let falhas = 0; const ok = (c, m) => { console.log((c ? 'OK   ' : 'FALHA') + ' ' + m); if (!c) falhas++; };
const sql = (q) => execSync(`mysql perfeita --default-character-set=utf8mb4 -N -e "${q.replace(/"/g, '\\"')}"`).toString().trim();
async function entrar(p, email) { await p.goto(BASE + '/entrar'); await p.fill('#email', email); await p.fill('#senha', 'Demo@2026'); await p.click('button[type=submit]'); await p.waitForLoadState(); }

(async () => {
  const atual = execSync(`php -r 'define("RAIZ", getcwd()); echo (require "app/versoes.php")[0]["versao"];'`, { cwd: RAIZ }).toString().trim();
  ok(/^\d+\.\d+$/.test(atual), `versão atual no formato número.decimal (${atual})`);

  // 1. Instalação nova: ninguém vê a janela
  ok(sql("select count(*) from usuarios where versao_vista is null or versao_vista <> '" + atual + "'") === '0', 'instalação nova: todos já marcados com a versão atual');

  // 2. Simula um sistema em uso recebendo esta versão: o aviso vai para todos os ativos, uma vez só
  sql(`delete from migracoes where arquivo='versao:${atual}'`);
  sql("update usuarios set versao_vista=null");
  sql("update usuarios set versao_vista='1.0' where email='gestor@demo.local'");
  const ativos = sql('select count(*) from usuarios where ativo=1');
  execSync('php database/migrar.php --atualizacao', { cwd: RAIZ });
  ok(sql("select count(*) from notificacoes where tipo='versao_nova'") === ativos, `atualização avisa no sino todos os ${ativos} usuários ativos`);
  execSync('php database/migrar.php --atualizacao', { cwd: RAIZ });
  ok(sql("select count(*) from notificacoes where tipo='versao_nova'") === ativos, 'rodar a atualização de novo não repete o aviso');

  const b = await chromium.launch();
  const erros = [];
  const nova = async () => { const p = await (await b.newContext({ viewport: { width: 1280, height: 860 } })).newPage(); p.on('pageerror', e => erros.push(e.message)); await p.route(/open-meteo|jsdelivr/, r => r.fulfill({ body: '{}' })); return p; };

  // 3. Quem não viu: janela com as novidades da versão atual
  const ven = await nova();
  await entrar(ven, 'televendas1@demo.local');
  const janela = ven.locator('[data-novidades]');
  ok(await janela.isVisible(), 'janela de novidades aparece no primeiro acesso depois da atualização');
  ok((await janela.locator('#nov-titulo').innerText()).includes('ONE_V' + atual), 'título com o nome da versão (ONE_V' + atual + ')');
  ok(await janela.locator('.nov-versao').count() === 1, 'quem nunca viu nenhuma vê só a versão atual');
  ok(await janela.locator('.nov-versao li').count() >= 1, 'lista as mudanças');
  await ven.goto(BASE + '/agenda');
  await Promise.all([ven.waitForNavigation(), ven.click('[data-novidades-ok]')]);
  ok(new URL(ven.url()).pathname.endsWith('/agenda'), '"Entendi" volta para a mesma página');
  ok(await ven.locator('[data-novidades]').count() === 0, 'depois do "Entendi" a janela não aparece mais');
  ok(sql("select versao_vista from usuarios where email='televendas1@demo.local'") === atual, 'versão vista gravada');
  await ven.goto(BASE + '/');
  ok(await ven.locator('[data-novidades]').count() === 0, '... nem em outra página');

  // 4. Quem estava numa versão antiga vê as versões que perdeu (até 3)
  const ges = await nova();
  await entrar(ges, 'gestor@demo.local');
  const perdidas = await ges.locator('[data-novidades] .nov-versao').count();
  ok(perdidas >= 2 && perdidas <= 3, `gestor (estava na 1.0) vê as versões que perdeu (${perdidas})`);
  ok(await ges.locator('[data-novidades] .nov-versao[data-versao="1.0"]').count() === 0, '... sem repetir a que já tinha visto');

  // 5. Histórico completo e rodapé do menu
  await ges.goto(BASE + '/novidades');
  ok(await ges.locator('.nov-historico .nov-versao').count() === Number(execSync(`php -r 'define("RAIZ", getcwd()); echo count(require "app/versoes.php");'`, { cwd: RAIZ }).toString()), 'página Novidades com todas as versões');
  ok(await ges.locator('[data-novidades]').count() === 0, 'abrir o histórico também conta como visto');
  ok((await ges.locator('.menu-rodape a').innerText()).includes('ONE_V' + atual), 'rodapé do menu mostra ONE_V' + atual + ' com link para as novidades');

  // 6. Sino: o aviso da versão leva para as novidades
  const fin = await nova();
  await entrar(fin, 'financeiro@demo.local');
  await fin.click('[data-novidades-ok]');
  const lista = await (await fin.request.get(BASE + '/notificacoes/resumo?lista=1', { headers: { Accept: 'application/json' } })).json();
  ok(lista.lista.some(n => String(n.titulo).includes('ONE_V' + atual)), 'aviso "One atualizado" no sino de cada pessoa');

  // 7. Usuário novo não recebe novidades antigas
  const adm = await nova();
  await entrar(adm, 'admin@demo.local');
  await adm.click('[data-novidades-ok]');
  await adm.goto(BASE + '/usuarios/novo');
  await adm.fill('#nome', 'Pessoa Nova Teste');
  await adm.fill('#email', 'nova.versao@demo.local');
  await adm.selectOption('#perfil_id', { index: 2 });
  await adm.fill('#senha', 'Teste@Versao2026');
  await adm.fill('#senha_confirmacao', 'Teste@Versao2026');
  await Promise.all([adm.waitForNavigation(), adm.click('button:has-text("Criar usuário")')]);
  ok(sql("select versao_vista from usuarios where email='nova.versao@demo.local'") === atual, 'usuário criado já começa com a versão atual vista');

  // 8. Pacote sai com o nome da versão
  const script = require('fs').readFileSync(path.join(RAIZ, 'ferramentas/gerar_pacote.sh'), 'utf8');
  ok(script.includes('ONE_V') && script.includes('app/versoes.php'), 'gerar_pacote.sh usa o número de app/versoes.php (ONE_Vx.y.zip)');

  ok(erros.length === 0, 'sem erros de JavaScript ' + erros.join(' / '));
  await b.close();
  console.log(falhas ? `\n${falhas} FALHA(S)` : '\nTudo certo');
  process.exit(falhas ? 1 : 0);
})();
