// Teste automático (Playwright). Rodar todos: bash tests/rodar.sh  ·  só este: node tests/teste_formulas.js
// Fórmulas (V15.0): a Responsável Técnica cria a fórmula a partir do produto novo, coloca ingredientes (contas na hora:
// massa, %, cápsula, custo), não aprova com pendência, aprova → vira a ficha técnica do produto (por embalagem, na unidade da
// matéria-prima), avisa a Produção, guarda a ficha anterior; nova versão, comparar; testes; permissões; especialista sabe da fórmula aberta.
const { chromium } = require('playwright');
const { execSync } = require('child_process');
const BASE = 'http://127.0.0.1:8000';
let falhas = 0; const ok = (c, m) => { console.log((c ? 'OK   ' : 'FALHA') + ' ' + m); if (!c) falhas++; };
const sql = (q) => execSync(`mysql perfeita --default-character-set=utf8mb4 -N -e "${q.replace(/"/g, '\\"')}"`).toString().trim();

const entrar = async (b, email) => {
  const p = await (await b.newContext({ viewport: { width: 1360, height: 900 } })).newPage(); p.erros = []; p.on('pageerror', (e) => p.erros.push(e.message)); p.on('dialog', (d) => d.accept());
  await p.route(/open-meteo/, (r) => r.fulfill({ body: '{}' }));
  await p.goto(BASE + '/entrar'); await p.fill('#email', email); await p.fill('#senha', 'Demo@2026'); await p.click('button[type=submit]'); await p.waitForLoadState();
  if (await p.locator('[data-novidades-ok]').count()) { await p.click('[data-novidades-ok]'); await p.waitForLoadState(); }
  return p;
};
const mp = (nome) => sql(`select id from materias_primas where nome like '${nome}%' limit 1`);

