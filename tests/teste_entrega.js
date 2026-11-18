// V16.3 — Endereço de entrega diferente do endereço do CNPJ: cadastro, pedido (escolha + cópia do endereço),
// envio ao ERP, Depósito, e a revisão das lojas com endereço de entrega escrito só na observação.
// Rodar com o banco de TESTE e o servidor local no ar.
const { chromium } = require('playwright');
const { execSync } = require('child_process');
const fs = require('fs');
const BASE = 'http://127.0.0.1:8000';
let falhas = 0; const ok = (c, m) => { console.log((c ? 'OK   ' : 'FALHA') + ' ' + m); if (!c) falhas++; };
const sql = (q) => execSync(`mysql perfeita --default-character-set=utf8mb4 -N -e "${q.replace(/"/g, '\\"')}"`).toString().trim();
const id = (email) => sql(`select id from usuarios where email='${email}'`);

(async () => {
  const tv1 = id('televendas1@demo.local'), tv2 = id('televendas2@demo.local');
  const loja = (nome, dona, extra = '') => {
    sql(`insert into clientes (tipo, razao_social, nome_fantasia, cnpj, endereco, bairro, cep, cidade, uf, responsavel_id, etapa_id, prioridade, observacoes)
         values ('cliente', '${nome} Ltda', '${nome}', NULL, 'Rua do CNPJ', 'Centro', '90010000', 'Porto Alegre', 'RS', ${dona}, (select min(id) from funil_etapas), 'Média', ${extra ? `'${extra}'` : 'NULL'})`);
    return sql(`select max(id) from clientes where nome_fantasia='${nome}'`);
  };
  const a = loja('Entrega Loja Um', tv1);
  const b = loja('Entrega Obs Dois', tv1, 'Cliente bom. Entregar em: Rua Nova, 10 - Canoas');
  const c = loja('Entrega Obs Tres', tv2, 'End. entrega: Av. Outra, 99');
  const d = loja('Sem Entrega Quatro', tv1, 'Paga sempre em dia');
  sql("insert into produtos (slug, codigo, nome, ficha_json, ativo, visivel_catalogo) values ('produto-entrega', 'EN01', 'Produto da Entrega', '{}', 1, 1)");
  const prod = sql("select id from produtos where slug='produto-entrega'");
  sql(`insert into produto_precos (produto_id, tabela_preco_id, preco) select ${prod}, id, 100 from tabelas_preco`);

  const nav = await chromium.launch(); const erros = [];
  const entrar = async (email) => {
    const ctx = await nav.newContext();
    const p = await ctx.newPage(); p.on('pageerror', (e) => erros.push(email + ': ' + e.message)); p.on('dialog', (dl) => dl.accept());
    await p.goto(BASE + '/entrar'); await p.fill('#email', email); await p.fill('#senha', 'Demo@2026'); await p.click('button[type=submit]'); await p.waitForLoadState();
    return p;
  };
  const fazerPedido = async (p, cliente, onde) => {
    await p.goto(BASE + '/pedidos/novo?cliente=' + cliente);
    await p.fill(`input[name="qtd[${prod}]"]`, '200');
    if (onde) await p.check(`input[name=entrega][value=${onde}]`);
    await p.locator('input[name=pagamento]:not([disabled])').first().check();
    await Promise.all([p.waitForNavigation(), p.click('[data-enviar]')]);
    return sql(`select max(id) from pedidos where cliente_id=${cliente}`);
  };
  try {
    const v = await entrar('televendas1@demo.local');

    // 1) cadastro: número, complemento e o endereço de entrega
    await v.goto(BASE + `/carteira/clientes/${a}/editar`);
    ok(await v.locator('[data-entrega-campos]').isHidden(), 'campos de entrega escondidos até marcar "Entregar em outro endereço"');
    await v.fill('#end_numero', '500'); await v.fill('#end_complemento', 'Loja 3');
    await v.check('[data-entrega-outro]');
    await v.fill('#entrega_endereco', 'Rua do Depósito');
    await Promise.all([v.waitForNavigation(), v.click('.form-cliente button[type=submit]')]);
    ok((await v.locator('.aviso, .alerta, [role=alert]').allInnerTexts()).join(' ').includes('informe pelo menos o endereço, a cidade e a UF'), 'pede cidade e UF da entrega');
    await v.goto(BASE + `/carteira/clientes/${a}/editar`);
    await v.fill('#end_numero', '500'); await v.fill('#end_complemento', 'Loja 3');
    await v.check('[data-entrega-outro]');
    await v.fill('#entrega_razao', 'Depósito da loja'); await v.fill('#entrega_endereco', 'Rua do Depósito'); await v.fill('#entrega_numero', '55');
    await v.fill('#entrega_bairro', 'Distrito'); await v.fill('#entrega_cidade', 'Florianópolis'); await v.selectOption('#entrega_uf', 'SC'); await v.fill('#entrega_cep', '88000-100');
    await Promise.all([v.waitForNavigation(), v.click('.form-cliente button[type=submit]')]);
    ok(sql(`select concat(end_numero, '|', end_complemento, '|', entrega_endereco, '|', entrega_cidade, '|', entrega_uf, '|', entrega_cep) from clientes where id=${a}`)
      === '500|Loja 3|Rua do Depósito|Florianópolis|SC|88000100', 'cadastro guarda número, complemento e o endereço de entrega');
    await v.goto(BASE + '/carteira/clientes/' + a);
    ok((await v.locator('[data-cadastro-erp]').innerText()).includes('Florianópolis/SC'), 'ficha mostra onde entregar');

    // 2) pedido: vem marcado o endereço de entrega; o pedido guarda a cópia
    await v.goto(BASE + '/pedidos/novo?cliente=' + a);
    ok(await v.locator('input[name=entrega][value=entrega]').isChecked(), 'pedido já vem com o endereço de entrega marcado');
    ok((await v.locator('[data-entrega-pedido]').innerText()).includes('Rua do CNPJ'), 'e mostra o endereço do cadastro como opção');
    const p1 = await fazerPedido(v, a, null);
    ok(sql(`select concat(entrega_outro, '|', entrega_uf, '|', entrega_cidade) from pedidos where id=${p1}`) === '1|SC|Florianópolis', 'pedido guarda: entrega em outro endereço, UF SC');
    ok(sql(`select entrega_texto from pedidos where id=${p1}`).startsWith('Depósito da loja · Rua do Depósito, 55'), 'pedido guarda a cópia do endereço');
    const fake = JSON.parse(fs.readFileSync('storage/omie_fake/pedidos.json', 'utf8')).find((x) => x.uuid === sql(`select uuid from pedidos where id=${p1}`));
    ok(fake && fake.entrega && fake.entrega.uf === 'SC' && fake.observacoes.includes('ENTREGAR EM: Depósito da loja'), 'vai ao ERP com o endereço de entrega (e na observação da nota)');
    ok((await v.locator('[data-entrega-ver]').innerText()).includes('Endereço de entrega'), 'tela do pedido mostra "Entregar em"');
    const p2 = await fazerPedido(v, a, 'cadastro');
    ok(sql(`select concat(entrega_outro, '|', entrega_uf) from pedidos where id=${p2}`) === '0|RS', 'escolhendo o endereço do cadastro, entrega no RS');
    // mudar o cadastro depois não muda o pedido antigo
    sql(`update clientes set entrega_cidade='Joinville' where id=${a}`);
    ok(sql(`select entrega_cidade from pedidos where id=${p1}`) === 'Florianópolis', 'pedido antigo não muda quando o cadastro muda');

    const adm = await entrar('admin@demo.local');
    sql(`update pedidos set situacao='aprovado', expedicao='aguardando', expedicao_desde=NOW() where id=${p1}`); // aprovado: entra na fila do Depósito
    await adm.goto(BASE + '/deposito/' + p1);
    ok((await adm.locator('[data-entrega-deposito]').innerText()).includes('ENTREGAR EM OUTRO ENDEREÇO'), 'Depósito vê o aviso de entrega em outro endereço');

    // 3) revisão: endereço escrito só na observação
    await adm.goto(BASE + '/carteira/entregas');
    await Promise.all([adm.waitForNavigation(), adm.click('[data-entregas-procurar]')]);
    ok(sql(`select count(*) from clientes where entrega_revisar is not null and id in (${b},${c})`) === '2' && sql(`select count(*) from clientes where entrega_revisar is not null and id=${d}`) === '0',
      'procura nas observações: marca só as que citam endereço de entrega');
    await v.goto(BASE + '/carteira/lista');
    ok((await v.locator('[data-aviso-entregas]').innerText()).includes('1 loja(s)'), 'a vendedora vê o aviso só com as lojas dela');
    await v.goto(BASE + '/carteira/entregas');
    const lista = await v.locator('[data-entregas]').innerText();
    ok(lista.includes('Entregar em: Rua Nova, 10 - Canoas') && !lista.includes('Av. Outra'), 'lista de revisão mostra o trecho da observação (só da carteira dela)');
    const f = v.locator(`[data-loja="${b}"]`);
    await f.locator('[name=entrega_endereco]').fill('Rua Nova'); await f.locator('[name=entrega_numero]').fill('10'); await f.locator('[name=entrega_cidade]').fill('Canoas');
    await Promise.all([v.waitForNavigation(), f.locator('button[value=confirmar]').click()]);
    ok(sql(`select concat(entrega_endereco, '|', entrega_cidade, '|', entrega_uf, '|', entrega_revisar is null) from clientes where id=${b}`) === 'Rua Nova|Canoas|RS|1', 'confirmar grava o endereço de entrega e tira da revisão');
    ok(await v.locator('[data-entregas-vazio]').isVisible(), 'lista da vendedora fica vazia');
    const v2 = await entrar('televendas2@demo.local');
    await v2.goto(BASE + '/carteira/entregas');
    await Promise.all([v2.waitForNavigation(), v2.locator(`[data-loja="${c}"] button[value=descartar]`).click()]);
    ok(sql(`select concat(entrega_revisar is null, '|', coalesce(entrega_endereco, '-')) from clientes where id=${c}`) === '1|-', '"Não é endereço de entrega" só tira da revisão');
    ok(sql(`select count(*) from auditoria where entidade='cliente' and entidade_id=${b} and acao='editar'`) !== '0', 'confirmação fica na auditoria');

    ok(erros.length === 0, 'sem erros de JavaScript' + (erros.length ? ': ' + erros.join(' | ') : ''));
  } catch (e) { console.log('FALHA', e.message); falhas++; }
  await nav.close();
  console.log(falhas ? `\n${falhas} falha(s)` : '\nTudo certo.');
  process.exit(falhas ? 1 : 0);
})();
