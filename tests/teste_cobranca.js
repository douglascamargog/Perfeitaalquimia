// Teste automático (Playwright). Rodar todos: bash tests/rodar.sh  ·  só este: node tests/teste_cobranca.js
const path = require('path');
const RECURSOS = path.join(__dirname, 'recursos');
const { chromium } = require('playwright');
const { execSync } = require('child_process');
const BASE = 'http://127.0.0.1:8000', DIR = process.argv[2] || path.join(__dirname, 'saida');
let falhas = 0;
const ok = (c, m) => { console.log((c ? 'OK   ' : 'FALHA') + ' ' + m); if (!c) falhas++; };
const sql = (q) => execSync(`mysql perfeita --default-character-set=utf8mb4 -N -e "${q}"`).toString().trim();
async function entrar(p, email) { await p.goto(BASE + '/entrar'); await p.fill('#email', email); await p.fill('#senha', 'Demo@2026'); await p.click('button[type=submit]'); await p.waitForLoadState(); }
async function limpar(p) { await p.evaluate(() => { const a = document.querySelector('.aviso-flutuante'); a.classList.remove('visivel'); a.textContent = ''; delete a.dataset.lido; }); }
async function sair(p) { await p.goto(BASE + '/'); await p.click('form[action$="/sair"] button'); await p.waitForLoadState(); }
const aviso = async (p) => { await p.waitForSelector('.aviso-flutuante.visivel'); const t = await p.locator('.aviso-flutuante').innerText(); await p.waitForTimeout(150); return t; };

