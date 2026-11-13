// Teste automático (Playwright). Rodar todos: bash tests/rodar.sh  ·  só este: node tests/teste_acessos.js
// Acessos por aba (sem acesso / ver / ver e editar), exceções por pessoa e "foco" pela função.
const path = require('path');
const { chromium } = require('playwright');
const { execSync } = require('child_process');
const BASE = 'http://127.0.0.1:8000';
const RAIZ = path.join(__dirname, '..');
let falhas = 0; const ok = (c, m) => { console.log((c ? 'OK   ' : 'FALHA') + ' ' + m); if (!c) falhas++; };
const sql = (q) => execSync(`mysql perfeita --default-character-set=utf8mb4 -N -e "${q.replace(/"/g, '\\"')}"`).toString().trim();
const php = (codigo) => execSync(`php -r '${codigo.replace(/'/g, "'\\''")}'`, { cwd: RAIZ }).toString().trim();
async function entrar(p, email) { await p.goto(BASE + '/entrar'); await p.fill('#email', email); await p.fill('#senha', 'Demo@2026'); await p.click('button[type=submit]'); await p.waitForLoadState(); }
const card = (p, titulo) => p.locator('.b-topo span', { hasText: titulo });
const itemMenu = (p, url) => p.locator(`.menu-lateral a[href$="${url}"]`).first();
const MURAL = 'Mural de posts e Planejamento';

