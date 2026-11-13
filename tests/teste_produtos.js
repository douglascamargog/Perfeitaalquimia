// Teste automático (Playwright). Rodar todos: bash tests/rodar.sh  ·  só este: node tests/teste_produtos.js
// Produtos completo: cartões e filtros, abas do produto, parte técnica só da Responsável Técnica,
// documentos fora da pasta pública (o "interno" some para o vendedor), Catálogo separado.
const fs = require('fs');
const path = require('path');
const { chromium } = require('playwright');
const { execSync } = require('child_process');
const BASE = 'http://127.0.0.1:8000';
const DIR = path.join(process.argv[2] || path.join(__dirname, 'saida'), 'produtos');
let falhas = 0; const ok = (c, m) => { console.log((c ? 'OK   ' : 'FALHA') + ' ' + m); if (!c) falhas++; };
const sql = (q) => execSync(`mysql perfeita --default-character-set=utf8mb4 -N -e "${q.replace(/"/g, '\\"')}"`).toString().trim();
async function entrar(p, email) {
  await p.goto(BASE + '/entrar'); await p.fill('#email', email); await p.fill('#senha', 'Demo@2026'); await p.click('button[type=submit]'); await p.waitForLoadState();
  if (await p.locator('[data-novidades-ok]').count()) { await p.click('[data-novidades-ok]'); await p.waitForLoadState(); }
}
const aviso = async (p) => (await p.locator('.aviso, .alerta').allInnerTexts()).join(' | ');

