// V16.6 — Bonificação no orçamento: o vendedor oferece, o cliente vê no link como presente (não muda) e,
// ao aprovar, a bonificação vai para o pedido com as mesmas regras (CFOP de bonificação, a loja não paga, limite de 15%).
// Rodar com o banco de TESTE e o servidor local no ar.
const { chromium } = require('playwright');
const { execSync } = require('child_process');
const BASE = 'http://127.0.0.1:8000';
let falhas = 0; const ok = (c, m) => { console.log((c ? 'OK   ' : 'FALHA') + ' ' + m); if (!c) falhas++; };
const sql = (q) => execSync(`mysql perfeita --default-character-set=utf8mb4 -N -e "${q.replace(/"/g, '\\"')}"`).toString().trim();

(async () => {
  const tv1 = sql("select id from usuarios where email='televendas1@demo.local'");
  sql(`insert into clientes (tipo, razao_social, nome_fantasia, endereco, cidade, uf, responsavel_id, etapa_id, prioridade)
       values ('cliente', 'Orc Bonif Ltda', 'Orc Bonif', 'Rua A', 'Porto Alegre', 'RS', ${tv1}, (select min(id) from funil_etapas), 'Média')`);
  const cli = sql("select max(id) from clientes where nome_fantasia='Orc Bonif'");
  sql("insert into produtos (slug, codigo, nome, ficha_json, ativo, visivel_catalogo) values ('produto-orc-bonif', 'OB01', 'Produto Orc Bonif', '{}', 1, 1)");
  const prod = sql("select id from produtos where slug='produto-orc-bonif'");
  sql(`insert into produto_precos (produto_id, tabela_preco_id, preco) select ${prod}, id, 100 from tabelas_preco`);

  const nav = await chromium.launch(); const erros = [];
  const pagina = async () => { const p = await (await nav.newContext()).newPage(); p.on('pageerror', (e) => erros.push(e.message)); p.on('dialog', (d) => d.accept()); return p; };
  try {
    const v = await pagina();
    await v.goto(BASE + '/entrar'); await v.fill('#email', 'televendas1@demo.local'); await v.fill('#senha', 'Demo@2026'); await v.click('button[type=submit]'); await v.waitForLoadState();
    const montar = async () => {
      await v.goto(BASE + '/pedidos/novo?cliente=' + cli);
      await v.fill(`input[name="qtd[${prod}]"]`, '10'); await v.fill(`input[name="bonif[${prod}]"]`, '1');
    };
    await montar();
    await Promise.all([v.waitForNavigation(), v.click('[data-gerar-orcamento]')]);
    ok((await v.locator('body').innerText()).includes('Escolha o motivo da bonificação'), 'orçamento com bonificação exige o motivo');
    await montar();
    await v.selectOption('[data-bonificacao-motivo]', 'Primeiro pedido');
    await Promise.all([v.waitForNavigation(), v.click('[data-gerar-orcamento]')]);
    const orc = sql(`select max(id) from orcamentos where cliente_id=${cli}`);
    ok(sql(`select concat(subtotal, '|', bonificado_valor, '|', bonificacao_motivo) from orcamentos where id=${orc}`) === '1000.00|100.00|Primeiro pedido', 'orçamento guarda o vendido, o bonificado e o motivo');
    ok(sql(`select group_concat(concat(bonificado, ':', quantidade, ':', total) order by bonificado) from orcamento_itens where orcamento_id=${orc}`) === '0:10:1000.00,1:1:0.00', 'itens: vendido e bonificado (total 0)');
    ok((await v.locator('[data-orc-bonificado]').count()) === 1, 'tela do orçamento mostra o item bonificado');

    // editar o orçamento: a bonificação volta na coluna "Bonif."
    await v.goto(BASE + `/pedidos/novo?cliente=${cli}&orcamento=${orc}`);
    ok((await v.inputValue(`input[name="qtd[${prod}]"]`)) === '10' && (await v.inputValue(`input[name="bonif[${prod}]"]`)) === '1', 'editar orçamento traz vendido e bonificado separados');

    // o cliente abre o link, vê o presente, diminui a quantidade e aprova
    const c = await pagina();
    await c.goto(BASE + '/o/' + sql(`select token from orcamentos where id=${orc}`));
    const presente = await c.locator('[data-orcamento-bonificacao]').innerText();
    ok(presente.includes('1×') && presente.includes('Produto Orc Bonif') && presente.includes('R$ 0,00'), 'cliente vê a bonificação de presente no link');
    ok((await c.locator(`[data-orcamento-bonificacao] input`).count()) === 0, 'cliente não consegue mudar a bonificação');
    await c.fill(`input[name="qtd[${prod}]"]`, '5'); await c.locator(`input[name="qtd[${prod}]"]`).dispatchEvent('input');
    await c.locator('input[name=pagamento]:not([disabled])').first().check();
    await Promise.all([c.waitForNavigation(), c.click('[data-enviar]')]);
    const ped = sql(`select pedido_id from orcamentos where id=${orc}`);
    ok(ped !== 'NULL' && ped !== '', 'orçamento aprovado virou pedido');
    ok(sql(`select concat(subtotal, '|', bonificado_valor, '|', bonificacao_motivo, '|', bonificacao_acima_limite) from pedidos where id=${ped}`) === '500.00|100.00|Primeiro pedido|1',
      'pedido leva a bonificação oferecida; com menos quantidade passou de 15% e vai marcado para aprovação');
    ok(sql(`select group_concat(concat(bonificado, ':', quantidade, ':', cfop) order by bonificado) from pedido_itens where pedido_id=${ped}`) === '0:5:5102,1:1:5910', 'itens do pedido: 5 vendidos (5102) e 1 bonificado (5910)');
    ok(sql(`select cliente_alterou from orcamentos where id=${orc}`) === '1', 'a alteração do cliente é registrada (só nos vendidos)');
    ok(erros.length === 0, 'sem erros de JavaScript' + (erros.length ? ': ' + erros.join(' | ') : ''));
  } catch (e) { console.log('FALHA', e.message); falhas++; }
  await nav.close();
  console.log(falhas ? `\n${falhas} falha(s)` : '\nTudo certo.');
  process.exit(falhas ? 1 : 0);
})();
