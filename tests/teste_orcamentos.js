// Teste automático (Playwright). Rodar todos: bash tests/rodar.sh  ·  só este: node tests/teste_orcamentos.js
// Orçamento por link e modelos de pedido: salvar modelo pessoal e de campanha, usar dois modelos (soma), gerar orçamento,
// copiar/WhatsApp/imagem, link sem senha (visto + aviso), cliente muda e acrescenta e aprova (vira pedido sem duplicar),
// boleto bloqueado, recusar com motivo, vencido, cancelado, link errado, outro vendedor não vê, transformar em pedido.
const { chromium } = require('playwright');
const { execSync } = require('child_process');
const BASE = 'http://127.0.0.1:8000';
let falhas = 0; const ok = (c, m) => { console.log((c ? 'OK   ' : 'FALHA') + ' ' + m); if (!c) falhas++; };
const sql = (q) => execSync(`mysql perfeita --default-character-set=utf8mb4 -N -e "${q.replace(/"/g, '\\"')}"`).toString().trim();

(async () => {
  sql('update campanhas set ativa = 0'); // este teste confere preços de tabela: sem campanhas (elas têm o próprio teste)
  const b = await chromium.launch();
  const erros = [];
  const pagina = async (opcoes = {}) => {
    const p = await (await b.newContext({ viewport: { width: 1300, height: 900 }, acceptDownloads: true, ...opcoes })).newPage();
    p.on('pageerror', (e) => erros.push(e.message + ' @ ' + p.url() + ' ' + (e.stack || '').split('\n')[1])); await p.route(/open-meteo|jsdelivr/, (r) => r.fulfill({ body: '{}' }));
    return p;
  };
  const como = async (email) => {
    const p = await pagina();
    await p.goto(BASE + '/entrar'); await p.fill('#email', email); await p.fill('#senha', 'Demo@2026'); await p.click('button[type=submit]'); await p.waitForLoadState();
    if (await p.locator('[data-novidades-ok]').count()) { await p.click('[data-novidades-ok]'); await p.waitForLoadState(); }
    return { p, csrf: await p.evaluate(() => document.querySelector('meta[name=csrf]').content) };
  };
  const post = async (s, caminho, form = {}) => {
    const r = await s.p.request.post(BASE + caminho, { form: { _csrf: s.csrf, ...form } });
    return { url: r.url(), html: await r.text(), status: r.status() };
  };
  const avisos = (html) => [...html.matchAll(/class="aviso[- ][a-z ]*"[^>]*>([^<]*)</g)].map((m) => m[1]).join(' | ');

  const vend = sql("select id from usuarios where email='televendas1@demo.local'");
  // Cliente do vendedor, sem atraso, com WhatsApp; e um com título em atraso (boleto bloqueado)
  const cli = sql(`select id from clientes where responsavel_id=${vend} and credito_situacao='ok' and whatsapp<>'' and tabela_preco_id=1 order by id limit 1`);
  const cliAtraso = sql(`select id from clientes where responsavel_id=${vend} and credito_situacao='atraso' order by id limit 1`);
  const [p1, p2, p3, p4] = sql('select p.id from produtos p join produto_precos pp on pp.produto_id=p.id and pp.tabela_preco_id=1 where p.ativo=1 and p.visivel_catalogo=1 order by p.id limit 4').split('\n');
  const preco = (id) => Number(sql(`select preco from produto_precos where produto_id=${id} and tabela_preco_id=1`));
  const minimo = Number(sql(`select coalesce(ru.pedido_minimo, r.pedido_minimo) from regiao_ufs ru join regioes r on r.id=ru.regiao_id where ru.uf=(select uf from clientes where id=${cli})`) || 0);
  const grande = Math.ceil((minimo + 100) / preco(p1)); // quantidade que passa do pedido mínimo

  // ---------- Modelos ----------
  const v = await como('televendas1@demo.local');
  await v.p.goto(BASE + `/pedidos/novo?cliente=${cli}`);
  ok(await v.p.locator('[data-modelos]').count() === 1 && await v.p.locator('[data-gerar-orcamento]').count() === 1, 'Novo pedido tem "Usar um modelo" e "Gerar orçamento"');
  await v.p.fill(`.ped-qtd[name="qtd[${p1}]"]`, '3');
  await v.p.fill(`.ped-qtd[name="qtd[${p2}]"]`, '2');
  await v.p.click('[data-salvar-modelo] summary');
  await v.p.fill('input[name=modelo_nome]', 'Primeiro pedido teste');
  await Promise.all([v.p.waitForNavigation(), v.p.click('[data-salvar-modelo-botao]')]);
  const mPessoal = sql("select id from pedido_modelos where nome='Primeiro pedido teste'");
  ok(!!mPessoal && sql(`select tipo, dono_id from pedido_modelos where id=${mPessoal}`) === `pessoal\t${vend}` && sql(`select count(*) from pedido_modelo_itens where modelo_id=${mPessoal}`) === '2', 'modelo pessoal salvo com 2 produtos');
  ok(await v.p.inputValue(`.ped-qtd[name="qtd[${p1}]"]`) === '3', 'a tela volta com as quantidades escolhidas');
  // vendedor não cria campanha
  await post(v, '/pedidos/modelos', { cliente_id: cli, modelo_nome: 'Campanha proibida', modelo_tipo: 'campanha', [`qtd[${p1}]`]: '1' });
  ok(sql("select count(*) from pedido_modelos where nome='Campanha proibida'") === '0', 'vendedora não cria modelo de campanha');
  const g = await como('gestor@demo.local');
  await post(g, '/pedidos/modelos', { cliente_id: cli, modelo_nome: 'Campanha Black', modelo_tipo: 'campanha', modelo_funcao: '', modelo_validade: '2099-12-31', [`qtd[${p1}]`]: '10', [`qtd[${p3}]`]: '5' });
  const mCamp = sql("select id from pedido_modelos where nome='Campanha Black'");
  ok(!!mCamp && sql(`select tipo from pedido_modelos where id=${mCamp}`) === 'campanha', 'gestão cria campanha para todos');

  await v.p.goto(BASE + `/pedidos/novo?cliente=${cli}`);
  ok(await v.p.locator(`[data-modelo-escolha] option[value="${mCamp}"]`).count() === 1, 'vendedora vê a campanha da gestão');
  await v.p.selectOption('[data-modelo-escolha]', mCamp); await v.p.click('[data-modelo-aplicar]');
  await v.p.selectOption('[data-modelo-escolha]', mPessoal); await v.p.click('[data-modelo-aplicar]');
  ok(await v.p.inputValue(`.ped-qtd[name="qtd[${p1}]"]`) === '13' && await v.p.inputValue(`.ped-qtd[name="qtd[${p2}]"]`) === '2' && await v.p.inputValue(`.ped-qtd[name="qtd[${p3}]"]`) === '5', 'dois modelos somam as quantidades');
  await v.p.fill(`.ped-qtd[name="qtd[${p1}]"]`, String(grande)); // ajusta depois de aplicar
  await v.p.fill('#obs', 'Condição especial desta semana');

  // ---------- Gerar orçamento ----------
  await Promise.all([v.p.waitForNavigation(), v.p.click('[data-gerar-orcamento]')]);
  const orc = sql(`select max(id) from orcamentos where cliente_id=${cli}`);
  ok(v.p.url().endsWith(`/orcamentos/${orc}`) && sql(`select count(*) from orcamento_itens where orcamento_id=${orc}`) === '3', 'orçamento gerado com os 3 produtos');
  ok(sql(`select situacao from orcamentos where id=${orc}`) === 'aberto' && sql(`select datediff(validade, curdate()) from orcamentos where id=${orc}`) === '15', 'aberto, vale 15 dias');
  const token = sql(`select token from orcamentos where id=${orc}`);
  ok(/^[0-9a-f]{64}$/.test(token) && (await v.p.inputValue('[data-link]')).endsWith('/o/' + token), 'link secreto (só o token, sem dado do cliente)');
  const wa = await v.p.getAttribute('[data-whatsapp]', 'href');
  ok(wa.startsWith('https://wa.me/55') && decodeURIComponent(wa).includes('/o/' + token), 'botão do WhatsApp leva o link na mensagem');
  const [download] = await Promise.all([v.p.waitForEvent('download'), v.p.click('[data-baixar-imagem]')]);
  const arquivo = await download.path();
  const png = require('fs').readFileSync(arquivo);
  ok(download.suggestedFilename() === `orcamento-${orc}.png` && png.slice(1, 4).toString() === 'PNG' && png.length > 20000, 'imagem do orçamento (PNG) baixada');
  await v.p.waitForTimeout(300);
  ok(sql(`select count(*) from orcamento_historico where orcamento_id=${orc} and texto like 'Baixou a imagem%'`) === '1', 'baixar a imagem fica no histórico');
  ok(sql(`select count(*) from cliente_eventos where cliente_id=${cli} and tipo='orcamento'`) !== '0', 'orçamento na linha do tempo do cliente');

  // ---------- Outro vendedor ----------
  const v2 = await como('televendas2@demo.local');
  ok((await v2.p.request.get(BASE + `/orcamentos/${orc}`)).status() === 404, 'outra vendedora não abre o orçamento');
  await v2.p.goto(BASE + '/orcamentos');
  ok(await v2.p.locator(`tr[data-orcamento="${orc}"]`).count() === 0, 'nem vê na lista');
  await v.p.goto(BASE + '/orcamentos');
  ok(await v.p.locator(`tr[data-orcamento="${orc}"]`).count() === 1, 'a vendedora vê na lista "Esperando o cliente"');

  // ---------- Cliente abre o link (sem senha, no celular) ----------
  const c = await pagina({ viewport: { width: 390, height: 844 }, isMobile: true });
  await c.goto(BASE + '/o/' + token);
  ok(await c.locator('[data-orcamento-cliente]').count() === 1 && await c.locator('.cat-nav').count() === 0, 'link abre sem login, sem o menu do catálogo');
  ok(await c.locator('meta[name=robots][content*=noindex]').count() === 1, 'fora dos buscadores (noindex)');
  ok(sql(`select situacao from orcamentos where id=${orc}`) === 'visto', 'abrir marca "Cliente abriu"');
  ok(sql(`select count(*) from notificacoes where tipo='orcamento_visto' and usuario_id=${vend}`) === '1', 'vendedora avisada no sino');
  const largura = await c.evaluate(() => document.documentElement.scrollWidth);
  ok(largura <= 392, 'cabe no celular sem rolar para o lado (' + largura + 'px)');
  // muda uma quantidade (−), acrescenta um produto da lista "mais produtos"
  await c.click(`.ped-qtd[name="qtd[${p2}]"] >> xpath=../button[@data-orc-menos]`);
  ok(await c.inputValue(`.ped-qtd[name="qtd[${p2}]"]`) === '1', 'botão − diminui');
  await c.click('.orc-mais summary');
  await c.fill('[data-filtro-produtos]', sql(`select lower(nome) from produtos where id=${p4}`).slice(0, 12));
  await c.click(`.ped-qtd[name="qtd[${p4}]"] >> xpath=../button[@data-orc-mais]`);
  ok(await c.inputValue(`.ped-qtd[name="qtd[${p4}]"]`) === '1', 'acrescenta produto que não estava no orçamento');
  await c.check('input[name=pagamento][value="1:1"]');
  await c.fill('#msg', 'Pode mandar junto um mostruário?');
  await Promise.all([c.waitForNavigation(), c.click('[data-enviar]')]);
  const ped = sql(`select pedido_id from orcamentos where id=${orc}`);
  ok(await c.locator('[data-fim=aprovado]').count() === 1 && (await c.textContent('.orc-fim')).includes('#' + ped), 'cliente vê "Orçamento aprovado" com o nº do pedido');
  ok(sql(`select concat(origem,'/',situacao,'/',vendedor_id) from pedidos where id=${ped}`) === `orcamento/aguardando_aprovacao/${vend}`, 'virou pedido "aguardando aprovação" da vendedora');
  ok(sql(`select count(*) from pedido_itens where pedido_id=${ped}`) === '4' && sql(`select quantidade from pedido_itens where pedido_id=${ped} and produto_id=${p2}`) === '1', 'pedido sai com o que o cliente confirmou');
  ok(sql(`select concat(situacao,'/',cliente_alterou) from orcamentos where id=${orc}`) === 'aprovado/1' && sql(`select quantidade_cliente from orcamento_itens where orcamento_id=${orc} and produto_id=${p2}`) === '1', 'orçamento aprovado, marcado "cliente alterou"');
  ok(sql(`select observacoes from pedidos where id=${ped}`).includes('mostruário'), 'a mensagem do cliente vai na observação do pedido');
  ok(sql(`select count(*) from notificacoes where tipo='orcamento_aprovado' and usuario_id=${vend} and link='/pedidos/${ped}'`) === '1', 'vendedora avisada: aprovado');
  ok(sql(`select count(*) from notificacoes where tipo='pedido_aprovar' and pedido_id=${ped}`) !== '0', 'Financeiro avisado para aprovar');
  // aprovar de novo (duas abas / clique duplo): não duplica
  const csrfC = await c.evaluate(() => document.querySelector('meta[name=csrf]').content);
  await c.request.post(BASE + `/o/${token}/aprovar`, { form: { _csrf: csrfC, pagamento: '1:1', [`qtd[${p1}]`]: String(grande) } });
  ok(sql(`select count(*) from pedidos where uuid=(select uuid from orcamentos where id=${orc})`) === '1' && sql(`select pedido_id from orcamentos where id=${orc}`) === ped, 'aprovar de novo não duplica o pedido');
  ok(sql(`select count(*) from cliente_eventos where cliente_id=${cli} and tipo='orcamento' and descricao like '%aprovado pelo cliente%'`) === '1', 'aprovação na linha do tempo');
  await v.p.goto(BASE + `/orcamentos/${orc}`);
  ok(await v.p.locator('.orc-mudou').count() >= 1 && (await v.p.textContent('[data-historico]')).includes('aprovou com alterações'), 'a vendedora vê o que o cliente mudou e o histórico');

  // ---------- Cliente com título em atraso: boleto bloqueado; recusar ----------
  await post(v, '/orcamentos', { cliente_id: cliAtraso, [`qtd[${p1}]`]: String(grande + 20) });
  const orc2 = sql(`select max(id) from orcamentos where cliente_id=${cliAtraso}`);
  const tok2 = sql(`select token from orcamentos where id=${orc2}`);
  const c2 = await pagina();
  await c2.goto(BASE + '/o/' + tok2);
  ok(await c2.locator('fieldset.cat-forma.bloqueada').count() === 1, 'cliente com título em atraso: boleto bloqueado no link');
  const csrf2 = await c2.evaluate(() => document.querySelector('meta[name=csrf]').content);
  let r = await c2.request.post(BASE + `/o/${tok2}/aprovar`, { form: { _csrf: csrf2, pagamento: '3:3', [`qtd[${p1}]`]: String(grande + 20) } });
  ok(avisos(await r.text()).includes('Indisponível') && sql(`select pedido_id is null from orcamentos where id=${orc2}`) === '1', 'aprovar com boleto bloqueado não passa');
  r = await c2.request.post(BASE + `/o/${tok2}/recusar`, { form: { _csrf: csrf2, mensagem: '' } });
  ok(avisos(await r.text()).includes('motivo') && sql(`select situacao from orcamentos where id=${orc2}`) === 'visto', 'recusar exige o motivo');
  await c2.request.post(BASE + `/o/${tok2}/recusar`, { form: { _csrf: csrf2, mensagem: 'Achei caro, vou esperar a promoção' } });
  ok(sql(`select situacao from orcamentos where id=${orc2}`) === 'recusado' && sql(`select count(*) from notificacoes where tipo='orcamento_recusado' and usuario_id=${vend}`) === '1', 'recusado com motivo (vendedora avisada)');
  await c2.goto(BASE + '/o/' + tok2);
  ok(await c2.locator('[data-fim=recusado]').count() === 1, 'link mostra "recebemos a sua resposta"');

  // ---------- Vencido, cancelado, link errado ----------
  await post(v, '/orcamentos', { cliente_id: cli, [`qtd[${p1}]`]: '1' });
  const orc3 = sql(`select max(id) from orcamentos where cliente_id=${cli}`);
  sql(`update orcamentos set validade=curdate() - interval 1 day where id=${orc3}`);
  await c2.goto(BASE + '/o/' + sql(`select token from orcamentos where id=${orc3}`));
  ok(await c2.locator('[data-fim=expirado]').count() === 1, 'link vencido não abre');
  await post(v, '/orcamentos', { cliente_id: cli, [`qtd[${p1}]`]: '1' });
  const orc4 = sql(`select max(id) from orcamentos where cliente_id=${cli}`);
  await post(v, `/orcamentos/${orc4}/cancelar`);
  await c2.goto(BASE + '/o/' + sql(`select token from orcamentos where id=${orc4}`));
  ok(sql(`select situacao from orcamentos where id=${orc4}`) === 'cancelado' && await c2.locator('[data-fim=cancelado]').count() === 1, 'orçamento cancelado: link não abre');
  ok((await c2.request.get(BASE + '/o/' + 'a'.repeat(64))).status() === 404, 'link inventado: não encontrado');

  // ---------- A vendedora transforma em pedido (cliente confirmou por telefone) ----------
  await post(v, '/orcamentos', { cliente_id: cli, [`qtd[${p1}]`]: String(grande) });
  const orc5 = sql(`select max(id) from orcamentos where cliente_id=${cli}`);
  await v.p.goto(BASE + `/pedidos/novo?cliente=${cli}&orcamento=${orc5}`);
  ok(await v.p.inputValue(`.ped-qtd[name="qtd[${p1}]"]`) === String(grande) && await v.p.locator('input[name=orcamento_id]').count() === 1, 'editar o orçamento abre com os itens dele');
  await v.p.check('input[name=pagamento][value="1:1"]');
  await Promise.all([v.p.waitForNavigation(), v.p.click('[data-enviar]')]);
  const ped5 = sql(`select pedido_id from orcamentos where id=${orc5}`);
  ok(ped5 !== 'NULL' && sql(`select situacao from orcamentos where id=${orc5}`) === 'aprovado' && v.p.url().endsWith('/pedidos/' + ped5), '"Enviar pedido" transforma o orçamento em pedido');

  // ---------- Modelos: tirar ----------
  await post(v, `/pedidos/modelos/${mCamp}/remover`);
  ok(sql(`select ativo from pedido_modelos where id=${mCamp}`) === '1', 'vendedora não tira a campanha da gestão');
  await post(v, `/pedidos/modelos/${mPessoal}/remover`);
  ok(sql(`select ativo from pedido_modelos where id=${mPessoal}`) === '0', 'vendedora tira o próprio modelo');

  ok(erros.length === 0, 'nenhum erro de JavaScript' + (erros.length ? ': ' + erros.join(' / ') : ''));
  await b.close();
  console.log(falhas ? `\n${falhas} FALHA(S)` : '\nTudo certo.');
  process.exit(falhas ? 1 : 0);
})();
