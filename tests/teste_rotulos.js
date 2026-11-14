// Teste automático (Playwright). Rodar todos: bash tests/rodar.sh  ·  só este: node tests/teste_rotulos.js
// Produção › Rótulos: estoque de rótulos dos nossos produtos e dos clientes de terceirização num lugar só.
const { chromium } = require('playwright');
const { execSync } = require('child_process');
const BASE = 'http://127.0.0.1:8000';
let falhas = 0; const ok = (c, m) => { console.log((c ? 'OK   ' : 'FALHA') + ' ' + m); if (!c) falhas++; };
const sql = (q) => execSync(`mysql perfeita --default-character-set=utf8mb4 -N -e "${q.replace(/"/g, '\\"')}"`).toString().trim();

(async () => {
  const b = await chromium.launch();
  const erros = [];
  const como = async (email) => {
    const p = await (await b.newContext({ viewport: { width: 1300, height: 900 } })).newPage();
    p.on('pageerror', (e) => erros.push(e.message)); await p.route(/open-meteo|jsdelivr/, (r) => r.fulfill({ body: '{}' }));
    await p.goto(BASE + '/entrar'); await p.fill('#email', email); await p.fill('#senha', 'Demo@2026'); await p.click('button[type=submit]'); await p.waitForLoadState();
    if (await p.locator('[data-novidades-ok]').count()) { await p.click('[data-novidades-ok]'); await p.waitForLoadState(); }
    const csrf = await p.evaluate(() => document.querySelector('meta[name=csrf]').content);
    return { p, csrf };
  };
  const post = async (s, caminho, form) => {
    const html = await (await s.p.request.post(BASE + caminho, { form: { _csrf: s.csrf, ...form } })).text();
    return [...html.matchAll(/class="aviso [a-z]+"[^>]*>([^<]*)</g)].map((m) => m[1]).join(' | ');
  };

  const pr = await como('producao@demo.local');
  // Uma embalagem marcada como rótulo no cadastro aparece na aba
  await post(pr, '/producao/materias', { nome: 'Rótulo Teste Beta', unidade: 'un', tipo: 'embalagem', rotulo: '1', estoque_minimo: '50', custo_unitario: '0,10', ativo: '1' });
  const mp = sql("select id from materias_primas where nome='Rótulo Teste Beta' and rotulo=1");
  ok(!!mp, 'cadastro da matéria-prima: "É rótulo de produto"');
  await pr.p.goto(BASE + '/producao/rotulos');
  ok(await pr.p.locator('.pr-abas a.ativo').innerText() === 'Rótulos', 'aba Rótulos na Produção');
  ok(await pr.p.locator(`[data-rotulo-nosso="${mp}"]`).count() === 1, 'rótulos dos nossos produtos aparecem');
  ok(await pr.p.locator('[data-rotulo-cliente]').count() >= 1, 'rótulos dos clientes (terceirização) aparecem juntos');
  ok(await pr.p.locator(`[data-rotulo-nosso="${mp}"] .selo.vermelho`).count() === 1, 'zerado: alerta de abaixo do mínimo');

  // Nossos: chegada e contagem
  let msg = await post(pr, `/producao/rotulos/nosso/${mp}`, { tipo: 'entrada', quantidade: '300', observacao: 'Gráfica X' });
  ok(msg.includes('Saldo: 300') && sql(`select estoque from materias_primas where id=${mp}`) === '300.000', 'chegada de rótulos soma no estoque');
  msg = await post(pr, `/producao/rotulos/nosso/${mp}`, { tipo: 'ajuste', quantidade: '280', observacao: '' });
  ok(msg.includes('Saldo: 280'), 'contagem ajusta o estoque');

  // Clientes: chegada pela Produção
  const tp = sql('select id from terceirizacao_produtos where ativo=1 order by id limit 1');
  const antes = Number(sql(`select rotulos_estoque from terceirizacao_produtos where id=${tp}`));
  msg = await post(pr, `/producao/rotulos/cliente/${tp}`, { tipo: 'entrada', quantidade: '40', observacao: 'Cliente trouxe' });
  ok(Number(sql(`select rotulos_estoque from terceirizacao_produtos where id=${tp}`)) === antes + 40, 'Produção registra chegada de rótulo de cliente');

  await pr.p.goto(BASE + '/producao/rotulos');
  const movs = await pr.p.locator('table').last().innerText();
  ok(movs.includes('Rótulo Teste Beta') && movs.includes('Cliente trouxe'), 'últimos movimentos: nossos e dos clientes juntos');
  await pr.p.goto(BASE + '/producao/rotulos?situacao=alerta');
  ok(await pr.p.locator(`[data-rotulo-nosso="${mp}"]`).count() === 0, 'filtro "Em alerta" (rótulo com estoque saiu da lista)');

  // Acessos
  const v = await como('televendas1@demo.local');
  await v.p.goto(BASE + '/producao/rotulos');
  ok(await v.p.locator('[data-rotulos-nossos]').count() === 0, 'vendedora não vê o estoque de rótulos');
  msg = await post(v, `/producao/rotulos/nosso/${mp}`, { tipo: 'entrada', quantidade: '10' });
  ok(sql(`select estoque from materias_primas where id=${mp}`) === '280.000', 'vendedora não registra rótulos');

  ok(erros.length === 0, 'nenhum erro de JavaScript' + (erros.length ? ': ' + erros.join(' / ') : ''));
  await b.close();
  console.log(falhas ? `\n${falhas} FALHA(S)` : '\nTudo certo.');
  process.exit(falhas ? 1 : 0);
})();
