// Teste automático (Playwright). Rodar todos: bash tests/rodar.sh  ·  só este: node tests/teste_prospeccao.js
const path = require('path');
const RECURSOS = path.join(__dirname, 'recursos');
const { chromium } = require('playwright');
const { execSync } = require('child_process');
const BASE = 'http://127.0.0.1:8000', DIR = process.argv[2] || path.join(__dirname, 'saida');
let falhas = 0; const ok = (c, m) => { console.log((c ? 'OK   ' : 'FALHA') + ' ' + m); if (!c) falhas++; };
const sql = (q) => execSync(`mysql perfeita --default-character-set=utf8mb4 -N -e "${q.replace(/"/g, '\\"')}"`).toString().trim();
async function entrar(p, email) { await p.goto(BASE + '/entrar'); await p.fill('#email', email); await p.fill('#senha', 'Demo@2026'); await p.click('button[type=submit]'); await p.waitForLoadState(); }
async function sair(p) { await p.goto(BASE + '/'); await p.click('form[action$="/sair"] button'); await p.waitForLoadState(); }
const aviso = async (p) => (await p.locator('.aviso').allInnerTexts()).join(' | ');
(async () => {
  const b = await chromium.launch(); const ctx = await b.newContext({ viewport: { width: 1440, height: 1000 } }); const p = await ctx.newPage();
  const erros = []; p.on('pageerror', e => erros.push(e.message));
  await p.route('https://cdn.jsdelivr.net/**', r => r.fulfill({ body: '' }));
  await p.route('https://api.open-meteo.com/**', r => r.fulfill({ json: {} }));

  // ---------- Sem "Black Friday" no sistema ----------
  ok(sql("select count(*) from origens where nome like '%Black Friday%'") === '0', 'origem renomeada (sem Black Friday)');
  ok(sql("select count(*) from clientes c join origens o on o.id=c.origem_id where o.nome='Lista de prospecção — Grande Porto Alegre'") === '499', 'as 499 lojas continuam ligadas à origem renomeada');

  // ---------- Vendedor: Para abordar ----------
  await entrar(p, 'televendas1@demo.local');
  const vend = sql("select id from usuarios where email='televendas1@demo.local'");
  for (const u of ['/', '/carteira', '/prospeccao']) { await p.goto(BASE + u); ok(!(await p.content()).includes('Black Friday'), `sem "Black Friday" em ${u}`); }
  await p.goto(BASE + '/prospeccao');
  const aguardando = sql(`select count(*) from clientes where excluido_em is null and tipo='lead' and contatado=0 and responsavel_id=${vend}`);
  ok((await p.locator('[data-aguardando]').innerText()).replace('.', '') === aguardando, `fila do vendedor: ${aguardando} aguardando (só a carteira dele)`);
  ok(await p.locator('.menu-lateral a[href$="/prospeccao"]').count() === 1 && await p.locator('.menu-lateral .em-breve').filter({ hasText: 'Prospecção' }).count() === 0, 'Prospecção no menu (pronta)');
  const r302 = await p.request.get(BASE + '/carteira/campanha', { maxRedirects: 0 });
  ok(r302.status() === 302 && (r302.headers()['location'] || '').endsWith('/prospeccao'), 'endereço antigo /carteira/campanha leva à Prospecção');

  // ---------- Encontrar empresas ----------
  const cidade = 'Caxias do Sul', cnae = '4771701';
  await p.goto(BASE + `/prospeccao/empresas?uf=RS&cidade=${encodeURIComponent(cidade)}&cnae=${cnae}`);
  const totalBanco = sql(`select count(*) from prospeccao_empresas where uf='RS' and cidade='${cidade}' and cnae='${cnae}'`);
  ok((await p.locator('.paginacao-barra span').first().innerText()).startsWith(totalBanco), `filtro cidade + CNAE bate com o banco (${totalBanco})`);
  // "já na carteira": um CNPJ de cliente aparece marcado e sem caixinha
  const cnpjCli = sql("select e.cnpj from prospeccao_empresas e join clientes c on c.cnpj=e.cnpj limit 1");
  await p.goto(BASE + `/prospeccao/empresas?q=${cnpjCli}`);
  ok(await p.locator(`tr[data-empresa="${cnpjCli}"] [data-na-carteira], tr[data-empresa="${cnpjCli}"] .selo.verde`).count() === 1 && await p.locator(`tr[data-empresa="${cnpjCli}"] input[data-marcar]`).count() === 0, 'empresa da carteira aparece marcada e não pode ser enviada');
  // envia 3 livres
  await p.goto(BASE + `/prospeccao/empresas?uf=RS&cidade=${encodeURIComponent(cidade)}&esconder=1`);
  ok(await p.locator('[data-barra-envio]').isHidden(), 'barra de envio escondida sem nada marcado');
  const marcar = p.locator('input[data-marcar]');
  const cnpjs = [];
  for (let i = 0; i < 3; i++) { await marcar.nth(i).check(); cnpjs.push(await marcar.nth(i).inputValue()); }
  ok(await p.locator('[data-barra-envio]').isVisible() && (await p.locator('[data-qtd-marcadas]').innerText()) === '3', 'barra mostra 3 marcadas');
  // um deles vira "já existe" no meio do caminho (outra pessoa cadastrou)
  sql(`insert into clientes (razao_social, cnpj, tipo, responsavel_id, uf, cidade) values ('Cadastrada por outra pessoa', '${cnpjs[2]}', 'lead', ${vend}, 'RS', '${cidade}')`);
  await Promise.all([p.waitForNavigation(), p.click('[data-barra-envio] button[type=submit]')]);
  ok((await aviso(p)).includes('2 lead(s) criado(s)') && (await aviso(p)).includes('1 já estavam'), 'cria 2 e avisa 1 que já estava');
  const novo = sql(`select concat_ws('|', c.tipo, o.nome, e.nome, c.responsavel_id) from clientes c join origens o on o.id=c.origem_id join funil_etapas e on e.id=c.etapa_id where c.cnpj='${cnpjs[0]}'`);
  ok(novo === `lead|Prospecção automática|Não abordado|${vend}`, 'lead com origem, etapa e responsável (o vendedor)');
  ok(Number(sql(`select count(*) from cliente_eventos ev join clientes c on c.id=ev.cliente_id where c.cnpj='${cnpjs[0]}'`)) >= 1, 'evento na linha do tempo');
  ok(sql(`select count(*) from prospeccao_envios where cnpj in ('${cnpjs[0]}','${cnpjs[1]}')`) === '2', 'envios registrados');
  // reenviar não duplica
  const tok = await p.locator('input[name=_csrf]').first().inputValue();
  await p.request.post(BASE + '/prospeccao/enviar', { form: { _csrf: tok, 'cnpjs[]': cnpjs[0] } });
  ok(sql(`select count(*) from clientes where cnpj='${cnpjs[0]}'`) === '1', 'enviar de novo não duplica');

  // ---------- Mensagem pronta na ficha ----------
  const cli = sql(`select id from clientes where responsavel_id=${vend} and whatsapp is not null and tipo='cliente' and excluido_em is null order by id limit 1`);
  const nomeCli = sql(`select coalesce(nullif(nome_fantasia,''), razao_social) from clientes where id=${cli}`);
  const codigo = sql(`select codigo_vendedor from usuarios where id=${vend}`);
  const whats = sql(`select whatsapp from clientes where id=${cli}`);
  await p.goto(BASE + '/carteira/lista');
  await p.evaluate((id) => { const b = document.createElement('button'); b.dataset.abrirCliente = id; b.id = 'abrir-teste'; document.body.append(b); }, cli);
  await p.click('#abrir-teste');
  await p.waitForSelector('[data-msg-pronta]');
  const modelo = sql("select id from mensagens_prontas where titulo='Catálogo do vendedor'");
  await p.selectOption('[data-msg-modelo]', modelo);
  const texto = await p.inputValue('[data-msg-texto]');
  ok(texto.includes(nomeCli) && texto.includes('/catalogo/v/' + codigo) && !texto.includes('{'), 'texto preenchido com o nome do cliente e o link do vendedor');
  const usosAntes = Number(sql(`select usos from mensagens_prontas where id=${modelo}`));
  await p.evaluate(() => { window.__abertos = []; window.open = (u) => { window.__abertos.push(u); return null; }; });
  await p.click('[data-msg-enviar]');
  const aberto = (await p.evaluate(() => window.__abertos))[0] || '';
  ok(aberto.startsWith('https://wa.me/55' + whats + '?text=') && decodeURIComponent(aberto).includes(nomeCli), 'abre o wa.me do cliente com o texto');
  await p.waitForTimeout(800);
  ok(Number(sql(`select usos from mensagens_prontas where id=${modelo}`)) === usosAntes + 1, 'conta o uso do modelo');
  ok(Number(sql(`select count(*) from cliente_eventos where cliente_id=${cli} and descricao like 'Mensagem pronta: Catálogo do vendedor%'`)) === 1, 'grava o evento na linha do tempo');
  const pCodigo = encodeURIComponent('/catalogo/v/' + codigo);
  // vendedor não edita modelos
  ok((await p.goto(BASE + '/mensagens')).status() === 403, 'vendedor não acessa /mensagens (403)');
  await sair(p);

  // ---------- Gestor: envia escolhendo outro vendedor; fila completa ----------
  await entrar(p, 'gestor@demo.local');
  const outro = sql("select id from usuarios where email='externo1@demo.local'") || vend;
  await p.goto(BASE + '/prospeccao/empresas?uf=RS&cidade=Pelotas&esconder=1');
  const c4 = await p.locator('input[data-marcar]').first().inputValue();
  await p.locator('input[data-marcar]').first().check();
  await p.selectOption('[data-barra-envio] select[name=responsavel]', outro);
  await Promise.all([p.waitForNavigation(), p.click('[data-barra-envio] button[type=submit]')]);
  ok(sql(`select responsavel_id from clientes where cnpj='${c4}'`) === outro, 'gestor escolhe o responsável');
  await p.goto(BASE + '/prospeccao');
  const aguardTodos = sql("select count(*) from clientes where excluido_em is null and tipo='lead' and contatado=0");
  ok((await p.locator('[data-aguardando]').innerText()).replace('.', '') === aguardTodos, `gestor vê a fila inteira (${aguardTodos})`);
  ok(await p.locator('[data-card=origens] tbody tr').count() >= 4, 'tabela "qual origem funciona melhor"');
  ok(await p.locator('[data-card=equipe]').count() === 1, 'abordagens da equipe (gestor)');

  // ---------- Mensagens: criar modelo com prévia ----------
  await p.goto(BASE + '/mensagens/nova');
  await p.fill('#titulo', 'Teste prévia'); await p.selectOption('#categoria', 'Outros');
  await p.fill('[data-texto]', 'Oi '); await p.click('[data-variavel="{nome}"]'); await p.type('[data-texto]', ', veja: ');
  await p.click('[data-variavel="{link_vendedor}"]'); await p.type('[data-texto]', ' {ultima_compra}');
  const previa = await p.locator('[data-previa]').innerText();
  ok(previa.startsWith('Oi ') && !previa.includes('{') && previa.includes('/catalogo'), 'prévia troca as variáveis ao vivo');
  await Promise.all([p.waitForNavigation(), p.click('[data-form-mensagem] button[type=submit]')]);
  ok(sql("select count(*) from mensagens_prontas where titulo='Teste prévia'") === '1', 'modelo salvo');
  await p.goto(BASE + '/mensagens/nova');
  await p.fill('#titulo', 'Errada'); await p.fill('[data-texto]', 'Oi {nome_errado}');
  await Promise.all([p.waitForNavigation(), p.click('[data-form-mensagem] button[type=submit]')]);
  ok((await aviso(p)).includes('Variável desconhecida'), 'recusa variável desconhecida');
  await sair(p);

  // ---------- Leads ----------
  const api = await (await chromium.launch()).newContext();
  const req = api.request;
  const funcaoLeads = sql("select id from funcoes where slug='prospeccao_reativacao'");
  let r = await req.post(BASE + '/leads/receber', { data: { nome: 'Teste Lead', empresa: 'Loja Teste UTM', whatsapp: '(51) 98888-7777', email: 'lead@teste.com', cidade: 'Canoas', uf: 'RS',
    utm_source: 'instagram', utm_medium: 'cpc', utm_campaign: 'teste-camp', utm_term: 'termo', utm_content: 'video1', inicio: Math.floor(Date.now() / 1000) - 30 }, headers: { Origin: 'https://site.exemplo' } });
  let j = await r.json();
  ok(r.status() === 200 && j.ok, 'endpoint recebe o lead (JSON, sem login)');
  const lead = sql("select concat_ws('|', c.tipo, o.nome, c.utm_source, c.utm_medium, c.utm_campaign, c.utm_term, c.utm_content, c.funcao_id) from clientes c join origens o on o.id=c.origem_id where c.whatsapp='51988887777'");
  ok(lead === `lead|Anúncio (tráfego pago)|instagram|cpc|teste-camp|termo|video1|${funcaoLeads}`, 'lead criado como Anúncio, com as 5 UTMs, na função de novos leads');
  ok(sql("select count(*) from tarefas t join clientes c on c.id=t.cliente_id where c.whatsapp='51988887777' and t.vence_em=curdate() and t.concluida_em is null") === '1', 'tarefa de retorno para hoje');
  ok(r.headers()['access-control-allow-origin'] === 'https://site.exemplo', 'CORS liberado para o site');
  r = await req.post(BASE + '/leads/receber', { form: { nome: 'Teste Lead de novo', whatsapp: '51988887777' } });
  ok((await r.json()).ok && sql("select count(*) from clientes where whatsapp='51988887777'") === '1', 'mesmo WhatsApp não duplica');
  ok(sql("select resultado from leads_recebidos order by id desc limit 1") === 'existente' && Number(sql("select count(*) from cliente_eventos ev join clientes c on c.id=ev.cliente_id where c.whatsapp='51988887777' and ev.descricao like 'Novo contato%'")) === 1, 'registra "Novo contato" no cliente existente');
  r = await req.post(BASE + '/leads/receber', { form: { nome: 'Robo', whatsapp: '51977776666', site_url: 'http://spam' } });
  ok(sql("select count(*) from clientes where whatsapp='51977776666'") === '0' && sql("select resultado from leads_recebidos order by id desc limit 1") === 'bloqueado', 'honeypot bloqueia robô');
  r = await req.post(BASE + '/leads/receber', { form: { nome: 'X', whatsapp: '' } });
  ok(r.status() === 422, 'dados inválidos → 422');
  let ult = 200;
  for (let i = 0; i < 6; i++) { ult = (await req.post(BASE + '/leads/receber', { form: { nome: 'Rajada ' + i, whatsapp: '5196666000' + i } })).status(); }
  ok(ult === 429, 'limite por hora → 429');
  ok(sql("select count(*) from leads_recebidos where ip_hash like '127.0.0.1%' or ip_hash = '127.0.0.1'") === '0' && sql("select length(ip_hash) from leads_recebidos order by id desc limit 1") === '64', 'IP guardado só como hash');
  await api.close();
  sql("delete from leads_recebidos where nome like 'Rajada%' or nome in ('Robo','X')"); // libera o limite para a página pública (só no teste)

  // página pública com UTM
  const pub = await (await b.newContext({ viewport: { width: 390, height: 844 } })).newPage();
  await pub.goto(BASE + '/seja-revendedor?utm_source=instagram&utm_medium=cpc&utm_campaign=lp-teste');
  ok(await pub.locator('.menu-lateral').count() === 0, 'página pública sem o menu do sistema');
  await pub.fill('#nome', 'Página Pública'); await pub.fill('#empresa', 'Loja da Página'); await pub.fill('#whatsapp', '(51) 97777-1234'); await pub.selectOption('#uf', 'RS');
  await pub.waitForTimeout(3100);
  await Promise.all([pub.waitForNavigation(), pub.click('[data-form-lead] button[type=submit]')]);
  ok(await pub.locator('[data-obrigado]').count() === 1, 'mostra o "obrigado"');
  ok(sql("select concat_ws('|', o.nome, c.utm_campaign) from clientes c join origens o on o.id=c.origem_id where c.whatsapp='51977771234'") === 'Anúncio (tráfego pago)|lp-teste', 'lead da página com a UTM do anúncio');

  // tela Leads (marketing)
  await entrar(p, 'marketing@demo.local');
  await p.goto(BASE + '/leads?dias=30');
  const linhaCamp = p.locator('[data-tabela-campanhas] tr', { hasText: 'teste-camp' });
  ok(await linhaCamp.count() === 1 && (await linhaCamp.locator('td').nth(3).innerText()) === '1', 'tabela por campanha conta o lead da campanha');
  const totalLeads = sql("select count(*) from leads_recebidos where resultado in ('criado','existente') and criado_em >= curdate() - interval 29 day");
  ok((await p.locator('[data-leads-total]').innerText()).replace('.', '') === totalLeads, `total de leads bate (${totalLeads})`);
  ok((await p.goto(BASE + '/prospeccao')).status() === 403, 'marketing não acessa a Prospecção (403)');
  await sair(p);

  ok(erros.length === 0, 'sem erros de JavaScript ' + erros.join(' / '));
  await b.close();
  console.log(falhas ? `\n${falhas} FALHA(S)` : '\nTudo certo');
  process.exit(falhas ? 1 : 0);
})();
