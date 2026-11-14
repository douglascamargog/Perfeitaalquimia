// Teste automático (Playwright). Rodar todos: bash tests/rodar.sh  ·  só este: node tests/teste_simulador.js
// Administração › Dados de teste: gera movimento simulado para as pessoas cadastradas e apaga só o simulado.
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
  const p = await (await b.newContext({ viewport: { width: 1300, height: 900 } })).newPage();
  await p.route(/open-meteo|jsdelivr/, r => r.fulfill({ body: '{}' }));
  p.on('dialog', d => d.accept());
  // Uma pessoa "nova" sem clientes, numa função de venda: deve receber clientes de teste
  sql("insert into usuarios (nome, email, senha_hash, perfil_id) select 'Vendedora Nova', 'nova@teste.local', senha_hash, perfil_id from usuarios where email='televendas1@demo.local'");
  const nova = sql("select id from usuarios where email='nova@teste.local'");
  sql(`insert into usuario_funcoes (usuario_id, funcao_id) select ${nova}, id from funcoes where slug='televendas'`);
  const reais = { lig: sql('select count(*) from ligacoes'), ev: sql('select count(*) from cliente_eventos'), ped: sql('select count(*) from pedidos'),
    tar: sql('select count(*) from tarefas'), cob: sql('select count(*) from cobrancas'), cli: sql('select count(*) from clientes') };

  await entrar(p, 'admin@demo.local');
  await p.goto(BASE + '/sistema/dados-de-teste');
  ok((await p.content()).includes('Vendedora Nova'), 'a tela lista quem vai receber movimento');
  await Promise.all([p.waitForNavigation(), p.click('button:has-text("Gerar movimento simulado")')]);
  ok((await aviso(p)).includes('Movimento simulado gerado'), 'gerou o movimento');
  const vendas = sql("select count(distinct u.id) from usuarios u join usuario_funcoes uf on uf.usuario_id=u.id join funcoes f on f.id=uf.funcao_id where f.setor_venda=1 and u.ativo=1");
  ok(sql('select count(distinct usuario_id) from ligacoes where simulado=1') === vendas || Number(sql('select count(distinct usuario_id) from ligacoes where simulado=1')) > Number(vendas), `cada pessoa de vendas recebeu ligações (${vendas})`);
  ok(Number(sql(`select count(*) from clientes where simulado=1 and responsavel_id=${nova}`)) === 30, 'pessoa sem clientes recebeu 30 clientes de teste');
  ok(Number(sql(`select count(*) from tarefas where simulado=1 and usuario_id=${nova}`)) > 10, 'retornos simulados');
  ok(Number(sql('select count(*) from pedidos where simulado=1')) > 10 && Number(sql('select count(*) from cobrancas where simulado=1')) > 5, 'pedidos e casos de cobrança simulados');
  ok(Number(sql('select count(*) from ordens_producao where simulado=1')) > 0 && Number(sql('select count(*) from mkt_posts where simulado=1')) > 0, 'produção e marketing também');
  await p.goto(BASE + `/acompanhamento/pessoa/${nova}?aba=agenda`);
  ok(Number(await p.locator('[data-feitas]').innerText()) > 0, 'o Acompanhamento mostra os dados simulados');

  // Algo real feito DEPOIS de gerar continua depois de apagar
  const cliReal = sql('select id from clientes where simulado=0 limit 1');
  sql(`insert into cliente_eventos (cliente_id, usuario_id, tipo, descricao) values (${cliReal}, ${nova}, 'contato', 'contato real')`);
  await p.goto(BASE + '/sistema/dados-de-teste');
  await Promise.all([p.waitForNavigation(), p.click('button:has-text("Apagar dados simulados")')]);
  ok((await aviso(p)).includes('digite APAGAR') && Number(sql('select count(*) from ligacoes where simulado=1')) > 0, 'sem digitar APAGAR nada é apagado');
  await p.fill('input[name=confirmar]', 'APAGAR');
  await Promise.all([p.waitForNavigation(), p.click('button:has-text("Apagar dados simulados")')]);
  ok(sql('select count(*) from ligacoes where simulado=1') === '0' && sql('select count(*) from clientes where simulado=1') === '0' && sql('select count(*) from pedidos where simulado=1') === '0', 'apagou o simulado');
  ok(sql('select count(*) from ligacoes') === reais.lig && sql('select count(*) from pedidos') === reais.ped && sql('select count(*) from tarefas') === reais.tar
    && sql('select count(*) from cobrancas') === reais.cob && sql('select count(*) from clientes') === reais.cli, 'o que já existia continua igual');
  ok(sql('select count(*) from cliente_eventos') === String(Number(reais.ev) + 1), 'o contato real feito depois continua');
  ok(sql("select count(*) from auditoria where entidade='simulador'") === '2', 'gerar e apagar ficam na auditoria');

  const v = await (await b.newContext()).newPage();
  await entrar(v, 'gestor@demo.local');
  ok((await v.goto(BASE + '/sistema/dados-de-teste')).status() === 403, 'só a administração usa os dados de teste');
  await b.close();
  console.log(falhas ? `\n${falhas} FALHA(S)` : '\nTudo certo.');
  process.exit(falhas ? 1 : 0);
})();
