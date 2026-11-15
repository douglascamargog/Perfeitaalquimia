// Teste automático (Playwright). Rodar todos: bash tests/rodar.sh  ·  só este: node tests/teste_ficha_rapida.js
// Ficha do cliente mais prática: anotação com print colado (Ctrl+V), arquivo protegido, agendar atividade (Agenda),
// "Próximas atividades" com Concluir, e o discador do topo (Televendas e Cobrança) para qualquer número.
const { chromium } = require('playwright');
const { execSync } = require('child_process');
const BASE = 'http://127.0.0.1:8000';
let falhas = 0; const ok = (c, m) => { console.log((c ? 'OK   ' : 'FALHA') + ' ' + m); if (!c) falhas++; };
const sql = (q) => execSync(`mysql perfeita --default-character-set=utf8mb4 -N -e "${q.replace(/"/g, '\\"')}"`).toString().trim();
const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

(async () => {
  const b = await chromium.launch();
  const erros = [];
  const como = async (email, opcoes = {}) => {
    const p = await (await b.newContext({ viewport: { width: 1360, height: 900 }, ...opcoes })).newPage();
    p.on('pageerror', (e) => erros.push(e.message + ' @ ' + p.url())); await p.route(/open-meteo|jsdelivr/, (r) => r.fulfill({ body: '{}' }));
    await p.goto(BASE + '/entrar'); await p.fill('#email', email); await p.fill('#senha', 'Demo@2026'); await p.click('button[type=submit]'); await p.waitForLoadState();
    if (await p.locator('[data-novidades-ok]').count()) { await p.click('[data-novidades-ok]'); await p.waitForLoadState(); }
    return { p, csrf: await p.evaluate(() => document.querySelector('meta[name=csrf]').content) };
  };
  const uid = (email) => sql(`select id from usuarios where email='${email}'`);
  const t1 = uid('televendas1@demo.local');
  const cli = sql(`select id from clientes where responsavel_id=${t1} order by id limit 1`);

  // ---------- Anotação com print colado ----------
  const v = await como('televendas1@demo.local');
  await v.p.goto(BASE + `/carteira/clientes/${cli}`);
  const caixa = v.p.locator('.cl-principal [data-compositor]');
  ok(await caixa.count() === 1, 'página do cliente tem a caixa rápida na linha do tempo');
  ok(await caixa.locator('[data-cx-painel]:visible').count() === 1, 'só a aba escolhida aparece (Anotação)');
  await caixa.locator('[data-cx-texto]').fill('Cliente pediu a tabela nova por e-mail');
  // simula Ctrl+V de uma imagem
  await caixa.locator('[data-cx-texto]').evaluate((el, b64) => {
    const bin = Uint8Array.from(atob(b64), (c) => c.charCodeAt(0));
    const dt = new DataTransfer();
    dt.items.add(new File([bin], 'image.png', { type: 'image/png' }));
    el.dispatchEvent(new ClipboardEvent('paste', { clipboardData: dt, bubbles: true, cancelable: true }));
  }, PNG);
  ok(await caixa.locator('.cx-previa img').count() === 1, 'print colado aparece como miniatura antes de salvar');
  await Promise.all([v.p.waitForNavigation(), caixa.locator('[data-cx-painel="nota"] button[type=submit]').click()]);
  const ev = sql(`select id from cliente_eventos where cliente_id=${cli} and tipo='nota' order by id desc limit 1`);
  ok(sql(`select descricao from cliente_eventos where id=${ev}`) === 'Cliente pediu a tabela nova por e-mail', 'anotação salva na linha do tempo');
  const anexo = sql(`select id from cliente_anexos where evento_id=${ev}`);
  ok(!!anexo && sql(`select mime from cliente_anexos where id=${anexo}`) === 'image/png', 'print guardado com a anotação');
  ok(await v.p.locator('.cl-linha-tempo [data-anexos] img').count() >= 1, 'linha do tempo mostra a miniatura');
  const img = await v.p.request.get(BASE + `/carteira/clientes/${cli}/anexos/${anexo}`);
  ok(img.status() === 200 && img.headers()['content-type'] === 'image/png', 'quem vê o cliente abre o print');
  const v2 = await como('televendas2@demo.local');
  ok((await v2.p.request.get(BASE + `/carteira/clientes/${cli}/anexos/${anexo}`)).status() === 404, 'outra vendedora não abre o print');
  // arquivo que não é imagem/PDF
  const r = await v.p.request.post(BASE + `/carteira/clientes/${cli}/anotar`, { headers: { Accept: 'application/json' },
    multipart: { _csrf: v.csrf, texto: 'x', 'anexos[]': { name: 'virus.exe', mimeType: 'application/octet-stream', buffer: Buffer.from('MZ\x90\x00 nada') } } });
  ok(r.status() === 422 && (await r.json()).mensagem.includes('imagem'), 'arquivo que não é imagem nem PDF é recusado');
  ok((await (await v.p.request.post(BASE + `/carteira/clientes/${cli}/anotar`, { headers: { Accept: 'application/json' }, form: { _csrf: v.csrf, texto: '' } })).json()).ok === false, 'anotação vazia não salva');

  // ---------- Agendar ----------
  const amanha = sql('select curdate() + interval 1 day');
  await v.p.click('.cl-principal [data-cx-aba="agendar"]');
  const ag = v.p.locator('.cl-principal [data-cx-painel="agendar"]');
  await ag.locator('select[name=atividade]').selectOption('ligar');
  await ag.locator('input[name=data]').fill(amanha);
  await ag.locator('input[name=hora]').fill('14:00');
  await ag.locator('input[name=notas]').fill('Oferecer a campanha de Magnésio');
  await Promise.all([v.p.waitForNavigation(), ag.locator('button[type=submit]').click()]);
  const tarefa = sql(`select id from tarefas where cliente_id=${cli} and titulo like 'Ligar: Oferecer%' order by id desc limit 1`);
  ok(!!tarefa && sql(`select concat(vence_em,' ',substr(hora,1,5),' ',usuario_id) from tarefas where id=${tarefa}`) === `${amanha} 14:00 ${t1}`, 'atividade agendada vira tarefa (dia, hora, dono)');
  ok(sql(`select count(*) from cliente_eventos where cliente_id=${cli} and tipo='tarefa' and descricao like 'Agendou: Ligar%'`) === '1', 'agendamento na linha do tempo');
  ok(await v.p.locator(`[data-proximas] [data-tarefa="${tarefa}"]`).count() === 1, 'aparece em "Próximas atividades"');
  await v.p.goto(BASE + '/agenda?ver=lista'); // em lista: "amanhã" pode já ser a semana seguinte (num domingo)
  ok((await v.p.content()).includes('Oferecer a campanha'), 'aparece na Agenda');
  await v.p.goto(BASE + `/carteira/clientes/${cli}`);
  await Promise.all([v.p.waitForNavigation(), v.p.click(`[data-proximas] [data-tarefa="${tarefa}"] button`)]);
  ok(v.p.url().endsWith(`/carteira/clientes/${cli}`) && sql(`select concluida_em is not null from tarefas where id=${tarefa}`) === '1', '"Concluir" fecha a tarefa e volta para a ficha');
  ok((await (await v.p.request.post(BASE + `/carteira/clientes/${cli}/agendar`, { headers: { Accept: 'application/json' }, form: { _csrf: v.csrf, atividade: 'ligar', data: '2000-01-01' } })).json()).ok === false, 'data no passado não agenda');
  // gaveta da Carteira também tem a caixa
  await v.p.goto(BASE + `/carteira/lista?abrir=${cli}`);
  await v.p.waitForSelector('.gaveta [data-compositor]', { timeout: 8000 }).catch(() => null);
  ok(await v.p.locator('.gaveta [data-compositor]').count() === 1, 'a gaveta da Carteira também tem a caixa rápida');

  // ---------- Discador ----------
  ok(await v.p.locator('[data-discador-botao]').count() === 1, 'Televendas vê o discador ao lado do sino');
  const prod = await como('producao@demo.local');
  ok(await prod.p.locator('[data-discador-botao]').count() === 0, 'Produção não vê o discador');
  ok((await prod.p.request.post(BASE + '/telefonia/ligar', { headers: { Accept: 'application/json' }, form: { _csrf: prod.csrf, numero: '51999999999' } })).status() === 403, '...nem liga pela URL');
  const tel = sql(`select telefone from clientes where id=${cli}`);
  const antes = Number(sql(`select count(*) from ligacoes where cliente_id=${cli}`));
  await v.p.goto(BASE + '/');
  await v.p.click('[data-discador-botao]');
  await v.p.fill('[data-discador-numero]', tel);
  await v.p.click('[data-discador-ligar]');
  await v.p.waitForSelector('[data-discador-cliente]', { timeout: 8000 }).catch(() => null);
  ok(await v.p.locator(`[data-discador-cliente="${cli}"]`).count() === 1, 'número de cliente: mostra o atalho para a ficha');
  ok(Number(sql(`select count(*) from ligacoes where cliente_id=${cli}`)) === antes + 1, 'ligação gravada na ficha do cliente');
  const avulsas = Number(sql("select count(*) from ligacoes where cliente_id is null and numero='51987654321'"));
  const ra = await (await v.p.request.post(BASE + '/telefonia/ligar', { headers: { Accept: 'application/json' }, form: { _csrf: v.csrf, numero: '(51) 98765-4321' } })).json();
  ok(ra.ok && ra.cliente === null && Number(sql("select count(*) from ligacoes where cliente_id is null and numero='51987654321'")) === avulsas + 1, 'número avulso: liga e grava sem cliente');
  const ri = await v.p.request.post(BASE + '/telefonia/ligar', { headers: { Accept: 'application/json' }, form: { _csrf: v.csrf, numero: '123' } });
  ok(ri.status() === 422, 'número inválido é recusado');
  const cob = await como('financeiro@demo.local'); // função Cobrança na demonstração
  ok(await cob.p.locator('[data-discador-botao]').count() === 1, 'Cobrança vê o discador');

  // ---------- Pedido novo: aviso para a gestão + letreiro no topo ----------
  const okCli = sql(`select id from clientes where responsavel_id=${t1} and credito_situacao='ok' and tabela_preco_id=1 order by id limit 1`);
  const prodPed = sql('select p.id from produtos p join produto_precos pp on pp.produto_id=p.id and pp.tabela_preco_id=1 where p.ativo=1 and p.visivel_catalogo=1 order by p.id limit 1');
  const qtdPed = Math.ceil(2000 / Number(sql(`select preco from produto_precos where produto_id=${prodPed} and tabela_preco_id=1`)));
  await v.p.request.post(BASE + '/pedidos', { form: { _csrf: v.csrf, cliente_id: okCli, pagamento: '1:1', [`qtd[${prodPed}]`]: String(qtdPed) } });
  const novoPed = sql(`select max(id) from pedidos where cliente_id=${okCli}`);
  const gestor = uid('gestor@demo.local');
  ok(sql(`select count(*) from notificacoes where tipo='pedido_novo' and usuario_id=${gestor} and pedido_id=${novoPed}`) === '1'
    && sql(`select titulo from notificacoes where tipo='pedido_novo' and usuario_id=${gestor} and pedido_id=${novoPed}`).includes('Televendas 1'), 'gestão recebe "Novo pedido feito por (vendedor)"');
  ok(sql(`select count(*) from notificacoes where tipo='pedido_novo' and usuario_id=${t1}`) === '0', '...a vendedora que fez não recebe o próprio');
  const g = await (await b.newContext({ viewport: { width: 1360, height: 900 } })).newPage();
  g.on('pageerror', (e) => erros.push(e.message + ' @ ' + g.url())); await g.route(/open-meteo|jsdelivr/, (r) => r.fulfill({ body: '{}' }));
  await g.addInitScript((n) => { try { localStorage.setItem('one:pedido-letreiro', String(n)); } catch {} }, Number(novoPed) - 1);
  await g.goto(BASE + '/entrar'); await g.fill('#email', 'gestor@demo.local'); await g.fill('#senha', 'Demo@2026'); await g.click('button[type=submit]'); await g.waitForLoadState();
  if (await g.locator('[data-novidades-ok]').count()) { await g.click('[data-novidades-ok]'); await g.waitForLoadState(); }
  await g.evaluate(() => document.dispatchEvent(new Event('visibilitychange')));
  await g.waitForSelector(`[data-letreiro-pedido="${novoPed}"]`, { timeout: 8000 }).catch(() => null);
  const letreiro = await g.locator('[data-letreiro]').innerText().catch(() => '');
  ok(letreiro.includes('Novo pedido feito por') && await g.locator('[data-letreiro]').isVisible(), 'letreiro do topo: "Novo pedido feito por…" passando');
  const anim = await g.evaluate(() => getComputedStyle(document.querySelector('.letreiro-rolar')).animationName);
  ok(anim === 'letreiro-passar', 'o texto do letreiro está em movimento');
  if (await g.locator('[data-com-lugar] .com-faixa').count()) {
    ok(await g.evaluate(() => getComputedStyle(document.querySelector('.com-rolar')).animationName) === 'letreiro-passar', 'comunicado também passa em movimento (até "Li e entendi")');
  }

  // ---------- Foto do produto (admin) ----------
  const adm = await como('admin@demo.local');
  const slug = sql(`select slug from produtos where id=${prodPed}`);
  await adm.p.goto(BASE + `/produtos/${slug}`);
  ok(await adm.p.locator('[data-trocar-foto]').count() === 1, 'admin vê "Trocar foto" no produto');
  const fotoAntes = sql(`select coalesce(imagem_id,0) from produtos where id=${prodPed}`);
  const foto = require('fs').readFileSync(require('path').join(__dirname, '..', 'public', 'assets', 'img', 'logo.png'));
  await Promise.all([adm.p.waitForNavigation(), adm.p.setInputFiles('[data-trocar-foto] input[type=file]', { name: 'nova.png', mimeType: 'image/png', buffer: foto })]);
  const fotoDepois = sql(`select imagem_id from produtos where id=${prodPed}`);
  ok(fotoDepois !== fotoAntes && sql(`select arquivo from midias where id=${fotoDepois}`).startsWith('media/produtos/'), 'foto trocada (vale para catálogo, Academia, pedido e orçamento)');
  const srcFoto = await adm.p.getAttribute('[data-foto-produto]', 'src');
  ok(!!srcFoto && (await adm.p.request.get(BASE + srcFoto.replace(/^.*?\/media\//, '/media/'))).status() === 200, 'a página do produto mostra a foto nova');
  ok((await v.p.request.post(BASE + `/produtos/${slug}/foto`, { multipart: { _csrf: v.csrf, foto: { name: 'x.png', mimeType: 'image/png', buffer: foto } } })).status() === 403, 'vendedora não troca a foto');
  ok((await adm.p.request.post(BASE + `/produtos/${slug}/foto`, { multipart: { _csrf: adm.csrf, foto: { name: 'x.txt', mimeType: 'text/plain', buffer: Buffer.from('oi') } } }).then((r) => r.text())).includes('JPG, PNG ou WEBP'), 'arquivo que não é foto é recusado');

  // ---------- Celular ----------
  const cel = await como('televendas1@demo.local', { viewport: { width: 390, height: 844 }, isMobile: true });
  await cel.p.goto(BASE + `/carteira/clientes/${cli}`);
  ok(await cel.p.evaluate(() => document.documentElement.scrollWidth) <= 392, 'página do cliente com a caixa cabe no celular');
  await cel.p.click('[data-discador-botao]');
  ok(await cel.p.locator('.discador-teclas button').first().isVisible() && await cel.p.evaluate(() => document.documentElement.scrollWidth) <= 392, 'discador com teclado no celular, sem rolar para o lado');

  ok(erros.length === 0, 'nenhum erro de JavaScript' + (erros.length ? ': ' + erros.join(' / ') : ''));
  await b.close();
  console.log(falhas ? `\n${falhas} FALHA(S)` : '\nTudo certo.');
  process.exit(falhas ? 1 : 0);
})();
