// Teste automático (Playwright). Rodar todos: bash tests/rodar.sh  ·  só este: bash tests/rodar.sh zerar
// Administração › Zerar dados (V15.1): só o Admin abre; pede a senha e a palavra ZERAR; faz backup antes;
// apaga os dados do dia a dia (clientes, pedidos, cobrança, produção...) e MANTÉM usuários, produtos, catálogo,
// configurações, treinamento e e-commerce. Toda tabela do banco tem de estar classificada (apagar ou manter).
const { chromium } = require('playwright');
const { execSync } = require('child_process');
const fs = require('fs');
const BASE = 'http://127.0.0.1:8000';
let falhas = 0; const ok = (c, m) => { console.log((c ? 'OK   ' : 'FALHA') + ' ' + m); if (!c) falhas++; };
const sql = (q) => execSync(`mysql perfeita --default-character-set=utf8mb4 -N -e "${q.replace(/"/g, '\\"')}"`).toString().trim();
const listas = JSON.parse(execSync(`php -r 'require "app/bootstrap.php"; echo json_encode(["apagar" => array_merge(...array_values(App\\Services\\ZerarDadosService::APAGAR)), "manter" => App\\Services\\ZerarDadosService::MANTER]);'`).toString());
const contar = (tabelas) => Object.fromEntries(tabelas.map((t) => [t, Number(sql(`select count(*) from ${t}`) || 0)]));
const totalApagar = () => Object.values(contar(listas.apagar)).reduce((a, b) => a + b, 0);

