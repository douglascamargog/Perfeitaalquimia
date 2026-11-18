// Teste automático (Playwright). Rodar todos: bash tests/rodar.sh  ·  só este: bash tests/rodar.sh geoapify
// V15.5: mapas pela Geoapify (sem pré-pagamento), contra um Geoapify SIMULADO (tests/recursos/geoapify_simulado.php):
// Administração › Mapas (escolher o serviço, chave guardada sem aparecer de novo, Testar, Localizar agora),
// ponto exato das lojas (só prédio/rua na mesma cidade), e no Planejador de visitas: trajeto pelas estradas,
// km e tempo, melhor ordem das visitas de cada dia, e volta para linha reta sem a chave.
const { chromium } = require('playwright');
const { execSync, spawn } = require('child_process');
const fs = require('fs');
const BASE = 'http://127.0.0.1:8000';
let falhas = 0; const ok = (c, m) => { console.log((c ? 'OK   ' : 'FALHA') + ' ' + m); if (!c) falhas++; };
const sql = (q) => execSync(`mysql perfeita --default-character-set=utf8mb4 -N -e "${q.replace(/"/g, '\\"')}"`).toString().trim();
const SEG = 'config/segredos.php';
const CHAVE = 'GeoTesteChave1234567890abcd';

(async () => {
  const simulado = spawn('php', ['-S', '127.0.0.1:8096', 'tests/recursos/geoapify_simulado.php'], { stdio: 'ignore' });
  await new Promise((r) => setTimeout(r, 700));
  const antes = fs.existsSync(SEG) ? fs.readFileSync(SEG, 'utf8') : null;
  fs.writeFileSync(SEG, "<?php\nreturn ['integracoes' => ['anthropic' => ['chave' => 'sk-ant-preservada'], 'geoapify' => ['url' => 'http://127.0.0.1:8096/v1']]];\n");
  sql("update configuracoes set valor='geoapify' where chave='mapas.provedor'");
  const tv1 = sql("select id from usuarios where email='televendas1@demo.local'");
  // Lojas com endereço (para localizar) — uma não existe, outra cai em outra cidade, outra só acha a cidade
  const loja = (nome, endereco, cidade) => {
    sql(`insert into clientes (tipo, razao_social, nome_fantasia, endereco, bairro, cidade, uf, responsavel_id, etapa_id) values ('cliente', '${nome} Ltda', '${nome}', '${endereco}', 'Centro', '${cidade}', 'RS', ${tv1}, (select min(id) from funil_etapas))`);
    return sql(`select max(id) from clientes where nome_fantasia='${nome}'`);
  };
  const ok1 = loja('Geo Loja Um', 'Rua Sinimbu, 1500', 'Caxias do Sul');
  const ok2 = loja('Geo Loja Dois', 'Av. Júlio de Castilhos, 200', 'Caxias do Sul');
  const nao = loja('Geo Loja Três', 'Rua Inexistente, 1', 'Caxias do Sul');
  const outra = loja('Geo Loja Quatro', 'Rua Errada, 10', 'Caxias do Sul');
  const cidadeSo = loja('Geo Loja Cinco', 'SoCidade, 5', 'Caxias do Sul');
  sql(`update clientes set lat=null, lng=null, geo_hash=null, geo_precisao=null where id in (${ok1},${ok2},${nao},${outra},${cidadeSo})`);
  // as outras lojas do banco de teste já contam como localizadas: o "Localizar agora" trabalha só nas deste teste
  sql(`update clientes c set c.geo_hash = SHA1(CONCAT_WS('|', COALESCE(c.endereco, ''), COALESCE(c.bairro, ''), COALESCE(c.cep, ''), COALESCE(c.cidade, ''), COALESCE(c.uf, ''))) where c.id not in (${ok1},${ok2},${nao},${outra},${cidadeSo})`);

  const b = await chromium.launch(); const erros = [];
  const entrar = async (email) => {
    const ctx = await b.newContext();
    await ctx.route('**/maplibre-gl@*/dist/maplibre-gl.js', (r) => r.fulfill({ path: 'tests/recursos/maplibre-gl.js' }));
    await ctx.route('**/maplibre-gl@*/dist/maplibre-gl.css', (r) => r.fulfill({ path: 'tests/recursos/maplibre-gl.css' }));
    await ctx.route('https://tiles.openfreemap.org/**', (r) => r.fulfill({ status: 200, contentType: 'application/json', body: '{"version":8,"sources":{},"layers":[]}' }));
    const p = await ctx.newPage(); p.on('pageerror', (e) => erros.push(e.message)); p.on('dialog', (d) => d.accept());
    await p.goto(BASE + '/entrar'); await p.fill('#email', email); await p.fill('#senha', 'Demo@2026'); await p.click('button[type=submit]'); await p.waitForLoadState();
    return p;
  };
  const aviso = (p) => p.locator('.aviso').first().innerText();
  try {
    const a = await entrar('admin@demo.local');
    await a.goto(BASE + '/administracao#google-maps');
    ok(await a.locator('[data-mapas] input[value=geoapify]').isChecked(), 'Administração › Mapas: Geoapify é o serviço em uso');
    const guardar = async (chave) => { await a.fill('[data-geoapify-chave]', chave); await Promise.all([a.waitForNavigation(), a.click('[data-form-geoapify] button:has-text("Guardar")')]); return aviso(a); };
    ok((await guardar('chave com espaço')).includes('não parece certa'), 'chave em formato errado: recusada com explicação');
    await guardar('ChaveErradaQualquer123456789');
    await Promise.all([a.waitForNavigation(), a.click('[data-google-testar]')]);
    ok((await aviso(a)).includes('recusou a chave'), 'chave errada: "Testar" explica em português');
    await guardar(CHAVE);
    const seg = fs.readFileSync(SEG, 'utf8');
    ok(seg.includes(CHAVE) && seg.includes('sk-ant-preservada') && (fs.statSync(SEG).mode & 0o777) === 0o600, 'chave guardada em config/segredos.php (600), sem apagar a da IA');
    ok(!(await a.content()).includes(CHAVE), 'a tela nunca mostra a chave de novo');
    await Promise.all([a.waitForNavigation(), a.click('[data-google-testar]')]);
    ok((await aviso(a)).includes('Chave funcionando'), '"Testar" com a chave certa: funcionando');

    // Localizar agora
    await Promise.all([a.waitForNavigation({ timeout: 90000 }), a.click('[data-google-localizar]')]);
    const geo = (id) => sql(`select concat(coalesce(geo_precisao,'-'), '|', lat is not null) from clientes where id=${id}`);
    ok(['telhado|1', 'rua|1'].includes(geo(ok1)) && ['telhado|1', 'rua|1'].includes(geo(ok2)), 'lojas com endereço achado ficam no ponto exato (prédio ou rua)');
    ok(geo(nao) === 'nao_encontrado|0' && geo(outra) === 'nao_encontrado|0' && geo(cidadeSo) === 'nao_encontrado|0',
      'endereço não achado, achado em outra cidade ou só a cidade: a loja continua aproximada');

    // Trocar o serviço
    await Promise.all([a.waitForNavigation(), a.check('[data-mapas] input[value=google]')]);
    ok(sql("select valor from configuracoes where chave='mapas.provedor'") === 'google' && await a.locator('[data-google-chave]').count() === 1, 'dá para trocar para o Google (mostra o campo da chave do Google)');
    await Promise.all([a.waitForNavigation(), a.check('[data-mapas] input[value=geoapify]')]);
    ok(sql("select valor from configuracoes where chave='mapas.provedor'") === 'geoapify', '...e voltar para a Geoapify');

    // Planejador de visitas com rotas reais
    const e = await entrar('externo1@demo.local');
    await e.goto(BASE + '/roteiros/novo');
    await e.fill('input[name=nome]', 'Serra pela estrada');
    await e.fill('input[name=data_inicio]', sql('select CURDATE() + INTERVAL 1 DAY')); await e.fill('input[name=data_fim]', sql('select CURDATE() + INTERVAL 2 DAY'));
    await e.fill('input[name=raio_km]', '40'); await e.fill('input[name=visitas_dia]', '2');
    const cid = e.locator('input[name="cidades[]"]');
    await cid.nth(0).fill('Porto Alegre/RS'); await cid.nth(1).fill('Caxias do Sul/RS');
    await Promise.all([e.waitForNavigation(), e.click('[data-form-roteiro] button[type=submit]')]);
    const rid = sql("select max(id) from roteiros where nome='Serra pela estrada'");
    const r = sql(`select concat(json_length(trajeto_json), '|', km_total > 50, '|', minutos_total > 30, '|', coalesce(rota_aviso, '')) from roteiros where id=${rid}`);
    ok(r.startsWith('11|1|1|'), 'roteiro com o trajeto pelas estradas, km e tempo (uma consulta à Geoapify)' + (r.startsWith('11|1|1|') ? '' : ': ' + r));
    // inclui as lojas localizadas + outras duas do caminho
    await e.goto(BASE + `/roteiros/${rid}?aba=caminho&so=todas`);
    for (const id of [ok1, ok2]) { if (await e.locator(`[data-loja-caminho="${id}"] input`).count()) await e.locator(`[data-loja-caminho="${id}"] input`).check(); }
    const marcadas = await e.locator('[data-form-caminho] input[name="clientes[]"]:checked').count();
    if (marcadas) await Promise.all([e.waitForNavigation(), e.click('[data-incluir-marcadas]')]);
    ok(marcadas >= 2 && Number(sql(`select count(*) from roteiro_paradas where roteiro_id=${rid}`)) >= 2, 'lojas no caminho medidas pelo trajeto real e incluídas no roteiro');
    const dias = JSON.parse(sql(`select coalesce(dias_json, '{}') from roteiros where id=${rid}`));
    ok(Object.keys(dias).length >= 1 && Object.values(dias).every((d) => d.km > 0 && d.minutos > 0), 'melhor ordem de cada dia calculada, com km e tempo do dia');
    await e.goto(BASE + `/roteiros/${rid}`);
    ok(await e.locator('[data-km-total]').count() === 1 && await e.locator('[data-km-dia]').count() >= 1 && (await e.locator('main').innerText()).includes('Powered by Geoapify'),
      'tela mostra km/tempo do trajeto e de cada dia, com a menção "Powered by Geoapify"');

    // Sem a chave: volta para linha reta, com aviso
    await a.goto(BASE + '/administracao#google-maps');
    await Promise.all([a.waitForNavigation(), a.click('[data-form-geoapify] button:has-text("Remover")')]);
    await e.goto(BASE + `/roteiros/${rid}/editar`);
    await Promise.all([e.waitForNavigation(), e.click('[data-form-roteiro] button[type=submit]')]);
    ok(sql(`select trajeto_json is null from roteiros where id=${rid}`) === '1' && (await e.locator('[data-rota-aviso]').innerText()).includes('linha reta'), 'sem a chave: o roteiro continua funcionando em linha reta, com aviso');

    // Só quem administra grava a chave
    const v = await entrar('televendas1@demo.local');
    const resp = await v.request.post(BASE + '/administracao/geoapify', { form: { chave: 'InvasorChave123456789012345', _csrf: await v.locator('meta[name=csrf]').getAttribute('content') } });
    ok(resp.status() === 403 && !fs.readFileSync(SEG, 'utf8').includes('Invasor'), 'vendedora não consegue gravar a chave (403)');
    ok(sql("select count(*) from auditoria where acao='geoapify'") >= '2' && sql(`select count(*) from auditoria where depois_json like '%${CHAVE}%'`) === '0', 'auditoria registra sem guardar a chave');
    ok(erros.length === 0, 'sem erros de JavaScript' + (erros.length ? ': ' + erros[0] : ''));
  } finally {
    await b.close(); simulado.kill();
    if (antes === null) { try { fs.unlinkSync(SEG); } catch { /* ok */ } } else fs.writeFileSync(SEG, antes);
  }
  process.exit(falhas ? 1 : 0);
})();
