// V17.7 — Produtos › Ligar produtos do Nota10: o duplicado criado pela importação é ligado ao produto do One
// (código liberado no Catálogo, sugestão pelo código, histórico movido, só completa o que falta, desfazer).
// Rodar com o banco de TESTE e o servidor local no ar.
const { chromium } = require('playwright');
const { execSync } = require('child_process');
const BASE = 'http://127.0.0.1:8000';
let falhas = 0; const ok = (c, m) => { console.log((c ? 'OK   ' : 'FALHA') + ' ' + m); if (!c) falhas++; };
const sql = (q) => execSync(`mysql perfeita --default-character-set=utf8mb4 -N -e "${q.replace(/"/g, '\\"')}"`).toString().trim();

(async () => {
  const lojista = sql("select id from tabelas_preco where slug='lojista'");
  sql(`insert into produtos (slug, nome, ficha_json, ativo, visivel_catalogo) values ('colageno-one', 'Colágeno Verisol 300g', '{}', 1, 1), ('cha-one', 'Chá Verde 100g', '{}', 1, 1)`);
  sql(`insert into produtos (slug, codigo, nota10_codigo, nome, ficha_json, ativo, visivel_catalogo, unidade, custo) values
       ('n10-pa002-abcde', 'PA002', 'PA002', 'COLAGENO VERISOL 300G', '{}', 0, 0, 'UN', 12.5), ('n10-pa009-fghij', 'PA009', 'PA009', 'CHA VERDE 100G', '{}', 0, 0, 'UN', 4)`);
  const one = sql("select id from produtos where slug='colageno-one'"), dup = sql("select id from produtos where slug='n10-pa002-abcde'");
  const cha = sql("select id from produtos where slug='cha-one'"), dupCha = sql("select id from produtos where slug='n10-pa009-fghij'");
  sql(`insert into produto_precos (produto_id, tabela_preco_id, preco) values (${one}, ${lojista}, 89.90), (${dup}, ${lojista}, 49.90)`);
  sql(`insert into produto_tecnico (produto_id, ean, ncm) values (${dup}, '7891234567895', '21069030')`);
  const cli = sql('select min(id) from clientes');
  sql(`insert into pedidos (uuid, cliente_id, tabela_preco_id, forma_pagamento_id, subtotal, total, situacao, origem) values (UUID(), ${cli}, ${lojista}, 1, 100, 100, 'faturado', 'nota10')`);
  const ped = sql('select max(id) from pedidos');
  sql(`insert into pedido_itens (pedido_id, produto_id, nome, quantidade, preco_unitario, total) values (${ped}, ${dup}, 'COLAGENO', 2, 50, 100), (${ped}, ${dupCha}, 'CHA', 1, 10, 10)`);

  const nav = await chromium.launch(); const erros = [];
  const p = await (await nav.newContext()).newPage(); p.on('pageerror', (e) => erros.push(e.message)); p.on('dialog', (d) => d.accept());
  try {
    await p.goto(BASE + '/entrar'); await p.fill('#email', 'admin@demo.local'); await p.fill('#senha', 'Demo@2026'); await p.click('button[type=submit]'); await p.waitForLoadState();

    // 1) Catálogo: duplicados do Nota10 não aparecem; aviso com link; o código do Nota10 pode ser dado ao produto do One
    await p.goto(BASE + '/produtos/catalogo');
    ok((await p.locator('[data-aviso-nota10]').innerText()).includes('2 produto(s)'), 'Catálogo avisa dos produtos que vieram só do Nota10');
    ok((await p.locator('#produto-' + dup).count()) === 0, 'o duplicado do Nota10 não aparece na lista do Catálogo');
    await p.fill(`#produto-${one} input[name=codigo]`, 'PA002');
    await Promise.all([p.waitForNavigation(), p.locator(`#produto-${one} button[type=submit]`).first().click()]);
    ok(sql(`select codigo from produtos where id=${one}`) === 'PA002' && sql(`select coalesce(codigo,'-') from produtos where id=${dup}`) === '-',
      'código do Nota10 aceito no produto do One (o duplicado libera o código e continua com o código do Nota10)');

    // 2) Tela de ligação: sugestões
    await p.goto(BASE + '/produtos/nota10');
    ok((await p.locator(`[data-n10-produto="PA002"] [data-sugestao="codigo"]`).count()) === 1, 'sugere o produto do One pelo mesmo código');
    ok((await p.locator(`[data-n10-produto="PA009"] [data-sugestao="nome"]`).count()) === 1, 'sem código: sugere pelo nome (sem acento e maiúsculas)');

    // 3) Ligar os do mesmo código
    await Promise.all([p.waitForNavigation(), p.click('[data-ligar-por-codigo]')]);
    ok(sql(`select produto_id from pedido_itens where pedido_id=${ped} and nome='COLAGENO'`) === one, 'as vendas do duplicado passam para o produto do One');
    ok(sql(`select concat(juntado_a_id, ativo) from produtos where id=${dup}`) === one + '0', 'o duplicado fica guardado, inativo, apontando para o do One');
    ok(sql(`select preco from produto_precos where produto_id=${one} and tabela_preco_id=${lojista}`) === '89.90', 'o preço do One continua valendo (não é trocado pelo do Nota10)');
    ok(sql(`select concat(ean, '|', ncm) from produto_tecnico where produto_id=${one}`) === '7891234567895|21069030', 'o que faltava (código de barras, NCM) vem do Nota10');
    ok(sql(`select concat(unidade, '|', round(custo, 2)) from produtos where id=${one}`) === 'UN|12.50', 'unidade e custo vazios no One são completados');
    ok(sql(`select produto_id from pedido_itens where nome='CHA'`) === dupCha, 'a sugestão só pelo nome não é ligada sozinha');

    // 4) Desfazer
    await p.goto(BASE + '/produtos/nota10');
    await Promise.all([p.waitForNavigation(), p.locator('[data-n10-ligados] button').first().click()]);
    ok(sql(`select produto_id from pedido_itens where nome='COLAGENO'`) === dup && sql(`select coalesce(juntado_a_id, 0) from produtos where id=${dup}`) === '0', 'desfazer devolve as vendas ao produto do Nota10');

    // 5) Ligar um a um (escolhendo na lista)
    await p.goto(BASE + '/produtos/nota10');
    const linha = p.locator('[data-n10-produto="PA009"]');
    await linha.locator('input[name=destino]').fill(`${cha} · Chá Verde 100g`);
    await Promise.all([p.waitForNavigation(), linha.locator('button').click()]);
    ok(sql(`select produto_id from pedido_itens where nome='CHA'`) === cha && sql(`select codigo from produtos where id=${cha}`) === 'PA009',
      'ligar um a um: vendas passam e o produto do One (sem código) recebe o código do Nota10');

    // 6) importação seguinte: o código do Nota10 cai no produto do One
    const php = "require 'app/bootstrap.php'; echo App\\Core\\Banco::valor('SELECT COALESCE(juntado_a_id, id) FROM produtos WHERE nota10_codigo = ? OR (codigo = ? AND nota10_codigo IS NULL) ORDER BY nota10_codigo IS NULL LIMIT 1', ['PA009', 'PA009']);";
    ok(execSync(`php -r "${php.replace(/"/g, '\\"').replace(/\$/g, '\\$')}"`).toString().trim() === cha, 'nas próximas importações o código PA009 do Nota10 cai no produto do One');

    ok(erros.length === 0, 'sem erros de JavaScript' + (erros.length ? ': ' + erros.join(' | ') : ''));
  } catch (e) { console.log('FALHA', e.message); falhas++; }
  await nav.close();
  console.log(falhas ? `\n${falhas} falha(s)` : '\nTudo certo.');
  process.exit(falhas ? 1 : 0);
})();
