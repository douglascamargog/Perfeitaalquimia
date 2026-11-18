// V16.2 — Importar Nota10, etapa 1: enviar o backup (.sql, .zip ou arquivo na pasta), ler em partes para a área separada (n10_),
// conferir e guardar as ligações. NADA muda nos dados do One. Também: a ficha do cliente com os dados do ERP.
// Usa tests/recursos/nota10_amostra.sql (estrutura real do Nota10, dados inventados). Rodar com o banco de TESTE.
const { chromium } = require('playwright');
const { execSync } = require('child_process');
const fs = require('fs');
const path = require('path');
const BASE = 'http://127.0.0.1:8000';
let falhas = 0; const ok = (c, m) => { console.log((c ? 'OK   ' : 'FALHA') + ' ' + m); if (!c) falhas++; };
const sql = (q) => execSync(`mysql perfeita --default-character-set=utf8mb4 -N -e "${q.replace(/"/g, '\\"')}"`).toString().trim();
const AMOSTRA = path.resolve('tests/recursos/nota10_amostra.sql');
const TMP = fs.mkdtempSync('/tmp/one-n10-');

(async () => {
  // começa sem nenhuma importação anterior
  sql('delete from nota10_importacoes; delete from nota10_mapas');
  for (const t of sql("show tables like 'n10\\\\_%'").split('\n').filter(Boolean)) sql(`drop table ${t}`);
  fs.mkdirSync('storage/nota10', { recursive: true });
  for (const f of fs.readdirSync('storage/nota10')) if (/\.(sql|zip|gz)$/.test(f)) fs.unlinkSync('storage/nota10/' + f);
  const antes = sql('select concat((select count(*) from clientes), "/", (select count(*) from pedidos), "/", (select count(*) from produtos))');

  const nav = await chromium.launch(); const erros = [];
  const entrar = async (email) => {
    const ctx = await nav.newContext();
    await ctx.route('**/chart.js@4.4.1/**', (r) => r.fulfill({ path: 'tests/recursos/chart.umd.js', contentType: 'application/javascript' }));
    const p = await ctx.newPage(); p.on('pageerror', (e) => erros.push(email + ': ' + e.message)); p.on('dialog', (d) => d.accept());
    await p.goto(BASE + '/entrar'); await p.fill('#email', email); await p.fill('#senha', 'Demo@2026'); await p.click('button[type=submit]'); await p.waitForLoadState();
    return p;
  };
  const lerTudo = async (p) => {
    await p.click('[data-n10-ler]');
    await p.waitForSelector('[data-n10-lido]', { timeout: 60000 });
  };
  try {
    const v = await entrar('televendas1@demo.local');
    ok((await v.goto(BASE + '/sistema/nota10')).status() === 403, 'só o Admin abre a importação do Nota10');

    const a = await entrar('admin@demo.local');
    await a.goto(BASE + '/sistema/nota10');
    ok(await a.locator('[data-n10-envio]').isVisible(), 'tela começa pedindo o backup');

    // 1) envio de .zip
    const zip = path.join(TMP, 'backup-nota10.zip');
    execSync(`python3 -c "import zipfile,sys; z=zipfile.ZipFile(sys.argv[1],'w',zipfile.ZIP_DEFLATED); z.write(sys.argv[2],'nota10.sql'); z.close()" ${zip} ${AMOSTRA}`);
    await a.setInputFiles('input[name=backup]', zip);
    await Promise.all([a.waitForNavigation(), a.click('[data-n10-envio] button[type=submit]')]);
    ok(await a.locator('[data-n10-leitura]').isVisible(), 'backup .zip recebido e aberto (vira .sql no servidor)');
    await lerTudo(a);
    ok(sql("select situacao from nota10_importacoes order by id desc limit 1") === 'lido', 'leitura em partes até o fim');
    ok(Number(sql('select count(*) from n10_clientes')) === 6 && Number(sql('select count(*) from n10_notas')) === 5, 'área separada com os clientes e pedidos do backup');
    ok(sql("show tables like 'n10_comandas'") === '', 'tabelas que o One não usa (PDV) são puladas');
    ok(sql("select Nome from n10_clientes where Numero=103") === "D'Ávila Naturais", 'acentos e apóstrofo preservados');
    ok(sql("select Obs from n10_clientes where Numero=101") === 'Entregar pela manhã;', 'texto com ponto e vírgula no fim não quebra a leitura');

    // 2) conferência
    const kpi = (k) => a.locator(`[data-n10="${k}"]`).innerText();
    ok((await kpi('clientes')) === '6' && (await kpi('notas')) === '5' && (await kpi('itens')) === '4', 'números: 6 cadastros, 5 pedidos, 4 itens');
    ok((await kpi('abertos')) === '1', 'títulos a receber em aberto (o título a pagar não conta)');
    const conferir = await a.locator('[data-n10-problemas]').innerText();
    ok(conferir.includes('1 CNPJ(s) aparecem em mais de um cadastro'), 'avisa CNPJ repetido');
    ok(conferir.includes('1 cliente(s) sem vendedor'), 'sem vendedor conta só clientes (não fornecedor nem transportadora)');
    ok(conferir.includes('de cliente que não está no cadastro'), 'avisa pedido de cliente fora do cadastro');
    const ops = await a.locator('[data-n10-operacoes]').innerText();
    ok(ops.includes('Venda') && ops.includes('R$ 3.050,00') && ops.includes('Bonificação'), 'separa venda de bonificação pelo CFOP (cancelado fica de fora)');
    ok(await a.evaluate(() => !!(window.Chart && Chart.getChart(document.getElementById('n10-grafico-anos')))), 'gráfico de pedidos por ano');
    ok((await a.locator('[data-n10-tipos] select[name="mapa[tipo_cadastro][F]"]').inputValue()) === 'fornecedor', 'palpite: tipo F vira fornecedor');
    ok((await a.locator('select[name="mapa[forma][1]"] option:checked').innerText()).includes('Boleto'), 'palpite: "BOLETO 28 DD" liga com Boleto');
    ok((await a.locator('select[name="mapa[segmento][7]"] option:checked').innerText()).includes('Farm'), 'segmento com nome da tabela de apoio e palpite Farmácia');
    ok((await a.locator('[data-n10-vendedores]').innerText()).includes('MARIA VENDAS'), 'vendedores com o nome do Nota10');

    // 3) guardar as ligações
    const tv1 = sql("select id from usuarios where email='televendas1@demo.local'");
    await a.selectOption('select[name="mapa[vendedor][1]"]', tv1);
    await a.selectOption('select[name="mapa[tipo_cadastro][T]"]', 'ignorar');
    await Promise.all([a.waitForNavigation(), a.click('[data-n10-salvar]')]);
    ok(sql("select destino from nota10_mapas where tipo='vendedor' and codigo='1'") === tv1, 'ligação do vendedor guardada');
    ok((await a.locator('select[name="mapa[vendedor][1]"]').inputValue()) === tv1 && (await a.locator('select[name="mapa[tipo_cadastro][T]"]').inputValue()) === 'ignorar', 'a tela volta com as escolhas guardadas');

    ok(sql('select concat((select count(*) from clientes), "/", (select count(*) from pedidos), "/", (select count(*) from produtos))') === antes, 'nada mudou nos clientes, pedidos e produtos do One');

    // 4) descartar apaga o backup e a área separada
    await Promise.all([a.waitForNavigation(), a.click('[data-n10-lido] form[action$="/descartar"] button')]);
    ok(sql("show tables like 'n10\\\\_%'") === '' && fs.readdirSync('storage/nota10').filter((f) => f.endsWith('.sql')).length === 0, 'descartar apaga a área separada e o arquivo do servidor');

    // 5) arquivo colocado direto na pasta (FTP / cPanel), em .sql
    fs.copyFileSync(AMOSTRA, 'storage/nota10/enviado-por-ftp.sql');
    await a.goto(BASE + '/sistema/nota10');
    ok((await a.locator('[data-n10-soltos]').innerText()).includes('enviado-por-ftp.sql'), 'mostra o arquivo que está na pasta storage/nota10');
    await Promise.all([a.waitForNavigation(), a.click('[data-n10-soltos] button')]);
    await lerTudo(a);
    ok((await a.locator('[data-n10="clientes"]').innerText()) === '6', 'arquivo da pasta lido e conferido');
    ok(fs.readFileSync('storage/nota10/.htaccess', 'utf8').includes('Require all denied'), 'pasta do backup protegida contra acesso pela web');

    // 6) backup automático e "Zerar dados" ignoram a área separada
    const lista = execSync(`php -r 'require "app/bootstrap.php"; echo json_encode(App\\Services\\ZerarDadosService::semClassificacao());'`).toString();
    ok(lista === '[]', 'Zerar dados: tabelas novas classificadas e área n10_ ignorada' + (lista !== '[]' ? ' ' + lista : ''));

    // 7) ficha do cliente com o que vem do ERP
    const cli = sql("select id from clientes where excluido_em is null and responsavel_id = " + tv1 + " order by id limit 1");
    sql(`update clientes set inscricao_estadual='0961234567', limite_credito=5000, email_nfe='nfe@loja.test', end_numero='120', entrega_endereco='Av. Entrega', entrega_cidade='Canoas', entrega_uf='RS' where id=${cli}`);
    sql(`insert into cliente_contatos (cliente_id, nome, cargo, celular, aniversario_dia, aniversario_mes, origem) values (${cli}, 'Ana Compras', 'compradora', '51999991111', 15, 3, 'nota10')`);
    sql(`insert into titulos_receber (cliente_id, documento, parcela, vencimento, valor, situacao, nota10_guid) values (${cli}, '12002', '1/2', CURDATE() - INTERVAL 10 DAY, 2000, 'aberto', 'teste-n10-1'), (${cli}, '12001', '1/1', '2024-06-07', 1000, 'pago', 'teste-n10-2')`);
    sql(`update titulos_receber set pago_em='2024-06-10' where nota10_guid='teste-n10-2'`);
    await v.goto(BASE + '/carteira/clientes/' + cli);
    const cad = await v.locator('[data-cadastro-erp]').innerText();
    ok(cad.includes('0961234567') && cad.includes('R$ 5.000,00') && cad.includes('nfe@loja.test') && cad.includes('Canoas'), 'ficha: cadastro completo (IE, limite, e-mail da NF-e, entrega)');
    ok((await v.locator('[data-contatos-loja]').innerText()).includes('Ana Compras') && (await v.locator('[data-contatos-loja]').innerText()).includes('15/03'), 'ficha: pessoas da loja com aniversário');
    const tit = await v.locator('[data-titulos-cliente]').innerText();
    ok(tit.includes('1 vencido(s)') && tit.includes('vencido') && tit.includes('3 dia(s) depois'), 'ficha: títulos com vencido e atraso no pagamento');
    const outro = sql("select id from clientes where excluido_em is null and id <> " + cli + " and id not in (select cliente_id from cliente_contatos) order by id limit 1");
    await v.goto(BASE + '/carteira/clientes/' + outro).catch(() => {});
    ok((await v.locator('[data-cadastro-erp], [data-contatos-loja], [data-titulos-cliente]').count()) === 0, 'loja sem dados do ERP não mostra blocos vazios');

    ok(erros.length === 0, 'sem erros de JavaScript' + (erros.length ? ': ' + erros.join(' | ') : ''));
  } catch (e) { console.log('FALHA', e.message); falhas++; }
  await nav.close();
  // limpa o que o teste deixou
  sql("delete from titulos_receber where nota10_guid like 'teste-n10-%'; delete from cliente_contatos where nome='Ana Compras'");
  console.log(falhas ? `\n${falhas} falha(s)` : '\nTudo certo.');
  process.exit(falhas ? 1 : 0);
})();