(async () => {
  const b = await chromium.launch();
  const nova = async () => { const p = await (await b.newContext({ viewport: { width: 1440, height: 900 } })).newPage(); p.on('pageerror', e => erros.push(e.message)); await p.route(/open-meteo|jsdelivr/, r => r.fulfill({ body: '{}' })); return p; };
  const erros = [];
  const adm = await nova(), ven = await nova(), ven2 = await nova(), fin = await nova();

  // O admin trabalha no Marketing (função Marketing, sem escolha pessoal de foco)
  const admId = sql("select id from usuarios where email='admin@demo.local'");
  sql(`insert ignore into usuario_funcoes (usuario_id, funcao_id) select ${admId}, id from funcoes where areas_foco='marketing' limit 1`);
  sql(`update usuarios set areas_foco=null where id=${admId}`);
  await entrar(adm, 'admin@demo.local');

  // 1. Admin do Marketing: Início só com Marketing; todas as abas sem cadeado
  ok(await card(adm, 'Posts desta semana').count() === 1, 'admin do Marketing vê o cartão "Posts desta semana"');
  ok(await card(adm, 'Pedidos para aprovar').count() === 0, '... e não vê "Pedidos para aprovar"');
  ok(await card(adm, 'Faturado no mês').count() === 0 && await card(adm, 'Meta da equipe').count() === 0, '... nem faturamento e metas da equipe');
  ok(await adm.locator('.menu-lateral a[data-bloqueado]').count() === 0, 'admin: nenhuma aba com cadeado');
  ok(await adm.locator('.menu-lateral [data-pendencia="/aprovacao"]').isHidden(), 'admin do Marketing: sem contador na Aprovação');

  // 7. Pedido novo avisa o Financeiro (foco Aprovação), não o admin do Marketing
  const vend = sql("select id from usuarios where email='televendas1@demo.local'");
  const cli = sql(`select c.id from clientes c join produto_precos pp on pp.tabela_preco_id = c.tabela_preco_id where c.responsavel_id=${vend} and c.tipo='cliente' and c.excluido_em is null and c.credito_situacao <> 'atraso' limit 1`);
  const pedido = php(`require "app/bootstrap.php"; use App\\Core\\Banco; $c = App\\Models\\Cliente::buscar(${cli});
    $prod = Banco::todos("SELECT produto_id FROM produto_precos WHERE tabela_preco_id = ? ORDER BY preco DESC LIMIT 5", [$c["tabela_preco_id"]]);
    $itens = []; foreach ($prod as $p) { $itens[$p["produto_id"]] = 20; }
    $f = Banco::um("SELECT f.id AS forma, c.id AS condicao FROM formas_pagamento f JOIN condicoes_pagamento c ON c.forma_pagamento_id = f.id WHERE c.valor_minimo = 0 AND f.slug <> \\"boleto\\" LIMIT 1");
    [$id, $erros] = App\\Services\\PedidoService::criar($c, ["itens" => $itens, "forma_pagamento_id" => $f["forma"], "condicao_id" => $f["condicao"], "origem" => "sistema", "vendedor_id" => ${vend}, "usuario_id" => ${vend}]);
    echo $id ?: json_encode($erros);`);
  ok(/^\d+$/.test(pedido), 'pedido criado (#' + pedido + ')');
  ok(sql(`select count(*) from notificacoes n join usuarios u on u.id=n.usuario_id where n.pedido_id=${pedido} and n.tipo='pedido_aprovar' and u.email='financeiro@demo.local'`) === '1', 'Financeiro recebe "pedido para aprovar"');
  ok(sql(`select count(*) from notificacoes where pedido_id=${pedido} and usuario_id=${admId}`) === '0', 'admin do Marketing não recebe o aviso do pedido');
  await entrar(fin, 'financeiro@demo.local');
  ok(await card(fin, 'Pedidos para aprovar').count() === 1 || await card(fin, 'Aguardando aprovação').count() === 1, 'Financeiro vê a aprovação no Início');

  // 2. O que aparece para cada um é definido pelo admin (Usuários), não no Meu perfil
  await adm.goto(BASE + '/meu-perfil');
  ok(await adm.locator('input[name="foco[]"]').count() === 0, 'Meu perfil não tem mais a escolha das áreas');
  await adm.goto(BASE + `/usuarios/${admId}/editar`);
  await adm.check('input[name=foco_modo][value=escolher]');
  await adm.check('input[name="foco[]"][value="marketing"]');
  await adm.check('input[name="foco[]"][value="diretoria"]');
  await Promise.all([adm.waitForNavigation(), adm.click('button:has-text("Salvar alterações")')]);
  ok(sql(`select areas_foco from usuarios where id=${admId}`).includes('diretoria'), 'admin define o que aparece para a pessoa (Usuários)');
  await adm.goto(BASE + '/');
  ok(await card(adm, 'Faturado no mês').count() === 1 && await card(adm, 'Posts desta semana').count() === 1, 'com Diretoria ligada: faturamento + Marketing no Início');
  await adm.goto(BASE + `/usuarios/${admId}/editar`);
  await adm.check('input[name=foco_modo][value=funcoes]');
  await Promise.all([adm.waitForNavigation(), adm.click('button:has-text("Salvar alterações")')]);
  ok(sql(`select ifnull(areas_foco,'NULL') from usuarios where id=${admId}`) === 'NULL', '"Seguir as funções" volta ao padrão da função');
  await adm.goto(BASE + `/usuarios/${admId}/editar`);
  await adm.check('input[name=foco_modo][value=escolher]');
  await adm.check('input[name="foco[]"][value="diretoria"]');
  await Promise.all([adm.waitForNavigation(), adm.click('button:has-text("Salvar alterações")')]);

  // 6. A grade não mostra o perfil Admin (ele sempre tem tudo)
  await adm.goto(BASE + '/acessos');
  ok(await adm.locator('[data-grade-acessos] select[data-perfil="admin"]').count() === 0, 'perfil Admin não aparece na grade');
  ok(await adm.locator('[data-grade-acessos] select[data-perfil="vendedor"]').count() > 10, 'grade com as abas para o perfil Vendedor');

  // 3. Vendedor com "Ver" no Planejamento: abre sem botões; POST devolve 403
  await entrar(ven, 'televendas1@demo.local');
  ok(await itemMenu(ven, '/marketing').locator('.menu-leitura').count() === 1, 'vendedor: Mural com o olho de "só leitura" no menu');
  await ven.goto(BASE + '/marketing/planejamento');
  ok(await ven.locator('h1').count() >= 1 && await ven.locator('button', { hasText: 'Salvar campanha' }).count() === 0 && await ven.locator('a', { hasText: 'Editar' }).count() === 0, 'vendedor vê o Planejamento sem criar nem editar');
  const tok = await ven.locator('meta[name=csrf]').getAttribute('content');
  const r403 = await ven.request.post(BASE + '/marketing/campanhas', { form: { nome: 'Invasão', inicio: '2026-11-01', fim: '2026-11-30' }, headers: { 'X-CSRF-Token': tok } });
  ok(r403.status() === 403, 'POST de criar campanha pelo vendedor → 403');
  ok(sql("select count(*) from mkt_campanhas where nome='Invasão'") === '0', '... e nada foi gravado');

  // 4. Tirando o acesso na grade: cadeado e página "sem acesso"
  await adm.selectOption(`select[aria-label="${MURAL} · Vendedor"]`, '0');
  await Promise.all([adm.waitForNavigation(), adm.click('button:has-text("Salvar acessos")')]);
  ok(sql("select count(*) from perfil_permissoes pp join perfis p on p.id=pp.perfil_id where p.slug='vendedor' and pp.permissao like 'marketing.%'") === '0', 'grade grava "Sem acesso" para o Vendedor');
  ok(sql("select count(*) from auditoria where acao like '%acesso%' or entidade like '%acesso%'") !== '0', 'mudança de acessos vai para a auditoria');
  await ven.goto(BASE + '/');
  ok(await itemMenu(ven, '/marketing').getAttribute('data-bloqueado') !== null, 'vendedor: Mural com cadeado no menu (a aba continua aparecendo)');
  const rs = await ven.goto(BASE + '/marketing/planejamento');
  ok(rs.status() === 403 && (await ven.content()).includes('Administração › Acessos'), 'abrir a aba sem acesso mostra a página amigável');
  // volta o "Ver"
  await adm.goto(BASE + '/acessos');
  await adm.selectOption(`select[aria-label="${MURAL} · Vendedor"]`, '1');
  await Promise.all([adm.waitForNavigation(), adm.click('button:has-text("Salvar acessos")')]);
  ok(sql("select group_concat(pp.permissao) from perfil_permissoes pp join perfis p on p.id=pp.perfil_id where p.slug='vendedor' and pp.permissao like 'marketing.%'") === 'marketing.ver', '"Ver" grava só a permissão de leitura');

  // 5. Exceção por pessoa: só este vendedor edita o Mural
  const v2 = sql("select id from usuarios where email='televendas2@demo.local'");
  await adm.goto(BASE + `/usuarios/${vend}/editar`);
  await adm.evaluate(() => document.querySelector('details.ac-excecoes').open = true);
  await adm.selectOption(`select[aria-label="Acesso desta pessoa: ${MURAL}"]`, '2');
  await Promise.all([adm.waitForNavigation(), adm.click('button:has-text("Salvar alterações")')]);
  ok(sql(`select concedida from usuario_permissoes where usuario_id=${vend} and permissao='marketing.editar'`) === '1', 'exceção gravada para a pessoa');
  await ven.goto(BASE + '/marketing/planejamento');
  ok(await ven.locator('button', { hasText: 'Salvar campanha' }).count() === 1, 'vendedor com exceção pode criar campanha');
  if (v2) {
    await entrar(ven2, 'televendas2@demo.local');
    await ven2.goto(BASE + '/marketing/planejamento');
    ok(await ven2.locator('button', { hasText: 'Salvar campanha' }).count() === 0, 'outro vendedor continua só lendo');
  }
  // exceção que RETIRA: sem Produtos para este vendedor
  await adm.goto(BASE + `/usuarios/${vend}/editar`);
  await adm.evaluate(() => document.querySelector('details.ac-excecoes').open = true);
  await adm.selectOption('select[aria-label="Acesso desta pessoa: Produtos"]', '0');
  await Promise.all([adm.waitForNavigation(), adm.click('button:has-text("Salvar alterações")')]);
  await ven.goto(BASE + '/');
  ok(await itemMenu(ven, '/produtos').getAttribute('data-bloqueado') !== null, 'exceção "Sem acesso" coloca o cadeado só para a pessoa');

  // Modo leitura em outras telas (Metas, Mensagens, Prospecção, Produtos) para o Gestor/Vendedor conforme a grade
  await adm.goto(BASE + '/acessos');
  await adm.selectOption('select[aria-label="Metas · Vendedor"]', '1');
  await adm.selectOption('select[aria-label="Mensagens prontas · Vendedor"]', '1');
  await Promise.all([adm.waitForNavigation(), adm.click('button:has-text("Salvar acessos")')]);
  await ven2.goto(BASE + '/metas'); // o acesso novo vale já na próxima página (sem sair e entrar)
  ok(await ven2.locator('main input[type=number], main input[name^="valor"]').count() === 0 && await ven2.locator('main button[type=submit]').count() === 0, 'Metas só leitura: sem campos nem botões');
  await ven2.goto(BASE + '/mensagens');
  ok(await ven2.locator('a', { hasText: 'Nova mensagem' }).count() === 0, 'Mensagens só leitura: sem "Nova mensagem"');

  ok(erros.length === 0, 'sem erros de JavaScript ' + erros.join(' / '));
  await b.close();
  console.log(falhas ? `\n${falhas} FALHA(S)` : '\nTudo certo');
  process.exit(falhas ? 1 : 0);
})();