(async () => {
  fs.mkdirSync(DIR, { recursive: true });
  const b = await chromium.launch();
  const erros = [];
  const nova = async () => { const p = await (await b.newContext({ viewport: { width: 1300, height: 900 } })).newPage(); p.on('pageerror', e => erros.push(e.message)); await p.route(/open-meteo|jsdelivr/, r => r.fulfill({ body: '{}' })); return p; };

  // ---------- 1. Lista com selos e filtros ----------
  const tec = await nova();
  await entrar(tec, 'tecnica@demo.local');
  await tec.goto(BASE + '/produtos');
  const total = Number(sql('select count(*) from produtos where ativo=1 and cliente_exclusivo_id is null'));
  ok(await tec.locator('.pd-cartao').count() === total, `lista mostra os ${total} produtos (sem os exclusivos de terceirização)`);
  const semFicha = Number(sql('select count(*) from produtos p where ativo=1 and cliente_exclusivo_id is null and not exists (select 1 from ficha_tecnica f where f.produto_id=p.id)'));
  await tec.goto(BASE + '/produtos?situacao=sem_ficha');
  ok(await tec.locator('.pd-cartao').count() === semFicha, `filtro "sem ficha técnica" (${semFicha})`);
  await tec.goto(BASE + '/produtos?situacao=vencendo');
  ok(await tec.locator('.pd-cartao').count() === 1, 'filtro "documento vencendo" acha o produto da demonstração');
  const slug = sql("select p.slug from produtos p join produto_documentos d on d.produto_id=p.id where d.interno=1 limit 1");
  const pid = sql(`select id from produtos where slug='${slug}'`);

  // ---------- 2. Página do produto: todas as abas ----------
  for (const aba of ['geral', 'conteudo', 'composicao', 'ficha', 'embalagens', 'documentos', 'historico']) {
    const r = await tec.goto(BASE + `/produtos/${slug}?aba=${aba}`);
    ok(r.status() === 200 && await tec.locator('.abas a.ativa').count() === 1, `aba ${aba} abre`);
  }

  // ---------- 3. Técnica edita composição, ficha (= Produção) e especificações ----------
  await tec.goto(BASE + `/produtos/${slug}?aba=composicao`);
  await tec.fill('textarea[name=ingredientes]', 'Ingrediente teste A, ingrediente <b>B</b>.');
  await tec.click('[data-nutri-mais]');
  const ult = tec.locator('[data-nutri-linhas] tr').last();
  await ult.locator('input[name="nutriente[]"]').fill('Vitamina Teste'); await ult.locator('input[name="quantidade[]"]').fill('10 mg'); await ult.locator('input[name="vd[]"]').fill('50%');
  await tec.fill('textarea[name=alergenicos]', 'Contém soja (teste).');
  await Promise.all([tec.waitForNavigation(), tec.click('[data-composicao] button[type=submit]')]);
  ok(sql(`select json_unquote(json_extract(ficha_json,'$.ingredients')) from produtos where id=${pid}`) === 'Ingrediente teste A, ingrediente <b>B</b>.', 'composição salva no mesmo cadastro da Academia');
  ok(sql(`select json_search(ficha_json,'one','Vitamina Teste') is not null from produtos where id=${pid}`) === '1', 'linha nova da tabela nutricional');
  ok(sql(`select alergenicos from produto_tecnico where produto_id=${pid}`) === 'Contém soja (teste).', 'alergênicos nas especificações');
  const acad = await tec.goto(BASE + `/academia/produtos/${slug}?aba=composicao`);
  ok(acad.status() === 200 && (await tec.content()).includes('ingrediente &lt;b&gt;B&lt;/b&gt;'), 'Academia mostra a composição nova (HTML escapado)');

  // ficha: acrescenta uma matéria-prima
  const antes = Number(sql(`select count(*) from ficha_tecnica f join materias_primas m on m.id=f.materia_prima_id where f.produto_id=${pid} and m.tipo='materia_prima'`));
  const emb = sql(`select count(*) from ficha_tecnica f join materias_primas m on m.id=f.materia_prima_id where f.produto_id=${pid} and m.tipo='embalagem'`);
  const mpNova = sql(`select id from materias_primas where tipo='materia_prima' and ativo=1 and id not in (select materia_prima_id from ficha_tecnica where produto_id=${pid}) order by id limit 1`);
  await tec.goto(BASE + `/produtos/${slug}?aba=ficha`);
  await tec.click('[data-acrescentar]');
  const tr = tec.locator('[data-linhas] [data-linha]').last();
  await tr.locator('select').selectOption(mpNova); await tr.locator('[data-qtd]').fill('0,005');
  await Promise.all([tec.waitForNavigation(), tec.click('[data-ficha] button[type=submit]')]);
  ok(Number(sql(`select count(*) from ficha_tecnica f join materias_primas m on m.id=f.materia_prima_id where f.produto_id=${pid} and m.tipo='materia_prima'`)) === antes + 1, 'ficha técnica ganhou a matéria-prima');
  ok(sql(`select count(*) from ficha_tecnica f join materias_primas m on m.id=f.materia_prima_id where f.produto_id=${pid} and m.tipo='embalagem'`) === emb, 'as embalagens da ficha continuam (cada aba salva só a sua parte)');
  await tec.goto(BASE + `/producao/fichas/${pid}`);
  ok(await tec.locator('[data-linhas] [data-linha]').count() === antes + 1 + Number(emb), 'Produção › Fichas técnicas mostra a mesma ficha');

  // especificações
  await tec.goto(BASE + `/produtos/${slug}?aba=embalagens`);
  await tec.fill('input[name=ean]', '123');
  await Promise.all([tec.waitForNavigation(), tec.click('button:has-text("Salvar especificações")')]);
  ok((await aviso(tec)).includes('EAN') && await tec.inputValue('input[name=ean]') === '123', 'EAN inválido é recusado (e o que foi digitado continua)');
  await tec.fill('input[name=ean]', '7891234567895'); await tec.fill('input[name=ncm]', '21069030'); await tec.fill('input[name=peso_bruto]', '95 g');
  await Promise.all([tec.waitForNavigation(), tec.click('button:has-text("Salvar especificações")')]);
  ok(sql(`select concat(ean,'|',ncm,'|',peso_bruto) from produto_tecnico where produto_id=${pid}`) === '7891234567895|2106.90.30|95 g', 'especificações salvas (NCM formatado)');

  // ---------- 4. Documentos ----------
  const pdf = path.join(DIR, 'laudo.pdf');
  fs.writeFileSync(pdf, '%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n');
  const falso = path.join(DIR, 'virus.pdf');
  fs.writeFileSync(falso, '<?php echo "oi"; ?>');
  await tec.goto(BASE + `/produtos/${slug}?aba=documentos`);
  const qtdDocs = await tec.locator('.pd-doc').count();
  await tec.setInputFiles('input[name=arquivo]', falso); await tec.fill('input[name=titulo]', 'Arquivo falso');
  await Promise.all([tec.waitForNavigation(), tec.click('button:has-text("Enviar documento")')]);
  ok((await aviso(tec)).includes('PDF, JPG ou PNG') && sql("select count(*) from produto_documentos where titulo='Arquivo falso'") === '0', 'arquivo que não é PDF/imagem é recusado');
  await tec.selectOption('select[name=tipo]', 'laudo'); await tec.fill('input[name=titulo]', 'Laudo de teste');
  await tec.fill('input[name=validade]', '2030-05-20'); await tec.check('input[name=interno]');
  await tec.setInputFiles('input[name=arquivo]', pdf);
  await Promise.all([tec.waitForNavigation(), tec.click('button:has-text("Enviar documento")')]);
  const doc = sql("select concat(id,'|',arquivo,'|',interno,'|',validade) from produto_documentos where titulo='Laudo de teste'").split('|');
  ok(doc[2] === '1' && doc[3] === '2030-05-20' && await tec.locator('.pd-doc').count() === qtdDocs + 1, 'documento enviado (interno, com validade)');
  ok(fs.existsSync(path.join(__dirname, '..', 'storage', 'documentos', 'produtos', doc[1])) && !fs.existsSync(path.join(__dirname, '..', 'public', doc[1])), 'arquivo guardado fora da pasta pública, com nome aleatório');
  const baixa = await tec.request.get(BASE + `/produtos/documentos/${doc[0]}`);
  ok(baixa.status() === 200 && baixa.headers()['content-type'] === 'application/pdf' && (await baixa.text()).startsWith('%PDF'), 'documento abre pelo sistema');
  const semLogin = await (await b.newContext()).request.get(BASE + `/produtos/documentos/${doc[0]}`, { maxRedirects: 0 });
  ok(semLogin.status() === 302, 'sem login não baixa');
  ok((await (await b.newContext()).request.get(BASE + '/../storage/documentos/produtos/' + doc[1])).status() !== 200, 'arquivo não tem endereço público');

  // ---------- 5. Vendedor: só lê; "interno" não aparece ----------
  const ven = await nova();
  await entrar(ven, 'televendas1@demo.local');
  await ven.goto(BASE + `/produtos/${slug}?aba=documentos`);
  ok(await ven.locator('.pd-doc').count() === Number(sql(`select count(*) from produto_documentos where produto_id=${pid} and interno=0 and arquivado_em is null`)), 'vendedor vê os documentos, menos os internos');
  ok(await ven.locator('input[name=arquivo]').count() === 0 && await ven.locator('button:has-text("Remover")').count() === 0, 'vendedor não envia nem remove');
  ok((await ven.request.get(BASE + `/produtos/documentos/${doc[0]}`, { maxRedirects: 0 })).status() !== 200, 'vendedor não baixa documento interno nem pelo endereço');
  for (const aba of ['composicao', 'ficha', 'embalagens', 'conteudo']) {
    await ven.goto(BASE + `/produtos/${slug}?aba=${aba}`);
    ok(await ven.locator('.pd-aba form[method=post]').count() === 0, `vendedor só lê a aba ${aba}`);
  }
  await ven.goto(BASE + `/produtos/${slug}?aba=ficha`);
  ok(!(await ven.content()).includes('Custo por unidade'), 'vendedor não vê o custo da ficha');
  const csrf = await ven.evaluate(() => document.querySelector('meta[name=csrf]')?.content || '');
  const r403 = await ven.request.post(BASE + `/produtos/${slug}/composicao`, { form: { _csrf: csrf, ingredientes: 'hack' } });
  ok(r403.status() === 403 && !sql(`select ficha_json from produtos where id=${pid}`).includes('hack'), 'vendedor recebe 403 ao tentar editar a composição');
  ok(await ven.locator('.menu-lateral a[href$="/produtos"]').first().getAttribute('data-bloqueado') === null, 'vendedor tem a aba Produtos no menu (leitura)');

  // ---------- 6. Marketing edita o Conteúdo (Academia e catálogo mudam juntos) ----------
  const mkt = await nova();
  await entrar(mkt, 'marketing@demo.local');
  await mkt.goto(BASE + `/produtos/${slug}?aba=conteudo`);
  await mkt.fill('input[name=chamada]', 'Chamada nova de teste');
  await mkt.fill('textarea[name=faq]', 'Pode tomar à noite? | Sim, conforme orientação.');
  await Promise.all([mkt.waitForNavigation(), mkt.click('button:has-text("Salvar conteúdo")')]);
  ok(sql(`select chamada from produtos where id=${pid}`) === 'Chamada nova de teste', 'Marketing salva o conteúdo');
  await mkt.goto(BASE + `/academia/produtos/${slug}`);
  ok((await mkt.content()).includes('Chamada nova de teste'), 'Academia mostra a chamada nova');
  const csrfMkt = await mkt.evaluate(() => document.querySelector('meta[name=csrf]')?.content || '');
  ok((await mkt.request.post(BASE + `/produtos/${slug}/embalagens`, { form: { _csrf: csrfMkt, ean: '' } })).status() === 403, 'Marketing não edita a parte técnica');

  // ---------- 7. Histórico, remover documento e alerta ----------
  await tec.goto(BASE + `/produtos/${slug}?aba=historico`);
  const hist = await tec.locator('.pd-historico').innerText();
  ok(hist.includes('Composição') && hist.includes('Ficha técnica') && hist.includes('Documento enviado') && hist.includes('Conteúdo'), 'histórico mostra quem mudou o quê');
  await tec.goto(BASE + `/produtos/${slug}?aba=documentos`);
  tec.once('dialog', d => d.accept());
  await Promise.all([tec.waitForNavigation(), tec.locator('.pd-doc', { hasText: 'Laudo de teste' }).locator('button:has-text("Remover")').click()]);
  ok(sql("select arquivado_em is not null from produto_documentos where titulo='Laudo de teste'") === '1', 'remover só arquiva o documento');
  await tec.goto(BASE + '/');
  ok((await tec.content()).includes('documento(s) de produto vencido(s) ou vencendo'), 'One avisa a Técnica do documento vencendo');

  // ---------- 8. Catálogo (tela movida) e Produção só lê a ficha ----------
  await mkt.goto(BASE + '/produtos/catalogo?q=' + encodeURIComponent(sql(`select codigo from produtos where id=${pid}`)));
  ok(await mkt.locator('h1:has-text("Catálogo")').count() >= 1 && await mkt.locator('tbody tr').count() === 1, 'Catálogo separado continua com a lista para a vitrine');
  const pro = await nova();
  await entrar(pro, 'producao@demo.local');
  await pro.goto(BASE + `/producao/fichas/${pid}`);
  ok(await pro.locator('[data-acrescentar]').count() === 0, 'Produção vê a ficha técnica só para leitura');
  await pro.goto(BASE + '/producao/materias/nova');
  ok(await pro.locator('select#tipo option[value=embalagem]').count() === 1, 'cadastro de matéria-prima tem o tipo "Embalagem"');

  // celular: sem nada passando da largura
  const cel = await (await b.newContext({ viewport: { width: 390, height: 844 } })).newPage();
  await cel.route(/open-meteo|jsdelivr/, r => r.fulfill({ body: '{}' }));
  await entrar(cel, 'tecnica@demo.local');
  for (const aba of ['geral', 'documentos', 'embalagens']) {
    await cel.goto(BASE + `/produtos/${slug}?aba=${aba}`);
    ok(await cel.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth + 1), `celular: aba ${aba} cabe na tela`);
  }
  await tec.goto(BASE + `/produtos/${slug}`);
  await tec.screenshot({ path: path.join(DIR, 'produto.png'), fullPage: true });

  ok(erros.length === 0, 'nenhum erro de JavaScript' + (erros.length ? ': ' + erros.join(' / ') : ''));
  await b.close();
  console.log(falhas ? `\n${falhas} FALHA(S)` : '\nTudo certo.');
  process.exit(falhas ? 1 : 0);
})();
