// Teste automático (Playwright). Rodar todos: bash tests/rodar.sh  ·  só este: node tests/teste_localizacao.js
// Localização exata das lojas (V14.2): chave do Google Maps na Administração (segredos.php, nunca mostrada),
// "Testar" e "Localizar agora" contra um Google SIMULADO; endereço não achado ou de outra cidade fica aproximado;
// endereço mudado é localizado de novo; ajuste à mão na ficha (e o automático não mexe mais); permissões.
const { chromium } = require('playwright');
const { execSync, spawn } = require('child_process');
const fs = require('fs');
const BASE = 'http://127.0.0.1:8000';
let falhas = 0; const ok = (c, m) => { console.log((c ? 'OK   ' : 'FALHA') + ' ' + m); if (!c) falhas++; };
const sql = (q) => execSync(`mysql perfeita --default-character-set=utf8mb4 -N -e "${q.replace(/"/g, '\\"')}"`).toString().trim();
const SEG = 'config/segredos.php';
const CHAVE = 'AIzaChaveDeTesteCerta1234567890';

const entrar = async (b, email) => {
  const p = await (await b.newContext()).newPage(); p.erros = []; p.on('pageerror', (e) => p.erros.push(e.message)); p.on('dialog', (d) => d.accept());
  await p.route(/open-meteo|jsdelivr|openfreemap/, (r) => r.abort());
  await p.goto(BASE + '/entrar'); await p.fill('#email', email); await p.fill('#senha', 'Demo@2026'); await p.click('button[type=submit]'); await p.waitForLoadState();
  if (await p.locator('[data-novidades-ok]').count()) { await p.click('[data-novidades-ok]'); await p.waitForLoadState(); }
  return p;
};

