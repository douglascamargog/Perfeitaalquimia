// Teste automático (Playwright). Rodar todos: bash tests/rodar.sh  ·  só este: node tests/teste_notificacoes.js
// Notificações: contador do menu, sino, marca "novidade" nos clientes, atualização automática e privacidade.
const path = require('path');
const { chromium } = require('playwright');
const { execSync } = require('child_process');
const BASE = 'http://127.0.0.1:8000';
const RAIZ = path.join(__dirname, '..');
let falhas = 0; const ok = (c, m) => { console.log((c ? 'OK   ' : 'FALHA') + ' ' + m); if (!c) falhas++; };
const sql = (q) => execSync(`mysql perfeita --default-character-set=utf8mb4 -N -e "${q.replace(/"/g, '\\"')}"`).toString().trim();
const php = (codigo) => execSync(`php -r '${codigo.replace(/'/g, "'\\''")}'`, { cwd: RAIZ }).toString().trim();
async function entrar(p, email) { await p.goto(BASE + '/entrar'); await p.fill('#email', email); await p.fill('#senha', 'Demo@2026'); await p.click('button[type=submit]'); await p.waitForLoadState(); }
const contadorMenu = async (p, url) => { const b = p.locator(`.menu-lateral [data-pendencia="${url}"]`); return (await b.isHidden()) ? 0 : Number(await b.innerText()); };
const sino = async (p) => { const b = p.locator('[data-sino-contador]'); return (await b.isHidden()) ? 0 : Number(await b.innerText()); };