(async () => {
  // dados que precisam sair (além da demonstração) e que precisam ficar
  sql("insert into materias_primas (nome) values ('MP de teste do zerar')");
  sql("insert into notificacoes (usuario_id, tipo, titulo, link) select id, 'teste', 'aviso de teste', '/' from usuarios where email='admin@demo.local'");
  sql("insert into configuracoes (chave, rotulo, valor) values ('teste.zerar', 'teste', 'fica') on duplicate key update valor='fica'");
  fs.mkdirSync('storage/clientes', { recursive: true }); fs.writeFileSync('storage/clientes/print-teste.png', 'x');
  fs.writeFileSync('storage/clientes/.htaccess', 'Require all denied\n');

  const tabelasBanco = sql("select table_name from information_schema.tables where table_schema='perfeita' and table_type='BASE TABLE'").split('\n');
  const semLista = tabelasBanco.filter((t) => !listas.apagar.includes(t) && !listas.manter.includes(t));
  ok(semLista.length === 0, 'toda tabela do banco está classificada em apagar ou manter' + (semLista.length ? ': falta ' + semLista.join(', ') : ''));
  ok(listas.apagar.every((t) => !listas.manter.includes(t)), 'nenhuma tabela está nas duas listas');
  ok(['usuarios', 'perfis', 'produtos', 'produto_precos', 'configuracoes', 'trein_progresso', 'ecommerce_pedidos', 'auditoria'].every((t) => listas.manter.includes(t)),
    'usuários, produtos, preços, configurações, treinamento, e-commerce e auditoria ficam');

  const manterAntes = contar(listas.manter);
  const apagarAntes = totalApagar();
  ok(apagarAntes > 0 && Number(sql("select count(*) from materias_primas")) >= 1, `há dados do dia a dia para apagar (${apagarAntes})`);

  const b = await chromium.launch(); const erros = [];
  try {
    const entrar = async (email) => {
      const p = await (await b.newContext()).newPage(); p.on('pageerror', (e) => erros.push(e.message)); p.on('dialog', (d) => d.accept());
      await p.goto(BASE + '/entrar'); await p.fill('#email', email); await p.fill('#senha', 'Demo@2026'); await p.click('button[type=submit]'); await p.waitForLoadState();
      return p;
    };

    // Só o Admin
    for (const email of ['televendas1@demo.local', 'gestor@demo.local']) {
      const v = await entrar(email);
      const r1 = await v.goto(BASE + '/sistema/zerar');
      const r2 = await v.request.post(BASE + '/sistema/zerar', { form: { senha: 'Demo@2026', confirmar: 'ZERAR', _csrf: await v.locator('meta[name=csrf]').getAttribute('content') } });
      ok(r1.status() === 403 && r2.status() === 403 && totalApagar() === apagarAntes, `${email.split('@')[0]} não abre nem zera (403)`);
    }

    const a = await entrar('admin@demo.local');
    await a.goto(BASE + '/administracao');
    ok(await a.locator('a[href$="/sistema/zerar"]').count() > 0, 'Administração mostra o cartão "Zerar dados" para o Admin');
    await a.goto(BASE + '/sistema/zerar');
    const tela = await a.locator('main').innerText();
    ok(tela.includes('Carteira e clientes') && tela.includes('Produção e matérias-primas') && tela.includes('Usuários: logins'), 'tela mostra o que é apagado (com quantidades) e o que continua');
    ok(!tela.includes('ainda não sabe classificar'), 'nenhuma tabela sem classificação na tela');

    const enviar = async (senha, frase) => {
      await a.goto(BASE + '/sistema/zerar');
      await a.fill('input[name=senha]', senha); await a.fill('input[name=confirmar]', frase);
      await Promise.all([a.waitForNavigation(), a.click('[data-zerar-botao]')]);
      return a.locator('.aviso').first().innerText();
    };
    ok((await enviar('senha-errada', 'ZERAR')).includes('Senha incorreta') && totalApagar() === apagarAntes, 'senha errada: nada é apagado');
    ok((await enviar('Demo@2026', 'zerar já')).includes('digite ZERAR') && totalApagar() === apagarAntes, 'sem a palavra ZERAR: nada é apagado');

    const backupsAntes = fs.existsSync('storage/backups') ? fs.readdirSync('storage/backups').filter((f) => f.startsWith('one-antes-de-zerar-')).length : 0;
    const msg = await enviar('Demo@2026', 'zerar');
    ok(msg.includes('registro(s)') && await a.locator('[data-zerado]').count() === 1, 'senha + ZERAR: zera e mostra o backup feito antes');

    const apagarDepois = contar(listas.apagar);
    const sobrou = Object.entries(apagarDepois).filter(([, n]) => n > 0);
    ok(sobrou.length === 0, 'todos os dados do dia a dia foram apagados' + (sobrou.length ? ': sobrou ' + sobrou.map(([t, n]) => `${t}=${n}`).join(', ') : ''));
    const manterDepois = contar(listas.manter);
    const mudou = listas.manter.filter((t) => t !== 'auditoria' && manterDepois[t] !== manterAntes[t]);
    ok(mudou.length === 0, 'usuários, produtos, catálogo, configurações e treinamento continuam iguais' + (mudou.length ? ': mudou ' + mudou.join(', ') : ''));
    ok(sql("select valor from configuracoes where chave='teste.zerar'") === 'fica', 'configurações continuam');

    const backups = fs.readdirSync('storage/backups').filter((f) => f.startsWith('one-antes-de-zerar-'));
    ok(backups.length === backupsAntes + 1, 'backup completo feito antes de apagar (storage/backups)');
    const conteudo = execSync(`gunzip -c "storage/backups/${backups.sort().pop()}"`, { maxBuffer: 512 * 1024 * 1024 }).toString();
    ok(conteudo.includes('INSERT INTO `materias_primas`') && conteudo.includes('MP de teste do zerar'), 'o backup tem os dados que foram apagados');

    ok(!fs.existsSync('storage/clientes/print-teste.png') && fs.existsSync('storage/clientes/.htaccess'), 'anexos apagados; a proteção da pasta (.htaccess) fica');
    ok(sql("select count(*) from auditoria where acao='zerar_dados'") === '1', 'auditoria registra quem zerou e quando');

    await a.goto(BASE + '/sistema/zerar');
    ok(await a.locator('[data-zerar-vazio]').count() === 1, 'depois de zerar a tela diz que o One já está limpo');
    for (const tela of ['/', '/carteira', '/pedidos', '/producao', '/producao/materias', '/cobranca', '/produtos', '/formulas', '/desenvolvimento', '/compras', '/marketing']) {
      const r = await a.goto(BASE + tela);
      ok(r.status() === 200, `${tela} abre normalmente com o One vazio`);
    }
    const v = await entrar('televendas1@demo.local');
    ok(!v.url().includes('/entrar'), 'a equipe continua entrando com o mesmo login');
    ok(erros.length === 0, 'sem erros de JavaScript' + (erros.length ? ': ' + erros[0] : ''));
  } finally {
    await b.close();
    sql("delete from configuracoes where chave='teste.zerar'");
  }
  process.exit(falhas ? 1 : 0);
})();
