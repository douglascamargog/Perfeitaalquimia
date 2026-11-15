// Teste automático (Playwright). Rodar todos: bash tests/rodar.sh  ·  só este: node tests/teste_compras_itens.js
// Compras da empresa e página do item: lançar compra com NF (estoque, fornecedor, documentos), item de uso e consumo,
// vários fornecedores, documentos com validade, notas do Omie (lança sozinho, liga item novo, não duplica, avisos),
// acessos (Financeiro e Qualidade lançam; vendedora não; Diretoria só vê) e orçamento da Qualidade recebido virando lançamento.
const { chromium } = require('playwright');
const { execSync } = require('child_process');
const BASE = 'http://127.0.0.1:8000';
let falhas = 0; const ok = (c, m) => { console.log((c ? 'OK   ' : 'FALHA') + ' ' + m); if (!c) falhas++; };
const sql = (q) => execSync(`mysql perfeita --default-character-set=utf8mb4 -N -e "${q.replace(/"/g, '\\"')}"`).toString().trim();
const PDF = Buffer.from('%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n');

(async () => {
  const b = await chromium.launch();
  const erros = [];
  const como = async (email, opcoes = {}) => {
    const p = await (await b.newContext({ viewport: { width: 1440, height: 900 }, ...opcoes })).newPage();
    p.on('pageerror', (e) => erros.push(e.message + ' @ ' + p.url())); await p.route(/open-meteo|jsdelivr/, (r) => r.fulfill({ body: '{}' }));
    await p.goto(BASE + '/entrar'); await p.fill('#email', email); await p.fill('#senha', 'Demo@2026'); await p.click('button[type=submit]'); await p.waitForLoadState();
    if (await p.locator('[data-novidades-ok]').count()) { await p.click('[data-novidades-ok]'); await p.waitForLoadState(); }
    return { p, csrf: await p.evaluate(() => document.querySelector('meta[name=csrf]').content) };
  };
  const post = async (s, caminho, form = {}, multipart = null) => {
    const r = multipart ? await s.p.request.post(BASE + caminho, { multipart: { _csrf: s.csrf, ...form, ...multipart } })
      : await s.p.request.post(BASE + caminho, { form: { _csrf: s.csrf, ...form } });
    return { status: r.status(), html: await r.text() };
  };
  const uid = (email) => sql(`select id from usuarios where email='${email}'`);
  const estoque = (id) => Number(sql(`select estoque from materias_primas where id=${id}`));
  const papel = sql("select id from materias_primas where nome like 'Papel A4%'");
  const deterg = sql("select id from materias_primas where nome like 'Detergente%'");

  // ---------- Financeiro lança uma compra com 2 itens e a NF ----------
  const f = await como('financeiro@demo.local');
  await f.p.goto(BASE + '/financeiro/compras');
  ok(await f.p.locator('[data-lancamentos] tr[data-lancamento]').count() >= 1, 'Financeiro › Compras da empresa lista os lançamentos');
  await f.p.goto(BASE + '/financeiro/compras?aba=lancar');
  const [ePapel, eDet] = [estoque(papel), estoque(deterg)];
  await f.p.fill('[data-lancar-compra] input[name=fornecedor]', 'Papelaria Nova');
  await f.p.fill('[data-lancar-compra] input[name=numero_nf]', '998877');
  await f.p.setInputFiles('[data-lancar-compra] input[name=arquivo_nf]', { name: 'nf-998877.pdf', mimeType: 'application/pdf', buffer: PDF });
  const linhas = f.p.locator('[data-linha]');
  await linhas.nth(0).locator('select').selectOption(papel); await linhas.nth(0).locator('[name="quantidade[]"]').fill('5'); await linhas.nth(0).locator('[name="valor[]"]').fill('25,50');
  await linhas.nth(1).locator('select').selectOption(deterg); await linhas.nth(1).locator('[name="quantidade[]"]').fill('2'); await linhas.nth(1).locator('[name="valor[]"]').fill('30');
  ok((await f.p.textContent('[data-total-nota]')).includes('187,50'), 'total da nota calculado na tela (R$ 187,50)');
  await Promise.all([f.p.waitForNavigation(), f.p.click('[data-lancar-compra] button[type=submit]')]);
  const lanc = sql("select id from compras_lancadas where numero_nf='998877'");
  ok(!!lanc && Math.abs(estoque(papel) - (ePapel + 5)) < 0.001 && Math.abs(estoque(deterg) - (eDet + 2)) < 0.001, 'os 2 itens entraram no estoque');
  ok(sql(`select concat(ultimo_preco,'/',ultima_compra_em=curdate()) from mp_fornecedores where materia_prima_id=${papel} and fornecedor='Papelaria Nova'`) === '25.5000/1', 'fornecedor novo entrou na lista do item com o preço da compra');
  ok(sql(`select count(*) from mp_documentos where tipo='nf' and lancamento_id=${lanc}`) === '2', 'a NF foi para os documentos dos 2 itens');
  const nf = await f.p.request.get(BASE + `/financeiro/compras/${lanc}/nf`);
  ok(nf.status() === 200 && nf.headers()['content-type'] === 'application/pdf', 'NF abre pelo lançamento');

  // ---------- Página do item (uso e consumo) ----------
  await f.p.goto(BASE + `/producao/materias/${papel}`);
  ok(await f.p.locator('.it-numeros .it-num').count() === 5, 'página do item com os cartões de números (sem visual "cru")');
  ok((await f.p.textContent('[data-recorrencia]')).includes('dias'), 'recorrência: compra a cada X dias');
  ok(await f.p.locator('[data-dura], [data-previsao]').count() === 1, '"Dura em média" (uso e consumo) ou "Acaba em"');
  await f.p.goto(BASE + `/producao/materias/${papel}?aba=compras`);
  ok(await f.p.locator('[data-compras-item] tbody tr').count() >= 10, 'aba Compras com o histórico de NFs do item');
  await f.p.goto(BASE + `/producao/materias/${papel}?aba=fornecedores`);
  ok(await f.p.locator('[data-fornecedores] tr[data-fornecedor]').count() === 2 && await f.p.locator('[data-menor-preco]').count() === 1, 'dois fornecedores, menor preço destacado');
  const novo = sql(`select id from mp_fornecedores where materia_prima_id=${papel} and fornecedor='Papelaria Nova'`);
  await post(f, `/producao/materias/${papel}/fornecedores/${novo}/principal`);
  ok(sql(`select principal from mp_fornecedores where id=${novo}`) === '1' && sql(`select fornecedor from materias_primas where id=${papel}`) === 'Papelaria Nova', 'trocar o fornecedor principal');
  ok(sql(`select count(*) from mp_fornecedores where materia_prima_id=${papel} and principal=1`) === '1', 'só um principal');
  await post(f, `/producao/materias/${papel}/fornecedores`, { fornecedor: 'Atacado Escolar', ultimo_preco: '24,00', prazo_dias: '3' });
  ok(sql(`select count(*) from mp_fornecedores where materia_prima_id=${papel} and ativo=1`) === '3', 'adicionar fornecedor pela página do item');

  // ---------- Documentos ----------
  const mp = sql("select id from materias_primas where tipo='materia_prima' and ativo=1 order by id limit 1");
  const vence = sql('select curdate() + interval 10 day');
  await post(f, `/producao/materias/${mp}/documentos`, { tipo: 'laudo', titulo: 'Laudo lote 77', lote: '77', validade: vence }, { arquivo: { name: 'laudo.pdf', mimeType: 'application/pdf', buffer: PDF } });
  const doc = sql(`select id from mp_documentos where materia_prima_id=${mp} and titulo='Laudo lote 77'`);
  ok(!!doc, 'laudo enviado para a matéria-prima');
  await f.p.goto(BASE + `/producao/materias/${mp}?aba=documentos`);
  ok((await f.p.locator(`[data-documento="${doc}"]`).innerText()).includes('vencendo'), 'documento com validade próxima aparece como "vencendo"');
  const r1 = await post(f, `/producao/materias/${mp}/documentos`, { tipo: 'laudo' }, { arquivo: { name: 'x.exe', mimeType: 'application/octet-stream', buffer: Buffer.from('MZ nada') } });
  ok(sql(`select count(*) from mp_documentos where materia_prima_id=${mp}`) === '1', 'arquivo que não é PDF/imagem/XML é recusado');
  ok((await f.p.request.get(BASE + `/producao/materias/documento/${doc}`)).status() === 200, 'Financeiro abre o documento');

  // ---------- Item de uso e consumo não aparece na Produção ----------
  const prod = await como('producao@demo.local');
  await prod.p.goto(BASE + '/producao/materias');
  ok(!(await prod.p.content()).includes('Papel A4'), 'itens de uso e consumo não aparecem nas Matérias-primas da Produção');
  await prod.p.goto(BASE + '/financeiro/compras?aba=itens');
  ok((await prod.p.content()).includes('Sem acesso') || (await prod.p.request.get(BASE + '/financeiro/compras')).status() === 403, 'Produção não entra em Compras da empresa');

  // ---------- Notas do Omie ----------
  const q = await como('qualidade@demo.local');
  const omie1 = sql("select id from materias_primas where omie_codigo='OMIE-MP-100'");
  const e1 = estoque(omie1);
  await post(q, '/financeiro/compras/omie');
  ok(sql("select count(*) from compras_lancadas where origem='omie'") === '3', 'Qualidade busca as notas do Omie: 3 lançadas');
  ok(Math.abs(estoque(omie1) - (e1 + 25)) < 0.001, 'item já ligado entrou sozinho no estoque');
  ok(sql("select count(*) from notificacoes where tipo='compra_entrada' and usuario_id=" + uid('financeiro@demo.local')) !== '0', 'quem compra (Financeiro) é avisado');
  ok(sql("select count(*) from notificacoes where tipo='compra_entrada' and usuario_id=" + uid('producao@demo.local')) !== '0', 'a Produção é avisada (matéria-prima)');
  ok(sql("select count(*) from notificacoes where tipo='diretoria_compras' and usuario_id=" + uid('diretoria@demo.local')) !== '0', 'a Diretoria é avisada');
  await post(q, '/financeiro/compras/omie');
  ok(sql("select count(*) from compras_lancadas where origem='omie'") === '3', 'buscar de novo não duplica as notas');
  const linha = sql("select id from compras_lancadas_itens where omie_item='OMIE-ESC-55'");
  ok(sql(`select lancado from compras_lancadas_itens where id=${linha}`) === '0', 'item novo do Omie fica para ligar');
  await post(q, '/financeiro/compras/itens', { nome: 'Caneta azul (caixa)', unidade: 'un', tipo: 'consumo', categoria: 'Escritório', estoque_minimo: '1', ativo: '1' });
  const caneta = sql("select id from materias_primas where nome='Caneta azul (caixa)'");
  await post(q, `/financeiro/compras/ligar/${linha}`, { item: caneta });
  ok(estoque(caneta) === 5 && sql(`select omie_codigo from materias_primas where id=${caneta}`) === 'OMIE-ESC-55', 'ligar: entra no estoque e as próximas notas desse item entram sozinhas');
  ok(sql("select count(*) from compras_lancadas where situacao='pendente'") === '0', 'nota sai de "para ligar"');

  // ---------- Acessos ----------
  const v = await como('televendas1@demo.local');
  ok((await post(v, '/financeiro/compras', { fornecedor: 'X', 'item[]': papel, 'quantidade[]': '1' })).status === 403, 'vendedora não lança compra');
  ok((await v.p.request.get(BASE + `/producao/materias/documento/${doc}`)).status() !== 200, 'vendedora não abre documento do item');
  const d = await como('diretoria@demo.local');
  ok((await d.p.request.get(BASE + '/financeiro/compras')).status() === 200, 'Diretoria vê Compras da empresa');
  ok((await post(d, '/financeiro/compras', { fornecedor: 'X', 'item[]': papel, 'quantidade[]': '1' })).status === 403, '...mas não lança');

  // ---------- Orçamento da Qualidade recebido vira lançamento ----------
  const compra = sql("select id from compras where situacao='comprado' and materia_prima_id is not null order by id limit 1");
  if (compra) {
    const dep = await como('deposito@demo.local');
    await post(dep, `/deposito/recebimento/${compra}`, { quantidade: sql(`select replace(quantidade,'.',',') from compras where id=${compra}`), nf: '4455', lote: 'LT-4455', validade: '2028-12-31' },
      { arquivo_nf: { name: 'nf4455.pdf', mimeType: 'application/pdf', buffer: PDF } });
    ok(sql(`select count(*) from compras_lancadas where compra_id=${compra} and origem='orcamento' and numero_nf='4455' and arquivo is not null`) === '1', 'orçamento recebido no Depósito vira lançamento com a NF');
  } else ok(true, 'sem orçamento "comprado" com matéria-prima na demonstração (pulado)');

  // ---------- Fornecedores (V11.0) ----------
  await f.p.goto(BASE + '/financeiro/fornecedores');
  ok(await f.p.locator('[data-fornecedor="Papelaria Nova"]').count() === 1, 'lista de fornecedores (inclui o que veio da NF lançada)');
  await f.p.goto(BASE + '/financeiro/fornecedores/ver?nome=' + encodeURIComponent('papelaria nova'));
  ok((await f.p.textContent('h1')).includes('Papelaria Nova'), 'página do fornecedor (o nome não depende de maiúsculas)');
  ok(await f.p.locator('[data-itens-fornecedor] tbody tr[data-item="' + papel + '"]').count() === 1, 'mostra os itens que ele fornece');
  ok(await f.p.locator('[data-compras-fornecedor] tbody tr', { hasText: '998877' }).count() === 1, 'mostra as NFs dele');
  await f.p.goto(BASE + '/financeiro/fornecedores/ver?nome=' + encodeURIComponent('Atacado Escolar'));
  ok(await f.p.locator(`[data-itens-fornecedor] tr[data-item="${papel}"] [data-mais-barato]`).count() === 1, 'compara o preço com os outros fornecedores do item (menor preço)');
  ok((await prod.p.request.get(BASE + '/financeiro/fornecedores')).status() === 403, 'Produção não entra em Fornecedores');

  // ---------- Documentos vencendo no painel da Produção ----------
  await prod.p.goto(BASE + '/producao');
  ok(await prod.p.locator(`[data-card="docs-vencendo"] [data-doc-vencendo="${doc}"]`).count() === 1, 'laudo vencendo aparece no painel da Produção');

  // ---------- Celular ----------
  const cel = await como('financeiro@demo.local', { viewport: { width: 390, height: 844 }, isMobile: true });
  for (const u of [`/producao/materias/${papel}`, '/financeiro/compras?aba=lancar', '/financeiro/fornecedores', '/financeiro/fornecedores/ver?nome=Papelaria%20Nova']) {
    await cel.p.goto(BASE + u);
    ok(await cel.p.evaluate(() => document.documentElement.scrollWidth) <= 392, `${u} cabe no celular`);
  }

  ok(erros.length === 0, 'nenhum erro de JavaScript' + (erros.length ? ': ' + erros.join(' / ') : ''));
  await b.close();
  console.log(falhas ? `\n${falhas} FALHA(S)` : '\nTudo certo.');
  process.exit(falhas ? 1 : 0);
})();
