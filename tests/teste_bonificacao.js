// V16.4 — Produto bonificado no pedido: mesma nota, CFOP por item (venda 5102/6102, bonificação 5910/6910 pela UF de entrega),
// a loja não paga, fora do faturamento, motivo obrigatório, limite de 15% (acima: aprovação + aviso), relatório.
// Rodar com o banco de TESTE e o servidor local no ar.
const { chromium } = require('playwright');
const { execSync } = require('child_process');
const fs = require('fs');
const BASE = 'http://127.0.0.1:8000';
let falhas = 0; const ok = (c, m) => { console.log((c ? 'OK   ' : 'FALHA') + ' ' + m); if (!c) falhas++; };
const sql = (q) => execSync(`mysql perfeita --default-character-set=utf8mb4 -N -e "${q.replace(/"/g, '\\"')}"`).toString().trim();
const id = (email) => sql(`select id from usuarios where email='${email}'`);

(async () => {
  const tv1 = id('televendas1@demo.local');
  const loja = (nome, uf, extra = '') => {
    sql(`insert into clientes (tipo, razao_social, nome_fantasia, endereco, cidade, uf, responsavel_id, etapa_id, prioridade${extra ? ', entrega_endereco, entrega_cidade, entrega_uf' : ''})
         values ('cliente', '${nome} Ltda', '${nome}', 'Rua A', 'Porto Alegre', '${uf}', ${tv1}, (select min(id) from funil_etapas), 'Média'${extra})`);
    return sql(`select max(id) from clientes where nome_fantasia='${nome}'`);
  };
  const rs = loja('Bonif Loja RS', 'RS');
  const sc = loja('Bonif Loja Entrega SC', 'RS', ", 'Rua do Porto', 'Itajaí', 'SC'");
  sql("insert into produtos (slug, codigo, nome, ficha_json, ativo, visivel_catalogo) values ('produto-bonif', 'BN01', 'Produto Bonificável', '{}', 1, 1)");
  const prod = sql("select id from produtos where slug='produto-bonif'");
  sql(`insert into produto_precos (produto_id, tabela_preco_id, preco) select ${prod}, id, 100 from tabelas_preco`);
  sql('delete from notificacoes');

  const nav = await chromium.launch(); const erros = [];
  const entrar = async (email) => {
    const ctx = await nav.newContext();
    const p = await ctx.newPage(); p.on('pageerror', (e) => erros.push(email + ': ' + e.message)); p.on('dialog', (d) => d.accept());
    await p.goto(BASE + '/entrar'); await p.fill('#email', email); await p.fill('#senha', 'Demo@2026'); await p.click('button[type=submit]'); await p.waitForLoadState();
    return p;
  };
  const montar = async (p, cliente, qtd, bonif) => {
    await p.goto(BASE + '/pedidos/novo?cliente=' + cliente);
    await p.fill(`input[name="qtd[${prod}]"]`, String(qtd));
    await p.fill(`input[name="bonif[${prod}]"]`, String(bonif));
    await p.locator('input[name=pagamento]:not([disabled])').first().check();
  };
  const enviar = async (p) => { await Promise.all([p.waitForNavigation(), p.click('[data-enviar]')]); };
  try {
    const v = await entrar('televendas1@demo.local');

    // 1) a bonificação não ajuda a bater o pedido mínimo
    await montar(v, rs, 4, 2);
    ok(await v.locator('[data-enviar]').isDisabled(), 'bonificado não conta para o pedido mínimo');

    // 2) dentro do limite (10%): motivo obrigatório, CFOPs de dentro do estado
    await montar(v, rs, 10, 1);
    ok((await v.locator('[data-bonificacao-resumo]').innerText()).replace(/\u00a0/g, ' ').includes('Bonificado: R$ 100,00 (10% do vendido)'), 'resumo mostra o bonificado e o % do vendido');
    ok(await v.locator('[data-bonificacao-motivo]').evaluate((s) => s.required), 'motivo da bonificação é obrigatório');
    await v.locator('[data-bonificacao-motivo]').evaluate((s) => { s.required = false; });
    await enviar(v);
    ok((await v.locator('body').innerText()).includes('Escolha o motivo da bonificação'), 'servidor também exige o motivo');
    await montar(v, rs, 10, 1);
    await v.selectOption('[data-bonificacao-motivo]', 'Troca');
    await enviar(v);
    const p1 = sql(`select max(id) from pedidos where cliente_id=${rs}`);
    ok(sql(`select concat(subtotal, '|', bonificado_valor, '|', bonificacao_motivo, '|', bonificacao_acima_limite) from pedidos where id=${p1}`) === '1000.00|100.00|Troca|0',
      'pedido: subtotal só do vendido, R$ 100 bonificado, motivo, dentro do limite');
    ok(sql(`select group_concat(concat(bonificado, ':', quantidade, ':', total, ':', valor_bonificado, ':', cfop) order by bonificado) from pedido_itens where pedido_id=${p1}`)
      === '0:10:1000.00:0.00:5102,1:1:0.00:100.00:5910', 'itens: venda CFOP 5102 e bonificado CFOP 5910 (total 0, valor na nota 100)');
    const fake = JSON.parse(fs.readFileSync('storage/omie_fake/pedidos.json', 'utf8')).find((x) => x.uuid === sql(`select uuid from pedidos where id=${p1}`));
    ok(fake && fake.total === 1000 && fake.itens.some((i) => Number(i.bonificado) === 1 && i.cfop === '5910'), 'ERP recebe cada item com o CFOP e a marca de bonificado (a receber só o vendido)');
    ok(fake.observacoes.includes('BONIFICAÇÃO (Troca): R$ 100,00') && fake.observacoes.includes('não cobrar'), 'observação da nota avisa para não cobrar a bonificação');
    const ver = await v.locator('.ped-itens').innerText();
    ok(ver.includes('bonificado') && ver.includes('CFOP 5.910') && ver.includes('não cobrado') && (await v.locator('[data-pedido-bonificacao]').count()) === 1, 'tela do pedido mostra o item bonificado e o CFOP');

    // 3) acima do limite (20%) e entrega em SC: CFOPs de fora do estado, aprovação e aviso
    await montar(v, sc, 10, 2);
    ok((await v.locator('[data-bonificacao-resumo]').innerText()).includes('acima de 15%'), 'resumo avisa quando passa do limite');
    await v.selectOption('[data-bonificacao-motivo]', 'Campanha');
    await enviar(v);
    const p2 = sql(`select max(id) from pedidos where cliente_id=${sc}`);
    ok(sql(`select bonificacao_acima_limite from pedidos where id=${p2}`) === '1', 'pedido marcado: bonificação acima do limite');
    ok(sql(`select group_concat(cfop order by bonificado) from pedido_itens where pedido_id=${p2}`) === '6102,6910', 'entrega em SC: CFOPs 6102 e 6910');
    ok(Number(sql("select count(*) from notificacoes where titulo like '%bonificação acima do limite%'")) > 0, 'gestão recebe aviso da bonificação acima do limite');
    const adm = await entrar('admin@demo.local');
    await adm.goto(BASE + '/aprovacao');
    ok((await adm.locator('[data-bonificacao-aprovacao]').allInnerTexts()).join(' ').includes('acima do limite'), 'Aprovação mostra a bonificação acima do limite');

    // 4) repetir o último pedido: a bonificação volta na coluna "Bonif."
    sql(`update pedidos set situacao='aprovado' where id=${p1}`);
    await v.goto(BASE + `/pedidos/novo?cliente=${rs}&repetir=1`);
    ok((await v.inputValue(`input[name="qtd[${prod}]"]`)) === '10' && (await v.inputValue(`input[name="bonif[${prod}]"]`)) === '1', 'repetir último pedido traz vendido e bonificado separados');

    // 5) relatório
    await adm.goto(BASE + '/pedidos/bonificacoes');
    ok((await adm.locator('[data-bon="valor"]').innerText()) === 'R$ 300,00' && (await adm.locator('[data-bon="pedidos"]').innerText()) === '2', 'relatório: R$ 300,00 bonificado em 2 pedidos');
    await adm.goto(BASE + '/pedidos/bonificacoes?grupo=motivo');
    const g = await adm.locator('[data-bonificacao-grupos]').innerText();
    ok(g.includes('Campanha') && g.includes('Troca'), 'relatório por motivo');
    await adm.goto(BASE + '/pedidos/bonificacoes?grupo=produto');
    ok((await adm.locator('[data-bonificacao-grupos]').innerText()).includes('Produto Bonificável'), 'relatório por produto');
    const v2 = await entrar('televendas2@demo.local');
    await v2.goto(BASE + '/pedidos/bonificacoes');
    ok((await v2.locator('[data-bon="pedidos"]').innerText()) === '0', 'outra vendedora não vê as bonificações dos pedidos alheios');

    ok(erros.length === 0, 'sem erros de JavaScript' + (erros.length ? ': ' + erros.join(' | ') : ''));
  } catch (e) { console.log('FALHA', e.message); falhas++; }
  await nav.close();
  console.log(falhas ? `\n${falhas} falha(s)` : '\nTudo certo.');
  process.exit(falhas ? 1 : 0);
})();