(async () => {
  const b = await chromium.launch();
  const fin = await (await b.newContext({ viewport: { width: 1440, height: 900 } })).newPage();
  const ven = await (await b.newContext({ viewport: { width: 1440, height: 900 } })).newPage();
  const ges = await (await b.newContext({ viewport: { width: 1440, height: 900 } })).newPage();
  const erros = [];
  for (const p of [fin, ven, ges]) { p.on('pageerror', e => erros.push(e.message)); await p.route(/open-meteo|jsdelivr/, r => r.fulfill({ body: '{}' })); }
  await entrar(fin, 'financeiro@demo.local'); await entrar(ven, 'televendas1@demo.local'); await entrar(ges, 'gestor@demo.local');
  const vend = sql("select id from usuarios where email='televendas1@demo.local'");
  const finId = sql("select id from usuarios where email='financeiro@demo.local'");

  // 1. Vendedor cria um pedido → Financeiro vê "Aprovação" + 1 no menu e no sino
  await fin.goto(BASE + '/aprovacao');
  const aprovAntes = await contadorMenu(fin, '/aprovacao'), sinoAntes = await sino(fin);
  ok(aprovAntes === Number(sql("select count(*) from pedidos where situacao='aguardando_aprovacao'")), `contador da Aprovação = pedidos aguardando (${aprovAntes})`);
  const cli = sql(`select c.id from clientes c join produto_precos pp on pp.tabela_preco_id = c.tabela_preco_id where c.responsavel_id=${vend} and c.tipo='cliente' and c.excluido_em is null and c.credito_situacao <> 'atraso' limit 1`);
  const pedido = php(`require "app/bootstrap.php"; use App\\Core\\Banco; $c = App\\Models\\Cliente::buscar(${cli});
    $prod = Banco::todos("SELECT produto_id FROM produto_precos WHERE tabela_preco_id = ? ORDER BY preco DESC LIMIT 5", [$c["tabela_preco_id"]]);
    $itens = []; foreach ($prod as $p) { $itens[$p["produto_id"]] = 20; }
    $f = Banco::um("SELECT f.id AS forma, c.id AS condicao FROM formas_pagamento f JOIN condicoes_pagamento c ON c.forma_pagamento_id = f.id WHERE c.valor_minimo = 0 AND f.slug <> \\"boleto\\" LIMIT 1");
    [$id, $erros] = App\\Services\\PedidoService::criar($c, ["itens" => $itens, "forma_pagamento_id" => $f["forma"], "condicao_id" => $f["condicao"], "origem" => "sistema", "vendedor_id" => ${vend}, "usuario_id" => ${vend}]);
    echo $id ?: json_encode($erros);`);
  ok(/^\d+$/.test(pedido), 'pedido criado pelo vendedor (#' + pedido + ')');
  await fin.evaluate(() => window.__oneAtualizarNotificacoes());
  await fin.waitForTimeout(400);
  ok(await contadorMenu(fin, '/aprovacao') === aprovAntes + 1, 'atualização automática: Aprovação +1 no menu sem recarregar');
  ok(await sino(fin) === sinoAntes + 1, 'atualização automática: sino +1');
  ok(await fin.locator('.notif-nova', { hasText: `#${pedido}` }).count() === 1, 'aviso flutuante "Novo pedido para aprovar"');
  ok((await fin.title()).startsWith('('), 'título da aba mostra o total pendente');
  await fin.goto(BASE + '/aprovacao');
  ok(await fin.locator('.ped-fila-item', { hasText: '#' + pedido }).locator('.novidade').count() === 1, 'pedido aparece com a etiqueta "novo" na Aprovação');
  ok(sql(`select count(*) from notificacoes where pedido_id=${pedido} and usuario_id=${vend}`) === '0', 'quem criou não é avisado do próprio pedido');

  // 2. Financeiro aprova → contador cai e o vendedor recebe "Pedido aprovado"
  await Promise.all([fin.waitForNavigation(), fin.locator('.ped-fila-item', { hasText: '#' + pedido }).locator('form[action$="/aprovar"] button').click()]);
  ok(await contadorMenu(fin, '/aprovacao') === aprovAntes, 'depois de aprovar, o contador da Aprovação volta');
  ok(sql(`select count(*) from notificacoes where pedido_id=${pedido} and tipo='pedido_aprovar' and lida_em is null`) === '0', 'o "para aprovar" sai do sino de quem aprova');
  ok(sql(`select count(*) from notificacoes where pedido_id=${pedido} and usuario_id=${vend} and tipo='pedido_aprovado'`) === '1', 'vendedor recebe "Pedido aprovado"');

  // 3. Outra pessoa registra contato num cliente do vendedor → aviso e "novidade" na Carteira
  const token = await ges.locator('meta[name=csrf]').getAttribute('content');
  for (let i = 0; i < 10; i++) {
    await ges.request.post(BASE + `/carteira/clientes/${cli}/contato`, { form: { canal: 'WhatsApp', resumo: 'Contato do gestor ' + i, data: '' }, headers: { 'X-CSRF-Token': token, Accept: 'application/json' } });
  }
  ok(sql(`select count(*) from notificacoes where usuario_id=${vend} and cliente_id=${cli} and tipo='cliente_atualizado'`) === '1', 'dez contatos do gestor no mesmo cliente = um aviso só (no máximo 1 por hora)');
  const loja = sql(`select coalesce(nullif(nome_fantasia,''), razao_social) from clientes where id=${cli}`);
  await ven.goto(BASE + '/carteira/lista?q=' + encodeURIComponent(loja.slice(0, 15)));
  ok(await ven.locator('tr', { hasText: loja }).locator('.novidade').count() >= 1, 'cliente aparece com "novidade" na Carteira do vendedor');
  // a própria pessoa não se avisa
  const tokV = await ven.locator('meta[name=csrf]').getAttribute('content');
  const outro = sql(`select id from clientes where responsavel_id=${vend} and id<>${cli} and excluido_em is null limit 1`);
  await ven.request.post(BASE + `/carteira/clientes/${outro}/contato`, { form: { canal: 'Ligação', resumo: 'eu mesmo' }, headers: { 'X-CSRF-Token': tokV, Accept: 'application/json' } });
  ok(sql(`select count(*) from notificacoes where cliente_id=${outro} and tipo='cliente_atualizado'`) === '0', 'contato feito pela própria pessoa não gera aviso');
  // abrir a ficha marca como lida
  await ven.request.get(BASE + `/carteira/clientes/${cli}/ficha`);
  ok(sql(`select count(*) from notificacoes where usuario_id=${vend} and cliente_id=${cli} and lida_em is null`) === '0', 'abrir a ficha marca as novidades do cliente como lidas');

  // 4. Lead do site → o responsável é avisado
  const r = await ven.request.post(BASE + '/leads/receber', { form: { nome: 'Lead Notificação', empresa: 'Loja Aviso', whatsapp: '51955554444' } });
  const lead = sql("select id from clientes where whatsapp='51955554444'");
  const resp = sql(`select responsavel_id from clientes where id=${lead}`);
  ok((await r.json()).ok && sql(`select count(*) from notificacoes where usuario_id=${resp} and cliente_id=${lead} and tipo='lead_novo'`) === '1', 'lead do site avisa o responsável');

  // 5. Privacidade: ninguém abre a notificação de outra pessoa
  const deFin = sql(`select id from notificacoes where usuario_id=${finId} and lida_em is null order by id desc limit 1`);
  const rv = await ven.request.get(BASE + '/notificacoes/' + deFin, { maxRedirects: 0 });
  ok(rv.status() !== 302 || !(rv.headers()['location'] || '').includes('/aprovacao'), 'vendedor não abre notificação do financeiro');
  ok(sql(`select lida_em is null from notificacoes where id=${deFin}`) === '1', '... e ela continua não lida para o dono');
  const listaVen = await (await ven.request.get(BASE + '/notificacoes/resumo?lista=1', { headers: { Accept: 'application/json' } })).json();
  ok(listaVen.lista.every(n => !String(n.titulo).includes('para aprovar')), 'o sino do vendedor só tem os avisos dele');

  // 6. Sino: abrir a lista, clicar e marcar todas
  await ven.goto(BASE + '/');
  const antes = await sino(ven);
  ok(antes >= 1, `sino do vendedor com ${antes} aviso(s)`);
  await ven.click('[data-sino-botao]');
  await ven.waitForSelector('[data-sino-lista] a');
  await Promise.all([ven.waitForNavigation(), ven.locator('[data-sino-lista] a.nao-lida').first().click()]);
  await ven.waitForTimeout(300);
  ok(await sino(ven) === antes - 1, 'clicar na notificação abre e marca como lida');
  await ven.click('[data-sino-botao]');
  await ven.click('[data-sino-marcar] button');
  await ven.waitForTimeout(600);
  ok(await sino(ven) === 0 && sql(`select count(*) from notificacoes where usuario_id=${vend} and lida_em is null`) === '0', '"Marcar todas como lidas" zera o sino');
  await ven.goto(BASE + '/notificacoes');
  ok(await ven.locator('[data-notificacao]').count() >= 3, 'página de histórico das notificações');

  ok(erros.length === 0, 'sem erros de JavaScript ' + erros.join(' / '));
  await b.close();
  console.log(falhas ? `\n${falhas} FALHA(S)` : '\nTudo certo');
  process.exit(falhas ? 1 : 0);
})();
