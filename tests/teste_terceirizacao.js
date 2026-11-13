// Teste automático (Playwright). Rodar todos: bash tests/rodar.sh  ·  só este: node tests/teste_terceirizacao.js
// Terceirização: clientes de qualquer carteira, produtos com a marca do cliente (nosso e fórmula própria),
// pedido misto, exclusivo invisível para outros, rótulos (entrada e baixa na ordem de produção) e alertas.
const path = require('path');
const { chromium } = require('playwright');
const { execSync } = require('child_process');
const BASE = 'http://127.0.0.1:8000';
const RAIZ = path.join(__dirname, '..');
let falhas = 0; const ok = (c, m) => { console.log((c ? 'OK   ' : 'FALHA') + ' ' + m); if (!c) falhas++; };
const sql = (q) => execSync(`mysql perfeita --default-character-set=utf8mb4 -N -e "${q.replace(/"/g, '\\"')}"`).toString().trim();
const php = (codigo) => execSync(`php -r '${codigo.replace(/'/g, "'\\''")}'`, { cwd: RAIZ }).toString().trim();
async function entrar(p, email) { await p.goto(BASE + '/entrar'); await p.fill('#email', email); await p.fill('#senha', 'Demo@2026'); await p.click('button[type=submit]'); await p.waitForLoadState(); }