(async () => {
  const simulado = spawn('php', ['-S', '127.0.0.1:8095', 'tests/recursos/google_simulado.php'], { stdio: 'ignore' });
  await new Promise((r) => setTimeout(r, 700));
  const antes = fs.existsSync(SEG) ? fs.readFileSync(SEG, 'utf8') : null;
  fs.writeFileSync(SEG, "<?php\nreturn ['integracoes' => ['anthropic' => ['chave' => 'sk-ant-preservada'], 'google' => ['geocode_url' => 'http://127.0.0.1:8095/geocode/json']]];\n");
  sql("update configuracoes set valor='20' where chave='carteira.geo_por_rodada'");
  // Duas lojas especiais: endereço que o Google não acha e endereço que cai em outra cidade
  const [c1, c2, c3] = sql("select id from clientes where tipo<>'consumidor' and endereco<>'' and excluido_em is null order by id limit 3").split('\n');
  sql(`update clientes set endereco='Rua Inexistente 123' where id=${c1}`);
  sql(`update clientes set endereco='Rua Errada 45' where id=${c2}`);
  const b = await chromium.launch(); const paginas = [];
  try {
    const a = await entrar(b, 'admin@demo.local'); paginas.push(a);
    await a.goto(BASE + '/administracao#google-maps');
    ok(await a.locator('[data-google-maps]').isVisible() && (await a.locator('[data-geo-resumo]').innerText()).startsWith('0 de'), 'Administração mostra o Google Maps: nenhuma loja no ponto exato ainda');
    const guardar = async (chave) => { await a.fill('[data-google-chave]', chave); await Promise.all([a.waitForNavigation(), a.click('[data-google-maps] button:has-text("Guardar")')]); return a.locator('.aviso').first().innerText(); };
    ok((await guardar('chave com espaço')).includes('não parece certa'), 'chave com formato estranho é recusada');
    await guardar('AIzaChaveErradaErradaErrada000');
    await Promise.all([a.waitForNavigation(), a.click('[data-google-testar]')]);
    ok((await a.locator('.aviso').first().innerText()).includes('recusou a chave'), 'chave errada: "Testar" explica em português');
    await guardar(CHAVE);
    const seg = fs.readFileSync(SEG, 'utf8');
    ok(seg.includes(CHAVE) && seg.includes('sk-ant-preservada') && seg.includes('8095') && (fs.statSync(SEG).mode & 0o777) === 0o600, 'chave guardada em config/segredos.php (600), sem apagar as outras');
    ok(!(await a.content()).includes(CHAVE) && (await a.content()).includes('…7890'), 'a tela nunca mostra a chave de novo');
    await Promise.all([a.waitForNavigation(), a.click('[data-google-testar]')]);
    ok((await a.locator('.aviso').first().innerText()).includes('Chave funcionando'), 'chave certa: "Testar" localiza o endereço de teste');

    // Localizar agora: 20 por rodada
    await Promise.all([a.waitForNavigation(), a.click('[data-google-localizar]')]);
    const aviso = await a.locator('.aviso').first().innerText();
    ok(aviso.includes('18 loja(s) localizada(s)') && aviso.includes('2 sem endereço preciso'), '"Localizar agora": 18 no endereço, 2 sem endereço preciso');
    ok(sql(`select concat(lat is null,'|',geo_precisao) from clientes where id=${c1}`) === '1|nao_encontrado', 'endereço que o Google não acha: continua aproximado na cidade');
    ok(sql(`select concat(lat is null,'|',geo_precisao) from clientes where id=${c2}`) === '1|nao_encontrado', 'endereço achado em OUTRA cidade não é aceito');
    ok(sql(`select geo_precisao from clientes where id=${c3}`).match(/telhado|rua/) && sql(`select lat is not null from clientes where id=${c3}`) === '1', 'loja localizada ganha o ponto exato');
    const pendentesAntes = Number(sql("select count(*) from clientes c where c.excluido_em is null and c.tipo<>'consumidor' and c.geo_manual=0 and coalesce(c.endereco,'')<>'' and c.geo_hash is null"));
    await Promise.all([a.waitForNavigation(), a.click('[data-google-localizar]')]);
    ok(Number(sql("select count(*) from clientes c where c.excluido_em is null and c.tipo<>'consumidor' and c.geo_manual=0 and coalesce(c.endereco,'')<>'' and c.geo_hash is null")) === pendentesAntes - 20,
      'a rodada seguinte continua de onde parou (não repete as já localizadas)');

    // Endereço mudou → localiza de novo
    sql(`update clientes set endereco='Rua Nova Achável 99' where id=${c1}`);
    const pend = await (async () => { await a.goto(BASE + '/administracao#google-maps'); return a.locator('[data-geo-resumo]').innerText(); })();
    await Promise.all([a.waitForNavigation(), a.click('[data-google-localizar]')]);
    ok(sql(`select lat is not null from clientes where id=${c1}`) === '1', 'endereço corrigido: o One localiza de novo sozinho');

    // Mapa da Carteira: ponto exato
    const mapa = await (await a.request.get(BASE + '/carteira/mapa.json?nivel=ponto')).json();
    const item = mapa.itens.find((i) => String(i.id) === String(c3));
    ok(item && Number(item.exato) === 1 && Math.abs(Number(item.lat) - Number(sql(`select lat from clientes where id=${c3}`))) < 0.000001, 'mapa da Carteira usa o ponto exato da loja');

    // Ajuste à mão pela ficha
    const resp = sql(`select responsavel_id from clientes where id=${c3}`);
    const emailVend = sql(`select email from usuarios where id=${resp}`);
    const v = await entrar(b, emailVend); paginas.push(v);
    await v.goto(BASE + `/carteira/clientes/${c3}/localizacao`);
    ok(await v.locator('[data-form-localizacao]').isVisible(), 'a vendedora abre "Ajustar no mapa" de uma loja da carteira dela');
    await v.evaluate(() => { document.querySelector('[data-campo-lat]').value = '-29.912345'; document.querySelector('[data-campo-lng]').value = '-51.187654'; });
    await Promise.all([v.waitForNavigation(), v.click('[data-salvar-local]')]);
    ok(sql(`select concat(lat,'|',lng,'|',geo_manual,'|',geo_precisao) from clientes where id=${c3}`) === '-29.912345|-51.187654|1|manual', 'ponto ajustado à mão fica salvo');
    sql(`update clientes set endereco=concat(endereco,' fundos') where id=${c3}`);
    await Promise.all([a.waitForNavigation(), a.click('[data-google-localizar]')]);
    ok(sql(`select lat from clientes where id=${c3}`) === '-29.912345', 'o automático não mexe no ponto ajustado à mão');
    const fora = await v.request.post(BASE + `/carteira/clientes/${c3}/localizacao`, { form: { lat: '48.85', lng: '2.35', _csrf: await v.locator('meta[name=csrf]').getAttribute('content') }, maxRedirects: 0 });
    ok(fora.status() === 302 && sql(`select lat from clientes where id=${c3}`) === '-29.912345', 'ponto fora do Brasil é recusado');
    ok(sql(`select count(*) from auditoria where acao='localizacao' and entidade_id=${c3}`) >= '1', 'auditoria registra o ajuste');
    const r403 = await v.request.post(BASE + '/administracao/google-maps', { form: { chave: 'AIzaInvasorInvasor123456789', _csrf: await v.locator('meta[name=csrf]').getAttribute('content') } });
    ok(r403.status() === 403 && !fs.readFileSync(SEG, 'utf8').includes('Invasor'), 'vendedora não troca a chave do Google (403)');

    const erros = paginas.flatMap((p) => p.erros);
    ok(erros.length === 0, 'sem erros de JavaScript' + (erros.length ? ': ' + erros[0] : ''));
  } finally {
    await b.close(); simulado.kill();
    if (antes === null) { try { fs.unlinkSync(SEG); } catch { /* ok */ } } else fs.writeFileSync(SEG, antes);
  }
  process.exit(falhas ? 1 : 0);
})();