(async () => {
  const b = await chromium.launch(); const ctx = await b.newContext({ viewport: { width: 1440, height: 950 } }); const p = await ctx.newPage();
  const erros = []; p.on('pageerror', e => erros.push(e.message)); p.on('dialog', d => d.accept());

  // ---------- Financeiro ----------
  await entrar(p, 'financeiro@demo.local');
  ok(await p.locator('nav a', { hasText: 'Cobrança' }).count() === 1, 'financeiro vê "Cobrança" no menu');
  await p.goto(BASE + '/cobranca');
  ok(await p.locator('.funil-coluna').count() === 9, 'funil de cobrança com 9 colunas');
  const total = await p.locator('.cob-numero.destaque strong').innerText();
  ok(/R\$ [\d.]+,\d\d/.test(total), 'total em aberto aparece: ' + total);
  await p.screenshot({ path: DIR + '/cobranca-funil.png', fullPage: true });

  // arrastar um caso de "Título vencido" para "Em negociação"
  const card = p.locator('.funil-coluna').nth(0).locator('.funil-card').first();
  const casoId = await card.getAttribute('data-cliente');
  const alvo = p.locator('.funil-coluna').nth(3).locator('[data-soltar]');
  const a = await card.boundingBox(), d = await alvo.boundingBox();
  await p.mouse.move(a.x + a.width / 2, a.y + 12); await p.mouse.down();
  for (let k = 1; k <= 12; k++) await p.mouse.move(a.x + (d.x + 60 - a.x) * k / 12, a.y + 12 + (d.y + 40 - a.y) * k / 12);
  await p.mouse.up();
  await p.waitForTimeout(800);
  ok(sql(`select e.nome from cobrancas c join cobranca_etapas e on e.id=c.etapa_id where c.id=${casoId}`) === 'Em negociação', 'arrastar no funil muda a etapa do caso');

  // abrir um caso em "Título vencido" e registrar 3 tentativas sem sucesso
  await p.goto(BASE + '/cobranca');
  const card2 = p.locator('.funil-coluna').nth(0).locator('.funil-card').first();
  const caso2 = await card2.getAttribute('data-cliente');
  const cliente2 = sql(`select cliente_id from cobrancas where id=${caso2}`);
  await card2.locator('.nome-cliente').click();
  await p.waitForSelector('.cob-valor-grande');
  await p.screenshot({ path: DIR + '/cobranca-caso.png' });
  for (let i = 0; i < 3; i++) {
    await p.selectOption('#t-resultado', 'Não atendeu');
    await limpar(p); await p.click('form[action$="/tentativa"] button[type=submit]');
    const t = await aviso(p);
    if (i === 2) ok(t.includes('Sem contato'), '3ª tentativa sem sucesso avisa: ' + t.slice(0, 60));
    await p.waitForSelector('.cob-valor-grande');
  }
  ok(sql(`select e.tipo from cobrancas c join cobranca_etapas e on e.id=c.etapa_id where c.id=${caso2}`) === 'sem_contato', 'caso foi para "Sem contato — buscar contatos"');

  // buscar contatos com IA (demonstração)
  await p.waitForSelector('.busca-ia.destaque');
  ok(true, 'caixa de busca destacada no caso sem contato');
  const telAntes = sql(`select coalesce(whatsapp,'') from clientes where id=${cliente2}`);
  await p.click('[data-busca-contatos] [data-buscar]');
  await p.waitForSelector('.busca-ia-item', { timeout: 15000 });
  const itens = await p.locator('.busca-ia-item').count();
  ok(itens >= 3, `sugestões aparecem (${itens}), cada uma com fonte`);
  ok(await p.locator('.busca-ia-item a[href^="https://"]').count() >= 3, 'sugestões têm link da fonte');
  await p.screenshot({ path: DIR + '/cobranca-busca-ia.png' });
  // desmarca tudo e marca só o WhatsApp
  for (const cb of await p.locator('.busca-ia-item input:not([disabled])').all()) await cb.uncheck();
  await p.locator('.busca-ia-item', { hasText: 'WhatsApp' }).locator('input').check();
  await limpar(p); await p.click('form[data-aplicar] button[type=submit]');
  const t2 = await aviso(p);
  ok(t2.includes('atualizado'), 'salvar marcados: ' + t2);
  const telDepois = sql(`select coalesce(whatsapp,'') from clientes where id=${cliente2}`);
  ok(telDepois !== '' && telDepois !== telAntes, `WhatsApp atualizado (${telAntes || 'vazio'} → ${telDepois})`);
  ok(sql(`select count(*) from cliente_eventos where cliente_id=${cliente2} and tipo='busca_contato'`) === '1', 'linha do tempo registra a troca com a fonte');
  ok(sql(`select count(*) from auditoria where entidade='cliente' and entidade_id=${cliente2} and acao='contatos_ia'`) === '1', 'auditoria registra a troca de contato');
  ok(sql(`select e.tipo from cobrancas c join cobranca_etapas e on e.id=c.etapa_id where c.id=${caso2}`) === 'aberta', 'com contato novo, caso volta para tentativa');
  await p.waitForTimeout(1200);
  ok((await p.locator('.linha-tempo').innerText()).includes('WhatsApp:'), 'gaveta recarregada mostra a troca na linha do tempo');

  // segunda busca da mesma empresa = mostra a anterior (sem gastar)
  const buscasAntes = sql('select count(*) from buscas_contato');
  await p.click('[data-busca-contatos] [data-buscar]');
  await p.waitForSelector('.busca-ia-lista');
  ok((await p.locator('.busca-ia-lista').innerText()).includes('Busca recente'), 'repetir a busca mostra a recente');
  ok(sql('select count(*) from buscas_contato') === buscasAntes, 'nenhuma busca nova gravada');

  // promessa
  const amanha = new Date(Date.now() + 86400000).toISOString().slice(0, 10);
  await p.fill('#p-data', amanha);
  await limpar(p); await p.click('form[action$="/promessa"] button[type=submit]');
  ok((await aviso(p)).includes('Promessa registrada'), 'promessa registrada');
  ok(sql(`select count(*) from tarefas where cliente_id=${cliente2} and tipo='cobranca' and vence_em='${amanha}' and concluida_em is null`) === '1', 'tarefa de conferir pagamento criada (Agenda)');
  ok(sql(`select e.tipo from cobrancas c join cobranca_etapas e on e.id=c.etapa_id where c.id=${caso2}`) === 'promessa', 'caso na etapa Promessa');

  // marcar títulos pagos → encerra como Pago
  await p.waitForSelector('.cob-valor-grande');
  while (await p.locator('form[action*="/pago"] button').count()) {
    await limpar(p); await p.locator('form[action*="/pago"] button').first().click();
    await aviso(p); await p.waitForSelector('.cob-valor-grande');
  }
  ok(sql(`select concat(situacao,'/',e.tipo) from cobrancas c join cobranca_etapas e on e.id=c.etapa_id where c.id=${caso2}`) === 'encerrada/pago', 'todos os títulos pagos encerram o caso como Pago');
  ok(sql(`select credito_situacao from clientes where id=${cliente2}`) === 'ok', 'cliente volta a crédito "ok"');
  await p.keyboard.press('Escape');

  // lista, filtros, imprimir, CSV
  await p.goto(BASE + '/cobranca/lista?faixa=mais90');
  const linhas = await p.locator('.tabela-carteira tbody tr').count();
  ok(linhas === Number(sql("select count(*) from cobrancas where situacao='aberta' and datediff(curdate(), vencimento_mais_antigo) > 90")), `filtro +90 dias na lista (${linhas})`);
  await p.screenshot({ path: DIR + '/cobranca-lista.png', fullPage: true });
  const r = await p.request.get(BASE + '/cobranca/exportar.csv');
  ok(r.ok() && (await r.text()).includes('Dias de atraso'), 'exporta CSV');
  await p.goto(BASE + '/cobranca/imprimir');
  ok(await p.locator('.cob-relatorio tbody tr').count() >= 10, 'relatório de impressão');
  await p.emulateMedia({ media: 'print' });
  await p.screenshot({ path: DIR + '/cobranca-imprimir.png', fullPage: true });
  await p.emulateMedia({ media: 'screen' });

  // importar do ERP de novo não duplica
  const casos = sql('select count(*) from cobrancas');
  await p.goto(BASE + '/cobranca');
  await Promise.all([p.waitForNavigation(), p.click('form[action$="/cobranca/importar"] button')]);
  ok(sql('select count(*) from cobrancas') === casos, 'ler títulos do ERP de novo não duplica casos');

  // Listas: funil de cobrança editável
  await sair(p);
  await entrar(p, 'admin@demo.local');
  await p.goto(BASE + '/configuracoes/listas');
  ok(await p.locator('#cobranca tbody tr').count() === 9, 'Configurações › Listas mostra o funil de cobrança');
  await p.goto(BASE + '/configuracoes');
  ok((await p.content()).includes('Busca de contatos (IA)'), 'configurações da busca de contatos aparecem');
  await sair(p);

  // ---------- Vendedor ----------
  await entrar(p, 'televendas1@demo.local');
  ok(await p.locator('nav a', { hasText: 'Cobrança' }).count() === 0, 'vendedor não vê Cobrança no menu');
  const r403 = await p.goto(BASE + '/cobranca');
  ok(r403.status() === 403, 'vendedor recebe 403 em /cobranca');
  const meu = sql("select c.id from clientes c join usuarios u on u.id=c.responsavel_id where u.email='televendas1@demo.local' and c.id not in (select cliente_id from cobrancas where situacao='aberta') limit 1");
  await p.goto(BASE + '/carteira/lista?abrir=' + meu);
  await p.waitForSelector('.ficha-corpo details.ficha-secao');
  await p.locator('details.ficha-secao summary').evaluate(el => el.click());
  await p.fill('#cb-valor', '1.250,90');
  const ontem = new Date(Date.now() - 20 * 86400000).toISOString().slice(0, 10);
  await p.fill('#cb-venc', ontem);
  await p.fill('#cb-obs', 'Não responde há 2 semanas');
  await limpar(p); await p.locator('form[action$="/cobranca"] button[type=submit]').evaluate(el => el.click());
  ok((await aviso(p)).includes('enviado para a Cobrança'), 'vendedor envia cliente para a cobrança pela ficha');
  ok(sql(`select concat(valor_total,'/',origem) from cobrancas where cliente_id=${meu} and situacao='aberta'`) === '1250.90/manual', 'caso criado com R$ 1.250,90');
  await p.waitForSelector('.ficha-selos .selo.vermelho');
  ok((await p.locator('.ficha-selos').innerText()).includes('Em cobrança'), 'ficha mostra selo "Em cobrança"');
  ok(!(await p.locator('.ficha-selos').innerText()).includes('R$'), 'vendedor não vê o valor no selo (sem cobranca.ver)');
  ok(await p.locator('[data-busca-contatos]').count() === 1, 'vendedor tem o botão Buscar contatos com IA na ficha');
  await p.screenshot({ path: DIR + '/ficha-cobranca.png' });
  const rFin = await p.request.post(BASE + `/cobranca/1/tentativa`, { form: { resultado: 'Atendeu' }, headers: { 'X-CSRF-Token': await p.locator('meta[name=csrf]').getAttribute('content') } });
  ok(rFin.status() === 403, 'vendedor não registra tentativa (403)');
  await sair(p);

  // ---------- Produção ----------
  await entrar(p, 'producao@demo.local');
  ok((await p.goto(BASE + '/cobranca')).status() === 403, 'produção recebe 403 em /cobranca');
  ok((await p.request.post(BASE + `/clientes/${meu}/contatos-ia`, { headers: { 'X-CSRF-Token': await p.locator('meta[name=csrf]').getAttribute('content') } })).status() === 403, 'produção não usa a busca de contatos (403)');
  await sair(p);

  // ---------- Limite diário ----------
  sql("update configuracoes set valor='1' where chave='busca_ia.limite_diario'");
  await entrar(p, 'gestor@demo.local');
  const outro = sql(`select id from clientes where id not in (select cliente_id from buscas_contato) and id<>${meu} limit 1`);
  await p.goto(BASE + '/carteira/lista?abrir=' + outro);
  await p.waitForSelector('[data-busca-contatos] [data-buscar]');
  await p.click('[data-busca-contatos] [data-buscar]');
  await p.waitForSelector('[data-resultado] .aviso.erro');
  ok((await p.locator('[data-resultado]').innerText()).includes('limite'), 'limite diário bloqueia a busca');
  sql("update configuracoes set valor='30' where chave='busca_ia.limite_diario'");

  // celular
  const cel = await b.newContext({ viewport: { width: 390, height: 844 }, deviceScaleFactor: 1 }); const pc = await cel.newPage();
  await entrar(pc, 'financeiro@demo.local');
  await pc.goto(BASE + '/cobranca/lista');
  await pc.screenshot({ path: DIR + '/cobranca-celular.png', fullPage: false });
  const largura = await pc.evaluate(() => document.documentElement.scrollWidth);
  ok(largura <= 392, 'celular sem rolagem horizontal (' + largura + 'px)');

  ok(erros.length === 0, 'sem erros de JavaScript ' + erros.join(' | '));
  console.log(falhas ? `\n${falhas} FALHA(S)` : '\nTUDO OK');
  await b.close();
})();
