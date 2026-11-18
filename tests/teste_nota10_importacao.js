// V17.0 — Importação de verdade do Nota10 (regras em docs/nota10/regras-importacao.md), pela tela, de ponta a ponta.
// Usa tests/recursos/nota10_importacao.sql (estrutura real do Nota10, dados inventados). Rodar com o banco de TESTE.
const { chromium } = require('playwright');
const { execSync } = require('child_process');
const fs = require('fs');
const BASE = 'http://127.0.0.1:8000';
let falhas = 0; const ok = (c, m) => { console.log((c ? 'OK   ' : 'FALHA') + ' ' + m); if (!c) falhas++; };
const sql = (q) => execSync(`mysql perfeita --default-character-set=utf8mb4 -N -e "${q.replace(/"/g, '\\"')}"`).toString().trim();

(async () => {
  // começo limpo: sem importação anterior; Ronaldo (código 1286 no Nota10) já é funcionário do One; o produto PA001 já existe no One
  sql('delete from nota10_importacoes; delete from nota10_mapas');
  for (const t of sql("show tables like 'n10\\\\_%'").split('\n').filter(Boolean)) sql(`drop table ${t}`);
  for (const f of fs.readdirSync('storage/nota10')) if (/\.(sql|txt|zip|gz)$/.test(f)) fs.unlinkSync('storage/nota10/' + f);
  sql("update usuarios set codigo_vendedor='1286' where email='televendas1@demo.local'");
  sql("insert into produtos (slug, codigo, nome, ficha_json, ativo, visivel_catalogo) values ('colageno-do-one', 'PA001', 'Colageno do One', '{}', 1, 1)");
  sql("update cidades set cod_ibge = '4205407' where uf = 'SC' and nome like 'Florian%'");
  sql("insert ignore into cidades (nome, uf, cod_ibge) values ('Florianopolis', 'SC', '4205407')");
  const tv1 = sql("select id from usuarios where email='televendas1@demo.local'");
  fs.copyFileSync('tests/recursos/nota10_importacao.sql', 'storage/nota10/dump-teste.txt');

  const nav = await chromium.launch(); const erros = [];
  const p = await (await nav.newContext()).newPage(); p.on('pageerror', (e) => erros.push(e.message)); p.on('dialog', (d) => d.accept());
  try {
    await p.goto(BASE + '/entrar'); await p.fill('#email', 'admin@demo.local'); await p.fill('#senha', 'Demo@2026'); await p.click('button[type=submit]'); await p.waitForLoadState();
    await p.goto(BASE + '/sistema/nota10');
    await Promise.all([p.waitForNavigation(), p.click('[data-n10-soltos] button')]);
    await p.click('[data-n10-ler]'); await p.waitForSelector('[data-n10-lido]', { timeout: 60000 });

    // ligações sugeridas
    ok((await p.inputValue('select[name="mapa[vendedor][1286]"]')) === tv1, 'vendedor 1286 ligado ao funcionário com o mesmo código');
    ok((await p.inputValue('select[name="mapa[vendedor][58250]"]')) === 'novo', 'vendedora ativa sem cadastro no One: "Criar funcionário" já marcado');
    ok((await p.inputValue('select[name="mapa[vendedor][1237]"]')) === '', 'vendedor inativo: ninguém (os clientes vão para a fila)');
    ok((await p.inputValue('select[name="mapa[tipo_cadastro][P]"]')) === 'lead' && (await p.inputValue('select[name="mapa[tipo_cadastro][CF]"]')) === 'cliente_fornecedor'
      && (await p.inputValue('select[name="mapa[tipo_cadastro][ATIVO]"]')) === 'validar', 'tipos: P lead, CF cliente e fornecedor, tipo errado → validar cadastro');
    ok((await p.inputValue('select[name="mapa[grupo][2]"]')) === 'materia_prima' && (await p.inputValue('select[name="mapa[grupo][1]"]')) === 'produto', 'grupos: INSUMOS vira matéria-prima, CAPSULAS produto');
    await Promise.all([p.waitForNavigation(), p.click('[data-n10-salvar]')]);

    // importar
    await p.fill('[data-n10-form-importar] input[name=confirmar]', 'IMPORTAR');
    await Promise.all([p.waitForNavigation(), p.click('[data-n10-form-importar] button')]);
    await p.waitForSelector('[data-n10-importado]', { timeout: 120000 });
    const rel = await p.locator('[data-n10-relatorio]').innerText();
    ok(rel.includes('Pedidos faturados') && rel.includes('Faturamento importado'), 'relatório final na tela (o que está no One agora)');
    ok(fs.readdirSync('storage/backups').some((f) => f.startsWith('one-antes-de-importar-nota10-')), 'backup completo feito antes de importar');

    // funcionários e cadastros
    ok(sql("select concat(nome, '|', codigo_vendedor, '|', ativo) from usuarios where email = 'nota10-58250@funcionario.pendente'") === 'Jeina Silva|58250|1', 'funcionária criada com o código do Nota10');
    ok(sql('select count(*) from clientes where nota10_numero = 102') === '0' && sql("select count(*) from n10_x_cliente x join clientes c on c.id = x.cliente_id where x.numero in (101, 102) and c.nota10_numero = 101") === '2',
      'mesmo CNPJ em dois cadastros: uma loja só');
    ok(sql("select concat(c.tipo, '|', u.email) from clientes c join usuarios u on u.id = c.responsavel_id where c.nota10_numero = 108") === 'cliente|nota10-58250@funcionario.pendente', 'responsável = quem fez a última venda (ativo)');
    ok(sql("select concat(coalesce(c.responsavel_id, 'fila'), '|', f.slug) from clientes c join funcoes f on f.id = c.funcao_id where c.nota10_numero = 107") === 'fila|prospeccao_reativacao', 'vendedor inativo: cliente na fila de Prospecção e Reativação');
    ok(sql("select count(*) from clientes where tipo = 'lead' and nota10_numero between 9001 and 9100") === '56', 'prospects entram como leads');
    ok(sql('select count(*) from clientes where nota10_numero in (57207, 4405, 1286, 58250, 201, 301)') === '0', 'vendedores, cadastros internos, fornecedor e transportadora puros não viram cliente');
    ok(sql("select uf from clientes where nota10_numero = 103") === 'SC', 'UF errada corrigida pelo código IBGE do município');
    ok(sql("select concat(e.nome) from clientes c join funil_etapas e on e.id = c.etapa_id where c.nota10_numero = 106") === 'Validar cadastro', 'tipo digitado errado vai para "Validar cadastro"');
    ok(sql("select entrega_revisar is not null from clientes where nota10_numero = 101") === '1', 'endereço de entrega só na observação vai para a revisão');
    ok(sql("select group_concat(tipo order by tipo) from fornecedores") === 'fornecedor,fornecedor,transportadora', 'fornecedores (incluindo o cliente-fornecedor) e transportadora');

    // pedidos
    const ped = (g, campos) => sql(`select concat_ws('|', ${campos}) from pedidos where nota10_guid = '${g}'`);
    ok(ped('g01', 'situacao, numero_nf, nf_bonificacao, bonificado_valor') === 'faturado|5001|5002|99.80', 'pedido com NF: faturado, com a bonificação "gêmea" juntada');
    ok(sql("select count(*) from pedidos where nota10_guid = 'g01b'") === '0', 'o pedido "gêmeo" de bonificação não vira pedido separado');
    ok(sql("select concat(bonificado, '|', total, '|', valor_bonificado, '|', cfop) from pedido_itens where nota10_guid = 'it03'") === '1|0.00|99.80|5910', 'item da bonificação: total 0, valor na nota, CFOP 5910');
    ok(ped('g02', 'situacao, situacao_origem') === 'faturado|sem_nf', 'sem NF e StatusPedido 5 (Despachado): faturado');
    ok(ped('g03', 'situacao, situacao_origem') === 'historico|em_separacao', 'sem NF e StatusPedido 4 (Em separação): só histórico');
    ok(ped('g04', 'situacao') === 'cancelado', 'NF cancelada: cancelado');
    ok(ped('g06', 'situacao, situacao_origem, emitente_cnpj') === 'outra_empresa|faturado|34773706000108', 'pedido de outra empresa do grupo: guardado, fora do faturamento');
    ok(ped('g08', 'situacao, numero_nf, valor_nf') === 'faturado|7001|150.00', 'NF de "Outras saídas" ligada ao pedido de venda (sem contar duas vezes)');
    ok(sql("select count(*) from pedidos where nota10_guid = 'g07'") === '0', 'a NF de saída ligada não vira outro pedido');
    // conferência do faturamento (V17.5): o mês conta pela DATA DO PEDIDO; "Outras saídas" ligam pelo nº do pedido + cliente
    ok(ped('g24', 'situacao, tipo_venda') === 'faturado|nf_avulsa', 'mesmo nº de pedido mas OUTRO cliente: não liga (continua venda avulsa)');
    ok(ped('g22', 'tipo_venda') === 'nf_avulsa', '(1º backup) saída sem nº de pedido e fora da regra dos 3 dias fica como venda avulsa');
    ok(sql("select round(sum(total), 2) from pedidos where situacao = 'faturado' and nota10_guid in ('g21') and criado_em >= '2026-10-01' and criado_em < '2026-11-01'") === '400.00'
      && sql("select date(faturado_em) from pedidos where nota10_guid = 'g21'") === '2026-11-03', 'pedido de 30/10 com NF de 03/11: conta em outubro (data do pedido) e guarda a data da NF');
    ok(ped('g10', 'situacao, tipo_operacao') === 'historico|orcamento' && ped('g11', 'situacao, tipo_operacao') === 'historico|devolucao', 'orçamento e devolução: histórico');
    ok(ped('g16', 'situacao, possivel_duplicado') === 'historico|1', 'duplicado exato: marcado e fora do faturamento');
    ok(sql("select count(*) from pedidos where nota10_guid in ('g12', 'g13', 'g14', 'g15')") === '0', 'entradas, ajustes de estoque, compras e notas sem cliente não viram pedido');
    ok(sql("select concat(fornecedor, '|', numero_nf, '|', valor_total) from compras_lancadas where nota10_guid = 'g14'") === 'Insumos Fornecedor SA|9001|800.00', 'compra entra em Compras da empresa');

    // produtos e títulos
    ok(sql("select concat(ativo, visivel_catalogo) from produtos where nota10_codigo = 'PA002'") === '00' && sql("select count(*) from produtos where codigo = 'PA001'") === '1', 'produto só do Nota10: inativo e fora do catálogo; o do One é ligado, não duplicado');
    ok(sql("select count(*) from materias_primas where nota10_codigo in ('INS01', 'ROT01')") === '2' && sql("select rotulo from materias_primas where nota10_codigo = 'ROT01'") === '1', 'insumos e rótulos viram matéria-prima');
    ok(sql("select preco from produto_precos pp join tabelas_preco t on t.id = pp.tabela_preco_id and t.slug = 'lojista' join produtos p on p.id = pp.produto_id and p.codigo = 'PA001'") === '49.90', 'tabela "Base" do Nota10 vira a Lojista');
    ok(sql("select concat(sum(situacao = 'aberto'), '|', sum(situacao = 'pago'), '|', sum(situacao = 'cancelado')) from titulos_receber") === '3|1|1', 'contas a receber importadas: 3 abertas, 1 paga, 1 cancelada (a pagar fica guardada)');
    ok(sql("select credito_situacao from clientes where nota10_numero = 108") === 'atraso', 'título vencido de outra empresa do grupo também bloqueia o boleto');

    // cobrança: os títulos vencidos viram casos na tela de Cobrança (um por cliente)
    ok(sql("select count(*) from cobrancas where origem = 'nota10' and situacao = 'aberta'") === '2', 'títulos vencidos viram 2 casos de cobrança (um por cliente)');
    ok(sql("select count(*) from cobranca_titulos where omie_codigo like 'n10:%'") === '2', 'cada título vencido entra no caso (o a vencer não entra)');
    await p.goto(BASE + '/cobranca');
    const cob = await p.locator('body').innerText();
    ok(cob.includes('Empório Importado') && cob.includes('Loja da Jeina'), 'os casos aparecem na tela de Cobrança');

    // o One usa o histórico: ficha e última compra
    const c101 = sql('select id from clientes where nota10_numero = 101');
    ok(sql(`select ultima_compra_em from clientes where id = ${c101}`) === '2026-11-05', 'última compra recalculada pelos pedidos do Nota10');
    await p.goto(BASE + '/carteira/clientes/' + c101);
    const ficha = await p.locator('body').innerText();
    ok(ficha.includes('Outra empresa do grupo') && ficha.includes('Histórico (Nota10)'), 'ficha mostra os pedidos antigos com as situações novas');
    ok((await p.locator('[data-titulos-cliente]').innerText()).includes('vencido'), 'ficha mostra os títulos do Nota10');

    // "Importar de novo" pela tela (mesmo backup): não duplica nem os casos de cobrança
    const casos = sql('select count(*) from cobrancas');
    await p.goto(BASE + '/sistema/nota10');
    await p.fill('[data-n10-reimportar] input[name=confirmar]', 'IMPORTAR');
    await Promise.all([p.waitForNavigation(), p.click('[data-n10-reimportar] button')]);
    await p.waitForSelector('[data-n10-importado]', { timeout: 120000 });
    ok(sql('select count(*) from cobrancas') === casos, '"Importar de novo" pela tela não repete os casos de cobrança');

    // de novo: não duplica
    const antes = sql('select concat((select count(*) from clientes), (select count(*) from pedidos), (select count(*) from pedido_itens), (select count(*) from titulos_receber))');
    execSync(`php -r 'require "app/bootstrap.php"; $id = (int) App\\Core\\Banco::valor("select max(id) from nota10_importacoes"); App\\Services\\Nota10\\Importador::iniciar($id, 1); do { $r = App\\Services\\Nota10\\Importador::parte($id, 2); } while (!$r["fim"]);'`);
    ok(sql('select concat((select count(*) from clientes), (select count(*) from pedidos), (select count(*) from pedido_itens), (select count(*) from titulos_receber))') === antes, 'rodar de novo não duplica nada');

    // depois de importado, "descartar" apaga só o arquivo; os dados do Nota10 ficam
    await p.goto(BASE + '/sistema/nota10');
    ok((await p.locator('[data-agora="itens"]').innerText()).includes('12') && (await p.locator('[data-agora="faturados"]').innerText()).includes('8'),
      'depois de rodar de novo, o relatório mostra o que está no One (não zera)');

    // backup da virada (lido e importado por cima): não duplica quem não tem CNPJ, atualiza pedido que mudou e completa o cadastro
    const totalClientes = sql('select count(*) from clientes');
    const c105 = sql('select id from clientes where nota10_numero = 105');
    fs.copyFileSync('tests/recursos/nota10_importacao_virada.sql', 'storage/nota10/dump-virada.txt');
    await p.goto(BASE + '/sistema/nota10');
    ok((await p.locator('[data-n10-envio] h2').innerText()).includes('backup novo'), 'depois de importar, dá para enviar um backup novo');
    await Promise.all([p.waitForNavigation(), p.locator('[data-n10-soltos] li', { hasText: 'dump-virada' }).locator('button').click()]);
    await p.click('[data-n10-ler]'); await p.waitForSelector('[data-n10-lido]', { timeout: 60000 });
    await p.fill('[data-n10-form-importar] input[name=confirmar]', 'IMPORTAR');
    await Promise.all([p.waitForNavigation(), p.click('[data-n10-form-importar] button')]);
    await p.waitForSelector('[data-n10-importado]', { timeout: 120000 });
    ok(sql('select count(*) from clientes') === totalClientes, 'backup novo: nenhum cliente duplicado (nem os sem CNPJ/CPF)');
    ok(sql("select concat(situacao, '|', numero_nf) from pedidos where nota10_guid = 'g03'") === 'faturado|5010', 'pedido que ganhou NF no Nota10 passa a faturado, com a NF');
    ok(sql("select count(*) from pedido_historico h join pedidos p on p.id = h.pedido_id where p.nota10_guid = 'g03' and h.texto like 'Atualizado pelo Nota10%'") === '1', 'a mudança fica no histórico do pedido');
    ok(sql("select cliente_id from pedidos where nota10_guid = 'g20'") === c105, 'pedido novo de loja sem CNPJ vai para a mesma loja');
    ok(ped('g22', 'situacao, situacao_origem') === 'historico|nf_de_pedido' && ped('g21', 'numero_nf') === '5021',
      '"Outras saídas" com o mesmo nº de pedido e cliente (6 dias depois): sai do faturamento, não conta em dobro');
    ok(sql("select count(*) from pedidos where situacao = 'faturado' and nota10_guid in ('g21', 'g22')") === '1', 'o pedido e a NF dele somam uma vez só');
    ok(sql("select pago_em is not null from cobranca_titulos where omie_codigo = 'n10:t01'") === '1', 'título pago no Nota10 recebe baixa no caso de cobrança');
    ok(sql("select count(*) from cobrancas where origem = 'nota10'") === '2', 'reimportar não abre caso de cobrança repetido');
    ok(sql(`select telefone from clientes where id = ${c105}`) !== 'NULL' && sql(`select telefone from clientes where id = ${c105}`) !== '', 'cadastro completado com o telefone que faltava');
    ok(sql("select telefone from clientes where nota10_numero = 101") === sql("select telefone from clientes where nota10_numero = 101"), 'o que já estava preenchido no One continua');

    await p.goto(BASE + '/sistema/nota10');
    const arquivo = sql('select arquivo from nota10_importacoes order by id desc limit 1');
    await Promise.all([p.waitForNavigation(), p.click('[data-n10-lido] form[action$="/descartar"] button')]);
    ok(sql("show tables like 'n10_clientes'") === 'n10_clientes' && !fs.existsSync('storage/nota10/' + arquivo), 'depois da importação, descartar apaga só o arquivo (a área n10_ fica)');
    ok(erros.length === 0, 'sem erros de JavaScript' + (erros.length ? ': ' + erros.join(' | ') : ''));
  } catch (e) { console.log('FALHA', e.message); falhas++; }
  await nav.close();
  console.log(falhas ? `\n${falhas} falha(s)` : '\nTudo certo.');
  process.exit(falhas ? 1 : 0);
})();
