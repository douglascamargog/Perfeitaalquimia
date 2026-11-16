// =====================================================================
// Prints do Treinamento (V13.2): tira as imagens das telas do One com os DADOS DE DEMONSTRAÇÃO
// e salva em public/assets/img/treinamento/ (vão junto no pacote de atualização).
//
//   1) banco de demonstração recriado:  php database/migrar.php   (num banco vazio)
//   2) servidor rodando:                 php -S 127.0.0.1:8000 -t public public/index.php
//   3) node ferramentas/capturar_treinamento.js            → todas
//      node ferramentas/capturar_treinamento.js pedido-novo → só as escolhidas
//
// Só usa dados fictícios do ambiente de teste (nunca rode apontando para o sistema com dados reais).
// =====================================================================
const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');
const BASE = process.env.ONE_URL || 'http://127.0.0.1:8000';
const PASTA = path.join(__dirname, '..', 'public', 'assets', 'img', 'treinamento');
const SENHA = 'Demo@2026';

// Esconde o que não faz parte da tela (One flutuante, avisos que somem, letreiro)
const LIMPAR = `.one-flutuante,[data-one-abrir],.one-balao,.tut-fundo,.tut-luz,.tut-balao,.aviso-flutuante,[data-letreiro]{display:none!important}`;

// [nome do arquivo, e-mail, endereço, { recorte: seletor, acoes: async (p) => {}, celular: true }]
const PRINTS = [
  // ---------------- Comercial ----------------
  ['inicio-vendedora', 'televendas1@demo.local', '/', {}],
  ['meu-mes', 'televendas1@demo.local', '/meu-mes', {}],
  ['carteira-visao', 'televendas1@demo.local', '/carteira', {}],
  ['carteira-lista', 'televendas1@demo.local', '/carteira/lista', {}],
  ['carteira-funil', 'televendas1@demo.local', '/carteira/funil', {}],
  ['ficha-cliente', 'televendas1@demo.local', '/carteira/clientes/{cliente}', {}],
  ['ficha-cliente-anotacao', 'televendas1@demo.local', '/carteira/clientes/{cliente}', { recorte: '[data-compositor]' }],
  ['ficha-cliente-contato', 'televendas1@demo.local', '/carteira/clientes/{cliente}', { recorte: '[data-compositor]', acoes: async (p) => p.click('[data-cx-aba="contato"]') }],
  ['ficha-cliente-agendar', 'televendas1@demo.local', '/carteira/clientes/{cliente}', { recorte: '[data-compositor]', acoes: async (p) => p.click('[data-cx-aba="agendar"]') }],
  ['agenda', 'televendas1@demo.local', '/agenda', {}],
  ['recompra', 'televendas1@demo.local', '/recompra', {}],
  ['reativacao', 'televendas1@demo.local', '/reativacao', {}],
  ['pedido-novo', 'televendas1@demo.local', '/pedidos/novo?cliente={cliente}', { acoes: async (p) => {
    const qtds = p.locator('tr[data-linha] input.ped-qtd');
    for (const [i, q] of [[0, 12], [1, 6], [2, 24], [3, 10]]) { if (await qtds.count() > i) await qtds.nth(i).fill(String(q)); }
    await qtds.first().dispatchEvent('input');
  } }],
  ['pedido-carrinho', 'televendas1@demo.local', '/pedidos/novo?cliente={cliente}', { recorte: '[data-pedido] aside, .ped-resumo, [data-subtotal]', acoes: async (p) => {
    const qtds = p.locator('tr[data-linha] input.ped-qtd');
    for (const [i, q] of [[0, 12], [1, 6], [2, 24]]) { if (await qtds.count() > i) await qtds.nth(i).fill(String(q)); }
    await qtds.first().dispatchEvent('input');
  } }],
  ['pedidos-lista', 'televendas1@demo.local', '/pedidos', {}],
  ['orcamentos', 'televendas1@demo.local', '/orcamentos', {}],
  ['campanhas', 'gestor@demo.local', '/campanhas', {}],
  ['mensagens', 'televendas1@demo.local', '/mensagens', {}],
  ['prospeccao', 'reativacao@demo.local', '/prospeccao', {}],
  ['prospeccao-empresas', 'reativacao@demo.local', '/prospeccao/empresas', {}],
  ['terceirizacao', 'terceirizacao@demo.local', '/terceirizacao', {}],
  ['terceirizacao-rotulos', 'terceirizacao@demo.local', '/terceirizacao/rotulos', {}],
  ['app-externo', 'externo1@demo.local', '/app', { celular: true, espera: 2500 }],
  ['biblioteca', 'televendas1@demo.local', '/academia/produtos', {}],
  ['ficha-produto', 'televendas1@demo.local', '/academia/produtos/magnesio', {}],
  ['ficha-produto-atendimento', 'televendas1@demo.local', '/academia/produtos/magnesio?aba=atendimento', {}],
  ['aprovacao', 'financeiro@demo.local', '/aprovacao', {}],
  // ---------------- Fábrica ----------------
  ['producao', 'producao@demo.local', '/producao', {}],
  ['producao-ordens', 'producao@demo.local', '/producao/ordens', {}],
  ['producao-ordem-nova', 'producao@demo.local', '/producao/ordens/nova', {}],
  ['producao-ordem', 'producao@demo.local', '/producao/ordens', { abrir: 'table a[href*="/producao/ordens/"]' }],
  ['producao-materias', 'producao@demo.local', '/producao/materias', {}],
  ['producao-materia', 'producao@demo.local', '/producao/materias', { abrir: 'table a[href*="/producao/materias/"]' }],
  ['producao-fichas', 'tecnica@demo.local', '/producao/fichas', {}],
  ['producao-ficha', 'tecnica@demo.local', '/producao/fichas', { abrir: 'table a[href*="/producao/fichas/"]' }],
  ['producao-acabados', 'producao@demo.local', '/producao/acabados', {}],
  ['producao-rotulos', 'producao@demo.local', '/producao/rotulos', {}],
  ['produto-tecnico', 'tecnica@demo.local', '/produtos/magnesio', {}],
  ['desenvolvimento', 'tecnica@demo.local', '/desenvolvimento', {}],
  ['desenvolvimento-projeto', 'tecnica@demo.local', '/desenvolvimento', { abrir: 'a[href*="/desenvolvimento/"][data-projeto], [data-projeto] a[href*="/desenvolvimento/"]' }],
  ['qualidade-compras', 'qualidade@demo.local', '/compras', {}],
  ['qualidade-compra-nova', 'qualidade@demo.local', '/compras/novo', {}],
  ['deposito', 'deposito@demo.local', '/deposito', {}],
  ['deposito-separar', 'deposito@demo.local', '/deposito', { abrir: '[data-fila] a[href*="/deposito/"]' }],
  ['deposito-recebimento', 'deposito@demo.local', '/deposito/recebimento', {}],
  ['deposito-lotes', 'deposito@demo.local', '/deposito/lotes', {}],
  // ---------------- Marketing ----------------
  ['marketing-inicio', 'marketing@demo.local', '/marketing/inicio', {}],
  ['marketing-pedir', 'televendas1@demo.local', '/marketing/pedir', {}],
  ['marketing-mural', 'social@demo.local', '/marketing', {}],
  ['marketing-feed', 'social@demo.local', '/marketing/feed', {}],
  ['marketing-post-novo', 'social@demo.local', '/marketing/posts/novo', {}],
  ['marketing-planejamento', 'marketing@demo.local', '/marketing/planejamento', {}],
  ['ecommerce', 'ecommerce@demo.local', '/marketing/ecommerce', {}],
  ['catalogo-admin', 'marketing@demo.local', '/produtos/catalogo', {}],
  ['leads', 'marketing@demo.local', '/leads', {}],
  // ---------------- Financeiro e gestão ----------------
  ['cobranca', 'financeiro@demo.local', '/cobranca', {}],
  ['cobranca-caso', 'financeiro@demo.local', '/cobranca', { espera: 300, acoes: async (p) => { await p.locator('[data-funil] [data-abrir-cliente]').first().click(); await p.waitForTimeout(1500); } }],
  ['financeiro-compras', 'financeiro@demo.local', '/financeiro/compras', {}],
  ['financeiro-fornecedores', 'financeiro@demo.local', '/financeiro/fornecedores', {}],
  ['painel', 'gestor@demo.local', '/painel', {}],
  ['equipe-vendas', 'gestor@demo.local', '/equipe', {}],
  ['acompanhamento', 'gestor@demo.local', '/acompanhamento', {}],
  ['metas', 'gestor@demo.local', '/metas', {}],
  ['carteiras', 'gestor@demo.local', '/carteiras', {}],
  ['inicio-diretoria', 'diretoria@demo.local', '/', {}],
  ['configuracoes', 'admin@demo.local', '/configuracoes', {}],
  ['usuarios', 'admin@demo.local', '/usuarios', {}],
  ['integracao', 'admin@demo.local', '/integracao', {}],
  ['treinamento-equipe', 'gestor@demo.local', '/treinamento/equipe', {}],
  ['pessoas', 'televendas1@demo.local', '/pessoas', {}],
  ['comunicados', 'admin@demo.local', '/comunicados', {}],
];