(async () => {
  const b = await chromium.launch(); const paginas = [];
  try {
    ok(sql('select count(*) from formulas') === '2', 'demonstração: 2 fórmulas de exemplo');
    const t = await entrar(b, 'tecnica@demo.local'); paginas.push(t);
    await t.goto(BASE + '/formulas');
    ok((await t.locator('[data-formula]').count()) === 2 && (await t.locator('[data-formula="F-0001"]').innerText()).includes('5,37'), 'lista: cartões com o custo por embalagem (F-0001: R$ 5,37)');

    // Produto novo → Criar fórmula
    const proj = sql('select id from dp_projetos order by id limit 1');
    const prod = sql(`select produto_id from dp_projetos where id=${proj}`);
    await t.goto(BASE + '/desenvolvimento/' + proj);
    const botao = t.locator('[data-criar-formula]').first();
    ok(await botao.count() === 1, 'Produtos novos: a etapa da ficha técnica tem o botão "Criar fórmula"');
    await t.goto(BASE + '/formulas/nova?projeto=' + proj);
    await t.fill('input[name=nome]', 'Glucomannan cápsulas teste');
    await t.fill('input[name=porcao_qtd]', '3');
    await t.fill('input[name=porcoes_embalagem]', '20');
    await t.fill('input[name=capacidade_capsula_mg]', '500');
    await Promise.all([t.waitForNavigation(), t.click('[data-form-nova-formula] button[type=submit]')]);
    const fid = sql("select id from formulas where nome='Glucomannan cápsulas teste'");
    ok(fid !== '' && sql(`select concat(codigo,'|',dp_projeto_id,'|',coalesce(produto_id,'-'),'|',situacao) from formulas where id=${fid}`) === `F-0003|${proj}|${prod}|rascunho`, 'fórmula nasce F-0003, ligada ao projeto e ao produto, em rascunho');

    // Ingredientes: 1 linha + 2 novas
    const linhas = () => t.locator('[data-item]');
    const preencher = async (i, mpId, qtd, un, base = 'porcao', funcao = 'ativo', livre = '') => {
      const l = linhas().nth(i);
      if (mpId) await l.locator('[data-item-mp]').selectOption(mpId); else await l.locator('[data-item-livre]').fill(livre);
      await l.locator('[data-item-qtd]').fill(qtd); await l.locator('[data-item-unidade]').selectOption(un);
      await l.locator('[data-item-base]').selectOption(base); await l.locator('[data-item-funcao]').selectOption(funcao);
    };
    await preencher(0, mp('Glucomannan'), '1,2', 'g');
    await t.click('[data-item-mais]'); await preencher(1, mp('Psyllium'), '300', 'mg');
    await t.click('[data-item-mais]'); await preencher(2, null, '15', 'mg', 'porcao', 'excipiente', 'Dióxido de silício');
    await t.click('[data-item-mais]'); await preencher(3, mp('Cápsula vegetal'), '3', 'un', 'porcao', 'capsula');
    await t.click('[data-item-mais]'); await preencher(4, mp('Pote PET'), '1', 'un', 'embalagem', 'embalagem');
    ok((await t.locator('[data-r-massa]').innerText()).startsWith('1.515') && (await t.locator('[data-r-dose]').innerText()).startsWith('505'), 'contas na hora: massa 1.515 mg e 505 mg por cápsula');
    ok((await t.locator('[data-r-ocupacao]').innerText()).includes('não cabe') && await t.locator('[data-r-ocupacao].fm-ruim').count() === 1, 'cápsula de 500 mg: avisa que não cabe');
    await Promise.all([t.waitForNavigation(), t.click('[data-salvar-formula]')]);
    ok(sql(`select count(*) from formula_itens where formula_id=${fid}`) === '5', 'salvou os 5 ingredientes');
    // Custo esperado por embalagem: glucomannan 1,2 g×20 = 0,024 kg; psyllium 0,3 g×20 = 0,006 kg; 60 cápsulas; 1 pote
    const custo = Number(sql(`select round(0.024*(select custo_unitario from materias_primas where id=${mp('Glucomannan')}) + 0.006*(select custo_unitario from materias_primas where id=${mp('Psyllium')})
      + 60*(select custo_unitario from materias_primas where id=${mp('Cápsula vegetal')}) + (select custo_unitario from materias_primas where id=${mp('Pote PET')}), 2)`));
    ok((await t.locator('[data-r-custo-emb]').innerText()).includes(custo.toLocaleString('pt-BR', { minimumFractionDigits: 2 })), `custo por embalagem certo (R$ ${custo.toFixed(2)}), convertendo g/mg para kg`);
    ok((await t.locator('[data-pendencias]').innerText()).includes('Dióxido de silício') && await t.locator('[data-aprovar][disabled]').count() === 1, 'com ingrediente a cadastrar: Aprovar fica bloqueado e diz o que falta');

    // Troca o ingrediente livre por uma matéria-prima cadastrada e aprova
    await linhas().nth(2).locator('[data-item-mp]').selectOption(mp('Carvão'));
    await Promise.all([t.waitForNavigation(), t.click('[data-salvar-formula]')]);
    const fichaAntes = sql(`select count(*) from ficha_tecnica where produto_id=${prod}`);
    await Promise.all([t.waitForNavigation(), t.click('[data-aprovar]')]);
    ok((await t.locator('.aviso').first().innerText()).includes('ficha técnica do produto foi atualizada'), 'aprovar: avisa que a ficha técnica foi atualizada');
    ok(sql(`select round(quantidade,5) from ficha_tecnica where produto_id=${prod} and materia_prima_id=${mp('Glucomannan')}`) === '0.02400'
      && sql(`select round(quantidade,5) from ficha_tecnica where produto_id=${prod} and materia_prima_id=${mp('Cápsula vegetal')}`) === '60.00000'
      && sql(`select round(quantidade,5) from ficha_tecnica where produto_id=${prod} and materia_prima_id=${mp('Pote PET')}`) === '1.00000',
      'a ficha técnica do produto = fórmula por embalagem na unidade da matéria-prima (0,024 kg; 60 cápsulas; 1 pote)');
    ok(sql(`select situacao from formulas where id=${fid}`) === 'aprovada' && sql(`select count(*) from notificacoes where tipo='formula_aprovada'`) !== '0', 'fórmula aprovada e a Produção avisada no sino');
    ok(sql(`select count(*) from formula_historico where formula_id=${fid} and texto like 'Aprovada%'`) === '1' && (fichaAntes === '0' || sql(`select dados_json is not null from formula_historico where formula_id=${fid} and texto like 'Aprovada%'`) === '1'), 'histórico registra a aprovação (e guarda a ficha anterior, se havia)');
    ok(sql(`select count(*) from dp_historico where projeto_id=${proj} and texto like 'Fórmula F-0003%'`) === '1', 'o projeto do produto novo registra a fórmula aprovada');
    ok(await t.locator('[data-salvar-formula]').count() === 0, 'fórmula aprovada não muda (precisa de nova versão)');

    // Nova versão + comparar
    await Promise.all([t.waitForNavigation(), t.click('[data-nova-versao]')]);
    const v2 = sql("select id from formulas where codigo='F-0003' and versao=2");
    ok(v2 !== '' && t.url().endsWith('/formulas/' + v2) && sql(`select count(*) from formula_itens where formula_id=${v2}`) === '5', 'nova versão: v2 em rascunho com os mesmos ingredientes');
    await linhas().nth(0).locator('[data-item-qtd]').fill('1,5');
    await linhas().nth(1).locator('[data-item-tirar]').click();
    await Promise.all([t.waitForNavigation(), t.click('[data-salvar-formula]')]);
    await t.click('[data-comparar]'); await t.waitForLoadState();
    const cmp = await t.locator('[data-comparacao]').innerText();
    ok(cmp.includes('Psyllium') && cmp.includes('Glucomannan') && /1,2 g → 1,5 g/.test(cmp), 'comparar v1 → v2: mostra o que saiu e a quantidade que mudou');

    // Teste / lote piloto
    await t.goto(BASE + '/formulas/' + v2);
    await t.fill('#testes input[name=observacoes]', 'Encapsulou bem; dissolução ok');
    await t.selectOption('#testes select[name=resultado]', 'aprovado');
    await Promise.all([t.waitForNavigation(), t.click('[data-registrar-teste]')]);
    ok(sql(`select concat(resultado,'|',(select situacao from formulas where id=${v2})) from formula_testes where formula_id=${v2}`) === 'aprovado|em_teste', 'registrar teste: fica no histórico e a fórmula passa a "Em teste"');

    // Especialista de Formulação sabe da fórmula aberta (consulta direta, com o login da Técnica)
    const csrf = await t.locator('meta[name=csrf]').getAttribute('content');
    const r = await (await t.request.post(BASE + '/one/perguntar', { form: { pergunta: 'quanto custa?', especialista: 'formulacao', tela: '/formulas/' + v2, _csrf: csrf }, headers: { Accept: 'application/json' } })).json();
    ok(r.especialista === 'formulacao', 'o chat na tela da fórmula usa o especialista de Formulação');

    // Permissões
    const v = await entrar(b, 'televendas1@demo.local'); paginas.push(v);
    const r403 = await v.goto(BASE + '/formulas');
    ok(r403.status() === 403, 'vendedora não vê Fórmulas (403)');
    const p = await entrar(b, 'producao@demo.local'); paginas.push(p);
    await p.goto(BASE + '/formulas/' + fid);
    ok(await p.locator('[data-salvar-formula]').count() === 0 && await p.locator('[data-aprovar]').count() === 0, 'Produção vê a fórmula, mas não edita nem aprova');
    const ap = await p.request.post(BASE + `/formulas/${v2}/aprovar`, { form: { _csrf: await p.locator('meta[name=csrf]').getAttribute('content') } });
    ok(ap.status() === 403, 'Produção não aprova (403)');
    const erros = paginas.flatMap((x) => x.erros);
    ok(erros.length === 0, 'sem erros de JavaScript' + (erros.length ? ': ' + erros[0] : ''));
  } finally {
    await b.close();
  }
  process.exit(falhas ? 1 : 0);
})();
