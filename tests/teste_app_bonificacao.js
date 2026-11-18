// V16.5 — App do externo: bonificação no pedido (mesmo jeito do sistema) e o endereço de entrega no topo do pedido.
// Rodar com o banco de TESTE e o servidor local no ar.
const { chromium } = require('playwright');
const { execSync } = require('child_process');
const BASE = 'http://127.0.0.1:8000';
let falhas = 0; const ok = (c, m) => { console.log((c ? 'OK   ' : 'FALHA') + ' ' + m); if (!c) falhas++; };
const sql = (q) => execSync(`mysql perfeita --default-character-set=utf8mb4 -N -e "${q.replace(/"/g, '\\"')}"`).toString().trim();
const espera = (ms) => new Promise((r) => setTimeout(r, ms));
async function esperarAte(fn, ms = 10000) { const fim = Date.now() + ms; while (Date.now() < fim) { if (await fn()) return true; await espera(250); } return false; }

(async () => {
  const ext = sql("select id from usuarios where email='externo1@demo.local'");
  sql(`insert into clientes (tipo, razao_social, nome_fantasia, endereco, cidade, uf, responsavel_id, etapa_id, prioridade, entrega_endereco, entrega_numero, entrega_cidade, entrega_uf)
       values ('cliente', 'AAA App Bonif Ltda', 'AAA App Bonif', 'Rua A', 'Porto Alegre', 'RS', ${ext}, (select min(id) from funil_etapas), 'Média', 'Rua do Porto', '7', 'Itajaí', 'SC')`);
  const cli = sql("select max(id) from clientes where nome_fantasia='AAA App Bonif'");
  sql("insert into produtos (slug, codigo, nome, ficha_json, ativo, visivel_catalogo) values ('aaa-produto-app', 'AP01', 'AAA Produto do App', '{}', 1, 1)");
  const prod = sql("select id from produtos where slug='aaa-produto-app'");
  sql(`insert into produto_precos (produto_id, tabela_preco_id, preco) select ${prod}, id, 100 from tabelas_preco`);

  const nav = await chromium.launch();
  const ctx = await nav.newContext({ viewport: { width: 390, height: 844 } }); const p = await ctx.newPage();
  const erros = []; p.on('pageerror', (e) => erros.push(e.message));
  try {
    await p.goto(BASE + '/app');
    await p.fill('#email', 'externo1@demo.local'); await p.fill('#senha', 'Demo@2026'); await p.click('button[type=submit]'); await p.waitForLoadState();
    await p.waitForSelector('.ap-cliente');
    await p.fill('[data-busca-clientes]', 'AAA App Bonif');
    await p.locator(`[data-novo-pedido="${cli}"]`).click();
    await p.waitForSelector('.ap-produtos li');
    ok((await p.locator('.ap-entrega').innerText()).includes('Rua do Porto, 7') , 'topo do pedido mostra onde entregar (endereço de entrega da loja)');
    const linha = p.locator('.ap-produtos li', { hasText: 'AAA Produto do App' });
    await linha.locator('[data-produto] input').fill('10'); await linha.locator('[data-produto] input').dispatchEvent('input');
    await linha.locator('[data-bonif]').fill('2'); await linha.locator('[data-bonif]').dispatchEvent('input');
    const resumo = (await p.locator('[data-ap-bonificacao]').innerText()).replace(/ /g, ' ');
    ok(resumo.includes('Bonificado: R$ 200,00 (20% do vendido)') && resumo.includes('vai para aprovação'), 'app mostra o bonificado, o % e o aviso acima de 15%');
    await p.locator('.ap-forma input[type=radio]:not([disabled])').first().check();
    await p.click('[data-salvar]');
    ok((await p.locator('body').innerText()).includes('Escolha o motivo da bonificação'), 'app exige o motivo da bonificação');
    await p.selectOption('[data-bonif-motivo]', 'Amostra');
    await p.click('[data-salvar]');
    ok(await esperarAte(() => sql(`select count(*) from pedidos where cliente_id=${cli} and origem='app'`) === '1'), 'pedido do app chega ao servidor');
    const ped = sql(`select max(id) from pedidos where cliente_id=${cli}`);
    ok(sql(`select concat(subtotal, '|', bonificado_valor, '|', bonificacao_motivo, '|', bonificacao_acima_limite, '|', entrega_outro, '|', entrega_uf) from pedidos where id=${ped}`)
      === '1000.00|200.00|Amostra|1|1|SC', 'pedido: R$ 200 bonificado (Amostra), acima do limite, entrega em SC');
    ok(sql(`select group_concat(concat(bonificado, ':', quantidade, ':', total, ':', cfop) order by bonificado) from pedido_itens where pedido_id=${ped}`) === '0:10:1000.00:6102,1:2:0.00:6910',
      'itens do app: venda 6102 e bonificado 6910 (a loja não paga)');
    await p.click('[data-aba=pedidos]'); await espera(300);
    ok((await p.locator('.ap-fila').innerText()).replace(/ /g, ' ').includes('🎁 R$ 200,00 bonificado'), 'lista de pedidos do app mostra o bonificado');
    ok(erros.length === 0, 'sem erros de JavaScript' + (erros.length ? ': ' + erros.join(' | ') : ''));
  } catch (e) { console.log('FALHA', e.message); falhas++; }
  await nav.close();
  console.log(falhas ? `\n${falhas} falha(s)` : '\nTudo certo.');
  process.exit(falhas ? 1 : 0);
})();