async function cliente(p) {
  // um cliente da carteira da Televendas 1 que já comprou (para a ficha e o pedido)
  await p.goto(BASE + '/recompra');
  const id = await p.locator('[data-abrir-cliente]').first().getAttribute('data-abrir-cliente').catch(() => null);
  return id || '1';
}

(async () => {
  fs.mkdirSync(PASTA, { recursive: true });
  const escolhidos = process.argv.slice(2);
  const b = await chromium.launch({ args: ['--lang=pt-BR'] });
  const sessoes = {};
  let clienteId = null;
  for (const [nome, email, endereco, op] of PRINTS) {
    if (escolhidos.length && !escolhidos.includes(nome)) continue;
    const chave = email + (op.celular ? ':cel' : '');
    if (!sessoes[chave]) {
      const base = { locale: 'pt-BR', timezoneId: 'America/Sao_Paulo' };
      const ctx = await b.newContext(op.celular ? { ...base, viewport: { width: 390, height: 780 }, deviceScaleFactor: 2, isMobile: true, hasTouch: true } : { ...base, viewport: { width: 1280, height: 760 } });
      const p = await ctx.newPage();
      await p.route(/open-meteo/, (r) => r.fulfill({ body: '{}' }));
      await p.goto(BASE + '/entrar'); await p.fill('#email', email); await p.fill('#senha', SENHA); await p.click('button[type=submit]'); await p.waitForLoadState();
      if (await p.locator('[data-novidades-ok]').count()) { await p.click('[data-novidades-ok]'); await p.waitForLoadState(); }
      sessoes[chave] = p;
    }
    const p = sessoes[chave];
    if (endereco.includes('{cliente}') && !clienteId) clienteId = await cliente(sessoes['televendas1@demo.local'] || p);
    await p.goto(BASE + endereco.replace('{cliente}', clienteId));
    await p.addStyleTag({ content: LIMPAR });
    await p.waitForTimeout(op.espera || 600);
    if (op.abrir) {
      const link = p.locator(op.abrir).first();
      if (await link.count()) { await Promise.all([p.waitForLoadState(), link.click()]); await p.addStyleTag({ content: LIMPAR }); await p.waitForTimeout(500); }
      else console.log('   (sem item para abrir em ' + nome + ')');
    }
    if (op.acoes) { await op.acoes(p); await p.waitForTimeout(400); }
    const arquivo = path.join(PASTA, nome + '.jpg');
    let feito = false;
    if (op.recorte) {
      const alvo = p.locator(op.recorte).first();
      if (await alvo.count() && await alvo.isVisible()) { await alvo.scrollIntoViewIfNeeded(); await alvo.screenshot({ path: arquivo, type: 'jpeg', quality: 78 }); feito = true; }
    }
    if (!feito && op.rolar) {
      const alvo = p.locator(op.rolar).first();
      if (await alvo.count()) await alvo.evaluate((e) => e.scrollIntoView({ block: 'start' }));
      await p.waitForTimeout(200);
    }
    if (!feito) await p.screenshot({ path: arquivo, type: 'jpeg', quality: op.celular ? 70 : 72 });
    console.log('ok ', nome, (fs.statSync(arquivo).size / 1024).toFixed(0) + ' KB');
  }
  await b.close();
})();