(async () => {
  const b = await chromium.launch();
  const erros = [];
  const nova = async () => { const p = await (await b.newContext({ viewport: { width: 1440, height: 900 } })).newPage(); p.on('pageerror', e => erros.push(e.message)); await p.route(/open-meteo|jsdelivr/, r => r.fulfill({ body: '{}' })); return p; };
  const ges = await nova();
  await entrar(ges, 'gestor@demo.local');

  // 1. Lista: clientes da carteira de Terceirização + cliente de Televendas que faz produto com a marca dele
  await ges.goto(BASE + '/terceirizacao');
  const terc = Number(sql("select count(*) from clientes c join funcoes f on f.id=c.funcao_id where f.frente='terceirizacao' and c.excluido_em is null"));
  const linhas = await ges.locator('[data-cliente-terc]').count();
  ok(linhas >= terc + 1, `lista com os ${terc} da carteira de Terceirização e os de outras carteiras (${linhas})`);
  ok(await ges.locator('[data-cliente-terc]', { hasText: 'Televendas' }).count() >= 1, 'cliente da carteira de Televendas aparece (compra produto com a marca dele)');

  // 2. Cadastrar: nosso produto com a marca do cliente
  const cli = sql("select c.id from clientes c join funcoes f on f.id=c.funcao_id where f.frente='televendas' and c.tipo='cliente' and c.excluido_em is null and c.credito_situacao <> 'atraso' and not exists (select 1 from terceirizacao_produtos tp where tp.cliente_id=c.id) limit 1");
  const nosso = sql("select p.id from produtos p join produto_precos pp on pp.produto_id=p.id and pp.tabela_preco_id=(select id from tabelas_preco where slug='terceirizacao') where p.cliente_exclusivo_id is null and p.ativo=1 and exists (select 1 from ficha_tecnica ft where ft.produto_id=p.id) limit 1");
  await ges.goto(BASE + '/terceirizacao/produtos');
  await ges.selectOption('#te-cliente', cli);
  await ges.selectOption('#te-produto', nosso);
  await ges.fill('#te-nome', 'Produto Teste Marca X');
  await ges.fill('#te-codigo', 'MX-1');
  await ges.fill('#te-minimo', '100');
  await Promise.all([ges.waitForNavigation(), ges.click('[data-form-terc] button[type=submit]')]);
  const tp = sql(`select id from terceirizacao_produtos where cliente_id=${cli} and nome_rotulo='Produto Teste Marca X'`);
  ok(/^\d+$/.test(tp), 'nosso produto cadastrado com a marca do cliente');
  ok(sql("select count(*) from auditoria where entidade='terceirizacao_produto' and acao='criar'") !== '0', 'cadastro registrado na auditoria');

  // 3. Fórmula própria: produto exclusivo, fora do catálogo
  await ges.goto(BASE + '/terceirizacao/produtos?cliente=' + cli);
  await ges.check('[data-tipo][value=proprio]');
  await ges.fill('#te-embalagem', '30 cápsulas');
  await ges.fill('#te-nome', 'Fórmula Exclusiva Teste');
  await ges.fill('#te-preco', '25,50');
  await Promise.all([ges.waitForNavigation(), ges.click('[data-form-terc] button[type=submit]')]);
  const excl = sql(`select produto_id from terceirizacao_produtos where cliente_id=${cli} and nome_rotulo='Fórmula Exclusiva Teste'`);
  ok(sql(`select concat(cliente_exclusivo_id,'/',visivel_catalogo) from produtos where id=${excl}`) === `${cli}/0`, 'fórmula própria vira produto exclusivo do cliente, fora do catálogo');

  // 4. Pedido misto (sistema): as linhas da marca do cliente aparecem junto com os produtos normais
  await ges.goto(BASE + '/pedidos/novo?cliente=' + cli);
  ok(await ges.locator('.ped-linha-marca', { hasText: 'Produto Teste Marca X' }).count() === 1, 'pedido: produto com a marca do cliente como linha própria');
  ok(await ges.locator('.ped-linha-marca', { hasText: 'Fórmula Exclusiva Teste' }).count() === 1, 'pedido: fórmula própria do cliente');
  ok(await ges.locator('tr[data-linha]:not(.ped-linha-marca)').count() > 10, 'pedido: produtos normais continuam');
  const pedido = php(`require "app/bootstrap.php"; use App\\Core\\Banco; $c = App\\Models\\Cliente::buscar(${cli});
    $f = Banco::um("SELECT f.id AS forma, c.id AS condicao FROM formas_pagamento f JOIN condicoes_pagamento c ON c.forma_pagamento_id = f.id WHERE c.valor_minimo = 0 AND f.slug <> \\"boleto\\" LIMIT 1");
    $normal = Banco::valor("SELECT produto_id FROM produto_precos WHERE tabela_preco_id = ? ORDER BY preco DESC LIMIT 1", [App\\Services\\PedidoService::tabelaDoCliente($c)["id"]]);
    [$id, $erros] = App\\Services\\PedidoService::criar($c, ["itens" => ["t${tp}" => 200, $normal => 10], "forma_pagamento_id" => $f["forma"], "condicao_id" => $f["condicao"], "origem" => "sistema", "usuario_id" => 1]);
    echo $id ?: json_encode($erros);`);
  ok(/^\d+$/.test(pedido), 'pedido misto criado (#' + pedido + ')');
  ok(sql(`select concat(sum(terceirizacao_produto_id is not null),'/',sum(terceirizacao_produto_id is null)) from pedido_itens where pedido_id=${pedido}`) === '1/1', 'itens: 1 com a marca do cliente + 1 normal');
  ok(sql(`select nome from pedido_itens where pedido_id=${pedido} and terceirizacao_produto_id=${tp}`) === 'Produto Teste Marca X', 'item gravado com o nome do rótulo do cliente');
  // outro cliente não consegue pedir o produto dele
  const outro = php(`require "app/bootstrap.php"; $c = App\\Models\\Cliente::buscar((int) App\\Core\\Banco::valor("SELECT id FROM clientes WHERE id <> ${cli} AND tipo = \\"cliente\\" AND excluido_em IS NULL LIMIT 1"));
    echo count(App\\Services\\PedidoService::calcular($c, ["t${tp}" => 10, ${excl} => 5])["itens"]);`);
  ok(outro === '0', 'outro cliente não pede o produto com a marca de outro nem a fórmula exclusiva');

  // 5. Catálogo do cliente (logado) mostra "Seus produtos"; o exclusivo não aparece na vitrine pública
  const pub = await nova();
  await pub.goto(BASE + '/catalogo');
  ok(!(await pub.content()).includes('Fórmula Exclusiva Teste'), 'vitrine pública não mostra a fórmula exclusiva');

  // 6. App do externo: pacote só com os produtos da marca dos clientes da carteira dele
  const ext = await nova();
  await entrar(ext, 'externo1@demo.local');
  const pacote = await (await ext.request.get(BASE + '/app/api/dados', { headers: { Accept: 'application/json' } })).json();
  const chaves = Object.values(pacote.marca || {}).flat().map((m) => m.chave);
  ok(!chaves.includes('t' + tp), 'app do externo não recebe produtos com a marca de clientes de outra carteira');
  ok(!pacote.produtos.some((p) => Number(p.id) === Number(excl)), 'app: fórmula exclusiva fora da lista de produtos');

  // 7. Rótulos: entrada, ordem de produção sem rótulo é recusada, com rótulo baixa
  await ges.goto(BASE + '/terceirizacao/rotulos');
  const linha = ges.locator(`[data-rotulo="${tp}"]`);
  ok(await linha.locator('.selo.vermelho').count() === 1, 'sem rótulo = alerta (abaixo do mínimo)');
  await linha.locator('input[name=quantidade]').fill('50');
  await linha.locator('input[name=observacao]').fill('NF 123');
  await Promise.all([ges.waitForNavigation(), linha.locator('button[type=submit]').click()]);
  ok(sql(`select rotulos_estoque from terceirizacao_produtos where id=${tp}`) === '50', 'chegada de 50 rótulos registrada');
  // matéria-prima de sobra para o teste (só para testar os rótulos)
  sql(`update materias_primas set estoque = estoque + 100000 where id in (select materia_prima_id from ficha_tecnica where produto_id=${nosso})`);
  const op = php(`require "app/bootstrap.php"; echo App\\Services\\ProducaoService::criarOrdem(["produto_id" => ${nosso}, "quantidade" => 80, "cliente_id" => ${cli}], 1);`);
  const acabadoAntes = sql(`select coalesce((select quantidade from estoque_acabado where produto_id=${nosso}),0)`);
  const recusa = php(`require "app/bootstrap.php"; try { App\\Services\\ProducaoService::concluirOrdem(${op}, 1); echo "concluiu"; } catch (Throwable $e) { echo $e->getMessage(); }`);
  ok(recusa.includes('Faltam 30 rótulo'), 'ordem para o cliente sem rótulo suficiente é recusada ("' + recusa.slice(0, 60) + '…")');
  ok(sql(`select situacao from ordens_producao where id=${op}`) === 'planejada' && sql(`select coalesce((select quantidade from estoque_acabado where produto_id=${nosso}),0)`) === acabadoAntes, '... e nada muda no estoque');
  await ges.goto(BASE + '/terceirizacao/rotulos');
  ok(await ges.locator(`[data-rotulo="${tp}"]`, { hasText: 'não dá para as ordens' }).count() === 1, 'alerta: rótulos não dão para as ordens planejadas');
  sql(`update terceirizacao_produtos set rotulos_estoque = 120 where id=${tp}`);
  const conclui = php(`require "app/bootstrap.php"; try { App\\Services\\ProducaoService::concluirOrdem(${op}, 1); echo "concluiu"; } catch (Throwable $e) { echo $e->getMessage(); }`);
  ok(conclui === 'concluiu', 'com rótulo suficiente, a ordem conclui');
  ok(sql(`select rotulos_estoque from terceirizacao_produtos where id=${tp}`) === '40', 'baixou 80 rótulos (120 → 40)');
  ok(sql(`select concat(tipo,' ',quantidade) from terceirizacao_rotulos_mov where terceirizacao_produto_id=${tp} and ordem_producao_id=${op}`) === 'consumo -80', 'movimento de consumo com o número da OP');

  // 8. Alertas: Início (foco Terceirização/Produção) e menu
  const prod = await nova();
  await entrar(prod, 'producao@demo.local');
  ok(await prod.locator('[data-card=rotulos]').count() === 1, 'Início da Produção mostra o cartão de rótulos');
  ok(await prod.locator('.menu-lateral [data-pendencia="/terceirizacao"]').isVisible(), 'número no menu da Terceirização');

  // 9. Ordem de produção mostra o cliente
  await prod.goto(BASE + '/producao/ordens/' + op);
  ok((await prod.content()).includes('Terceirização'), 'ordem de produção mostra que é para cliente de terceirização');

  // 10. Frente da função é configurável (Funções)
  const adm = await nova();
  await entrar(adm, 'admin@demo.local');
  if (await adm.locator('[data-novidades-ok]').count()) await adm.click('[data-novidades-ok]');
  await adm.goto(BASE + '/funcoes');
  ok(await adm.locator('select[name=frente]').count() >= 6, 'Funções: escolha da frente de venda');

  // 11. Vendedor só vê; quem não tem acesso não edita
  const fin = await nova();
  await entrar(fin, 'financeiro@demo.local');
  await fin.goto(BASE + '/terceirizacao/produtos');
  ok(await fin.locator('[data-form-terc]').count() === 0, 'Financeiro vê a Terceirização sem o formulário (só leitura)');
  const tok = await fin.locator('meta[name=csrf]').getAttribute('content');
  const r403 = await fin.request.post(BASE + `/terceirizacao/rotulos/${tp}`, { form: { tipo: 'entrada', quantidade: '999' }, headers: { 'X-CSRF-Token': tok } });
  ok(r403.status() === 403 && sql(`select rotulos_estoque from terceirizacao_produtos where id=${tp}`) === '40', 'Financeiro não registra rótulos (403)');

  ok(erros.length === 0, 'sem erros de JavaScript ' + erros.join(' / '));
  await b.close();
  console.log(falhas ? `\n${falhas} FALHA(S)` : '\nTudo certo');
  process.exit(falhas ? 1 : 0);
})();
